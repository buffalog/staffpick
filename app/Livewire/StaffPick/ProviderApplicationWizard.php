<?php

namespace App\Livewire\StaffPick;

use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\ProviderApplication;
use App\Models\StaffPick\Specialty;
use App\Services\StaffPick\GeocodingService;
use App\Services\StaffPick\ProviderApplicationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Public six-step provider self-serve onboarding wizard (guest mode). Mirrors the admin
 * clinician wizard's fields but excludes admin-only ones (tier, payroll/tax id, internal
 * notes, is_active, rating). Auto-saves each step via ProviderApplicationService and is
 * resumable via the application token. Reuses the shared Leaflet map partial for the
 * step-2 pin-drop and step-5 service-zone polygon.
 */
class ProviderApplicationWizard extends Component
{
    use WithFileUploads;

    public const FIRST_STEP = 1;

    public const LAST_STEP = 6;

    #[Locked]
    public int $applicationId;

    public int $step = 1;

    /** @var array<string, mixed> */
    public array $data = [];

    /** Temp uploads keyed by credential document type id. @var array<int, mixed> */
    public array $credentialFiles = [];

    public bool $submitted = false;

    public function mount(string $applicationToken): void
    {
        $application = ProviderApplication::query()
            ->where('application_token', $applicationToken)
            ->firstOrFail();

        // A submitted/approved application can't be edited further.
        $this->submitted = $application->status !== ProviderApplication::STATUS_DRAFT;

        $this->applicationId = $application->id;
        $this->step = max(self::FIRST_STEP, min(self::LAST_STEP, (int) $application->current_step));

        // Seed every key the form (and the entangled Leaflet maps) bind against.
        $this->data = array_merge([
            'first_name' => $application->first_name,
            'last_name' => $application->last_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'street_address' => $application->street_address,
            'city' => $application->city,
            'state' => $application->state,
            'zip' => $application->zip,
            'latitude' => $application->latitude,
            'longitude' => $application->longitude,
            'geocode_failed' => false,
            'discipline_id' => null,
            'gender' => $application->gender,
            'is_contractor' => $application->is_contractor ?? true,
            'preferred_radius' => $application->preferred_radius ?? 15,
            'maximum_radius' => $application->maximum_radius ?? 25,
            'specialty_ids' => $application->specialties ?? [],
            'service_zones' => $application->service_zones ?? [],
        ], $application->step_data ?? []);
    }

    public function application(): ProviderApplication
    {
        return ProviderApplication::findOrFail($this->applicationId);
    }

    private function tenantId(): int
    {
        return $this->application()->tenant_id;
    }

    // ---- reactive option sources ---------------------------------------------

