<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@phumpanya.test',
                'account_id' => 'admin@phumpanya.test',
                'role' => UserRole::Admin,
            ],
            [
                'name' => 'Jane Doe',
                'email' => 'student@phumpanya.test',
                'account_id' => null,
                'role' => UserRole::Student,
            ],
            [
                'name' => 'Research Fellow',
                'email' => 'researcher@phumpanya.test',
                'account_id' => null,
                'role' => UserRole::Researcher,
            ],
            [
                'name' => 'Sunisa S.',
                'email' => 'teacher@phumpanya.test',
                'account_id' => null,
                'role' => UserRole::Teacher,
            ],
        ];

        foreach ($users as $data) {
            User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'account_id' => $data['account_id'],
                    'role' => $data['role'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
        }

        $this->call([
            ActivityLogSeeder::class,
            SearchHistorySeeder::class,
        ]);
    }
}
