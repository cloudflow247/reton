import type { ReactNode } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { AppShell } from '@/components/AppShell'
import { Button, Card, Pill } from '@/components/ui'
import { CheckIcon, ChevronRightIcon, LockIcon, ShieldIcon, UserIcon } from '@/components/icons'
import type { SharedProps } from '@/types'

export default function Profile() {
  const { auth } = usePage<SharedProps>().props
  const user = auth.user
  const wallet = auth.wallets[0]

  return (
    <div className="mx-auto max-w-lg space-y-5">
      <Head title="Profile" />
      <h1 className="font-display text-2xl font-bold tracking-tight">Profile</h1>

      <Card>
        <div className="flex items-center gap-4">
          <div className="flex h-14 w-14 items-center justify-center rounded-full bg-mint/12 font-display text-xl font-bold text-mint">
            {user?.name?.charAt(0) ?? '?'}
          </div>
          <div className="min-w-0">
            <div className="truncate font-display text-lg font-semibold">{user?.name ?? '—'}</div>
            <div className="truncate text-sm text-muted">{user?.email}</div>
          </div>
          {user?.email_verified ? (
            <Pill tone="mint">
              <CheckIcon size={12} /> Verified
            </Pill>
          ) : (
            <Pill tone="amber">Unverified</Pill>
          )}
        </div>
      </Card>

      <Card className="divide-y divide-line p-0">
        <Row icon={<UserIcon size={17} />} label="Account number" value={wallet?.account_number ?? '—'} mono />
        <Row icon={<UserIcon size={17} />} label="Phone" value={user?.phone ?? '—'} />
        <Row icon={<ShieldIcon size={17} />} label="KYC tier" value={<Pill tone="amber">Tier 1 · basic</Pill>} />
        <Link href="/pin" className="flex items-center gap-3 px-5 py-4 transition hover:bg-surface-2/60">
          <span className="text-muted">
            <LockIcon size={17} />
          </span>
          <span className="flex-1 text-sm text-muted">Transaction PIN</span>
          <span className="text-sm font-medium text-mint">{user?.has_transaction_pin ? 'Change' : 'Set up'}</span>
          <ChevronRightIcon size={16} className="text-muted" />
        </Link>
      </Card>

      <Button variant="danger" className="w-full" onClick={() => router.post('/logout')}>
        Sign out
      </Button>

      <p className="flex items-center justify-center gap-1.5 text-center text-xs text-muted">
        <LockIcon size={13} /> Your data is encrypted and protected.
      </p>
    </div>
  )
}

Profile.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

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
      <span className="text-muted">{icon}</span>
      <span className="flex-1 text-sm text-muted">{label}</span>
      <span className={`text-sm font-medium ${mono ? 'font-num tracking-wider' : ''}`}>{value}</span>
    </div>
  )
}
