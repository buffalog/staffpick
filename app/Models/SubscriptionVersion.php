<?php

namespace App\Models;

use Mpociot\Versionable\Version;

class SubscriptionVersion extends Version
{
    public $table = 'subscription_versions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'versionable_id' => 'integer',
        ];
    }
}
