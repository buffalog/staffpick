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
 * Idempotent and wired into DatabaseSeeder, so it runs on every deploy: run() seeds
 * every existing tenant, and staffpick:setup-tenant calls seedForTenant() for a tenant
 * it just provisioned. Rows are keyed on (tenant_id, name). Disciplines/tiers/reasons
 * refresh to their code defaults; credential types only seed their admin-tunable fields
 * (requiredness, expiry, scheduler visibility, active) on FIRST creation, so per-tenant
 * edits in the Credentialing Policies UI survive the re-seed.
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
     * Priority 1 = best tier (longest response window, highest matching rank). Windows
     * map to priority, not name, so scoring derives tier_rank from priority alone.
     *
     * @var list<array{name: string, priority: int, color: string, response_window_minutes: int}>
     */
    private const PROVIDER_TIERS = [
        ['name' => 'Platinum', 'priority' => 1, 'color' => '#E5E4E2', 'response_window_minutes' => 120],
        ['name' => 'Gold', 'priority' => 2, 'color' => '#D4AF37', 'response_window_minutes' => 60],
        ['name' => 'Silver', 'priority' => 3, 'color' => '#C0C0C0', 'response_window_minutes' => 45],
        ['name' => 'Bronze', 'priority' => 4, 'color' => '#CD7F32', 'response_window_minutes' => 30],
    ];

    /**
     * Demo-taxonomy names being folded into their canonical CliniConnects labels. On
     * re-seed the old type is retired (deactivated) and any credentials on it are moved
     * to the canonical type. old name => canonical name.
     *
     * @var array<string, string>
     */
    private const CREDENTIAL_TYPE_FOLDS = [
        'CPR Certification' => 'CPR/BLS',
        'Liability Insurance' => 'Liability/Malpractice Insurance',
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
            'Orthopedic Rehabilitation',
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
        $tenants = Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->command?->warn('TenantTaxonomySeeder: no tenants found — skipping.');

            return;
        }

        // Seed every existing tenant (new tenants are seeded on provision via
        // SetupTenant::seedForTenant). Idempotent, so safe to re-run on each deploy.
        foreach ($tenants as $tenant) {
            $this->seedForTenant($tenant);
        }

        // Demo convenience applies to the fcts showcase tenant only.
        $fcts = $tenants->firstWhere('uuid', self::DEFAULT_TENANT_UUID);
        if ($fcts !== null) {
            $this->relaxDemoCredentialRequirements($fcts->getKey());
        }

        $this->command?->info('Seeded default taxonomy for '.$tenants->count().' tenant(s).');
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
                    'response_window_minutes' => $tier['response_window_minutes'],
                    'is_active' => true,
                ],
            );
        }

        foreach ($this->credentialDocumentTypes() as $documentType) {
            $type = CredentialDocumentType::firstOrNew(
                ['tenant_id' => $tenantId, 'name' => $documentType['name']],
            );

            // Admin-tunable fields (requiredness, expiry, scheduler visibility, active) are
            // seeded ONLY when the type is first created, so per-tenant edits made in the
            // Credentialing Policies UI survive the every-deploy re-seed. Verification wiring
            // is code-owned and kept current on every run.
            if (! $type->exists) {
                $type->is_required = $documentType['is_required'];
                $type->has_expiry = $documentType['has_expiry'];
                $type->expiry_warning_days = $documentType['expiry_warning_days'];
                $type->deactivate_on_expiry = $documentType['deactivate_on_expiry'];
                $type->visible_to_scheduler = $documentType['visible_to_scheduler'];
                $type->is_active = true;
            }

            $type->verification_method = $documentType['verification_method'];
            $type->api_discipline = $documentType['api_discipline'];
            $type->rapidapi_host = $documentType['rapidapi_host'];
            $type->deep_link_url_template = $documentType['deep_link_url_template'];

            $type->save();
        }

        // Move any credentials still linked to the pre-split single "State License" type
        // onto the correct per-discipline type before retiring it.
        $this->repointLegacyStateLicenseCredentials($tenantId);

        // Fold the demo-name credential types (CPR Certification, Liability Insurance)
        // into their canonical CliniConnects labels, moving any linked credentials.
        $this->foldRenamedCredentialTypes($tenantId);

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
     * The canonical credential-type taxonomy: the three verification-wired professional
     * license types (kept — they ARE the per-discipline license concept) plus the
     * deduplicated CliniConnects HR/clinical document list. visible_to_scheduler=true for
     * clinical/licensing types (sp_staff sees them); false for HR-only documents.
     *
     * has_expiry / expiry_warning_days here are sensible starting points; each type is
     * editable per-tenant in the Credentialing Policies UI, so no code change is needed
     * to correct one. OIG Search Results and HIPAA / Confidentiality Training are flagged
     * HR-only by request but noted as overridable calls.
     *
     * @return list<array{name: string, is_required: bool, has_expiry: bool, expiry_warning_days: int, deactivate_on_expiry: bool, visible_to_scheduler: bool, verification_method: string, api_discipline: ?string, rapidapi_host: ?string, deep_link_url_template: ?string}>
     */
    private function credentialDocumentTypes(): array
    {
        // A plain manual (non-verification) document type. warn only applies when it expires.
        $manual = fn (string $name, bool $visibleToScheduler, bool $hasExpiry, int $warn = 30): array => [
            'name' => $name,
            'is_required' => false,
            'has_expiry' => $hasExpiry,
            'expiry_warning_days' => $hasExpiry ? $warn : 0,
            'deactivate_on_expiry' => false,
            'visible_to_scheduler' => $visibleToScheduler,
            'verification_method' => 'manual',
            'api_discipline' => null,
            'rapidapi_host' => null,
            'deep_link_url_template' => null,
        ];

        return [
            // Verification-wired per-discipline professional license (clinical => visible).
            ['name' => 'State License (PT)', 'is_required' => true, 'has_expiry' => true, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => true, 'visible_to_scheduler' => true, 'verification_method' => 'api', 'api_discipline' => 'PT', 'rapidapi_host' => 'physical-therapy-license-verification.p.rapidapi.com', 'deep_link_url_template' => null],
            ['name' => 'State License (OT)', 'is_required' => true, 'has_expiry' => true, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => true, 'visible_to_scheduler' => true, 'verification_method' => 'deep_link', 'api_discipline' => 'OT', 'rapidapi_host' => null, 'deep_link_url_template' => 'https://mqa-internet.doh.state.fl.us/MQASearchServices/HealthCareProviders?LicenseNumber={license_number}&BoardCode=OT'],
            ['name' => 'State License (SLP)', 'is_required' => true, 'has_expiry' => true, 'expiry_warning_days' => 30, 'deactivate_on_expiry' => true, 'visible_to_scheduler' => true, 'verification_method' => 'deep_link', 'api_discipline' => 'SLP', 'rapidapi_host' => null, 'deep_link_url_template' => 'https://mqa-internet.doh.state.fl.us/MQASearchServices/HealthCareProviders?LicenseNumber={license_number}&BoardCode=SLP'],

            // Clinical / licensing documents — visible_to_scheduler = true.
            $manual('Competency', true, true),
            $manual('Lymphedema Certificate', true, true, 60),
            $manual('CPR/BLS', true, true),
            $manual('Physical/Health Clearance', true, true),
            $manual('TB Test', true, true),
            $manual('TB Questionnaire', true, true),
            $manual('Chest X-Ray', true, false),
            $manual('Hepatitis B Form', true, false),
            $manual('Flu Shot', true, true),
            $manual('COVID-19 Vaccine', true, false),
            $manual('COVID-19 Exemption Form', true, false),
            $manual('HIV/AIDS Training Certificate', true, false),
            $manual('Domestic Violence CEU', true, false),
            $manual("Alzheimer's Continuing Education", true, false),
            $manual('Prevention of Medical Errors CEU', true, false),
            $manual('Human Trafficking Prevention', true, false),
            $manual('Elder Abuse CEU', true, false),
            $manual('OSHA/Bloodborne Pathogens CEU', true, true),
            $manual('Liability/Malpractice Insurance', true, true),

            // HR-only documents — visible_to_scheduler = false.
            $manual("Driver's License", false, true),
            $manual('Social Security Card', false, false),
            $manual('Auto Insurance', false, true),
            $manual('Vehicle Registration', false, true),
            $manual('FCTS Hire Packet', false, false),
            $manual('Agency Forms', false, false),
            $manual('Activa Orientation Checklist', false, false),
            $manual('Resume', false, false),
            $manual('Rate Sheet', false, false),
            $manual('HR Memo', false, false),
            $manual('Work Comp Exemption Document', false, true, 60),
            $manual('Level 2 Fingerprinting AHCA Affidavit', false, true, 60),
            $manual('Level 2 Fingerprinting AHCA Verification', false, true, 60),
            $manual('Other Licenses', false, true),
            $manual('OIG Search Results', false, false),
            $manual('HIPAA / Confidentiality Training', false, false),

            // Pre-existing extras kept as their own active types (required, HR-only).
            ['name' => 'Background Check', 'is_required' => true, 'has_expiry' => false, 'expiry_warning_days' => 60, 'deactivate_on_expiry' => false, 'visible_to_scheduler' => false, 'verification_method' => 'manual', 'api_discipline' => null, 'rapidapi_host' => null, 'deep_link_url_template' => null],
            ['name' => 'W-9', 'is_required' => true, 'has_expiry' => false, 'expiry_warning_days' => 0, 'deactivate_on_expiry' => false, 'visible_to_scheduler' => false, 'verification_method' => 'manual', 'api_discipline' => null, 'rapidapi_host' => null, 'deep_link_url_template' => null],
        ];
    }

    /**
     * Retire the demo-name credential types (CPR Certification, Liability Insurance) in
     * favour of their canonical CliniConnects labels, moving any linked credentials onto
     * the canonical type first. The (provider_id, document_type_id) unique index means a
     * provider that somehow holds both is de-duplicated: the old row is dropped rather
     * than repointed into a collision. Idempotent — once the old type is gone this no-ops.
     */
    private function foldRenamedCredentialTypes(int $tenantId): void
    {
        foreach (self::CREDENTIAL_TYPE_FOLDS as $oldName => $newName) {
            $old = CredentialDocumentType::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('name', $oldName)
                ->first();

            $new = CredentialDocumentType::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('name', $newName)
                ->first();

            if ($old === null || $new === null) {
                continue;
            }

            foreach (ProviderCredential::where('document_type_id', $old->getKey())->get() as $credential) {
                $collides = ProviderCredential::where('provider_id', $credential->provider_id)
                    ->where('document_type_id', $new->getKey())
                    ->exists();

                if ($collides) {
                    $credential->delete();
                } else {
                    $credential->update(['document_type_id' => $new->getKey()]);
                }
            }

            $old->update(['is_active' => false]);
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
