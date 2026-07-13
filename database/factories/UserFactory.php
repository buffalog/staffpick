<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Monotonic per-process counter used to make generated emails unique for a whole run.
     *
     * fake()->unique() is NOT sufficient here: Laravel rebuilds the application (and with it
     * the Faker generator, which owns the unique() tracker) for every test, so it only dedupes
     * within a single test. The suite shares one database with no rollback (see FeatureTest),
     * and safeEmail() draws from a small pool, so two tests eventually roll the same address
     * and collide on users_email_unique. That made CI fail randomly depending on how many
     * users earlier tests happened to create.
     */
    private static int $emailSequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => sprintf(
                '%s%d@example.net',
                Str::before(fake()->unique()->safeEmail(), '@'),
                ++static::$emailSequence,
            ),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
