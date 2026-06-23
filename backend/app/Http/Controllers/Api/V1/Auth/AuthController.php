<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Data\DeviceContext;
use App\Domain\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Resources\Api\V1\WalletResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register(
            $request->validated(),
            DeviceContext::fromRequest($request),
        );

        return ApiResponse::created([
            'user' => new UserResource($user),
            'token' => $this->issueToken($user, $request),
        ], 'Account created.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->auth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            DeviceContext::fromRequest($request),
        );

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $this->issueToken($user, $request),
        ], 'Signed in.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success([
            'user' => new UserResource($user),
            'wallets' => WalletResource::collection($user->wallets()->get()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var PersonalAccessToken|null $token */
        $token = $request->user()?->currentAccessToken();
        $token?->delete();

        return ApiResponse::noContent('Signed out.');
    }

    private function issueToken(User $user, Request $request): string
    {
        $name = $request->header('X-Device-Name') ?? 'api';

        return $user->createToken($name)->plainTextToken;
    }
}
