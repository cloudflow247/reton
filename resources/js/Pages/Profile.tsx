import type { FormEvent, ReactNode } from 'react'
import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { AppShell } from '@/components/AppShell'
import { FormPanel, Page, PageHero, SectionLabel } from '@/components/page-kit'
import { Button, Pill } from '@/components/ui'
import {
  BankIcon,
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
}>

export default function Profile() {
  const { auth, flash, kyc } = usePage<ProfileProps>().props
  const user = auth.user
  const wallet = auth.wallets[0]
  const initial = user?.name?.trim().charAt(0).toUpperCase() || '?'

  const tier2 = useForm({ bvn: '', date_of_birth: '', identity_consent: false })
  const tier3 = useForm({ nin: '', address_line1: '', city: '', state: '', identity_consent: false })

  function submitTier2(e: FormEvent) {
    e.preventDefault()
    tier2.post('/profile/kyc/tier-2', { preserveScroll: true })
  }

  function submitTier3(e: FormEvent) {
    e.preventDefault()
    tier3.post('/profile/kyc/tier-3', { preserveScroll: true })
  }

  return (
    <Page narrow>
      <Head title="Profile" />
      <PageHero
        icon={UserIcon}
        title="Profile"
        subtitle="Your identity, verification tier, and account settings."
        tone="slate"
      />

      {flash.success && (
        <p className="rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-sm text-mint">{flash.success}</p>
      )}

      <FormPanel className="shield-glow">
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
                ALATPay static wallet:{' '}
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

          {kyc.tier === 1 && (
            <form onSubmit={submitTier2} className="space-y-3 border-t border-line pt-4">
              <p className="text-sm font-semibold text-text">Upgrade to Tier 2 — BVN</p>
              <p className="text-xs text-muted">
                Required for a personal ALATPay static account. BVN is encrypted and only sent to ALATPay for
                provisioning.
              </p>
              <input
                className="field w-full px-3 py-2.5 text-sm"
                placeholder="11-digit BVN"
                inputMode="numeric"
                maxLength={11}
                value={tier2.data.bvn}
                onChange={(e) => tier2.setData('bvn', e.target.value.replace(/\D/g, '').slice(0, 11))}
              />
              {tier2.errors.bvn && <p className="text-xs text-danger">{tier2.errors.bvn}</p>}
              <input
                type="date"
                className="field w-full px-3 py-2.5 text-sm"
                value={tier2.data.date_of_birth}
                onChange={(e) => tier2.setData('date_of_birth', e.target.value)}
              />
              {tier2.errors.date_of_birth && <p className="text-xs text-danger">{tier2.errors.date_of_birth}</p>}
              <label className="flex items-start gap-2 text-xs leading-relaxed text-muted">
                <input
                  type="checkbox"
                  className="mt-0.5 rounded border-line"
                  checked={tier2.data.identity_consent}
                  onChange={(e) => tier2.setData('identity_consent', e.target.checked)}
                />
                I consent to Reton verifying my BVN with ALATPay under NDPR. ALATPay will send an OTP to the phone
                linked to my BVN. My number is encrypted and never stored in plain text.
              </label>
              {tier2.errors.identity_consent && <p className="text-xs text-danger">{tier2.errors.identity_consent}</p>}
              <Button type="submit" loading={tier2.processing} className="w-full">
                Verify BVN
              </Button>
            </form>
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
                I consent to NIN verification with Dojah. Reton logs verification outcomes for audit — never your
                full NIN in application logs.
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
            label="Reton account"
            value={wallet?.account_number ?? 'Pending'}
            mono
          />
          <LinkRow
            href="/add-money"
            icon={<ShieldIcon size={18} />}
            label="Deposit account"
            sub="Permanent ALATPay bank number"
            action="Manage"
          />
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
