import type { ReactNode } from 'react'
import { useMemo, useState } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { BalanceHeroCard } from '@/components/dashboard/DashboardKit'
import { pageItem, Page } from '@/components/page-kit'
import { Badge } from '@/components/ui/badge'
import {
  ArrowRightIcon,
  CardIcon,
  ChevronRightIcon,
  LockIcon,
  PlusIcon,
  ReceiveIcon,
  SendIcon,
  ShieldIcon,
  WalletIcon,
} from '@/components/icons'
import { TrustProtectionListener } from '@/components/TrustProtectionListener'
import {
  DASHBOARD_MORE_SHORTCUTS,
  DASHBOARD_SHORTCUTS,
  isServiceSoon,
  type AppService,
} from '@/lib/app-services'
import { ngn, shortDate } from '@/lib/format'
import { useCountUp } from '@/lib/useCountUp'
import { useUiStore } from '@/stores/ui-store'
import type { StatementEntry } from '@/lib/types'
import type { DashboardSummary, PageProps } from '@/types'

const item = pageItem

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

function nextTodo(needsPin: boolean, isNewUser: boolean) {
  if (needsPin) {
    return {
      title: 'Set your PIN',
      detail: 'Secure sends and withdrawals',
      href: '/pin',
      Icon: LockIcon,
    }
  }
  if (isNewUser) {
    return {
      title: 'Add money',
      detail: 'Fund your wallet to get started',
      href: '/add-money',
      Icon: PlusIcon,
    }
  }
  return null
}

