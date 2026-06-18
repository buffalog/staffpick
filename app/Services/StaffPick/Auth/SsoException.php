<?php

namespace App\Services\StaffPick\Auth;

use RuntimeException;

/**
 * Thrown when an SSO flow cannot complete — e.g. the authenticated email's domain
 * does not match the tenant's configured SSO domain.
 */
class SsoException extends RuntimeException {}