    /** @return array<int|string, string> */
    public function disciplineOptions(): array
    {
        return Discipline::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int|string, string> */
    public function specialtyOptions(): array
    {
        $disciplineId = $this->data['discipline_id'] ?? null;

        if (blank($disciplineId)) {
            return [];
        }

        return Specialty::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->whereHas('disciplines', fn (Builder $query) => $query->whereKey($disciplineId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return Collection<int, CredentialDocumentType> */
    public function credentialTypes()
    {
        return CredentialDocumentType::query()
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function updated(string $name): void
    {
        if ($name === 'data.discipline_id') {
            $this->data['specialty_ids'] = [];
        }
    }

    public function geocode(): void
    {
        if (blank($this->data['street_address'] ?? null) || blank($this->data['city'] ?? null) || blank($this->data['state'] ?? null)) {
            return;
        }

        $result = app(GeocodingService::class)->geocode(
            collect([
                $this->data['street_address'] ?? null,
                $this->data['city'] ?? null,
                $this->data['state'] ?? null,
                $this->data['zip'] ?? null,
            ])->filter()->implode(', ')
        );

        $this->data['latitude'] = $result['lat'] ?? null;
        $this->data['longitude'] = $result['lng'] ?? null;
        $this->data['geocode_failed'] = $result === null;
    }

    // ---- navigation ----------------------------------------------------------

    public function nextStep(): void
    {
        $this->validate($this->rulesForStep($this->step));
        $this->persistStep($this->step);

        if ($this->step < self::LAST_STEP) {
            $this->step++;
        }
    }

    public function previousStep(): void
    {
        if ($this->step > self::FIRST_STEP) {
            $this->step--;
        }
    }

    private function persistStep(int $step): void
    {
        app(ProviderApplicationService::class)->saveStep(
            $this->application(),
            $step,
            $this->columnsForStep($step),
            $this->data,
        );
    }

    public function submit(): void
    {
        $this->validate($this->rulesForStep(self::LAST_STEP));

        $application = $this->application();
        $uploads = $this->storeCredentialUploads($application);

        app(ProviderApplicationService::class)->saveStep(
            $application,
            self::LAST_STEP,
            ['credential_uploads' => $uploads],
            $this->data,
        );

        app(ProviderApplicationService::class)->submit($application->refresh());

        $this->submitted = true;
    }

    /**
     * Move each uploaded credential file into the application's folder and return the
     * stored-file metadata for the credential_uploads column.
     *
     * @return array<int, array{document_type_id: int, document_type: string, path: string, original_name: string}>
     */
    private function storeCredentialUploads(ProviderApplication $application): array
    {
        $existing = $application->credential_uploads ?? [];
        $dir = "provider-applications/{$application->application_token}";
        $types = $this->credentialTypes()->keyBy('id');

        foreach ($this->credentialFiles as $typeId => $file) {
            if ($file === null) {
                continue;
            }

            $path = $file->store($dir);

            $existing[] = [
                'document_type_id' => (int) $typeId,
                'document_type' => $types->get($typeId)?->name ?? __('Other'),
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
            ];
        }

        return $existing;
    }

    // ---- per-step column mapping + validation ---------------------------------

    /** @return array<string, mixed> */
    private function columnsForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'first_name' => $this->data['first_name'] ?? '',
                'last_name' => $this->data['last_name'] ?? '',
                'email' => $this->data['email'] ?? '',
                'phone' => $this->data['phone'] ?? null,
            ],
            2 => [
                'street_address' => $this->data['street_address'] ?? null,
                'city' => $this->data['city'] ?? null,
                'state' => $this->data['state'] ?? null,
                'zip' => $this->data['zip'] ?? null,
                'latitude' => $this->data['latitude'] ?? null,
                'longitude' => $this->data['longitude'] ?? null,
            ],
            3 => [
                'discipline' => $this->disciplineOptions()[$this->data['discipline_id'] ?? null] ?? null,
                'gender' => $this->data['gender'] ?? null,
                'is_contractor' => (bool) ($this->data['is_contractor'] ?? true),
                'preferred_radius' => $this->data['preferred_radius'] ?? null,
                'maximum_radius' => $this->data['maximum_radius'] ?? null,
            ],
            4 => ['specialties' => array_values($this->data['specialty_ids'] ?? [])],
            5 => ['service_zones' => $this->data['service_zones'] ?? []],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function rulesForStep(int $step): array
    {
        return match ($step) {
            1 => [
                'data.first_name' => ['required', 'string', 'max:255'],
                'data.last_name' => ['required', 'string', 'max:255'],
                'data.email' => ['required', 'email', 'max:255'],
                'data.phone' => ['nullable', 'string', 'max:30'],
            ],
            2 => [
                'data.street_address' => ['nullable', 'string', 'max:255'],
                'data.city' => ['nullable', 'string', 'max:255'],
                'data.state' => ['nullable', 'string', 'max:10'],
                'data.zip' => ['nullable', 'string', 'max:20'],
            ],
            3 => [
                'data.discipline_id' => ['required', 'integer'],
                'data.gender' => ['nullable', 'string', 'max:20'],
                'data.preferred_radius' => ['nullable', 'integer', 'min:1', 'max:500'],
                'data.maximum_radius' => ['nullable', 'integer', 'min:1', 'max:500'],
            ],
            4 => [
                'data.specialty_ids' => ['nullable', 'array'],
                'data.specialty_ids.*' => ['integer'],
            ],
            6 => [
                'credentialFiles.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ],
            default => [],
        };
    }

    public function render(): View
    {
        return view('livewire.staffpick.provider-application-wizard', [
            'application' => $this->application(),
        ]);
    }
}
