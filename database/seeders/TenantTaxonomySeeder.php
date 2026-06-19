<?php

namespace Database\Seeders;

use App\Models\StaffPick\CancellationReason;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\DeclineReason;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\OnHoldReason;
use App\Models\StaffPick\ProviderCredential;
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
        ['name' => 'State License (PT)', 'is_required' => true, 'has_expiry' => true, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => true, 'verification_method' => 'api', 'api_discipline' => 'PT', 'rapidapi_host' => 'physical-therapy-license-verification.p.rapidapi.com', 'deep_link_url_template' => null],
        ['name' => 'State License (OT)', 'is_required' => true, 'has_expiry' => true, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => true, 'verification_method' => 'deep_link', 'api_discipline' => 'OT', 'rapidapi_host' => null, 'deep_link_url_template' => 'https://mqa-internet.doh.state.fl.us/MQASearchServices/HealthCareProviders?LicenseNumber={license_number}&BoardCode=OT'],
        ['name' => 'State License (SLP)', 'is_required' => true, 'has_expiry' => true, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => true, 'verification_method' => 'deep_link', 'api_discipline' => 'SLP', 'rapidapi_host' => null, 'deep_link_url_template' => 'https://mqa-internet.doh.state.fl.us/MQASearchServices/HealthCareProviders?LicenseNumber={license_number}&BoardCode=SLP'],
        ['name' => 'CPR Certification', 'is_required' => true, 'has_expiry' => true, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => true, 'verification_method' => 'manual', 'api_discipline' => null, 'rapidapi_host' => null, 'deep_link_url_template' => null],
        ['name' => 'Liability Insurance', 'is_required' => true, 'has_expiry' => true, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => true, 'verification_method' => 'manual', 'api_discipline' => null, 'rapidapi_host' => null, 'deep_link_url_template' => null],
        ['name' => 'Background Check', 'is_required' => true, 'has_expiry' => false, 'expiry_warning_days' => 60, 'deactivate_on_expiry' => false, 'verification_method' => 'manual', 'api_discipline' => null, 'rapidapi_host' => null, 'deep_link_url_template' => null],
        ['name' => 'W-9', 'is_required' => true, 'has_expiry' => false, 'expiry_warning_days' => 0, 'deactivate_on_expiry' => false, 'verification_method' => 'manual', 'api_discipline' => null, 'rapidapi_host' => null, 'deep_link_url_template' => null],
    ];

    /**
     * Specialties scoped to each discipline, keyed by discipline abbreviation. A
     * specialty name shared across disciplines (e.g. Geriatrics) is created once per
     * tenant and mapped to each via the sp_discipline_specialties pivot.
     *
     * @var array<string, list<string>>
     */
    private const SPECIALTIES_BY_DISCIPLINE = [
        'PT' => [
            'Orthopaedics',
            'Geriatrics',
            'Neurology',
            'Pediatrics',
            'Cardiovascular and Pulmonary',
            'Sports',
            "Women's/Pelvic Health",
            'Oncology',
            'Clinical Electrophysiology',
            'Wound Management',
            'Hand Therapy',
            'Performing Arts Physical Therapy',
            'Critical Care',
            'Other (write in)',
        ],
        'OT' => [
            'Gerontology (BCG)',
            'Mental Health (BCMH)',
            'Pediatrics (BCP)',
            'Physical Rehabilitation (BCPR)',
            'Driving and Community Mobility (SCDCM)',
            'Environmental Modification (SCEM)',
            'Feeding, Eating, and Swallowing (SCFES)',
            'Low Vision (SCLV)',
            'School Systems (SCSS)',
            'Certified Hand Therapist (CHT)',
            'Assistive Technology Professional (ATP)',
            'Neonatal Therapist (CNT)',
            'Certified Aging in Place Specialist (CAPS)',
            'Certified Brain Injury Specialist (CBIS)',
            'Other (write in)',
        ],
        'SLP' => [
            'Swallowing Disorders (Dysphagia)',
            'Modified Barium Swallow Impairment Profile (MBSImP)',
            'Child Language & Literacy',
            'Board Certified Specialist in Child Language (BCS-CL)',
            'Augmentative and Alternative Communication (AAC)',
            'Fluency Disorders',
            'Board Certified Specialist in Fluency and Fluency Disorders (BCS-F)',
            'Voice and Resonance',
            'LSVT LOUD',
            'CCC-SLP',
            'PROMPT',
            'PECS',
            'Other (write in)',
        ],
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
        $this->relaxDemoCredentialRequirements($tenant->getKey());
        $this->command?->info("Seeded default taxonomy for tenant '{$tenant->name}'.");
    }

    /**
     * Demo convenience for the 'fcts' showcase tenant ONLY: require just the
     * State License (PT) credential, so the provider onboarding wizard can be
     * submitted (and the live RapidAPI verification demoed) without a wall of
     * unrelated OT/SLP/CPR/insurance/background-check uploads.
     *
     * Applied here in run() — which targets only the default 'fcts' tenant — so the
     * global default (every credential required, per CREDENTIAL_DOCUMENT_TYPES) is
     * left intact for real tenants provisioned via staffpick:setup-tenant. Runs on
     * every db:seed, so it survives redeploys that re-run the base taxonomy.
     */
    private function relaxDemoCredentialRequirements(int $tenantId): void
    {
        CredentialDocumentType::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', '!=', 'State License (PT)')
            ->update(['is_required' => false]);
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
                    'verification_method' => $documentType['verification_method'],
                    'api_discipline' => $documentType['api_discipline'],
                    'rapidapi_host' => $documentType['rapidapi_host'],
                    'deep_link_url_template' => $documentType['deep_link_url_template'],
                ],
            );
        }

        // Move any credentials still linked to the pre-split single "State License" type
        // onto the correct per-discipline type before retiring it.
        $this->repointLegacyStateLicenseCredentials($tenantId);

        // Retire the pre-split single "State License" type (replaced by the per-discipline
        // PT/OT/SLP types). Deactivate rather than delete to preserve any linked credentials.
        CredentialDocumentType::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'State License')
            ->update(['is_active' => false]);

        $this->seedSpecialties($tenantId);

        $this->seedReasons(OnHoldReason::class, $tenantId, self::ON_HOLD_REASONS);
        $this->seedReasons(CancellationReason::class, $tenantId, self::CANCELLATION_REASONS);
        $this->seedReasons(DeclineReason::class, $tenantId, self::DECLINE_REASONS);
    }

    /**
     * Re-point credentials linked to the legacy single "State License" type onto the
     * matching per-discipline type (PT/OT/SLP) based on each provider's discipline.
     *
     * Providers with no discipline, or a discipline outside PT/OT/SLP, are left on the
     * legacy type and logged — we don't guess. Idempotent: once moved, a credential no
     * longer matches the legacy type, so re-running is a no-op for it.
     */
    private function repointLegacyStateLicenseCredentials(int $tenantId): void
    {
        $legacy = CredentialDocumentType::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('name', 'State License')
            ->first();

        if ($legacy === null) {
            return;
        }

        // Discipline abbreviation (PT/OT/SLP) => the new per-discipline State License type.
        $typesByDiscipline = CredentialDocumentType::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('name', ['State License (PT)', 'State License (OT)', 'State License (SLP)'])
            ->get()
            ->keyBy('api_discipline');

        $credentials = ProviderCredential::where('document_type_id', $legacy->getKey())
            ->with('provider.discipline')
            ->get();

        foreach ($credentials as $credential) {
            $abbreviation = $credential->provider?->discipline?->abbreviation;
            $target = $abbreviation !== null ? $typesByDiscipline->get($abbreviation) : null;

            if ($target === null) {
                $this->command?->warn(sprintf(
                    'TenantTaxonomySeeder: credential #%d left on legacy "State License" — provider #%s has %s discipline; no per-discipline type to move it to.',
                    $credential->getKey(),
                    $credential->provider?->getKey() ?? 'null',
                    $abbreviation ?? 'no',
                ));

                continue;
            }

            $credential->update(['document_type_id' => $target->getKey()]);
        }
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
