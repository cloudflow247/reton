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
  BankIcon,
  BillIcon,
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
import { ngn, shortDate } from '@/lib/format'
import { useCountUp } from '@/lib/useCountUp'
import { useUiStore } from '@/stores/ui-store'
import type { StatementEntry } from '@/lib/types'
import type { DashboardSummary, PageProps, StaticAccount } from '@/types'

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
  const { auth, activity, summary, kycTier, staticAccount } = usePage<
    PageProps<{
      activity: StatementEntry[]
      summary: DashboardSummary
      kycTier: number
      staticAccount: StaticAccount | null
    }>
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
  const todo = nextTodo(needsPin, isNewUser)
  const copyAccount = staticAccount?.account_number ?? wallet?.account_number ?? ''

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
  const score = Number(trust.trust_score ?? 100)

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

      <div className="mx-auto max-w-2xl space-y-4 lg:max-w-none lg:grid lg:grid-cols-12 lg:gap-6 lg:space-y-0">
        <div className="space-y-4 lg:col-span-8">
          <motion.header variants={item} className="flex items-end justify-between gap-3">
            <div className="min-w-0">
              <p className="text-sm text-muted">{greeting()}</p>
              <h1 className="font-display text-2xl font-bold tracking-tight sm:text-[1.65rem]">{firstName}</h1>
              <p className="mt-1">
                <Badge variant="muted" className="text-[10px]">
                  KYC Tier {kycTier ?? 1}
                </Badge>
              </p>
            </div>

            {/* Trust score sits under the brand shield metaphor — compact, always visible */}
            <Link
              href="/protection"
              className="group flex shrink-0 flex-col items-center gap-1 rounded-2xl border border-mint/20 bg-gradient-to-b from-mint/[0.08] to-transparent px-3 py-2.5 transition hover:border-mint/40"
              aria-label={`Trust score ${score}`}
            >
              <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-mint/15 text-mint ring-1 ring-mint/25 transition group-hover:scale-105">
                <ShieldIcon size={18} />
              </span>
              <span className="flex items-baseline gap-0.5">
                <span className={`font-num text-sm font-bold ${tone.ring}`}>{score}</span>
                <span className="text-[10px] font-medium text-muted">trust</span>
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
              depositAccount={staticAccount}
              availableBalance={availableBalance}
              animatedAvailable={animatedAvailable}
              totalBalance={totalBalance}
              pendingBalance={pendingBalance}
              hidden={hidden}
              copied={copied}
              onToggleHidden={toggleHidden}
              onCopyAccount={() => {
                navigator.clipboard.writeText(copyAccount)
                setCopied(true)
                setTimeout(() => setCopied(false), 1500)
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
                  <span className="block truncate text-sm font-semibold text-text">
                    {todo.title}
                    <span className="font-normal text-muted"> — {todo.detail}</span>
                  </span>
                </span>
                <ChevronRightIcon size={16} className="shrink-0 text-mint transition group-hover:translate-x-0.5" />
              </Link>
            </motion.div>
          )}

          <motion.div variants={item}>
            <div className="grid grid-cols-4 gap-2">
              <QuickAction href="/send" label="Send" Icon={SendIcon} primary />
              <QuickAction href="/add-money" label="Add" Icon={PlusIcon} />
              <QuickAction href="/bills" label="Bills" Icon={BillIcon} />
              <QuickAction href="/withdraw" label="Cash out" Icon={BankIcon} />
            </div>
          </motion.div>

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
                      <div className="flex items-center justify-between gap-3 px-4 py-3 transition hover:bg-surface-2/50">
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
                      </div>
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
                <span className="font-num font-semibold text-mint">+{ngn(flow.inflow)}</span>
                <span className="mx-1 text-line">·</span>
                <span className="font-num font-semibold">−{ngn(flow.outflow)}</span>
              </p>
            )}
          </motion.div>
        </div>

        <aside className="hidden space-y-4 lg:col-span-4 lg:block">
          <motion.div variants={item}>
            <div className="overflow-hidden rounded-2xl border border-mint/15 bg-gradient-to-br from-mint/[0.06] to-transparent p-5">
              <div className="flex items-center gap-3">
                <TrustRing score={score} className={tone.ring} />
                <div className="min-w-0">
                  <p className="text-sm font-semibold">Trust shield</p>
                  <Badge variant={tone.badge} className="mt-1">
                    {tone.label}
                  </Badge>
                  <p className="mt-2 text-xs leading-relaxed text-muted">
                    Callbacks, recoveries, and protected transfers keep your score healthy.
                  </p>
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
                className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-line bg-surface px-4 py-2.5 text-sm font-semibold transition hover:border-mint/30 hover:text-mint"
              >
                Protection hub
              </Link>
            </div>
          </motion.div>

          <motion.div variants={item}>
            <Link
              href="/cards"
              className="flex items-center gap-3 rounded-2xl border border-line bg-surface p-4 transition hover:border-mint/30"
            >
              <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-mint/10 text-mint">
                <CardIcon size={18} />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold">Cards</span>
                <span className="block text-xs text-muted">Virtual cards for online spend</span>
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
  href,
  label,
  Icon,
  primary = false,
}: {
  href: string
  label: string
  Icon: (p: { size?: number; className?: string }) => JSX.Element
  primary?: boolean
}) {
  return (
    <motion.div whileHover={{ y: -2 }} whileTap={{ scale: 0.97 }}>
      <Link
        href={href}
        className={`elevate flex min-h-[4.5rem] flex-col items-center justify-center gap-1.5 rounded-2xl border px-2 py-3 text-center transition ${
          primary
            ? 'border-mint/30 bg-mint text-white shadow-[0_12px_28px_-16px_rgba(9,79,57,0.5)] hover:bg-mint-strong'
            : 'border-line bg-surface hover:border-mint/30'
        }`}
      >
        <Icon size={20} className={primary ? 'text-white' : 'text-mint'} />
        <span className={`text-[11px] font-semibold ${primary ? 'text-white' : 'text-text'}`}>{label}</span>
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
