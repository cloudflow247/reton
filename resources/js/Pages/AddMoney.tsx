import type { FormEvent, ReactNode } from 'react'
import { useEffect, useState } from 'react'
import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { BvnVerificationGate } from '@/components/BvnVerificationGate'
import { ComplianceStrip } from '@/components/dashboard/DashboardKit'
import { StaticWalletCard } from '@/components/StaticWalletCard'
import { Page, pageItem } from '@/components/page-kit'
import { AmountField, Button, Card, CopyRow, Pill } from '@/components/ui'
import {
  BankIcon,
  BoltIcon,
  CardIcon,
  CheckIcon,
  LockIcon,
  ShieldIcon,
} from '@/components/icons'
import { shortFundingAccountName } from '@/lib/funding-account-name'
import { ngn, toMinor } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { Deposit, KycProfile, PageProps, StaticAccount } from '@/types'

type DepositMethod = 'bank_transfer' | 'alatpay_checkout' | 'alatpay_card'

type AddMoneyProps = PageProps<{
  pendingDeposit: Deposit | null
  openDeposits: Deposit[]
  kyc: KycProfile
  staticAccount: StaticAccount | null
  bvnPendingOtp?: boolean
  bvnOtpHint?: string | null
  bvnProvider?: string
  bvnDemoMode?: boolean
}>

const methods: {
  id: DepositMethod
  title: string
  subtitle: string
  icon: typeof BankIcon
}[] = [
  {
    id: 'alatpay_checkout',
    title: 'Checkout',
    subtitle: 'Card · transfer · USSD',
    icon: ShieldIcon,
  },
  {
    id: 'alatpay_card',
    title: 'Card',
    subtitle: 'Visa · Mastercard · Verve',
    icon: CardIcon,
  },
  {
    id: 'bank_transfer',
    title: 'One-time',
    subtitle: 'Temporary account',
    icon: BankIcon,
  },
]

const methodLabel: Record<DepositMethod, string> = {
  bank_transfer: 'Bank transfer',
  alatpay_checkout: 'Secure checkout',
  alatpay_card: 'Card payment',
}

const ctaLabel: Record<DepositMethod, string> = {
  bank_transfer: 'Generate transfer account',
  alatpay_checkout: 'Continue to checkout',
  alatpay_card: 'Continue to card',
}

