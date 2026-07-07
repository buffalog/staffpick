<?php

namespace Tests\Feature\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Livewire\StaffPick\ManageCredentialAttachments;
use App\Models\StaffPick\CredentialAttachment;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class CredentialAttachmentTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    private function credential(bool $visibleToScheduler): ProviderCredential
    {
        $type = CredentialDocumentType::create([
            'tenant_id' => $this->tenant->id,
            'name' => $visibleToScheduler ? 'State License (PT)' : "Driver's License",
            'verification_method' => 'manual',
            'visible_to_scheduler' => $visibleToScheduler,
        ]);

        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);

        return ProviderCredential::create([
            'provider_id' => $provider->id,
            'document_type_id' => $type->id,
            'status' => 'valid',
            'verification_status' => ProviderCredential::VERIFICATION_UNVERIFIED,
        ]);
    }

    private function userWithSpRole(string $role): User
    {
        $user = $this->createUser($this->tenant);
        app(TenantPermissionService::class)->assignTenantUserRoles($this->tenant, $user, [$role]);

        return $user;
    }

    private function attachment(ProviderCredential $credential, array $attrs = []): CredentialAttachment
    {
        $attachment = $credential->attachments()->create(array_merge([
            'original_filename' => 'license.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 14,
            'uploaded_by_user_id' => $this->createUser($this->tenant)->id,
            'uploaded_at' => now(),
        ], $attrs));

        $attachment->storeContent('the-file-bytes');

        return $attachment;
    }

    public function test_access_gate_follows_visible_to_scheduler_per_role(): void
    {
        $visible = $this->credential(true);
        $hrOnly = $this->credential(false);

        $staff = $this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF);
        $hr = $this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_HR);
        $plain = $this->createUser($this->tenant);

        $this->assertTrue($visible->isAccessibleBy($staff));
        $this->assertFalse($hrOnly->isAccessibleBy($staff));
        $this->assertTrue($hrOnly->isAccessibleBy($hr));
        $this->assertFalse($visible->isAccessibleBy($plain));
    }

    public function test_sp_staff_uploads_a_proof_file_for_a_visible_credential(): void
    {
        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF));
        $credential = $this->credential(true);

        // createWithContent (not create) so ->get() returns real bytes — a plain fake()
        // ->create() reports a size but writes no content, so storeContent would persist
        // zero bytes.
        Livewire::test(ManageCredentialAttachments::class, ['credentialId' => $credential->id])
            ->set('upload', UploadedFile::fake()->createWithContent('proof.pdf', 'fake-pdf-bytes'))
            ->call('save')
            ->assertHasNoErrors();

        $attachment = $credential->attachments()->withTrashed()->first();
        $this->assertNotNull($attachment);
        $this->assertSame('proof.pdf', $attachment->original_filename);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertTrue($attachment->hasContent());
    }

    public function test_upload_rejects_a_disallowed_extension(): void
    {
        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF));
        $credential = $this->credential(true);

        Livewire::test(ManageCredentialAttachments::class, ['credentialId' => $credential->id])
            ->set('upload', UploadedFile::fake()->create('malware.exe', 10))
            ->call('save')
            ->assertHasErrors('upload');

        $this->assertSame(0, $credential->attachments()->withTrashed()->count());
    }

    public function test_admin_delete_tombstones_the_attachment(): void
    {
        $admin = $this->createTenantAdmin($this->tenant);
        $this->actingAs($admin);
        $credential = $this->credential(true);
        $attachment = $this->attachment($credential);

        Livewire::test(ManageCredentialAttachments::class, ['credentialId' => $credential->id])
            ->call('delete', $attachment->id);

        $fresh = CredentialAttachment::withTrashed()->find($attachment->id);
        $this->assertNotNull($fresh, 'row must persist as an audit tombstone');
        $this->assertNotNull($fresh->deleted_at);
        $this->assertFalse($fresh->hasContent(), 'BLOB must be cleared to reclaim storage');
        $this->assertSame($admin->id, (int) $fresh->deleted_by_user_id);
        $this->assertSame(0, $credential->attachments()->count(), 'tombstone drops out of the live list/count');
    }

    public function test_delete_control_is_absent_from_the_dom_for_non_admins(): void
    {
        $credential = $this->credential(true);
        $this->attachment($credential);

        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF));
        Livewire::test(ManageCredentialAttachments::class, ['credentialId' => $credential->id])
            ->assertDontSeeHtml('wire:click="delete');

        $this->actingAs($this->createTenantAdmin($this->tenant));
        Livewire::test(ManageCredentialAttachments::class, ['credentialId' => $credential->id])
            ->assertSeeHtml('wire:click="delete');
    }

    public function test_download_streams_content_for_an_authorized_user(): void
    {
        $this->withExceptionHandling();
        $credential = $this->credential(true);
        $attachment = $this->attachment($credential);

        $response = $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF))
            ->get(route('staffpick.credential-attachments.download', $attachment));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        // Proves the hex CONVERT write/read roundtrips the exact bytes through VARBINARY(MAX).
        $this->assertSame('the-file-bytes', $response->getContent());
    }

    public function test_download_is_forbidden_for_a_user_without_access(): void
    {
        $this->withExceptionHandling();
        // HR-only credential; a scheduler must not be able to pull its attachment.
        $credential = $this->credential(false);
        $attachment = $this->attachment($credential);

        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF))
            ->get(route('staffpick.credential-attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_download_of_a_tombstoned_attachment_is_not_found(): void
    {
        $this->withExceptionHandling();
        $credential = $this->credential(true);
        $attachment = $this->attachment($credential);
        $attachment->update(['content' => null, 'deleted_by_user_id' => $this->createUser($this->tenant)->id]);
        $attachment->delete();

        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF))
            ->get(route('staffpick.credential-attachments.download', $attachment))
            ->assertNotFound();
    }
}
