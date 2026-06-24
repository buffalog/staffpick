<?php

namespace App\Events\StaffPick;

use App\Models\StaffPick\Provider;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProviderApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public Provider $provider) {}
}
