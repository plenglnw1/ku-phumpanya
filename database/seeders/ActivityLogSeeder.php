<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();

        $rows = [
            ['ip' => '186.22.14.8', 'action' => 'login', 'status' => 'success'],
            ['ip' => '10.0.0.42', 'action' => 'register', 'status' => 'success'],
            [
                'ip' => '203.150.1.9',
                'action' => 'edit data',
                'status' => 'success',
                'before' => ['name' => 'worrapon'],
                'after' => ['name' => 'manus'],
            ],
            ['ip' => '172.16.0.5', 'action' => 'login', 'status' => 'failed'],
            ['ip' => '192.168.1.100', 'action' => 'login', 'status' => 'success'],
        ];

        foreach ($rows as $index => $row) {
            ActivityLog::query()->create([
                'ip' => $row['ip'],
                'action' => $row['action'],
                'user_id' => $user?->id,
                'before' => $row['before'] ?? null,
                'after' => $row['after'] ?? null,
                'status' => $row['status'],
                'created_at' => now()->subHours($index * 3),
            ]);
        }

        ActivityLog::factory()->count(15)->create();
    }
}
