<?php

namespace App\Filament\Dashboard\Widgets;

use App\Filament\Dashboard\Pages\Dashboard;
use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Models\StaffPick\IntakeRequest;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Three ops stat cards for the staff dashboard: pending / active / completed counts,
 * each linking to its scoped Cases page. Tenant-scoped.
 */
class StaffDashboardStats extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $tenantId = ($t = Filament::getTenant()) instanceof Tenant ? $t->id : null;

        $count = fn (array $statuses): int => IntakeRequest::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $statuses)
            ->count();

        $oldestPendingDays = (function () use ($tenantId): int {
            $oldest = IntakeRequest::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', Dashboard::PENDING)
                ->orderBy('created_at')
                ->first();

            return $oldest?->created_at !== null ? (int) $oldest->created_at->diffInDays(now()) : 0;
        })();

        return [
            Stat::make(__('Pending Cases'), $count(Dashboard::PENDING))
                ->description($oldestPendingDays > 0 ? __('Oldest: :daysd', ['days' => $oldestPendingDays]) : __('None waiting'))
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning')
                ->url(IntakeRequestResource::getUrl('index')),
            Stat::make(__('Active Cases'), $count(Dashboard::ACTIVE))
                ->description(__('Currently in service'))
                ->descriptionIcon('heroicon-o-briefcase')
                ->color('primary')
                ->url(IntakeRequestResource::getUrl('cases')),
            Stat::make(__('Completed'), $count(Dashboard::COMPLETED))
                ->description(__('Closed cases'))
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->url(IntakeRequestResource::getUrl('completed-cases')),
        ];
    }
}
