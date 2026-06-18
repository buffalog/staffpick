<?php

namespace App\Models\StaffPick;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit record of an authentication attempt (SSO, social, or super-admin login).
 * Not directly tenant-FK'd (sp_* cascade-path constraints); tenant_id/user_id are
 * plain references that may be null when the attempt fails before resolution.
 */
class AuthLog extends Model
{
    use HasFactory;

    public const EVENT_SSO_REDIRECT = 'sso_redirect';

    public const EVENT_SSO_CALLBACK = 'sso_callback';

    public const EVENT_SOCIAL_LOGIN = 'social_login';

    public const EVENT_SUPER_ADMIN_LOGIN = 'super_admin_login';

    protected $table = 'sp_auth_logs';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'event_type',
        'email',
        'ip_address',
        'provider',
        'success',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
        ];
    }
}
