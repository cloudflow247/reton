import type { ReactNode } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { AdminLayout, AdminPanel, AdminTable } from '@/components/AdminLayout'
import { Button, Card, Pill } from '@/components/ui'
import { useAdminBase } from '@/lib/admin'
import { ngn, shortDate } from '@/lib/format'
import type { PageProps } from '@/types'

type CallbackRow = {
  id: string
  reference: string
  status: string
  reason: string | null
  responds_by: string | null
  transfer?: {
    id: string
    reference: string
    amount: number
    currency: string
  } | null
  initiator?: { id: string; name: string; email: string } | null
  created_at: string | null
}

type PaginatorLink = { url: string | null; label: string; active: boolean }

type CallbackCollection =
  | CallbackRow[]
  | {
      data: CallbackRow[]
      links?: PaginatorLink[]
    }

type CallbacksProps = PageProps<{
  filters: { status: string }
  callbacks: CallbackCollection
}>

const STATUS_FILTERS = [
  { value: 'pending', label: 'Pending' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'all', label: 'All' },
] as const

function collectionRows<T>(collection: { data: T[] } | T[] | undefined | null): T[] {
  if (!collection) return []
  return Array.isArray(collection) ? collection : (collection.data ?? [])
}

function collectionLinks(collection: CallbackCollection): PaginatorLink[] {
  if (Array.isArray(collection) || !collection.links) return []
  return collection.links
}

function statusTone(status: string): 'mint' | 'amber' | 'danger' | 'muted' {
  if (status === 'resolved' || status === 'released' || status === 'accepted') return 'mint'
  if (status === 'pending') return 'amber'
  if (status === 'rejected' || status === 'refunded' || status === 'expired') return 'danger'
  return 'muted'
}

export default function Callbacks() {
  const { filters, callbacks, flash } = usePage<CallbacksProps>().props
  const adminBase = useAdminBase()
  const rows = collectionRows(callbacks)
  const links = collectionLinks(callbacks)
  const status = filters?.status ?? 'pending'

  function resolve(callback: CallbackRow, resolution: 'release' | 'refund') {
    const label = resolution === 'release' ? 'release funds to receiver' : 'refund to sender'
    if (!window.confirm(`${callback.reference}: ${label}?`)) return
    router.post(
      `${adminBase}/callbacks/${callback.id}/resolve`,
      { resolution },
      { preserveScroll: true },
    )
  }

  return (
    <>
      <Head title="Callbacks" />

      <AdminPanel title="Callbacks" subtitle="Protected-transfer disputes awaiting admin resolution.">
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
                href={`${adminBase}/callbacks?status=${f.value}`}
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
            <table className="w-full min-w-[880px] text-left text-sm">
              <thead className="border-b border-line bg-surface-2/80 text-xs uppercase tracking-wide text-muted">
                <tr>
                  <th className="px-4 py-2.5 font-semibold">Ref</th>
                  <th className="px-4 py-2.5 font-semibold">Transfer</th>
                  <th className="px-4 py-2.5 font-semibold">Initiator</th>
                  <th className="px-4 py-2.5 font-semibold">Reason</th>
                  <th className="px-4 py-2.5 font-semibold">Responds by</th>
                  <th className="px-4 py-2.5 font-semibold">Status</th>
                  <th className="px-4 py-2.5 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((cb) => (
                  <tr key={cb.id} className="border-b border-line/80 last:border-0">
                    <td className="px-4 py-2.5 font-mono text-xs text-muted">{cb.reference}</td>
                    <td className="px-4 py-2.5">
                      {cb.transfer ? (
                        <>
                          <div className="font-mono text-xs">{cb.transfer.reference}</div>
                          <div className="font-num text-xs text-muted">{ngn(Number(cb.transfer.amount))}</div>
                        </>
                      ) : (
                        <span className="text-muted">-</span>
                      )}
                    </td>
                    <td className="px-4 py-2.5">
                      {cb.initiator ? (
                        <>
                          <div className="font-medium">{cb.initiator.name}</div>
                          <div className="text-xs text-muted">{cb.initiator.email}</div>
                        </>
                      ) : (
                        <span className="text-muted">-</span>
                      )}
                    </td>
                    <td className="max-w-[200px] truncate px-4 py-2.5 text-muted" title={cb.reason ?? undefined}>
                      {cb.reason ?? '-'}
                    </td>
                    <td className="px-4 py-2.5 text-muted">{shortDate(cb.responds_by)}</td>
                    <td className="px-4 py-2.5">
                      <Pill tone={statusTone(cb.status)}>{cb.status}</Pill>
                    </td>
                    <td className="px-4 py-2.5">
                      {cb.status === 'pending' ? (
                        <div className="flex flex-wrap gap-1.5">
                          <Button type="button" className="h-8 px-2.5 text-xs" onClick={() => resolve(cb, 'release')}>
                            Release
                          </Button>
                          <Button type="button" variant="ghost" className="h-8 px-2.5 text-xs text-danger" onClick={() => resolve(cb, 'refund')}>
                            Refund
                          </Button>
                        </div>
                      ) : (
                        <span className="text-xs text-muted">-</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </AdminTable>

          {rows.length === 0 && <p className="p-8 text-center text-sm text-muted">No callbacks for this filter.</p>}

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

Callbacks.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>
