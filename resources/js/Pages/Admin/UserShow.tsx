import type { ReactNode } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { AdminLayout, AdminPanel, AdminTable } from '@/components/AdminLayout'
import { Card, Pill } from '@/components/ui'
import { useAdminBase } from '@/lib/admin'
import { ngn, shortDate } from '@/lib/format'
import type { PageProps } from '@/types'

type DeskProps = PageProps<{
  user: {
    id: string
    name: string
    email: string
    phone: string | null
    status: string
    is_admin: boolean
    email_verified: boolean
    has_pin: boolean
    last_login_at: string | null
    created_at: string | null
  }
  wallets: Array<{
    id: string
    currency: string
    balance: number
    held_balance: number
    available: number
    status: string
  }>
  kyc: {
    tier: number | string
    bvn_last4: string | null
    nin_last4: string | null
    bvn_verified_at: string | null
    nin_verified_at: string | null
    city: string | null
    state: string | null
  } | null
  recent_transfers: Array<{
    id: string
    reference: string
    type: string
    status: string
    amount: number
    currency: string
    created_at: string | null
  }>
}>

export default function UserShow() {
  const { user, wallets, kyc, recent_transfers } = usePage<DeskProps>().props
  const adminBase = useAdminBase()

  return (
    <>
      <Head title={`User · ${user.name}`} />

      <AdminPanel
        title={user.name}
        subtitle={user.email}
        actions={
          <Link href={`${adminBase}/users`} className="text-xs font-semibold text-mint hover:underline">
            ← All users
          </Link>
        }
      >
        <div className="grid gap-4 lg:grid-cols-3">
          <Card className="space-y-3 p-5 lg:col-span-1">
            <h2 className="text-xs font-bold uppercase tracking-[0.14em] text-muted">Account</h2>
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between gap-3">
                <dt className="text-muted">Status</dt>
                <dd>
                  <Pill tone={user.status === 'active' ? 'mint' : user.status === 'suspended' ? 'amber' : 'danger'}>
                    {user.status}
                  </Pill>
                </dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-muted">Role</dt>
                <dd>{user.is_admin ? 'Admin' : 'Customer'}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-muted">Phone</dt>
                <dd>{user.phone ?? '—'}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-muted">Email</dt>
                <dd>{user.email_verified ? 'Verified' : 'Unverified'}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-muted">PIN</dt>
                <dd>{user.has_pin ? 'Set' : 'Missing'}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-muted">Last login</dt>
                <dd>{user.last_login_at ? shortDate(user.last_login_at) : '—'}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-muted">Joined</dt>
                <dd>{user.created_at ? shortDate(user.created_at) : '—'}</dd>
              </div>
            </dl>
          </Card>

          <Card className="space-y-3 p-5 lg:col-span-1">
            <h2 className="text-xs font-bold uppercase tracking-[0.14em] text-muted">KYC</h2>
            {kyc ? (
              <dl className="space-y-2 text-sm">
                <div className="flex justify-between gap-3">
                  <dt className="text-muted">Tier</dt>
                  <dd>{String(kyc.tier)}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-muted">BVN</dt>
                  <dd>{kyc.bvn_last4 ? `••••${kyc.bvn_last4}` : '—'}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-muted">BVN verified</dt>
                  <dd>{kyc.bvn_verified_at ? shortDate(kyc.bvn_verified_at) : 'No'}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-muted">NIN</dt>
                  <dd>{kyc.nin_last4 ? `••••${kyc.nin_last4}` : '—'}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-muted">NIN verified</dt>
                  <dd>{kyc.nin_verified_at ? shortDate(kyc.nin_verified_at) : 'No'}</dd>
                </div>
                <div className="flex justify-between gap-3">
                  <dt className="text-muted">Location</dt>
                  <dd>{[kyc.city, kyc.state].filter(Boolean).join(', ') || '—'}</dd>
                </div>
              </dl>
            ) : (
              <p className="text-sm text-muted">No KYC profile yet.</p>
            )}
          </Card>

          <Card className="space-y-3 p-5 lg:col-span-1">
            <h2 className="text-xs font-bold uppercase tracking-[0.14em] text-muted">Wallets</h2>
            {wallets.length === 0 ? (
              <p className="text-sm text-muted">No wallets.</p>
            ) : (
              <ul className="space-y-3">
                {wallets.map((w) => (
                  <li key={w.id} className="rounded-xl border border-line bg-surface-2/50 px-3 py-2.5 text-sm">
                    <div className="flex items-center justify-between gap-2">
                      <span className="font-semibold">{w.currency}</span>
                      <Pill tone="muted">{w.status}</Pill>
                    </div>
                    <div className="mt-1 text-muted">Available {ngn(w.available)}</div>
                    <div className="text-xs text-muted">
                      Balance {ngn(w.balance)} · Held {ngn(w.held_balance)}
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>

        <Card className="overflow-hidden p-0">
          <div className="border-b border-line px-4 py-3">
            <h2 className="text-xs font-bold uppercase tracking-[0.14em] text-muted">Recent transfers</h2>
          </div>
          <AdminTable>
            <thead>
              <tr>
                <th>Reference</th>
                <th>Type</th>
                <th>Status</th>
                <th>Amount</th>
                <th>When</th>
              </tr>
            </thead>
            <tbody>
              {recent_transfers.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-8 text-center text-sm text-muted">
                    No transfers yet.
                  </td>
                </tr>
              ) : (
                recent_transfers.map((t) => (
                  <tr key={t.id}>
                    <td className="font-mono text-xs">{t.reference}</td>
                    <td>{t.type}</td>
                    <td>{t.status}</td>
                    <td>{ngn(t.amount)}</td>
                    <td className="text-muted">{t.created_at ? shortDate(t.created_at) : '—'}</td>
                  </tr>
                ))
              )}
            </tbody>
          </AdminTable>
        </Card>
      </AdminPanel>
    </>
  )
}

UserShow.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>
