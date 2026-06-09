<?php

namespace Database\Factories\StaffPick;

use App\Models\StaffPick\ReferralSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralSource>
 */
class ReferralSourceFactory extends Factory
{
    protected $model = ReferralSource::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip' => fake()->postcode(),
            'phone' => fake()->numerify('##########'),
            'email' => fake()->unique()->companyEmail(),
            'status' => 'active',
            'billing_terms_days' => 14,
        ];
    }
}
