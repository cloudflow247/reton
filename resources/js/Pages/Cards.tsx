import type { ReactNode } from 'react'
import { useState } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import {
  CardIcon,
  CheckIcon,
  ChevronRightIcon,
  ContactlessIcon,
  CopyIcon,
  EyeIcon,
  EyeOffIcon,
  PlusIcon,
  ShieldIcon,
  SnowIcon,
} from '@/components/icons'
import { ngn } from '@/lib/format'
import type { PageProps } from '@/types'

export default function Cards() {
  const { auth } = usePage<PageProps>().props
  const user = auth.user
  const wallet = auth.wallets[0]
  const [reveal, setReveal] = useState(false)
  const [frozen, setFrozen] = useState(false)
  const [copied, setCopied] = useState(false)

  const last4 = (wallet?.account_number ?? '8824').slice(-4)
  const full = `5061 28•• •••• ${last4}`
  const holder = (user?.name ?? 'Reton User').toUpperCase()

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.25 }}
      className="mx-auto max-w-lg space-y-6"
    >
      <Head title="Cards" />

      <div className="flex items-center gap-3">
        <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-mint/10 text-mint">
          <CardIcon size={22} />
        </span>
        <div>
          <h1 className="font-display text-xl font-bold tracking-tight">Cards</h1>
          <p className="text-sm text-muted">Spend online & in-store with a secure virtual card.</p>
        </div>
      </div>

      {/* The card */}
      <motion.div
        whileHover={{ rotateX: 3, rotateY: -3 }}
        transition={{ type: 'spring', stiffness: 200, damping: 18 }}
        style={{ transformPerspective: 1000 }}
        className={`mesh sheen relative h-56 overflow-hidden rounded-[24px] p-6 text-white shadow-[0_28px_60px_-28px_rgba(9,79,57,0.65)] ${
          frozen ? 'grayscale-[0.6]' : ''
        }`}
      >
        <div aria-hidden className="blob pointer-events-none absolute -right-16 -top-20 h-56 w-56 bg-white/15 blur-2xl" />
        <div aria-hidden className="blob-slow pointer-events-none absolute -bottom-24 -left-10 h-52 w-52 bg-[#34e0a8]/25 blur-2xl" />

        <div className="relative flex items-start justify-between">
          <div>
            <span className="text-[11px] uppercase tracking-[0.2em] text-white/60">Virtual Card</span>
            <div className="mt-1 font-display text-lg font-bold tracking-tight">Reton</div>
          </div>
          <ContactlessIcon size={26} className="text-white/60" />
        </div>

        <div className="relative mt-auto">
          <div className="flex items-center gap-2 font-num text-xl tracking-[0.18em] text-white/95">
            {reveal ? full : `•••• •••• •••• ${last4}`}
            <button
              onClick={() => {
                navigator.clipboard.writeText(full.replace(/[^\d]/g, ''))
                setCopied(true)
                setTimeout(() => setCopied(false), 1400)
              }}
              className="text-white/60 transition hover:text-white"
              aria-label="Copy card number"
            >
              {copied ? <CheckIcon size={15} /> : <CopyIcon size={15} />}
            </button>
          </div>

          <div className="mt-4 flex items-end justify-between">
            <div className="min-w-0">
              <div className="text-[10px] uppercase tracking-wider text-white/50">Card holder</div>
              <div className="truncate text-sm font-semibold">{holder}</div>
            </div>
            <div>
              <div className="text-[10px] uppercase tracking-wider text-white/50">Exp</div>
              <div className="font-num text-sm">{reveal ? '08/29' : '••/••'}</div>
            </div>
            <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[10px] font-bold backdrop-blur">
              <span className="grid h-3.5 w-3.5 place-items-center rounded-full bg-white text-[8px] font-black text-mint-strong">
                G
              </span>
              Pay enabled
            </span>
          </div>
        </div>
      </motion.div>

      {/* Card actions */}
      <div className="grid grid-cols-3 gap-3">
        <CardAction Icon={reveal ? EyeOffIcon : EyeIcon} label={reveal ? 'Hide' : 'Details'} onClick={() => setReveal((v) => !v)} />
        <CardAction Icon={SnowIcon} label={frozen ? 'Unfreeze' : 'Freeze'} active={frozen} onClick={() => setFrozen((v) => !v)} />
        <CardActionLink Icon={PlusIcon} label="Fund" href="/add-money" />
      </div>

      {frozen && (
        <motion.p
          initial={{ opacity: 0, y: -4 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-center justify-center gap-2 rounded-xl bg-amber/10 px-4 py-2.5 text-xs font-medium text-amber"
        >
          <SnowIcon size={14} /> Card frozen — no new charges will go through.
        </motion.p>
      )}

      {/* Card balance */}
      <div className="card flex items-center justify-between p-5">
        <div>
          <div className="text-xs text-muted">Spending balance</div>
          <div className="font-num text-xl font-bold">{wallet ? ngn(wallet.available_balance) : '—'}</div>
        </div>
        <Link
          href="/add-money"
          className="btn inline-flex items-center gap-1.5 bg-mint px-4 py-2 text-sm text-white hover:bg-mint-strong"
        >
          <PlusIcon size={15} /> Top up
        </Link>
      </div>

      {/* Select card type */}
      <div className="space-y-3">
        <h2 className="font-display text-sm font-semibold uppercase tracking-wide text-muted">Your cards</h2>

        <div className="card flex items-center gap-3 p-4">
          <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-mint/10 text-mint">
            <CardIcon size={22} />
          </span>
          <div className="min-w-0 flex-1">
            <div className="text-sm font-bold">Virtual card</div>
            <div className="text-xs text-muted">Active · Visa · NGN</div>
          </div>
          <ChevronRightIcon size={18} className="text-muted" />
        </div>

        <div className="card flex items-center gap-3 p-4 opacity-70">
          <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-2 text-muted">
            <CardIcon size={22} />
          </span>
          <div className="min-w-0 flex-1">
            <div className="text-sm font-bold">Physical card</div>
            <div className="text-xs text-muted">Tap-to-pay, delivered to you</div>
          </div>
          <span className="rounded-full border border-mint/30 bg-mint/[0.07] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-mint">
            Coming soon
          </span>
        </div>
      </div>

      {/* Controls */}
      <div className="card divide-y divide-line p-0">
        <ControlRow Icon={SnowIcon} title="Freeze card" hint="Instantly block all transactions">
          <Toggle on={frozen} onClick={() => setFrozen((v) => !v)} />
        </ControlRow>
        <ControlRow Icon={ShieldIcon} title="Online payments" hint="Allow e-commerce charges">
          <Toggle on />
        </ControlRow>
        <ControlRow Icon={EyeIcon} title="Reveal details" hint="Show full number, expiry & CVV">
          <Toggle on={reveal} onClick={() => setReveal((v) => !v)} />
        </ControlRow>
      </div>

      <p className="flex items-center justify-center gap-1.5 pb-2 text-xs text-muted">
        <ShieldIcon size={13} className="text-mint" /> PCI-DSS secured · freeze anytime
      </p>
    </motion.div>
  )
}

