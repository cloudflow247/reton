import type { ComponentType, ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { CheckIcon, WalletIcon } from './icons'
import { Button } from './ui'
import { ngn } from '@/lib/format'

export const PAGE_SPRING = { type: 'spring', stiffness: 380, damping: 32 } as const

export const pageList = {
  hidden: {},
  show: { transition: { staggerChildren: 0.05, delayChildren: 0.02 } },
}

export const pageItem = {
  hidden: { y: 10 },
  show: { y: 0, transition: PAGE_SPRING },
}

type IconCmp = ComponentType<{ size?: number; className?: string }>

const heroTones = {
  mint: {
    gradient: 'from-emerald-500/20 via-mint/10 to-transparent',
    iconBg: 'bg-mint/15 text-mint',
  },
  slate: {
    gradient: 'from-slate-500/15 via-slate-400/10 to-transparent',
    iconBg: 'bg-slate-500/15 text-slate-600',
  },
  violet: {
    gradient: 'from-violet-500/20 via-purple-400/10 to-transparent',
    iconBg: 'bg-violet-500/15 text-violet-600',
  },
  amber: {
    gradient: 'from-amber-500/20 via-orange-400/10 to-transparent',
    iconBg: 'bg-amber-500/15 text-amber-600',
  },
  sky: {
    gradient: 'from-sky-500/20 via-blue-400/10 to-transparent',
    iconBg: 'bg-sky-500/15 text-sky-600',
  },
} as const

/** Staggered page wrapper — use on every authenticated screen. */
export function Page({
  children,
  className = '',
  narrow = false,
}: {
  children: ReactNode
  className?: string
  narrow?: boolean
}) {
  return (
    <motion.div
      variants={pageList}
      initial="hidden"
      animate="show"
      className={`${narrow ? 'mx-auto max-w-lg' : ''} space-y-5 pb-6 ${className}`}
    >
      {children}
    </motion.div>
  )
}

/** Compact hero — title, one-line instruction, optional balance chip. */
export function PageHero({
  icon: Icon,
  title,
  subtitle,
  balance,
  tone = 'mint',
  mesh = false,
}: {
  icon: IconCmp
  title: string
  subtitle: string
  balance?: number
  tone?: keyof typeof heroTones
  mesh?: boolean
}) {
  const t = heroTones[tone]

  if (mesh) {
    return (
      <motion.div variants={pageItem} className="mesh sheen relative overflow-hidden rounded-[22px] p-5 text-white sm:p-6">
        <div aria-hidden className="pointer-events-none absolute inset-0">
          <div className="blob absolute -right-10 -top-12 h-40 w-40 bg-white/10 blur-2xl" />
          <div className="blob-slow absolute -bottom-10 left-0 h-36 w-36 bg-emerald-300/20 blur-3xl" />
        </div>
        <div className="relative flex items-start justify-between gap-3">
          <div>
            <div className="flex items-center gap-2.5">
              <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 backdrop-blur-sm">
                <Icon size={20} />
              </span>
              <div>
                <h1 className="font-display text-xl font-bold tracking-tight sm:text-2xl">{title}</h1>
                <p className="mt-0.5 text-xs text-white/75 sm:text-sm">{subtitle}</p>
              </div>
            </div>
          </div>
          {balance !== undefined && (
            <div className="rounded-xl bg-white/12 px-3 py-2 text-right backdrop-blur-sm">
              <div className="flex items-center justify-end gap-1 text-[10px] font-medium uppercase tracking-wide text-white/60">
                <WalletIcon size={11} /> Available
              </div>
              <div className="font-num text-sm font-bold">{ngn(balance)}</div>
            </div>
          )}
        </div>
      </motion.div>
    )
  }

  return (
    <motion.div
      variants={pageItem}
      className={`panel relative overflow-hidden bg-gradient-to-br ${t.gradient} p-5`}
    >
      <div className="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-mint/8 blur-2xl" />
      <div className="relative flex items-start justify-between gap-3">
        <div className="flex items-start gap-3">
          <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${t.iconBg}`}>
            <Icon size={22} />
          </span>
          <div>
            <h1 className="font-display text-xl font-bold tracking-tight sm:text-2xl">{title}</h1>
            <p className="mt-1 text-sm leading-relaxed text-muted">{subtitle}</p>
          </div>
        </div>
        {balance !== undefined && <BalanceChip balance={balance} />}
      </div>
    </motion.div>
  )
}

export function BalanceChip({ balance }: { balance: number }) {
  return (
    <div className="shrink-0 rounded-xl border border-line/80 bg-surface/90 px-3 py-2 text-right backdrop-blur-sm">
      <div className="flex items-center justify-end gap-1 text-[10px] font-medium uppercase tracking-wide text-muted">
        <WalletIcon size={11} /> Balance
      </div>
      <div className="font-num text-sm font-bold text-mint">{ngn(balance)}</div>
    </div>
  )
}

/** Morphing step indicator — clear progress on multi-step flows. */
export function PageSteps({ steps, current }: { steps: string[]; current: number }) {
  return (
    <motion.div variants={pageItem} className="flex items-center gap-2 px-0.5">
      {steps.map((label, i) => {
        const n = i + 1
        const done = current > n
        const active = current === n
        return (
          <div key={label} className="flex flex-1 items-center gap-2">
            <span
              className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold transition-colors ${
                done || active ? 'bg-mint text-white' : 'bg-surface-2 text-muted'
              } ${active ? 'ring-2 ring-mint/30 ring-offset-2 ring-offset-bg' : ''}`}
            >
              {done ? <CheckIcon size={12} /> : n}
            </span>
            <span className={`hidden text-[11px] font-medium sm:inline ${active ? 'text-text' : 'text-muted'}`}>
              {label}
            </span>
            {i < steps.length - 1 && (
              <div className={`h-px flex-1 transition-colors ${done ? 'bg-mint/40' : 'bg-line'}`} />
            )}
          </div>
        )
      })}
    </motion.div>
  )
}

