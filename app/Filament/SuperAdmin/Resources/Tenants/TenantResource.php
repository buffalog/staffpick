<?php

namespace App\Filament\SuperAdmin\Resources\Tenants;

use App\Filament\SuperAdmin\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Platform-wide tenant directory for super admins, with an "Access as tenant" action
 * that drops them straight into a tenant's dashboard (super admins bypass membership,
 * so no impersonation session swap is needed).
 */
class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('uuid')->label(__('Slug'))->badge()->color('gray')->searchable(),
                TextColumn::make('users_count')->label(__('Members'))->counts('users')->badge(),
                TextColumn::make('config.sso_provider')->label(__('SSO'))
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state ? str($state)->headline()->toString() : '—'),
                TextColumn::make('created_at')->label(__('Created'))->date()->sortable(),
            ])
            ->recordActions([
                Action::make('accessAsTenant')
                    ->label(__('Access as tenant'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('primary')
                    ->url(fn (Tenant $record): string => url('/dashboard/'.$record->uuid))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Tenants');
    }

    public static function getModelLabel(): string
    {
        return __('Tenant');
    }
}
