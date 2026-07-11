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
  PlusIcon,
  ShieldIcon,
  WalletIcon,
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
  detail: string
  icon: typeof BankIcon
  highlight?: boolean
}[] = [
  {
    id: 'alatpay_checkout',
    title: 'Secure checkout',
    subtitle: 'Card, transfer, USSD & more',
    detail: 'Encrypted hosted checkout — pick any channel you prefer.',
    icon: ShieldIcon,
    highlight: true,
  },
  {
    id: 'alatpay_card',
    title: 'Pay with card',
    subtitle: 'Visa · Mastercard · Verve',
    detail: 'Card-only checkout. Name prefilled from your Reton profile.',
    icon: CardIcon,
  },
  {
    id: 'bank_transfer',
    title: 'One-time transfer',
    subtitle: 'Exact amount · temporary account',
    detail: 'Generate a one-time account for this amount only.',
    icon: BankIcon,
  },
]

const methodLabel: Record<DepositMethod, string> = {
  bank_transfer: 'Bank transfer',
  alatpay_checkout: 'Secure checkout',
  alatpay_card: 'Card payment',
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
      <Page narrow className="max-w-lg">
        <Head title="Add money" />

        <motion.header
          variants={pageItem}
          className="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-[#0a6a4d] via-[#0e7e5c] to-[#094f39] p-4 text-white shadow-[0_20px_40px_-22px_rgba(9,79,57,0.75)] sm:p-5"
        >
          <div aria-hidden className="pointer-events-none absolute inset-0">
            <div className="absolute -right-14 -top-16 h-44 w-44 rounded-full bg-white/12 blur-3xl" />
            <div className="absolute -bottom-16 left-0 h-40 w-40 rounded-full bg-emerald-200/25 blur-3xl" />
          </div>

          <div className="relative flex items-start justify-between gap-3">
            <div className="min-w-0">
              <div className="flex items-center gap-2.5">
                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 backdrop-blur-sm">
                  <PlusIcon size={20} />
                </span>
                <div>
                  <h1 className="font-display text-xl font-bold tracking-tight sm:text-2xl">Add money</h1>
                  <p className="mt-0.5 text-xs text-white/80 sm:text-sm">
                    Fund instantly — bank transfer, card, or secure checkout.
                  </p>
                </div>
              </div>
            </div>
            {wallet && (
              <div className="rounded-xl bg-white/12 px-3 py-2 text-right backdrop-blur-sm">
                <div className="flex items-center justify-end gap-1 text-[10px] font-bold uppercase tracking-wide text-white/70">
                  <WalletIcon size={11} /> Available
                </div>
                <div className="font-num text-sm font-bold text-white">{ngn(wallet.available_balance)}</div>
              </div>
            )}
          </div>

          <div className="relative mt-4 grid grid-cols-3 gap-2">
            {[
              { icon: BankIcon, label: 'Bank fund' },
              { icon: ShieldIcon, label: 'Encrypted' },
              { icon: LockIcon, label: 'Ledger-backed' },
            ].map(({ icon: Icon, label }) => (
              <div
                key={label}
                className="rounded-xl border border-white/20 bg-black/15 px-2 py-2 text-center"
              >
                <Icon size={14} className="mx-auto text-white" />
                <p className="mt-1 text-[10px] font-bold text-white/90">{label}</p>
              </div>
            ))}
          </div>
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
              <Card className="space-y-3 border-amber/20 p-4">
                <div className="flex items-center gap-2">
                  <Pill tone="amber">Saved</Pill>
                  <p className="text-sm font-semibold">Resume a payment</p>
                </div>
                <p className="text-xs text-muted">
                  Pending payments stay saved — come back anytime before they expire.
                </p>
                <ul className="space-y-2">
                  {openDeposits.map((deposit) => (
                    <li
                      key={deposit.id}
                      className="flex items-center justify-between gap-3 rounded-xl border border-line bg-surface-2/50 px-3 py-2.5"
                    >
                      <div className="min-w-0">
                        <p className="text-sm font-medium">{ngn(deposit.amount)}</p>
                        <p className="truncate text-[11px] text-muted">
                          {methodLabel[deposit.method ?? 'bank_transfer']} · {deposit.reference}
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

            <Card className="space-y-5 overflow-hidden p-0">
              <div className="border-b border-line/80 bg-surface-2/40 px-5 py-4">
                <p className="text-sm font-semibold text-text">Or fund a specific amount</p>
                <p className="mt-0.5 text-xs text-muted">
                  Use checkout or a one-time transfer when you need an exact amount.
                </p>
              </div>

              <form onSubmit={submit} className="space-y-5 px-5 pb-5">
                <AmountField value={amount} onChange={setAmount} invalid={!!form.errors.amount} />
                {minor > 0 && (
                  <p className="text-xs text-muted">
                    Funding <span className="font-num font-semibold text-text">{ngn(minor)}</span>
                  </p>
                )}

                <div className="space-y-2">
                  <p className="text-xs font-semibold uppercase tracking-wide text-muted">Payment method</p>
                  <div className="grid gap-2">
                    {methods.map((option) => {
                      const Icon = option.icon
                      const selected = method === option.id
                      return (
                        <button
                          key={option.id}
                          type="button"
                          onClick={() => setMethod(option.id)}
                          className={cn(
                            'flex items-start gap-3 rounded-2xl border p-3.5 text-left transition',
                            selected
                              ? 'border-mint/50 bg-mint/5 ring-1 ring-mint/20'
                              : 'border-line bg-surface-2/50 hover:border-mint/25',
                          )}
                        >
                          <span
                            className={cn(
                              'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                              selected ? 'bg-mint text-white' : 'bg-surface text-muted',
                            )}
                          >
                            <Icon size={18} />
                          </span>
                          <span className="min-w-0 flex-1">
                            <span className="flex flex-wrap items-center gap-2">
                              <span className="text-sm font-semibold text-text">{option.title}</span>
                              {option.highlight && selected && <Pill tone="mint">Recommended</Pill>}
                            </span>
                            <span className="mt-0.5 block text-xs text-mint">{option.subtitle}</span>
                            <span className="mt-1 block text-[11px] leading-relaxed text-muted">{option.detail}</span>
                          </span>
                          <span
                            className={cn(
                              'mt-1 h-4 w-4 shrink-0 rounded-full border',
                              selected ? 'border-mint bg-mint' : 'border-line',
                            )}
                          />
                        </button>
                      )
                    })}
                  </div>
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
                  {method === 'bank_transfer' && (
                    <>
                      <BankIcon size={18} /> Generate transfer account
                    </>
                  )}
                  {method === 'alatpay_checkout' && (
                    <>
                      <ShieldIcon size={18} /> Continue to secure checkout
                    </>
                  )}
                  {method === 'alatpay_card' && (
                    <>
                      <CardIcon size={18} /> Continue to card checkout
                    </>
                  )}
                </Button>

                <p className="flex items-center gap-2 border-t border-line pt-4 text-[11px] text-muted">
                  <LockIcon size={13} className="shrink-0" />
                  {method === 'bank_transfer'
                    ? 'Your transfer details are saved — leave and return anytime.'
                    : 'Redirected to secure checkout. We never see your card or bank login.'}
                </p>
              </form>
            </Card>

            <ComplianceStrip />
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
      className="mx-auto max-w-lg space-y-5"
    >
      <Head title="Add money" />

      <div className="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-[#0a6a4d] via-[#0e7e5c] to-[#094f39] p-5 text-white shadow-[0_20px_40px_-22px_rgba(9,79,57,0.75)]">
        <div className="relative flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex min-w-0 items-center gap-3">
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15">
              <BankIcon size={22} />
            </span>
            <div className="min-w-0">
              <h2 className="font-display text-lg font-bold">Transfer to fund</h2>
              <p className="text-sm text-white/75">{ngn(deposit.amount)} · any Nigerian bank</p>
            </div>
          </div>
          <Pill tone="amber">Awaiting transfer</Pill>
        </div>
      </div>

      <Card className="space-y-5 p-5">
        <p className="flex items-start gap-2 text-sm text-muted">
          <BoltIcon size={16} className="mt-0.5 shrink-0 text-mint" />
          Send exactly {ngn(deposit.amount)} to the account below. Your wallet credits automatically when it arrives.
        </p>
        <div className="divide-y divide-line rounded-2xl border border-line bg-surface-2/50 px-4">
          <CopyRow label="Bank" value={va?.bank_name ?? '—'} />
          <CopyRow label="Account number" value={va?.account_number ?? '—'} mono />
          <CopyRow label="Account name" value={accountName} />
        </div>
        <p className="rounded-xl border border-mint/20 bg-mint/5 px-3 py-2 text-xs text-muted">
          <LockIcon size={12} className="mr-1 inline text-mint" />
          Saved under ref <span className="font-mono text-text">{deposit.reference}</span>. Reload anytime — details stay
          until funded.
        </p>
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
      className="mx-auto max-w-lg space-y-5"
    >
      <Head title="Add money" />

      <Card className="space-y-4 p-6">
        <div className="flex items-start gap-3">
          <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-mint/10 text-mint">
            {isCard ? <CardIcon size={22} /> : <ShieldIcon size={22} />}
          </span>
          <div>
            <h2 className="font-display text-lg font-bold">Complete your payment</h2>
            <p className="mt-1 text-sm text-muted">
              {ngn(deposit.amount)} · {isCard ? 'card checkout' : 'secure checkout'}
            </p>
          </div>
        </div>

        <p className="text-xs text-muted">
          Ref <span className="font-mono text-text">{deposit.reference}</span> — saved until you pay or it expires.
        </p>

        <a
          href={payUrl}
          className="btn flex w-full items-center justify-center gap-2 bg-mint py-3 text-sm text-white hover:bg-mint-strong"
        >
          {isCard ? <CardIcon size={18} /> : <ShieldIcon size={18} />}
          {isCard ? 'Continue to card checkout' : 'Continue to secure checkout'}
        </a>

        <p className="rounded-xl border border-amber/25 bg-amber/5 px-3 py-2 text-xs text-muted">
          Waiting for confirmation… This page refreshes every few seconds once you have paid.
        </p>

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
      className="mx-auto max-w-lg space-y-5"
    >
      <Head title="Add money" />

      <Card className="space-y-4 border-mint/30 p-6">
        <div className="flex items-start gap-3">
          <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-mint text-white">
            <CheckIcon size={24} />
          </span>
          <div>
            <h2 className="font-display text-lg font-bold">Wallet funded</h2>
            <p className="mt-1 text-sm text-muted">{ngn(deposit.amount)} has been added to your wallet.</p>
          </div>
        </div>

        {(description || bank || deposit.provider_reference) && (
          <div className="space-y-2 rounded-2xl border border-line bg-surface-2/40 px-4 py-3 text-sm">
            <p className="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted">Transfer receipt</p>
            {description && <p className="font-medium text-text">{description}</p>}
            {bank?.payer_name && (
              <p className="text-xs text-muted">
                From <span className="text-text">{bank.payer_name}</span>
                {bank.bank_name ? ` · ${bank.bank_name}` : ''}
              </p>
            )}
            {(deposit.provider_reference || bank?.provider_reference) && (
              <p className="font-mono text-[11px] text-muted">
                Payment ref {deposit.provider_reference ?? bank?.provider_reference}
              </p>
            )}
            <p className="font-mono text-[11px] text-muted">Reton ref {deposit.reference}</p>
          </div>
        )}

        <div className="flex flex-wrap gap-2">
          <Link href="/dashboard" className="btn bg-mint px-5 py-2.5 text-sm text-white hover:bg-mint-strong">
            Go to dashboard
          </Link>
          <Link href="/activity" className="btn border border-line bg-surface px-4 py-2.5 text-sm hover:border-mint/30">
            View activity
          </Link>
          <Button variant="ghost" className="px-4 py-2" onClick={onDismiss}>
            Add more money
          </Button>
        </div>
      </Card>
    </motion.div>
  )
}

AddMoney.layout = (page: ReactNode) => page
