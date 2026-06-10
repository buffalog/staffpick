<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Subject;
use App\Services\StaffPick\MatchingResult;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MatchesViewTest extends TestCase
{
    private function makeResult(string $lastName, array $factors, float $score = 0.85, float $distance = 3.4): MatchingResult
    {
        $provider = new Provider;
        $provider->first_name = 'Ana';
        $provider->last_name = $lastName;
        $provider->setRelation('tier', new ProviderTier(['name' => 'Gold']));

        return new MatchingResult($provider, $score, $distance, $factors);
    }

    private function intakeWithSubjectCoordinates(?float $lat, ?float $lng): IntakeRequest
    {
        $subject = new Subject;
        $subject->latitude = $lat;
        $subject->longitude = $lng;

        $record = new IntakeRequest;
        $record->setRelation('subject', $subject);

        return $record;
    }

    private function render(IntakeRequest $record, Collection $results): string
    {
        return view('staffpick.intake-requests.matches', [
            'record' => $record,
            'results' => $results,
        ])->render();
    }

    public function test_it_renders_a_row_with_provider_distance_score_and_factor_badges(): void
    {
        $record = $this->intakeWithSubjectCoordinates(40.0, -75.0);
        $results = new Collection([
            $this->makeResult('Closematch', ['near_miss' => false, 'specialty' => 1.0, 'language' => true, 'tier_priority' => 1], 0.85, 3.4),
        ]);

        $html = $this->render($record, $results);

        $this->assertStringContainsString('Closematch', $html);
        $this->assertStringContainsString('Gold', $html);
        $this->assertStringContainsString('3.4', $html);   // distance, 1dp
        $this->assertStringContainsString('0.850', $html); // score, 3dp
        $this->assertStringContainsString('Specialty', $html);
        $this->assertStringContainsString('Language', $html);
    }

    public function test_it_flags_near_miss_providers(): void
    {
        $record = $this->intakeWithSubjectCoordinates(40.0, -75.0);
        $results = new Collection([
            $this->makeResult('Edgecase', ['near_miss' => true, 'specialty' => 0.0, 'language' => false, 'tier_priority' => 1]),
        ]);

        $this->assertStringContainsString('Near miss', $this->render($record, $results));
    }

    public function test_it_shows_an_empty_state_when_no_providers_match(): void
    {
        $record = $this->intakeWithSubjectCoordinates(40.0, -75.0);

        $html = $this->render($record, new Collection);

        $this->assertStringContainsString('No eligible', $html);
    }

    public function test_it_explains_when_the_subject_has_no_coordinates(): void
    {
        $record = $this->intakeWithSubjectCoordinates(null, null);

        $html = $this->render($record, new Collection);

        $this->assertStringContainsString('geocoded address', $html);
    }
}
