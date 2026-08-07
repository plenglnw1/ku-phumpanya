<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Password login (email/password session auth)
    |--------------------------------------------------------------------------
    |
    | When true, Blade /login and POST /api/auth/login accept email/password.
    | Google OAuth remains the primary path either way.
    |
    */
    'password_login_enabled' => env('AUTH_PASSWORD_LOGIN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Public password self-registration
    |--------------------------------------------------------------------------
    |
    | Kept off by default — new accounts are created by an admin from the
    | dashboard (or seeded). Does not affect Google OAuth profile completion.
    |
    */
    'password_register_enabled' => env('AUTH_PASSWORD_REGISTER_ENABLED', false),

    'allowed_roles_on_register' => [
        'student',
        'researcher',
        'teacher',
    ],

];
