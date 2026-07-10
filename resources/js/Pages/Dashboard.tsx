import type { ReactNode } from 'react'
import { useMemo, useState } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { BalanceHeroCard, ComplianceStrip } from '@/components/dashboard/DashboardKit'
import { FormPanel, pageItem, pageList, Page, StepRow } from '@/components/page-kit'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  ArrowRightIcon,
  BankIcon,
  BillIcon,
  BoltIcon,
  CardIcon,
  ChevronRightIcon,
  GiftIcon,
  LockIcon,
  PhoneIcon,
  PlusIcon,
  ReceiveIcon,
  SendIcon,
  ShieldIcon,
  SignalIcon,
  SparkleIcon,
  TvIcon,
  WalletIcon,
} from '@/components/icons'
import { TrustProtectionListener } from '@/components/TrustProtectionListener'
import { ngn, shortDate } from '@/lib/format'
import { useCountUp } from '@/lib/useCountUp'
import { useUiStore } from '@/stores/ui-store'
import type { StatementEntry } from '@/lib/types'
import type { DashboardSummary, PageProps } from '@/types'

const list = pageList
const item = pageItem

const billShortcuts = [
  { to: '/bills?category=airtime', label: 'Airtime', Icon: PhoneIcon },
  { to: '/bills?category=data', label: 'Data', Icon: SignalIcon },
  { to: '/bills?category=electricity', label: 'Power', Icon: BoltIcon },
  { to: '/bills?category=cable_tv', label: 'TV', Icon: TvIcon },
  { to: '/bills?category=betting', label: 'Betting', Icon: GiftIcon },
  { to: '/bills?category=rrr', label: 'Remita', Icon: BillIcon },
] as const

const gettingStarted = [
  {
    step: 1,
    title: 'Add money',
    detail: 'Fund your wallet via bank transfer or ALATPay checkout.',
    href: '/add-money',
    icon: PlusIcon,
  },
  {
    step: 2,
    title: 'Set your PIN',
    detail: 'Secure every send, bill pay, and withdrawal with a 4-digit PIN.',
    href: '/pin',
    icon: LockIcon,
  },
  {
    step: 3,
    title: 'Send or withdraw',
    detail: 'Move money to Reton friends or your own bank — same name only.',
    href: '/send',
    icon: SendIcon,
  },
] as const

function greeting() {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 17) return 'Good afternoon'
  return 'Good evening'
}

function trustTone(score: number) {
  if (score >= 80) return { ring: 'text-mint', label: 'Strong', badge: 'default' as const, tip: 'Great standing — keep confirming protected transfers on time.' }
  if (score >= 60) return { ring: 'text-amber', label: 'Fair', badge: 'warning' as const, tip: 'Resolve open callbacks and recoveries to improve your score.' }
  return { ring: 'text-danger', label: 'At risk', badge: 'danger' as const, tip: 'Open the protection hub and clear pending items to restore trust.' }
}

