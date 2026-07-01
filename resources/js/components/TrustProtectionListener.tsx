import { useTrustProtectionEcho } from '@/hooks/useTrustProtectionEcho'

type Props = {
  userId: string
  only?: string[]
}

/** Subscribes to trust-protection broadcasts without subscribing on an empty channel. */
export function TrustProtectionListener({ userId, only = ['summary'] }: Props) {
  useTrustProtectionEcho(userId, only)
  return null
}
