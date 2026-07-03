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
                'faculty' => 'สำนักวิชาพหุวิทยาการ',
                'department' => 'ระบบสารสนเทศ',
                'employee_id' => '00000001',
            ],
            [
                'name' => 'Jane Doe',
                'email' => 'student@phumpanya.test',
                'account_id' => null,
                'role' => UserRole::Student,
                'faculty' => 'เกษตรศาสตร์',
                'department' => 'วนศาสตร์',
                'student_id' => '6410000001',
            ],
            [
                'name' => 'Research Fellow',
                'email' => 'researcher@phumpanya.test',
                'account_id' => null,
                'role' => UserRole::Researcher,
                'faculty' => 'วนศาสตร์',
                'department' => 'BCG Research',
                'employee_id' => '10000001',
                'research_affiliation' => 'ศูนย์วิจัย BCG',
            ],
            [
                'name' => 'Sunisa S.',
                'email' => 'teacher@phumpanya.test',
                'account_id' => null,
                'role' => UserRole::Teacher,
                'faculty' => 'อุตสาหกรรมเกษตร',
                'department' => 'เทคโนโลยีชีวภาพ',
                'employee_id' => '20000001',
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
                    'profile_completed_at' => now(),
                    'faculty' => $data['faculty'] ?? null,
                    'department' => $data['department'] ?? null,
                    'student_id' => $data['student_id'] ?? null,
                    'employee_id' => $data['employee_id'] ?? null,
                    'research_affiliation' => $data['research_affiliation'] ?? null,
                ],
            );
        }

        $this->call([
            ActivityLogSeeder::class,
            SearchHistorySeeder::class,
        ]);
    }
}
