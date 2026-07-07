<?php

namespace Tests\Feature\StaffPick;

use App\Constants\TenancyPermissionConstants;
use App\Livewire\StaffPick\ManageProviderPhoto;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderPhoto;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantPermissionService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

class ProviderPhotoTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        Filament::setCurrentPanel(Filament::getPanel('dashboard'));
        Filament::setTenant($this->tenant, isQuiet: true);
    }

    private function userWithSpRole(string $role): User
    {
        $user = $this->createUser($this->tenant);
        app(TenantPermissionService::class)->assignTenantUserRoles($this->tenant, $user, [$role]);

        return $user;
    }

    private function photo(Provider $provider, string $bytes = 'JPEG-BYTES'): ProviderPhoto
    {
        $photo = ProviderPhoto::updateOrCreate(
            ['provider_id' => $provider->id],
            ['mime_type' => 'image/jpeg', 'file_size' => strlen($bytes), 'updated_by_user_id' => $this->createUser($this->tenant)->id],
        );
        $photo->storeContent($bytes);

        return $photo;
    }

    private function realJpeg(int $w = 1200, int $h = 900): string
    {
        $gd = imagecreatetruecolor($w, $h);
        imagefilledrectangle($gd, 0, 0, $w, $h, imagecolorallocate($gd, 30, 120, 200));
        ob_start();
        imagejpeg($gd, null, 92);

        return (string) ob_get_clean();
    }

    public function test_upload_thumbnails_a_large_image(): void
    {
        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF));
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);
        $big = $this->realJpeg(1200, 900);

        Livewire::test(ManageProviderPhoto::class, ['providerId' => $provider->id])
            ->set('upload', UploadedFile::fake()->createWithContent('big.jpg', $big))
            ->call('save')
            ->assertHasNoErrors();

        $photo = ProviderPhoto::where('provider_id', $provider->id)->first();
        $stored = $photo->readContent();
        [$w, $h] = getimagesizefromstring($stored);
        $this->assertLessThanOrEqual(512, max($w, $h), 'stored image should be downsized to the avatar cap');
        $this->assertLessThan(strlen($big), strlen($stored), 'thumbnail should be smaller than the upload');
        $this->assertSame('image/jpeg', $photo->mime_type);
    }

    public function test_provider_photo_accessor_resolves_to_the_relation(): void
    {
        // Regression guard: a legacy `photo` column once shadowed the photo() relation, so
        // $provider->photo returned the (null) column and photos never rendered on the card
        // or profile. The accessor must resolve to the ProviderPhoto relation.
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->photo($provider);

        $fresh = $provider->fresh();
        $this->assertTrue($fresh->hasPhoto());
        $this->assertInstanceOf(ProviderPhoto::class, $fresh->photo);
    }

    public function test_photo_access_gate_by_role_and_ownership(): void
    {
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertTrue($provider->isPhotoAccessibleBy($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF)));
        $this->assertTrue($provider->isPhotoAccessibleBy($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_HR)));
        $this->assertFalse($provider->isPhotoAccessibleBy($this->createUser($this->tenant)));

        // A provider may act on their OWN record (the self-service dimension).
        $owner = $this->createUser($this->tenant);
        $ownProvider = Provider::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $owner->id]);
        $this->assertTrue($ownProvider->isPhotoAccessibleBy($owner));
    }

    public function test_staff_uploads_a_photo(): void
    {
        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF));
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(ManageProviderPhoto::class, ['providerId' => $provider->id])
            ->set('upload', UploadedFile::fake()->createWithContent('me.jpg', 'REAL-JPEG-BYTES'))
            ->call('save')
            ->assertHasNoErrors();

        $photo = ProviderPhoto::where('provider_id', $provider->id)->first();
        $this->assertNotNull($photo);
        $this->assertSame('image/jpeg', $photo->mime_type);
        $this->assertTrue($photo->hasContent());
    }

    public function test_upload_replaces_in_place(): void
    {
        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_ADMIN));
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(ManageProviderPhoto::class, ['providerId' => $provider->id])
            ->set('upload', UploadedFile::fake()->createWithContent('first.jpg', 'FIRST'))
            ->call('save')->assertHasNoErrors();
        $firstId = ProviderPhoto::where('provider_id', $provider->id)->value('id');

        Livewire::test(ManageProviderPhoto::class, ['providerId' => $provider->id])
            ->set('upload', UploadedFile::fake()->createWithContent('second.png', 'SECOND'))
            ->call('save')->assertHasNoErrors();

        $this->assertSame(1, ProviderPhoto::where('provider_id', $provider->id)->count());
        $photo = ProviderPhoto::where('provider_id', $provider->id)->first();
        $this->assertSame($firstId, $photo->id, 'replace overwrites the same row');
        $this->assertSame('image/png', $photo->mime_type);
    }

    public function test_upload_rejects_a_non_image(): void
    {
        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF));
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);

        Livewire::test(ManageProviderPhoto::class, ['providerId' => $provider->id])
            ->set('upload', UploadedFile::fake()->createWithContent('resume.pdf', 'x'))
            ->call('save')
            ->assertHasErrors('upload');

        $this->assertSame(0, ProviderPhoto::where('provider_id', $provider->id)->count());
    }

    public function test_photo_route_serves_bytes_with_cache_headers(): void
    {
        $this->withExceptionHandling();
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->photo($provider, 'JPEG-BYTES');

        $response = $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF))
            ->get(route('staffpick.provider-photos.show', ['provider' => $provider->id]));

        $response->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->assertStringContainsString('max-age', (string) $response->headers->get('Cache-Control'));
        $this->assertNotNull($response->headers->get('ETag'));
        $this->assertSame('JPEG-BYTES', $response->getContent());
    }

    public function test_photo_route_returns_304_on_matching_etag(): void
    {
        $this->withExceptionHandling();
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->photo($provider);
        $staff = $this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF);

        $etag = $this->actingAs($staff)
            ->get(route('staffpick.provider-photos.show', ['provider' => $provider->id]))
            ->headers->get('ETag');

        $this->actingAs($staff)
            ->withHeaders(['If-None-Match' => $etag])
            ->get(route('staffpick.provider-photos.show', ['provider' => $provider->id]))
            ->assertStatus(304);
    }

    public function test_photo_route_is_forbidden_without_access(): void
    {
        $this->withExceptionHandling();
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->photo($provider);

        $this->actingAs($this->createUser($this->tenant))
            ->get(route('staffpick.provider-photos.show', ['provider' => $provider->id]))
            ->assertForbidden();
    }

    public function test_photo_route_404_when_no_photo(): void
    {
        $this->withExceptionHandling();
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->userWithSpRole(TenancyPermissionConstants::ROLE_SP_STAFF))
            ->get(route('staffpick.provider-photos.show', ['provider' => $provider->id]))
            ->assertNotFound();
    }

    public function test_provider_can_upload_their_own_photo(): void
    {
        $owner = $this->createUser($this->tenant);
        $provider = Provider::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $owner->id]);

        $this->actingAs($owner);
        Livewire::test(ManageProviderPhoto::class, ['providerId' => $provider->id])
            ->set('upload', UploadedFile::fake()->createWithContent('self.png', 'SELF'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(ProviderPhoto::where('provider_id', $provider->id)->exists());
    }
}
