<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ReferenceController extends Controller
{
    public function faculties(): JsonResponse
    {
        return response()->json([
            'faculties' => config('ku_faculties.faculties', []),
        ]);
    }

    public function roles(): JsonResponse
    {
        $allowed = config('auth_flow.allowed_roles_on_register', []);

        $roles = collect($allowed)
            ->map(function (string $value): array {
                $role = UserRole::from($value);

                return [
                    'value' => $role->value,
                    'label' => $role->label(),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'roles' => $roles,
        ]);
    }
}
