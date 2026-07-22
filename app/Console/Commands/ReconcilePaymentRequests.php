<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Services\PaymentRequestService;
use Illuminate\Console\Command;

/**
 * Reconciles pending payment requests against AlatPay - a safety net for missed
 * or delayed webhooks. Only requests old enough to have settled are checked.
 */
class ReconcilePaymentRequests extends Command
{
    protected $signature = 'payment-requests:reconcile';

    protected $description = 'Reconcile pending AlatPay payment requests against the provider';

    public function handle(PaymentRequestService $requests): int
    {
        $credited = 0;

        PaymentRequest::query()
            ->where('status', PaymentRequestStatus::Pending->value)
            ->whereNotNull('provider_reference')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->orderBy('created_at')
            ->each(function (PaymentRequest $request) use ($requests, &$credited): void {
                if ($requests->reconcile($request)) {
                    $credited++;
                }
            });

        $this->info("Reconciled {$credited} payment request(s).");

        return self::SUCCESS;
    }
}
