<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Exceptions\FraudBlockedException;
use App\Domain\Fraud\Services\FraudService;
use App\Domain\Auth\Services\PinService;
use App\Domain\Transfers\Enums\TransferType;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\VerifiesPin;
use App\Http\Requests\Api\V1\Transfer\CreateTransferRequest;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class SendController extends Controller
{
    use VerifiesPin;

    public function __construct(
        private readonly TransferService $transfers,
        private readonly PinService $pins,
        private readonly FraudService $fraud,
    ) {}

    public function store(CreateTransferRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $from = Wallet::findOrFail($request->string('from_wallet_id')->toString());
        $this->authorize('operate', $from);

        $to = Wallet::findOrFail($request->string('to_wallet_id')->toString());

        if ($from->currency !== $to->currency) {
            throw ValidationException::withMessages([
                'to_wallet_id' => ['The recipient wallet currency must match the source wallet.'],
            ]);
        }

        $this->verifyPin($this->pins, $user, $request->string('pin')->toString());

        $amount = Money::of($request->integer('amount'), $from->currency);

        $assessment = $this->fraud->evaluate(new FraudContext(
            user: $user,
            wallet: $from,
            amount: $amount,
            action: 'transfer',
            beneficiary: $to,
            deviceFingerprint: $request->header('X-Device-Fingerprint'),
            ipAddress: $request->ip(),
        ));

        if ($assessment->isBlocked()) {
            throw FraudBlockedException::make();
        }

        $note = $request->filled('note') ? $request->string('note')->toString() : null;

        $transfer = TransferType::from($request->string('type')->toString()) === TransferType::Protected
            ? $this->transfers->sendProtected($user, $from, $to, $amount, $note, null)
            : $this->transfers->sendNormal($user, $from, $to, $amount, $note, null);

        // Flash a small receipt the Send page shows as its success screen.
        return back()->with('transfer', [
            'reference' => $transfer->reference,
            'type' => $transfer->type->value,
            'amount' => $transfer->amount,
            'recipient_name' => $this->holderName($to),
        ]);
    }

    private function holderName(Wallet $wallet): string
    {
        if ($wallet->owner_type === User::class) {
            $owner = User::find($wallet->owner_id);

            if ($owner instanceof User) {
                return (string) $owner->name;
            }
        }

        return 'Reton user';
    }
}
