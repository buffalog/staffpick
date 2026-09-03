<?php

namespace Database\Factories\StaffPick;

use App\Models\StaffPick\IntakeRequest;
use App\Models\StaffPick\Subject;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntakeRequest>
 */
class IntakeRequestFactory extends Factory
{
    protected $model = IntakeRequest::class;

    public function definition(): array
    {
        return [
            'reference_number' => fake()->unique()->bothify('CASE-#####'),
            'status' => IntakeRequest::STATUS_UNMATCHED,
            'visits_completed' => 0,
            // Subject shares the intake request's tenant; sp_subjects.tenant_id is NOT NULL.
            'subject_id' => fn (array $attributes) => Subject::factory()->create([
                'tenant_id' => $attributes['tenant_id'] ?? Tenant::factory(),
            ])->id,
        ];
    }
}