/** Segmented control with layoutId morph — tabs, filters, modes. */
export function MorphTabs<T extends string>({
  tabs,
  value,
  onChange,
  layoutId,
  className = '',
}: {
  tabs: { id: T; label: string; count?: number }[]
  value: T
  onChange: (id: T) => void
  layoutId: string
  className?: string
}) {
  return (
    <motion.div variants={pageItem} className={`inline-flex rounded-full border border-line bg-surface-2 p-1 ${className}`}>
      {tabs.map((t) => {
        const on = value === t.id
        return (
          <button
            key={t.id}
            type="button"
            onClick={() => onChange(t.id)}
            className="relative rounded-full px-4 py-2 font-display text-sm font-semibold sm:px-5"
          >
            {on && (
              <motion.span
                layoutId={layoutId}
                className="absolute inset-0 rounded-full bg-mint shadow-sm"
                transition={PAGE_SPRING}
              />
            )}
            <span className={`relative z-10 flex items-center gap-1.5 ${on ? 'text-white' : 'text-muted'}`}>
              {t.label}
              {t.count !== undefined && t.count > 0 && (
                <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold ${on ? 'bg-white/20' : 'bg-line'}`}>
                  {t.count}
                </span>
              )}
            </span>
          </button>
        )
      })}
    </motion.div>
  )
}

/** Security / info strip — one clear sentence. */
export function InfoStrip({
  tone = 'amber',
  title,
  children,
}: {
  tone?: 'amber' | 'mint' | 'muted'
  title?: string
  children: ReactNode
}) {
  const styles = {
    amber: 'border-amber/25 bg-amber/[0.06]',
    mint: 'border-mint/25 bg-mint/[0.06]',
    muted: 'border-line bg-surface-2/60',
  }[tone]

  return (
    <motion.div variants={pageItem} className={`rounded-2xl border px-4 py-3 text-xs leading-relaxed text-muted ${styles}`}>
      {title && <p className="mb-0.5 font-semibold text-text">{title}</p>}
      {children}
    </motion.div>
  )
}

/** Primary form surface — consistent padding, glow, spacing. */
export function FormPanel({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <motion.div variants={pageItem} className={`panel shield-glow space-y-5 p-4 sm:p-5 ${className}`}>
      {children}
    </motion.div>
  )
}

export function SectionLabel({ children, action }: { children: ReactNode; action?: ReactNode }) {
  return (
    <motion.div variants={pageItem} className="flex items-center justify-between px-0.5">
      <h2 className="text-xs font-semibold uppercase tracking-wide text-muted">{children}</h2>
      {action}
    </motion.div>
  )
}

/** Unified outcome screen after a successful action. */
export function SuccessScreen({
  ok = true,
  amount,
  title,
  subtitle,
  children,
  primaryLabel,
  onPrimary,
  secondaryHref,
  secondaryLabel = 'Back to home',
}: {
  ok?: boolean
  amount?: number
  title: string
  subtitle?: string
  children?: ReactNode
  primaryLabel: string
  onPrimary: () => void
  secondaryHref?: string
  secondaryLabel?: string
}) {
  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.96 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={PAGE_SPRING}
      className="mx-auto max-w-md px-1 pt-2"
    >
      <div className="panel shield-glow overflow-hidden p-0 text-center">
        <div
          className={`relative px-6 pb-8 pt-10 ${
            ok ? 'bg-gradient-to-b from-mint/15 to-transparent' : 'bg-gradient-to-b from-danger/10 to-transparent'
          }`}
        >
          <motion.div
            initial={{ scale: 0.5, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            transition={{ ...PAGE_SPRING, delay: 0.05 }}
            className={`mx-auto flex h-20 w-20 items-center justify-center rounded-full ${
              ok ? 'bg-mint text-white shadow-lg shadow-mint/30' : 'bg-danger/15 text-danger'
            }`}
          >
            <CheckIcon size={36} />
          </motion.div>
          {amount !== undefined && (
            <motion.h2
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.12 }}
              className="mt-5 font-num text-3xl font-bold tracking-tight"
            >
              {ngn(amount)}
            </motion.h2>
          )}
          <p className={`font-display text-lg font-bold tracking-tight ${amount !== undefined ? 'mt-1' : 'mt-5'}`}>
            {title}
          </p>
          {subtitle && <p className="mt-1 text-sm text-muted">{subtitle}</p>}
        </div>
        <div className="space-y-3 px-6 pb-8 text-left">
          {children}
          <Button className="w-full py-3.5" onClick={onPrimary}>
            {primaryLabel}
          </Button>
          {secondaryHref && (
            <Link href={secondaryHref} className="block text-center text-sm font-semibold text-mint hover:underline">
              {secondaryLabel}
            </Link>
          )}
        </div>
      </div>
    </motion.div>
  )
}

/** Numbered instruction row — getting started, how-it-works. */
export function StepRow({
  step,
  title,
  detail,
  href,
  icon: Icon,
}: {
  step: number
  title: string
  detail: string
  href: string
  icon: IconCmp
}) {
  return (
    <Link
      href={href}
      className="group flex items-center gap-3 rounded-xl border border-line bg-surface/80 px-3.5 py-3 transition hover:border-mint/35"
    >
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-mint/12 text-sm font-bold text-mint">
        {step}
      </span>
      <span className="min-w-0 flex-1">
        <span className="flex items-center gap-1.5 text-sm font-semibold">
          <Icon size={15} className="text-mint" /> {title}
        </span>
        <span className="block text-xs text-muted">{detail}</span>
      </span>
    </Link>
  )
}

export function EmptyState({
  icon: Icon,
  title,
  description,
  action,
}: {
  icon: IconCmp
  title: string
  description: string
  action?: ReactNode
}) {
  return (
    <div className="flex flex-col items-center gap-3 px-4 py-12 text-center">
      <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-mint/10 text-mint">
        <Icon size={24} />
      </span>
      <p className="text-sm font-medium">{title}</p>
      <p className="max-w-xs text-xs leading-relaxed text-muted">{description}</p>
      {action}
    </div>
  )
}

/** PIN input — consistent styling everywhere. */
export function PinField({
  id = 'pin',
  value,
  onChange,
  error,
  label = 'Transaction PIN',
}: {
  id?: string
  value: string
  onChange: (v: string) => void
  error?: string
  label?: string
}) {
  return (
    <div>
      <label htmlFor={id} className="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted">
        {label}
      </label>
      <input
        id={id}
        type="password"
        inputMode="numeric"
        maxLength={4}
        value={value}
        onChange={(e) => onChange(e.target.value.replace(/\D/g, '').slice(0, 4))}
        placeholder="••••"
        autoComplete="off"
        className="field w-full px-4 py-3.5 text-center font-num text-lg tracking-[0.35em]"
      />
      <AnimatePresence>
        {error && (
          <motion.p
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            className="mt-1.5 text-xs text-danger"
          >
            {error}
          </motion.p>
        )}
      </AnimatePresence>
    </div>
  )
}

export function SelectField({
  id,
  label,
  value,
  onChange,
  options,
  placeholder = 'Choose…',
  error,
}: {
  id: string
  label: string
  value: string
  onChange: (v: string) => void
  options: { value: string; label: string }[]
  placeholder?: string
  error?: string
}) {
  return (
    <div>
      <label htmlFor={id} className="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted">
        {label}
      </label>
      <select
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="field w-full px-4 py-3.5 text-sm font-medium"
      >
        <option value="">{placeholder}</option>
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
      {error && <p className="mt-1.5 text-xs text-danger">{error}</p>}
    </div>
  )
}
