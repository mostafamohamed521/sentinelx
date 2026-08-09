<?php

namespace App\Modules\Authentication\Identity\API\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authentication\Identity\API\Requests\ChangePasswordRequest;
use App\Modules\Authentication\Identity\API\Requests\LoginRequest;
use App\Modules\Authentication\Identity\API\Requests\RegisterRequest;
use App\Modules\Authentication\Identity\API\Requests\UpdateProfileRequest;
use App\Modules\Authentication\Identity\Application\ChangePasswordAction;
use App\Modules\Authentication\Identity\Application\GetCurrentUserAction;
use App\Modules\Authentication\Identity\Application\LoginUserAction;
use App\Modules\Authentication\Identity\Application\LogoutUserAction;
use App\Modules\Authentication\Identity\Application\RefreshTokenAction;
use App\Modules\Authentication\Identity\Application\RegisterUserAction;
use App\Modules\Authentication\Identity\Application\UpdateProfileAction;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Authentication\Identity\Presentation\UserResource;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    // POST /api/v1/auth/register
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $user = $action->handle($request->validated());

        return (new UserResource($user))
            ->additional(['message' => 'Organization created. Please check your email to verify your account.'])
            ->response()
            ->setStatusCode(201);
    }

    // POST /api/v1/auth/login
    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $token = $action->handle($request->validated());

        return $this->tokenResponse($token);
    }

    // POST /api/v1/auth/logout
    public function logout(LogoutUserAction $action): JsonResponse
    {
        $action->handle();

        return response()->json(['message' => 'Logged out.']);
    }

    // POST /api/v1/auth/refresh
    public function refresh(RefreshTokenAction $action): JsonResponse
    {
        $token = $action->handle();

        return $this->tokenResponse($token);
    }

    // GET /api/v1/auth/me
    public function me(GetCurrentUserAction $action): UserResource
    {
        return new UserResource($action->handle());
    }

    // PATCH /api/v1/me — any authenticated Human, own record only
    public function updateProfile(UpdateProfileRequest $request, UpdateProfileAction $action): UserResource
    {
        /** @var User $user */
        $user = $request->user('api');

        return new UserResource($action->handle($user, $request->validated()));
    }

    // POST /api/v1/me/change-password — any authenticated Human, own record only
    public function changePassword(ChangePasswordRequest $request, ChangePasswordAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        $action->handle($user, $request->validated());

        return response()->json(['status' => 'success', 'message' => 'Password changed successfully']);
    }

    private function tokenResponse(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }
}