export default function AddMoney() {
  const {
    auth,
    flash,
    pendingDeposit,
    openDeposits: openDepositsProp,
    kyc,
    staticAccount,
    bvnPendingOtp,
    bvnOtpHint,
    bvnProvider,
    bvnDemoMode,
  } = usePage<AddMoneyProps>().props
  const openDeposits = Array.isArray(openDepositsProp) ? openDepositsProp : []
  const wallet = auth.wallets[0]
  const profileName = auth.user?.name ?? null

  const [amount, setAmount] = useState('')
  const [method, setMethod] = useState<DepositMethod>('alatpay_checkout')
  const [dismissed, setDismissed] = useState(false)
  const form = useForm({ wallet_id: wallet?.id ?? '', amount: 0, method: 'alatpay_checkout' as DepositMethod })
  const minor = toMinor(amount)

  const activeDeposit =
    pendingDeposit && (pendingDeposit.status === 'pending' || pendingDeposit.status === 'completed')
      ? pendingDeposit
      : null

  useEffect(() => {
    if (!activeDeposit || activeDeposit.status !== 'pending' || dismissed) {
      return
    }

    const timer = window.setInterval(() => {
      router.reload({ only: ['pendingDeposit', 'openDeposits'] })
    }, 5000)

    return () => window.clearInterval(timer)
  }, [activeDeposit?.id, activeDeposit?.status, dismissed])

  function submit(e: FormEvent) {
    e.preventDefault()
    form.transform((data) => ({ ...data, amount: toMinor(amount), method }))
    form.post('/deposits')
  }

  function resume(reference: string) {
    router.get('/add-money', { reference }, { preserveState: false })
    setDismissed(false)
  }

  if (activeDeposit?.status === 'completed' && !dismissed) {
    return (
      <AppShell>
        <SuccessPanel deposit={activeDeposit} onDismiss={() => goFresh(setDismissed)} />
      </AppShell>
    )
  }

  if (activeDeposit?.status === 'pending' && !dismissed) {
    if (activeDeposit.method === 'bank_transfer') {
      return (
        <AppShell>
          <BankTransferPanel
            deposit={activeDeposit}
            profileName={profileName}
            onDismiss={() => goFresh(setDismissed)}
          />
        </AppShell>
      )
    }

    return (
      <AppShell>
        <CheckoutPendingPanel deposit={activeDeposit} onDismiss={() => goFresh(setDismissed)} />
      </AppShell>
    )
  }

  return (
    <AppShell>
      <Page narrow className="max-w-lg space-y-4">
        <Head title="Add money" />

        <motion.header variants={pageItem} className="flex items-end justify-between gap-3 px-0.5">
          <div className="min-w-0">
            <h1 className="font-display text-2xl font-bold tracking-tight text-text">Add money</h1>
            <p className="mt-0.5 text-sm text-muted">Bank transfer or checkout</p>
          </div>
          {wallet && (
            <div className="text-right">
              <p className="text-[10px] font-semibold uppercase tracking-wide text-muted">Available</p>
              <p className="font-num text-sm font-bold text-mint">{ngn(wallet.available_balance)}</p>
            </div>
          )}
        </motion.header>

        {kyc?.bvn_verified && (
          <StaticWalletCard
            kyc={kyc}
            staticAccount={staticAccount}
            wallet={wallet}
            profileName={profileName}
          />
        )}

        {!kyc?.bvn_verified ? (
          <BvnVerificationGate
            pendingOtp={bvnPendingOtp}
            otpHint={bvnOtpHint}
            provider={bvnProvider}
            demoMode={bvnDemoMode}
          />
        ) : (
          <>
            {openDeposits.length > 0 && (
              <Card className="space-y-2.5 p-3.5">
                <p className="text-xs font-semibold text-text">Resume payment</p>
                <ul className="space-y-2">
                  {openDeposits.map((deposit) => (
                    <li
                      key={deposit.id}
                      className="flex items-center justify-between gap-3 rounded-xl bg-surface-2/60 px-3 py-2"
                    >
                      <div className="min-w-0">
                        <p className="text-sm font-semibold">{ngn(deposit.amount)}</p>
                        <p className="truncate text-[11px] text-muted">
                          {methodLabel[deposit.method ?? 'bank_transfer']}
                        </p>
                      </div>
                      <Button className="shrink-0 px-3 py-1.5 text-xs" onClick={() => resume(deposit.reference)}>
                        Continue
                      </Button>
                    </li>
                  ))}
                </ul>
              </Card>
            )}

            <Card className="overflow-hidden p-0">
              <form onSubmit={submit} className="space-y-4 p-4">
                <div>
                  <p className="text-sm font-semibold text-text">Fund an amount</p>
                  <p className="mt-0.5 text-xs text-muted">Checkout or one-time transfer</p>
                </div>

                <AmountField value={amount} onChange={setAmount} invalid={!!form.errors.amount} />

                <div className="space-y-2">
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-muted">Method</p>
                  <div className="grid grid-cols-3 gap-1.5 rounded-xl bg-surface-2/70 p-1">
                    {methods.map((option) => {
                      const Icon = option.icon
                      const selected = method === option.id
                      return (
                        <button
                          key={option.id}
                          type="button"
                          onClick={() => setMethod(option.id)}
                          className={cn(
                            'flex flex-col items-center gap-1 rounded-lg px-1.5 py-2.5 text-center transition',
                            selected
                              ? 'bg-surface text-mint shadow-sm ring-1 ring-mint/20'
                              : 'text-muted hover:text-text',
                          )}
                        >
                          <Icon size={16} />
                          <span className="text-[11px] font-semibold leading-tight">{option.title}</span>
                        </button>
                      )
                    })}
                  </div>
                  <p className="text-center text-[11px] text-muted">
                    {methods.find((m) => m.id === method)?.subtitle}
                  </p>
                </div>

                {form.errors.amount && <p className="text-sm text-danger">{form.errors.amount}</p>}
                {form.errors.method && <p className="text-sm text-danger">{form.errors.method}</p>}
                {flash.error && <p className="text-sm text-danger">{flash.error}</p>}

                <Button
                  type="submit"
                  loading={form.processing}
                  disabled={minor < 100}
                  className="flex w-full items-center justify-center gap-2"
                >
                  {ctaLabel[method]}
                </Button>

                <p className="flex items-center justify-center gap-1.5 text-[11px] text-muted">
                  <LockIcon size={12} className="shrink-0 text-mint" />
                  Encrypted · we never see your card details
                </p>
              </form>
            </Card>

            <ComplianceStrip compact />
          </>
        )}
      </Page>
    </AppShell>
  )
}

