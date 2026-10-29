<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($user);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json($user->fresh());
    }

    /**
     * Deletes the account; search history and progress cascade with it. Google
     * accounts have no password, so they confirm by typing their email instead.
     */
    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->password === null) {
            $request->validateWithBag('userDeletion', [
                'confirm_email' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if (mb_strtolower(trim((string) $value)) !== mb_strtolower($user->email)) {
                        $fail('อีเมลไม่ตรงกับบัญชีนี้');
                    }
                }],
            ]);
        } else {
            $request->validateWithBag('userDeletion', [
                'password' => ['required', 'current_password'],
            ]);
        }

        Auth::guard('web')->logout();
        $user->delete();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
