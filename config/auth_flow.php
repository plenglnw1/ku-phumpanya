<?php

declare(strict_types=1);

return [

    'password_enabled' => env('AUTH_PASSWORD_ENABLED', false),

    'allowed_roles_on_register' => [
        'student',
        'researcher',
        'teacher',
    ],

];
