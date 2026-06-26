import type { ReactNode } from 'react'
import { useState } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import {
  BillIcon,
  CheckIcon,
  ChevronRightIcon,
  ClockIcon,
  CopyIcon,
  EyeIcon,
  EyeOffIcon,
  PlusIcon,
  ReceiveIcon,
  SendIcon,
  ShieldIcon,
  WalletIcon,
} from '@/components/icons'
import { ngn, shortDate } from '@/lib/format'
import { useCountUp } from '@/lib/useCountUp'
import type { StatementEntry } from '@/lib/types'
import type { PageProps } from '@/types'

const list = {
  hidden: {},
  show: { transition: { staggerChildren: 0.06, delayChildren: 0.08 } },
}
const item = {
  hidden: { opacity: 0, y: 10 },
  show: { opacity: 1, y: 0, transition: { type: 'spring', stiffness: 340, damping: 26 } },
}

const actions = [
  { to: '/send', label: 'Send', Icon: SendIcon },
  { to: '/add-money', label: 'Add money', Icon: PlusIcon },
  { to: '/bills', label: 'Bills', Icon: BillIcon },
  { to: '/protection', label: 'Protection', Icon: ShieldIcon, accent: true },
] as const

export default function Dashboard() {
  const { auth, activity } = usePage<PageProps<{ activity: StatementEntry[] }>>().props
  const wallet = auth.wallets[0]
  const [copied, setCopied] = useState(false)
  const [hidden, setHidden] = useState(false)
  const balance = useCountUp(wallet?.available_balance ?? 0)
  const recent = (activity ?? []).slice(0, 6)

  return (
    <motion.div variants={list} initial="hidden" animate="show" className="space-y-6">
      <Head title="Dashboard" />

      {/* Greeting */}
      <motion.div variants={item} className="flex items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <span className="flex h-11 w-11 items-center justify-center rounded-full bg-mint/10 font-display text-lg font-bold text-mint">
            {(auth.user?.name ?? 'R').charAt(0).toUpperCase()}
          </span>
          <div>
            <p className="text-sm text-muted">Good to see you,</p>
            <h1 className="font-display text-xl font-bold leading-tight tracking-tight">{auth.user?.name ?? '—'}</h1>
          </div>
        </div>
        <Link
          href="/pin"
          className="hidden items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5 text-xs font-medium text-muted transition hover:border-mint/40 hover:text-mint sm:inline-flex"
        >
          <ShieldIcon size={14} />
          {auth.user?.has_transaction_pin ? 'PIN active' : 'Set your PIN'}
        </Link>
      </motion.div>

      {/* Hero: the balance, on the brand-emerald card. */}
      <motion.div
        variants={item}
        className="brand-card relative overflow-hidden p-6"
      >
        <motion.div
          aria-hidden
          className="pointer-events-none absolute -right-12 -top-20 h-64 w-64 rounded-full bg-white/10 blur-2xl"
          animate={{ scale: [1, 1.18, 1], opacity: [0.55, 0.85, 0.55] }}
          transition={{ duration: 8, repeat: Infinity, ease: 'easeInOut' }}
        />
        <div className="relative flex items-center justify-between">
          <span className="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-white/70">
            <WalletIcon size={15} /> Available balance
          </span>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-xs font-medium text-white">
            <ShieldIcon size={13} /> Protected
          </span>
        </div>

        <div className="relative mt-3 flex items-center gap-3">
          <div className="font-num text-5xl font-bold text-white">
            {hidden ? <span className="tracking-[0.1em]">₦ ••••••</span> : ngn(balance)}
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

        <div className="relative mt-2 flex items-center gap-3 text-sm text-white/75">
          <span>Total {wallet && !hidden ? ngn(wallet.balance) : '••••'}</span>
          {!!wallet?.held_balance && !hidden && (
            <span className="rounded-full bg-white/15 px-2 py-0.5 text-xs text-white">
              {ngn(wallet.held_balance)} held
            </span>
          )}
        </div>

        {wallet && (
          <button
            onClick={() => {
              navigator.clipboard.writeText(wallet.account_number ?? '')
              setCopied(true)
              setTimeout(() => setCopied(false), 1500)
            }}
            className="relative mt-5 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs text-white/85 transition hover:bg-white/15"
            title="Copy your account number so others can pay you"
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
      </motion.div>

      {/* Quick actions */}
      <motion.div variants={item} className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {actions.map((a) => (
          <Action key={a.to} to={a.to} label={a.label} Icon={a.Icon} accent={'accent' in a && a.accent} />
        ))}
      </motion.div>

      {/* Insight tiles */}
      <motion.div variants={item} className="grid grid-cols-2 gap-3">
        <Tile
          Icon={ClockIcon}
          tone="amber"
          label="Held in escrow"
          value={wallet ? ngn(wallet.held_balance) : '—'}
          hint="Protected & recoverable"
        />
        <Tile
          Icon={WalletIcon}
          tone="mint"
          label="Total balance"
          value={wallet ? ngn(wallet.balance) : '—'}
          hint="Available + held"
        />
      </motion.div>

      {/* Recent activity */}
      <motion.div variants={item} className="flex items-center justify-between">
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

function Action({
  to,
  label,
  Icon,
  accent,
}: {
  to: string
  label: string
  Icon: (p: { size?: number }) => JSX.Element
  accent?: boolean
}) {
  return (
    <motion.div whileHover={{ y: -3 }} whileTap={{ scale: 0.96 }} transition={{ type: 'spring', stiffness: 400, damping: 22 }}>
      <Link
        href={to}
        className="flex flex-col items-center gap-2 rounded-2xl border border-line bg-surface px-3 py-4 shadow-sm transition hover:border-mint/40"
      >
        <span
          className={`flex h-10 w-10 items-center justify-center rounded-full transition ${
            accent ? 'bg-mint text-white' : 'bg-mint/10 text-mint'
          }`}
        >
          <Icon size={18} />
        </span>
        <span className="font-display text-sm font-semibold">{label}</span>
      </Link>
    </motion.div>
  )
}

function Tile({
  Icon,
  tone,
  label,
  value,
  hint,
}: {
  Icon: (p: { size?: number }) => JSX.Element
  tone: 'mint' | 'amber'
  label: string
  value: string
  hint: string
}) {
  const toneCls = tone === 'amber' ? 'bg-amber/10 text-amber' : 'bg-mint/10 text-mint'
  return (
    <div className="card flex items-center gap-3 p-4">
      <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${toneCls}`}>
        <Icon size={18} />
      </span>
      <div className="min-w-0">
        <div className="text-xs text-muted">{label}</div>
        <div className="truncate font-num text-base font-semibold">{value}</div>
        <div className="truncate text-[11px] text-muted">{hint}</div>
      </div>
    </div>
  )
}
