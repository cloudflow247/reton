import { useEcho } from '@laravel/echo-react'
import { router } from '@inertiajs/react'

/**
 * Reload trust-related Inertia props when callback/recovery state changes over Reverb.
 * Only mount via {@link TrustProtectionListener} so we never open a channel with an empty name.
 */
export function useTrustProtectionEcho(userId: string, only: string[] = ['summary']) {
  useEcho(
    `users.${userId}`,
    '.trust.protection.changed',
    () => {
      router.reload({ only })
    },
    [userId, only.join(',')],
  )
}
