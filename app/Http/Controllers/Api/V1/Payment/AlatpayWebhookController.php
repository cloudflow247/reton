<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payments\Services\AlatpayWebhookRouter;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (unauthenticated) AlatPay webhook receiver. Trust is established by the
 * HMAC signature, verified inside the router/guard. The router dispatches each
 * event to the owning domain (payouts, payment requests, or deposits).
 */
class AlatpayWebhookController extends Controller
{
    public function __construct(private readonly AlatpayWebhookRouter $router) {}

    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Alatpay-Signature');
        $signature = is_string($signature) ? $signature : null;

        $this->router->handle($request->getContent(), $signature);

        return ApiResponse::success(null, 'Webhook received.');
    }
}
