<?php

namespace App\Filament\Dashboard\Pages;

use App\Constants\TenancyPermissionConstants;
use App\Models\StaffPick\CredentialDocumentType;
use App\Services\TenantPermissionService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Credentialing policies: the per-tenant rules that govern each credential document
 * type. Tenant admins toggle whether a type is required, whether a lapsed credential
 * of that type auto-deactivates the provider, and how many days before expiry the
 * warning alerts begin. Edits save inline (toggle / blur). Gated to tenant admins via
 * the tenant-settings permission, like the other Settings-group pages.
 */
class CredentialingPolicies extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $slug = 'settings/credentialing';

    protected string $view = 'filament.dashboard.pages.credentialing-policies';

    public function getTitle(): string|Htmlable
    {
        return __('Credentialing Policies');
    }

    public static function getNavigationLabel(): string
    {
        return __('Credentialing Policies');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public static function canAccess(): bool
    {
        return app(TenantPermissionService::class)->tenantUserHasPermissionTo(
            Filament::getTenant(),
            auth()->user(),
            TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS,
        );
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CredentialDocumentType::query()
                    ->where('tenant_id', Filament::getTenant()?->id)
                    ->where('is_active', true)
                    ->orderBy('name')
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('Credential type'))
                    ->weight('medium'),
                ToggleColumn::make('is_required')
                    ->label(__('Required')),
                ToggleColumn::make('deactivate_on_expiry')
                    ->label(__('Deactivate on expiry')),
                TextInputColumn::make('expiry_warning_days')
                    ->label(__('Warning days'))
                    ->type('number')
                    ->rules(['integer', 'min:0', 'max:365']),
            ])
            ->paginated(false);
    }
}
