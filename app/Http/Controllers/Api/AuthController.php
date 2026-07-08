<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Auth\CompleteUserProfile;
use App\Actions\Auth\LogoutUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteRegistrationRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthController extends Controller
{
    public function __construct(
        private readonly CompleteUserProfile $completeUserProfile,
        private readonly LogoutUser $logoutUser,
    ) {}

    public function completeRegistration(CompleteRegistrationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasCompletedProfile()) {
            return response()->json($user->fresh());
        }

        $user = $this->completeUserProfile->execute($request);

        return response()->json($user);
    }

    public function logout(Request $request): Response
    {
        $this->logoutUser->execute($request);

        return response()->noContent();
    }
}
