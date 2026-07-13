<?php

namespace App\Models;

use Mpociot\Versionable\Version;

class TransactionVersion extends Version
{
    public $table = 'transaction_versions';

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
