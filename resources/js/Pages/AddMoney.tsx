import type { FormEvent, ReactNode } from 'react'
import { useEffect, useState } from 'react'
import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { AlatMark } from '@/components/PoweredByAlat'
import { StaticWalletCard } from '@/components/StaticWalletCard'
import { Page, PageHero } from '@/components/page-kit'
import { AmountField, Button, Card, CopyRow, Pill } from '@/components/ui'
import { BankIcon, BoltIcon, CardIcon, CheckIcon, LockIcon, PlusIcon, ShieldIcon } from '@/components/icons'
import { ngn, toMinor } from '@/lib/format'
import type { Deposit, KycProfile, PageProps, StaticAccount } from '@/types'

type DepositMethod = 'bank_transfer' | 'alatpay_checkout' | 'alatpay_card'

type AddMoneyProps = PageProps<{
  pendingDeposit: Deposit | null
  openDeposits: Deposit[]
  kyc: KycProfile
  staticAccount: StaticAccount | null
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
    title: 'Pay with ALATPay',
    subtitle: 'Card, transfer, USSD & more',
    detail: 'Secure hosted checkout — pick any ALATPay channel.',
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
    title: 'Transfer from any bank',
    subtitle: 'One-time account number',
    detail: 'Send from GTBank, Access, Zenith, or any Nigerian bank.',
    icon: BankIcon,
  },
]

const methodLabel: Record<DepositMethod, string> = {
  bank_transfer: 'Bank transfer',
  alatpay_checkout: 'ALATPay checkout',
  alatpay_card: 'Card payment',
}

