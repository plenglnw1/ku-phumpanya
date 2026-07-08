<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculties_are_public(): void
    {
        $this->getJson('/api/reference/faculties')
            ->assertOk()
            ->assertJsonStructure(['faculties'])
            ->assertJsonFragment(['faculties' => config('ku_faculties.faculties')]);
    }

    public function test_roles_are_public(): void
    {
        $this->getJson('/api/reference/roles')
            ->assertOk()
            ->assertJsonStructure([
                'roles' => [
                    '*' => ['value', 'label'],
                ],
            ])
            ->assertJsonFragment(['value' => 'student', 'label' => 'Student']);
    }
}
