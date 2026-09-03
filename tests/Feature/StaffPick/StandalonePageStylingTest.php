<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\FeatureTest;

/**
 * /offers/{token} renders outside any Filament panel (#[Layout('components.layouts.intake')]
 * -> resources/css/app.css), where the panel's colour aliases do not exist: colors.css
 * defines a `primary` scale and nothing else. `bg-success-600` therefore compiled to no rule
 * at all while `text-white` still applied, so "Accept offer" was white-on-white — present in
 * the DOM, fully clickable, and invisible. A provider accepted by clicking blank space.
 *
 * assertSee('Accept offer') passes against that markup, which is why it survived. These
 * assertions check the rule exists in the stylesheet the page actually loads.
 */
class StandalonePageStylingTest extends FeatureTest
{
    /** Utilities that take a numeric shade, i.e. the ones an undefined palette silently kills. */
    private const COLOUR_UTILITY = '/(?:bg|text|border|ring|divide|from|via|to|fill|stroke|accent|outline|decoration|caret|placeholder)-[a-z]+-\d{2,3}\b/';

    private Tenant $tenant;

    private Discipline $discipline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);
    }

    /** The compiled stylesheet the standalone (non-panel) layout loads. */
    private function standaloneStylesheet(): string
    {
        $manifestPath = public_path('build/manifest.json');

        $this->assertFileExists($manifestPath, 'Run `npm run build` — this test reads the compiled CSS.');

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $entry = $manifest['resources/css/app.css']['file'] ?? null;

        $this->assertNotNull($entry, 'resources/css/app.css is missing from the Vite manifest.');

        return (string) file_get_contents(public_path('build/'.$entry));
    }

    /**
     * @return array<int, string> colour utilities in $html that the stylesheet has no rule for
     */
    private function unstyledColourUtilities(string $html): array
    {
        $css = $this->standaloneStylesheet();

        preg_match_all(self::COLOUR_UTILITY, $html, $matches);

        $classes = array_unique($matches[0]);
        $this->assertNotEmpty($classes, 'Found no colour utilities at all — the page probably did not render.');

        $missing = array_values(array_filter(
            $classes,
            fn (string $class): bool => ! str_contains($css, $class),
        ));

        sort($missing);

        return $missing;
    }

    private function offerFor(User $user, string $status = AssignmentOffer::STATUS_PENDING): AssignmentOffer
    {
        $provider = Provider::factory()->create([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $this->discipline->id,
            'user_id' => $user->id,
        ]);

        $intake = IntakeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subject_id' => Subject::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'discipline_id' => $this->discipline->id,
            'status' => IntakeRequest::STATUS_MATCH_SENT,
        ]);

        return AssignmentOffer::create([
            'tenant_id' => $this->tenant->id,
            'intake_request_id' => $intake->id,
            'provider_id' => $provider->id,
            'offer_sequence' => 1,
            'status' => $status,
            'offered_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'token' => 'tok_'.Str::random(40),
        ]);
    }

    private function renderOffer(string $status): string
    {
        $user = $this->createUser($this->tenant);
        $offer = $this->offerFor($user, $status);

        $this->actingAs($user);

        return $this->get('/offers/'.$offer->token)->assertSuccessful()->getContent();
    }

    public function test_every_colour_on_the_pending_offer_page_actually_has_a_rule(): void
    {
        $html = $this->renderOffer(AssignmentOffer::STATUS_PENDING);

        $this->assertStringContainsString('Accept offer', $html);
        $this->assertSame([], $this->unstyledColourUtilities($html), implode("\n", [
            'These classes render on /offers/{token} but compile to no rule in app.css, so the',
            'elements using them are unstyled — an invisible button reads exactly like a working',
            'one to assertSee(). Use a literal Tailwind colour on standalone pages, or add the',
            'scale to resources/css/colors.css.',
        ]));
    }

    public function test_every_colour_on_the_accepted_offer_page_actually_has_a_rule(): void
    {
        // The "Offer accepted" heading is a separate branch of the blade and carried the same
        // undefined alias, so it needs rendering in its own right.
        $html = $this->renderOffer(AssignmentOffer::STATUS_ACCEPTED);

        $this->assertStringContainsString('Offer accepted', $html);
        $this->assertSame([], $this->unstyledColourUtilities($html));
    }
}
