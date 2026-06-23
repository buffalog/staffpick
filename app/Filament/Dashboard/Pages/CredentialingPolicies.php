<?php

namespace App\Filament\Dashboard\Pages;

use App\Filament\Dashboard\Support\SpRoleAccess;
use App\Models\StaffPick\CredentialDocumentType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
        return SpRoleAccess::isAdmin();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addCredentialType')
                ->label(__('Add Credential Type'))
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading(__('Add Credential Type'))
                ->modalSubmitActionLabel(__('Add'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Credential Name'))
                        ->required()
                        ->maxLength(255),
                    Toggle::make('is_required')
                        ->label(__('Required'))
                        ->default(false),
                    Toggle::make('deactivate_on_expiry')
                        ->label(__('Deactivate provider on expiry'))
                        ->default(true),
                    TextInput::make('expiry_warning_days')
                        ->label(__('Warning days before expiry'))
                        ->numeric()
                        ->minValue(0)
                        ->default(30),
                    Toggle::make('has_expiry')
                        ->label(__('Has expiry date'))
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    CredentialDocumentType::create([
                        'tenant_id' => Filament::getTenant()?->id,
                        'name' => $data['name'],
                        'is_required' => (bool) ($data['is_required'] ?? false),
                        'has_expiry' => (bool) ($data['has_expiry'] ?? true),
                        'deactivate_on_expiry' => (bool) ($data['deactivate_on_expiry'] ?? true),
                        'expiry_warning_days' => (int) ($data['expiry_warning_days'] ?? 30),
                        'verification_method' => CredentialDocumentType::METHOD_MANUAL,
                        'is_active' => true,
                    ]);

                    Notification::make()
                        ->title(__('Credential type added'))
                        ->success()
                        ->send();
                }),
        ];
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