Cards.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function CardAction({
  Icon,
  label,
  active,
  onClick,
}: {
  Icon: (p: { size?: number }) => JSX.Element
  label: string
  active?: boolean
  onClick: () => void
}) {
  return (
    <motion.button
      whileTap={{ scale: 0.95 }}
      onClick={onClick}
      className={`flex flex-col items-center gap-2 rounded-2xl border p-3.5 transition ${
        active ? 'border-amber/40 bg-amber/[0.07] text-amber' : 'border-line bg-surface text-text hover:border-mint/40'
      }`}
    >
      <Icon size={20} />
      <span className="text-xs font-semibold">{label}</span>
    </motion.button>
  )
}

function CardActionLink({ Icon, label, href }: { Icon: (p: { size?: number }) => JSX.Element; label: string; href: string }) {
  return (
    <Link href={href} className="flex flex-col items-center gap-2 rounded-2xl border border-line bg-surface p-3.5 text-text transition hover:border-mint/40">
      <Icon size={20} />
      <span className="text-xs font-semibold">{label}</span>
    </Link>
  )
}

function ControlRow({
  Icon,
  title,
  hint,
  children,
}: {
  Icon: (p: { size?: number }) => JSX.Element
  title: string
  hint: string
  children: ReactNode
}) {
  return (
    <div className="flex items-center gap-3 px-5 py-3.5">
      <span className="flex h-9 w-9 items-center justify-center rounded-full bg-surface-2 text-muted">
        <Icon size={17} />
      </span>
      <div className="min-w-0 flex-1">
        <div className="text-sm font-medium">{title}</div>
        <div className="text-xs text-muted">{hint}</div>
      </div>
      {children}
    </div>
  )
}

function Toggle({ on, onClick }: { on: boolean; onClick?: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`relative h-6 w-11 shrink-0 rounded-full transition-colors ${on ? 'bg-mint' : 'bg-surface-2'}`}
      aria-pressed={on}
    >
      <motion.span
        layout
        transition={{ type: 'spring', stiffness: 600, damping: 32 }}
        className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow ${on ? 'right-0.5' : 'left-0.5'}`}
      />
    </button>
  )
}
