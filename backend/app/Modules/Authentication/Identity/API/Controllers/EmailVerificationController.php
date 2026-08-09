<?php

namespace App\Modules\Authentication\Identity\API\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authentication\Identity\Application\ResendVerificationEmailAction;
use App\Modules\Authentication\Identity\Application\VerifyEmailAction;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    // GET /api/v1/auth/verify-email/{id}/{hash}
    public function verify(Request $request, string $id, string $hash, VerifyEmailAction $action): JsonResponse
    {
        return response()->json(['message' => $action->handle($id, $hash)]);
    }

    // POST /api/v1/auth/email/resend
    public function resend(Request $request, ResendVerificationEmailAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        return response()->json(['message' => $action->handle($user)]);
    }
}
