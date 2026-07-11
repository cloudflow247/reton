import type { ComponentType } from 'react'
import {
  ActivityIcon,
  BankIcon,
  BillIcon,
  CardIcon,
  ChatIcon,
  GiftIcon,
  LockIcon,
  PlusIcon,
  SendIcon,
  ShieldIcon,
} from '@/components/icons'
import type { FeatureFlags } from '@/types'

export type ServiceIcon = ComponentType<{ size?: number; className?: string }>

export type AppService = {
  to: string
  label: string
  hint: string
  Icon: ServiceIcon
  feature?: keyof FeatureFlags
  /** Visual group in the services sheet / dashboard. */
  group: 'money' | 'trust' | 'account'
}

/** Full service catalog — user-facing names only (no payment-rail brands). */
export const APP_SERVICES: AppService[] = [
  {
    to: '/send',
    label: 'Send',
    hint: 'Transfer with optional protection',
    Icon: SendIcon,
    group: 'money',
  },
  {
    to: '/add-money',
    label: 'Add money',
    hint: 'Fund your wallet securely',
    Icon: PlusIcon,
    group: 'money',
  },
  {
    to: '/withdraw',
    label: 'Cash out',
    hint: 'Withdraw to your bank',
    Icon: BankIcon,
    feature: 'withdraw',
    group: 'money',
  },
  {
    to: '/bills',
    label: 'Bills',
    hint: 'Airtime, power & more',
    Icon: BillIcon,
    feature: 'bills',
    group: 'money',
  },
  {
    to: '/cards',
    label: 'Cards',
    hint: 'Virtual cards for online spend',
    Icon: CardIcon,
    feature: 'cards',
    group: 'money',
  },
  {
    to: '/protection',
    label: 'Protection',
    hint: 'Callbacks, holds & recoveries',
    Icon: ShieldIcon,
    group: 'trust',
  },
  {
    to: '/marketplace',
    label: 'Shop',
    hint: 'Buy & sell with escrow',
    Icon: GiftIcon,
    group: 'trust',
  },
  {
    to: '/activity',
    label: 'Activity',
    hint: 'History and receipts',
    Icon: ActivityIcon,
    group: 'account',
  },
  {
    to: '/support',
    label: 'Support',
    hint: 'Help when you need it',
    Icon: ChatIcon,
    group: 'account',
  },
  {
    to: '/pin',
    label: 'PIN',
    hint: 'Secure money moves',
    Icon: LockIcon,
    group: 'account',
  },
]

export const SERVICE_GROUPS: { id: AppService['group']; title: string; blurb: string }[] = [
  { id: 'money', title: 'Money', blurb: 'Move funds quickly and safely' },
  { id: 'trust', title: 'Trust first', blurb: 'Protection built into every flow' },
  { id: 'account', title: 'Account', blurb: 'History, help, and security' },
]

/** Compact home-row shortcuts (always visible on dashboard). */
export const DASHBOARD_SHORTCUTS: AppService[] = [
  APP_SERVICES.find((s) => s.to === '/send')!,
  APP_SERVICES.find((s) => s.to === '/add-money')!,
  APP_SERVICES.find((s) => s.to === '/withdraw')!,
  APP_SERVICES.find((s) => s.to === '/bills')!,
]

/** Second dashboard row — services that were desktop-nav only. */
export const DASHBOARD_MORE_SHORTCUTS: AppService[] = [
  APP_SERVICES.find((s) => s.to === '/cards')!,
  APP_SERVICES.find((s) => s.to === '/protection')!,
  APP_SERVICES.find((s) => s.to === '/marketplace')!,
  APP_SERVICES.find((s) => s.to === '/activity')!,
]

export function isServiceSoon(
  service: AppService,
  features: FeatureFlags | undefined,
): boolean {
  return service.feature !== undefined && features?.[service.feature] === false
}
