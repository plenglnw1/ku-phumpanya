<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->hasCompletedProfile()) {
            $exempt = $request->routeIs('register.complete', 'logout')
                || $request->is('api/auth/register/complete', 'api/logout');

            if (! $exempt) {
                if ($request->is('api/*')) {
                    abort(403);
                }

                return redirect()->route('register.complete');
            }
        }

        return $next($request);
    }
}
