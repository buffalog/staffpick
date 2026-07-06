<?php

use App\Models\StaffPick\Language;
use App\Models\StaffPick\Provider;
use App\Support\StaffPick\ProviderNotesPromotion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * One-time promotion of two free-text facts staff recorded in provider Notes into their
 * structured homes: "Languages Spoken: ..." -> the sp_provider_languages pivot, and
 * "Can Adjust Own Service Zones: Yes/No" -> the can_adjust_own_service_zones column. The
 * matched phrases are then stripped from Notes so the data no longer lives in two places.
 *
 * Conservative by design: only the two exact labelled patterns are touched (every other
 * note is left verbatim), and if a listed language isn't in the sp_languages taxonomy its
 * phrase is kept in Notes rather than silently dropped. Runs on the SQL Server default
 * (case-insensitive) collation, so name matching is case-insensitive.
 *
 * Irreversible: down() is a no-op because the original free-text sentences cannot be
 * faithfully reconstructed once promoted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Provider::query()
            ->whereNotNull('notes')
            ->where(function ($query) {
                $query->where('notes', 'like', '%Languages Spoken%')
                    ->orWhere('notes', 'like', '%Adjust Own Service Zones%');
            })
            ->get()
            ->each(function (Provider $provider): void {
                $parsed = ProviderNotesPromotion::parse($provider->notes);
                $notes = $parsed['notes'];

                if ($parsed['languageNames'] !== []) {
                    $matchedIds = [];
                    $unmatched = [];

                    foreach ($parsed['languageNames'] as $name) {
                        $language = Language::where('name', $name)->first();

                        if ($language !== null) {
                            $matchedIds[] = $language->id;
                        } else {
                            $unmatched[] = $name;
                        }
                    }

                    if ($matchedIds !== []) {
                        $provider->languages()->syncWithoutDetaching($matchedIds);
                    }

                    // Never lose a language we couldn't map to the taxonomy — put it back
                    // in Notes and flag it for a human rather than dropping it on the floor.
                    if ($unmatched !== []) {
                        Log::warning('Provider notes promotion: unmatched languages retained in notes.', [
                            'provider_id' => $provider->id,
                            'languages' => $unmatched,
                        ]);

                        $retained = 'Languages Spoken (unmatched): '.implode(', ', $unmatched).'.';
                        $notes = trim(($notes ?? '').' '.$retained);
                    }
                }

                if ($parsed['canAdjustServiceZones'] !== null) {
                    $provider->can_adjust_own_service_zones = $parsed['canAdjustServiceZones'];
                }

                $provider->notes = ($notes === null || $notes === '') ? null : $notes;
                $provider->save();
            });
    }

    public function down(): void
    {
        // Irreversible — the original free-text sentences cannot be reconstructed.
    }
};
