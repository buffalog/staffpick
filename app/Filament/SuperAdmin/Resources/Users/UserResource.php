<?php

namespace App\Filament\SuperAdmin\Resources\Users;

use App\Filament\SuperAdmin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Platform-wide user directory for super admins: reset passwords and grant/revoke the
 * global admin flag. Super-admin accounts are protected here — they cannot be demoted
 * or deleted from the UI (is_super_admin is only settable via Artisan/DB).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'email';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('Email'))->searchable()->sortable(),
                IconColumn::make('is_admin')->label(__('Admin'))->boolean(),
                IconColumn::make('is_super_admin')->label(__('Super Admin'))->boolean(),
                TextColumn::make('tenants_count')->label(__('Tenants'))->counts('tenants')->badge(),
                TextColumn::make('created_at')->label(__('Joined'))->date()->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin')->label(__('Admins')),
                TernaryFilter::make('is_super_admin')->label(__('Super admins')),
            ])
            ->recordActions([
                Action::make('resetPassword')
                    ->label(__('Reset password'))
                    ->icon(Heroicon::OutlinedKey)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription(__('Generates a new temporary password and shows it once.'))
                    ->action(function (User $record): void {
                        $password = Str::password(16);
                        $record->password = $password; // hashed by the model cast
                        $record->save();

                        Notification::make()
                            ->title(__('Temporary password for :email', ['email' => $record->email]))
                            ->body($password)
                            ->persistent()
                            ->warning()
                            ->send();
                    }),
                Action::make('toggleAdmin')
                    ->label(fn (User $record): string => $record->is_admin ? __('Revoke admin') : __('Grant admin'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color(fn (User $record): string => $record->is_admin ? 'danger' : 'success')
                    ->requiresConfirmation()
                    // Never demote/alter a super admin from the UI.
                    ->hidden(fn (User $record): bool => $record->isSuperAdmin())
                    ->action(fn (User $record) => $record->update(['is_admin' => ! $record->is_admin])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Users');
    }

    public static function getModelLabel(): string
    {
        return __('User');
    }
}
