<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'account_id',
    'google_id',
    'avatar_url',
    'profile_completed_at',
    'faculty',
    'department',
    'student_id',
    'employee_id',
    'research_affiliation',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function hasCompletedProfile(): bool
    {
        return $this->profile_completed_at !== null;
    }

    /**
     * @return list<string>
     */
    public static function profileFieldsForRole(UserRole $role): array
    {
        return match ($role) {
            UserRole::Student => ['faculty', 'department', 'student_id'],
            UserRole::Researcher => ['faculty', 'department', 'employee_id', 'research_affiliation'],
            UserRole::Teacher => ['faculty', 'department', 'employee_id'],
            UserRole::Admin => ['faculty', 'department', 'employee_id'],
        };
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }
}
