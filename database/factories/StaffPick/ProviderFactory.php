<?php

namespace Database\Factories\StaffPick;

use App\Models\StaffPick\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'business_name' => null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('##########'),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip' => fake()->postcode(),
            'is_contractor' => true,
            'radius_preferred_miles' => 15,
            'radius_max_miles' => 25,
            'status' => 'active',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactive',
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => fake()->sentence(),
        ]);
    }
}
