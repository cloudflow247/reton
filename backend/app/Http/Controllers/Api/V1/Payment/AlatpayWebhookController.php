<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Payments\Services\AlatpayDepositService;
use App\Domain\Payments\Services\PayoutService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public (unauthenticated) AlatPay webhook receiver. Trust is established by the
 * HMAC signature, verified inside the handling service. Events are routed by
 * type: collections credit deposits, transfers settle payouts.
 */
class AlatpayWebhookController extends Controller
{
    public function __construct(
        private readonly AlatpayDepositService $deposits,
        private readonly PayoutService $payouts,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = $request->header('X-Alatpay-Signature');
        $signature = is_string($signature) ? $signature : null;

        $type = (string) (json_decode($raw, true)['type'] ?? '');

        if (Str::startsWith($type, 'transfer')) {
            $this->payouts->handleWebhook($raw, $signature);
        } else {
            $this->deposits->handleWebhook($raw, $signature);
        }

        return ApiResponse::success(null, 'Webhook received.');
    }
}
