<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Subject;
use App\Services\StaffPick\MatchingResult;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MatchesViewTest extends TestCase
{
    private function makeResult(string $lastName, array $factors, bool $languageMatched = false, bool $languageWarning = false): MatchingResult
    {
        $provider = new Provider;
        $provider->id = 7;
        $provider->first_name = 'Ana';
        $provider->last_name = $lastName;
        $provider->setRelation('tier', new ProviderTier(['name' => 'Gold']));

        return new MatchingResult($provider, 1.85, 3.4, $languageMatched, $languageWarning, $factors);
    }

    private function record(?float $lat, ?float $lng, ?string $languagePreference = null): IntakeRequest
    {
        $subject = new Subject;
        $subject->latitude = $lat;
        $subject->longitude = $lng;
        $subject->language_preference = $languagePreference;

        $record = new IntakeRequest;
        $record->id = 99;
        $record->setRelation('subject', $subject);
        $record->setRelation('discipline', new Discipline(['name' => 'Physical Therapy']));

        return $record;
    }

    private function render(IntakeRequest $record, Collection $results): string
    {
        return view('staffpick.intake-requests.matches', [
            'record' => $record,
            'results' => $results,
        ])->render();
    }

    public function test_it_renders_a_row_with_columns_and_an_assign_button(): void
    {
        $record = $this->record(40.0, -75.0);
        $results = new Collection([
            $this->makeResult('Closematch', ['is_preferred' => false, 'tier_priority' => 1]),
        ]);

        $html = $this->render($record, $results);

        $this->assertStringContainsString('Closematch', $html);
        $this->assertStringContainsString('Physical Therapy', $html); // discipline column
        $this->assertStringContainsString('Gold', $html);             // tier column
        $this->assertStringContainsString('3.4', $html);              // distance
        $this->assertStringContainsString('1.850', $html);            // score
        $this->assertStringContainsString('wire:click="assignProvider(99, 7)"', $html);
    }

    public function test_it_flags_preferred_providers(): void
    {
        $record = $this->record(40.0, -75.0);
        $results = new Collection([
            $this->makeResult('Star', ['is_preferred' => true, 'tier_priority' => 1]),
        ]);

        $this->assertStringContainsString('Preferred', $this->render($record, $results));
    }

    public function test_it_shows_a_language_warning_banner(): void
    {
        $record = $this->record(40.0, -75.0, 'Spanish');
        $results = new Collection([
            $this->makeResult('NoSpanish', ['is_preferred' => false, 'tier_priority' => 1], languageMatched: false, languageWarning: true),
        ]);

        $html = $this->render($record, $results);

        $this->assertStringContainsString('Language warning', $html);
        $this->assertStringContainsString('Spanish', $html);
    }

    public function test_it_shows_an_empty_state_when_no_providers_match(): void
    {
        $html = $this->render($this->record(40.0, -75.0), new Collection);

        $this->assertStringContainsString('No eligible', $html);
    }

    public function test_it_explains_when_the_subject_has_no_coordinates(): void
    {
        $html = $this->render($this->record(null, null), new Collection);

        $this->assertStringContainsString('geocoded address', $html);
    }
}
