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

    /**
     * Mirror a factory-set discipline_id into the sp_provider_disciplines pivot as the
     * primary, so factory-built providers satisfy the matching engine's whereHas on the
     * disciplines set (real providers always have a pivot row; see the backfill migration).
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Provider $provider): void {
            if ($provider->discipline_id !== null && $provider->disciplines()->doesntExist()) {
                $provider->disciplines()->attach($provider->discipline_id, ['is_primary' => true]);
            }
        });
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
