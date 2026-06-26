import type { Deposit, User, Wallet } from '@/lib/types'

export type TransferReceipt = {
  reference: string
  type: 'normal' | 'protected'
  amount: number
  recipient_name: string
}

/** Props shared with every page via HandleInertiaRequests::share(). */
export type SharedProps = {
  auth: {
    user: User | null
    wallets: Wallet[]
  }
  flash: {
    success: string | null
    error: string | null
    deposit: Deposit | null
    transfer: TransferReceipt | null
  }
}

/** Helper for page-specific props: PageProps<{ foo: Bar }>. */
export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> =
  SharedProps & T
