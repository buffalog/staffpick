<?php

namespace App\Http\Controllers;

use App\Models\StaffPick\ProviderSurvey;
use App\Models\Tenant;
use App\Services\StaffPick\AuditLogger;
use App\Services\StaffPick\TenantContext;
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

        $this->auditView($survey);

        if ($survey->isResponded()) {
            return view('staffpick.survey.thanks', ['alreadyResponded' => true]);
        }

        return view('staffpick.survey.show', ['survey' => $survey]);
    }

    /**
     * HIPAA read audit for a public survey open: unauthenticated token holder (user_id null),
     * stamped with the survey's own tenant so the event is tenant-scoped; the subject resolves
     * from the survey's subject_id.
     */
    private function auditView(ProviderSurvey $survey): void
    {
        $tenant = Tenant::find($survey->tenant_id);

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run(
            $tenant,
            fn () => app(AuditLogger::class)->record('viewed', $survey, ['actor_label' => 'survey-token']),
        );
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
        // Public request, no tenant context. The opaque token is the cross-tenant credential —
        // ->crossTenant() opts the PHI read out of the fail-closed scope explicitly. (The only
        // relation the view reads is $survey->provider, which is clinician data, not PHI.)
        $survey = ProviderSurvey::query()->crossTenant()->where('token', $token)->first();

        abort_if($survey === null, 404);

        return $survey;
    }
}
