<?php

namespace App\Support\StaffPick;

/**
 * Extracts the two legacy free-text facts staff used to type into a provider's Notes
 * ("Languages Spoken: Spanish. Can Adjust Own Service Zones: Yes.") so they can be
 * promoted into the structured `languages` pivot and `can_adjust_own_service_zones`
 * column. Pure string logic — no DB — so the risky parsing is unit-tested without the
 * SQL Server container. Taxonomy matching + persistence live in the data migration.
 */
class ProviderNotesPromotion
{
    /**
     * @return array{languageNames: list<string>, canAdjustServiceZones: bool|null, notes: string|null}
     */
    public static function parse(?string $notes): array
    {
        $result = [
            'languageNames' => [],
            'canAdjustServiceZones' => null,
            'notes' => $notes,
        ];

        if ($notes === null || trim($notes) === '') {
            $result['notes'] = null;

            return $result;
        }

        // "Languages Spoken: Spanish, Haitian Creole." — capture the list up to the period.
        if (preg_match('/Languages?\s+Spoken\s*:\s*([^.]+?)\s*\./i', $notes, $matches)) {
            $names = preg_split('/\s*(?:,|;|\/|\band\b)\s*/i', trim($matches[1])) ?: [];
            $result['languageNames'] = array_values(array_filter(array_map('trim', $names), fn (string $n): bool => $n !== ''));
            $notes = preg_replace('/\s*Languages?\s+Spoken\s*:\s*[^.]+?\s*\.\s*/i', ' ', $notes);
        }

        // "Can Adjust Own Service Zones: Yes." (or No). Trailing period optional.
        if (preg_match('/Can\s+Adjust\s+Own\s+Service\s+Zones?\s*:\s*(Yes|No)\b\.?/i', $notes, $matches)) {
            $result['canAdjustServiceZones'] = strtolower($matches[1]) === 'yes';
            $notes = preg_replace('/\s*Can\s+Adjust\s+Own\s+Service\s+Zones?\s*:\s*(?:Yes|No)\b\.?\s*/i', ' ', $notes);
        }

        $notes = trim(preg_replace('/\s+/', ' ', $notes ?? ''));
        $result['notes'] = $notes === '' ? null : $notes;

        return $result;
    }
}
