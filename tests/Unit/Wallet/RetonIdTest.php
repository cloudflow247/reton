<?php

declare(strict_types=1);

use App\Domain\Wallet\Support\RetonId;

it('generates R plus nine digits with a valid luhn checksum', function () {
    $id = RetonId::generate();

    expect($id)->toMatch('/^R\d{9}$/')
        ->and(RetonId::isValid($id))->toBeTrue()
        ->and(strlen($id))->toBe(10);
});

it('rejects numeric nuban-style numbers that are not reton ids', function () {
    expect(RetonId::isValid('0450041659'))->toBeFalse()
        ->and(RetonId::isValid('2543297233'))->toBeFalse()
        ->and(RetonId::isValid('R12345678'))->toBeFalse()
        ->and(RetonId::isValid('R1234567890'))->toBeFalse();
});

it('normalizes whitespace and lowercase r', function () {
    $id = RetonId::generate();
    $spaced = strtolower(substr($id, 0, 1)).' '.substr($id, 1);

    expect(RetonId::normalize($spaced))->toBe($id)
        ->and(RetonId::isValid($spaced))->toBeTrue();
});

it('detects a tampered check digit', function () {
    $id = RetonId::generate();
    $body = substr($id, 0, 9);
    $badCheck = $id[9] === '0' ? '1' : '0';

    expect(RetonId::isValid($body.$badCheck))->toBeFalse();
});
