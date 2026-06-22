<?php

namespace App\Filament\Provider\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * A clinician's own caseload in the Provider portal: a calendar of their assigned
 * cases (by evaluation date) plus a clickable case list. Reached via the avatar
 * menu only — never the sidebar. Scoped to the Provider record linked to the
 * signed-in user for the current tenant; shows an empty state if none is linked.
 */
class MyCases extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'my-cases';

    protected string $view = 'filament.provider.pages.my-cases';

    /** @var array<string, ?Provider> */
    protected static array $providerCache = [];

    public function getTitle(): string|Htmlable
    {
        return __('My Cases');
    }

    public static function getNavigationLabel(): string
    {
        return __('My Cases');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
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
            // Sort in SQL — evaluation_date is intentionally uncast, so never read it
            // into PHP for sorting (dblib throws on populated date-cast reads).
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
