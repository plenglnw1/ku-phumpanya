<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class RedirectAfterLogin
{
    public static function for(User $user): string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $backend = rtrim((string) config('app.url'), '/');

        // Admin always lives on the Laravel host, even when the public frontend is elsewhere.
        return $user->isAdmin()
            ? $backend.'/filament'
            : $frontend.'/';
    }
}
