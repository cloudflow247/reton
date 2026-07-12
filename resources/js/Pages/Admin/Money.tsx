import type { ReactNode } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { AdminLayout, AdminPanel, AdminTable } from '@/components/AdminLayout'
import { Card, Pill } from '@/components/ui'
import { useAdminBase } from '@/lib/admin'
import { ngn, shortDate } from '@/lib/format'
import type { PageProps } from '@/types'

type DepositRow = {
  id: string
  reference: string
  status: string
  amount: number
  currency: string
  method: string
  user?: { name: string; email: string } | null
  created_at: string | null
}

type PayoutRow = {
  id: string
  reference: string
  status: string
  amount: number
  currency: string
  provider: string | null
  account_number: string | null
  bank_code: string | null
  user?: { name: string; email: string } | null
  created_at: string | null
}

type LedgerRow = {
  id: string
  type: string
  description: string | null
  idempotency_key: string | null
  created_at: string | null
}

type MoneyProps = PageProps<{
  tab: string
  deposits: DepositRow[] | { data: DepositRow[] }
  payouts: PayoutRow[] | { data: PayoutRow[] }
  ledger: LedgerRow[] | { data: LedgerRow[] }
}>

const TABS = [
  { value: 'ledger', label: 'Ledger' },
  { value: 'deposits', label: 'Deposits' },
  { value: 'payouts', label: 'Payouts' },
] as const

function collectionRows<T>(collection: { data: T[] } | T[] | undefined | null): T[] {
  if (!collection) return []
  return Array.isArray(collection) ? collection : (collection.data ?? [])
}

function statusTone(status: string): 'mint' | 'amber' | 'danger' | 'muted' {
  const v = status.toLowerCase()
  if (['completed', 'success', 'successful', 'posted', 'settled'].includes(v)) return 'mint'
  if (['pending', 'processing', 'queued'].includes(v)) return 'amber'
  if (['failed', 'reversed', 'cancelled', 'canceled'].includes(v)) return 'danger'
  return 'muted'
}

