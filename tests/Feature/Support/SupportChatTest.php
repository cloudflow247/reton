<?php

declare(strict_types=1);

use App\Domain\Support\Models\SupportMessage;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Services\TransactionLookupService;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function supportUser(int $fundMinor = 100_000_00): array
{
    $user = User::factory()->create(['transaction_pin' => Hash::make('1234')]);
    $wallet = app(WalletService::class)->open($user, 'NGN');

    if ($fundMinor > 0) {
        app(WalletService::class)->fund($wallet, Money::of($fundMinor, 'NGN'));
        $wallet->refresh();
    }

    return [$user, $wallet];
}

it('renders the support page for authenticated users', function () {
    [$user] = supportUser();

    $this->actingAs($user)->get('/support')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Support')
            ->has('messages')
            ->has('quickPrompts')
            ->has('welcome'));
});

it('rejects guests from the support page', function () {
    $this->get('/support')->assertRedirect('/login');
});

it('responds to a protection question with guidance', function () {
    [$user] = supportUser();

    $this->actingAs($user)->post('/support/messages', [
        'message' => 'How does callback protection work?',
    ])->assertRedirect();

    expect(SupportMessage::query()->where('user_id', $user->id)->count())->toBe(2);

    $assistant = SupportMessage::query()
        ->where('user_id', $user->id)
        ->where('role', 'assistant')
        ->latest('created_at')
        ->first();

    expect($assistant)->not->toBeNull()
        ->and($assistant->body)->toContain('Protected transfers')
        ->and($assistant->actions)->not->toBeEmpty();
});

it('looks up a transfer reference scoped to the user', function () {
    [$sender, $from] = supportUser(50_000_00);
    [$recipient, $to] = supportUser();

    $transfer = app(TransferService::class)->sendNormal(
        $sender,
        $from,
        $to,
        Money::of(5_000_00, 'NGN'),
        'Lunch',
        null,
    );

    $this->actingAs($sender)->post('/support/messages', [
        'message' => 'Find '.$transfer->reference,
    ])->assertRedirect();

    $assistant = SupportMessage::query()
        ->where('user_id', $sender->id)
        ->where('role', 'assistant')
        ->latest('created_at')
        ->first();

    expect($assistant->body)->toContain($transfer->reference)
        ->and($assistant->body)->toContain('transfer');

    // A user not involved in the transfer cannot see it
    [$stranger] = supportUser();
    $this->actingAs($stranger)->post('/support/messages', [
        'message' => 'Find '.$transfer->reference,
    ])->assertRedirect();

    $foreignReply = SupportMessage::query()
        ->where('user_id', $stranger->id)
        ->where('role', 'assistant')
        ->latest('created_at')
        ->first();

    expect($foreignReply->body)->toContain("couldn't find");
});

it('extracts references from free-form text', function () {
    $lookup = app(TransactionLookupService::class);

    expect($lookup->extractReference('Please check TRF-01JABCDEFGHIJK'))->toBe('TRF-01JABCDEFGHIJK');
});

it('escalates to a human support ticket', function () {
    [$user] = supportUser();

    $this->actingAs($user)->post('/support/escalate', [
        'subject' => 'Payment stuck',
        'note' => 'My protected transfer never released.',
    ])->assertRedirect()
        ->assertSessionHas('support_ticket');

    $ticket = SupportTicket::query()->where('user_id', $user->id)->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->status->value)->toBe('escalated')
        ->and($ticket->reference)->toStartWith('TKT-');
});

it('validates support message input', function () {
    [$user] = supportUser();

    $this->actingAs($user)->post('/support/messages', ['message' => ''])
        ->assertSessionHasErrors('message');
});

it('reports trust score from live dashboard summary', function () {
    [$user] = supportUser();

    $this->actingAs($user)->post('/support/messages', [
        'message' => 'What is my trust score?',
    ])->assertRedirect();

    $assistant = SupportMessage::query()
        ->where('user_id', $user->id)
        ->where('role', 'assistant')
        ->latest('created_at')
        ->first();

    expect($assistant->body)->toContain('trust score')
        ->and($assistant->body)->toContain('/100');
});

it('suggests recovery when user reports a wrong transfer', function () {
    [$user] = supportUser();

    $this->actingAs($user)->post('/support/messages', [
        'message' => 'I sent money to the wrong person',
    ])->assertRedirect();

    $assistant = SupportMessage::query()
        ->where('user_id', $user->id)
        ->where('role', 'assistant')
        ->latest('created_at')
        ->first();

    expect($assistant->body)->toContain('Wrong-transfer recovery')
        ->and(collect($assistant->actions)->pluck('href'))->toContain('/protection');
});
