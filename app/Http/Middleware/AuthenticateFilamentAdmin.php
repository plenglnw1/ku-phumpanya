<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Auth\LogoutUser;
use Closure;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * The SPA and the admin panel share one session cookie, so a student who signed in
 * with Google would otherwise hit a 403 on /filament with no way to switch account.
 * Sign that session out and show the panel login instead.
 */
final class AuthenticateFilamentAdmin extends Authenticate
{
    public function handle($request, Closure $next, ...$guards): Response
    {
        $user = Filament::auth()->user();

        if ($user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            app(LogoutUser::class)->execute($request);

            return redirect()->to(Filament::getLoginUrl());
        }

        return parent::handle($request, $next, ...$guards);
    }
}
