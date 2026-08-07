<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;

final class SessionController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! config('auth_flow.password_login_enabled')) {
            return response()->json([
                'message' => 'Password login is disabled.',
            ], 403);
        }

        if ($request->user() !== null) {
            return response()->json($request->user());
        }

        $request->authenticate();

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json($request->user());
    }
}