export default function Money() {
  const { tab: rawTab, deposits, payouts, ledger, flash } = usePage<MoneyProps>().props
  const adminBase = useAdminBase()
  const tab = rawTab || 'ledger'
  const depositRows = collectionRows(deposits)
  const payoutRows = collectionRows(payouts)
  const ledgerRows = collectionRows(ledger)

  return (
    <>
      <Head title="Money" />

      <AdminPanel title="Money" subtitle="Ledger postings, deposits, and payouts.">
        {flash.success && (
          <p className="rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-sm text-mint">{flash.success}</p>
        )}
        {flash.error && (
          <p className="rounded-xl border border-danger/30 bg-danger/5 px-4 py-2.5 text-sm text-danger">{flash.error}</p>
        )}

        <div className="flex flex-wrap gap-1.5">
          {TABS.map((t) => {
            const on = tab === t.value
            return (
              <Link
                key={t.value}
                href={`${adminBase}/money?tab=${t.value}`}
                className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                  on ? 'bg-mint/15 text-mint' : 'bg-surface-2 text-muted hover:text-text'
                }`}
                preserveState
              >
                {t.label}
              </Link>
            )
          })}
        </div>

        {tab === 'ledger' && (
          <Card className="overflow-hidden p-0">
            <AdminTable>
              <table className="w-full min-w-[640px] text-left text-sm">
                <thead className="border-b border-line bg-surface-2/80 text-xs uppercase tracking-wide text-muted">
                  <tr>
                    <th className="px-4 py-2.5 font-semibold">Type</th>
                    <th className="px-4 py-2.5 font-semibold">Description</th>
                    <th className="px-4 py-2.5 font-semibold">Idempotency</th>
                    <th className="px-4 py-2.5 font-semibold">When</th>
                  </tr>
                </thead>
                <tbody>
                  {ledgerRows.map((row) => (
                    <tr key={row.id} className="border-b border-line/80 last:border-0">
                      <td className="px-4 py-2.5">
                        <Pill tone="muted">{row.type}</Pill>
                      </td>
                      <td className="px-4 py-2.5">{row.description ?? '—'}</td>
                      <td className="px-4 py-2.5 font-mono text-xs text-muted">{row.idempotency_key ?? '—'}</td>
                      <td className="px-4 py-2.5 text-muted">{shortDate(row.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </AdminTable>
            {ledgerRows.length === 0 && <p className="p-8 text-center text-sm text-muted">No ledger entries yet.</p>}
          </Card>
        )}

        {tab === 'deposits' && (
          <Card className="overflow-hidden p-0">
            <AdminTable>
              <table className="w-full min-w-[760px] text-left text-sm">
                <thead className="border-b border-line bg-surface-2/80 text-xs uppercase tracking-wide text-muted">
                  <tr>
                    <th className="px-4 py-2.5 font-semibold">Ref</th>
                    <th className="px-4 py-2.5 font-semibold">User</th>
                    <th className="px-4 py-2.5 font-semibold">Amount</th>
                    <th className="px-4 py-2.5 font-semibold">Method</th>
                    <th className="px-4 py-2.5 font-semibold">Status</th>
                    <th className="px-4 py-2.5 font-semibold">When</th>
                  </tr>
                </thead>
                <tbody>
                  {depositRows.map((row) => (
                    <tr key={row.id} className="border-b border-line/80 last:border-0">
                      <td className="px-4 py-2.5 font-mono text-xs text-muted">{row.reference}</td>
                      <td className="px-4 py-2.5">
                        {row.user ? (
                          <>
                            <div className="font-medium">{row.user.name}</div>
                            <div className="text-xs text-muted">{row.user.email}</div>
                          </>
                        ) : (
                          <span className="text-muted">—</span>
                        )}
                      </td>
                      <td className="px-4 py-2.5 font-num">{ngn(Number(row.amount))}</td>
                      <td className="px-4 py-2.5 text-muted">{row.method || '—'}</td>
                      <td className="px-4 py-2.5">
                        <Pill tone={statusTone(row.status)}>{row.status}</Pill>
                      </td>
                      <td className="px-4 py-2.5 text-muted">{shortDate(row.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </AdminTable>
            {depositRows.length === 0 && <p className="p-8 text-center text-sm text-muted">No deposits yet.</p>}
          </Card>
        )}

        {tab === 'payouts' && (
          <Card className="overflow-hidden p-0">
            <AdminTable>
              <table className="w-full min-w-[820px] text-left text-sm">
                <thead className="border-b border-line bg-surface-2/80 text-xs uppercase tracking-wide text-muted">
                  <tr>
                    <th className="px-4 py-2.5 font-semibold">Ref</th>
                    <th className="px-4 py-2.5 font-semibold">User</th>
                    <th className="px-4 py-2.5 font-semibold">Amount</th>
                    <th className="px-4 py-2.5 font-semibold">Destination</th>
                    <th className="px-4 py-2.5 font-semibold">Provider</th>
                    <th className="px-4 py-2.5 font-semibold">Status</th>
                    <th className="px-4 py-2.5 font-semibold">When</th>
                  </tr>
                </thead>
                <tbody>
                  {payoutRows.map((row) => (
                    <tr key={row.id} className="border-b border-line/80 last:border-0">
                      <td className="px-4 py-2.5 font-mono text-xs text-muted">{row.reference}</td>
                      <td className="px-4 py-2.5">
                        {row.user ? (
                          <>
                            <div className="font-medium">{row.user.name}</div>
                            <div className="text-xs text-muted">{row.user.email}</div>
                          </>
                        ) : (
                          <span className="text-muted">—</span>
                        )}
                      </td>
                      <td className="px-4 py-2.5 font-num">{ngn(Number(row.amount))}</td>
                      <td className="px-4 py-2.5 font-mono text-xs text-muted">
                        {row.bank_code && row.account_number
                          ? `${row.bank_code} · ${row.account_number}`
                          : row.account_number ?? '—'}
                      </td>
                      <td className="px-4 py-2.5 text-muted">{row.provider ?? '—'}</td>
                      <td className="px-4 py-2.5">
                        <Pill tone={statusTone(row.status)}>{row.status}</Pill>
                      </td>
                      <td className="px-4 py-2.5 text-muted">{shortDate(row.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </AdminTable>
            {payoutRows.length === 0 && <p className="p-8 text-center text-sm text-muted">No payouts yet.</p>}
          </Card>
        )}
      </AdminPanel>
    </>
  )
}

Money.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>
