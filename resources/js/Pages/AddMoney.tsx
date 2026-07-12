import type { FormEvent, ReactNode } from 'react'
import { useEffect, useState } from 'react'
import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { BvnVerificationGate } from '@/components/BvnVerificationGate'
import { ComplianceStrip } from '@/components/dashboard/DashboardKit'
import { StaticWalletCard } from '@/components/StaticWalletCard'
import { Page, PageHeader } from '@/components/page-kit'
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
import type { Deposit, FeatureFlags, KycProfile, PageProps, StaticAccount } from '@/types'

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
  feature: keyof FeatureFlags
  icon: typeof BankIcon
}[] = [
  {
    id: 'alatpay_checkout',
    title: 'Checkout',
    subtitle: 'Card · transfer · USSD',
    feature: 'checkout',
    icon: ShieldIcon,
  },
  {
    id: 'alatpay_card',
    title: 'Card',
    subtitle: 'Visa · Mastercard · Verve',
    feature: 'card_pay',
    icon: CardIcon,
  },
  {
    id: 'bank_transfer',
    title: 'One-time',
    subtitle: 'Temporary account',
    feature: 'one_time',
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

const trustPoints = [
  { title: 'Bank-grade rails', detail: 'Settled through licensed ALATPay / Wema collection' },
  { title: 'Encrypted identity', detail: 'BVN verified once — never stored in plain text' },
  { title: 'Ledger-backed credit', detail: 'Every naira posts to an immutable double-entry ledger' },
  { title: 'PIN on spend', detail: 'Outbound payments always require your transaction PIN' },
] as const

export default function AddMoney() {
  const {
    auth,
    flash,
    features,
    pendingDeposit,
    openDeposits: openDepositsProp,
    kyc,
    staticAccount,
    bvnPendingOtp,
    bvnOtpHint,
    bvnProvider,
    bvnDemoMode,
  } = usePage<AddMoneyProps>().props
  const wallet = auth.wallets[0]
  const profileName = auth.user?.name ?? null

  const enabledMethods = methods.filter((option) => Boolean(features?.[option.feature]))
  const amountMethodsLive = enabledMethods.length > 0
  const defaultMethod = (enabledMethods[0]?.id ?? 'alatpay_checkout') as DepositMethod
  const openDeposits = (Array.isArray(openDepositsProp) ? openDepositsProp : []).filter((deposit) =>
    enabledMethods.some((option) => option.id === (deposit.method ?? 'bank_transfer')),
  )

  const [amount, setAmount] = useState('')
  const [dismissed, setDismissed] = useState(false)
  const form = useForm({
    wallet_id: wallet?.id ?? '',
    amount: 0,
    method: defaultMethod,
  })
  const method = form.data.method
  const minor = toMinor(amount)
  const hasActiveStatic = Boolean(staticAccount?.status === 'active' && staticAccount.account_number)

  useEffect(() => {
    if (!enabledMethods.some((option) => option.id === form.data.method) && enabledMethods[0]) {
      form.setData('method', enabledMethods[0].id)
    }
  }, [enabledMethods, form.data.method])

  function selectMethod(next: DepositMethod) {
    if (!enabledMethods.some((option) => option.id === next)) {
      return
    }
    form.setData('method', next)
  }

  const activeDeposit =
    pendingDeposit &&
    (pendingDeposit.status === 'pending' || pendingDeposit.status === 'completed') &&
    (pendingDeposit.status === 'completed' ||
      enabledMethods.some((option) => option.id === (pendingDeposit.method ?? 'bank_transfer')))
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
    if (!amountMethodsLive) {
      return
    }
    form.transform((data) => ({
      ...data,
      amount: toMinor(amount),
      method: data.method,
    }))
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

        <PageHeader
          title="Add money"
          subtitle={
            amountMethodsLive
              ? 'Fund safely — bank transfer or checkout'
              : 'Transfer to your protected deposit account'
          }
          balance={wallet?.available_balance}
        />

        {flash.success && (
          <p className="rounded-2xl border border-mint/25 bg-mint/5 px-4 py-3 text-sm text-mint">
            {flash.success}
          </p>
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
            <motion.div
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
              className="space-y-3"
            >
              {!amountMethodsLive && hasActiveStatic && (
                <div className="flex items-center gap-2 px-0.5">
                  <span className="inline-flex items-center gap-1.5 rounded-full bg-mint/10 px-2.5 py-1 text-[11px] font-semibold text-mint">
                    <ShieldIcon size={12} />
                    Recommended
                  </span>
                  <p className="text-xs text-muted">Same account every time · auto-credits</p>
                </div>
              )}

              <StaticWalletCard
                kyc={kyc}
                staticAccount={staticAccount}
                wallet={wallet}
                profileName={profileName}
              />
            </motion.div>

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
                          {methodLabel[(deposit.method ?? 'bank_transfer') as DepositMethod]}
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

            {amountMethodsLive ? (
              <Card className="overflow-hidden p-0">
                <form onSubmit={submit} className="space-y-4 p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-text">Fund an amount</p>
                      <p className="mt-0.5 text-xs text-muted">Checkout or one-time transfer</p>
                    </div>
                    <span className="inline-flex items-center gap-1 rounded-full bg-mint/10 px-2 py-1 text-[10px] font-semibold text-mint">
                      <LockIcon size={11} />
                      Secure
                    </span>
                  </div>

                  <AmountField value={amount} onChange={setAmount} invalid={!!form.errors.amount} />

                  <fieldset className="relative z-10 space-y-2">
                    <legend className="text-[10px] font-semibold uppercase tracking-wide text-muted">Method</legend>
                    <div
                      className="grid grid-cols-3 gap-1.5 rounded-xl bg-surface-2/70 p-1"
                      role="radiogroup"
                      aria-label="Funding method"
                    >
                      {methods.map((option) => {
                        const Icon = option.icon
                        const live = Boolean(features?.[option.feature])
                        const selected = live && method === option.id
                        return (
                          <label
                            key={option.id}
                            className={cn(
                              'relative flex min-h-14 flex-col items-center justify-center gap-1 rounded-lg px-1.5 py-2.5 text-center transition',
                              live ? 'cursor-pointer active:scale-[0.98]' : 'cursor-not-allowed opacity-45',
                              selected
                                ? 'bg-surface text-mint shadow-sm ring-1 ring-mint/25'
                                : live
                                  ? 'text-muted hover:bg-surface/60 hover:text-text'
                                  : 'text-muted',
                            )}
                          >
                            <input
                              type="radio"
                              name="deposit_method"
                              value={option.id}
                              checked={selected}
                              disabled={!live}
                              onChange={() => selectMethod(option.id)}
                              className="sr-only"
                            />
                            <Icon size={16} className="pointer-events-none" />
                            <span className="pointer-events-none text-[11px] font-semibold leading-tight">
                              {option.title}
                            </span>
                            {!live && (
                              <span className="pointer-events-none text-[9px] font-medium text-muted">Soon</span>
                            )}
                          </label>
                        )
                      })}
                    </div>
                    <p className="text-center text-[11px] text-muted" aria-live="polite">
                      {methods.find((m) => m.id === method)?.subtitle}
                    </p>
                  </fieldset>

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
            ) : (
              <ComingSoonFundingPanel flashError={flash.error} />
            )}

            <FundingSafetyPanel />
            <ComplianceStrip compact />
          </>
        )}
      </Page>
    </AppShell>
  )
}

function ComingSoonFundingPanel({ flashError }: { flashError?: string | null }) {
  return (
    <motion.section
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.32, ease: [0.22, 1, 0.36, 1], delay: 0.04 }}
      className="overflow-hidden rounded-2xl border border-line bg-surface shadow-[0_12px_28px_-22px_rgba(9,79,57,0.28)]"
      aria-label="More funding options coming soon"
    >
      <div className="relative border-b border-line/70 bg-[linear-gradient(135deg,rgba(9,79,57,0.08),transparent_55%)] px-4 pb-4 pt-4">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-[10px] font-semibold uppercase tracking-[0.14em] text-muted">More ways to fund</p>
            <h2 className="mt-1 font-display text-lg font-bold tracking-tight text-text">Checkout & card</h2>
            <p className="mt-1 max-w-sm text-sm leading-relaxed text-muted">
              Instant checkout, card, and one-time accounts are rolling out once ALATPay enables them on this
              merchant.
            </p>
          </div>
          <Pill tone="amber">Coming soon</Pill>
        </div>
      </div>

      <ul className="divide-y divide-line/70">
        {methods.map((option) => {
          const Icon = option.icon
          return (
            <li key={option.id} className="flex items-center gap-3 px-4 py-3.5">
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-muted">
                <Icon size={18} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold text-text">{option.title}</span>
                <span className="mt-0.5 block text-xs text-muted">{option.subtitle}</span>
              </span>
              <span className="shrink-0 rounded-full border border-line bg-surface-2/60 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-muted">
                Soon
              </span>
            </li>
          )
        })}
      </ul>

      <div className="space-y-3 border-t border-line/70 bg-surface-2/35 px-4 py-4">
        <p className="flex items-start gap-2 text-sm leading-relaxed text-muted">
          <BoltIcon size={16} className="mt-0.5 shrink-0 text-mint" />
          <span>
            For now, use your <span className="font-semibold text-text">permanent deposit account</span> above.
            Send from any Nigerian bank — Reton credits you automatically when the transfer settles.
          </span>
        </p>
        {flashError && <p className="text-sm text-danger">{flashError}</p>}
      </div>
    </motion.section>
  )
}

function FundingSafetyPanel() {
  return (
    <motion.section
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1], delay: 0.08 }}
      className="rounded-2xl border border-mint/20 bg-mint/5 p-4"
      aria-label="Funding safety"
    >
      <div className="flex items-start gap-3">
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-mint text-white">
          <ShieldIcon size={18} />
        </span>
        <div>
          <p className="text-sm font-semibold text-text">Built for safe deposits</p>
          <p className="mt-0.5 text-xs leading-relaxed text-muted">
            Trust-first funding — verify once, transfer with confidence, recover when something goes wrong.
          </p>
        </div>
      </div>

      <ul className="mt-4 grid gap-2.5 sm:grid-cols-2">
        {trustPoints.map((point) => (
          <li
            key={point.title}
            className="rounded-xl border border-line/80 bg-surface/90 px-3 py-2.5"
          >
            <p className="flex items-center gap-1.5 text-[12px] font-semibold text-text">
              <CheckIcon size={12} className="shrink-0 text-mint" />
              {point.title}
            </p>
            <p className="mt-1 text-[11px] leading-relaxed text-muted">{point.detail}</p>
          </li>
        ))}
      </ul>
    </motion.section>
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
