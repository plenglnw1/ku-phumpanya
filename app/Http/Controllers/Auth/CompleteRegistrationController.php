<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompleteUserProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteRegistrationRequest;
use App\Support\RedirectAfterLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompleteRegistrationController extends Controller
{
    public function __construct(
        private readonly CompleteUserProfile $completeUserProfile,
    ) {}

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
        $user = $this->completeUserProfile->execute($request);

        return redirect(RedirectAfterLogin::for($user));
    }
}
