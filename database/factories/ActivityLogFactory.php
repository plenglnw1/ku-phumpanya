<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        $actions = ['login', 'register', 'edit data', 'search', 'logout'];

        return [
            'ip' => fake()->ipv4(),
            'action' => fake()->randomElement($actions),
            'user_id' => User::factory(),
            'before' => null,
            'after' => null,
            'status' => fake()->randomElement(['success', 'failed']),
        ];
    }
}
