<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_complete_registration(): void
    {
        $user = User::factory()->profileIncomplete()->withGoogle()->create();

        $response = $this->actingAs($user)->post('/register/complete', [
            'role' => 'student',
            'faculty' => 'เกษตรศาสตร์',
            'department' => 'วนศาสตร์',
            'student_id' => '6410000001',
        ]);

        $response->assertRedirect(route('search.index', absolute: false));

        $user->refresh();
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertSame('6410000001', $user->student_id);
        $this->assertNotNull($user->profile_completed_at);
    }

    public function test_researcher_can_complete_registration(): void
    {
        $user = User::factory()->profileIncomplete()->withGoogle()->create();

        $response = $this->actingAs($user)->post('/register/complete', [
            'role' => 'researcher',
            'faculty' => 'วนศาสตร์',
            'department' => 'BCG Research',
            'employee_id' => '10000001',
            'research_affiliation' => 'ศูนย์วิจัย BCG',
        ]);

        $response->assertRedirect(route('search.index', absolute: false));

        $user->refresh();
        $this->assertSame(UserRole::Researcher, $user->role);
        $this->assertSame('10000001', $user->employee_id);
    }

    public function test_teacher_requires_employee_id(): void
    {
        $user = User::factory()->profileIncomplete()->withGoogle()->create();

        $response = $this->actingAs($user)->post('/register/complete', [
            'role' => 'teacher',
            'faculty' => 'อุตสาหกรรมเกษตร',
            'department' => 'เทคโนโลยีชีวภาพ',
        ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_admin_role_cannot_be_selected(): void
    {
        $user = User::factory()->profileIncomplete()->withGoogle()->create();

        $response = $this->actingAs($user)->post('/register/complete', [
            'role' => 'admin',
            'faculty' => 'เกษตรศาสตร์',
            'department' => 'วนศาสตร์',
            'employee_id' => '99999999',
        ]);

        $response->assertSessionHasErrors('role');
    }
}
