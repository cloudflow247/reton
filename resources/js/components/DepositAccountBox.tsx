import { useState } from 'react'
import { router } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AlatMark } from '@/components/PoweredByAlat'
import { BankIcon, CheckIcon, CopyIcon, LockIcon, ShieldIcon } from '@/components/icons'
import { Button } from '@/components/ui'
import { cn } from '@/lib/utils'
import { ngn } from '@/lib/format'
import { shortFundingAccountName } from '@/lib/funding-account-name'
import type { KycProfile, StaticAccount } from '@/types'

type DepositAccountBoxProps = {
  staticAccount: StaticAccount
  profileName?: string | null
  walletTypeLabel?: string
  kyc?: KycProfile | null
  className?: string
  /** Compact single-row style for embeds */
  compact?: boolean
  showCheckDeposits?: boolean
}

export function DepositAccountBox({
  staticAccount,
  profileName,
  walletTypeLabel = 'Personal',
  kyc,
  className,
  compact = false,
  showCheckDeposits = true,
}: DepositAccountBoxProps) {
  const [copiedField, setCopiedField] = useState<string | null>(null)
  const [checking, setChecking] = useState(false)

  const bank = staticAccount.bank_name ?? 'Wema Bank'
  const accountNumber = staticAccount.account_number ?? ''
  const accountName = shortFundingAccountName(staticAccount.account_name, profileName)

  function copy(field: string, value: string) {
    if (!value) return
    void navigator.clipboard.writeText(value)
    setCopiedField(field)
    window.setTimeout(() => setCopiedField(null), 1400)
  }

  function copyAll() {
    const payload = [bank, accountName, accountNumber].filter(Boolean).join('\n')
    copy('all', payload)
  }

  function checkDeposits() {
    setChecking(true)
    router.post(
      '/add-money/check-deposits',
      {},
      {
        preserveScroll: true,
        onFinish: () => setChecking(false),
      },
    )
  }

  if (compact) {
    return (
      <motion.button
        type="button"
        initial={{ opacity: 0, y: 8 }}
        animate={{ opacity: 1, y: 0 }}
        onClick={() => copy('number', accountNumber)}
        className={cn(
          'flex w-full items-center gap-3 rounded-2xl border border-mint/25 bg-gradient-to-br from-mint/10 via-surface to-surface p-3.5 text-left transition hover:border-mint/40 active:scale-[0.99]',
          className,
        )}
      >
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-mint text-white shadow-sm">
          <BankIcon size={18} />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block text-[10px] font-bold uppercase tracking-wide text-muted">Bank fund · {bank}</span>
          <span className="mt-0.5 block font-num text-lg font-bold tracking-wider text-text">{accountNumber}</span>
          <span className="mt-0.5 block truncate text-xs font-medium text-muted">{accountName}</span>
        </span>
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-mint/10 text-mint">
          {copiedField === 'number' ? <CheckIcon size={15} /> : <CopyIcon size={15} />}
        </span>
      </motion.button>
    )
  }

  return (
    <motion.section
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }}
      className={cn(
        'relative overflow-hidden rounded-[24px] border border-mint/20 bg-surface shadow-[0_18px_40px_-28px_rgba(9,79,57,0.45)]',
        className,
      )}
      aria-label="Your deposit account"
    >
      <div className="relative overflow-hidden bg-gradient-to-br from-[#0a6a4d] via-[#0e7e5c] to-[#094f39] px-4 pb-4 pt-4 text-white sm:px-5 sm:pb-5 sm:pt-5">
        <div aria-hidden className="pointer-events-none absolute inset-0">
          <div className="absolute -right-12 -top-14 h-40 w-40 rounded-full bg-white/12 blur-3xl" />
          <div className="absolute -bottom-14 left-0 h-36 w-36 rounded-full bg-emerald-200/20 blur-3xl" />
        </div>

        <div className="relative flex items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <span className="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white">
                <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-200" />
                Active
              </span>
              <span className="rounded-full bg-black/20 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white/90">
                {walletTypeLabel}
              </span>
            </div>
            <h2 className="mt-2.5 font-display text-lg font-bold tracking-tight sm:text-xl">Your deposit account</h2>
            <p className="mt-1 text-xs leading-relaxed text-white/85 sm:text-[13px]">
              Transfer from any Nigerian bank — funds credit automatically.
            </p>
          </div>
          <AlatMark size={36} />
        </div>

        <button
          type="button"
          onClick={() => copy('number', accountNumber)}
          className="relative mt-4 flex w-full items-center gap-3 rounded-2xl border border-white/25 bg-white/12 px-3.5 py-3 text-left backdrop-blur-sm transition hover:bg-white/18 active:scale-[0.99]"
        >
          <span className="min-w-0 flex-1">
            <span className="block text-[10px] font-bold uppercase tracking-[0.14em] text-white/80">Account number</span>
            <span className="mt-1 block font-num text-[1.45rem] font-bold tracking-[0.14em] text-white sm:text-[1.65rem]">
              {accountNumber || '—'}
            </span>
            <span className="mt-1 block truncate text-xs font-medium text-white/85">
              {[bank, accountName].filter(Boolean).join(' · ')}
            </span>
          </span>
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-white">
            {copiedField === 'number' ? <CheckIcon size={16} className="text-emerald-100" /> : <CopyIcon size={16} />}
          </span>
        </button>
      </div>

      <div className="space-y-1 px-3 py-3 sm:px-4">
        <DetailRow
          label="Bank"
          value={bank}
          copied={copiedField === 'bank'}
          onCopy={() => copy('bank', bank)}
        />
        <DetailRow
          label="Account name"
          value={accountName}
          copied={copiedField === 'name'}
          onCopy={() => copy('name', accountName)}
        />
      </div>

      <div className="flex flex-wrap gap-2 border-t border-line/80 px-3 py-3 sm:px-4">
        <Button type="button" variant="ghost" className="flex-1 gap-1.5 text-xs" onClick={copyAll}>
          {copiedField === 'all' ? <CheckIcon size={14} /> : <CopyIcon size={14} />}
          {copiedField === 'all' ? 'Copied details' : 'Copy all details'}
        </Button>
        {showCheckDeposits && (
          <Button
            type="button"
            className="flex-1 gap-1.5 text-xs"
            loading={checking}
            onClick={checkDeposits}
          >
            <ShieldIcon size={14} />
            Check for deposits
          </Button>
        )}
      </div>

      <div className="flex items-start gap-2 border-t border-line/80 bg-surface-2/40 px-4 py-3">
        <LockIcon size={13} className="mt-0.5 shrink-0 text-mint" />
        <p className="text-[11px] leading-relaxed text-muted">
          Named to your profile only. Escrow and fraud checks still apply after credit
          {kyc ? ` · Tier ${kyc.tier} · up to ${ngn(kyc.limits.wallet_balance_max)}` : ''}.
        </p>
      </div>
    </motion.section>
  )
}

function DetailRow({
  label,
  value,
  copied,
  onCopy,
}: {
  label: string
  value: string
  copied: boolean
  onCopy: () => void
}) {
  return (
    <button
      type="button"
      onClick={onCopy}
      className="flex w-full items-center justify-between gap-3 rounded-xl px-2 py-2.5 text-left transition hover:bg-surface-2/70"
    >
      <span className="min-w-0">
        <span className="block text-[10px] font-bold uppercase tracking-wide text-muted">{label}</span>
        <span className="mt-0.5 block truncate text-sm font-semibold text-text">{value || '—'}</span>
      </span>
      <span className="inline-flex shrink-0 items-center gap-1 rounded-full border border-line bg-surface px-2.5 py-1 text-[11px] font-semibold text-muted">
        {copied ? <CheckIcon size={12} className="text-mint" /> : <CopyIcon size={12} />}
        {copied ? 'Copied' : 'Copy'}
      </span>
    </button>
  )
}
