<?php

namespace App\Filament\Provider\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderCredential;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The provider portal landing page: tier, credential alerts, and case counts up top,
 * then the clinician's case list. Takes over the panel's root route from the default
 * Dashboard — slug 'dashboard' + routePath '/' preserve the filament.provider.pages
 * .dashboard route name that the portal switchers and invitation redirects rely on.
 */
class ProviderHome extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'dashboard';

    protected static string $routePath = '/';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected string $view = 'filament.provider.pages.provider-home';

    /** @var array<string, ?Provider> */
    protected static array $providerCache = [];

    public static function getRoutePath(Panel $panel): string
    {
        return static::$routePath;
    }

    public function getTitle(): string|Htmlable
    {
        return __('Home');
    }

    public static function getNavigationLabel(): string
    {
        return __('Home');
    }

    /** The Provider record linked to the current user for the active tenant, if any. */
    public function provider(): ?Provider
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        if (! $tenant instanceof Tenant || $user === null) {
            return null;
        }

        return static::$providerCache[$tenant->id.':'.$user->id]
            ??= $user->providerForTenant($tenant->id);
    }

    /**
     * Tier block: name + a colour token for the view.
     *
     * @return array{name: ?string, color: string}
     */
    public function tierBlock(): array
    {
        $name = $this->provider()?->tier?->name;

        return [
            'name' => $name,
            'color' => match ($name) {
                'Gold' => 'amber',
                'Silver' => 'gray',
                'Platinum' => 'indigo',
                default => 'neutral',
            },
        ];
    }

    /**
     * Credentials that are expired or within their type's warning window.
     *
     * expires_at is a 'date'-cast column, so it's read via getRawOriginal() (raw
     * string) and parsed with Carbon — never through the cast — to stay safe on the
     * dblib toolchain. The per-type window comes from the credential's document type.
     *
     * @return array<int, array{type: string, expires: string, expired: bool}>
     */
    public function credentialAlerts(): array
    {
        $provider = $this->provider();

        if ($provider === null) {
            return [];
        }

        $today = now()->startOfDay();

        return ProviderCredential::query()
            ->where('provider_id', $provider->id)
            ->whereNotNull('expires_at')
            ->with('documentType')
            ->get()
            ->map(function (ProviderCredential $credential) use ($today): ?array {
                $raw = $credential->getRawOriginal('expires_at');

                if (blank($raw)) {
                    return null;
                }

                $expires = Carbon::parse($raw)->startOfDay();
                $warningDays = (int) ($credential->documentType?->expiry_warning_days ?? 0);
                $threshold = $today->copy()->addDays($warningDays);

                // Not yet within the warning window and not expired — no alert.
                if ($expires->gt($threshold)) {
                    return null;
                }

                return [
                    'type' => $credential->documentType?->name ?? __('Credential'),
                    'expires' => $expires->format('M j, Y'),
                    'expired' => $expires->lt($today),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Active vs closed case counts for this provider.
     *
     * @return array{active: int, closed: int}
     */
    public function caseCounts(): array
    {
        $provider = $this->provider();

        if ($provider === null) {
            return ['active' => 0, 'closed' => 0];
        }

        $assigned = fn (): Builder => IntakeRequest::query()
            ->where('tenant_id', Filament::getTenant()?->id)
            ->whereHas('assignments', fn (Builder $sub): Builder => $sub
                ->where('provider_id', $provider->id)
                ->where('status', '!=', Assignment::STATUS_CANCELLED));

        return [
            'active' => $assigned()->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'closed' => $assigned()->where('status', 'completed')->count(),
        ];
    }

    /**
     * The clinician's case list — same shape as the My Cases page. Duplicated here
     * (rather than shared) to keep the already-deployed MyCases page untouched.
     */
    public function table(Table $table): Table
    {
        $provider = $this->provider();

        $query = $provider === null
            ? IntakeRequest::query()->whereRaw('1 = 0')
            : IntakeRequest::query()
                ->where('tenant_id', Filament::getTenant()?->id)
                ->whereHas('assignments', fn (Builder $sub): Builder => $sub
                    ->where('provider_id', $provider->id)
                    ->where('status', '!=', Assignment::STATUS_CANCELLED))
                ->with(['subject', 'discipline', 'referralSource']);

        return $table
            ->query($query)
            ->defaultSort('evaluation_date', 'desc')
            ->columns([
                TextColumn::make('subject_name')
                    ->label(__('Case Name'))
                    ->state(fn (IntakeRequest $record): string => trim("{$record->subject?->first_name} {$record->subject?->last_name}"))
                    ->searchable(['subject.first_name', 'subject.last_name']),
                TextColumn::make('discipline.name')
                    ->label(__('Discipline'))
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => IntakeRequestResource::statusOptions()[$state] ?? str($state)->headline())
                    ->color(fn (string $state): string => IntakeRequestResource::statusColor($state)),
                TextColumn::make('evaluation_date')
                    ->label(__('Evaluation Date'))
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('referralSource.name')
                    ->label(__('Referral Source'))
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('View'))
                    ->modalHeading(fn (IntakeRequest $record): string => trim("{$record->subject?->first_name} {$record->subject?->last_name}") ?: __('Case'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->infolist([
                        TextEntry::make('subject_name')
                            ->label(__('Case Name'))
                            ->state(fn (IntakeRequest $record): string => trim("{$record->subject?->first_name} {$record->subject?->last_name}"))
                            ->placeholder('—'),
                        TextEntry::make('discipline.name')->label(__('Discipline'))->placeholder('—'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => IntakeRequestResource::statusOptions()[$state] ?? str($state)->headline())
                            ->color(fn (string $state): string => IntakeRequestResource::statusColor($state)),
                        TextEntry::make('evaluation_date')->label(__('Evaluation Date'))->date()->placeholder('—'),
                        TextEntry::make('referralSource.name')->label(__('Referral Source'))->placeholder('—'),
                        TextEntry::make('frequency')->label(__('Frequency'))->placeholder('—'),
                        TextEntry::make('visit_type')->label(__('Visit Type'))->placeholder('—'),
                        TextEntry::make('notes')->label(__('Notes'))->placeholder('—')->columnSpanFull(),
                    ]),
            ])
            ->recordAction('view')
            ->emptyStateHeading(__('No cases assigned'))
            ->emptyStateDescription(__('Cases assigned to you will appear here.'));
    }
}
