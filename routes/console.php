<?php

use App\Jobs\StaffPick\AggregateProviderRatings;
use App\Jobs\StaffPick\ProcessMatchTimeouts;
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

// StaffPick: daily admin alert (Slack + bell) for credentials expiring within 30 days.
Schedule::command('staffpick:check-credential-expiry')
    ->dailyAt('07:00')
    ->name('staffpick-check-credential-expiry')
    ->withoutOverlapping();

// StaffPick: expire timed-out match offers and cascade to the next provider.
Schedule::job(new ProcessMatchTimeouts)
    ->everyFiveMinutes()
    ->name('staffpick-process-match-timeouts')
    ->withoutOverlapping();
