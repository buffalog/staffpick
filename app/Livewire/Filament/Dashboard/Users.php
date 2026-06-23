<?php

namespace App\Livewire\Filament\Dashboard;

use App\Constants\TenancyPermissionConstants;
use App\Models\User;
use App\Services\TeamService;
use App\Services\TenantPermissionService;
use App\Services\TenantService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;

class Users extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $tenant = Filament::getTenant();

                return User::query()
                    ->whereHas('tenants', function (Builder $query) use ($tenant) {
                        $query->where('tenant_id', $tenant->id);
                    });
            })
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('sp_roles')
                    ->label(__('Role'))
                    ->badge()
                    ->placeholder('—')
                    ->getStateUsing(fn (User $user): array => collect($user->spRolesForTenant(Filament::getTenant()->id))
                        ->map(fn (string $role): string => Str::of($role)->after('sp_')->title()->toString())
                        ->values()
                        ->all()),
                TextColumn::make('teams')
                    ->getStateUsing(function (User $record, TeamService $teamService) {
                        $teams = $teamService->getUserTeams($record, Filament::getTenant())->pluck('name')->toArray();

                        return implode("\n", $teams);
                    })
                    ->separator("\n")
                    ->visible(fn (): bool => config('app.teams_enabled', false))
                    ->badge()
                    ->label(__('Team(s)')),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('addUser')
                    ->label(__('Add User'))
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading(__('Add User'))
                    ->modalSubmitActionLabel(__('Create User'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(table: User::class),
                        TextInput::make('password')
                            ->label(__('Temporary Password'))
                            ->password()
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('Share this with the user. They should change it after first login.')),
                        Select::make('role')
                            ->label(__('Role'))
                            ->required()
                            ->options(collect(TenancyPermissionConstants::SP_TENANT_ROLES)
                                ->mapWithKeys(fn (string $role): array => [
                                    $role => Str::of($role)->after('sp_')->title()->toString(),
                                ])
                                ->all()),
                    ])
                    ->action(function (array $data, TenantService $tenantService, TenantPermissionService $tenantPermissionService): void {
                        $tenant = Filament::getTenant();

                        $user = User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'password' => bcrypt($data['password']),
                        ]);

                        // email_verified_at is not fillable — set it explicitly so the
                        // user can sign in immediately with the temp password instead of
                        // being blocked by email verification.
                        $user->forceFill(['email_verified_at' => now()])->save();

                        // Reuse the existing attach path (seat checks + default tenant).
                        if (! $tenantService->addUserToTenant($tenant, $user)) {
                            $user->delete();

                            Notification::make()
                                ->title(__('Could not add user — workspace seat limit reached.'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $tenantPermissionService->assignTenantUserRoles($tenant, $user, [$data['role']]);

                        Notification::make()
                            ->title(__('User created.'))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('editRoles')
                    ->label(__('Edit Roles'))
                    ->icon('heroicon-o-shield-check')
                    ->modalHeading(__('Edit Roles'))
                    ->modalSubmitActionLabel(__('Save'))
                    // Don't let an admin edit their own roles (and risk locking
                    // themselves out) — mirrors the old self-disabled role column.
                    ->disabled(fn (User $user): bool => $user->id === auth()->id())
                    ->fillForm(fn (User $user): array => [
                        'roles' => $user->spRolesForTenant(Filament::getTenant()->id),
                    ])
                    ->schema([
                        Select::make('roles')
                            ->label(__('Roles'))
                            ->multiple()
                            ->required()
                            ->options(collect(TenancyPermissionConstants::SP_TENANT_ROLES)
                                ->mapWithKeys(fn (string $role): array => [
                                    $role => Str::of($role)->after('sp_')->title()->toString(),
                                ])
                                ->all()),
                    ])
                    ->action(function (User $user, array $data, TenantPermissionService $tenantPermissionService): void {
                        $tenantPermissionService->assignTenantUserRoles(
                            Filament::getTenant(),
                            $user,
                            array_values($data['roles']),
                        );

                        Notification::make()
                            ->title(__('User roles updated.'))
                            ->success()
                            ->send();
                    }),
                Action::make('remove')
                    ->label(__('Remove User'))
                    ->color('danger')
                    ->requiresConfirmation(true)
                    ->visible(function (User $user, TenantService $tenantService) {
                        return $tenantService->canRemoveUser(Filament::getTenant(), $user);
                    })
                    ->action(function (User $user, TenantService $tenantService) {
                        $result = $tenantService->removeUser(Filament::getTenant(), $user);

                        if ($result) {
                            Notification::make()
                                ->title(__('User has been removed.'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('User could not be removed.'))
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    public function render(): View
    {
        return view('livewire.filament.dashboard.users');
    }
}
