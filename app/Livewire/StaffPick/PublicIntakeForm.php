<?php

namespace App\Livewire\StaffPick;

use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\InsuranceType;
use App\Models\StaffPick\ReferralSource;
use App\Services\StaffPick\GeocodingService;
use App\Services\StaffPick\IntakeSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Public, no-login intake submission form for referral sources. The opaque token
 * in the URL resolves the referral source (and through it the tenant); there is
 * no Filament tenant in a public request, so BelongsToTenant scopes are no-ops
 * and we scope queries by the resolved source's tenant_id explicitly.
 *
 * Thin UI over {@see IntakeSubmissionService}.
 */
#[Layout('components.layouts.intake')]
class PublicIntakeForm extends Component
{
    #[Locked]
    public string $token = '';

    #[Locked]
    public ?int $sourceId = null;

    #[Locked]
    public ?int $tenantId = null;

    public string $sourceName = '';

    public bool $inactive = false;

    public bool $submitted = false;

    public ?string $referenceNumber = null;

    public mixed $recaptcha = null;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(string $token): void
    {
        $source = ReferralSource::withoutGlobalScopes()->where('intake_token', $token)->first();

        abort_if($source === null, 404);

        $this->token = $token;
        $this->sourceId = $source->id;
        $this->tenantId = $source->tenant_id;
        $this->sourceName = $source->name;
        $this->inactive = ! $source->isActive();
    }

    /**
     * @return array<int|string, string>
     */
    public function disciplineOptions(): array
    {
        return Discipline::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function insuranceTypeOptions(): array
    {
        return InsuranceType::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['data.address', 'data.city', 'data.state', 'data.zip'], true)) {
            $this->geocode();
        }
    }

    /**
     * Geocode the entered address and flag failures so the form can show the
     * pin-drop map for correction without blocking submission.
     */
    public function geocode(): void
    {
        if (blank($this->data['address'] ?? null) || blank($this->data['city'] ?? null) || blank($this->data['state'] ?? null)) {
            return;
        }

        $result = app(GeocodingService::class)->geocode(
            collect([$this->data['address'] ?? null, $this->data['city'] ?? null, $this->data['state'] ?? null, $this->data['zip'] ?? null])
                ->filter()
                ->implode(', ')
        );

        $this->data['latitude'] = $result['lat'] ?? null;
        $this->data['longitude'] = $result['lng'] ?? null;
        $this->data['geocode_failed'] = $result === null;
    }

    public function submit(): void
    {
        $this->validate();

        if (config('app.recaptcha_enabled')) {
            Validator::make(
                [recaptchaFieldName() => $this->recaptcha],
                [recaptchaFieldName() => recaptchaRuleName()],
            )->validate();
        }

        $source = ReferralSource::withoutGlobalScopes()->find($this->sourceId);

        abort_if($source === null || ! $source->isActive(), 404);

        $intake = app(IntakeSubmissionService::class)->submit($source, $this->data);

        $this->submitted = true;
        $this->referenceNumber = $intake->reference_number;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'data.first_name' => ['required', 'string', 'max:255'],
            'data.last_name' => ['required', 'string', 'max:255'],
            'data.email' => ['nullable', 'email', 'max:255'],
            'data.phone' => ['nullable', 'string', 'max:30'],
            'data.phone_alt' => ['nullable', 'string', 'max:30'],
            'data.alt_contact_name' => ['nullable', 'string', 'max:255'],
            'data.alt_contact_phone' => ['nullable', 'string', 'max:30'],
            'data.alt_contact_relationship' => ['nullable', 'string', 'max:255'],
            'data.date_of_birth' => ['nullable', 'date'],
            'data.gender' => ['nullable', 'string', 'max:30'],
            'data.address' => ['required', 'string', 'max:255'],
            'data.address_2' => ['nullable', 'string', 'max:255'],
            'data.city' => ['required', 'string', 'max:255'],
            'data.state' => ['required', 'string', 'max:10'],
            'data.zip' => ['nullable', 'string', 'max:20'],
            'data.latitude' => ['nullable', 'numeric'],
            'data.longitude' => ['nullable', 'numeric'],
            'data.preferred_language' => ['nullable', 'string', 'max:255'],
            'data.diagnosis' => ['nullable', 'string', 'max:2000'],
            'data.pcp_name' => ['nullable', 'string', 'max:255'],
            'data.pcp_phone' => ['nullable', 'string', 'max:30'],
            'data.insurance_type_id' => ['nullable', 'integer'],
            'data.insurance_id' => ['nullable', 'string', 'max:255'],
            'data.insurance_group' => ['nullable', 'string', 'max:255'],
            'data.provider_gender_preference' => ['nullable', 'string', 'max:30'],
            'data.language_preference' => ['nullable', 'string', 'max:255'],
            'data.discipline_id' => ['required', 'integer'],
            'data.visit_type' => ['nullable', 'string', 'max:255'],
            'data.frequency' => ['nullable', 'string', 'max:255'],
            'data.start_date' => ['nullable', 'date'],
            'data.end_date' => ['nullable', 'date'],
            'data.visits_authorized' => ['nullable', 'integer', 'min:0'],
            'data.authorization_number' => ['nullable', 'string', 'max:255'],
            'data.radius_miles' => ['nullable', 'integer', 'min:0'],
            'data.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function render(): View
    {
        return view('livewire.staffpick.public-intake-form');
    }
}
