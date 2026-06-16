<?php

namespace Database\Seeders;

use App\Models\StaffPick\CancellationReason;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\DeclineReason;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\OnHoldReason;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Specialty;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Seeds the default StaffPick taxonomy for a tenant: disciplines, provider tiers,
 * credential document types, and the on-hold / cancellation / decline reason lists.
 *
 * Idempotent — every row is keyed on (tenant_id, name) via updateOrCreate, so the
 * seeder is safe to run repeatedly and on each new tenant bootstrap. Run standalone
 * it targets the default 'fcts' tenant; staffpick:setup-tenant calls seedForTenant()
 * directly for whichever tenant it just provisioned.
 */
class TenantTaxonomySeeder extends Seeder
{
    private const DEFAULT_TENANT_UUID = 'fcts';

    /**
     * @var list<array{name: string, abbreviation: string, sort_order: int}>
     */
    private const DISCIPLINES = [
        ['name' => 'Physical Therapy', 'abbreviation' => 'PT', 'sort_order' => 1],
        ['name' => 'Occupational Therapy', 'abbreviation' => 'OT', 'sort_order' => 2],
        ['name' => 'Speech-Language Pathology', 'abbreviation' => 'SLP', 'sort_order' => 3],
    ];

    /**
     * @var list<array{name: string, priority: int, color: string}>
     */
    private const PROVIDER_TIERS = [
        ['name' => 'Gold', 'priority' => 1, 'color' => '#D4AF37'],
        ['name' => 'Silver', 'priority' => 2, 'color' => '#C0C0C0'],
        ['name' => 'Platinum', 'priority' => 3, 'color' => '#E5E4E2'],
    ];

    /**
     * @var list<array{name: string, is_required: bool, has_expiry: bool, expiry_warning_days: int, deactivate_on_expiry: bool}>
     */
    private const CREDENTIAL_DOCUMENT_TYPES = [
        ['name' => 'State License', 'is_required' => true, 'has_expiry' => true, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => true],
        ['name' => 'CPR Certification', 'is_required' => true, 'has_expiry' => true, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => false],
        ['name' => 'Liability Insurance', 'is_required' => true, 'has_expiry' => true, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => false],
        ['name' => 'Background Check', 'is_required' => true, 'has_expiry' => false, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => false],
        ['name' => 'W-9', 'is_required' => true, 'has_expiry' => false, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => false],
    ];

    /**
     * Specialties scoped to each discipline, keyed by discipline abbreviation. A
     * specialty name shared across disciplines (e.g. Geriatrics) is created once per
     * tenant and mapped to each via the sp_discipline_specialties pivot.
     *
     * @var array<string, list<string>>
     */
    private const SPECIALTIES_BY_DISCIPLINE = [
        'PT' => ['Orthopedics', 'Geriatrics', 'Neurology', 'Pediatrics', 'Sports Medicine', 'Cardiopulmonary', "Women's Health"],
        'OT' => ['Pediatrics', 'Geriatrics', 'Hand Therapy', 'Neurological Rehabilitation', 'Mental Health', 'Low Vision'],
        'SLP' => ['Dysphagia', 'Fluency', 'Voice Disorders', 'Aphasia', 'Pediatric Language', 'Augmentative Communication'],
    ];

    /**
     * @var list<string>
     */
    private const ON_HOLD_REASONS = [
        'Scheduling Conflict',
        'Awaiting Authorization',
        'Patient Unavailable',
        'Provider Unavailable',
    ];

    /**
     * @var list<string>
     */
    private const CANCELLATION_REASONS = [
        'Patient Discharged',
        'No Provider Available',
        'Duplicate Request',
        'Patient Declined',
    ];

    /**
     * @var list<string>
     */
    private const DECLINE_REASONS = [
        'Out of Range',
        'Schedule Full',
        'Not My Specialty',
        'Unavailable',
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->where('uuid', self::DEFAULT_TENANT_UUID)->first();

        if ($tenant === null) {
            $this->command?->warn("TenantTaxonomySeeder: no tenant with uuid '".self::DEFAULT_TENANT_UUID."' found — skipping.");

            return;
        }

        $this->seedForTenant($tenant);
        $this->command?->info("Seeded default taxonomy for tenant '{$tenant->name}'.");
    }

    /**
     * Seed (or refresh) the default taxonomy for a specific tenant.
     */
    public function seedForTenant(Tenant $tenant): void
    {
        $tenantId = $tenant->getKey();

        foreach (self::DISCIPLINES as $discipline) {
            Discipline::updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $discipline['name']],
                [
                    'abbreviation' => $discipline['abbreviation'],
                    'sort_order' => $discipline['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        foreach (self::PROVIDER_TIERS as $tier) {
            ProviderTier::updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $tier['name']],
                [
                    'priority' => $tier['priority'],
                    'color' => $tier['color'],
                    'is_active' => true,
                ],
            );
        }

        foreach (self::CREDENTIAL_DOCUMENT_TYPES as $documentType) {
            CredentialDocumentType::updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $documentType['name']],
                [
                    'is_required' => $documentType['is_required'],
                    'has_expiry' => $documentType['has_expiry'],
                    'expiry_warning_days' => $documentType['expiry_warning_days'],
                    'deactivate_on_expiry' => $documentType['deactivate_on_expiry'],
                    'is_active' => true,
                ],
            );
        }

        $this->seedSpecialties($tenantId);

        $this->seedReasons(OnHoldReason::class, $tenantId, self::ON_HOLD_REASONS);
        $this->seedReasons(CancellationReason::class, $tenantId, self::CANCELLATION_REASONS);
        $this->seedReasons(DeclineReason::class, $tenantId, self::DECLINE_REASONS);
    }

    /**
     * Create the tenant's specialties and map each to its discipline(s) via the
     * sp_discipline_specialties pivot. Idempotent: specialties are keyed on
     * (tenant_id, name) and mappings are attached without detaching existing ones.
     */
    private function seedSpecialties(int $tenantId): void
    {
        foreach (self::SPECIALTIES_BY_DISCIPLINE as $abbreviation => $specialtyNames) {
            $discipline = Discipline::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('abbreviation', $abbreviation)
                ->first();

            if ($discipline === null) {
                continue;
            }

            $specialtyIds = [];

            foreach ($specialtyNames as $name) {
                $specialtyIds[] = Specialty::withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'name' => $name],
                    ['is_active' => true],
                )->getKey();
            }

            $discipline->specialties()->syncWithoutDetaching($specialtyIds);
        }
    }

    /**
     * Upsert a flat list of name-only reason rows for a reason model.
     *
     * @param  class-string<Model>  $model
     * @param  list<string>  $names
     */
    private function seedReasons(string $model, int $tenantId, array $names): void
    {
        foreach ($names as $name) {
            $model::updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                ['is_active' => true],
            );
        }
    }
}
