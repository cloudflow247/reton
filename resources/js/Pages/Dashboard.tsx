import type { ReactNode } from 'react'
import { useMemo, useState } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import {
  ArrowRightIcon,
  BoltIcon,
  CardIcon,
  CheckIcon,
  ChevronRightIcon,
  CopyIcon,
  EyeIcon,
  EyeOffIcon,
  GiftIcon,
  PhoneIcon,
  PlusIcon,
  QrIcon,
  ReceiveIcon,
  SendIcon,
  ShieldIcon,
  SignalIcon,
  SparkleIcon,
  TrendIcon,
  TvIcon,
  WalletIcon,
} from '@/components/icons'
import { ngn, shortDate } from '@/lib/format'
import { useCountUp } from '@/lib/useCountUp'
import type { StatementEntry } from '@/lib/types'
import type { PageProps } from '@/types'

const list = {
  hidden: {},
  show: { transition: { staggerChildren: 0.06, delayChildren: 0.05 } },
}
const item = {
  hidden: { opacity: 0, y: 12 },
  show: { opacity: 1, y: 0, transition: { type: 'spring', stiffness: 320, damping: 26 } },
}

const services = [
  { to: '/send', label: 'Send', Icon: SendIcon, tone: 'mint' },
  { to: '/add-money', label: 'Add money', Icon: PlusIcon, tone: 'mint' },
  { to: '/cards', label: 'Cards', Icon: CardIcon, tone: 'violet' },
  { to: '/receive', label: 'Receive', Icon: ReceiveIcon, tone: 'mint' },
  { to: '/bills?category=airtime', label: 'Airtime', Icon: PhoneIcon, tone: 'sky' },
  { to: '/bills?category=data', label: 'Data', Icon: SignalIcon, tone: 'sky' },
  { to: '/bills?category=electricity', label: 'Electricity', Icon: BoltIcon, tone: 'amber' },
  { to: '/bills?category=cable_tv', label: 'TV', Icon: TvIcon, tone: 'rose' },
] as const

const toneClass: Record<string, string> = {
  mint: 'bg-mint/10 text-mint',
  violet: 'bg-[#6d4aff]/10 text-[#6d4aff]',
  sky: 'bg-[#1f8fff]/10 text-[#1f8fff]',
  amber: 'bg-amber/12 text-amber',
  rose: 'bg-[#e0457b]/10 text-[#e0457b]',
}

function greeting() {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 17) return 'Good afternoon'
  return 'Good evening'
}

