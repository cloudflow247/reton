<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificationPreferenceService
{
    /**
     * @param  array{notify_email?: bool, notify_sms?: bool}  $preferences
     */
    public function update(User $user, array $preferences): User
    {
        $notifyEmail = array_key_exists('notify_email', $preferences)
            ? (bool) $preferences['notify_email']
            : (bool) $user->notify_email;

        $notifySms = array_key_exists('notify_sms', $preferences)
            ? (bool) $preferences['notify_sms']
            : (bool) $user->notify_sms;

        if ($notifySms && blank($user->phone)) {
            throw ValidationException::withMessages([
                'notify_sms' => ['Add a phone number to your account before enabling SMS alerts.'],
            ]);
        }

        return DB::transaction(function () use ($user, $notifyEmail, $notifySms): User {
            $user->forceFill([
                'notify_email' => $notifyEmail,
                'notify_sms' => $notifySms,
            ])->save();

            return $user->refresh();
        });
    }

    public function smsAlertFeeMinor(): int
    {
        return max(0, (int) config('reton.sms.alert_fee_minor', 600));
    }
}
