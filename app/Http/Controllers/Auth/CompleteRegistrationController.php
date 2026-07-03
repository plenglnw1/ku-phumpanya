<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteRegistrationRequest;
use App\Support\RedirectAfterLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompleteRegistrationController extends Controller
{
    public function create(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return redirect()->route('welcome');
        }

        if ($user->hasCompletedProfile()) {
            return redirect(RedirectAfterLogin::for($user));
        }

        return view('auth.register-complete', [
            'user' => $user,
            'faculties' => config('ku_faculties.faculties', []),
        ]);
    }

    public function store(CompleteRegistrationRequest $request): RedirectResponse
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

        return redirect(RedirectAfterLogin::for($user->fresh()));
    }
}
