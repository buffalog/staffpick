<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_tenant_permission' => 'boolean',
        ];
    }
}
