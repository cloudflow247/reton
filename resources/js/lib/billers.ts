import type { BillCategory } from '@/lib/types'
import type { BillerBrandId } from '@/components/biller-icons'

/**
 * Known billers with brand icons for the Bills UI.
 * `code` is sent to the backend / Interswitch payment-code resolver.
 */
export type Biller = {
  code: string
  name: string
  brand: BillerBrandId
  bg: string
  fg: string
  paymentCode?: string
  ref?: string
}

const NETWORKS: Biller[] = [
  { code: 'mtn', name: 'MTN', brand: 'mtn', bg: '#FFCB05', fg: '#16130b', ref: '0803 000 0000' },
  { code: 'glo', name: 'Glo', brand: 'glo', bg: '#00A859', fg: '#ffffff', ref: '0805 000 0000' },
  { code: 'airtel', name: 'Airtel', brand: 'airtel', bg: '#E40000', fg: '#ffffff', ref: '0802 000 0000' },
  { code: 't2', name: 'T2', brand: 't2', bg: '#F04E00', fg: '#ffffff', ref: '0809 000 0000' },
]

const CABLE: Biller[] = [
  { code: 'dstv', name: 'DStv', brand: 'dstv', bg: '#0095DA', fg: '#ffffff', ref: 'Smartcard number' },
  { code: 'gotv', name: 'GOtv', brand: 'gotv', bg: '#76BC21', fg: '#16302b', ref: 'IUC number' },
  { code: 'startimes', name: 'StarTimes', brand: 'startimes', bg: '#ED1C24', fg: '#ffffff', ref: 'Smartcard number' },
  { code: 'showmax', name: 'Showmax', brand: 'showmax', bg: '#191919', fg: '#ffffff', ref: 'Account / phone' },
]

const ELECTRICITY: Biller[] = [
  { code: 'ikedc', name: 'Ikeja Electric', brand: 'ikedc', bg: '#E11B22', fg: '#ffffff', ref: 'Meter number' },
  { code: 'ekedc', name: 'Eko Electric', brand: 'ekedc', bg: '#5B2D8E', fg: '#ffffff', ref: 'Meter number' },
  { code: 'ibedc', name: 'Ibadan Electric', brand: 'ibedc', bg: '#0a7a3b', fg: '#ffffff', ref: 'Meter number' },
  { code: 'aedc', name: 'Abuja Electric', brand: 'aedc', bg: '#0b5cab', fg: '#ffffff', ref: 'Meter number' },
  { code: 'phed', name: 'PH Electric', brand: 'phed', bg: '#1b4f9c', fg: '#ffffff', ref: 'Meter number' },
  { code: 'kedco', name: 'Kano Electric', brand: 'kedco', bg: '#16923c', fg: '#ffffff', ref: 'Meter number' },
]

const BETTING: Biller[] = [
  { code: 'sportybet', name: 'SportyBet', brand: 'sportybet', bg: '#E90003', fg: '#ffffff', ref: 'SportyBet user ID' },
  { code: 'bet9ja', name: 'Bet9ja', brand: 'bet9ja', bg: '#006837', fg: '#ffffff', ref: 'Bet9ja user ID' },
  { code: 'betking', name: 'BetKing', brand: 'betking', bg: '#1E3A8A', fg: '#ffffff', ref: 'BetKing user ID' },
  { code: 'nairabet', name: 'NairaBet', brand: 'nairabet', bg: '#F59E0B', fg: '#111827', ref: 'NairaBet user ID' },
]

export const billersByCategory: Partial<Record<BillCategory, Biller[]>> = {
  airtime: NETWORKS,
  data: NETWORKS,
  cable_tv: CABLE,
  electricity: ELECTRICITY,
  betting: BETTING,
}

const ALL_BILLERS: Biller[] = [...NETWORKS, ...CABLE, ...ELECTRICITY, ...BETTING]

/** Resolve a stored biller_code (supports legacy `9mobile` → T2). */
export function findBiller(code: string): Biller | null {
  const normalized = code === '9mobile' ? 't2' : code
  return ALL_BILLERS.find((b) => b.code === normalized) ?? null
}

export function billerDisplayName(code: string, fallback: string): string {
  return findBiller(code)?.name ?? fallback
}
