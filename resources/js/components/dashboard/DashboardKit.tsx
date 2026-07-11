import { useEffect, useState, type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { BankIcon, CheckIcon, CopyIcon, EyeIcon, EyeOffIcon, ShieldIcon } from '@/components/icons'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { ngn } from '@/lib/format'
import type { Wallet } from '@/lib/types'

type DepositAccount = {
  account_number: string
  account_name: string | null
  bank_name: string | null
}

type BalanceHeroCardProps = {
  wallet: Wallet | undefined
  availableBalance: number
  animatedAvailable: number
  totalBalance: number
  pendingBalance: number
  hidden: boolean
  copied: boolean
  depositAccount?: DepositAccount | null
  depositCopied?: boolean
  onToggleHidden: () => void
  onCopyAccount: () => void
  onCopyDeposit?: () => void
}

type ReceiveTab = 'reton' | 'bank'

export function BalanceHeroCard({
  wallet,
  availableBalance,
  animatedAvailable,
  totalBalance,
  pendingBalance,
  hidden,
  copied,
  depositAccount = null,
  depositCopied = false,
  onToggleHidden,
  onCopyAccount,
  onCopyDeposit,
}: BalanceHeroCardProps) {
  const hasPending = pendingBalance > 0
  const retonId = wallet?.account_number ?? null
  const settledCheck = availableBalance + pendingBalance
  const hasBank = Boolean(depositAccount?.account_number)
  const [receiveTab, setReceiveTab] = useState<ReceiveTab>(hasBank ? 'bank' : 'reton')

  useEffect(() => {
    setReceiveTab(hasBank ? 'bank' : 'reton')
  }, [hasBank])

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
      className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#0a6a4d] via-[#0e7e5c] to-[#094f39] p-3.5 text-white shadow-[0_20px_40px_-22px_rgba(9,79,57,0.75)] sm:rounded-[28px] sm:p-6"
    >
      <div aria-hidden className="pointer-events-none absolute inset-0">
        <div className="absolute -right-14 -top-16 h-44 w-44 rounded-full bg-white/12 blur-3xl" />
        <div className="absolute -bottom-16 left-0 h-40 w-40 rounded-full bg-emerald-200/25 blur-3xl" />
      </div>

      <div className="relative flex items-center justify-between gap-2">
        <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-white sm:text-xs">
          Available to spend
        </p>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          onClick={onToggleHidden}
          className="h-8 w-8 shrink-0 rounded-full bg-white/10 text-white hover:bg-white/20 hover:text-white sm:h-9 sm:w-9"
          aria-label={hidden ? 'Show balance' : 'Hide balance'}
        >
          {hidden ? <EyeOffIcon size={16} /> : <EyeIcon size={16} />}
        </Button>
      </div>

      <motion.p
        key={hidden ? 'hidden' : 'shown'}
        initial={{ opacity: 0.75, y: 4 }}
        animate={{ opacity: 1, y: 0 }}
        className={cn(
          'relative mt-1 font-num text-[1.9rem] font-bold leading-none tracking-tight text-white sm:mt-2 sm:text-[2.75rem]',
          hidden && 'blur-md select-none',
        )}
      >
        {hidden ? '₦ ••••••' : ngn(animatedAvailable)}
      </motion.p>

      {!hidden && (
        <div className="relative mt-2.5 grid grid-cols-2 gap-2 sm:mt-4">
          <MetricChip label="Ledger" value={ngn(totalBalance)} hint="Total balance" />
          <MetricChip
            label="Escrow"
            value={ngn(pendingBalance)}
            hint="Held funds"
            warn={hasPending}
          />
        </div>
      )}

      {!hidden && hasPending && settledCheck !== totalBalance && (
        <p className="relative mt-2 rounded-lg bg-amber-400/20 px-2.5 py-1.5 text-[11px] font-medium text-white">
          Balance check pending — contact support if this persists.
        </p>
      )}

      <div className="relative mt-3 sm:mt-4">
        <div
          className="grid grid-cols-2 gap-1 rounded-xl bg-black/25 p-1"
          role="tablist"
          aria-label="How to receive money"
        >
          <ReceiveTabButton
            active={receiveTab === 'reton'}
            onClick={() => setReceiveTab('reton')}
            icon={<ShieldIcon size={13} />}
            label="Reton ID"
          />
          <ReceiveTabButton
            active={receiveTab === 'bank'}
            onClick={() => setReceiveTab('bank')}
            icon={<BankIcon size={13} />}
            label="Bank fund"
          />
        </div>

        <div className="mt-2">
          {receiveTab === 'reton' ? (
            <ReceivePanel
              eyebrow="Receive on Reton"
              hint="For Reton users only — not a bank account"
              value={retonId}
              copied={copied}
              onCopy={onCopyAccount}
              empty={<p className="text-xs font-medium text-white">Your Reton ID appears after wallet setup.</p>}
            />
          ) : hasBank && depositAccount ? (
            <ReceivePanel
              eyebrow="Fund from your bank"
              hint={[depositAccount.bank_name, depositAccount.account_name].filter(Boolean).join(' · ') || 'Transfer from any Nigerian bank'}
              value={depositAccount.account_number}
              copied={depositCopied}
              onCopy={onCopyDeposit}
            />
          ) : (
            <div className="rounded-xl border border-white/30 bg-black/20 px-3 py-2.5">
              <p className="text-xs font-bold text-white">No bank funding account yet</p>
              <p className="mt-1 text-[11px] leading-snug text-white/90">
                Reton ID is for Reton-to-Reton only. Set up bank funding to receive transfers from other banks.
              </p>
              <Link
                href="/add-money"
                prefetch
                className="mt-2 inline-flex text-xs font-bold text-white underline decoration-white/50 underline-offset-2 hover:decoration-white"
              >
                Set up bank funding →
              </Link>
            </div>
          )}
        </div>
      </div>
    </motion.div>
  )
}

