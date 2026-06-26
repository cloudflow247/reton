<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Payments\Services\AlatpayDepositService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payment\InitiateDepositRequest;
use App\Http\Resources\Api\V1\DepositResource;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;

class AddMoneyController extends Controller
{
    public function __construct(private readonly AlatpayDepositService $deposits) {}

    public function store(InitiateDepositRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wallet = Wallet::findOrFail($request->string('wallet_id')->toString());
        $this->authorize('operate', $wallet);

        $deposit = $this->deposits->initiate(
            $user,
            $wallet,
            Money::of($request->integer('amount'), $wallet->currency),
        );

        // Flash the funded virtual account; the AddMoney page renders it.
        return back()->with('deposit', (new DepositResource($deposit))->resolve());
    }
}