export default function Dashboard() {
  const { auth, activity, summary, kycTier } = usePage<
    PageProps<{ activity: StatementEntry[]; summary: DashboardSummary; kycTier: number }>
  >().props
  const wallets = Array.isArray(auth?.wallets) ? auth.wallets : []
  const wallet = wallets[0]
  const [copied, setCopied] = useState(false)
  const hidden = useUiStore((s) => s.balanceHidden)
  const toggleHidden = useUiStore((s) => s.toggleBalanceHidden)
  const totalBalance = wallet?.balance ?? 0
  const availableBalance = wallet?.available_balance ?? 0
  const pendingBalance = wallet?.held_balance ?? 0
  const animatedAvailable = useCountUp(availableBalance)
  const recent = (Array.isArray(activity) ? activity : []).slice(0, 5)
  const firstName = (auth?.user?.name ?? 'there').split(' ')[0]
  const needsPin = !auth?.user?.has_transaction_pin
  const isNewUser = recent.length === 0

  const trust = summary ?? {
    pending_callbacks: 0,
    open_recoveries: 0,
    protected_transfers_pending: 0,
    open_fraud_alerts: 0,
    trust_score: 100,
  }

  const attentionCount =
    (trust.pending_callbacks ?? 0) +
    (trust.open_recoveries ?? 0) +
    (trust.open_fraud_alerts ?? 0) +
    (trust.protected_transfers_pending ?? 0)

  const tone = trustTone(Number(trust.trust_score ?? 100))

  const flow = useMemo(() => {
    const entries = Array.isArray(activity) ? activity : []
    const inflow = entries.filter((e) => e.direction === 'credit').reduce((s, e) => s + e.amount, 0)
    const outflow = entries.filter((e) => e.direction === 'debit').reduce((s, e) => s + e.amount, 0)
    return { inflow, outflow }
  }, [activity])

  return (
    <Page className="!pb-3">
      <Head title="Home" />
      {auth?.user?.id && <TrustProtectionListener userId={auth.user.id} only={['summary', 'activity']} />}

      <div className="lg:grid lg:grid-cols-12 lg:gap-6">
        <div className="space-y-5 lg:col-span-8">
      <motion.header variants={item} className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm text-muted">{greeting()}</p>
          <h1 className="font-display text-2xl font-bold tracking-tight sm:text-[1.65rem]">{firstName}</h1>
          <p className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted">
            <span>Trust-first wallet</span>
            <Badge variant="muted" className="text-[10px]">
              KYC Tier {kycTier ?? 1}
            </Badge>
          </p>
        </div>
        <Link
          href="/protection"
          className="elevate inline-flex shrink-0 items-center gap-1.5 rounded-full border border-line bg-surface px-3.5 py-2 text-xs font-semibold shadow-sm transition hover:border-mint/35"
        >
          <ShieldIcon size={14} className="text-mint" />
          <span className="text-mint">{trust.trust_score}</span>
          <span className="text-muted">trust</span>
        </Link>
      </motion.header>

      {attentionCount > 0 && (
        <motion.div variants={item}>
          <Link
            href="/protection"
            className="group flex items-center gap-3 overflow-hidden rounded-2xl border border-amber/30 bg-gradient-to-r from-amber/[0.1] to-amber/[0.04] px-4 py-3.5 transition hover:border-amber/45"
          >
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber/15 text-amber">
              <ShieldIcon size={20} />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block text-sm font-semibold text-text">
                {attentionCount} {attentionCount === 1 ? 'item needs' : 'items need'} attention
              </span>
              <span className="block text-xs text-muted">Tap to review callbacks, recoveries, or protected transfers</span>
            </span>
            <ArrowRightIcon size={18} className="shrink-0 text-amber transition group-hover:translate-x-0.5" />
          </Link>
        </motion.div>
      )}

      {(isNewUser || needsPin) && (
        <motion.div variants={item}>
          <FormPanel className="overflow-hidden border-mint/20 bg-gradient-to-br from-mint/[0.06] to-transparent">
            <p className="text-base font-semibold">Getting started</p>
            <p className="mt-0.5 text-xs text-muted">Three steps — about 2 minutes</p>
            <div className="mt-3 space-y-2">
              {gettingStarted.map((s) => (
                <StepRow key={s.step} {...s} />
              ))}
            </div>
          </FormPanel>
        </motion.div>
      )}

      <motion.div variants={item}>
        <BalanceHeroCard
          wallet={wallet}
          availableBalance={availableBalance}
          animatedAvailable={animatedAvailable}
          totalBalance={totalBalance}
          pendingBalance={pendingBalance}
          hidden={hidden}
          copied={copied}
          onToggleHidden={toggleHidden}
          onCopyAccount={() => {
            navigator.clipboard.writeText(wallet?.account_number ?? '')
            setCopied(true)
            setTimeout(() => setCopied(false), 1500)
          }}
        />
      </motion.div>

      <motion.div variants={item}>
        <p className="mb-2.5 px-0.5 text-xs font-semibold uppercase tracking-wide text-muted">Quick actions</p>
        <div className="grid grid-cols-3 gap-2 sm:grid-cols-6">
          <QuickAction href="/send" label="Send" Icon={SendIcon} primary />
          <QuickAction href="/add-money" label="Add" Icon={PlusIcon} />
          <QuickAction href="/withdraw" label="Withdraw" Icon={BankIcon} />
          <QuickAction href="/bills" label="Bills" Icon={BillIcon} />
          <QuickAction href="/cards" label="Cards" Icon={CardIcon} />
          <QuickAction href="/protection" label="Shield" Icon={ShieldIcon} highlight={attentionCount > 0} />
        </div>
      </motion.div>

      <motion.div variants={item}>
        <Link
          href="/marketplace"
          className="elevate group flex items-center gap-4 overflow-hidden rounded-2xl border border-mint/20 bg-gradient-to-br from-mint/[0.08] to-transparent p-4 transition hover:border-mint/35"
        >
          <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-mint/15 text-mint">
            <SparkleIcon size={22} />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block text-sm font-semibold text-text">Protected marketplace</span>
            <span className="block text-xs text-muted">Buy & sell with escrow — share a link or item code</span>
          </span>
          <ChevronRightIcon size={18} className="shrink-0 text-mint transition group-hover:translate-x-0.5" />
        </Link>
      </motion.div>

      <motion.div variants={item} className="lg:hidden">
        <Card className="border-mint/15">
          <CardHeader className="pb-2">
            <div className="flex items-center justify-between">
              <CardTitle className="flex items-center gap-2 text-base">
                <ShieldIcon size={18} className="text-mint" /> Trust shield
              </CardTitle>
              <Badge variant={tone.badge}>{tone.label}</Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex items-center gap-4">
              <TrustRing score={trust.trust_score} className={tone.ring} />
              <p className="text-xs leading-relaxed text-muted">{tone.tip}</p>
            </div>
            <div className="grid grid-cols-4 gap-2">
              <TrustStat label="Protected" value={trust.protected_transfers_pending} />
              <TrustStat label="Callbacks" value={trust.pending_callbacks} />
              <TrustStat label="Recoveries" value={trust.open_recoveries} />
              <TrustStat label="Alerts" value={trust.open_fraud_alerts} warn={trust.open_fraud_alerts > 0} />
            </div>
            <Link href="/protection" className="inline-flex items-center gap-1 text-sm font-semibold text-mint hover:underline">
              Open protection hub <ChevronRightIcon size={16} />
            </Link>
          </CardContent>
        </Card>
      </motion.div>

      <motion.div variants={item} className="lg:hidden">
        <ComplianceStrip />
      </motion.div>

      <motion.div variants={item}>
        <div className="flex items-center justify-between px-0.5">
          <div>
            <h2 className="text-sm font-semibold">Pay bills</h2>
            <p className="text-[11px] text-muted">Airtime · power · TV · betting & more</p>
          </div>
          <Link href="/bills" className="text-xs font-medium text-mint hover:underline">
            All bills
          </Link>
        </div>
        <div className="mt-2.5 grid grid-cols-3 gap-2 sm:grid-cols-6">
          {billShortcuts.map(({ to, label, Icon }) => (
            <motion.div key={to} whileHover={{ y: -2 }} whileTap={{ scale: 0.98 }}>
              <Link
                href={to}
                className="elevate flex flex-col items-center gap-1.5 rounded-2xl border border-line bg-surface px-2 py-3.5 transition hover:border-mint/30"
              >
                <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-mint/10 text-mint">
                  <Icon size={18} />
                </span>
                <span className="text-[11px] font-semibold">{label}</span>
              </Link>
            </motion.div>
          ))}
        </div>
      </motion.div>

      <motion.div variants={item}>
        <div className="flex items-center justify-between px-0.5">
          <h2 className="text-sm font-semibold">Recent activity</h2>
          <Link href="/activity" className="text-xs font-medium text-mint hover:underline">
            See all
          </Link>
        </div>

        <div className="card mt-2.5 overflow-hidden p-0">
          {recent.length > 0 ? (
            <ul className="divide-y divide-line">
              {recent.map((e, i) => (
                <motion.li
                  key={e.id}
                  initial={{ opacity: 0, x: -8 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: i * 0.04 }}
                >
                  <div className="flex items-center justify-between gap-3 px-4 py-3.5 transition hover:bg-surface-2/50">
                    <div className="flex min-w-0 items-center gap-3">
                      <span
                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${
                          e.direction === 'credit' ? 'bg-mint/10 text-mint' : 'bg-surface-2 text-muted'
                        }`}
                      >
                        {e.direction === 'credit' ? <ReceiveIcon size={17} /> : <SendIcon size={17} />}
                      </span>
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                          {e.transaction?.description ?? e.transaction?.type ?? 'Movement'}
                        </p>
                        <p className="text-xs text-muted">{shortDate(e.created_at)}</p>
                      </div>
                    </div>
                    <span
                      className={`shrink-0 font-num text-sm font-bold ${e.direction === 'credit' ? 'text-mint' : 'text-text'}`}
                    >
                      {e.direction === 'credit' ? '+' : '−'}
                      {ngn(e.amount)}
                    </span>
                  </div>
                </motion.li>
              ))}
            </ul>
          ) : (
            <div className="flex flex-col items-center gap-3 px-4 py-12 text-center">
              <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-mint/10 text-mint">
                <WalletIcon size={24} />
              </span>
              <p className="text-sm font-medium">No transactions yet</p>
              <p className="max-w-xs text-xs text-muted">Add money, then send, pay bills, or withdraw to your bank.</p>
              <Link
                href="/add-money"
                className="btn mt-1 inline-flex items-center gap-1.5 bg-mint px-4 py-2.5 text-sm text-white shadow-[0_10px_24px_-14px_rgba(9,79,57,0.65)] hover:bg-mint-strong"
              >
                <PlusIcon size={15} />
                Add money
              </Link>
            </div>
          )}
        </div>

        {(flow.inflow > 0 || flow.outflow > 0) && (
          <p className="mt-2.5 px-0.5 text-xs text-muted">
            Recent flow: <span className="font-num font-semibold text-mint">+{ngn(flow.inflow)}</span>
            <span className="mx-1 text-line">·</span>
            <span className="font-num font-semibold text-text">−{ngn(flow.outflow)}</span>
          </p>
        )}
      </motion.div>
        </div>

        <aside className="mt-5 hidden space-y-5 lg:col-span-4 lg:mt-0 lg:block">
          <motion.div variants={item}>
            <Card className="border-mint/15 bg-gradient-to-br from-mint/[0.04] to-transparent">
              <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-base">
                  <ShieldIcon size={18} className="text-mint" /> Trust shield
                </CardTitle>
                <CardDescription>Live protection metrics</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center gap-4">
                  <TrustRing score={trust.trust_score} className={tone.ring} />
                  <div>
                    <Badge variant={tone.badge}>{tone.label}</Badge>
                    <p className="mt-2 text-xs leading-relaxed text-muted">{tone.tip}</p>
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <TrustStat label="Protected" value={trust.protected_transfers_pending} />
                  <TrustStat label="Callbacks" value={trust.pending_callbacks} />
                  <TrustStat label="Recoveries" value={trust.open_recoveries} />
                  <TrustStat label="Alerts" value={trust.open_fraud_alerts} warn={trust.open_fraud_alerts > 0} />
                </div>
                <Link
                  href="/protection"
                  className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-text transition hover:border-mint/30 hover:text-mint"
                >
                  Open protection hub
                </Link>
              </CardContent>
            </Card>
          </motion.div>

          <motion.div variants={item}>
            <ComplianceStrip />
          </motion.div>

          <motion.div variants={item}>
            <Card>
              <CardHeader className="pb-2">
                <CardTitle className="text-base">Need help?</CardTitle>
                <CardDescription>AI support with live account lookup</CardDescription>
              </CardHeader>
              <CardContent>
                <Link
                  href="/support"
                  className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-text transition hover:border-mint/30 hover:text-mint"
                >
                  Chat with Reton Support
                </Link>
              </CardContent>
            </Card>
          </motion.div>
        </aside>
      </div>
    </Page>
  )
}

Dashboard.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function QuickAction({
  href,
  label,
  Icon,
  primary = false,
  highlight = false,
}: {
  href: string
  label: string
  Icon: (p: { size?: number }) => JSX.Element
  primary?: boolean
  highlight?: boolean
}) {
  return (
    <motion.div whileHover={{ y: -2 }} whileTap={{ scale: 0.97 }}>
      <Link
        href={href}
        className={`elevate relative flex min-h-[4.25rem] flex-col items-center justify-center gap-1.5 rounded-2xl border px-2 py-3 text-center transition ${
          primary
            ? 'border-mint/30 bg-mint text-white shadow-[0_12px_28px_-16px_rgba(9,79,57,0.5)] hover:bg-mint-strong'
            : highlight
              ? 'border-amber/35 bg-amber/[0.06] hover:border-amber/50'
              : 'border-line bg-surface hover:border-mint/30 hover:shadow-md'
        }`}
      >
        {highlight && !primary && (
          <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-amber ring-2 ring-surface" />
        )}
        <Icon size={20} className={primary ? 'text-white' : highlight ? 'text-amber' : 'text-mint'} />
        <span className={`text-[11px] font-semibold sm:text-xs ${primary ? 'text-white' : 'text-text'}`}>{label}</span>
      </Link>
    </motion.div>
  )
}

function TrustRing({ score, className }: { score: number; className: string }) {
  const radius = 36
  const circumference = 2 * Math.PI * radius

  return (
    <div className="flex shrink-0 flex-col items-center gap-1">
      <div className="relative h-24 w-24">
        <svg className="h-full w-full -rotate-90" viewBox="0 0 96 96" aria-hidden>
          <circle cx="48" cy="48" r={radius} fill="none" stroke="currentColor" strokeWidth="8" className="text-surface-2" />
          <motion.circle
            cx="48"
            cy="48"
            r={radius}
            fill="none"
            stroke="currentColor"
            strokeWidth="8"
            strokeLinecap="round"
            strokeDasharray={circumference}
            initial={{ strokeDashoffset: circumference }}
            animate={{ strokeDashoffset: circumference - (score / 100) * circumference }}
            transition={{ duration: 1, ease: 'easeOut' }}
            className={className}
          />
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <motion.span
            className="font-num text-2xl font-bold leading-none"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 0.3 }}
          >
            {score}
          </motion.span>
          <span className="text-[10px] font-medium uppercase tracking-wide text-muted">Score</span>
        </div>
      </div>
    </div>
  )
}

function TrustStat({ label, value, warn = false }: { label: string; value: number; warn?: boolean }) {
  return (
    <motion.div
      whileHover={{ scale: 1.02 }}
      className="rounded-xl border border-line bg-surface-2/50 px-3 py-2.5 text-center transition hover:border-mint/20"
    >
      <div className="text-[10px] font-medium uppercase tracking-wide text-muted">{label}</div>
      <div className={`mt-0.5 font-num text-xl font-bold ${warn && value > 0 ? 'text-danger' : 'text-text'}`}>{value}</div>
    </motion.div>
  )
}
