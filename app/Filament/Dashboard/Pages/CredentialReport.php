<?php

namespace App\Filament\Dashboard\Pages;

use App\Filament\Dashboard\Resources\Providers\ProviderResource;
use App\Filament\Dashboard\Support\HelpHeaderAction;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\CredentialDocumentType;
use App\Models\StaffPick\Provider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Credential report (spec section 5): pick a credential type and see every provider in
 * the roster with their current status for it — valid, expiring soon, expired, or not on
 * file. The payoff of the taxonomy cleanup: a type name means the same thing on every row.
 *
 * The type dropdown is scheduler-visibility filtered, so the Scheduler view (sp_staff)
 * can only report on types it is allowed to see.
 */
class CredentialReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $slug = 'credential-report';

    protected string $view = 'filament.dashboard.pages.credential-report';

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * The selected type id, mirrored out of the form into a first-class public property so
     * the table reads it reliably each request (reading the form's statePath array from
     * table() proved lifecycle-flaky — the table rendered before the array hydrated).
     */
    public ?int $documentTypeId = null;

    /** Memoized selected type (per request) so warn-days isn't re-queried per row. */
    protected ?CredentialDocumentType $selectedType = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Credential Report');
    }

    public static function getNavigationLabel(): string
    {
        return __('Credential Report');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Our Providers');
    }

    public static function canAccess(): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_type_id')
                    ->label(__('Credential Type'))
                    ->options(fn (): array => CredentialDocumentType::query()
                        ->where('tenant_id', Filament::getTenant()?->id)
                        ->where('is_active', true)
                        ->visibleToCurrentUser()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state): void {
                        $this->documentTypeId = filled($state) ? (int) $state : null;
                        // The table is built once at boot (before this runs), so rebuild it
                        // with the new type; otherwise it renders one selection stale.
                        $this->resetTable();
                    })
                    ->placeholder(__('Select a credential type…')),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        $typeId = $this->selectedTypeId();

        $query = Provider::query()
            ->where('tenant_id', Filament::getTenant()?->id)
            ->where('is_active', true)
            ->with('discipline');

        if ($typeId !== null) {
            // Only the credential of the selected type — first() below is that one (or none).
            // Eager-load constraint closures receive the Relation (HasMany), not a Builder,
            // so no strict Builder type hint here.
            $query->with(['credentials' => fn ($relation) => $relation->where('document_type_id', $typeId)]);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('Provider'))
                    ->state(fn (Provider $record): string => trim("{$record->first_name} {$record->last_name}"))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name']),
                TextColumn::make('discipline.abbreviation')
                    ->label(__('Discipline'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->state(fn (Provider $record): string => $this->statusFor($record, $typeId))
                    ->formatStateUsing(fn (string $state): string => $this->statusLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        'valid' => 'success',
                        'expiring_soon' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('expires_at')
                    ->label(__('Expires'))
                    ->state(fn (Provider $record) => $record->credentials->first()?->expires_at)
                    ->date()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('report_status')
                    ->label(__('Status'))
                    ->options([
                        'valid' => __('Valid'),
                        'expiring_soon' => __('Expiring soon'),
                        'expired' => __('Expired'),
                        'not_on_file' => __('Not on file'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $this->applyStatusFilter($query, $data['value'] ?? null, $typeId)),
            ])
            ->recordActions([
                Action::make('viewProvider')
                    ->label(__('View'))
                    ->icon(Heroicon::OutlinedUser)
                    ->color('gray')
                    ->url(fn (Provider $record): string => ProviderResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading($typeId === null
                ? __('Select a credential type to run the report.')
                : __('No providers.'));
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            HelpHeaderAction::make('scheduler/credentialing'),
        ];
    }

    private function selectedTypeId(): ?int
    {
        return $this->documentTypeId;
    }

    private function statusFor(Provider $provider, ?int $typeId): string
    {
        if ($typeId === null) {
            return 'not_on_file';
        }

        $credential = $provider->credentials->first();

        if ($credential === null) {
            return 'not_on_file';
        }

        if ($credential->expires_at === null) {
            return 'valid'; // on file, no expiration — present, not an invalid state
        }

        $today = today();

        if ($credential->expires_at->lt($today)) {
            return 'expired';
        }

        if ($credential->expires_at->lte($today->copy()->addDays($this->warnDaysFor($typeId)))) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'valid' => __('Valid'),
            'expiring_soon' => __('Expiring soon'),
            'expired' => __('Expired'),
            default => __('Not on file'),
        };
    }

    private function warnDaysFor(int $typeId): int
    {
        $this->selectedType ??= CredentialDocumentType::find($typeId);

        return (int) ($this->selectedType?->expiry_warning_days ?: 30);
    }

    private function applyStatusFilter(Builder $query, ?string $value, ?int $typeId): Builder
    {
        if (blank($value) || $typeId === null) {
            return $query;
        }

        $today = now()->toDateString();
        $warnDate = now()->addDays($this->warnDaysFor($typeId))->toDateString();
        $ofType = fn (Builder $c): Builder => $c->where('document_type_id', $typeId);

        return match ($value) {
            'not_on_file' => $query->whereDoesntHave('credentials', $ofType),
            'expired' => $query->whereHas('credentials', fn (Builder $c): Builder => $ofType($c)
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '<', $today)),
            'expiring_soon' => $query->whereHas('credentials', fn (Builder $c): Builder => $ofType($c)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$today, $warnDate])),
            'valid' => $query->whereHas('credentials', fn (Builder $c): Builder => $ofType($c)
                ->where(fn (Builder $w): Builder => $w->whereNull('expires_at')->orWhereDate('expires_at', '>', $warnDate))),
            default => $query,
        };
    }
}
