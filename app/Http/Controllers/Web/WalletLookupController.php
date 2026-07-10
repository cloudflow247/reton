<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Support\RetonId;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Name enquiry for the Send form: resolves a RETON ID to its holder.
 * A small JSON endpoint (not Inertia) because it runs as a typeahead.
 */
class WalletLookupController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $number = RetonId::normalize(
            is_string($request->query('account_number'))
                ? $request->query('account_number')
                : null,
        );

        if ($number === null || ! RetonId::isValid($number)) {
            throw ValidationException::withMessages([
                'account_number' => ['Enter a valid RETON ID (R + 9 digits).'],
            ]);
        }

        $wallet = Wallet::where('account_number', $number)->first();

        if (! $wallet instanceof Wallet) {
            abort(404);
        }

        $name = 'Reton user';
        if ($wallet->owner_type === User::class) {
            $owner = User::find($wallet->owner_id);
            if ($owner instanceof User) {
                $name = (string) $owner->name;
            }
        }

        return response()->json([
            'wallet_id' => $wallet->id,
            'account_number' => $wallet->account_number,
            'account_name' => $name,
        ]);
    }
}
