<?php

namespace Tests\Feature\StaffPick;

use App\Models\StaffPick\AssignmentOffer;
use App\Models\StaffPick\Discipline;
use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Provider;
use App\Models\StaffPick\ProviderTier;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use App\Services\StaffPick\TierResponseScorer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\FeatureTest;

class TierResponseScorerTest extends FeatureTest
{
    private Tenant $tenant;

    private Discipline $discipline;

    private ProviderTier $platinum;

    private ProviderTier $gold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->discipline = Discipline::create(['tenant_id' => $this->tenant->id, 'name' => 'Physical Therapy']);
        $this->platinum = ProviderTier::create(['tenant_id' => $this->tenant->id, 'name' => 'Platinum', 'priority' => 1]);
        $this->gold = ProviderTier::create(['tenant_id' => $this->tenant->id, 'name' => 'Gold', 'priority' => 2]);
    }

    private function scorer(): TierResponseScorer
    {
        return app(TierResponseScorer::class);
    }

    private function provider(array $attributes = []): Provider
    {
        return Provider::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'discipline_id' => $this->discipline->id,
            'status' => Provider::STATUS_ACTIVE,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $subjectAttributes
     */
    private function case(array $attributes = [], array $subjectAttributes = []): IntakeRequest
    {
        $subject = Subject::factory()->create(['tenant_id' => $this->tenant->id, ...$subjectAttributes]);

        return IntakeRequest::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'subject_id' => $subject->id,
            'discipline_id' => $this->discipline->id,
        ], $attributes));
    }

    /**
     * Give a provider $received offers, of which $accepted were accepted.
     */
    private function offers(Provider $provider, int $received, int $accepted): void
    {
        $case = $this->case();

        for ($i = 0; $i < $received; $i++) {
            AssignmentOffer::create([
                'tenant_id' => $this->tenant->id,
                'intake_request_id' => $case->id,
                'provider_id' => $provider->id,
                'offer_sequence' => 1,
                'status' => $i < $accepted ? AssignmentOffer::STATUS_ACCEPTED : AssignmentOffer::STATUS_DECLINED,
                'offered_at' => now(),
                'expires_at' => now()->addMinutes(5),
                'token' => 'tok_'.Str::random(40),
            ]);
        }
    }

    /**
     * A single offer of an arbitrary status (reuses the offers() shape).
     */
    private function offer(Provider $provider, string $status): void
    {
        AssignmentOffer::create([
            'tenant_id' => $this->tenant->id,
            'intake_request_id' => $this->case()->id,
            'provider_id' => $provider->id,
            'offer_sequence' => 1,
            'status' => $status,
            'offered_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'token' => 'tok_'.Str::random(40),
        ]);
    }

    /**
     * @return array<int> ordered provider ids
     */
    private function order(IntakeRequest $case, Provider ...$providers): array
    {
        return $this->scorer()->order($case, collect($providers))
            ->map(fn (Provider $p): int => $p->id)
            ->all();
    }

    public function test_requested_provider_ranks_first_over_everything(): void
    {
        // Worst possible on every other signal: no tier, poor response rate, not preferred.
        $requested = $this->provider(['tier_id' => null]);
        $this->offers($requested, 10, 1); // 0.1

        $strong = $this->provider(['tier_id' => $this->platinum->id, 'is_preferred' => true]);
        $this->offers($strong, 10, 10); // 1.0

        $case = $this->case(['requested_provider_id' => $requested->id]);

        $this->assertSame([$requested->id, $strong->id], $this->order($case, $strong, $requested));
    }

    public function test_preferred_beats_higher_tier_and_response_rate(): void
    {
        $preferred = $this->provider(['tier_id' => $this->gold->id, 'is_preferred' => true]);
        $this->offers($preferred, 10, 1); // 0.1

        $strong = $this->provider(['tier_id' => $this->platinum->id, 'is_preferred' => false]);
        $this->offers($strong, 10, 10); // 1.0

        $case = $this->case();

        $this->assertSame([$preferred->id, $strong->id], $this->order($case, $strong, $preferred));
    }

    public function test_tier_beats_response_rate(): void
    {
        $platinumBad = $this->provider(['tier_id' => $this->platinum->id]);
        $this->offers($platinumBad, 10, 0); // 0.0

        $goldPerfect = $this->provider(['tier_id' => $this->gold->id]);
        $this->offers($goldPerfect, 10, 10); // 1.0

        $case = $this->case();

        $this->assertSame([$platinumBad->id, $goldPerfect->id], $this->order($case, $goldPerfect, $platinumBad));
    }

    public function test_response_rate_breaks_ties_within_a_tier(): void
    {
        $high = $this->provider(['tier_id' => $this->gold->id]);
        $this->offers($high, 10, 8); // 0.8

        $low = $this->provider(['tier_id' => $this->gold->id]);
        $this->offers($low, 10, 3); // 0.3

        $case = $this->case();

        $this->assertSame([$high->id, $low->id], $this->order($case, $low, $high));
    }

    public function test_cold_start_provider_scores_a_perfect_response_rate(): void
    {
        $newbie = $this->provider(['tier_id' => $this->gold->id]); // zero offers received → 1.0
        $veteran = $this->provider(['tier_id' => $this->gold->id]);
        $this->offers($veteran, 10, 5); // 0.5

        $case = $this->case();

        // No history beats a real 0.5 rate — new providers start perfect until they have history.
        $this->assertSame([$newbie->id, $veteran->id], $this->order($case, $veteran, $newbie));
    }

    public function test_a_pending_offer_is_excluded_from_the_response_rate_denominator(): void
    {
        // X resolved 1/1 = 1.0, plus a pending offer that must NOT sink it to 0.5.
        $x = $this->provider(['tier_id' => $this->gold->id]);
        $this->offers($x, 1, 1);
        $this->offer($x, AssignmentOffer::STATUS_PENDING);

        $y = $this->provider(['tier_id' => $this->gold->id]);
        $this->offers($y, 2, 1); // real 0.5

        // Only passes if pending is excluded: then X = 1.0 > Y = 0.5.
        $this->assertSame([$x->id, $y->id], $this->order($this->case(), $y, $x));
    }

    public function test_a_withdrawn_offer_is_excluded_from_the_response_rate_denominator(): void
    {
        $x = $this->provider(['tier_id' => $this->gold->id]);
        $this->offers($x, 1, 1);
        $this->offer($x, AssignmentOffer::STATUS_WITHDRAWN);

        $y = $this->provider(['tier_id' => $this->gold->id]);
        $this->offers($y, 2, 1); // real 0.5

        $this->assertSame([$x->id, $y->id], $this->order($this->case(), $y, $x));
    }

    private const SUBJECT_LAT = 26.82;

    private const SUBJECT_LNG = -80.05;

    public function test_distance_breaks_a_tie_closest_first(): void
    {
        // Identical on every higher signal (same tier, not preferred/requested, zero offers
        // => 1.0), so only distance can decide.
        $near = $this->provider(['tier_id' => $this->gold->id, 'latitude' => 26.83, 'longitude' => self::SUBJECT_LNG]);
        $far = $this->provider(['tier_id' => $this->gold->id, 'latitude' => 27.20, 'longitude' => self::SUBJECT_LNG]);

        $case = $this->case([], ['latitude' => self::SUBJECT_LAT, 'longitude' => self::SUBJECT_LNG]);

        $this->assertSame([$near->id, $far->id], $this->order($case, $far, $near));

        // Flip the coords onto fresh providers: order must follow distance, not seed order.
        $wasFar = $this->provider(['tier_id' => $this->gold->id, 'latitude' => 27.20, 'longitude' => self::SUBJECT_LNG]);
        $wasNear = $this->provider(['tier_id' => $this->gold->id, 'latitude' => 26.83, 'longitude' => self::SUBJECT_LNG]);

        $this->assertSame([$wasNear->id, $wasFar->id], $this->order($case, $wasFar, $wasNear));
    }

    public function test_a_higher_signal_outranks_a_closer_provider(): void
    {
        // Preferred but far vs plain but near — preferred (signal 2) must win over distance (5).
        $farPreferred = $this->provider(['tier_id' => $this->gold->id, 'is_preferred' => true, 'latitude' => 27.20, 'longitude' => self::SUBJECT_LNG]);
        $nearPlain = $this->provider(['tier_id' => $this->gold->id, 'is_preferred' => false, 'latitude' => 26.83, 'longitude' => self::SUBJECT_LNG]);

        $case = $this->case([], ['latitude' => self::SUBJECT_LAT, 'longitude' => self::SUBJECT_LNG]);

        $this->assertSame([$farPreferred->id, $nearPlain->id], $this->order($case, $nearPlain, $farPreferred));
    }

    public function test_scorer_query_count_is_constant_regardless_of_pool_size(): void
    {
        $case = $this->case([], ['latitude' => self::SUBJECT_LAT, 'longitude' => self::SUBJECT_LNG]);

        $smallCount = $this->queryCountForPoolOf($case->id, 12);
        $largeCount = $this->queryCountForPoolOf($case->id, 24);

        $this->assertSame($smallCount, $largeCount, 'scorer query count must not grow with pool size');
        // tier-max + grouped rates + the case subject. All constant in the pool size; distance
        // is pure-PHP and tier arrives eager-loaded exactly as MatchingEngine hands it over.
        $this->assertLessThanOrEqual(3, $smallCount);
    }

    private function queryCountForPoolOf(int $caseId, int $size): int
    {
        $ids = collect(range(1, $size))->map(function (): int {
            $p = $this->provider(['tier_id' => $this->gold->id]);
            $this->offers($p, 2, 1);

            return $p->id;
        });

        // MatchingEngine hands the scorer providers with tier eager-loaded (->with(['tier',
        // 'languages'])); mirror that so the count reflects the real dispatch input, not a
        // factory-only lazy load. Reload AFTER building so setup queries don't pollute the count.
        $pool = Provider::with('tier')->whereIn('id', $ids)->get();

        // Fresh case each call: order()'s one-time $case->subject load caches on the model, so
        // reusing a case would make the second measurement cheaper and confound the comparison.
        $case = IntakeRequest::findOrFail($caseId);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->scorer()->order($case, $pool);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
