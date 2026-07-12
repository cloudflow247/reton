import type { FormEvent, ReactNode } from 'react'
import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { AppShell } from '@/components/AppShell'
import { BvnVerificationGate } from '@/components/BvnVerificationGate'
import { FormPanel, Page, PageHero, SectionLabel } from '@/components/page-kit'
import { Button, Pill } from '@/components/ui'
import {
  BankIcon,
  BellIcon,
  CheckIcon,
  ChevronRightIcon,
  LockIcon,
  MailIcon,
  PhoneIcon,
  ShieldIcon,
  UndoIcon,
  UserIcon,
} from '@/components/icons'
import { ngn } from '@/lib/format'
import type { KycProfile, PageProps } from '@/types'

type ProfileProps = PageProps<{
  kyc: KycProfile
  bvnPendingOtp?: boolean
  bvnOtpHint?: string | null
  bvnProvider?: string
  bvnDemoMode?: boolean
  smsAlertFeeMinor?: number
}>

export default function Profile() {
  const {
    auth,
    flash,
    kyc,
    bvnPendingOtp,
    bvnOtpHint,
    bvnProvider,
    bvnDemoMode,
    smsAlertFeeMinor = 600,
  } = usePage<ProfileProps>().props
  const user = auth.user
  const wallet = auth.wallets[0]
  const initial = user?.name?.trim().charAt(0).toUpperCase() || '?'
  const smsFeeLabel = ngn(smsAlertFeeMinor)

  const tier3 = useForm({ nin: '', address_line1: '', city: '', state: '', identity_consent: false })
  const notifications = useForm({
    notify_email: user?.notify_email ?? true,
    notify_sms: user?.notify_sms ?? false,
  })

  function submitTier3(e: FormEvent) {
    e.preventDefault()
    tier3.post('/profile/kyc/tier-3', { preserveScroll: true })
  }

  function toggleEmail(next: boolean) {
    notifications.transform(() => ({
      notify_email: next,
      notify_sms: notifications.data.notify_sms,
    }))
    notifications.put('/profile/notifications', {
      preserveScroll: true,
      onSuccess: () => notifications.setData('notify_email', next),
    })
  }

  function toggleSms(next: boolean) {
    if (next) {
      const ok = window.confirm(
        `SMS alerts cost ${smsFeeLabel} per message, deducted from your Reton wallet when an alert is sent. Continue?`,
      )
      if (!ok) return
    }

    notifications.transform(() => ({
      notify_email: notifications.data.notify_email,
      notify_sms: next,
    }))
    notifications.put('/profile/notifications', {
      preserveScroll: true,
      onSuccess: () => notifications.setData('notify_sms', next),
    })
  }

  return (
    <Page narrow>
      <Head title="Profile" />
      <PageHero
        icon={UserIcon}
        title="Profile"
        subtitle="Identity, KYC, and settings"
        tone="slate"
      />

      {flash.success && (
        <p className="rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-sm text-mint">{flash.success}</p>
      )}
      {flash.error && (
        <p className="rounded-xl border border-danger/25 bg-danger/5 px-4 py-2.5 text-sm text-danger">{flash.error}</p>
      )}

      <FormPanel>
        <div className="flex items-center gap-4">
          <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-mint to-mint-strong font-display text-2xl font-bold text-white shadow-sm">
            {initial}
          </div>
          <div className="min-w-0 flex-1">
            <div className="truncate font-display text-lg font-semibold leading-tight">{user?.name ?? '—'}</div>
            <div className="mt-0.5 flex items-center gap-1.5 truncate text-sm text-muted">
              <MailIcon size={14} className="shrink-0" />
              <span className="truncate">{user?.email ?? '—'}</span>
            </div>
            {user?.phone && (
              <div className="mt-0.5 flex items-center gap-1.5 truncate text-sm text-muted">
                <PhoneIcon size={14} className="shrink-0" />
                <span className="truncate font-num tracking-wide">{user.phone}</span>
              </div>
            )}
          </div>
        </div>

        <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-line pt-4">
          <VerifyPill ok={!!user?.email_verified} label="Email" />
          <VerifyPill ok={!!user?.phone_verified} label="Phone" />
          {!user?.email_verified && (
            <button
              type="button"
              onClick={() => router.post('/email/verification-notification', {}, { preserveScroll: true })}
              className="text-xs font-semibold text-mint hover:underline"
            >
              Resend verification email
            </button>
          )}
          {user?.has_transaction_pin ? (
            <Pill tone="mint">
              <CheckIcon size={12} /> PIN set
            </Pill>
          ) : (
            <Pill tone="amber">
              <LockIcon size={12} /> No PIN
            </Pill>
          )}
        </div>
      </FormPanel>

      <SectionLabel>KYC verification</SectionLabel>
      <FormPanel className="space-y-4">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="text-sm font-semibold">
                Tier {kyc.tier} · {kyc.tier_label}
              </p>
              <p className="mt-1 text-xs text-muted">
                Deposit account:{' '}
                <span className="font-medium text-text">
                  {kyc.limits.static_wallet_type === 'individual' ? 'Personal account' : 'Collection account'}
                </span>
              </p>
            </div>
            <Pill tone={kyc.tier >= 3 ? 'mint' : kyc.tier === 2 ? 'muted' : 'amber'}>Tier {kyc.tier}</Pill>
          </div>

          <ul className="grid gap-2 text-xs text-muted sm:grid-cols-3">
            <li className="rounded-lg bg-surface-2 px-3 py-2">
              <span className="block text-[10px] uppercase tracking-wide">Per transaction</span>
              <span className="font-num font-semibold text-text">{ngn(kyc.limits.single_transaction_max)}</span>
            </li>
            <li className="rounded-lg bg-surface-2 px-3 py-2">
              <span className="block text-[10px] uppercase tracking-wide">Daily inflow</span>
              <span className="font-num font-semibold text-text">{ngn(kyc.limits.daily_inflow_max)}</span>
            </li>
            <li className="rounded-lg bg-surface-2 px-3 py-2">
              <span className="block text-[10px] uppercase tracking-wide">Balance cap</span>
              <span className="font-num font-semibold text-text">{ngn(kyc.limits.wallet_balance_max)}</span>
            </li>
          </ul>

          {kyc.tier >= 2 && kyc.bvn_last4 && (
            <p className="text-xs text-muted">
              BVN verified · ends {kyc.bvn_last4}
              {kyc.date_of_birth ? ` · DOB ${kyc.date_of_birth}` : ''}
            </p>
          )}

          {kyc.tier >= 3 && kyc.nin_last4 && (
            <p className="text-xs text-muted">
              NIN verified · ends {kyc.nin_last4}
              {kyc.address_line1 ? ` · ${kyc.address_line1}, ${kyc.city}` : ''}
            </p>
          )}

          {(kyc.tier === 1 || bvnPendingOtp) && (
            <div className="border-t border-line pt-4">
              <BvnVerificationGate
                returnTo="/profile"
                pendingOtp={bvnPendingOtp}
                otpHint={bvnOtpHint}
                provider={bvnProvider}
                demoMode={bvnDemoMode}
              />
            </div>
          )}

          {kyc.tier === 2 && (
            <form onSubmit={submitTier3} className="space-y-3 border-t border-line pt-4">
              <p className="text-sm font-semibold text-text">Upgrade to Tier 3 — NIN & address</p>
              <p className="text-xs text-muted">Unlock the highest limits for funding, sending, and marketplace sales.</p>
              <input
                className="field w-full px-3 py-2.5 text-sm"
                placeholder="11-digit NIN"
                inputMode="numeric"
                maxLength={11}
                value={tier3.data.nin}
                onChange={(e) => tier3.setData('nin', e.target.value.replace(/\D/g, '').slice(0, 11))}
              />
              {tier3.errors.nin && <p className="text-xs text-danger">{tier3.errors.nin}</p>}
              <input
                className="field w-full px-3 py-2.5 text-sm"
                placeholder="Street address"
                value={tier3.data.address_line1}
                onChange={(e) => tier3.setData('address_line1', e.target.value)}
              />
              <div className="grid gap-2 sm:grid-cols-2">
                <input
                  className="field w-full px-3 py-2.5 text-sm"
                  placeholder="City"
                  value={tier3.data.city}
                  onChange={(e) => tier3.setData('city', e.target.value)}
                />
                <input
                  className="field w-full px-3 py-2.5 text-sm"
                  placeholder="State"
                  value={tier3.data.state}
                  onChange={(e) => tier3.setData('state', e.target.value)}
                />
              </div>
              {(tier3.errors.address_line1 || tier3.errors.city || tier3.errors.state) && (
                <p className="text-xs text-danger">
                  {tier3.errors.address_line1 || tier3.errors.city || tier3.errors.state}
                </p>
              )}
              <label className="flex items-start gap-2 text-xs leading-relaxed text-muted">
                <input
                  type="checkbox"
                  className="mt-0.5 rounded border-line"
                  checked={tier3.data.identity_consent}
                  onChange={(e) => tier3.setData('identity_consent', e.target.checked)}
                />
                I consent to NIN verification. Reton logs verification outcomes for audit — never your full NIN in
                application logs.
              </label>
              {tier3.errors.identity_consent && <p className="text-xs text-danger">{tier3.errors.identity_consent}</p>}
              <Button type="submit" loading={tier3.processing} className="w-full">
                Complete full KYC
              </Button>
            </form>
          )}

          {kyc.tier >= 3 && (
            <p className="flex items-center gap-2 border-t border-line pt-4 text-sm text-mint">
              <CheckIcon size={16} /> You have full KYC — highest limits apply.
            </p>
          )}
      </FormPanel>

      <SectionLabel>Wallet</SectionLabel>
      <FormPanel className="divide-y divide-line !space-y-0 !p-0">
          <Row
            icon={<BankIcon size={18} />}
            label="RETON ID"
            value={wallet?.account_number ?? 'Pending'}
            mono
          />
          <LinkRow
            href="/add-money"
            icon={<ShieldIcon size={18} />}
            label="Deposit account"
            sub="Permanent bank deposit number"
            action="Manage"
          />
      </FormPanel>

      <SectionLabel>Notifications</SectionLabel>
      <FormPanel className="!space-y-0 !p-0">
        <p className="border-b border-line px-5 py-3 text-xs leading-relaxed text-muted">
          Transaction alerts come from Reton only — not from your bank. Email is free. SMS is charged per alert.
        </p>
        <PreferenceToggle
          icon={<MailIcon size={18} />}
          label="Email alerts"
          sub="Credits, debits, and account notices — free"
          checked={notifications.data.notify_email}
          disabled={notifications.processing}
          onChange={toggleEmail}
          error={notifications.errors.notify_email}
        />
        <PreferenceToggle
          icon={<BellIcon size={18} />}
          label="SMS alerts"
          sub={`Off by default · ${smsFeeLabel} per SMS when enabled`}
          checked={notifications.data.notify_sms}
          disabled={notifications.processing || !user?.phone}
          onChange={toggleSms}
          error={notifications.errors.notify_sms}
        />
        {!user?.phone && (
          <p className="border-t border-line px-5 py-3 text-xs text-amber">
            Add a phone number to your account before enabling SMS alerts.
          </p>
        )}
      </FormPanel>

      <SectionLabel>Security</SectionLabel>
      <FormPanel className="divide-y divide-line !space-y-0 !p-0">
          <LinkRow
            href="/pin"
            icon={<LockIcon size={18} />}
            label="Transaction PIN"
            sub={user?.has_transaction_pin ? 'Required for every payment' : 'Not set up yet'}
            action={user?.has_transaction_pin ? 'Change' : 'Set up'}
          />
      </FormPanel>

      <Button variant="danger" className="w-full" onClick={() => router.post('/logout')}>
        <UndoIcon size={16} /> Sign out
      </Button>

      <p className="flex items-center justify-center gap-1.5 text-center text-xs text-muted">
        <ShieldIcon size={13} /> Your data is encrypted and protected.
      </p>
    </Page>
  )
}

