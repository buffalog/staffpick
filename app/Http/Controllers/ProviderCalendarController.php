<?php

namespace App\Http\Controllers;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Response;

/**
 * Public iCal feed of a provider's active, scheduled cases. Authenticated by the
 * provider's calendar_token (no login) and scoped to the token's tenant — an invalid,
 * revoked, or wrong-tenant token 404s. Carries no PHI beyond the cases the provider is
 * already assigned to.
 */
class ProviderCalendarController extends Controller
{
    public function feed(string $tenantIdentifier, string $token): Response
    {
        $tenant = Tenant::where('uuid', $tenantIdentifier)->firstOrFail();

        $provider = Provider::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('calendar_token')
            ->where('calendar_token', $token)
            ->firstOrFail();

        $cases = IntakeRequest::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('lead_clinician_id', $provider->id)
            ->where('status', 'active')
            ->whereNotNull('evaluation_date')
            ->with('subject')
            ->get();

        // ponytail: no 75-octet line folding — ref/name/status values are short. Add
        // CRLF-space folding if a strict parser ever rejects a long DESCRIPTION.
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//StaffPick//Provider Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'REFRESH-INTERVAL;VALUE=DURATION:PT8H',
            'X-PUBLISHED-TTL:PT8H',
            'X-WR-CALNAME:'.$this->escapeText($provider->full_name.' — StaffPick Cases'),
            'X-WR-TIMEZONE:UTC',
        ];

        $stamp = now()->utc()->format('Ymd\THis\Z');

        foreach ($cases as $case) {
            $date = Carbon::parse($case->evaluation_date)->format('Ymd');
            $ref = $case->reference_number ?: 'case-'.$case->id;
            $subjectName = trim("{$case->subject?->first_name} {$case->subject?->last_name}");

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$ref.'@staffpick';
            $lines[] = 'DTSTAMP:'.$stamp;
            $lines[] = 'DTSTART;VALUE=DATE:'.$date;
            $lines[] = 'DTEND;VALUE=DATE:'.$date;
            $lines[] = 'SUMMARY:'.$this->escapeText($ref.' · '.($subjectName !== '' ? $subjectName : $ref));
            $lines[] = 'DESCRIPTION:'.$this->escapeText("Provider: {$provider->full_name}\nStatus: {$case->status}\nRef: {$ref}");
            $lines[] = 'URL:'.route('filament.dashboard.resources.intake-requests.view', [
                'tenant' => $tenant->uuid,
                'record' => $case->id,
            ]);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="staffpick-calendar.ics"',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Escape a value for an iCal TEXT property per RFC 5545: backslash first, then
     * semicolon, comma, and any newline → literal \n.
     */
    private function escapeText(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $value,
        );
    }
}
