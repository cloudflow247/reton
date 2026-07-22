<?php

declare(strict_types=1);

namespace App\Domain\Payments\Alatpay\Data;

/**
 * A single inbound payment into a static account, as reported by AlatPay.
 * NOTE: AlatPay reports amount in MAJOR units (e.g. 100.00 = NGN 100).
 */
final readonly class StaticAccountTransaction
{
    public function __construct(
        public string $transactionId,
        public int $status,
        public string $accountNumber,
        public float $amountMajor,
        public ?string $narration = null,
        public ?string $notificationEmail = null,
    ) {}

    /**
     * Collection-history rows are inbound payments. Portal "Settled" may arrive as
     * numeric status 1, or as settlementStatus/status strings - treat all as paid.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 1;
    }

    public function amountMinor(): int
    {
        return (int) round($this->amountMajor * 100);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function statusFromRow(array $row): int
    {
        $raw = $row['status'] ?? $row['Status'] ?? null;

        if (is_numeric($raw)) {
            $code = (int) $raw;

            // Docs use 1 for successful collections; some portal rows use 2 for Settled.
            return in_array($code, [1, 2], true) ? 1 : $code;
        }

        if (is_string($raw) && self::looksSettled($raw)) {
            return 1;
        }

        foreach (['settlementStatus', 'SettlementStatus', 'settlement_status'] as $key) {
            if (isset($row[$key]) && is_string($row[$key]) && self::looksSettled($row[$key])) {
                return 1;
            }
        }

        return 0;
    }

    private static function looksSettled(string $value): bool
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, [
            '1',
            'settled',
            'successful',
            'success',
            'completed',
            'paid',
            'successfulsettlement',
        ], true);
    }
}
