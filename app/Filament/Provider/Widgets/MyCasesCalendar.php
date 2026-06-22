<?php

namespace App\Filament\Provider\Widgets;

use App\Filament\Dashboard\Resources\IntakeRequests\IntakeRequestResource;
use App\Models\StaffPick\Assignment;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * FullCalendar (CDN) view of the signed-in clinician's assigned cases, placed on
 * the My Cases page in the Provider portal. One event per case with an evaluation
 * date; past events are greyed, upcoming events blue, today is highlighted by
 * FullCalendar itself.
 */
class MyCasesCalendar extends Widget
{
    protected string $view = 'filament.provider.widgets.my-cases-calendar';

    protected int|string|array $columnSpan = 'full';

    private const COLOR_UPCOMING = '#2563eb';

    private const COLOR_PAST = '#9ca3af';

    /**
     * Calendar events for FullCalendar. evaluation_date is intentionally uncast, so
     * it comes back as a raw 'Y-m-d' string — parse it with Carbon (string parsing,
     * not a date-cast read) to classify past vs upcoming.
     *
     * @return array<int, array{title: string, start: string, color: string}>
     */
    public function getEvents(): array
    {
        $provider = $this->provider();

        if ($provider === null) {
            return [];
        }

        $today = now()->startOfDay();

        return IntakeRequest::query()
            ->where('tenant_id', Filament::getTenant()?->id)
            ->whereNotNull('evaluation_date')
            ->whereHas('assignments', fn (Builder $sub): Builder => $sub
                ->where('provider_id', $provider->id)
                ->where('status', '!=', Assignment::STATUS_CANCELLED))
            ->with('subject')
            ->get()
            ->map(function (IntakeRequest $intake) use ($today): ?array {
                $date = $intake->evaluation_date;

                if (blank($date)) {
                    return null;
                }

                $day = substr((string) $date, 0, 10);
                $name = trim("{$intake->subject?->first_name} {$intake->subject?->last_name}");
                $statusLabel = IntakeRequestResource::statusOptions()[$intake->status] ?? str($intake->status)->headline()->toString();

                return [
                    'title' => trim(($name !== '' ? $name : __('Case')).' — '.$statusLabel),
                    'start' => $day,
                    'color' => Carbon::parse($day)->lt($today) ? self::COLOR_PAST : self::COLOR_UPCOMING,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function provider(): ?Provider
    {
        $tenant = Filament::getTenant();
        $user = auth()->user();

        if (! $tenant instanceof Tenant || $user === null) {
            return null;
        }

        return $user->providerForTenant($tenant->id);
    }
}
