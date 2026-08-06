<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RedirectAfterLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user === null) {
            $user = User::create([
                'name' => $googleUser->getName() ?? $googleUser->getEmail(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => null,
            ]);
        } else {
            $user->fill([
                'google_id' => $googleUser->getId(),
                'name' => $user->name ?: ($googleUser->getName() ?? $googleUser->getEmail()),
                'avatar_url' => $googleUser->getAvatar() ?? $user->avatar_url,
            ]);

            if ($user->email_verified_at === null) {
                $user->email_verified_at = now();
            }

            $user->save();
        }

        Auth::login($user, remember: true);

        if (! $user->hasCompletedProfile()) {
            return redirect(rtrim((string) config('app.frontend_url'), '/').'/register/');
        }

        return redirect(RedirectAfterLogin::for($user));
    }
}
