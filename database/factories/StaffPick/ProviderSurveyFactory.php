<?php

namespace Database\Factories\StaffPick;

use App\Models\StaffPick\ProviderSurvey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderSurvey>
 */
class ProviderSurveyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rating' => null,
            'comment' => null,
            'sent_at' => now(),
            'responded_at' => null,
            'delivery_channel' => ProviderSurvey::CHANNEL_SMS,
            'status' => ProviderSurvey::STATUS_PENDING,
        ];
    }

    /**
     * A completed survey response with a rating and a response timestamp.
     */
    public function responded(int $rating, ?\DateTimeInterface $respondedAt = null): static
    {
        return $this->state(fn (): array => [
            'rating' => $rating,
            'responded_at' => $respondedAt ?? now(),
            'status' => ProviderSurvey::STATUS_RESPONDED,
        ]);
    }
}
