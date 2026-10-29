<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Records the user's acceptance of the privacy notice (PDPA). */
final class ConsentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'consented_at' => now(),
            'consent_version' => (string) config('pdpa.consent_version'),
        ])->save();

        return response()->json($user->fresh());
    }
}
