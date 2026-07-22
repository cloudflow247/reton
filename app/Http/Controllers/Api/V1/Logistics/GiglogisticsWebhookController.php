<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Domain\Logistics\Giglogistics\Services\GiglogisticsWebhookGuard;
use App\Domain\Logistics\Giglogistics\Services\GiglogisticsWebhookService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public Giglogistics webhook receiver - trust via HMAC, not Sanctum.
 */
class GiglogisticsWebhookController extends Controller
{
    public function __construct(
        private readonly GiglogisticsWebhookGuard $guard,
        private readonly GiglogisticsWebhookService $webhooks,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Giglogistics-Signature');
        $signature = is_string($signature) ? $signature : null;

        $this->guard->verify($request->getContent(), $signature);

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $event = (string) ($payload['event'] ?? $request->header('X-Giglogistics-Event', ''));

        $this->webhooks->handle($event, $payload);

        return ApiResponse::success(null, 'Webhook received.');
    }
}
