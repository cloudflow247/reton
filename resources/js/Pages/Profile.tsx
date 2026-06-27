import type { ReactNode } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { Button, Card, Pill } from '@/components/ui'
import {
  BankIcon,
  CheckIcon,
  ChevronRightIcon,
  LockIcon,
  MailIcon,
  PhoneIcon,
  ShieldIcon,
  UndoIcon,
} from '@/components/icons'
import type { SharedProps } from '@/types'

export default function Profile() {
  const { auth } = usePage<SharedProps>().props
  const user = auth.user
  const wallet = auth.wallets[0]
  const initial = user?.name?.trim().charAt(0).toUpperCase() || '?'

  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.32, ease: [0.22, 1, 0.36, 1] }}
      className="mx-auto max-w-lg space-y-5"
    >
      <Head title="Profile" />
      <h1 className="font-display text-2xl font-bold tracking-tight">Profile</h1>

      {/* Identity card */}
      <Card className="shield-glow">
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

        {/* Verification chips */}
        <div className="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
          <VerifyPill ok={!!user?.email_verified} label="Email" />
          <VerifyPill ok={!!user?.phone_verified} label="Phone" />
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
      </Card>

      {/* Wallet details */}
      <div>
        <h2 className="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-muted">Wallet</h2>
        <Card className="divide-y divide-line p-0">
          <Row
            icon={<BankIcon size={18} />}
            label="Account number"
            value={wallet?.account_number ?? 'Pending'}
            mono
          />
          <Row
            icon={<ShieldIcon size={18} />}
            label="KYC tier"
            value={<Pill tone="amber">Tier 1 · basic</Pill>}
          />
        </Card>
      </div>

      {/* Security & settings */}
      <div>
        <h2 className="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-muted">Security</h2>
        <Card className="divide-y divide-line p-0">
          <LinkRow
            href="/pin"
            icon={<LockIcon size={18} />}
            label="Transaction PIN"
            sub={user?.has_transaction_pin ? 'Required for every payment' : 'Not set up yet'}
            action={user?.has_transaction_pin ? 'Change' : 'Set up'}
          />
        </Card>
      </div>

      <Button variant="danger" className="w-full" onClick={() => router.post('/logout')}>
        <UndoIcon size={16} /> Sign out
      </Button>

      <p className="flex items-center justify-center gap-1.5 text-center text-xs text-muted">
        <ShieldIcon size={13} /> Your data is encrypted and protected.
      </p>
    </motion.div>
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
