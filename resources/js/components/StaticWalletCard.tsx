import type { FormEvent } from 'react'
import { useState } from 'react'
import { Link, router, useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { Button, Card, CopyRow, Pill } from '@/components/ui'
import { AlatMark } from '@/components/PoweredByAlat'
import { BankIcon, ShieldIcon } from '@/components/icons'
import { ngn } from '@/lib/format'
import type { KycProfile, PageProps, StaticAccount, Wallet } from '@/types'

type Props = {
  kyc: KycProfile
  staticAccount: StaticAccount | null
  wallet: Wallet | undefined
  compact?: boolean
}

export function StaticWalletCard({ kyc, staticAccount, wallet, compact = false }: Props) {
  const { flash, errors } = usePage<PageProps>().props
  const [otp, setOtp] = useState('')
  const form = useForm({ wallet_id: wallet?.id ?? '' })

  if (!wallet) {
    return null
  }

  const walletTypeLabel = kyc.limits.static_wallet_type === 'individual' ? 'Personal' : 'Basic collection'
  const provisionError = (errors as Record<string, string> | undefined)?.wallet
    ?? (errors as Record<string, string> | undefined)?.bvn
    ?? (errors as Record<string, string> | undefined)?.kyc

  function provision() {
    form.post('/static-account', { preserveScroll: true })
  }

  function verifyOtp(e: FormEvent) {
    e.preventDefault()
    if (!staticAccount) return
    router.post(`/static-account/${staticAccount.id}/verify`, { otp }, { preserveScroll: true })
  }

  if (staticAccount?.status === 'active' && staticAccount.account_number) {
    return (
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }}
      >
        <Card className={`overflow-hidden ${compact ? 'p-0' : 'p-0'}`}>
          <div className="bg-gradient-to-br from-mint/15 via-mint/[0.06] to-transparent px-5 pb-4 pt-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="min-w-0">
                <div className="mb-1.5 flex flex-wrap items-center gap-2">
                  <Pill tone="mint">Active</Pill>
                  <span className="text-[11px] font-medium text-muted">{walletTypeLabel} account</span>
                </div>
                <p className="text-base font-semibold tracking-tight text-text">Your deposit account</p>
                <p className="mt-0.5 text-xs text-muted">
                  Transfer from any Nigerian bank — funds credit your Reton wallet automatically.
                </p>
              </div>
              <AlatMark size={40} />
            </div>
          </div>

          <div className="divide-y divide-line/80 px-5">
            <CopyRow label="Bank" value={staticAccount.bank_name ?? 'ALAT by Wema'} />
            <CopyRow label="Account name" value={staticAccount.account_name ?? '—'} />
            <div className="py-4">
              <p className="text-xs text-muted">Account number</p>
              <div className="mt-1 flex items-center justify-between gap-3">
                <p className="font-num text-2xl font-semibold tracking-[0.12em] text-text sm:text-[1.65rem]">
                  {staticAccount.account_number}
                </p>
                <CopyOnly value={staticAccount.account_number} />
              </div>
            </div>
          </div>

          <p className="border-t border-line/80 px-5 py-3 text-[11px] leading-relaxed text-muted">
            Tier {kyc.tier} · up to {ngn(kyc.limits.wallet_balance_max)} balance. Escrow and fraud checks still apply after credit.
          </p>
        </Card>
      </motion.div>
    )
  }

  if (staticAccount?.status === 'pending_otp' || staticAccount?.needs_otp) {
    return (
      <Card className={`space-y-3 ${compact ? 'p-4' : 'p-5'}`}>
        <p className="text-sm font-semibold">Activate your deposit account</p>
        <p className="text-xs text-muted">
          Enter the OTP ALATPay sent to the phone on your BVN.
        </p>
        {flash.success && <p className="text-xs text-mint">{flash.success}</p>}
        <form onSubmit={verifyOtp} className="flex flex-wrap gap-2">
          <input
            value={otp}
            onChange={(e) => setOtp(e.target.value)}
            placeholder="Enter OTP"
            className="field min-w-[8rem] flex-1 px-3 py-2 text-sm"
            inputMode="numeric"
            autoComplete="one-time-code"
          />
          <Button type="submit">Verify</Button>
        </form>
      </Card>
    )
  }

  return (
    <Card className={`space-y-4 ${compact ? 'p-4' : 'p-5'}`}>
      <div className="flex items-start gap-3">
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-mint/10 text-mint">
          <BankIcon size={20} />
        </span>
        <div>
          <p className="text-sm font-semibold text-text">Permanent bank account</p>
          <p className="mt-1 text-xs leading-relaxed text-muted">
            Get a personal ALATPay number for unlimited transfers. Your tier sets limits (
            {ngn(kyc.limits.wallet_balance_max)} balance cap).
          </p>
        </div>
      </div>

      {kyc.tier === 1 && (
        <p className="rounded-lg border border-amber/25 bg-amber/5 px-3 py-2 text-xs text-muted">
          <ShieldIcon size={12} className="mr-1 inline text-amber" />
          Tier 1 uses a collection account.{' '}
          <Link href="/profile" className="font-semibold text-mint hover:underline">
            Verify BVN
          </Link>{' '}
          for a personal named account (Tier 2).
        </p>
      )}

      {provisionError && (
        <p className="rounded-lg border border-danger/20 bg-danger/5 px-3 py-2 text-xs text-danger">
          {provisionError}
        </p>
      )}

      <Button type="button" onClick={provision} loading={form.processing} className="w-full">
        {form.processing ? 'Opening account…' : 'Get my account number'}
      </Button>
    </Card>
  )
}

function CopyOnly({ value }: { value: string }) {
  const [copied, setCopied] = useState(false)

  return (
    <button
      type="button"
      onClick={() => {
        void navigator.clipboard.writeText(value)
        setCopied(true)
        window.setTimeout(() => setCopied(false), 1400)
      }}
      className="shrink-0 rounded-full border border-mint/30 bg-mint/10 px-3.5 py-1.5 text-xs font-semibold text-mint transition hover:bg-mint/15"
    >
      {copied ? 'Copied' : 'Copy'}
    </button>
  )
}
