<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LogoutUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\RedirectAfterLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly LogoutUser $logoutUser,
    ) {}

    /**
     * Display the login view.
     */
    public function create(): View|RedirectResponse
    {
        if (! config('auth_flow.password_enabled')) {
            return redirect()->route('welcome');
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (! config('auth_flow.password_enabled')) {
            return redirect()->route('welcome');
        }
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(RedirectAfterLogin::for($request->user()));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->logoutUser->execute($request);

        return redirect('/');
    }
}
