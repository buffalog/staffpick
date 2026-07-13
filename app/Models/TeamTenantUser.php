<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TeamTenantUser extends Pivot
{
    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_user_id' => 'integer',
            'team_id' => 'integer',
        ];
    }
}
