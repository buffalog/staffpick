<?php

namespace Database\Factories\StaffPick;

use App\Models\StaffPick\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('##########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip' => fake()->postcode(),
            // Left null by default: the local FreeTDS (pdo_dblib) driver returns
            // SQL Server `date` columns in a format Carbon can't parse, which
            // breaks rendering in tests. Railway's pdo_sqlsrv handles them fine.
            'date_of_birth' => null,
            'gender' => fake()->randomElement(['male', 'female', 'non_binary', 'other']),
            'is_active' => true,
        ];
    }
}
