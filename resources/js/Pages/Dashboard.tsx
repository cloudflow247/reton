import type { ReactNode } from 'react'
import { useMemo, useState } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  ArrowRightIcon,
  BoltIcon,
  CheckIcon,
  ChevronRightIcon,
  CopyIcon,
  EyeIcon,
  EyeOffIcon,
  GiftIcon,
  PhoneIcon,
  PlusIcon,
  ReceiveIcon,
  SendIcon,
  ShieldIcon,
  SignalIcon,
  TvIcon,
  WalletIcon,
} from '@/components/icons'
import { TrustProtectionListener } from '@/components/TrustProtectionListener'
import { ngn, shortDate } from '@/lib/format'
import { useCountUp } from '@/lib/useCountUp'
import { useUiStore } from '@/stores/ui-store'
import type { StatementEntry } from '@/lib/types'
import type { DashboardSummary, PageProps } from '@/types'

const list = {
  hidden: {},
  show: { transition: { staggerChildren: 0.05, delayChildren: 0.03 } },
}
const item = {
  hidden: { opacity: 0, y: 10 },
  show: { opacity: 1, y: 0, transition: { type: 'spring', stiffness: 340, damping: 28 } },
}

const billShortcuts = [
  { to: '/bills?category=airtime', label: 'Airtime', Icon: PhoneIcon },
  { to: '/bills?category=data', label: 'Data', Icon: SignalIcon },
  { to: '/bills?category=electricity', label: 'Power', Icon: BoltIcon },
  { to: '/bills?category=cable_tv', label: 'TV', Icon: TvIcon },
] as const

function greeting() {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 17) return 'Good afternoon'
  return 'Good evening'
}

function trustTone(score: number) {
  if (score >= 80) return { ring: 'text-mint', label: 'Strong', badge: 'default' as const }
  if (score >= 60) return { ring: 'text-amber', label: 'Fair', badge: 'warning' as const }
  return { ring: 'text-danger', label: 'At risk', badge: 'danger' as const }
}

