<?php

namespace App\Filament\Dashboard\Resources\IntakeRequests;

use App\Filament\Dashboard\Resources\IntakeRequests\Pages\AllCases;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\Cases;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\CompletedCases;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\CreateIntakeRequest;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\EditIntakeRequest;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ListIntakeRequests;
use App\Filament\Dashboard\Resources\IntakeRequests\Pages\ViewIntakeRequest;
use App\Filament\Dashboard\Resources\IntakeRequests\Schemas\IntakeRequestForm;
use App\Filament\Dashboard\Resources\IntakeRequests\Schemas\IntakeRequestInfolist;
use App\Filament\Dashboard\Resources\IntakeRequests\Tables\IntakeRequestsTable;
use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\TenantConfig;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class IntakeRequestResource extends Resource
{
    protected static ?string $model = IntakeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'reference_number';

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'draft' => __('Draft'),
            'pending' => __('Pending'),
            'matching' => __('Matching'),
            'offered' => __('Offered'),
            'no_clinicians_available' => __('No clinicians available'),
            'assigned_pending' => __('Assigned (Pending)'),
            'active' => __('Active'),
            'on_hold' => __('On Hold'),
            'completed' => __('Completed'),
            'finished' => __('Finished'),
            'cancelled' => __('Cancelled'),
            'closed' => __('Closed'),
        ];
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'active', 'finished', 'completed' => 'success',
            'matching', 'offered' => 'info',
            'assigned_pending', 'on_hold' => 'warning',
            'cancelled', 'no_clinicians_available' => 'danger',
            default => 'gray',
        };
    }

    public static function form(Schema $schema): Schema
    {
        return IntakeRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IntakeRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IntakeRequestsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            // Eager-load the relationships the table/infolist render — subject (drives
            // the gender/language preference columns + entries) and specialties (the
            // requested-specialties entry) included — to avoid an N+1 across the rows.
            ->with(['subject', 'referralSource', 'discipline', 'assigner', 'specialties'])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntakeRequests::route('/'),       // Pending Cases (dispatch queue)
            'cases' => Cases::route('/cases'),               // active
            'completed-cases' => CompletedCases::route('/completed-cases'),
            'all-cases' => AllCases::route('/all-cases'),
            'create' => CreateIntakeRequest::route('/create'),
            'view' => ViewIntakeRequest::route('/{record}'),
            'edit' => EditIntakeRequest::route('/{record}/edit'),
        ];
    }

    /**
     * Four scoped sidebar entries instead of the resource's single default item.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $group = static::getNavigationGroup();

        $item = fn (string $label, string $page, int $sort): NavigationItem => NavigationItem::make($label)
            ->group($group)
            ->icon(static::getNavigationIcon())
            ->sort($sort)
            ->url(static::getUrl($page))
            ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.'.$page));

        return [
            $item(__('All Cases'), 'all-cases', 1),
            $item(__('Active Cases'), 'cases', 2),
            $item(__('Pending Cases'), 'index', 3),
            $item(__('Discharged Cases'), 'completed-cases', 4),
        ];
    }

    public static function canAccess(): bool
    {
        return SpRoleAccess::isAdminOrStaff();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Cases');
    }

    public static function getModelLabel(): string
    {
        return TenantConfig::entityLabel('intake_request', __('Intake Request'));
    }

    public static function getPluralModelLabel(): string
    {
        return Str::plural(static::getModelLabel());
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }
}