function goFresh(setDismissed: (v: boolean) => void) {
  setDismissed(true)
  router.get('/add-money', { fresh: '1' }, { preserveState: false })
}

function BankTransferPanel({
  deposit,
  profileName,
  onDismiss,
}: {
  deposit: Deposit
  profileName: string | null
  onDismiss: () => void
}) {
  const va = deposit.virtual_account
  const accountName = shortFundingAccountName(va?.account_name, profileName)

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="mx-auto max-w-lg space-y-4"
    >
      <Head title="Add money" />

      <div className="flex items-center justify-between gap-3 px-0.5">
        <div>
          <h2 className="font-display text-xl font-bold">Transfer to fund</h2>
          <p className="text-sm text-muted">{ngn(deposit.amount)} · any Nigerian bank</p>
        </div>
        <Pill tone="amber">Awaiting</Pill>
      </div>

      <Card className="space-y-4 p-4">
        <p className="flex items-start gap-2 text-sm text-muted">
          <BoltIcon size={16} className="mt-0.5 shrink-0 text-mint" />
          Send exactly {ngn(deposit.amount)}. Credits automatically when it arrives.
        </p>
        <div className="divide-y divide-line rounded-2xl border border-line bg-surface-2/50 px-4">
          <CopyRow label="Bank" value={va?.bank_name ?? '—'} />
          <CopyRow label="Account number" value={va?.account_number ?? '—'} mono />
          <CopyRow label="Account name" value={accountName} />
        </div>
        <Button variant="ghost" className="w-full" onClick={onDismiss}>
          Start a new payment
        </Button>
      </Card>
    </motion.div>
  )
}

function CheckoutPendingPanel({ deposit, onDismiss }: { deposit: Deposit; onDismiss: () => void }) {
  const isCard = deposit.method === 'alatpay_card'
  const payUrl = `/deposits/${deposit.id}/pay`

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="mx-auto max-w-lg space-y-4"
    >
      <Head title="Add money" />

      <Card className="space-y-4 p-5">
        <div>
          <h2 className="font-display text-lg font-bold">Complete payment</h2>
          <p className="mt-1 text-sm text-muted">
            {ngn(deposit.amount)} · {isCard ? 'card' : 'checkout'}
          </p>
        </div>

        <a
          href={payUrl}
          className="btn flex w-full items-center justify-center gap-2 bg-mint py-3 text-sm text-white hover:bg-mint-strong"
        >
          {isCard ? <CardIcon size={18} /> : <ShieldIcon size={18} />}
          Continue
        </a>

        <p className="text-center text-xs text-muted">Waiting for confirmation…</p>

        <Button variant="ghost" className="w-full" onClick={onDismiss}>
          Start a new payment
        </Button>
      </Card>
    </motion.div>
  )
}

function SuccessPanel({ deposit, onDismiss }: { deposit: Deposit; onDismiss: () => void }) {
  const bank = deposit.bank_transfer
  const description =
    deposit.description ??
    (bank?.narration
      ? `Bank transfer — ${bank.narration}`
      : bank?.payer_name
        ? `Bank transfer from ${bank.payer_name}`
        : null)

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="mx-auto max-w-lg space-y-4"
    >
      <Head title="Add money" />

      <Card className="space-y-4 border-mint/30 p-5">
        <div className="flex items-start gap-3">
          <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-mint text-white">
            <CheckIcon size={22} />
          </span>
          <div>
            <h2 className="font-display text-lg font-bold">Wallet funded</h2>
            <p className="mt-1 text-sm text-muted">{ngn(deposit.amount)} added.</p>
          </div>
        </div>

        {(description || bank || deposit.provider_reference) && (
          <div className="space-y-1.5 rounded-2xl border border-line bg-surface-2/40 px-4 py-3 text-sm">
            {description && <p className="font-medium text-text">{description}</p>}
            {bank?.payer_name && (
              <p className="text-xs text-muted">
                From <span className="text-text">{bank.payer_name}</span>
                {bank.bank_name ? ` · ${bank.bank_name}` : ''}
              </p>
            )}
            <p className="font-mono text-[11px] text-muted">Ref {deposit.reference}</p>
          </div>
        )}

        <div className="flex flex-wrap gap-2">
          <Link href="/dashboard" className="btn bg-mint px-5 py-2.5 text-sm text-white hover:bg-mint-strong">
            Dashboard
          </Link>
          <Button variant="ghost" className="px-4 py-2" onClick={onDismiss}>
            Add more
          </Button>
        </div>
      </Card>
    </motion.div>
  )
}

AddMoney.layout = (page: ReactNode) => page
