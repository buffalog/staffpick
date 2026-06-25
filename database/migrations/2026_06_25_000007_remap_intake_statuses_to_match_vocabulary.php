<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename/remap existing intake statuses to the match/dispatch vocabulary (commit 2 of
 * the redesign). Deploys atomically with the engine cutover (commit 3), so no row is
 * ever left in a status the running code doesn't recognize.
 *
 * Mapping (confirmed): pending/matching → unmatched, offered → match_sent,
 * assigned_pending/active → matched, no_clinicians_available → escalated. on_hold,
 * completed, cancelled, draft and other terminal values are unchanged.
 */
return new class extends Migration
{
    private const FORWARD = [
        'pending' => 'unmatched',
        'matching' => 'unmatched',
        'offered' => 'match_sent',
        'assigned_pending' => 'matched',
        'active' => 'matched',
        'no_clinicians_available' => 'escalated',
    ];

    /** Best-effort reverse to a single canonical old value (the remap is lossy). */
    private const REVERSE = [
        'unmatched' => 'pending',
        'match_sent' => 'offered',
        'matched' => 'active',
        'escalated' => 'no_clinicians_available',
    ];

    public function up(): void
    {
        foreach (self::FORWARD as $old => $new) {
            DB::table('sp_intake_requests')->where('status', $old)->update(['status' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::REVERSE as $new => $old) {
            DB::table('sp_intake_requests')->where('status', $new)->update(['status' => $old]);
        }
    }
};
