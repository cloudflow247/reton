<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Kyc\Services\KycService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function __construct(private readonly KycService $kyc) {}

    public function upgradeTier2(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'bvn' => ['required', 'string', 'digits:11'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'identity_consent' => ['accepted'],
            'return_to' => ['nullable', 'string', 'max:255'],
        ]);

        $this->kyc->upgradeToTier2(
            $user,
            $validated['bvn'],
            $validated['date_of_birth'],
            $request->ip(),
        );

        $message = 'BVN verified — you can now fund your wallet and open your ALATPay deposit account.';

        $returnTo = (string) ($validated['return_to'] ?? '');
        if ($returnTo !== '' && str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//')) {
            return redirect($returnTo)->with('success', $message);
        }

        return redirect()->route('profile')->with('success', $message);
    }

    public function upgradeTier3(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'nin' => ['required', 'string', 'digits:11'],
            'address_line1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'identity_consent' => ['accepted'],
        ]);

        $this->kyc->upgradeToTier3(
            $user,
            $validated['nin'],
            $validated['address_line1'],
            $validated['city'],
            $validated['state'],
            $request->ip(),
        );

        return redirect()->route('profile')->with('success', 'Full KYC complete — your limits have been raised.');
    }
}
