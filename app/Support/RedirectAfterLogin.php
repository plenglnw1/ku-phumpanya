<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class RedirectAfterLogin
{
    public static function for(User $user): string
    {
        return $user->isAdmin()
            ? url('/admin')
            : rtrim((string) config('app.frontend_url'), '/').'/learn/';
    }
}
