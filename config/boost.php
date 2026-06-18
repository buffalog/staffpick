<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Laravel Boost configuration overrides
|--------------------------------------------------------------------------
|
| This file is recursively merged onto the package defaults in
| vendor/laravel/boost/config/boost.php (mergeConfigFrom), so only the
| keys we deliberately override need to be present here.
|
*/

return [
    'guidelines' => [
        /*
         | Suppress Boost's generic built-in "deploy with Laravel Cloud"
         | guideline. The project's real deployment + Azure SQL / dblib
         | constraints live in .ai/guidelines/deployment-railway.md, which
         | Boost composes in but never overwrites. Without this exclude,
         | boost:install would re-emit the contradictory generic line.
         */
        'exclude' => [
            'deployments',
        ],
    ],
];
