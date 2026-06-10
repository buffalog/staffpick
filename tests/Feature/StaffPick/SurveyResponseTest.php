<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\ProviderSurvey;
use Illuminate\Support\Str;
use Tests\Feature\FeatureTest;

class SurveyResponseTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // The base FeatureTest disables exception handling; this endpoint asserts
        // real HTTP responses (404, validation redirects), so re-enable it.
        $this->withExceptionHandling();
    }

    private function survey(array $attributes = []): ProviderSurvey
    {
        $tenant = $this->createTenant();

        // High sentinel FK ids: the endpoint only needs the survey itself, and the
        // suite shares a DB across tests — real (small) assignment ids must not collide.
        return ProviderSurvey::create(array_merge([
            'tenant_id' => $tenant->id,
            'assignment_id' => 990001,
            'provider_id' => 990002,
            'subject_id' => 990003,
            'delivery_channel' => ProviderSurvey::CHANNEL_SMS,
            'status' => ProviderSurvey::STATUS_SENT,
            'token' => 'tok-'.Str::random(20),
        ], $attributes));
    }

    public function test_the_form_renders_for_a_valid_token(): void
    {
        $survey = $this->survey();

        $this->get(route('survey.show', ['token' => $survey->token]))
            ->assertSuccessful()
            ->assertSee('How was your visit?')
            ->assertSee('Submit rating');
    }

    public function test_an_unknown_token_returns_404(): void
    {
        $this->get(route('survey.show', ['token' => 'does-not-exist']))->assertNotFound();
    }

    public function test_submitting_a_rating_records_the_response(): void
    {
        $survey = $this->survey();

        $this->post(route('survey.submit', ['token' => $survey->token]), [
            'rating' => 4,
            'comment' => 'Great visit, very professional.',
        ])->assertSuccessful()->assertSee('Thank you');

        $survey->refresh();
        $this->assertSame(ProviderSurvey::STATUS_RESPONDED, $survey->status);
        $this->assertSame(4, $survey->rating);
        $this->assertSame('Great visit, very professional.', $survey->comment);
        $this->assertNotNull($survey->responded_at);
    }

    public function test_rating_is_required_and_bounded(): void
    {
        $survey = $this->survey();

        $this->post(route('survey.submit', ['token' => $survey->token]), ['rating' => 9])
            ->assertSessionHasErrors('rating');
        $this->post(route('survey.submit', ['token' => $survey->token]), [])
            ->assertSessionHasErrors('rating');

        $this->assertSame(ProviderSurvey::STATUS_SENT, $survey->fresh()->status);
    }

    public function test_an_already_responded_survey_is_not_overwritten(): void
    {
        $survey = $this->survey([
            'status' => ProviderSurvey::STATUS_RESPONDED,
            'rating' => 5,
            'responded_at' => now()->subDay(),
        ]);

        $this->get(route('survey.show', ['token' => $survey->token]))
            ->assertSuccessful()
            ->assertSee('already been completed');

        $this->post(route('survey.submit', ['token' => $survey->token]), ['rating' => 1])
            ->assertSuccessful();

        // The original rating stands.
        $this->assertSame(5, $survey->fresh()->rating);
    }
}
