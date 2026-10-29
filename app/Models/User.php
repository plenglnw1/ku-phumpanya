<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
#[Hidden(['password', 'remember_token', 'google_id'])]
#[Appends(['consent_required'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'consented_at' => 'datetime',
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
     * True until the current privacy notice version is accepted, so a new notice
     * version asks everyone again.
     *
     * @return Attribute<bool, never>
     */
    protected function consentRequired(): Attribute
    {
        return Attribute::get(fn (): bool => $this->consented_at === null
            || $this->consent_version !== (string) config('pdpa.consent_version'));
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
