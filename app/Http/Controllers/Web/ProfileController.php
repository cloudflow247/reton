<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Kyc\Services\KycService;
use App\Domain\Notifications\Services\NotificationPreferenceService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\UpdateNotificationPreferencesRequest;
use App\Http\Resources\Api\V1\KycResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly KycService $kyc,
        private readonly NotificationPreferenceService $preferences,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->kyc->forUser($user);

        return Inertia::render('Profile', [
            'kyc' => (new KycResource($profile))->resolve(),
            'bvnPendingOtp' => $this->kyc->hasPendingAlatpayBvn($user),
            'bvnOtpHint' => $this->kyc->pendingAlatpayBvnHint($user),
            'bvnProvider' => $this->kyc->bvnProvider(),
            'bvnDemoMode' => $this->kyc->bvnDemoMode(),
            'smsAlertFeeMinor' => $this->preferences->smsAlertFeeMinor(),
        ]);
    }

    public function updateNotifications(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->preferences->update($user, $request->validated());

        return back()->with('success', 'Notification preferences saved.');
    }
}