export default function AddMoney() {
  const { auth, flash, pendingDeposit, openDeposits, kyc, staticAccount } = usePage<AddMoneyProps>().props
  const wallet = auth.wallets[0]

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
    if (!activeDeposit || activeDeposit.status !== 'pending') {
      return
    }

    const timer = window.setInterval(() => {
      router.reload({ only: ['pendingDeposit', 'openDeposits'] })
    }, 5000)

    return () => window.clearInterval(timer)
  }, [activeDeposit?.id, activeDeposit?.status])

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
          <BankTransferPanel deposit={activeDeposit} onDismiss={() => goFresh(setDismissed)} />
        </AppShell>
      )
    }

    return (
      <AppShell>
        <CheckoutPendingPanel deposit={activeDeposit} onDismiss={() => goFresh(setDismissed)} />
      </AppShell>
    )
  }

  const otherOpen = openDeposits.filter((d) => d.reference !== pendingDeposit?.reference)

  return (
    <AppShell>
      <Page narrow className="max-w-lg">
        <Head title="Add money" />

        <PageHero
          icon={PlusIcon}
          title="Add money"
          subtitle="Fund your wallet — bank transfer, card, or ALATPay checkout."
          balance={wallet?.available_balance}
          tone="mint"
        />

        <StaticWalletCard kyc={kyc} staticAccount={staticAccount} wallet={wallet} />

        {openDeposits.length > 0 && (
          <Card className="space-y-3 p-4">
            <p className="text-sm font-semibold">Resume a payment</p>
            <p className="text-xs text-muted">
              Pending payments stay saved — reload or come back anytime before they expire.
            </p>
            <ul className="space-y-2">
              {openDeposits.map((deposit) => (
                <li
                  key={deposit.id}
                  className="flex items-center justify-between gap-3 rounded-xl border border-line bg-surface-2/50 px-3 py-2.5"
                >
                  <div className="min-w-0">
                    <p className="text-sm font-medium">{ngn(deposit.amount)}</p>
                    <p className="text-[11px] text-muted">
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

        <Card className="space-y-5 p-5">
          <form onSubmit={submit} className="space-y-5">
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
                      className={`flex items-start gap-3 rounded-2xl border p-3.5 text-left transition ${
                        selected
                          ? 'border-mint/50 bg-mint/5 ring-1 ring-mint/20'
                          : 'border-line bg-surface-2/50 hover:border-mint/25'
                      }`}
                    >
                      <span
                        className={`mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${
                          selected ? 'bg-mint text-white' : 'bg-surface text-muted'
                        }`}
                      >
                        {option.id === 'alatpay_checkout' ? <AlatMark size={28} /> : <Icon size={18} />}
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
                        className={`mt-1 h-4 w-4 shrink-0 rounded-full border ${
                          selected ? 'border-mint bg-mint' : 'border-line'
                        }`}
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
                  <ShieldIcon size={18} /> Continue to ALATPay
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
                ? 'Your transfer details are saved — you can leave and return from this page anytime.'
                : 'You will be redirected to ALATPay’s secure checkout. We never see your card or bank login.'}
            </p>
          </form>
        </Card>

        <Card className="flex items-center gap-3 p-4">
          <AlatMark size={36} />
          <p className="text-xs leading-relaxed text-muted">
            Settlement is powered by <span className="font-medium text-text">ALAT by Wema</span> — a licensed bank rail.
            Every deposit is ledger-backed and reconciled.
          </p>
        </Card>
      </Page>
    </AppShell>
  )
}

function goFresh(setDismissed: (v: boolean) => void) {
  setDismissed(true)
  router.get('/add-money', { fresh: '1' }, { preserveState: false })
}

function BankTransferPanel({ deposit, onDismiss }: { deposit: Deposit; onDismiss: () => void }) {
  const va = deposit.virtual_account

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="mx-auto max-w-lg space-y-5"
    >
      <Head title="Add money" />

      <div className="brand-card sheen relative overflow-hidden p-6 text-white shield-glow">
        <div className="relative flex items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15">
              <BankIcon size={22} />
            </span>
            <div>
              <h2 className="font-display text-lg font-bold">Transfer to fund</h2>
              <p className="text-sm text-white/75">{ngn(deposit.amount)} · any Nigerian bank</p>
            </div>
          </div>
          <Pill tone="amber">Awaiting transfer</Pill>
        </div>
      </div>

      <Card className="space-y-5">
        <p className="flex items-start gap-2 text-sm text-muted">
          <BoltIcon size={16} className="mt-0.5 shrink-0 text-mint" />
          Send exactly {ngn(deposit.amount)} to the account below. Your wallet credits automatically when it arrives.
        </p>
        <div className="divide-y divide-line rounded-2xl border border-line bg-surface-2/50 px-4">
          <CopyRow label="Bank" value={va?.bank_name ?? '—'} />
          <CopyRow label="Account number" value={va?.account_number ?? '—'} mono />
          <CopyRow label="Account name" value={va?.account_name ?? '—'} />
        </div>
        <p className="rounded-xl border border-mint/20 bg-mint/5 px-3 py-2 text-xs text-muted">
          <LockIcon size={12} className="mr-1 inline text-mint" />
          This payment is saved under ref <span className="font-mono text-text">{deposit.reference}</span>. Reload or
          bookmark this page — your account details will still be here until funded.
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
            {isCard ? <CardIcon size={22} /> : <AlatMark size={32} />}
          </span>
          <div>
            <h2 className="font-display text-lg font-bold">Complete your payment</h2>
            <p className="mt-1 text-sm text-muted">
              {ngn(deposit.amount)} · {isCard ? 'card checkout' : 'ALATPay checkout'}
            </p>
          </div>
        </div>

        <p className="text-xs text-muted">
          Ref <span className="font-mono text-text">{deposit.reference}</span> — saved until you pay or it expires.
          You can leave and return here anytime.
        </p>

        <a href={payUrl} className="btn flex w-full items-center justify-center gap-2 bg-mint py-3 text-sm text-white hover:bg-mint-strong">
          {isCard ? <CardIcon size={18} /> : <ShieldIcon size={18} />}
          {isCard ? 'Continue to card checkout' : 'Continue to ALATPay'}
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
        <div className="flex flex-wrap gap-2">
          <Link href="/dashboard" className="btn bg-mint px-5 py-2.5 text-sm text-white hover:bg-mint-strong">
            Go to dashboard
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
