<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Constants\UsStates;
use App\Filament\Dashboard\Support\HelpHeaderAction;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\Language;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Specialty;
use App\Models\Tenant;
use App\Services\StaffPick\GeocodingService;
use App\Services\StaffPick\ProviderProfileService;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Self-service clinician onboarding wizard. A clinician builds their own Provider
 * profile across six steps and submits it for admin review. Thin UI over
 * {@see ProviderProfileService}; lives at /dashboard/{tenant}/providers/profile.
 */
class ProviderProfile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $slug = 'providers/profile';

    // Within "My Account": before My Offers (sort 2).
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.dashboard.pages.provider-profile';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /** 1-based wizard step to resume on, restored from the saved draft. */
    public ?int $resumeStep = null;

    public function getTitle(): string|Htmlable
    {
        return __('My Provider Profile');
    }

    public static function getNavigationLabel(): string
    {
        return __('My Provider Profile');
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function canAccess(): bool
    {
        return static::isVisibleToCurrentUser();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * "My Provider Profile" is a clinician's own page. Show it to any non-admin tenant
     * member (clinicians — including those who haven't onboarded yet, since this page
     * IS the onboarding wizard) and to admins who are also clinicians (i.e. have a
     * provider record linked to them). Hide it from admin/scheduler-only users who have
     * no provider record.
     */
    protected static function isVisibleToCurrentUser(): bool
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        if (! $tenant instanceof Tenant || $user === null) {
            return false;
        }

        $isAdmin = in_array(
            TenancyPermissionConstants::ROLE_ADMIN,
            app(TenantPermissionService::class)->getTenantUserRoles($tenant, $user),
            true,
        );

        if (! $isAdmin) {
            return true;
        }

        // Admin who is also a clinician — only when a provider record is linked to them.
        return Provider::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->getKey())
            ->exists();
    }

    public function mount(): void
    {
        $provider = $this->currentProvider();

        $this->resumeStep = $provider?->onboarding_step;

        $this->form->fill($provider ? $this->stateFromProvider($provider) : []);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    $this->personalStep(),
                    $this->professionalStep(),
                    $this->serviceAreaStep(),
                    $this->availabilityStep(),
                    $this->credentialsStep(),
                    $this->reviewStep(),
                ])
                    ->startOnStep(fn (): int => $this->resumeStep ?? 1)
                    ->persistStepInQueryString('step')
                    ->submitAction(new HtmlString(Blade::render(
                        '<x-filament::button type="submit" wire:loading.attr="disabled">{{ __("Submit for review") }}</x-filament::button>'
                    ))),
            ]);
    }

    public function submit(): void
    {
        $state = $this->form->getState();
        $state['credentials'] = $this->normalizeCredentialState($state['credentials'] ?? []);

        app(ProviderProfileService::class)->submit(Filament::getTenant(), auth()->user(), $state);

        Notification::make()
            ->title(__('Profile submitted for review'))
            ->body(__('An administrator will review your application shortly.'))
            ->success()
            ->send();

        $this->redirect(static::getUrl());
    }

    /**
     * Persist the wizard's current (possibly incomplete) state as a draft. Called
     * debounced from the front end on every change and immediately on credential
     * upload, so the clinician never loses progress. No validation runs — the draft
     * captures whatever has been entered so far.
     */
    public function autoSave(?int $step = null): void
    {
        $state = $this->data;
        $state['credentials'] = $this->normalizeCredentialState($state['credentials'] ?? []);

        app(ProviderProfileService::class)->saveDraft(Filament::getTenant(), auth()->user(), $state, $step);
    }

    private function personalStep(): Step
    {
        return Step::make(__('Personal Information'))
            ->icon(Heroicon::OutlinedIdentification)
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('first_name')->label(__('First name'))->required(),
                    TextInput::make('last_name')->label(__('Last name'))->required(),
                    TextInput::make('email')->label(__('Email'))->email(),
                    TextInput::make('phone')->label(__('Phone'))->tel(),
                    TextInput::make('business_name')->label(__('Business name'))->columnSpanFull(),
                ]),
                Section::make(__('Address'))
                    ->description(__('Your address is geocoded so we can position you for matching.'))
                    ->schema([
                        TextInput::make('address')->label(__('Street address'))->columnSpanFull()
                            ->live(onBlur: true)->afterStateUpdated(fn (Get $get, Set $set) => $this->geocodeAddressState($get, $set)),
                        Grid::make(3)->schema([
                            TextInput::make('city')->label(__('City'))
                                ->live(onBlur: true)->afterStateUpdated(fn (Get $get, Set $set) => $this->geocodeAddressState($get, $set)),
                            Select::make('state')->label(__('State'))
                                ->options(UsStates::options())
                                ->searchable()
                                ->live()->afterStateUpdated(fn (Get $get, Set $set) => $this->geocodeAddressState($get, $set)),
                            TextInput::make('zip')->label(__('ZIP'))->maxLength(20)
                                ->live(onBlur: true)->afterStateUpdated(fn (Get $get, Set $set) => $this->geocodeAddressState($get, $set)),
                        ]),
                        // Explicit trigger: Livewire 4 wire:model.blur (Filament's ->live(onBlur:true))
                        // does not round-trip on blur, so the afterStateUpdated geocode never fires
                        // mid-step. This button geocodes on demand so the pin populates before submit.
                        Actions::make([
                            Action::make('geocode')
                                ->label(__('Find address on map'))
                                ->icon(Heroicon::OutlinedMapPin)
                                ->color('gray')
                                ->action(fn (Get $get, Set $set) => $this->geocodeAddressState($get, $set)),
                        ]),
                        Placeholder::make('geocode_warning')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => $get('geocode_failed') === true)
                            ->content(new HtmlString(
                                '<div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-400">'
                                .e(__("We couldn't confirm this address on the map. Drag the pin below to your exact location — you can still continue, but matching won't be able to place you until your location is set."))
                                .'</div>'
                            )),
                        View::make('filament.forms.leaflet-map')
                            ->viewData([
                                'mode' => 'marker',
                                'latModel' => 'data.latitude',
                                'lngModel' => 'data.longitude',
                                'failedModel' => 'data.geocode_failed',
                            ]),
                        Hidden::make('latitude'),
                        Hidden::make('longitude'),
                        Hidden::make('geocode_failed'),
                    ]),
            ])
            ->afterValidation(function (Get $get, Set $set): void {
                // Final geocode safety net when advancing, unless a blur already resolved it.
                if (filled($get('latitude'))) {
                    return;
                }

                $this->geocodeAddressState($get, $set);
            });
    }

    private function professionalStep(): Step
    {
        return Step::make(__('Professional'))
            ->icon(Heroicon::OutlinedAcademicCap)
            ->schema([
                Grid::make(2)->schema([
                    Select::make('discipline_ids')
                        ->label(__('Discipline'))
                        ->options(fn (): array => Discipline::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable()
                        ->required()
                        ->helperText(__('Select every discipline you are licensed in.'))
                        // Specialties are scoped to the chosen disciplines; clear the selection
                        // (and any write-in) when they change so nothing stale persists.
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('specialties', []);
                            $set('specialty_other_note', null);
                        }),
                    Select::make('gender')
                        ->label(__('Gender'))
                        ->options([
                            'female' => __('Female'),
                            'male' => __('Male'),
                            'non_binary' => __('Non-binary'),
                            'other' => __('Other'),
                        ]),
                    TextInput::make('years_experience')->label(__('Years of experience'))->numeric()->minValue(0)->maxValue(80),
                ]),
                Select::make('specialties')
                    ->label(__('Specialties'))
                    ->multiple()
                    ->options(fn (Get $get): array => blank($get('discipline_ids'))
                        ? []
                        : Specialty::query()
                            ->where('is_active', true)
                            ->whereHas('disciplines', fn (Builder $query) => $query->whereIn('sp_disciplines.id', (array) $get('discipline_ids')))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                    ->disabled(fn (Get $get): bool => blank($get('discipline_ids')))
                    ->helperText(__('Select a discipline first to see its specialties.'))
                    ->live()
                    ->searchable(),
                TextInput::make('specialty_other_note')
                    ->label(__('Other specialty — please specify'))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $this->isOtherSpecialtySelected($get)),
                Select::make('languages')
                    ->label(__('Languages spoken'))
                    ->hint(__('Select all languages you\'re comfortable using with patients. Language match is a scoring factor in our matching engine.'))
                    ->multiple()
                    ->options(fn (): array => Language::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
            ]);
    }

    private function serviceAreaStep(): Step
    {
        return Step::make(__('Service Area'))
            ->icon(Heroicon::OutlinedMapPin)
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('radius_preferred_miles')->label(__('Preferred radius (miles)'))->numeric()->required()->default(15),
                    TextInput::make('radius_max_miles')->label(__('Maximum radius (miles)'))->numeric()->required()->default(25),
                ]),
                Section::make(__('Service zone (optional)'))
                    ->description(__('Optionally outline the area you cover. Draw a polygon on the map below — we use it to refine matching beyond your radius.'))
                    ->schema([
                        TextInput::make('service_zone_name')->label(__('Zone name')),
                        ViewField::make('service_zone_points')
                            ->hiddenLabel()
                            ->helperText(__('Draw the area where you\'re willing to travel for appointments. This is used alongside your radius to find the best matches.'))
                            ->view('filament.forms.leaflet-map')
                            ->viewData([
                                'mode' => 'polygon',
                                'pointsModel' => 'data.service_zone_points',
                                'centerLatModel' => 'data.latitude',
                                'centerLngModel' => 'data.longitude',
                                'radiusModel' => 'data.radius_preferred_miles',
                            ]),
                    ]),
            ]);
    }

    private function availabilityStep(): Step
    {
        return Step::make(__('Availability'))
            ->icon(Heroicon::OutlinedClock)
            ->schema([
                Repeater::make('availability')
                    ->label(__('Weekly availability'))
                    ->schema([
                        Select::make('day_of_week')
                            ->label(__('Day'))
                            ->options([
                                0 => __('Sunday'),
                                1 => __('Monday'),
                                2 => __('Tuesday'),
                                3 => __('Wednesday'),
                                4 => __('Thursday'),
                                5 => __('Friday'),
                                6 => __('Saturday'),
                            ])
                            ->required(),
                        TimePicker::make('start_time')->label(__('Start'))->seconds(false)->required(),
                        TimePicker::make('end_time')->label(__('End'))->seconds(false)->required(),
                    ])
                    ->columns(3)
                    ->addActionLabel(__('Add availability window')),
            ]);
    }

    private function credentialsStep(): Step
    {
        return Step::make(__('Credentials'))
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->schema(fn (): array => $this->credentialFields());
    }

    /**
     * One upload section per active credential document type for this tenant.
     *
     * @return array<int, Component>
     */
    private function credentialFields(): array
    {
        return CredentialDocumentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (CredentialDocumentType $type): Section {
                $fields = [
                    FileUpload::make("credentials.{$type->id}.file_path")
                        ->label(__('Document'))
                        ->directory('staffpick/credentials')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(10240)
                        ->required((bool) $type->is_required)
                        // Credential files persist on upload completion, not on debounce.
                        ->live()
                        ->afterStateUpdated(fn () => $this->autoSave()),
                    TextInput::make("credentials.{$type->id}.document_number")->label(__('Document number')),
                ];

                if (in_array($type->verification_method, [CredentialDocumentType::METHOD_API, CredentialDocumentType::METHOD_DEEP_LINK], true)) {
                    $fields[] = TextInput::make("credentials.{$type->id}.license_number")
                        ->label(__('License number'))
                        ->hint($type->verification_method === CredentialDocumentType::METHOD_API
                            ? __('Your state-issued license number, e.g. PT34980. Required for real-time automated verification against the state licensing board.')
                            : __('Your state-issued license number. Required for staff to verify your license against the state licensing board.'));
                }

                if ($type->has_expiry) {
                    $fields[] = DatePicker::make("credentials.{$type->id}.expires_at")->label(__('Expiry date'));
                }

                return Section::make($type->name)
                    ->description($type->is_required ? __('Required') : __('Optional'))
                    ->schema($fields)
                    ->columns(2);
            })
            ->all();
    }

    private function reviewStep(): Step
    {
        return Step::make(__('Review & Submit'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->schema([
                Section::make(__('Ready to submit'))
                    ->description(__('Review your information using the previous steps. When you submit, your profile is sent to an administrator for review and your status is set to pending.'))
                    ->schema([
                        Toggle::make('confirm')
                            ->label(__('I confirm the information provided is accurate.'))
                            ->accepted()
                            ->required(),
                    ]),
            ]);
    }

    /**
     * Flatten the nested credentials form state into the list the service expects.
     *
     * @param  array<int|string, array<string, mixed>>  $credentials
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCredentialState(array $credentials): array
    {
        return collect($credentials)
            ->map(function (array $credential, $documentTypeId): array {
                $file = $credential['file_path'] ?? null;

                return [
                    'document_type_id' => (int) $documentTypeId,
                    'file_path' => is_array($file) ? Arr::first($file) : $file,
                    'document_number' => $credential['document_number'] ?? null,
                    'license_number' => $credential['license_number'] ?? null,
                    'expires_at' => $credential['expires_at'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Geocode the entered address and flag failures so the wizard can warn the
     * clinician inline without blocking them. Only runs once the core address
     * parts are present, to avoid premature lookups.
     */
    private function geocodeAddressState(Get $get, Set $set): void
    {
        if (blank($get('address')) || blank($get('city')) || blank($get('state'))) {
            return;
        }

        $result = app(GeocodingService::class)->geocode(
            collect([$get('address'), $get('city'), $get('state'), $get('zip')])->filter()->implode(', ')
        );

        $set('latitude', $result['lat'] ?? null);
        $set('longitude', $result['lng'] ?? null);
        $set('geocode_failed', $result === null);
    }

    /**
     * Decode the provider's active service-zone polygon back into the flat
     * {latitude, longitude} list the map and service expect. The stored ring is
     * closed (first point repeated last); drop that duplicate for editing.
     *
     * @return array<int, array{latitude: float, longitude: float}>
     */
    private function serviceZonePointsFromProvider(Provider $provider): array
    {
        $zone = $provider->serviceZones()->where('is_active', true)->first();

        if (! $zone) {
            return [];
        }

        $geojson = json_decode((string) $zone->polygon_geojson, true);
        $ring = $geojson['coordinates'][0] ?? [];

        if (count($ring) > 1 && $ring[0] === end($ring)) {
            array_pop($ring);
        }

        return collect($ring)
            ->map(fn (array $point): array => ['latitude' => (float) $point[1], 'longitude' => (float) $point[0]])
            ->all();
    }

    /**
     * Whether the tenant's "Other (write in)" specialty is among the current selection,
     * which reveals the free-text write-in field.
     */
    private function isOtherSpecialtySelected(Get $get): bool
    {
        $otherId = Specialty::otherId(Filament::getTenant()?->id);

        if ($otherId === null) {
            return false;
        }

        return in_array($otherId, array_map('intval', (array) $get('specialties')), true);
    }

    /** The stored write-in detail on the provider's "Other (write in)" specialty pivot. */
    private function otherSpecialtyNote(Provider $provider): ?string
    {
        $otherId = Specialty::otherId($provider->tenant_id);

        if ($otherId === null) {
            return null;
        }

        return $provider->specialties()->where('sp_specialties.id', $otherId)->first()?->pivot?->notes;
    }

    private function currentProvider(): ?Provider
    {
        return Provider::query()
            ->where('tenant_id', Filament::getTenant()?->id)
            ->where('user_id', auth()->id())
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function stateFromProvider(Provider $provider): array
    {
        return [
            'first_name' => $provider->first_name,
            'last_name' => $provider->last_name,
            'email' => $provider->email,
            'phone' => $provider->phone,
            'business_name' => $provider->business_name,
            'gender' => $provider->gender,
            'address' => $provider->address,
            'city' => $provider->city,
            'state' => $provider->state,
            'zip' => $provider->zip,
            'latitude' => $provider->latitude,
            'longitude' => $provider->longitude,
            'discipline_ids' => $provider->disciplines()->pluck('sp_disciplines.id')->all(),
            'years_experience' => $provider->years_experience,
            'radius_preferred_miles' => $provider->radius_preferred_miles,
            'radius_max_miles' => $provider->radius_max_miles,
            'service_zone_name' => $provider->serviceZones()->where('is_active', true)->value('name'),
            'service_zone_points' => $this->serviceZonePointsFromProvider($provider),
            'specialties' => $provider->specialties()->pluck('sp_specialties.id')->all(),
            'specialty_other_note' => $this->otherSpecialtyNote($provider),
            'languages' => $provider->languages()->pluck('sp_languages.id')->all(),
            'availability' => $provider->availability()
                ->get(['day_of_week', 'start_time', 'end_time'])
                ->map(fn ($a) => ['day_of_week' => $a->day_of_week, 'start_time' => $a->start_time, 'end_time' => $a->end_time])
                ->all(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            HelpHeaderAction::make('clinician/completing-your-profile'),
            $this->managePhotoAction(),
            $this->generateCalendarLinkAction(),
            $this->calendarSubscriptionAction(),
            $this->revokeCalendarLinkAction(),
        ];
    }

    /** Self-service photo upload — a provider may set their own photo (feature two's new
     * permission dimension). Only once a provider record is linked to them. */
    private function managePhotoAction(): Action
    {
        return Action::make('managePhoto')
            ->label(__('Profile photo'))
            ->icon('heroicon-o-camera')
            ->color('gray')
            ->visible(fn (): bool => $this->currentProvider() !== null)
            ->modalHeading(__('Profile photo'))
            ->modalContent(fn () => view('staffpick.providers.photo-modal', ['providerId' => $this->currentProvider()->id]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    /** Visible before a token exists: issue one for the logged-in provider. */
    private function generateCalendarLinkAction(): Action
    {
        return Action::make('generateCalendarLink')
            ->label(__('Generate calendar link'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->visible(fn (): bool => ($p = $this->currentProvider()) !== null && $p->calendar_token === null)
            ->action(function (): void {
                $this->currentProvider()?->generateCalendarToken();

                Notification::make()
                    ->title(__('Calendar link generated'))
                    ->body(__('Subscribe to it in your calendar app to see your scheduled cases.'))
                    ->success()
                    ->send();
            });
    }

    /** Visible once a token exists: show the read-only, copyable feed URL. */
    private function calendarSubscriptionAction(): Action
    {
        return Action::make('calendarSubscription')
            ->label(__('Calendar subscription'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->visible(fn (): bool => ($p = $this->currentProvider()) !== null && $p->calendar_token !== null)
            ->modalHeading(__('Calendar subscription'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->infolist([
                TextEntry::make('calendar_url')
                    ->label(__('Feed URL'))
                    ->state(fn (): ?string => $this->currentProvider()?->calendarFeedUrl())
                    ->copyable()
                    ->helperText(__('Add this as a subscribed calendar in Google Calendar, Apple Calendar, or Outlook.')),
                TextEntry::make('calendar_generated')
                    ->label(__('Generated'))
                    ->state(fn (): ?string => $this->currentProvider()?->calendar_token_generated_at?->diffForHumans())
                    ->placeholder('—'),
            ]);
    }

    /** Visible once a token exists: invalidate the old link and issue a fresh one. */
    private function revokeCalendarLinkAction(): Action
    {
        return Action::make('revokeCalendarLink')
            ->label(__('Revoke & regenerate'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('danger')
            ->visible(fn (): bool => ($p = $this->currentProvider()) !== null && $p->calendar_token !== null)
            ->requiresConfirmation()
            ->modalDescription(__('The current link stops working immediately and a new one is issued. Anyone using the old URL will need the new one.'))
            ->action(function (): void {
                $provider = $this->currentProvider();
                $provider?->revokeCalendarToken();
                $provider?->generateCalendarToken();

                Notification::make()
                    ->title(__('Calendar link regenerated'))
                    ->body(__('The old link no longer works — re-subscribe with the new URL.'))
                    ->success()
                    ->send();
            });
    }
}
