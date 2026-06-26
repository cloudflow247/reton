import type { ReactNode } from 'react'
import { useState } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { Card } from '@/components/ui'
import { CheckIcon, CopyIcon, ReceiveIcon, SendIcon, ShieldIcon } from '@/components/icons'
import { ngn, shortDate } from '@/lib/format'
import { useCountUp } from '@/lib/useCountUp'
import type { StatementEntry } from '@/lib/types'
import type { PageProps } from '@/types'

const list = {
  hidden: {},
  show: { transition: { staggerChildren: 0.05, delayChildren: 0.1 } },
}
const row = {
  hidden: { opacity: 0, y: 8 },
  show: { opacity: 1, y: 0 },
}

export default function Dashboard() {
  const { auth, activity } = usePage<PageProps<{ activity: StatementEntry[] }>>().props
  const wallet = auth.wallets[0]
  const [copied, setCopied] = useState(false)
  const balance = useCountUp(wallet?.available_balance ?? 0)

  return (
    <div className="space-y-6">
      <Head title="Dashboard" />
      <div>
        <p className="text-sm text-muted">Good to see you,</p>
        <h1 className="font-display text-2xl font-bold tracking-tight">{auth.user?.name ?? '—'}</h1>
      </div>

      {/* Hero: the balance, on the brand-emerald card. */}
      <motion.div
        initial={{ opacity: 0, y: 14, scale: 0.99 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
        className="brand-card relative overflow-hidden p-6"
      >
        <div className="pointer-events-none absolute -right-12 -top-20 h-64 w-64 rounded-full bg-white/10 blur-2xl" />
        <div className="flex items-center justify-between">
          <span className="text-xs font-medium uppercase tracking-wider text-white/70">Available balance</span>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-xs font-medium text-white">
            <ShieldIcon size={13} /> Protected
          </span>
        </div>
        <div className="mt-3 font-num text-5xl font-bold text-white">{ngn(balance)}</div>
        <div className="mt-2 flex items-center gap-3 text-sm text-white/75">
          <span>Total {wallet ? ngn(wallet.balance) : '—'}</span>
          {!!wallet?.held_balance && (
            <span className="rounded-full bg-white/15 px-2 py-0.5 text-xs text-white">{ngn(wallet.held_balance)} held</span>
          )}
        </div>

        {wallet && (
          <button
            onClick={() => {
              navigator.clipboard.writeText(wallet.account_number ?? '')
              setCopied(true)
              setTimeout(() => setCopied(false), 1500)
            }}
            className="mt-5 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs text-white/85 transition hover:bg-white/15"
            title="Copy your account number so others can pay you"
          >
            <span className="text-white/60">Acct</span>
            <span className="font-num tracking-wider text-white">{wallet.account_number}</span>
            {copied ? <CheckIcon size={14} /> : <CopyIcon size={14} />}
          </button>
        )}
      </motion.div>

      <div className="grid grid-cols-3 gap-3">
        <Action to="/send" label="Send" Icon={SendIcon} />
        <Action to="/receive" label="Receive" Icon={ReceiveIcon} />
        <Action to="/protection" label="Protection" Icon={ShieldIcon} accent />
      </div>

      <div className="flex items-center justify-between">
        <h2 className="font-display text-lg font-semibold">Recent activity</h2>
        <Link href="/activity" className="text-sm text-mint hover:underline">
          See all
        </Link>
      </div>

      <Card className="p-0">
        <motion.div variants={list} initial="hidden" animate="show" className="divide-y divide-line">
          {(activity ?? []).slice(0, 6).map((e) => (
            <motion.div variants={row} key={e.id} className="flex items-center justify-between px-5 py-3.5">
              <div className="flex items-center gap-3">
                <span
                  className={`flex h-9 w-9 items-center justify-center rounded-full ${
                    e.direction === 'credit' ? 'bg-mint/10 text-mint' : 'bg-surface-2 text-muted'
                  }`}
                >
                  {e.direction === 'credit' ? <ReceiveIcon size={16} /> : <SendIcon size={16} />}
                </span>
                <div>
                  <div className="text-sm font-medium">{e.transaction?.description ?? e.transaction?.type ?? 'Movement'}</div>
                  <div className="text-xs text-muted">{shortDate(e.created_at)}</div>
                </div>
              </div>
              <div className={`font-num text-sm ${e.direction === 'credit' ? 'text-mint' : 'text-text'}`}>
                {e.direction === 'credit' ? '+' : '−'}
                {ngn(e.amount)}
              </div>
            </motion.div>
          ))}
          {activity && activity.length === 0 && (
            <div className="px-5 py-10 text-center text-sm text-muted">
              No movements yet. Add money to get started.
            </div>
          )}
        </motion.div>
      </Card>
    </div>
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
    <motion.div whileHover={{ y: -3 }} whileTap={{ scale: 0.97 }} transition={{ type: 'spring', stiffness: 400, damping: 25 }}>
      <Link
        href={to}
        className="flex flex-col items-center gap-2 rounded-2xl border border-line bg-surface px-3 py-4 shadow-sm transition hover:border-mint/40"
      >
        <span
          className={`flex h-10 w-10 items-center justify-center rounded-full ${
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
