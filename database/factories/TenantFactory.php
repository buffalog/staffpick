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
            // A distinct domain per tenant: the tenants_domain_unique index allows
            // only one NULL on SQL Server, so factories must not leave it NULL when
            // multiple tenants are created in a run.
            'domain' => Str::uuid()->toString().'.test',
        ];
    }
}
