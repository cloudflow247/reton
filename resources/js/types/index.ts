import type { Bill, Deposit, Payout, User, Wallet } from '@/lib/types'

export type { Deposit, KycProfile, StaticAccount, DigitalListing, DigitalOrder, Wallet } from '@/lib/types'

export type TransferReceipt = {
  reference: string
  type: 'normal' | 'protected'
  amount: number
  recipient_name: string
}

export type DashboardSummary = {
  pending_callbacks: number
  open_recoveries: number
  protected_transfers_pending: number
  open_fraud_alerts: number
  trust_score: number
}

export type DemoInfo = {
  password: string
  pin: string
  accounts: { name: string; email: string }[]
}

/** Feature flags shared with every page (Coming Soon when false). */
export type FeatureFlags = {
  withdraw: boolean
  bills: boolean
  cards: boolean
  checkout: boolean
  card_pay: boolean
  one_time: boolean
  physical_listings: boolean
}

export type CountryDialCode = {
  iso: string
  name: string
  dial: string
}

/** Props shared with every page via HandleInertiaRequests::share(). */
export type SharedProps = {
  auth: {
    user: User | null
    wallets: Wallet[]
  }
  demo: DemoInfo | null
  /** Secret admin panel base path - only set for platform administrators. */
  adminPath: string | null
  features: FeatureFlags
  flash: {
    success: string | null
    error: string | null
    deposit: Deposit | null
    transfer: TransferReceipt | null
    bill: Bill | null
    payout: Payout | null
    support_ticket?: string | null
  }
}

/** Helper for page-specific props: PageProps<{ foo: Bar }>. */
export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> =
  SharedProps & T
