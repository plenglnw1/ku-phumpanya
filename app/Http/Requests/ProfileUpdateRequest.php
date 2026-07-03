<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $role = $user?->role;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user?->id),
            ],
            'faculty' => ['nullable', 'string', Rule::in(config('ku_faculties.faculties', []))],
            'department' => ['nullable', 'string', 'max:255'],
            'student_id' => [
                Rule::requiredIf($role === UserRole::Student),
                'nullable',
                'string',
                'regex:/^\d{10}$/',
                Rule::unique(User::class, 'student_id')->ignore($user?->id),
            ],
            'employee_id' => [
                Rule::requiredIf(in_array($role, [UserRole::Researcher, UserRole::Teacher], true)),
                'nullable',
                'string',
                'max:20',
                Rule::unique(User::class, 'employee_id')->ignore($user?->id),
            ],
            'research_affiliation' => ['nullable', 'string', 'max:255'],
        ];
    }
}
