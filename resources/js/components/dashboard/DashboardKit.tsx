import { useState, type ReactNode } from 'react'
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

  // Prefer bank tab when a funding account exists — that's the usual confusion point.
  const [receiveTab, setReceiveTab] = useState<ReceiveTab>(hasBank ? 'bank' : 'reton')

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      className="relative overflow-hidden rounded-[22px] bg-gradient-to-br from-[#0a6a4d] via-[#0e7e5c] to-[#094f39] p-3.5 text-white shadow-[0_24px_48px_-24px_rgba(9,79,57,0.7)] sm:rounded-[28px] sm:p-6"
    >
      <div aria-hidden className="pointer-events-none absolute inset-0">
        <div className="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-white/10 blur-3xl" />
        <div className="absolute -bottom-20 left-0 h-48 w-48 rounded-full bg-emerald-300/20 blur-3xl" />
      </div>

      <div className="relative flex items-center justify-between gap-2">
        <div className="min-w-0">
          <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-white/60">
            Available to spend
          </p>
          <p className="mt-0.5 hidden text-xs text-white/50 sm:block">Settled · not in escrow</p>
        </div>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          onClick={onToggleHidden}
          className="h-8 w-8 shrink-0 rounded-full text-white/80 hover:bg-white/15 hover:text-white sm:h-10 sm:w-10"
          aria-label={hidden ? 'Show balance' : 'Hide balance'}
        >
          {hidden ? <EyeOffIcon size={16} /> : <EyeIcon size={16} />}
        </Button>
      </div>

      <motion.p
        key={hidden ? 'hidden' : 'shown'}
        initial={{ opacity: 0.7, scale: 0.98 }}
        animate={{ opacity: 1, scale: 1 }}
        className={cn(
          'relative mt-1.5 font-num text-[1.85rem] font-bold leading-none tracking-tight sm:mt-3 sm:text-[3rem]',
          hidden && 'blur-md select-none',
        )}
      >
        {hidden ? '₦ ••••••' : ngn(animatedAvailable)}
      </motion.p>

      {!hidden && (
        <div className="relative mt-2.5 flex items-stretch gap-1.5 sm:mt-5 sm:grid sm:grid-cols-2 sm:gap-2">
          <div className="min-w-0 flex-1 rounded-xl border border-white/15 bg-white/10 px-2.5 py-1.5 backdrop-blur-sm sm:rounded-2xl sm:px-3 sm:py-2.5">
            <p className="text-[9px] font-semibold uppercase tracking-wide text-white/55 sm:text-[10px]">
              Ledger
            </p>
            <p className="mt-0.5 font-num text-xs font-bold sm:mt-1 sm:text-sm">{ngn(totalBalance)}</p>
            <p className="hidden text-[10px] text-white/45 sm:mt-0.5 sm:block">Available + escrow</p>
          </div>
          <div
            className={cn(
              'min-w-0 flex-1 rounded-xl border px-2.5 py-1.5 backdrop-blur-sm sm:rounded-2xl sm:px-3 sm:py-2.5',
              hasPending ? 'border-amber-200/30 bg-amber-400/15' : 'border-white/15 bg-white/10',
            )}
          >
            <p
              className={cn(
                'text-[9px] font-semibold uppercase tracking-wide sm:text-[10px]',
                hasPending ? 'text-amber-100/80' : 'text-white/55',
              )}
            >
              Escrow
            </p>
            <p className="mt-0.5 inline-flex items-center gap-1 font-num text-xs font-bold sm:mt-1 sm:gap-1.5 sm:text-sm">
              {hasPending && <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-200" />}
              {ngn(pendingBalance)}
            </p>
            <p className={cn('hidden text-[10px] sm:mt-0.5 sm:block', hasPending ? 'text-amber-50/60' : 'text-white/45')}>
              Protected / recovery holds
            </p>
          </div>
        </div>
      )}

      {!hidden && hasPending && settledCheck !== totalBalance && (
        <p className="relative mt-1.5 text-[10px] text-amber-100/70 sm:mt-2">
          Balance check pending reconciliation — contact support if this persists.
        </p>
      )}

      <div className="relative mt-2.5 sm:mt-4">
        <div
          className="flex rounded-full border border-white/15 bg-black/20 p-0.5"
          role="tablist"
          aria-label="How to receive money"
        >
          <ReceiveTabButton
            active={receiveTab === 'reton'}
            onClick={() => setReceiveTab('reton')}
            icon={<ShieldIcon size={12} />}
            label="Reton ID"
          />
          <ReceiveTabButton
            active={receiveTab === 'bank'}
            onClick={() => setReceiveTab('bank')}
            icon={<BankIcon size={12} />}
            label="Bank fund"
          />
        </div>

        <div className="mt-1.5 sm:mt-2">
          {receiveTab === 'reton' ? (
            <ReceivePanel
              eyebrow="Pay you on Reton"
              hint="Friends send here — not a bank account"
              value={retonId}
              copied={copied}
              onCopy={onCopyAccount}
              empty={
                <p className="text-[11px] text-white/60">Your Reton ID will appear after wallet setup.</p>
              }
            />
          ) : hasBank && depositAccount ? (
            <ReceivePanel
              eyebrow="Fund from any bank"
              hint={[depositAccount.bank_name, depositAccount.account_name].filter(Boolean).join(' · ') || 'Transfer from your bank app'}
              value={depositAccount.account_number}
              copied={depositCopied}
              onCopy={onCopyDeposit}
            />
          ) : (
            <div className="rounded-xl border border-dashed border-white/25 bg-black/15 px-3 py-2.5 sm:rounded-2xl">
              <p className="text-[11px] font-semibold text-white/90">No bank funding account yet</p>
              <p className="mt-0.5 text-[10px] leading-snug text-white/55">
                Reton ID is for Reton-to-Reton only. Open a bank account to receive transfers from GTBank, Access, Zenith, and others.
              </p>
              <Link
                href="/add-money"
                prefetch
                className="mt-2 inline-flex text-[11px] font-semibold text-emerald-200 underline-offset-2 hover:underline"
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
        'flex flex-1 items-center justify-center gap-1 rounded-full px-2 py-1.5 text-[10px] font-semibold transition sm:gap-1.5 sm:px-3 sm:py-2 sm:text-xs',
        active ? 'bg-white text-[#0a6a4d] shadow-sm' : 'text-white/70 hover:text-white',
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
      className="flex w-full items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-left backdrop-blur transition hover:bg-white/15 sm:rounded-2xl sm:px-3.5 sm:py-2.5"
    >
      <span className="min-w-0 flex-1">
        <span className="block text-[9px] font-semibold uppercase tracking-wide text-white/55 sm:text-[10px]">
          {eyebrow}
        </span>
        <span className="mt-0.5 block font-num text-sm tracking-wider sm:text-[15px]">{value}</span>
        <span className="mt-0.5 block truncate text-[10px] text-white/50">{hint}</span>
      </span>
      <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/15">
        {copied ? <CheckIcon size={14} className="text-emerald-200" /> : <CopyIcon size={14} />}
      </span>
    </button>
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
