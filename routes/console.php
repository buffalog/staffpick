<?php

use App\Jobs\StaffPick\AggregateProviderRatings;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Schedule::command('app:generate-sitemap')->everyOddHour();

Schedule::command('app:metrics-beat')->dailyAt('00:01');

Schedule::command('app:local-subscription-expiring-soon-reminder')->dailyAt('00:01');

Schedule::command('app:cleanup-local-subscription-statuses')->hourly();

Schedule::command('app:sync-seat-based-subscription-quantities')->hourly();

// StaffPick: weekly provider rating aggregation + tier-review generation (Sunday night).
Schedule::job(new AggregateProviderRatings)
    ->weeklyOn(0, '23:00')
    ->name('staffpick-aggregate-provider-ratings')
    ->withoutOverlapping();

// StaffPick: daily Slack alert for credentials expiring within 30 days.
Schedule::command('staffpick:notify-expiring-credentials')
    ->dailyAt('07:00')
    ->name('staffpick-notify-expiring-credentials')
    ->withoutOverlapping();
