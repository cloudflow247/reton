<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Name enquiry for the Send form: resolves a 10-digit account number to its
 * holder. A small JSON endpoint (not Inertia) because it runs as a typeahead.
 */
class WalletLookupController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $number = $request->query('account_number');

        if (! is_string($number) || preg_match('/^\d{10}$/', $number) !== 1) {
            throw ValidationException::withMessages([
                'account_number' => ['Enter a valid 10-digit account number.'],
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
