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
            : route('search.index', absolute: false);
    }
}
