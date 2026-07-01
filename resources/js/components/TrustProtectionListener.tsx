import { useTrustProtectionEcho } from '@/hooks/useTrustProtectionEcho'
import { reverbEnabled } from '@/lib/broadcasting'

type Props = {
  userId: string
  only?: string[]
}

/** Subscribes to trust-protection broadcasts without subscribing on an empty channel. */
export function TrustProtectionListener({ userId, only = ['summary'] }: Props) {
  if (!reverbEnabled()) {
    return null
  }

  return <TrustProtectionListenerActive userId={userId} only={only} />
}

function TrustProtectionListenerActive({ userId, only }: Props) {
  useTrustProtectionEcho(userId, only)
  return null
}
