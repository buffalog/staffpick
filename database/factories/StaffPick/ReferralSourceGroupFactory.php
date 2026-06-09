<?php

namespace Database\Factories\StaffPick;

use App\Models\StaffPick\ReferralSourceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralSourceGroup>
 */
class ReferralSourceGroupFactory extends Factory
{
    protected $model = ReferralSourceGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'is_active' => true,
        ];
    }
}
