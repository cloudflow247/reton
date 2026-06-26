import type { Bill, Deposit, User, Wallet } from '@/lib/types'

export type TransferReceipt = {
  reference: string
  type: 'normal' | 'protected'
  amount: number
  recipient_name: string
}

export type DemoInfo = {
  password: string
  pin: string
  accounts: { name: string; email: string }[]
}

/** Props shared with every page via HandleInertiaRequests::share(). */
export type SharedProps = {
  auth: {
    user: User | null
    wallets: Wallet[]
  }
  demo: DemoInfo | null
  flash: {
    success: string | null
    error: string | null
    deposit: Deposit | null
    transfer: TransferReceipt | null
    bill: Bill | null
  }
}

/** Helper for page-specific props: PageProps<{ foo: Bar }>. */
export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> =
  SharedProps & T
