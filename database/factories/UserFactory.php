<?php

namespace Database\Factories;

use App\Enums\UserRole;
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
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Student,
            'faculty' => 'เกษตรศาสตร์',
            'department' => 'วนศาสตร์',
            'student_id' => fake()->unique()->numerify('##########'),
            'profile_completed_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function profileIncomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'profile_completed_at' => null,
            'faculty' => null,
            'department' => null,
            'student_id' => null,
            'employee_id' => null,
            'research_affiliation' => null,
        ]);
    }

    public function withGoogle(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_id' => (string) fake()->unique()->numerify('####################'),
            'password' => null,
        ]);
    }

    public function researcher(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Researcher,
            'student_id' => null,
            'employee_id' => fake()->unique()->numerify('########'),
            'research_affiliation' => 'ศูนย์วิจัย BCG',
        ]);
    }

    public function teacher(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Teacher,
            'student_id' => null,
            'employee_id' => fake()->unique()->numerify('########'),
        ]);
    }
}
