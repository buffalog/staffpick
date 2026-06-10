<?php

namespace App\Http\Controllers;

use App\Models\StaffPick\ProviderSurvey;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated capture of patient satisfaction survey responses. The
 * opaque token in the link is the only credential; once a rating is recorded the
 * survey is marked responded and the weekly aggregator picks it up.
 */
class SurveyController extends Controller
{
    public function show(string $token): View
    {
        $survey = $this->resolveSurvey($token);

        if ($survey->isResponded()) {
            return view('staffpick.survey.thanks', ['alreadyResponded' => true]);
        }

        return view('staffpick.survey.show', ['survey' => $survey]);
    }

    public function submit(Request $request, string $token): View
    {
        $survey = $this->resolveSurvey($token);

        // First response wins — re-submissions just see the thank-you page.
        if ($survey->isResponded()) {
            return view('staffpick.survey.thanks', ['alreadyResponded' => true]);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $survey->update([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'status' => ProviderSurvey::STATUS_RESPONDED,
            'responded_at' => now(),
        ]);

        return view('staffpick.survey.thanks', ['alreadyResponded' => false]);
    }

    private function resolveSurvey(string $token): ProviderSurvey
    {
        // No Filament tenant in a public request, so the BelongsToTenant scope is a
        // no-op and the token resolves across tenants.
        $survey = ProviderSurvey::query()->where('token', $token)->first();

        abort_if($survey === null, 404);

        return $survey;
    }
}
