<?php

namespace App\Events\StaffPick;

use App\Models\StaffPick\ReferralSource;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralSourceApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public ReferralSource $source) {}
}
