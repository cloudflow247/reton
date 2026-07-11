<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payments\Services\PayoutService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public Paystack webhook receiver. Trust is HMAC SHA512 via x-paystack-signature.
 */
class PaystackWebhookController extends Controller
{
    public function __construct(private readonly PayoutService $payouts) {}

    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Paystack-Signature');
        $signature = is_string($signature) ? $signature : null;

        $this->payouts->handlePaystackWebhook($request->getContent(), $signature);

        return ApiResponse::success(null, 'Webhook received.');
    }
}
