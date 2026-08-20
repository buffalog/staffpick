<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Concerns\StoresBinaryContent;
use App\Models\StaffPick\CredentialAttachment;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\StaffPick\ProviderPhoto;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\Feature\FeatureTest;

/**
 * Proves the {@see StoresBinaryContent} hex translation
 * survived the engine swap, byte for byte.
 *
 * The two fixtures are not synthetic. They were pulled out of the live Azure SQL Edge
 * staging database through the SQL Server hex path
 * (CONVERT(VARCHAR(MAX), content, 2)) before it was decommissioned, and verified on
 * extraction by length + md5. Writing them back through the Postgres path
 * (decode(?, 'hex') / encode(content, 'hex') / octet_length(content)) and getting the same
 * bytes and the same md5 is what demonstrates the translation is faithful across engines
 * rather than merely self-consistent.
 *
 * A real PDF and a real JPEG are the point: both are binary formats full of NUL bytes and
 * high bytes, which is exactly what a naive text round-trip would corrupt or truncate.
 */
class BinaryContentRoundTripTest extends FeatureTest
{
    private const FIXTURES = [
        'pdf' => [
            'path' => 'credential_attachment.pdf',
            'bytes' => 253280,
            'md5' => 'a499ee6e1f8783cc3aa4eadbab7c9ff7',
        ],
        'jpeg' => [
            'path' => 'provider_photo.jpg',
            'bytes' => 10722,
            'md5' => '26265b33897382d2821b5be80089059e',
        ],
    ];

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    private function fixture(string $key): string
    {
        $meta = self::FIXTURES[$key];
        $contents = file_get_contents(base_path("tests/Fixtures/Blobs/{$meta['path']}"));

        // Guard the guard: if the fixture itself were truncated by a checkout or an
        // .gitattributes text-mode mangling, every assertion below would compare corrupt
        // bytes to corrupt bytes and pass.
        $this->assertSame($meta['bytes'], strlen($contents), "Fixture {$meta['path']} is the wrong size on disk.");
        $this->assertSame($meta['md5'], md5($contents), "Fixture {$meta['path']} does not match its recorded md5 on disk.");

        return $contents;
    }

    private function credentialAttachment(): CredentialAttachment
    {
        $type = CredentialDocumentType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Round Trip License',
            'verification_method' => 'manual',
        ]);

        $credential = ProviderCredential::create([
            'provider_id' => Provider::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'document_type_id' => $type->id,
            'status' => 'valid',
            'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED,
        ]);

        return $credential->attachments()->create([
            'original_filename' => 'credential_attachment.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => self::FIXTURES['pdf']['bytes'],
            'uploaded_by_user_id' => $this->createUser($this->tenant)->id,
            'uploaded_at' => now(),
        ]);
    }

    public function test_a_real_pdf_survives_the_credential_attachment_round_trip(): void
    {
        $bytes = $this->fixture('pdf');
        $attachment = $this->credentialAttachment();

        $this->assertFalse($attachment->hasContent(), 'A freshly created attachment should hold no bytes.');

        $attachment->storeContent($bytes);

        $this->assertTrue($attachment->hasContent());
        $this->assertSame($bytes, $attachment->readContent(), 'The PDF did not survive the round trip byte for byte.');
        $this->assertSame(self::FIXTURES['pdf']['md5'], md5($attachment->readContent()));
        $this->assertSame(self::FIXTURES['pdf']['bytes'], strlen($attachment->readContent()));
    }

    public function test_a_real_jpeg_survives_the_provider_photo_round_trip(): void
    {
        $bytes = $this->fixture('jpeg');

        $photo = ProviderPhoto::create([
            'provider_id' => Provider::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'mime_type' => 'image/jpeg',
            'file_size' => self::FIXTURES['jpeg']['bytes'],
        ]);

        $photo->storeContent($bytes);

        $this->assertTrue($photo->hasContent());
        $this->assertSame($bytes, $photo->readContent(), 'The JPEG did not survive the round trip byte for byte.');
        $this->assertSame(self::FIXTURES['jpeg']['md5'], md5($photo->readContent()));
    }

    public function test_octet_length_reports_the_true_byte_count_not_the_hex_length(): void
    {
        $bytes = $this->fixture('jpeg');
        $attachment = $this->credentialAttachment();
        $attachment->storeContent($bytes);

        $length = DB::selectOne(
            'SELECT octet_length(content) AS len FROM sp_credential_attachments WHERE id = ?',
            [$attachment->getKey()],
        );

        // The wire format is hex, so a translation that measured the encoded form would
        // report exactly double. octet_length() measures the decoded bytea.
        $this->assertSame(self::FIXTURES['jpeg']['bytes'], (int) $length->len);
    }

    public function test_replacing_and_clearing_content_behaves(): void
    {
        $pdf = $this->fixture('pdf');
        $jpeg = $this->fixture('jpeg');
        $attachment = $this->credentialAttachment();

        $attachment->storeContent($pdf);
        $attachment->storeContent($jpeg);

        $this->assertSame($jpeg, $attachment->readContent(), 'Replace-in-place left bytes of the previous blob behind.');

        $attachment->clearContent();

        $this->assertFalse($attachment->hasContent());
        $this->assertNull($attachment->readContent());
    }

    /**
     * Postgres encode() emits LOWERCASE hex where SQL Server CONVERT style 2 emitted
     * uppercase. hex2bin() accepts either, so the round trip is unaffected — but anything
     * that compared the hex representation itself would silently break. Pin the observed
     * casing so a future change to the encoding has to acknowledge it.
     */
    public function test_the_hex_wire_format_is_lowercase_and_case_insensitively_decodable(): void
    {
        $bytes = $this->fixture('jpeg');
        $attachment = $this->credentialAttachment();
        $attachment->storeContent($bytes);

        $row = DB::selectOne(
            "SELECT encode(content, 'hex') AS hex FROM sp_credential_attachments WHERE id = ?",
            [$attachment->getKey()],
        );

        $this->assertSame(strtolower($row->hex), $row->hex, 'Postgres encode() is expected to emit lowercase hex.');
        $this->assertSame($bytes, hex2bin($row->hex));
        $this->assertSame($bytes, hex2bin(strtoupper($row->hex)), 'hex2bin must decode the uppercase form identically.');
    }
}