export default function Dashboard() {
  const { auth, activity, activityFlow, summary, features, depositAccount } = usePage<
    PageProps<{
      activity: StatementEntry[]
      activityFlow?: { inflow: number; outflow: number; net: number; count: number }
      summary: DashboardSummary
      kycTier: number
      depositAccount?: { account_number: string; account_name: string | null; bank_name: string | null } | null
    }>
  >().props
  const wallets = Array.isArray(auth?.wallets) ? auth.wallets : []
  const wallet = wallets[0]
  const [copied, setCopied] = useState(false)
  const [depositCopied, setDepositCopied] = useState(false)
  const hidden = useUiStore((s) => s.balanceHidden)
  const toggleHidden = useUiStore((s) => s.toggleBalanceHidden)
  const totalBalance = wallet?.balance ?? 0
  const availableBalance = wallet?.available_balance ?? 0
  const pendingBalance = wallet?.held_balance ?? 0
  const animatedAvailable = useCountUp(availableBalance)
  const recent = Array.isArray(activity) ? activity : []
  const firstName = (auth?.user?.name ?? 'there').split(' ')[0]
  const needsPin = !auth?.user?.has_transaction_pin
  const isNewUser = recent.length === 0
  const todo = nextTodo(needsPin, isNewUser)
  const copyAccount = wallet?.account_number ?? ''
  const cardsSoon = features?.cards === false

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
    (trust.open_fraud_alerts ?? 0)

  const tone = trustTone(Number(trust.trust_score ?? 100))
  const score = Number(trust.trust_score ?? 100)

  const flow = useMemo(() => {
    if (activityFlow) {
      return { inflow: activityFlow.inflow, outflow: activityFlow.outflow }
    }
    const entries = recent
    const inflow = entries.filter((e) => e.direction === 'credit').reduce((s, e) => s + e.amount, 0)
    const outflow = entries.filter((e) => e.direction === 'debit').reduce((s, e) => s + e.amount, 0)
    return { inflow, outflow }
  }, [activityFlow, recent])

  return (
    <Page className="!pb-4 sm:!pb-6">
      <Head title="Home" />
      {auth?.user?.id && <TrustProtectionListener userId={auth.user.id} only={['summary', 'activity']} />}

      <div className="mx-auto max-w-2xl space-y-4 lg:max-w-none lg:grid lg:grid-cols-12 lg:items-start lg:gap-6 lg:space-y-0">
        <div className="space-y-4 lg:col-span-8">
          <motion.header variants={item} className="flex items-start justify-between gap-2 px-0.5 sm:items-end sm:gap-3">
            <div className="min-w-0 flex-1">
              <p className="text-sm text-muted">{greeting()}</p>
              <h1 className="font-display text-xl font-bold tracking-tight text-text sm:text-2xl">{firstName}</h1>
            </div>

            <Link
              href="/protection"
              className="group flex shrink-0 items-center gap-2 rounded-2xl border border-mint/25 bg-mint/[0.06] px-2 py-1.5 transition hover:border-mint/40 hover:bg-mint/[0.1] sm:gap-2.5 sm:px-2.5 sm:py-2"
              aria-label={`Trust score ${score} - open protection`}
              title="Trust & protection"
            >
              <span className="relative flex h-9 w-9 items-center justify-center rounded-xl bg-mint text-white shadow-sm ring-1 ring-mint/30 transition group-hover:scale-[1.03] sm:h-10 sm:w-10">
                <ShieldIcon size={18} />
                {attentionCount > 0 && (
                  <span className="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber px-1 text-[9px] font-bold text-white ring-2 ring-bg">
                    {attentionCount > 9 ? '9+' : attentionCount}
                  </span>
                )}
              </span>
              <span className={`font-num text-sm font-bold sm:hidden ${tone.ring}`}>{score}</span>
              <span className="hidden pr-0.5 text-right sm:block">
                <span className="block text-[10px] font-semibold uppercase tracking-wide text-muted">Trust</span>
                <span className={`font-num text-sm font-bold ${tone.ring}`}>
                  {score}
                  <span className="ml-1 text-[10px] font-semibold text-muted">{tone.label}</span>
                </span>
              </span>
            </Link>
          </motion.header>

          {attentionCount > 0 && (
            <motion.div variants={item}>
              <Link
                href="/protection"
                className="group flex items-center gap-3 rounded-2xl border border-amber/30 bg-amber/[0.07] px-3.5 py-3 transition hover:border-amber/45"
              >
                <ShieldIcon size={18} className="shrink-0 text-amber" />
                <span className="min-w-0 flex-1 text-sm font-semibold">
                  {attentionCount} {attentionCount === 1 ? 'item needs' : 'items need'} attention
                </span>
                <ArrowRightIcon size={16} className="shrink-0 text-amber transition group-hover:translate-x-0.5" />
              </Link>
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
              depositAccount={depositAccount}
              depositCopied={depositCopied}
              onToggleHidden={toggleHidden}
              onCopyAccount={() => {
                navigator.clipboard.writeText(copyAccount)
                setCopied(true)
                setTimeout(() => setCopied(false), 1500)
              }}
              onCopyDeposit={() => {
                if (!depositAccount?.account_number) return
                navigator.clipboard.writeText(depositAccount.account_number)
                setDepositCopied(true)
                setTimeout(() => setDepositCopied(false), 1500)
              }}
            />
          </motion.div>

          {todo && (
            <motion.div variants={item}>
              <Link
                href={todo.href}
                className="group flex items-center gap-3 rounded-2xl border border-mint/25 bg-mint/[0.05] px-3.5 py-3 transition hover:border-mint/40 hover:bg-mint/[0.08]"
              >
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-mint/15 text-mint">
                  <todo.Icon size={16} />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block text-[10px] font-semibold uppercase tracking-wide text-mint">To do</span>
                  <span className="block text-sm font-semibold leading-snug text-text">
                    {todo.title}
                    <span className="font-normal text-muted"> - {todo.detail}</span>
                  </span>
                </span>
                <ChevronRightIcon size={16} className="shrink-0 text-mint transition group-hover:translate-x-0.5" />
              </Link>
            </motion.div>
          )}

          <motion.section variants={item} aria-label="Services" className="space-y-2">
            <div className="flex items-end justify-between px-0.5">
              <div>
                <h2 className="text-sm font-bold text-text">Services</h2>
                <p className="text-[11px] font-medium text-text/65">Security first · tap to move money</p>
              </div>
            </div>
            <div className="grid grid-cols-4 gap-1.5 sm:gap-2">
              {DASHBOARD_SHORTCUTS.map((service, index) => (
                <QuickAction
                  key={service.to}
                  service={service}
                  primary={index === 0}
                  soon={isServiceSoon(service, features)}
                  shortLabel={
                    service.to === '/add-money' ? 'Add' : service.to === '/withdraw' ? 'Cash' : undefined
                  }
                />
              ))}
            </div>
            <div className="grid grid-cols-4 gap-1.5 sm:gap-2">
              {DASHBOARD_MORE_SHORTCUTS.map((service) => (
                <QuickAction
                  key={service.to}
                  service={service}
                  soon={isServiceSoon(service, features)}
                  shortLabel={
                    service.to === '/marketplace'
                      ? 'Shop'
                      : service.to === '/protection'
                        ? 'Protect'
                        : service.to === '/activity'
                          ? 'Activity'
                          : undefined
                  }
                />
              ))}
            </div>
          </motion.section>

          <motion.div variants={item}>
            <div className="flex items-center justify-between px-0.5">
              <h2 className="text-sm font-semibold">Recent</h2>
              <Link href="/activity" className="text-xs font-medium text-mint hover:underline">
                See all
              </Link>
            </div>

            <div className="card mt-2 overflow-hidden p-0">
              {recent.length > 0 ? (
                <ul className="divide-y divide-line">
                  {recent.map((e, i) => (
                    <motion.li
                      key={e.id}
                      initial={{ opacity: 0, x: -6 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ delay: i * 0.03 }}
                    >
                      <Link
                        href={`/activity/${e.id}`}
                        className="flex items-center justify-between gap-3 px-4 py-3 transition hover:bg-surface-2/50"
                      >
                        <div className="flex min-w-0 items-center gap-3">
                          <span
                            className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${
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
                        <span
                          className={`shrink-0 font-num text-sm font-bold ${e.direction === 'credit' ? 'text-mint' : 'text-text'}`}
                        >
                          {e.direction === 'credit' ? '+' : '−'}
                          {ngn(e.amount)}
                        </span>
                      </Link>
                    </motion.li>
                  ))}
                </ul>
              ) : (
                <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
                  <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-mint/10 text-mint">
                    <WalletIcon size={22} />
                  </span>
                  <p className="text-sm font-medium">No activity yet</p>
                  <Link href="/add-money" className="text-xs font-semibold text-mint hover:underline">
                    Add money to get started
                  </Link>
                </div>
              )}
            </div>

            {(flow.inflow > 0 || flow.outflow > 0) && (
              <p className="mt-2 px-0.5 text-xs text-muted">
                <span className="text-[10px] uppercase tracking-wide">Shown · </span>
                <span className="font-num font-semibold text-mint">+{ngn(flow.inflow)}</span>
                <span className="mx-1 text-line">·</span>
                <span className="font-num font-semibold">−{ngn(flow.outflow)}</span>
              </p>
            )}
          </motion.div>
        </div>

        <aside className="hidden space-y-4 lg:col-span-4 lg:block">
          <motion.div variants={item}>
            <div className="overflow-hidden rounded-2xl border border-mint/15 bg-gradient-to-br from-mint/[0.06] to-transparent p-4 sm:p-5">
              <div className="flex items-center gap-3">
                <TrustRing score={score} className={tone.ring} />
                <div className="min-w-0">
                  <p className="text-sm font-semibold">Trust shield</p>
                  <Badge variant={tone.badge} className="mt-1">
                    {tone.label}
                  </Badge>
                  <p className="mt-2 hidden text-xs leading-relaxed text-muted sm:block">
                    Callbacks, recoveries, and protected transfers keep your score healthy.
                  </p>
                  <p className="mt-1 text-xs text-muted sm:hidden">Protected transfers keep you safe.</p>
                </div>
              </div>
              <div className="mt-4 grid grid-cols-2 gap-2">
                <TrustStat label="Protected" value={trust.protected_transfers_pending} />
                <TrustStat label="Callbacks" value={trust.pending_callbacks} />
                <TrustStat label="Recoveries" value={trust.open_recoveries} />
                <TrustStat label="Alerts" value={trust.open_fraud_alerts} warn={trust.open_fraud_alerts > 0} />
              </div>
              <Link
                href="/protection"
                prefetch
                className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-line bg-surface px-4 py-2.5 text-sm font-semibold transition hover:border-mint/30 hover:text-mint"
              >
                Protection hub
              </Link>
            </div>
          </motion.div>

          <motion.div variants={item}>
            <Link
              href="/cards"
              prefetch
              className="flex items-center gap-3 rounded-2xl border border-line bg-surface p-4 transition hover:border-mint/30"
            >
              <span className="relative flex h-10 w-10 items-center justify-center rounded-xl bg-mint/10 text-mint">
                <CardIcon size={18} />
                {cardsSoon && (
                  <span className="absolute -right-1 -top-1 rounded-full bg-amber px-1 py-0.5 text-[8px] font-bold uppercase tracking-wide text-white">
                    Soon
                  </span>
                )}
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold">Cards</span>
                <span className="block text-xs text-muted">
                  {cardsSoon ? 'Virtual cards coming soon' : 'Virtual cards for online spend'}
                </span>
              </span>
              <ChevronRightIcon size={16} className="text-muted" />
            </Link>
          </motion.div>
        </aside>
      </div>
    </Page>
  )
}

Dashboard.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function QuickAction({
  service,
  primary = false,
  soon = false,
  shortLabel,
}: {
  service: AppService
  primary?: boolean
  soon?: boolean
  shortLabel?: string
}) {
  const { to, label, Icon, hint } = service
  const display = shortLabel ?? label

  return (
    <motion.div whileHover={{ y: -2 }} whileTap={{ scale: 0.97 }}>
      <Link
        href={to}
        prefetch
        title={soon ? `${label} - coming soon` : hint}
        className={`elevate relative flex min-h-[4.25rem] flex-col items-center justify-center gap-1 rounded-2xl border px-1 py-2.5 text-center transition sm:min-h-[4.5rem] sm:gap-1.5 sm:px-2 sm:py-3 ${
          primary
            ? 'border-mint/30 bg-mint text-white shadow-[0_12px_28px_-16px_rgba(9,79,57,0.5)] hover:bg-mint-strong'
            : soon
              ? 'border-line/80 bg-surface text-muted opacity-90 hover:border-line'
              : 'border-line bg-surface hover:border-mint/30'
        }`}
      >
        {soon && (
          <span className="absolute right-0.5 top-0.5 rounded-md bg-amber/15 px-1 py-0.5 text-[7px] font-bold uppercase tracking-wide text-amber sm:right-1 sm:top-1 sm:text-[8px]">
            Soon
          </span>
        )}
        <Icon size={18} className={primary ? 'text-white' : soon ? 'text-muted' : 'text-mint'} />
        <span
          className={`max-w-full text-[10px] font-semibold leading-tight sm:text-[11px] ${primary ? 'text-white' : soon ? 'text-muted' : 'text-text'}`}
        >
          {display}
        </span>
      </Link>
    </motion.div>
  )
}

function TrustRing({ score, className }: { score: number; className: string }) {
  const radius = 32
  const circumference = 2 * Math.PI * radius

  return (
    <div className="relative h-20 w-20 shrink-0">
      <svg className="h-full w-full -rotate-90" viewBox="0 0 84 84" aria-hidden>
        <circle cx="42" cy="42" r={radius} fill="none" stroke="currentColor" strokeWidth="7" className="text-surface-2" />
        <motion.circle
          cx="42"
          cy="42"
          r={radius}
          fill="none"
          stroke="currentColor"
          strokeWidth="7"
          strokeLinecap="round"
          strokeDasharray={circumference}
          initial={{ strokeDashoffset: circumference }}
          animate={{ strokeDashoffset: circumference - (score / 100) * circumference }}
          transition={{ duration: 0.9, ease: 'easeOut' }}
          className={className}
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <span className="font-num text-xl font-bold leading-none">{score}</span>
        <span className="text-[9px] font-medium uppercase tracking-wide text-muted">Score</span>
      </div>
    </div>
  )
}

function TrustStat({ label, value, warn = false }: { label: string; value: number; warn?: boolean }) {
  return (
    <div className="rounded-xl border border-line bg-surface/80 px-2.5 py-2 text-center">
      <div className="text-[10px] font-medium uppercase tracking-wide text-muted">{label}</div>
      <div className={`mt-0.5 font-num text-lg font-bold ${warn && value > 0 ? 'text-danger' : 'text-text'}`}>{value}</div>
    </div>
  )
}
