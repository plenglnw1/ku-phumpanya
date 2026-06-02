<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Student = 'student';
    case Researcher = 'researcher';
    case Teacher = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Student => 'Student',
            self::Researcher => 'Researcher',
            self::Teacher => 'Teacher',
        };
    }
}