Profile.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function VerifyPill({ ok, label }: { ok: boolean; label: string }) {
  return ok ? (
    <Pill tone="mint">
      <CheckIcon size={12} /> {label} verified
    </Pill>
  ) : (
    <Pill tone="amber">{label} unverified</Pill>
  )
}

function PreferenceToggle({
  icon,
  label,
  sub,
  checked,
  disabled,
  onChange,
  error,
}: {
  icon: ReactNode
  label: string
  sub: string
  checked: boolean
  disabled?: boolean
  onChange: (next: boolean) => void
  error?: string
}) {
  return (
    <div className="flex items-start gap-3 border-t border-line px-5 py-4 first:border-t-0">
      <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-mint/10 text-mint">
        {icon}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-medium text-text">{label}</span>
        <span className="block text-xs text-muted">{sub}</span>
        {error && <span className="mt-1 block text-xs text-danger">{error}</span>}
      </span>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={`relative h-7 w-12 shrink-0 rounded-full transition ${
          checked ? 'bg-mint' : 'bg-line'
        } disabled:opacity-50`}
      >
        <span
          className={`absolute top-0.5 left-0.5 size-6 rounded-full bg-white shadow transition ${
            checked ? 'translate-x-5' : 'translate-x-0'
          }`}
        />
      </button>
    </div>
  )
}

function Row({
  icon,
  label,
  value,
  mono,
}: {
  icon: ReactNode
  label: string
  value: ReactNode
  mono?: boolean
}) {
  return (
    <div className="flex items-center gap-3 px-5 py-4">
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-mint/10 text-mint">
        {icon}
      </span>
      <span className="flex-1 text-sm text-muted">{label}</span>
      <span className={`text-sm font-semibold ${mono ? 'font-num tracking-wider' : ''}`}>{value}</span>
    </div>
  )
}

function LinkRow({
  href,
  icon,
  label,
  sub,
  action,
}: {
  href: string
  icon: ReactNode
  label: string
  sub: string
  action: string
}) {
  return (
    <Link href={href} className="flex items-center gap-3 px-5 py-4 transition hover:bg-surface-2/60">
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-mint/10 text-mint">
        {icon}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-medium text-text">{label}</span>
        <span className="block truncate text-xs text-muted">{sub}</span>
      </span>
      <span className="text-sm font-semibold text-mint">{action}</span>
      <ChevronRightIcon size={16} className="text-muted" />
    </Link>
  )
}
