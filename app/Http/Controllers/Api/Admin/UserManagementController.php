<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UserManagementController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $role = UserRole::tryFrom((string) $request->input('role'));
        $faculties = config('ku_faculties.faculties', []);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', 'string', Rule::enum(UserRole::class)],
            'faculty' => ['required', 'string', Rule::in($faculties)],
            'department' => ['required', 'string', 'max:255'],
            'student_id' => [
                Rule::requiredIf($role === UserRole::Student),
                'nullable',
                'string',
                'regex:/^\d{10}$/',
                Rule::unique(User::class, 'student_id'),
            ],
            'employee_id' => [
                Rule::requiredIf(in_array($role, [UserRole::Researcher, UserRole::Teacher, UserRole::Admin], true)),
                'nullable',
                'string',
                'max:20',
                Rule::unique(User::class, 'employee_id'),
            ],
            'research_affiliation' => ['nullable', 'string', 'max:255'],
        ], [
            'student_id.regex' => 'รหัสนิสิตต้องเป็นตัวเลข 10 หลัก',
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $role,
            'faculty' => $validated['faculty'],
            'department' => $validated['department'],
            'student_id' => null,
            'employee_id' => null,
            'research_affiliation' => null,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ];

        if ($role === UserRole::Student) {
            $payload['student_id'] = $validated['student_id'] ?? null;
        }

        if (in_array($role, [UserRole::Researcher, UserRole::Teacher, UserRole::Admin], true)) {
            $payload['employee_id'] = $validated['employee_id'] ?? null;
        }

        if ($role === UserRole::Researcher) {
            $payload['research_affiliation'] = $validated['research_affiliation'] ?? null;
        }

        $user = User::query()->create($payload);

        return response()->json($user, 201);
    }
}
