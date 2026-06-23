<?php

namespace App\Filament\Provider\Pages;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\IntakeRequest;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Read-only case detail for a clinician, reached from the provider home case list.
 * Routed at /provider/{tenant}/cases/{intakeRequest}; never in the sidebar. Scoped to
 * the signed-in provider — a case not assigned to them 403s.
 */
class ViewCase extends Page
{
    protected static string $routePath = '/cases/{intakeRequest}';

    protected string $view = 'filament.provider.pages.view-case';

    public IntakeRequest $record;

    public static function getRoutePath(Panel $panel): string
    {
        return static::$routePath;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(int|string $intakeRequest): void
    {
        $tenant = Filament::getTenant();
        $provider = auth()->user()?->providerForTenant($tenant->id);

        abort_if($provider === null, 403);

        $intake = IntakeRequest::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey((int) $intakeRequest)
            ->with(['subject', 'discipline', 'referralSource'])
            ->first();

        abort_if($intake === null, 404);

        // Must be assigned to THIS provider (non-cancelled). Compared in SQL to avoid
        // the dblib/sqlsrv string-vs-int strict-compare pitfall.
        $assignedToProvider = $intake->assignments()
            ->where('provider_id', $provider->id)
            ->where('status', '!=', Assignment::STATUS_CANCELLED)
            ->exists();

        abort_unless($assignedToProvider, 403);

        $this->record = $intake;
    }

    public function getTitle(): string|Htmlable
    {
        return trim("{$this->record->subject?->first_name} {$this->record->subject?->last_name}") ?: __('Case');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return collect([$this->record->discipline?->name, $this->record->referralSource?->name])
            ->filter()
            ->implode(' · ') ?: null;
    }

    public function caseInfolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->record)
            ->components([
                Section::make(__('Status'))
                    ->schema([
                        TextEntry::make('status')
                            ->hiddenLabel()
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => IntakeRequestResource::statusOptions()[$state] ?? str($state)->headline())
                            ->color(fn (string $state): string => IntakeRequestResource::statusColor($state)),
                    ]),
                Section::make(__('Details'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('evaluation_date')->label(__('Evaluation Date'))->date()->placeholder('—'),
                        TextEntry::make('start_date')
                            ->label(__('Proposed Start'))
                            ->date()
                            ->hidden(fn (IntakeRequest $record): bool => blank($record->start_date)),
                        TextEntry::make('frequency')
                            ->label(__('Frequency'))
                            ->hidden(fn (IntakeRequest $record): bool => blank($record->frequency)),
                        TextEntry::make('visit_type')
                            ->label(__('Visit Type'))
                            ->hidden(fn (IntakeRequest $record): bool => blank($record->visit_type)),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->columnSpanFull()
                            ->hidden(fn (IntakeRequest $record): bool => blank($record->notes)),
                    ]),
            ]);
    }
}
