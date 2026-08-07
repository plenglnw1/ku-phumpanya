<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class RedirectAfterLogin
{
    public static function for(User $user): string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        // Next.js owns /admin/ (product dashboard). Filament lives at /filament.
        return $user->isAdmin()
            ? $frontend.'/admin/'
            : $frontend.'/';
    }
}