export default function Dashboard() {
  const { auth, activity } = usePage<PageProps<{ activity: StatementEntry[] }>>().props
  const wallet = auth.wallets[0]
  const [copied, setCopied] = useState(false)
  const [hidden, setHidden] = useState(false)
  const balance = useCountUp(wallet?.available_balance ?? 0)
  const recent = (activity ?? []).slice(0, 6)

  // Lightweight spending insight from the statement — inflow vs outflow.
  const flow = useMemo(() => {
    const entries = activity ?? []
    const inflow = entries.filter((e) => e.direction === 'credit').reduce((s, e) => s + e.amount, 0)
    const outflow = entries.filter((e) => e.direction === 'debit').reduce((s, e) => s + e.amount, 0)
    const total = Math.max(inflow + outflow, 1)
    return { inflow, outflow, inPct: (inflow / total) * 100, outPct: (outflow / total) * 100 }
  }, [activity])

  return (
    <motion.div variants={list} initial="hidden" animate="show" className="space-y-5">
      <Head title="Dashboard" />

      {/* Greeting */}
      <motion.div variants={item} className="flex items-end justify-between gap-4">
        <div>
          <p className="text-sm text-muted">{greeting()},</p>
          <h1 className="font-display text-2xl font-bold leading-tight tracking-tight">
            {(auth.user?.name ?? '—').split(' ')[0]} 👋
          </h1>
        </div>
        <Link
          href="/receive"
          className="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3.5 py-2 text-xs font-semibold text-text shadow-sm transition hover:border-mint/40 hover:text-mint"
        >
          <QrIcon size={15} /> My code
        </Link>
      </motion.div>

      {/* Hero: the balance, on a living emerald mesh card. */}
      <motion.div variants={item}>
        <div className="mesh sheen relative overflow-hidden rounded-[24px] p-6 text-white shadow-[0_28px_60px_-28px_rgba(9,79,57,0.65)]">
          {/* Morphing light */}
          <div
            aria-hidden
            className="blob pointer-events-none absolute -right-16 -top-20 h-64 w-64 bg-white/15 blur-2xl"
          />
          <div
            aria-hidden
            className="blob-slow pointer-events-none absolute -bottom-24 -left-10 h-56 w-56 bg-[#34e0a8]/25 blur-2xl"
          />

          <div className="relative flex items-center justify-between">
            <span className="inline-flex items-center gap-2 text-xs font-medium uppercase tracking-[0.14em] text-white/75">
              <WalletIcon size={15} /> Available balance
            </span>
            <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold text-white backdrop-blur">
              <ShieldIcon size={13} /> Protected
            </span>
          </div>

          <div className="relative mt-3 flex items-center gap-3">
            <div
              className={`reveal-blur font-num text-[2.6rem] font-bold leading-none text-white ${
                hidden ? 'blur-md select-none' : ''
              }`}
            >
              {hidden ? '₦ 0,000,000' : ngn(balance)}
            </div>
            <button
              onClick={() => setHidden((v) => !v)}
              className="mb-1 flex h-8 w-8 items-center justify-center rounded-full text-white/70 transition hover:bg-white/15 hover:text-white"
              aria-label={hidden ? 'Show balance' : 'Hide balance'}
            >
              <AnimatePresence mode="wait" initial={false}>
                <motion.span
                  key={hidden ? 'off' : 'on'}
                  initial={{ opacity: 0, scale: 0.6, rotate: -20 }}
                  animate={{ opacity: 1, scale: 1, rotate: 0 }}
                  exit={{ opacity: 0, scale: 0.6, rotate: 20 }}
                  transition={{ duration: 0.18 }}
                >
                  {hidden ? <EyeOffIcon size={18} /> : <EyeIcon size={18} />}
                </motion.span>
              </AnimatePresence>
            </button>
          </div>

          <div className="relative mt-2 flex flex-wrap items-center gap-2 text-sm text-white/75">
            <span>Total {wallet && !hidden ? ngn(wallet.balance) : '••••'}</span>
            {!!wallet?.held_balance && !hidden && (
              <span className="rounded-full bg-white/15 px-2 py-0.5 text-xs text-white">
                {ngn(wallet.held_balance)} held in escrow
              </span>
            )}
          </div>

          {/* Account chip + inline actions */}
          <div className="relative mt-5 flex flex-wrap items-center gap-2.5">
            {wallet && (
              <button
                onClick={() => {
                  navigator.clipboard.writeText(wallet.account_number ?? '')
                  setCopied(true)
                  setTimeout(() => setCopied(false), 1500)
                }}
                className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs text-white/85 backdrop-blur transition hover:bg-white/20"
                title="Copy your account number"
              >
                <span className="text-white/60">Acct</span>
                <span className="font-num tracking-wider text-white">{wallet.account_number}</span>
                <AnimatePresence mode="wait" initial={false}>
                  <motion.span
                    key={copied ? 'done' : 'copy'}
                    initial={{ opacity: 0, scale: 0.6 }}
                    animate={{ opacity: 1, scale: 1 }}
                    exit={{ opacity: 0, scale: 0.6 }}
                    transition={{ duration: 0.15 }}
                  >
                    {copied ? <CheckIcon size={14} /> : <CopyIcon size={14} />}
                  </motion.span>
                </AnimatePresence>
              </button>
            )}
            <div className="ml-auto flex items-center gap-2">
              <Link
                href="/add-money"
                className="btn inline-flex items-center gap-1.5 bg-white px-4 py-2 text-sm text-mint-strong shadow-sm hover:bg-white/90"
              >
                <PlusIcon size={16} /> Add money
              </Link>
              <Link
                href="/send"
                className="btn inline-flex items-center gap-1.5 border border-white/25 bg-white/10 px-4 py-2 text-sm text-white backdrop-blur hover:bg-white/20"
              >
                <SendIcon size={16} /> Send
              </Link>
            </div>
          </div>
        </div>
      </motion.div>

      {/* Service grid */}
      <motion.div variants={item} className="grid grid-cols-4 gap-2.5 sm:gap-3">
        {services.map((s) => (
          <Service key={s.label} {...s} />
        ))}
      </motion.div>

      {/* Rewards / promo strip */}
      <motion.div variants={item}>
        <Link
          href="/protection"
          className="elevate group relative flex items-center gap-4 overflow-hidden rounded-2xl border border-mint/20 bg-gradient-to-r from-mint/[0.09] via-surface to-surface p-4"
        >
          <div
            aria-hidden
            className="blob pointer-events-none absolute -right-8 -top-10 h-32 w-32 bg-mint/15 blur-2xl"
          />
          <span className="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-mint/12 text-mint">
            <GiftIcon size={24} />
          </span>
          <div className="relative min-w-0 flex-1">
            <div className="flex items-center gap-1.5">
              <span className="font-display text-sm font-bold tracking-tight">Every transfer is reversible</span>
              <SparkleIcon size={14} className="text-mint" />
            </div>
            <p className="truncate text-xs text-muted">
              Send protected — recall or recover money if something goes wrong.
            </p>
          </div>
          <ArrowRightIcon
            size={18}
            className="relative shrink-0 text-mint transition-transform group-hover:translate-x-1"
          />
        </Link>
      </motion.div>

      {/* Insights */}
      <motion.div variants={item} className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div className="card p-5">
          <div className="flex items-center justify-between">
            <span className="inline-flex items-center gap-2 text-sm font-semibold">
              <TrendIcon size={16} className="text-mint" /> Money flow
            </span>
            <Link href="/activity" className="text-xs font-medium text-mint hover:underline">
              Details
            </Link>
          </div>
          <div className="mt-4 space-y-3">
            <FlowBar label="In" value={ngn(flow.inflow)} pct={flow.inPct} tone="mint" />
            <FlowBar label="Out" value={ngn(flow.outflow)} pct={flow.outPct} tone="muted" />
          </div>
        </div>

        <div className="card relative flex items-center gap-3 overflow-hidden p-5">
          <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber/12 text-amber">
            <ShieldIcon size={20} />
          </span>
          <div className="min-w-0">
            <div className="text-xs text-muted">Held in escrow</div>
            <div className="truncate font-num text-xl font-bold">{wallet ? ngn(wallet.held_balance) : '—'}</div>
            <div className="truncate text-[11px] text-muted">Protected &amp; recoverable</div>
          </div>
        </div>
      </motion.div>

      {/* Recent activity */}
      <motion.div variants={item} className="flex items-center justify-between pt-1">
        <h2 className="font-display text-lg font-semibold">Recent activity</h2>
        <Link href="/activity" className="inline-flex items-center gap-1 text-sm text-mint hover:underline">
          See all <ChevronRightIcon size={15} />
        </Link>
      </motion.div>

      <motion.div variants={item}>
        <div className="card p-0">
          <motion.div variants={list} initial="hidden" animate="show" className="divide-y divide-line">
            {recent.map((e) => (
              <motion.div
                variants={item}
                key={e.id}
                whileHover={{ x: 3 }}
                transition={{ type: 'spring', stiffness: 400, damping: 28 }}
                className="flex items-center justify-between px-5 py-3.5"
              >
                <div className="flex min-w-0 items-center gap-3">
                  <span
                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full ${
                      e.direction === 'credit' ? 'bg-mint/10 text-mint' : 'bg-surface-2 text-muted'
                    }`}
                  >
                    {e.direction === 'credit' ? <ReceiveIcon size={16} /> : <SendIcon size={16} />}
                  </span>
                  <div className="min-w-0">
                    <div className="truncate text-sm font-medium">
                      {e.transaction?.description ?? e.transaction?.type ?? 'Movement'}
                    </div>
                    <div className="text-xs text-muted">{shortDate(e.created_at)}</div>
                  </div>
                </div>
                <div className={`font-num text-sm ${e.direction === 'credit' ? 'text-mint' : 'text-text'}`}>
                  {e.direction === 'credit' ? '+' : '−'}
                  {ngn(e.amount)}
                </div>
              </motion.div>
            ))}
            {recent.length === 0 && (
              <div className="flex flex-col items-center gap-3 px-5 py-12 text-center">
                <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-mint/10 text-mint">
                  <WalletIcon size={24} />
                </span>
                <p className="text-sm text-muted">No movements yet.</p>
                <Link
                  href="/add-money"
                  className="btn inline-flex items-center gap-1.5 bg-mint px-4 py-2 text-sm text-white hover:bg-mint-strong"
                >
                  <PlusIcon size={15} /> Add money
                </Link>
              </div>
            )}
          </motion.div>
        </div>
      </motion.div>
    </motion.div>
  )
}

Dashboard.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function Service({
  to,
  label,
  Icon,
  tone,
}: {
  to: string
  label: string
  Icon: (p: { size?: number }) => JSX.Element
  tone: string
}) {
  return (
    <motion.div whileHover={{ y: -3 }} whileTap={{ scale: 0.95 }} transition={{ type: 'spring', stiffness: 400, damping: 22 }}>
      <Link href={to} className="tile flex flex-col items-center gap-2 px-2 py-3.5">
        <span className={`flex h-11 w-11 items-center justify-center rounded-2xl ${toneClass[tone] ?? toneClass.mint}`}>
          <Icon size={20} />
        </span>
        <span className="text-center text-[11px] font-semibold leading-tight text-text sm:text-xs">{label}</span>
      </Link>
    </motion.div>
  )
}

function FlowBar({
  label,
  value,
  pct,
  tone,
}: {
  label: string
  value: string
  pct: number
  tone: 'mint' | 'muted'
}) {
  return (
    <div>
      <div className="mb-1 flex items-center justify-between text-xs">
        <span className="text-muted">{label}</span>
        <span className="font-num font-semibold text-text">{value}</span>
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-surface-2">
        <motion.div
          className={`h-full rounded-full ${tone === 'mint' ? 'bg-mint' : 'bg-muted/50'}`}
          initial={{ width: 0 }}
          animate={{ width: `${Math.max(pct, 3)}%` }}
          transition={{ type: 'spring', stiffness: 120, damping: 22, delay: 0.15 }}
        />
      </div>
    </div>
  )
}
