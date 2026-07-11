<?php

declare(strict_types=1);

use App\Domain\Ledger\Enums\EntryDirection;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Wallet\Support\StatementMoneyFlow;

it('sums credits and debits from the exact statement window', function () {
    $entries = collect([
        new LedgerEntry(['direction' => EntryDirection::Credit, 'amount' => 100_00]),
        new LedgerEntry(['direction' => EntryDirection::Credit, 'amount' => 200_00]),
        new LedgerEntry(['direction' => EntryDirection::Debit, 'amount' => 50_00]),
        new LedgerEntry(['direction' => EntryDirection::Credit, 'amount' => 93_00]),
    ]);

    $flow = StatementMoneyFlow::fromEntries($entries);

    expect($flow['inflow'])->toBe(393_00)
        ->and($flow['outflow'])->toBe(50_00)
        ->and($flow['net'])->toBe(343_00)
        ->and($flow['count'])->toBe(4);
});

it('matches array-shaped statement resources used by inertia props', function () {
    $flow = StatementMoneyFlow::fromEntries([
        ['direction' => 'credit', 'amount' => 150_00],
        ['direction' => 'credit', 'amount' => 100_00],
        ['direction' => 'debit', 'amount' => 25_00],
    ]);

    expect($flow['inflow'])->toBe(250_00)
        ->and($flow['outflow'])->toBe(25_00)
        ->and($flow['net'])->toBe(225_00);
});

it('does not treat wallet ledger total as money-in for a partial window', function () {
    // Regression: dashboard previously summed 6 credits while showing 5 rows,
    // making "money in" equal the all-time wallet total by coincidence.
    $shown = [
        ['direction' => 'credit', 'amount' => 100_00],
        ['direction' => 'credit', 'amount' => 200_00],
        ['direction' => 'credit', 'amount' => 93_00],
        ['direction' => 'credit', 'amount' => 100_00],
        ['direction' => 'credit', 'amount' => 150_00],
    ];

    $hiddenSixth = ['direction' => 'credit', 'amount' => 100_00];

    expect(StatementMoneyFlow::fromEntries($shown)['inflow'])->toBe(643_00)
        ->and(StatementMoneyFlow::fromEntries([...$shown, $hiddenSixth])['inflow'])->toBe(743_00);
});
