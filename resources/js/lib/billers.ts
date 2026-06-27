import type { BillCategory } from '@/lib/types'

/**
 * Known billers, grouped by category, with brand-accurate tile colours. Picking
 * one fills the biller code + name on the Bills form. `short` is the compact
 * label shown on the coloured tile; `name` is the full label beside it.
 */
export type Biller = {
  code: string
  name: string
  short: string
  bg: string
  fg: string
  /** Optional reference hint shown as the customer-reference placeholder. */
  ref?: string
}

const NETWORKS: Biller[] = [
  { code: 'mtn', name: 'MTN', short: 'MTN', bg: '#FFCB05', fg: '#16130b', ref: '0803 000 0000' },
  { code: 'glo', name: 'Glo', short: 'glo', bg: '#00A859', fg: '#ffffff', ref: '0805 000 0000' },
  { code: 'airtel', name: 'Airtel', short: 'airtel', bg: '#E40000', fg: '#ffffff', ref: '0802 000 0000' },
  { code: '9mobile', name: '9mobile', short: '9', bg: '#006E51', fg: '#9ee23f', ref: '0809 000 0000' },
]

const CABLE: Biller[] = [
  { code: 'dstv', name: 'DStv', short: 'DStv', bg: '#0095DA', fg: '#ffffff', ref: 'Smartcard number' },
  { code: 'gotv', name: 'GOtv', short: 'GOtv', bg: '#76BC21', fg: '#16302b', ref: 'IUC number' },
  { code: 'startimes', name: 'StarTimes', short: 'Star', bg: '#ED1C24', fg: '#ffffff', ref: 'Smartcard number' },
  { code: 'showmax', name: 'Showmax', short: 'SM', bg: '#191919', fg: '#ffffff', ref: 'Account / phone' },
]

const ELECTRICITY: Biller[] = [
  { code: 'ikedc', name: 'Ikeja Electric', short: 'IKE', bg: '#E11B22', fg: '#ffffff', ref: 'Meter number' },
  { code: 'ekedc', name: 'Eko Electric', short: 'EKO', bg: '#5B2D8E', fg: '#ffffff', ref: 'Meter number' },
  { code: 'ibedc', name: 'Ibadan Electric', short: 'IBE', bg: '#0a7a3b', fg: '#ffffff', ref: 'Meter number' },
  { code: 'aedc', name: 'Abuja Electric', short: 'AED', bg: '#0b5cab', fg: '#ffffff', ref: 'Meter number' },
  { code: 'phed', name: 'PH Electric', short: 'PHE', bg: '#1b4f9c', fg: '#ffffff', ref: 'Meter number' },
  { code: 'kedco', name: 'Kano Electric', short: 'KED', bg: '#16923c', fg: '#ffffff', ref: 'Meter number' },
]

export const billersByCategory: Partial<Record<BillCategory, Biller[]>> = {
  airtime: NETWORKS,
  data: NETWORKS,
  cable_tv: CABLE,
  electricity: ELECTRICITY,
}
