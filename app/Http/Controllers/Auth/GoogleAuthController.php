<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RedirectAfterLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        // KU's Apache WAF 403s any query string containing "userinfo.profile", which Google appends
        // to the callback scope whenever the `profile` scope is granted. Ask for openid+email only.
        return Socialite::driver('google')->setScopes(['openid', 'email'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            Log::warning('google.callback.invalid_state', [
                'has_state' => $request->has('state'),
                'has_session_cookie' => $request->hasCookie(config('session.cookie')),
            ]);

            return $this->failed('session');
        } catch (Throwable $e) {
            Log::error('google.callback.provider', ['exception' => $e]);

            return $this->failed('google');
        }

        try {
            $email = $googleUser->getEmail();
            $user = User::query()->where('google_id', $googleUser->getId())->first()
                ?? (filled($email) ? User::query()->where('email', $email)->first() : null);

            if ($user !== null && $user->google_id !== null && $user->google_id !== $googleUser->getId()) {
                Log::warning('google.callback.google_id_conflict', ['user_id' => $user->id]);

                return $this->failed('account');
            }

            $user = DB::transaction(function () use ($user, $googleUser): User {
                if ($user === null) {
                    $user = new User([
                        'name' => $googleUser->getName() ?? $googleUser->getEmail(),
                        'email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'avatar_url' => $googleUser->getAvatar(),
                        'password' => null,
                    ]);
                    $user->email_verified_at = now();
                } else {
                    $user->fill([
                        'google_id' => $googleUser->getId(),
                        'name' => $user->name ?: ($googleUser->getName() ?? $googleUser->getEmail()),
                        'avatar_url' => $googleUser->getAvatar() ?? $user->avatar_url,
                    ]);
                    $user->email_verified_at ??= now();
                }

                $user->save();

                return $user;
            });
        } catch (Throwable $e) {
            Log::error('google.callback.user_upsert', [
                'email_domain' => Str::after((string) $googleUser->getEmail(), '@'),
                'exception' => $e,
            ]);

            return $this->failed('account');
        }

        try {
            Auth::login($user, remember: true);

            if (! $user->hasCompletedProfile()) {
                return redirect(rtrim((string) config('app.frontend_url'), '/').'/register/');
            }

            return redirect(RedirectAfterLogin::for($user));
        } catch (Throwable $e) {
            Log::error('google.callback.session_login', ['user_id' => $user->id, 'exception' => $e]);

            return $this->failed('session');
        }
    }

    private function failed(string $reason): RedirectResponse
    {
        return redirect(rtrim((string) config('app.frontend_url'), '/').'/?login_error='.$reason);
    }
}
