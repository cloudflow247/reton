import { motion } from 'framer-motion'
import { CheckIcon, CopyIcon, EyeIcon, EyeOffIcon, ShieldIcon } from '@/components/icons'
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

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      className="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-[#0a6a4d] via-[#0e7e5c] to-[#094f39] p-5 text-white shadow-[0_32px_64px_-28px_rgba(9,79,57,0.75)] sm:p-6"
    >
      <div aria-hidden className="pointer-events-none absolute inset-0">
        <div className="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10 blur-3xl" />
        <div className="absolute -bottom-20 left-0 h-48 w-48 rounded-full bg-emerald-300/20 blur-3xl" />
      </div>

      <div className="relative flex items-start justify-between gap-2">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/60 sm:text-[11px]">
            Available to spend
          </p>
          <p className="mt-0.5 text-xs text-white/50">Settled · not in escrow</p>
        </div>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          onClick={onToggleHidden}
          className="h-10 w-10 rounded-full text-white/80 hover:bg-white/15 hover:text-white"
          aria-label={hidden ? 'Show balance' : 'Hide balance'}
        >
          {hidden ? <EyeOffIcon size={18} /> : <EyeIcon size={18} />}
        </Button>
      </div>

      <motion.p
        key={hidden ? 'hidden' : 'shown'}
        initial={{ opacity: 0.7, scale: 0.98 }}
        animate={{ opacity: 1, scale: 1 }}
        className={cn(
          'relative mt-3 font-num text-[2.5rem] font-bold leading-none tracking-tight sm:text-[3rem]',
          hidden && 'blur-md select-none',
        )}
      >
        {hidden ? '₦ ••••••' : ngn(animatedAvailable)}
      </motion.p>

      {!hidden && (
        <div className="relative mt-5 grid grid-cols-2 gap-2">
          <div className="rounded-2xl border border-white/15 bg-white/10 px-3 py-2.5 backdrop-blur-sm">
            <p className="text-[10px] font-semibold uppercase tracking-wide text-white/55">Ledger total</p>
            <p className="mt-1 font-num text-sm font-bold">{ngn(totalBalance)}</p>
            <p className="mt-0.5 text-[10px] text-white/45">Available + escrow</p>
          </div>
          <div
            className={cn(
              'rounded-2xl border px-3 py-2.5 backdrop-blur-sm',
              hasPending ? 'border-amber-200/30 bg-amber-400/15' : 'border-white/15 bg-white/10',
            )}
          >
            <p className={cn('text-[10px] font-semibold uppercase tracking-wide', hasPending ? 'text-amber-100/80' : 'text-white/55')}>
              In escrow
            </p>
            <p className="mt-1 inline-flex items-center gap-1.5 font-num text-sm font-bold">
              {hasPending && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-200" />}
              {ngn(pendingBalance)}
            </p>
            <p className={cn('mt-0.5 text-[10px]', hasPending ? 'text-amber-50/60' : 'text-white/45')}>
              Protected / recovery holds
            </p>
          </div>
        </div>
      )}

      {!hidden && hasPending && settledCheck !== totalBalance && (
        <p className="relative mt-2 text-[10px] text-amber-100/70">
          Balance check pending reconciliation — contact support if this persists.
        </p>
      )}

      <div className="relative mt-4 flex flex-col gap-2">
        {retonId && (
          <button
            type="button"
            onClick={onCopyAccount}
            className="inline-flex max-w-full items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-2 text-xs backdrop-blur transition hover:bg-white/20"
          >
            <span className="shrink-0 text-white/55">RETON ID</span>
            <span className="font-num tracking-wider">{retonId}</span>
            {copied ? <CheckIcon size={14} className="text-emerald-200" /> : <CopyIcon size={14} />}
          </button>
        )}

        {depositAccount && (
          <button
            type="button"
            onClick={onCopyDeposit}
            className="inline-flex max-w-full flex-col items-start gap-0.5 rounded-2xl border border-white/20 bg-black/15 px-3.5 py-2.5 text-left text-xs backdrop-blur transition hover:bg-black/25"
          >
            <span className="inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-white/55">
              <ShieldIcon size={12} /> Bank deposit account
            </span>
            <span className="inline-flex items-center gap-2">
              <span className="font-num text-sm tracking-wider">{depositAccount.account_number}</span>
              {depositCopied ? <CheckIcon size={14} className="text-emerald-200" /> : <CopyIcon size={14} />}
            </span>
            <span className="text-[10px] text-white/50">
              {[depositAccount.bank_name, depositAccount.account_name].filter(Boolean).join(' · ') || 'ALAT by Wema'}
            </span>
          </button>
        )}
      </div>
    </motion.div>
  )
}

export function ComplianceStrip() {
  const items = [
    'PIN on every payment',
    'Encrypted KYC',
    'Immutable ledger',
    'Audit logs',
  ] as const

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
