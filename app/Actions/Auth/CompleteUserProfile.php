<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Http\Requests\Auth\CompleteRegistrationRequest;
use App\Models\User;

final class CompleteUserProfile
{
    public function execute(CompleteRegistrationRequest $request): User
    {
        $user = $request->user();
        $role = UserRole::from($request->validated('role'));

        $payload = [
            'role' => $role,
            'faculty' => $request->validated('faculty'),
            'department' => $request->validated('department'),
            'profile_completed_at' => now(),
            'student_id' => null,
            'employee_id' => null,
            'research_affiliation' => null,
        ];

        if ($role === UserRole::Student) {
            $payload['student_id'] = $request->validated('student_id');
        }

        if (in_array($role, [UserRole::Researcher, UserRole::Teacher], true)) {
            $payload['employee_id'] = $request->validated('employee_id');
        }

        if ($role === UserRole::Researcher) {
            $payload['research_affiliation'] = $request->validated('research_affiliation');
        }

        $user->update($payload);

        return $user->fresh();
    }
}
