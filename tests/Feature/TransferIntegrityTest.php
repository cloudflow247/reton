<?php

declare(strict_types=1);

use App\Domain\Callback\Services\CallbackService;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Exceptions\InvalidTransferStateException;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Services\TransferService;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('treats a repeated web Idempotency-Key as a single transfer', function () {
    [$sender, $from] = readyUserWithWallet([], 1_000_00);
    [, $to] = readyUserWithWallet();

    $payload = [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 250_00,
        'type' => 'normal',
        'pin' => '1234',
        'idempotency_key' => 'web-send-dup-1',
    ];

    $this->actingAs($sender)
        ->withHeaders(['Idempotency-Key' => 'web-send-dup-1'])
        ->post('/transfers', $payload)
        ->assertSessionHas('transfer');

    $this->actingAs($sender)
        ->withHeaders(['Idempotency-Key' => 'web-send-dup-1'])
        ->post('/transfers', $payload)
        ->assertSessionHas('transfer');

    expect(Transfer::query()->count())->toBe(1)
        ->and($from->fresh()->balance)->toBe(75000)
        ->and($to->fresh()->balance)->toBe(25000);
});

it('refuses to release a protected transfer while a callback is open', function () {
    [$sender, $from] = readyUserWithWallet([], 1_000_00);
    [, $to] = readyUserWithWallet();

    $transfer = app(TransferService::class)->sendProtected(
        $sender,
        $from,
        $to,
        Money::of(400_00, 'NGN'),
        null,
        'protected-callback-lock',
    );

    app(CallbackService::class)->initiate($transfer->fresh(), $sender, 'Sent to the wrong account');

    expect(fn () => app(TransferService::class)->release($transfer->fresh()))
        ->toThrow(InvalidTransferStateException::class);

    expect($transfer->fresh()->status)->toBe(TransferStatus::Held)
        ->and($to->fresh()->held_balance)->toBe(40000)
        ->and($to->fresh()->availableMinor())->toBe(0);
});

it('keeps available plus held equal to ledger after hold and refund', function () {
    [$sender, $from] = readyUserWithWallet([], 1_000_00);
    [, $to] = readyUserWithWallet();

    $transfer = app(TransferService::class)->sendProtected(
        $sender,
        $from,
        $to,
        Money::of(250_00, 'NGN'),
        null,
        'protected-refund-invariant',
    );

    $receiver = $to->fresh();
    expect($receiver->availableMinor() + $receiver->heldMinor())->toBe($receiver->ledgerMinor());

    app(TransferService::class)->refund($transfer->fresh(), 'callback upheld');

    $receiver = $to->fresh();
    expect($receiver->availableMinor() + $receiver->heldMinor())->toBe($receiver->ledgerMinor())
        ->and($receiver->held_balance)->toBe(0)
        ->and($from->fresh()->balance)->toBe(100000);
});
