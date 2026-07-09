<?php

declare(strict_types=1);

namespace App\Domain\Cards\Services;

use App\Domain\Cards\Data\FxQuote;
use App\Domain\Cards\Exceptions\VirtualCardException;

final class FxQuoteService
{
    /**
     * Quote how much to debit from the source wallet to load a target amount on a card.
     */
    public function quote(string $sourceCurrency, string $targetCurrency, int $targetAmountMinor): FxQuote
    {
        $sourceCurrency = strtoupper($sourceCurrency);
        $targetCurrency = strtoupper($targetCurrency);

        if ($sourceCurrency === $targetCurrency) {
            return new FxQuote(
                sourceCurrency: $sourceCurrency,
                sourceAmountMinor: $targetAmountMinor,
                targetCurrency: $targetCurrency,
                targetAmountMinor: $targetAmountMinor,
                rate: 1.0,
                spreadBps: 0,
            );
        }

        $rate = (float) config('reton.fx.usd_ngn_rate', 1600);
        $spreadBps = (int) config('reton.fx.spread_bps', 150);
        $spread = 1 + ($spreadBps / 10_000);

        if ($sourceCurrency === 'NGN' && $targetCurrency === 'USD') {
            $usdMajor = $targetAmountMinor / 100;
            $ngnMajor = $usdMajor * $rate * $spread;
            $sourceMinor = (int) ceil($ngnMajor * 100);

            return new FxQuote(
                sourceCurrency: 'NGN',
                sourceAmountMinor: $sourceMinor,
                targetCurrency: 'USD',
                targetAmountMinor: $targetAmountMinor,
                rate: $rate,
                spreadBps: $spreadBps,
            );
        }

        if ($sourceCurrency === 'USD' && $targetCurrency === 'NGN') {
            $ngnMajor = ($targetAmountMinor / 100) / $spread;
            $usdMajor = $ngnMajor / $rate;
            $sourceMinor = (int) ceil($usdMajor * 100);

            return new FxQuote(
                sourceCurrency: 'USD',
                sourceAmountMinor: $sourceMinor,
                targetCurrency: 'NGN',
                targetAmountMinor: $targetAmountMinor,
                rate: $rate,
                spreadBps: $spreadBps,
            );
        }

        throw VirtualCardException::providerFailed('fx', 'Only NGN ↔ USD conversion is supported.');
    }
}
