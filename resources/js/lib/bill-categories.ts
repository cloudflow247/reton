import type { ComponentType } from 'react'
import { BillIcon, BoltIcon, GiftIcon, PhoneIcon, SignalIcon, TvIcon } from '@/components/icons'
import type { BillCategory } from '@/lib/types'

type IconCmp = ComponentType<{ size?: number; className?: string }>

export type BillCategoryMeta = {
  value: BillCategory
  label: string
  tagline: string
  Icon: IconCmp
  gradient: string
  ring: string
  iconBg: string
}

export const billCategoryMeta: Record<BillCategory, BillCategoryMeta> = {
  airtime: {
    value: 'airtime',
    label: 'Airtime',
    tagline: 'Top up any network',
    Icon: PhoneIcon,
    gradient: 'from-emerald-500/20 via-mint/10 to-transparent',
    ring: 'ring-mint/40',
    iconBg: 'bg-mint/15 text-mint',
  },
  data: {
    value: 'data',
    label: 'Data',
    tagline: 'Bundles & plans',
    Icon: SignalIcon,
    gradient: 'from-sky-500/20 via-blue-400/10 to-transparent',
    ring: 'ring-sky-400/40',
    iconBg: 'bg-sky-500/15 text-sky-600',
  },
  electricity: {
    value: 'electricity',
    label: 'Power',
    tagline: 'Prepaid & postpaid',
    Icon: BoltIcon,
    gradient: 'from-amber-500/25 via-orange-400/10 to-transparent',
    ring: 'ring-amber-400/40',
    iconBg: 'bg-amber-500/15 text-amber-600',
  },
  cable_tv: {
    value: 'cable_tv',
    label: 'TV',
    tagline: 'DStv, GOtv & more',
    Icon: TvIcon,
    gradient: 'from-violet-500/20 via-purple-400/10 to-transparent',
    ring: 'ring-violet-400/40',
    iconBg: 'bg-violet-500/15 text-violet-600',
  },
  rrr: {
    value: 'rrr',
    label: 'Remita',
    tagline: 'Pay with RRR code',
    Icon: BillIcon,
    gradient: 'from-slate-500/15 via-slate-400/10 to-transparent',
    ring: 'ring-slate-400/40',
    iconBg: 'bg-slate-500/15 text-slate-600',
  },
  betting: {
    value: 'betting',
    label: 'Betting',
    tagline: 'Fund your wallet',
    Icon: GiftIcon,
    gradient: 'from-rose-500/20 via-pink-400/10 to-transparent',
    ring: 'ring-rose-400/40',
    iconBg: 'bg-rose-500/15 text-rose-600',
  },
}

export function categoryMeta(value: BillCategory): BillCategoryMeta {
  return billCategoryMeta[value]
}
