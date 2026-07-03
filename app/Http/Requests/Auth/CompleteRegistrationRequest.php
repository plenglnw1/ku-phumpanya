<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = UserRole::tryFrom((string) $this->input('role'));

        $faculties = config('ku_faculties.faculties', []);

        return [
            'role' => [
                'required',
                'string',
                Rule::in(config('auth_flow.allowed_roles_on_register', [])),
            ],
            'faculty' => ['required', 'string', Rule::in($faculties)],
            'department' => ['required', 'string', 'max:255'],
            'student_id' => [
                Rule::requiredIf($role === UserRole::Student),
                'nullable',
                'string',
                'regex:/^\d{10}$/',
                Rule::unique(User::class, 'student_id')->ignore($this->user()?->id),
            ],
            'employee_id' => [
                Rule::requiredIf(in_array($role, [UserRole::Researcher, UserRole::Teacher], true)),
                'nullable',
                'string',
                'max:20',
                Rule::unique(User::class, 'employee_id')->ignore($this->user()?->id),
            ],
            'research_affiliation' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.regex' => 'รหัสนิสิตต้องเป็นตัวเลข 10 หลัก',
            'role.in' => 'ไม่สามารถเลือกบทบาทนี้ได้',
        ];
    }
}
