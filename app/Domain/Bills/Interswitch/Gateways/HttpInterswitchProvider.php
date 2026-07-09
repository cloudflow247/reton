<?php

declare(strict_types=1);

namespace App\Domain\Bills\Interswitch\Gateways;

use App\Domain\Bills\Interswitch\Services\InterswitchTokenService;
use App\Domain\Bills\Remita\Contracts\BillProviderGateway;
use App\Domain\Bills\Remita\Data\BillPaymentInstruction;
use App\Domain\Bills\Remita\Data\BillProviderResult;
use App\Domain\Bills\Remita\Data\RemoteBill;
use App\Domain\Bills\Remita\Data\RrrInquiry;
use App\Domain\Bills\Remita\Exceptions\BillProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Live Interswitch Quickteller VAS (airtime, data, utilities, cable).
 *
 * @see https://docs.interswitchgroup.com/docs/bills-payment-1
 * @see https://docs.interswitchgroup.com/docs/airtime-recharge-virtual-top-up
 */
class HttpInterswitchProvider implements BillProviderGateway
{
    public function __construct(private readonly InterswitchTokenService $tokens) {}

    public function lookupRrr(string $rrr): ?RrrInquiry
    {
        return null;
    }

    public function payBill(BillPaymentInstruction $instruction): BillProviderResult
    {
        if ($instruction->paymentCode === null || $instruction->paymentCode === '') {
            throw BillProviderException::requestFailed('payBill', 422);
        }

        $requestRef = $instruction->requestReference ?? $this->requestReference($instruction->reference);
        $mobile = $this->normalizePhone($instruction->customerMobile ?? $instruction->customerReference);

        $response = $this->client()->post('/Transactions', [
            'TerminalId' => (string) config('services.interswitch.terminal_id'),
            'paymentCode' => $instruction->paymentCode,
            'customerId' => $this->customerId($instruction->customerReference, $mobile),
            'customerMobile' => $mobile,
            'customerEmail' => $instruction->customerEmail ?? 'noreply@retonpay.com',
            'amount' => (string) $instruction->amount->amount,
            'requestReference' => $requestRef,
        ]);

        if (! $response->successful()) {
            throw BillProviderException::requestFailed('payBill', $response->status());
        }

        $data = $response->json();
        $code = (string) ($data['ResponseCode'] ?? '');

        return new BillProviderResult(
            providerReference: (string) ($data['TransactionRef'] ?? $requestRef),
            status: $this->normaliseResponseCode($code),
            metadata: [
                'request_reference' => $requestRef,
                'response_code' => $code,
                'response_description' => $data['ResponseDescription'] ?? null,
                'recharge_pin' => $data['RechargePIN'] ?? null,
                'phcn_token' => $data['PhcnTokenDetails'] ?? null,
                'additional_info' => $data['AdditionalInfo'] ?? null,
            ],
        );
    }

    public function fetchBill(string $providerReference): ?RemoteBill
    {
        $response = $this->client()->get('/Transactions', [
            'requestRef' => $providerReference,
        ]);

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw BillProviderException::requestFailed('fetchBill', $response->status());
        }

        $data = $response->json();
        $code = (string) ($data['transactionResponseCode'] ?? $data['ResponseCode'] ?? '');

        return new RemoteBill(
            providerReference: $providerReference,
            status: $this->normaliseResponseCode($code),
            amount: (int) ($data['amount'] ?? $data['Amount'] ?? 0),
            currency: 'NGN',
        );
    }

    /** Health check: list VAS categories. */
    public function ping(): bool
    {
        $response = $this->client()->get('/services/categories');

        return $response->successful()
            && (string) $response->json('ResponseCode', '') === '90000';
    }

    private function client(): PendingRequest
    {
        $terminalId = (string) config('services.interswitch.terminal_id');

        return Http::baseUrl(rtrim((string) config('services.interswitch.base_url'), '/'))
            ->timeout((int) config('services.interswitch.timeout', 15))
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->tokens->bearerToken(),
                'TerminalID' => $terminalId,
                'terminalId' => $terminalId,
            ])
            ->acceptJson()
            ->asJson();
    }

    private function normaliseResponseCode(string $code): string
    {
        return match ($code) {
            '90000', '00', '0' => 'completed',
            '90009', '90006' => 'pending',
            default => str_starts_with($code, '900') ? 'failed' : 'pending',
        };
    }

    private function requestReference(string $internalReference): string
    {
        $prefix = (string) config('services.interswitch.request_reference_prefix', '1453');
        $digits = preg_replace('/\D/', '', $internalReference) ?: (string) random_int(100000, 999999);

        return substr($prefix.$digits, 0, 20);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0')) {
            return '234'.substr($digits, 1);
        }

        if (! str_starts_with($digits, '234')) {
            return '234'.$digits;
        }

        return $digits;
    }

    private function customerId(string $reference, string $mobile): string
    {
        $digits = (string) preg_replace('/\D/', '', $reference);

        if (strlen($digits) >= 10 && (str_starts_with($digits, '0') || str_starts_with($digits, '234'))) {
            return $this->normalizePhone($reference);
        }

        return $digits !== '' ? $digits : $mobile;
    }
}
