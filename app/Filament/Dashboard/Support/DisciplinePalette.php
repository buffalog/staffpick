<?php

namespace App\Filament\Dashboard\Support;

/**
 * Header colors for the provider profile card, keyed by discipline abbreviation.
 * Exact hex values are Jeremy's approved mockup spec. Assistant disciplines share
 * their lead discipline's color (PTA→PT, OTA→OT). Anything unmapped falls back to a
 * neutral slate so a tenant's custom discipline still renders a readable header.
 *
 * Providers can hold multiple disciplines; the card header renders one colored
 * segment per discipline, each resolved through this palette.
 */
class DisciplinePalette
{
    /**
     * @return array{bg: string, text: string}
     */
    public static function forAbbreviation(?string $abbreviation): array
    {
        return match (strtoupper(trim((string) $abbreviation))) {
            'PT', 'PTA' => ['bg' => '#E1F5EE', 'text' => '#085041'],
            'OT', 'OTA' => ['bg' => '#FAECE7', 'text' => '#4A1B0C'],
            'SLP' => ['bg' => '#EEEDFE', 'text' => '#26215C'],
            default => ['bg' => '#F1F5F9', 'text' => '#334155'],
        };
    }
}
