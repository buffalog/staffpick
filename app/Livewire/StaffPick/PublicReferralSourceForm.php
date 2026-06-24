<?php

namespace App\Livewire\StaffPick;

use App\Events\StaffPick\ReferralSourceRegistered;
use App\Models\StaffPick\ReferralSource;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Public, no-login self-registration form for referral sources. The tenant is
 * locked in from the route; there is no Filament tenant in a public request, so
 * the created record is scoped by the locked tenant_id explicitly.
 */
#[Layout('components.layouts.intake')]
class PublicReferralSourceForm extends Component
{
    #[Locked]
    public int $tenantId;

    #[Locked]
    public string $tenantName;

    public bool $submitted = false;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function submit(): void
    {
        // The form posts via Livewire's /livewire/update endpoint, which route
        // throttling doesn't cover — rate-limit the write here per IP.
        $key = 'referral-source-register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $this->addError('data.name', __('Too many submissions. Please try again in a minute.'));

            return;
        }

        RateLimiter::hit($key, decaySeconds: 60);

        $this->validate();

        $source = ReferralSource::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantId,
            'status' => ReferralSource::STATUS_PENDING,
            'name' => $this->data['name'],
            'contact_name' => $this->data['contact_name'],
            'phone' => $this->data['phone'],
            'fax' => $this->data['fax'] ?? null,
            'email' => $this->data['email'],
            'address' => $this->data['address'] ?? null,
            'city' => $this->data['city'] ?? null,
            'state' => $this->data['state'] ?? null,
            'zip' => $this->data['zip'] ?? null,
            'portal_username' => $this->data['portal_username'] ?? null,
        ]);

        ReferralSourceRegistered::dispatch($source);

        $this->submitted = true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'data.name' => ['required', 'string', 'max:255'],
            'data.contact_name' => ['required', 'string', 'max:255'],
            'data.phone' => ['required', 'string', 'max:30'],
            'data.fax' => ['nullable', 'string', 'max:30'],
            'data.email' => ['required', 'email', 'max:255'],
            'data.address' => ['nullable', 'string', 'max:255'],
            'data.city' => ['nullable', 'string', 'max:255'],
            'data.state' => ['nullable', 'string', 'max:10'],
            'data.zip' => ['nullable', 'string', 'max:20'],
            'data.portal_username' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function render(): View
    {
        return view('livewire.staffpick.public-referral-source-form');
    }
}
