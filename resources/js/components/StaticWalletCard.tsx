import type { FormEvent } from 'react'
import { useState } from 'react'
import { Link, router, useForm, usePage } from '@inertiajs/react'
import { DepositAccountBox } from '@/components/DepositAccountBox'
import { Button, Card, Pill } from '@/components/ui'
import { BankIcon, ShieldIcon } from '@/components/icons'
import { ngn } from '@/lib/format'
import type { KycProfile, PageProps, StaticAccount, Wallet } from '@/types'

type Props = {
  kyc: KycProfile
  staticAccount: StaticAccount | null
  wallet: Wallet | undefined
  compact?: boolean
  profileName?: string | null
}

export function StaticWalletCard({ kyc, staticAccount, wallet, compact = false, profileName }: Props) {
  const { auth, flash, errors } = usePage<PageProps>().props
  const [otp, setOtp] = useState('')
  const form = useForm({ wallet_id: wallet?.id ?? '' })

  if (!wallet) {
    return null
  }

  const resolvedName = profileName ?? auth.user?.name ?? null
  const walletTypeLabel = kyc.limits.static_wallet_type === 'individual' ? 'Personal' : 'Basic collection'
  const provisionError =
    (errors as Record<string, string> | undefined)?.wallet ??
    (errors as Record<string, string> | undefined)?.bvn ??
    (errors as Record<string, string> | undefined)?.kyc

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
      <DepositAccountBox
        staticAccount={staticAccount}
        profileName={resolvedName}
        walletTypeLabel={walletTypeLabel}
        compact={compact}
      />
    )
  }

  if (staticAccount?.status === 'pending_otp' || staticAccount?.needs_otp) {
    return (
      <Card className={`space-y-3 ${compact ? 'p-4' : 'p-5'}`}>
        <div className="flex flex-wrap items-center gap-2">
          <Pill tone="amber">OTP required</Pill>
          <p className="text-sm font-semibold">Activate your deposit account</p>
        </div>
        <p className="text-xs text-muted">Enter the one-time code sent to the phone on your BVN.</p>
        {flash.success && <p className="text-xs text-mint">{flash.success}</p>}
        <form onSubmit={verifyOtp} className="flex flex-wrap gap-2">
          <input
            value={otp}
            onChange={(e) => setOtp(e.target.value)}
            placeholder="Enter OTP"
            className="field min-w-[8rem] flex-1 px-3 py-2 text-sm"
            inputMode="numeric"
            autoComplete="one-time-code"
            aria-label="Deposit account OTP"
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
            Get a personal Reton deposit number for unlimited transfers. Your tier sets limits (
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
        <p className="rounded-lg border border-danger/20 bg-danger/5 px-3 py-2 text-xs text-danger">{provisionError}</p>
      )}

      <Button type="button" onClick={provision} loading={form.processing} className="w-full">
        {form.processing ? 'Opening account…' : 'Get my account number'}
      </Button>
    </Card>
  )
}
