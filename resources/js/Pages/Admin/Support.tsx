import type { ReactNode } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { AdminLayout, AdminPanel, AdminTable } from '@/components/AdminLayout'
import { Button, Card, Pill } from '@/components/ui'
import { useAdminBase } from '@/lib/admin'
import { shortDate } from '@/lib/format'
import type { PageProps } from '@/types'

type TicketRow = {
  id: string
  reference: string
  subject: string
  note?: string | null
  status: string
  user?: { id: string; name: string; email: string } | null
  created_at: string | null
  updated_at: string | null
}

type PaginatorLink = { url: string | null; label: string; active: boolean }

type TicketCollection =
  | TicketRow[]
  | {
      data: TicketRow[]
      links?: PaginatorLink[]
    }

type SupportProps = PageProps<{
  filters: { status: string }
  tickets: TicketCollection
}>

const STATUS_FILTERS = [
  { value: 'open', label: 'Open' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'all', label: 'All' },
] as const

function collectionRows<T>(collection: { data: T[] } | T[] | undefined | null): T[] {
  if (!collection) return []
  return Array.isArray(collection) ? collection : (collection.data ?? [])
}

function collectionLinks(collection: TicketCollection): PaginatorLink[] {
  if (Array.isArray(collection) || !collection.links) return []
  return collection.links
}

function statusTone(status: string): 'mint' | 'amber' | 'danger' | 'muted' {
  if (status === 'resolved' || status === 'closed') return 'mint'
  if (status === 'escalated') return 'danger'
  if (status === 'open' || status === 'pending') return 'amber'
  return 'muted'
}

export default function Support() {
  const { filters, tickets, flash } = usePage<SupportProps>().props
  const adminBase = useAdminBase()
  const rows = collectionRows(tickets)
  const links = collectionLinks(tickets)
  const status = filters?.status ?? 'open'

  function resolveTicket(ticket: TicketRow) {
    if (!window.confirm(`Mark ${ticket.reference} as resolved?`)) return
    const note = window.prompt('Optional resolution note') ?? ''
    router.post(
      `${adminBase}/support/${ticket.id}/resolve`,
      { note: note.trim() || undefined },
      { preserveScroll: true },
    )
  }

  return (
    <>
      <Head title="Support" />

      <AdminPanel title="Support" subtitle="Open tickets, escalations, and resolutions.">
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
                href={`${adminBase}/support?status=${f.value}`}
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
            <table className="w-full min-w-[760px] text-left text-sm">
              <thead className="border-b border-line bg-surface-2/80 text-xs uppercase tracking-wide text-muted">
                <tr>
                  <th className="px-4 py-2.5 font-semibold">Ref</th>
                  <th className="px-4 py-2.5 font-semibold">Subject</th>
                  <th className="px-4 py-2.5 font-semibold">User</th>
                  <th className="px-4 py-2.5 font-semibold">Status</th>
                  <th className="px-4 py-2.5 font-semibold">Opened</th>
                  <th className="px-4 py-2.5 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((ticket) => (
                  <tr key={ticket.id} className="border-b border-line/80 last:border-0">
                    <td className="px-4 py-2.5 font-mono text-xs text-muted">{ticket.reference}</td>
                    <td className="px-4 py-2.5">
                      <div className="font-medium text-text">{ticket.subject}</div>
                      {ticket.note && <div className="mt-0.5 line-clamp-2 text-xs text-muted">{ticket.note}</div>}
                    </td>
                    <td className="px-4 py-2.5">
                      {ticket.user ? (
                        <>
                          <div className="font-medium">{ticket.user.name}</div>
                          <div className="text-xs text-muted">{ticket.user.email}</div>
                        </>
                      ) : (
                        <span className="text-muted">—</span>
                      )}
                    </td>
                    <td className="px-4 py-2.5">
                      <Pill tone={statusTone(ticket.status)}>{ticket.status}</Pill>
                    </td>
                    <td className="px-4 py-2.5 text-muted">{shortDate(ticket.created_at)}</td>
                    <td className="px-4 py-2.5">
                      {ticket.status !== 'resolved' && ticket.status !== 'closed' ? (
                        <Button type="button" variant="ghost" className="h-8 px-2.5 text-xs" onClick={() => resolveTicket(ticket)}>
                          Resolve
                        </Button>
                      ) : (
                        <span className="text-xs text-muted">—</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </AdminTable>

          {rows.length === 0 && <p className="p-8 text-center text-sm text-muted">No tickets for this filter.</p>}

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

Support.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>
