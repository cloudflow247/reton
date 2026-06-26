<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Services\PinService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SetPinRequest;
use App\Http\Requests\Api\V1\Auth\VerifyPinRequest;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class PinController extends Controller
{
    public function __construct(private readonly PinService $pins) {}

    public function set(SetPinRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->pins->set(
            $user,
            $request->string('pin')->toString(),
            $request->filled('current_pin') ? $request->string('current_pin')->toString() : null,
        );

        return ApiResponse::noContent('Transaction PIN updated.');
    }

    public function verify(VerifyPinRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->pins->verify($user, $request->string('pin')->toString());

        return ApiResponse::noContent('PIN verified.');
    }
}
