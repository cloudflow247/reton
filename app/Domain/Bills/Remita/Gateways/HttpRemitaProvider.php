<?php

declare(strict_types=1);

namespace App\Domain\Bills\Remita\Gateways;

use App\Domain\Bills\Remita\Contracts\BillProviderGateway;
use App\Domain\Bills\Remita\Data\BillPaymentInstruction;
use App\Domain\Bills\Remita\Data\BillProviderResult;
use App\Domain\Bills\Remita\Data\RemoteBill;
use App\Domain\Bills\Remita\Data\RrrInquiry;
use App\Domain\Bills\Remita\Exceptions\BillProviderException;
use App\Support\Money\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Live Remita integration over HTTP. Amounts cross the wire in minor units
 * (kobo); Remita's API speaks major units, so they are converted at the edge.
 *
 * Endpoint shapes follow Remita's RRR query + bill-payment API. Requests are
 * authenticated with an API-key/secret pair read from config.
 */
class HttpRemitaProvider implements BillProviderGateway
{
    public function lookupRrr(string $rrr): ?RrrInquiry
    {
        $response = $this->client()->get('/billpayment/api/v1/rrr/'.$rrr.'/status');

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw BillProviderException::requestFailed('lookupRrr', $response->status());
        }

        $data = (array) $response->json('data', $response->json());

        if (($data['rrr'] ?? null) === null) {
            return null;
        }

        return new RrrInquiry(
            rrr: $rrr,
            billerName: (string) ($data['billerName'] ?? 'Remita biller'),
            amount: Money::of($this->toMinor($data['amount'] ?? 0), 'NGN'),
            payerName: (string) ($data['payerName'] ?? ''),
            isPaid: $this->normaliseStatus((string) ($data['status'] ?? 'pending')) === 'completed',
        );
    }

    public function payBill(BillPaymentInstruction $instruction): BillProviderResult
    {
        $response = $this->client()->post('/billpayment/api/v1/payments', [
            'merchantId' => config('services.remita.merchant_id'),
            'reference' => $instruction->reference,
            'category' => $instruction->category->value,
            'billerCode' => $instruction->billerCode,
            'customerReference' => $instruction->customerReference,
            'amount' => $this->toMajor($instruction->amount),
            'currency' => $instruction->amount->currency,
            'narration' => $instruction->narration,
        ]);

        if (! $response->successful()) {
            throw BillProviderException::requestFailed('payBill', $response->status());
        }

        $data = (array) $response->json('data', $response->json());

        return new BillProviderResult(
            providerReference: (string) ($data['transactionId'] ?? $instruction->reference),
            status: $this->normaliseStatus((string) ($data['status'] ?? 'pending')),
        );
    }

    public function fetchBill(string $providerReference): ?RemoteBill
    {
        $response = $this->client()->get('/billpayment/api/v1/payments/'.$providerReference);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw BillProviderException::requestFailed('fetchBill', $response->status());
        }

        $data = (array) $response->json('data', $response->json());

        return new RemoteBill(
            providerReference: $providerReference,
            status: $this->normaliseStatus((string) ($data['status'] ?? 'pending')),
            amount: $this->toMinor($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'NGN'),
        );
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.remita.base_url'))
            ->timeout((int) config('services.remita.timeout', 15))
            ->withHeaders([
                'apiKey' => (string) config('services.remita.api_key'),
                'apiSecret' => (string) config('services.remita.api_secret'),
            ])
            ->acceptJson()
            ->asJson();
    }

    private function normaliseStatus(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'successful', 'success', 'paid', '00', '01' => 'completed',
            'failed', 'declined', 'rejected' => 'failed',
            default => 'pending',
        };
    }

    private function toMinor(float|int|string $major): int
    {
        return (int) round(((float) $major) * 100);
    }

    private function toMajor(Money $amount): float
    {
        return $amount->amount / 100;
    }
}