export default function Dashboard() {
  const { auth, activity, summary } = usePage<PageProps<{ activity: StatementEntry[]; summary: DashboardSummary }>>().props
  const wallet = auth.wallets[0]
  const [copied, setCopied] = useState(false)
  const hidden = useUiStore((s) => s.balanceHidden)
  const toggleHidden = useUiStore((s) => s.toggleBalanceHidden)
  const totalBalance = wallet?.balance ?? 0
  const availableBalance = wallet?.available_balance ?? 0
  const pendingBalance = wallet?.held_balance ?? 0
  const animatedAvailable = useCountUp(availableBalance)
  const hasPending = pendingBalance > 0
  const recent = (activity ?? []).slice(0, 5)
  const firstName = (auth.user?.name ?? 'there').split(' ')[0]

  const trust = summary ?? {
    pending_callbacks: 0,
    open_recoveries: 0,
    protected_transfers_pending: 0,
    open_fraud_alerts: 0,
    trust_score: 100,
  }

  const attentionCount =
    trust.pending_callbacks + trust.open_recoveries + trust.open_fraud_alerts + trust.protected_transfers_pending

  const tone = trustTone(trust.trust_score)

  const flow = useMemo(() => {
    const entries = activity ?? []
    const inflow = entries.filter((e) => e.direction === 'credit').reduce((s, e) => s + e.amount, 0)
    const outflow = entries.filter((e) => e.direction === 'debit').reduce((s, e) => s + e.amount, 0)
    return { inflow, outflow }
  }, [activity])

  return (
    <motion.div variants={list} initial="hidden" animate="show" className="space-y-4 pb-2">
      <Head title="Dashboard" />
      {auth.user?.id && (
        <TrustProtectionListener userId={auth.user.id} only={['summary', 'activity']} />
      )}

      {/* Header */}
      <motion.header variants={item} className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm text-muted">{greeting()}</p>
          <h1 className="font-display text-2xl font-bold tracking-tight text-text">{firstName}</h1>
        </div>
        <Link
          href="/protection"
          className="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-mint shadow-sm transition hover:border-mint/30"
        >
          <ShieldIcon size={14} />
          Trust {trust.trust_score}
        </Link>
      </motion.header>

      {/* Attention — only when something needs the user */}
      {attentionCount > 0 && (
        <motion.div variants={item}>
          <Link
            href="/protection"
            className="flex items-center gap-3 rounded-2xl border border-amber/30 bg-amber/[0.08] px-4 py-3.5 transition hover:border-amber/45"
          >
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber/15 text-amber">
              <ShieldIcon size={20} />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block text-sm font-semibold text-text">
                {attentionCount} {attentionCount === 1 ? 'item needs' : 'items need'} your attention
              </span>
              <span className="block text-xs text-muted">
                Callbacks, recoveries, or protected transfers waiting on you
              </span>
            </span>
            <ArrowRightIcon size={18} className="shrink-0 text-amber" />
          </Link>
        </motion.div>
      )}

      {/* Balance */}
      <motion.div variants={item}>
        <div className="mesh relative overflow-hidden rounded-[22px] p-5 text-white shadow-[0_24px_48px_-24px_rgba(9,79,57,0.6)] sm:p-6">
          <div aria-hidden className="blob pointer-events-none absolute -right-12 -top-16 h-48 w-48 bg-white/12 blur-2xl" />

          <div className="relative flex items-center justify-between gap-2">
            <span className="text-xs font-medium uppercase tracking-wider text-white/70">Available to spend</span>
            <button
              type="button"
              onClick={toggleHidden}
              className="flex h-8 w-8 items-center justify-center rounded-full text-white/70 transition hover:bg-white/15 hover:text-white"
              aria-label={hidden ? 'Show balance' : 'Hide balance'}
            >
              {hidden ? <EyeOffIcon size={17} /> : <EyeIcon size={17} />}
            </button>
          </div>

          <div
            className={`relative mt-1 font-num text-[2.25rem] font-bold leading-none tracking-tight sm:text-[2.75rem] ${hidden ? 'blur-md select-none' : ''}`}
          >
            {hidden ? '₦ ••••••' : ngn(animatedAvailable)}
          </div>

          {!hidden && wallet && hasPending && (
            <div className="relative mt-4 space-y-2">
              <div className="flex flex-wrap items-center gap-2">
                <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-300/30 bg-amber-400/15 px-3 py-1 text-xs font-semibold text-amber-50">
                  <span className="h-1.5 w-1.5 rounded-full bg-amber-200" aria-hidden />
                  {ngn(pendingBalance)} pending
                </span>
                <span className="text-xs text-white/55">
                  Total in wallet {ngn(totalBalance)}
                </span>
              </div>
              <p className="text-[11px] leading-relaxed text-white/55">
                Pending is from protected sales or holds — not spendable until the buyer confirms or a dispute ends.
              </p>
            </div>
          )}

          {wallet && (
            <button
              type="button"
              onClick={() => {
                navigator.clipboard.writeText(wallet.account_number ?? '')
                setCopied(true)
                setTimeout(() => setCopied(false), 1500)
              }}
              className="relative mt-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs text-white/90 backdrop-blur transition hover:bg-white/20"
            >
              <span className="text-white/60">Acct</span>
              <span className="font-num tracking-wide">{wallet.account_number}</span>
              {copied ? <CheckIcon size={14} /> : <CopyIcon size={14} />}
            </button>
          )}
        </div>
      </motion.div>

      {/* Primary actions — what most people open the app to do */}
      <motion.div variants={item} className="grid grid-cols-2 gap-2.5 sm:grid-cols-4 sm:gap-3">
        <QuickAction href="/send" label="Send" Icon={SendIcon} primary />
        <QuickAction href="/add-money" label="Add money" Icon={PlusIcon} />
        <QuickAction href="/marketplace" label="Digital shop" Icon={GiftIcon} />
        <QuickAction href="/protection" label="Protection" Icon={ShieldIcon} highlight={attentionCount > 0} />
      </motion.div>

      {/* Trust snapshot */}
      <motion.div variants={item}>
        <Card className="border-mint/15">
          <CardHeader className="pb-2">
            <div className="flex items-start justify-between gap-3">
              <div>
                <CardTitle className="text-base">Your trust shield</CardTitle>
                <CardDescription>Callback protection &amp; recovery at a glance</CardDescription>
              </div>
              <Badge variant={tone.badge}>{tone.label}</Badge>
            </div>
          </CardHeader>
          <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center">
            <TrustRing score={trust.trust_score} className={tone.ring} />
            <div className="grid flex-1 grid-cols-2 gap-2 sm:grid-cols-4">
              <TrustStat label="Protected" value={trust.protected_transfers_pending} />
              <TrustStat label="Callbacks" value={trust.pending_callbacks} />
              <TrustStat label="Recoveries" value={trust.open_recoveries} />
              <TrustStat label="Alerts" value={trust.open_fraud_alerts} warn={trust.open_fraud_alerts > 0} />
            </div>
          </CardContent>
          <div className="border-t border-line px-5 py-3">
            <Link
              href="/protection"
              className="inline-flex items-center gap-1 text-sm font-semibold text-mint hover:underline"
            >
              Open protection hub <ChevronRightIcon size={16} />
            </Link>
          </div>
        </Card>
      </motion.div>

      {/* Bills — secondary, compact */}
      <motion.div variants={item}>
        <div className="flex items-center justify-between px-0.5">
          <h2 className="text-sm font-semibold text-text">Pay bills</h2>
          <Link href="/bills" className="text-xs font-medium text-mint hover:underline">
            All bills
          </Link>
        </div>
        <div className="mt-2 grid grid-cols-4 gap-2">
          {billShortcuts.map(({ to, label, Icon }) => (
            <Link
              key={to}
              href={to}
              className="elevate flex flex-col items-center gap-1.5 rounded-2xl border border-line bg-surface px-2 py-3 transition hover:border-mint/25"
            >
              <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-mint/10 text-mint">
                <Icon size={18} />
              </span>
              <span className="text-[11px] font-semibold text-text">{label}</span>
            </Link>
          ))}
        </div>
      </motion.div>

      {/* Recent activity */}
      <motion.div variants={item}>
        <div className="flex items-center justify-between px-0.5">
          <h2 className="text-sm font-semibold text-text">Recent activity</h2>
          <Link href="/activity" className="text-xs font-medium text-mint hover:underline">
            See all
          </Link>
        </div>

        <div className="card mt-2 overflow-hidden p-0">
          {recent.length > 0 ? (
            <ul className="divide-y divide-line">
              {recent.map((e) => (
                <li key={e.id}>
                  <div className="flex items-center justify-between gap-3 px-4 py-3.5">
                    <div className="flex min-w-0 items-center gap-3">
                      <span
                        className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${
                          e.direction === 'credit' ? 'bg-mint/10 text-mint' : 'bg-surface-2 text-muted'
                        }`}
                      >
                        {e.direction === 'credit' ? <ReceiveIcon size={16} /> : <SendIcon size={16} />}
                      </span>
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                          {e.transaction?.description ?? e.transaction?.type ?? 'Movement'}
                        </p>
                        <p className="text-xs text-muted">{shortDate(e.created_at)}</p>
                      </div>
                    </div>
                    <span className={`shrink-0 font-num text-sm font-semibold ${e.direction === 'credit' ? 'text-mint' : 'text-text'}`}>
                      {e.direction === 'credit' ? '+' : '−'}
                      {ngn(e.amount)}
                    </span>
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <div className="flex flex-col items-center gap-3 px-4 py-10 text-center">
              <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-mint/10 text-mint">
                <WalletIcon size={22} />
              </span>
              <p className="text-sm text-muted">No transactions yet</p>
              <Link
                href="/add-money"
                className="btn inline-flex items-center gap-1.5 bg-mint px-4 py-2 text-sm text-white hover:bg-mint-strong"
              >
                <PlusIcon size={15} /> Add money
              </Link>
            </div>
          )}
        </div>

        {(flow.inflow > 0 || flow.outflow > 0) && (
          <p className="mt-2 px-0.5 text-xs text-muted">
            This period: <span className="font-num font-medium text-mint">+{ngn(flow.inflow)}</span>
            {' · '}
            <span className="font-num font-medium text-text">−{ngn(flow.outflow)}</span>
          </p>
        )}
      </motion.div>
    </motion.div>
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
    <Link
      href={href}
      className={`elevate relative flex min-h-[4.5rem] flex-col items-center justify-center gap-2 rounded-2xl border px-3 py-3.5 text-center transition ${
        primary
          ? 'border-mint/30 bg-mint text-white hover:bg-mint-strong'
          : highlight
            ? 'border-amber/35 bg-amber/[0.06] hover:border-amber/50'
            : 'border-line bg-surface hover:border-mint/25'
      }`}
    >
      {highlight && !primary && (
        <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-amber ring-2 ring-surface" />
      )}
      <Icon size={22} className={primary ? 'text-white' : highlight ? 'text-amber' : 'text-mint'} />
      <span className={`text-xs font-semibold sm:text-sm ${primary ? 'text-white' : 'text-text'}`}>{label}</span>
    </Link>
  )
}

function TrustRing({ score, className }: { score: number; className: string }) {
  const radius = 36
  const circumference = 2 * Math.PI * radius
  const offset = circumference - (score / 100) * circumference

  return (
    <div className="flex shrink-0 flex-col items-center gap-1">
      <div className="relative h-24 w-24">
        <svg className="h-full w-full -rotate-90" viewBox="0 0 96 96" aria-hidden>
          <circle cx="48" cy="48" r={radius} fill="none" stroke="currentColor" strokeWidth="8" className="text-surface-2" />
          <circle
            cx="48"
            cy="48"
            r={radius}
            fill="none"
            stroke="currentColor"
            strokeWidth="8"
            strokeLinecap="round"
            strokeDasharray={circumference}
            strokeDashoffset={offset}
            className={className}
          />
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="font-num text-2xl font-bold leading-none">{score}</span>
          <span className="text-[10px] font-medium uppercase tracking-wide text-muted">Score</span>
        </div>
      </div>
    </div>
  )
}

function TrustStat({ label, value, warn = false }: { label: string; value: number; warn?: boolean }) {
  return (
    <div className="rounded-xl border border-line bg-surface-2/50 px-3 py-2.5 text-center">
      <div className="text-[10px] font-medium uppercase tracking-wide text-muted">{label}</div>
      <div className={`mt-0.5 font-num text-xl font-bold ${warn && value > 0 ? 'text-danger' : 'text-text'}`}>
        {value}
      </div>
    </div>
  )
}
