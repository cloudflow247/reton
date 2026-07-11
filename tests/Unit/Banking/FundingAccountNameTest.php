<?php

declare(strict_types=1);

use App\Support\Banking\FundingAccountName;

it('strips merchant prefix when the suffix matches the profile name', function () {
    expect(FundingAccountName::display(
        'CLOUDFLOW TECHNOLOGY LTD - GABRIEL MOGAJI',
        'Gabriel Mogaji',
    ))->toBe('GABRIEL MOGAJI');
});

it('prefers the profile name when tokens overlap the provider name', function () {
    expect(FundingAccountName::display(
        'RETON / Ada Lovelace',
        'Ada Lovelace',
    ))->toBe('ADA LOVELACE');
});

it('falls back to the profile name when the provider name is empty', function () {
    expect(FundingAccountName::display(null, 'Gabriel Mogaji'))->toBe('GABRIEL MOGAJI')
        ->and(FundingAccountName::display('', 'Gabriel Mogaji'))->toBe('GABRIEL MOGAJI');
});

it('keeps an unrelated provider name unchanged', function () {
    expect(FundingAccountName::display('ACME COLLECTIONS LTD', 'Gabriel Mogaji'))
        ->toBe('ACME COLLECTIONS LTD');
});