function MetricChip({
  label,
  value,
  hint,
  warn = false,
}: {
  label: string
  value: string
  hint: string
  warn?: boolean
}) {
  return (
    <div
      className={cn(
        'rounded-xl border px-2.5 py-2 sm:px-3 sm:py-2.5',
        warn ? 'border-amber-200/40 bg-amber-400/20' : 'border-white/25 bg-white/15',
      )}
    >
      <p className="text-[10px] font-bold uppercase tracking-wide text-white/90">{label}</p>
      <p className="mt-0.5 inline-flex items-center gap-1.5 font-num text-sm font-bold text-white sm:text-[15px]">
        {warn && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-100" />}
        {value}
      </p>
      <p className="mt-0.5 text-[10px] font-medium text-white/80">{hint}</p>
    </div>
  )
}

function ReceiveTabButton({
  active,
  onClick,
  icon,
  label,
}: {
  active: boolean
  onClick: () => void
  icon: ReactNode
  label: string
}) {
  return (
    <button
      type="button"
      role="tab"
      aria-selected={active}
      onClick={onClick}
      className={cn(
        'inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-bold transition',
        active ? 'bg-white text-[#0a6a4d] shadow-sm' : 'text-white/85 hover:bg-white/10 hover:text-white',
      )}
    >
      {icon}
      {label}
    </button>
  )
}

function ReceivePanel({
  eyebrow,
  hint,
  value,
  copied = false,
  onCopy,
  empty,
}: {
  eyebrow: string
  hint: string
  value?: string | null
  copied?: boolean
  onCopy?: () => void
  empty?: ReactNode
}) {
  if (!value) {
    return <>{empty}</>
  }

  return (
    <button
      type="button"
      onClick={onCopy}
      className="flex w-full items-center gap-2.5 rounded-xl border border-white/30 bg-white/15 px-3 py-2.5 text-left transition hover:bg-white/20 active:scale-[0.99]"
    >
      <span className="min-w-0 flex-1">
        <span className="block text-[10px] font-bold uppercase tracking-wide text-white/90">{eyebrow}</span>
        <span className="mt-0.5 block font-num text-[15px] font-bold tracking-wider text-white">{value}</span>
        <span className="mt-0.5 block truncate text-[11px] font-medium text-white/85">{hint}</span>
      </span>
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
        {copied ? <CheckIcon size={15} className="text-emerald-100" /> : <CopyIcon size={15} />}
      </span>
    </button>
  )
}

export function ComplianceStrip({ compact = false }: { compact?: boolean }) {
  const items = [
    'PIN on every payment',
    'Encrypted KYC',
    'Immutable ledger',
    'Audit logs',
  ] as const

  if (compact) {
    return (
      <div className="flex flex-wrap gap-1.5">
        {items.map((label) => (
          <span
            key={label}
            className="inline-flex items-center gap-1 rounded-full border border-line bg-surface px-2.5 py-1 text-[10px] font-semibold text-muted"
          >
            <CheckIcon size={10} className="text-mint" /> {label}
          </span>
        ))}
      </div>
    )
  }

  return (
    <div className="rounded-2xl border border-line/80 bg-surface/80 px-4 py-3 backdrop-blur-sm">
      <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">Security posture</p>
      <p className="mt-1 text-xs leading-relaxed text-muted">
        Built to PCI DSS and ISO 27001 control principles — encryption in transit & at rest, least-privilege
        access, and verification audit trails. Reton is not a certified PCI DSS Level 1 processor; settlement
        runs on licensed bank rails.
      </p>
      <div className="mt-3 flex flex-wrap gap-2">
        {items.map((label) => (
          <span
            key={label}
            className="inline-flex items-center gap-1 rounded-full border border-mint/20 bg-mint/5 px-2.5 py-1 text-[10px] font-semibold text-mint"
          >
            <CheckIcon size={10} /> {label}
          </span>
        ))}
      </div>
    </div>
  )
}
