<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Ledger\Models\Transaction;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Models\Payout;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMoneyController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = (string) $request->string('tab', 'ledger');

        $deposits = Deposit::query()
            ->with('user:id,name,email')
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (Deposit $deposit): array => [
                'id' => $deposit->id,
                'reference' => $deposit->reference,
                'status' => $deposit->status?->value ?? (string) $deposit->status,
                'amount' => $deposit->amount,
                'currency' => $deposit->currency,
                'method' => $deposit->method?->value ?? (string) ($deposit->method ?? ''),
                'user' => $deposit->user ? [
                    'name' => $deposit->user->name,
                    'email' => $deposit->user->email,
                ] : null,
                'created_at' => $deposit->created_at?->toIso8601String(),
            ]);

        $payouts = Payout::query()
            ->with('user:id,name,email')
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (Payout $payout): array => [
                'id' => $payout->id,
                'reference' => $payout->reference,
                'status' => $payout->status?->value ?? (string) $payout->status,
                'amount' => $payout->amount,
                'currency' => $payout->currency,
                'provider' => $payout->provider,
                'account_number' => $payout->account_number,
                'bank_code' => $payout->bank_code,
                'user' => $payout->user ? [
                    'name' => $payout->user->name,
                    'email' => $payout->user->email,
                ] : null,
                'created_at' => $payout->created_at?->toIso8601String(),
            ]);

        $ledger = Transaction::query()
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (Transaction $txn): array => [
                'id' => $txn->id,
                'type' => $txn->type?->value ?? (string) $txn->type,
                'description' => $txn->description,
                'idempotency_key' => $txn->idempotency_key,
                'created_at' => $txn->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Money', [
            'tab' => $tab,
            'deposits' => $deposits,
            'payouts' => $payouts,
            'ledger' => $ledger,
        ]);
    }
}
