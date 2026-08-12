<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'uuid' => fake()->uuid(),
            // A distinct domain per tenant. tenants_domain_unique is partial (WHERE domain
            // IS NOT NULL) so NULLs would be fine, but a real value keeps factory-made
            // tenants distinguishable.
            'domain' => Str::uuid()->toString().'.test',
        ];
    }
}
