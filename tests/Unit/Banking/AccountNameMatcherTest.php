<?php

declare(strict_types=1);

use App\Support\Banking\AccountNameMatcher;

it('matches when account name shares profile name tokens', function () {
    expect(AccountNameMatcher::matches('ADA LOVELACE', 'Ada Lovelace'))->toBeTrue()
        ->and(AccountNameMatcher::matches('LOVELACE ADA SMITH', 'Ada Lovelace'))->toBeTrue();
});

it('rejects unrelated account names', function () {
    expect(AccountNameMatcher::matches('JOHN DOE', 'Ada Lovelace'))->toBeFalse()
        ->and(AccountNameMatcher::matches('ADA', 'Ada Lovelace'))->toBeFalse();
});
