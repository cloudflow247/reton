import type { ReactNode } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { AdminLayout, AdminPanel, AdminTable } from '@/components/AdminLayout'
import { Button, Card, Pill } from '@/components/ui'
import { useAdminBase } from '@/lib/admin'
import { ngn, shortDate } from '@/lib/format'
import type { PageProps } from '@/types'

type RecoveryRow = {
  id: string
  reference: string
  status: string
  reason: string | null
  amount: number
  fee: number | null
  currency: string
  expires_at: string | null
  reporter?: { id: string; name: string; email: string } | null
  transfer?: { id: string; reference: string } | null
  created_at: string | null
}

type PaginatorLink = { url: string | null; label: string; active: boolean }

type RecoveryCollection =
  | RecoveryRow[]
  | {
      data: RecoveryRow[]
      links?: PaginatorLink[]
    }

type RecoveriesProps = PageProps<{
  filters: { status: string }
  recoveries: RecoveryCollection
}>

const STATUS_FILTERS = [
  { value: 'held', label: 'Held' },
  { value: 'escalated', label: 'Escalated' },
  { value: 'returned', label: 'Returned' },
  { value: 'released', label: 'Released' },
  { value: 'all', label: 'All' },
] as const

const OPEN_STATUSES = new Set(['held', 'escalated'])

function collectionRows<T>(collection: { data: T[] } | T[] | undefined | null): T[] {
  if (!collection) return []
  return Array.isArray(collection) ? collection : (collection.data ?? [])
}

function collectionLinks(collection: RecoveryCollection): PaginatorLink[] {
  if (Array.isArray(collection) || !collection.links) return []
  return collection.links
}

function statusTone(status: string): 'mint' | 'amber' | 'danger' | 'muted' {
  if (status === 'returned' || status === 'released') return 'mint'
  if (status === 'held') return 'amber'
  if (status === 'escalated' || status === 'expired') return 'danger'
  return 'muted'
}

export default function Recoveries() {
  const { filters, recoveries, flash } = usePage<RecoveriesProps>().props
  const adminBase = useAdminBase()
  const rows = collectionRows(recoveries)
  const links = collectionLinks(recoveries)
  const status = filters?.status ?? 'held'

  function resolve(recovery: RecoveryRow, resolution: 'return' | 'release') {
    const label = resolution === 'return' ? 'return funds to sender' : 'release funds to receiver'
    if (!window.confirm(`${recovery.reference}: ${label}?`)) return
    router.post(
      `${adminBase}/recoveries/${recovery.id}/resolve`,
      { resolution },
      { preserveScroll: true },
    )
  }

  return (
    <>
      <Head title="Recoveries" />

      <AdminPanel title="Recoveries" subtitle="Wrong-transfer holds and admin dispositions.">
        {flash.success && (
          <p className="rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-sm text-mint">{flash.success}</p>
        )}
        {flash.error && (
          <p className="rounded-xl border border-danger/30 bg-danger/5 px-4 py-2.5 text-sm text-danger">{flash.error}</p>
        )}

        <div className="flex flex-wrap gap-1.5">
          {STATUS_FILTERS.map((f) => {
            const on = status === f.value
            return (
              <Link
                key={f.value}
                href={`${adminBase}/recoveries?status=${f.value}`}
                className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                  on ? 'bg-mint/15 text-mint' : 'bg-surface-2 text-muted hover:text-text'
                }`}
                preserveState
              >
                {f.label}
              </Link>
            )
          })}
        </div>

        <Card className="overflow-hidden p-0">
          <AdminTable>
            <table className="w-full min-w-[920px] text-left text-sm">
              <thead className="border-b border-line bg-surface-2/80 text-xs uppercase tracking-wide text-muted">
                <tr>
                  <th className="px-4 py-2.5 font-semibold">Ref</th>
                  <th className="px-4 py-2.5 font-semibold">Reporter</th>
                  <th className="px-4 py-2.5 font-semibold">Transfer</th>
                  <th className="px-4 py-2.5 font-semibold">Amount</th>
                  <th className="px-4 py-2.5 font-semibold">Reason</th>
                  <th className="px-4 py-2.5 font-semibold">Expires</th>
                  <th className="px-4 py-2.5 font-semibold">Status</th>
                  <th className="px-4 py-2.5 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.id} className="border-b border-line/80 last:border-0">
                    <td className="px-4 py-2.5 font-mono text-xs text-muted">{row.reference}</td>
                    <td className="px-4 py-2.5">
                      {row.reporter ? (
                        <>
                          <div className="font-medium">{row.reporter.name}</div>
                          <div className="text-xs text-muted">{row.reporter.email}</div>
                        </>
                      ) : (
                        <span className="text-muted">—</span>
                      )}
                    </td>
                    <td className="px-4 py-2.5 font-mono text-xs text-muted">{row.transfer?.reference ?? '—'}</td>
                    <td className="px-4 py-2.5 font-num">
                      <div>{ngn(Number(row.amount))}</div>
                      {row.fee != null && Number(row.fee) > 0 && (
                        <div className="text-xs text-muted">fee {ngn(Number(row.fee))}</div>
                      )}
                    </td>
                    <td className="max-w-[180px] truncate px-4 py-2.5 text-muted" title={row.reason ?? undefined}>
                      {row.reason ?? '—'}
                    </td>
                    <td className="px-4 py-2.5 text-muted">{shortDate(row.expires_at)}</td>
                    <td className="px-4 py-2.5">
                      <Pill tone={statusTone(row.status)}>{row.status}</Pill>
                    </td>
                    <td className="px-4 py-2.5">
                      {OPEN_STATUSES.has(row.status) ? (
                        <div className="flex flex-wrap gap-1.5">
                          <Button type="button" className="h-8 px-2.5 text-xs" onClick={() => resolve(row, 'return')}>
                            Return to sender
                          </Button>
                          <Button type="button" variant="ghost" className="h-8 px-2.5 text-xs" onClick={() => resolve(row, 'release')}>
                            Release to receiver
                          </Button>
                        </div>
                      ) : (
                        <span className="text-xs text-muted">—</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </AdminTable>

          {rows.length === 0 && <p className="p-8 text-center text-sm text-muted">No recoveries for this filter.</p>}

          {links.length > 3 && (
            <div className="flex flex-wrap gap-1 border-t border-line p-3">
              {links.map((link, i) => (
                <button
                  key={`${link.label}-${i}`}
                  type="button"
                  disabled={!link.url}
                  onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                  className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${
                    link.active ? 'bg-mint/12 text-mint' : 'text-muted hover:bg-surface-2'
                  }`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          )}
        </Card>
      </AdminPanel>
    </>
  )
}

Recoveries.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>
