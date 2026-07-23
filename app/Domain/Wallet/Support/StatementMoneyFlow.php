<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Support;

use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use Illuminate\Support\Collection;

/**
 * Pure money-flow aggregates for statement windows.
 *
 * Totals must always be computed from the exact entries shown to the user -
 * never from a larger window or from wallet.balance (which is all-time).
 */
final class StatementMoneyFlow
{
    /**
     * @param  Collection<int, LedgerEntry>|iterable<int, array{direction: string, amount: int}>  $entries
     * @return array{inflow: int, outflow: int, net: int, count: int}
     */
    public static function fromEntries(iterable $entries): array
    {
        $inflow = 0;
        $outflow = 0;
        $count = 0;

        foreach ($entries as $entry) {
            $count++;

            if ($entry instanceof LedgerEntry) {
                $direction = $entry->direction;
                $amount = (int) $entry->amount;
            } else {
                $direction = $entry['direction'];
                $amount = $entry['amount'];
            }

            $isCredit = $direction === EntryDirection::Credit
                || $direction === EntryDirection::Credit->value
                || $direction === 'credit';

            if ($isCredit) {
                $inflow += $amount;
            } else {
                $outflow += $amount;
            }
        }

        return [
            'inflow' => $inflow,
            'outflow' => $outflow,
            'net' => $inflow - $outflow,
            'count' => $count,
        ];
    }
}
