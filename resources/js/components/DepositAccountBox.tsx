import { useState } from 'react'
import { router } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { BankIcon, CheckIcon, CopyIcon, RefreshIcon, ShieldIcon } from '@/components/icons'
import { cn } from '@/lib/utils'
import { shortFundingAccountName } from '@/lib/funding-account-name'
import type { StaticAccount } from '@/types'

type DepositAccountBoxProps = {
  staticAccount: StaticAccount
  profileName?: string | null
  walletTypeLabel?: string
  className?: string
  compact?: boolean
  showCheckDeposits?: boolean
}

export function DepositAccountBox({
  staticAccount,
  profileName,
  walletTypeLabel = 'Personal',
  className,
  compact = false,
  showCheckDeposits = true,
}: DepositAccountBoxProps) {
  const [copied, setCopied] = useState(false)
  const [checking, setChecking] = useState(false)

  const bank = staticAccount.bank_name ?? 'Wema Bank'
  const accountNumber = staticAccount.account_number ?? ''
  const accountName = shortFundingAccountName(staticAccount.account_name, profileName)

  function copyNumber() {
    if (!accountNumber) return
    void navigator.clipboard.writeText(accountNumber)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 1400)
  }

  function copyAll() {
    const payload = [bank, accountName, accountNumber].filter(Boolean).join('\n')
    void navigator.clipboard.writeText(payload)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 1400)
  }

  function checkDeposits() {
    setChecking(true)
    router.post('/add-money/check-deposits', {}, {
      preserveScroll: true,
      onFinish: () => setChecking(false),
    })
  }

  if (compact) {
    return (
      <motion.button
        type="button"
        initial={{ opacity: 0, y: 6 }}
        animate={{ opacity: 1, y: 0 }}
        onClick={copyNumber}
        className={cn(
          'flex w-full items-center gap-3 rounded-2xl border border-line bg-surface p-3.5 text-left transition hover:border-mint/35 active:scale-[0.99]',
          className,
        )}
      >
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-mint text-white">
          <BankIcon size={18} />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block text-[10px] font-semibold uppercase tracking-wide text-muted">{bank}</span>
          <span className="mt-0.5 block font-num text-lg font-bold tracking-wider text-text">{accountNumber}</span>
          <span className="mt-0.5 block truncate text-xs text-muted">{accountName}</span>
        </span>
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-mint/10 text-mint">
          {copied ? <CheckIcon size={15} /> : <CopyIcon size={15} />}
        </span>
      </motion.button>
    )
  }

  return (
    <motion.section
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
      className={cn(
        'overflow-hidden rounded-2xl border border-line bg-surface shadow-[0_12px_28px_-20px_rgba(9,79,57,0.35)]',
        className,
      )}
      aria-label="Your deposit account"
    >
      <div className="flex items-center justify-between gap-2 border-b border-line/70 px-4 py-2.5">
        <div className="flex min-w-0 items-center gap-2">
          <span className="inline-flex items-center gap-1.5 text-[11px] font-semibold text-mint">
            <span className="h-1.5 w-1.5 rounded-full bg-mint" />
            Active
          </span>
          <span className="text-[11px] text-muted">·</span>
          <span className="truncate text-[11px] font-medium text-muted">{walletTypeLabel} account</span>
        </div>
        {showCheckDeposits && (
          <button
            type="button"
            onClick={checkDeposits}
            disabled={checking}
            className="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-semibold text-mint transition hover:bg-mint/10 disabled:opacity-60"
          >
            <RefreshIcon size={12} className={checking ? 'animate-spin' : undefined} />
            {checking ? 'Checking…' : 'Check'}
          </button>
        )}
      </div>

      <button
        type="button"
        onClick={copyNumber}
        className="flex w-full items-center gap-3 px-4 py-4 text-left transition hover:bg-surface-2/50 active:scale-[0.995]"
      >
        <span className="min-w-0 flex-1">
          <span className="block text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">Account number</span>
          <span className="mt-1 block font-num text-[1.55rem] font-bold tracking-[0.12em] text-text">
            {accountNumber || '-'}
          </span>
          <span className="mt-1 block truncate text-xs text-muted">
            {bank} · {accountName}
          </span>
        </span>
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-mint/10 text-mint">
          {copied ? <CheckIcon size={16} /> : <CopyIcon size={16} />}
        </span>
      </button>

      <div className="grid grid-cols-2 gap-2 border-t border-line/70 px-3 py-3">
        <ActionChip label="Copy number" onClick={copyNumber} icon={copied ? CheckIcon : CopyIcon} />
        <ActionChip label="Copy all" onClick={copyAll} icon={ShieldIcon} />
      </div>
    </motion.section>
  )
}

function ActionChip({
  label,
  onClick,
  icon: Icon,
}: {
  label: string
  onClick: () => void
  icon: typeof CopyIcon
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-line bg-surface-2/40 px-3 py-2 text-xs font-semibold text-text transition hover:border-mint/30 hover:bg-mint/5"
    >
      <Icon size={13} className="text-mint" />
      {label}
    </button>
  )
}
