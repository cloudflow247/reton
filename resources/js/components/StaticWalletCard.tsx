import type { FormEvent } from 'react'
import { useState } from 'react'
import { Link, router, useForm, usePage } from '@inertiajs/react'
import { Button, Card, CopyRow, Pill } from '@/components/ui'
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
  const { flash } = usePage<PageProps>().props
  const [otp, setOtp] = useState('')
  const form = useForm({ wallet_id: wallet?.id ?? '' })

  if (!wallet) {
    return null
  }

  const tierLabel = `Tier ${kyc.tier} · ${kyc.tier_label}`
  const walletTypeLabel = kyc.limits.static_wallet_type === 'individual' ? 'Personal' : 'Basic collection'

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
      <Card className={`space-y-3 ${compact ? 'p-4' : 'p-5'}`}>
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div>
            <p className="text-sm font-semibold text-text">Permanent deposit account</p>
            <p className="text-xs text-muted">
              {walletTypeLabel} · {tierLabel} — transfer any amount, anytime
            </p>
          </div>
          <Pill tone="mint">Active</Pill>
        </div>
        <CopyRow label="Bank" value={staticAccount.bank_name ?? 'ALAT by Wema'} />
        <CopyRow label="Account name" value={staticAccount.account_name ?? '—'} />
        <CopyRow label="Account number" value={staticAccount.account_number} mono />
        <p className="text-[11px] leading-relaxed text-muted">
          Funds land in your Reton wallet automatically. Escrow, holds, and limits still apply after credit.
        </p>
      </Card>
    )
  }

  if (staticAccount?.status === 'pending_otp' || staticAccount?.needs_otp) {
    return (
      <Card className={`space-y-3 ${compact ? 'p-4' : 'p-5'}`}>
        <p className="text-sm font-semibold">Activate your deposit account</p>
        <p className="text-xs text-muted">
          ALATPay sent an OTP to your registered phone. Demo OTP: <span className="font-mono">123456</span>
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
    <Card className={`space-y-3 ${compact ? 'p-4' : 'p-5'}`}>
      <div className="flex items-start gap-3">
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-mint/10 text-mint">
          <BankIcon size={20} />
        </span>
        <div>
          <p className="text-sm font-semibold text-text">Get a permanent bank number</p>
          <p className="mt-1 text-xs text-muted">
            ALATPay static wallet ({walletTypeLabel}) — no fixed amount, no expiry. Your tier sets limits (
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

      <Button type="button" onClick={provision} loading={form.processing} className="w-full">
        Open deposit account
      </Button>
    </Card>
  )
}
