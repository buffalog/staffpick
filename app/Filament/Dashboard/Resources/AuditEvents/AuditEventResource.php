<?php

namespace App\Filament\Dashboard\Resources\AuditEvents;

use App\Filament\Dashboard\Resources\AuditEvents\Pages\ListAuditEvents;
use App\Filament\Dashboard\Resources\AuditEvents\Pages\ViewAuditEvent;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\AuditEvent;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\IntakeRequestHistory;
use App\Models\StaffPick\Notification;
use App\Models\StaffPick\ProviderSurvey;
use App\Models\StaffPick\Subject;
use App\Models\StaffPick\Visit;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only HIPAA audit-log viewer for the compliance officer (an sp_admin or super-admin).
 * Browse and filter the audit trail, and pull one patient's complete access history via the
 * subject filter. The viewer is authorized by role to see the PHI these rows hold.
 *
 * AuditEvent has NO tenant global scope, so tenant confinement is enforced explicitly in
 * getEloquentQuery(); a super-admin reads across tenants. The resource is hard read-only: no
 * create/edit/delete anywhere, matching the model's own immutability.
 */
class AuditEventResource extends Resource
{
    protected static ?string $model = AuditEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

    // AuditEvent has no tenant() ownership relationship (it is not BelongsToTenant), so opt out
    // of Filament's automatic tenant scoping. Tenant confinement is enforced explicitly in
    // getEloquentQuery() instead (super-admins read across tenants).
    protected static bool $isScopedToTenant = false;

    /** The actions the audit stream records; drives the action filter options. */
    private const ACTIONS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'viewed' => 'Viewed',
        'login' => 'Login',
        'logout' => 'Logout',
        'login_failed' => 'Login failed',
    ];

    /** The models whose writes are audited; drives the auditable-type filter options. */
    private const AUDITABLE_TYPES = [
        Subject::class,
        IntakeRequest::class,
        IntakeRequestHistory::class,
        Assignment::class,
        AssignmentOffer::class,
        ProviderSurvey::class,
        Visit::class,
        Notification::class,
    ];

    public static function canAccess(): bool
    {
        return SpRoleAccess::isAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return SpRoleAccess::isAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Audit Log');
    }

    public static function getModelLabel(): string
    {
        return __('Audit Event');
    }

    /**
     * Tenant-scoped explicitly (no global scope on AuditEvent). A super-admin reads across
     * tenants; everyone else is confined to the current Filament tenant.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()?->is_super_admin) {
            return $query;
        }

        return $query->where('tenant_id', Filament::getTenant()?->id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('When'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('actor_label')
                    ->label(__('Actor'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('action')
                    ->label(__('Action'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::ACTIONS[$state] ?? str($state)->headline())
                    ->color(fn (string $state): string => match ($state) {
                        'deleted', 'login_failed' => 'danger',
                        'created' => 'success',
                        'updated' => 'warning',
                        'viewed' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('auditable_type')
                    ->label(__('Record'))
                    ->formatStateUsing(fn (?string $state): ?string => $state !== null ? class_basename($state) : null)
                    ->placeholder('-'),
                TextColumn::make('auditable_id')
                    ->label(__('Record ID'))
                    ->placeholder('-'),
                TextColumn::make('subject_id')
                    ->label(__('Patient ID'))
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('ip_address')
                    ->label(__('IP'))
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('Action'))
                    ->options(self::ACTIONS),
                SelectFilter::make('auditable_type')
                    ->label(__('Record type'))
                    ->options(collect(self::AUDITABLE_TYPES)->mapWithKeys(fn (string $c): array => [$c => class_basename($c)])->all()),
                Filter::make('subject')
                    ->schema([
                        TextInput::make('subject_id')
                            ->label(__('Patient ID'))
                            ->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['subject_id'] ?? null),
                        fn (Builder $q): Builder => $q->where('subject_id', $data['subject_id']),
                    )),
                Filter::make('occurred_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('From'))
                            ->default(now()->subDays(90)),
                        DatePicker::make('until')
                            ->label(__('Until')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['from'] ?? null), fn (Builder $q): Builder => $q->whereDate('occurred_at', '>=', $data['from']))
                        ->when(filled($data['until'] ?? null), fn (Builder $q): Builder => $q->whereDate('occurred_at', '<=', $data['until']))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Event'))
                ->columns(2)
                ->schema([
                    TextEntry::make('occurred_at')->label(__('When'))->dateTime(),
                    TextEntry::make('action')->label(__('Action'))->badge()
                        ->formatStateUsing(fn (string $state): string => self::ACTIONS[$state] ?? str($state)->headline()),
                    TextEntry::make('actor_label')->label(__('Actor')),
                    TextEntry::make('user_id')->label(__('User ID'))->placeholder('-'),
                    TextEntry::make('tenant_id')->label(__('Tenant ID'))->placeholder('-'),
                    TextEntry::make('ip_address')->label(__('IP address'))->placeholder('-'),
                    TextEntry::make('user_agent')->label(__('User agent'))->placeholder('-')->columnSpanFull(),
                ]),
            Section::make(__('Record'))
                ->columns(2)
                ->schema([
                    TextEntry::make('auditable_type')->label(__('Type'))
                        ->formatStateUsing(fn (?string $state): ?string => $state !== null ? class_basename($state) : null)
                        ->placeholder('-'),
                    TextEntry::make('auditable_id')->label(__('Record ID'))->placeholder('-'),
                    TextEntry::make('subject_id')->label(__('Patient ID'))->placeholder('-'),
                ]),
            Section::make(__('Details'))
                ->schema([
                    TextEntry::make('context')
                        ->label(__('Changes / context'))
                        ->state(fn (AuditEvent $record): array => self::formatContext($record->context))
                        ->listWithLineBreaks()
                        ->placeholder(__('No details.'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Render the context json readably: "field: old -> new" for updates, "field: value" for
     * created/read/auth events.
     *
     * @param  array<string, mixed>|null  $context
     * @return array<int, string>
     */
    private static function formatContext(?array $context): array
    {
        if (empty($context)) {
            return [];
        }

        if (isset($context['changes']) && is_array($context['changes'])) {
            $lines = [];

            foreach ($context['changes'] as $field => $change) {
                if (is_array($change) && array_key_exists('old', $change)) {
                    $lines[] = $field.': '.self::scalar($change['old']).' -> '.self::scalar($change['new']);
                } else {
                    $lines[] = $field.': '.self::scalar($change);
                }
            }

            return $lines;
        }

        return collect($context)->map(fn ($value, $key): string => $key.': '.self::scalar($value))->values()->all();
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditEvents::route('/'),
            'view' => ViewAuditEvent::route('/{record}'),
        ];
    }
}
