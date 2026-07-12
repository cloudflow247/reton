import type { ReactNode } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { AdminLayout, AdminPanel, AdminTable } from '@/components/AdminLayout'
import { Button, Card, Pill } from '@/components/ui'
import { useAdminBase } from '@/lib/admin'
import { ngn, shortDate } from '@/lib/format'
import type { PageProps } from '@/types'

type AlertRow = {
  id: string
  score: number
  level: string
  action: string | null
  recommended_action: string | null
  status: string
  amount: number | null
  currency: string | null
  signals: unknown
  user?: { id: string; name: string; email: string } | null
  created_at: string | null
  resolved_at?: string | null
}

type PaginatorLink = { url: string | null; label: string; active: boolean }

type AlertCollection =
  | AlertRow[]
  | {
      data: AlertRow[]
      links?: PaginatorLink[]
    }

type FraudProps = PageProps<{
  filters: { status: string }
  alerts: AlertCollection
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

function collectionLinks(collection: AlertCollection): PaginatorLink[] {
  if (Array.isArray(collection) || !collection.links) return []
  return collection.links
}

function statusTone(status: string): 'mint' | 'amber' | 'danger' | 'muted' {
  if (status === 'resolved') return 'mint'
  if (status === 'open') return 'amber'
  return 'muted'
}

function levelTone(level: string): 'mint' | 'amber' | 'danger' | 'muted' {
  const v = level.toLowerCase()
  if (v === 'critical' || v === 'high' || v === 'block') return 'danger'
  if (v === 'medium' || v === 'review') return 'amber'
  if (v === 'low') return 'mint'
  return 'muted'
}

function signalSummary(signals: unknown): string {
  if (!signals) return '—'
  if (Array.isArray(signals)) {
    return signals
      .map((s) => (typeof s === 'string' ? s : typeof s === 'object' && s && 'code' in s ? String((s as { code: unknown }).code) : JSON.stringify(s)))
      .slice(0, 3)
      .join(', ')
  }
  if (typeof signals === 'object') {
    return Object.keys(signals as object).slice(0, 4).join(', ') || '—'
  }
  return String(signals)
}

export default function Fraud() {
  const { filters, alerts, flash } = usePage<FraudProps>().props
  const adminBase = useAdminBase()
  const rows = collectionRows(alerts)
  const links = collectionLinks(alerts)
  const status = filters?.status ?? 'open'

  function resolveAlert(alert: AlertRow) {
    if (!window.confirm(`Resolve fraud alert (score ${alert.score})?`)) return
    const freeze =
      (alert.recommended_action === 'freeze' || alert.level === 'high') &&
      window.confirm('Also freeze the user account?')
    router.post(
      `${adminBase}/fraud/${alert.id}/resolve`,
      { freeze_user: freeze },
      { preserveScroll: true },
    )
  }

  return (
    <>
      <Head title="Fraud" />

      <AdminPanel title="Fraud" subtitle="Rule-engine alerts and recommended actions.">
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
                href={`${adminBase}/fraud?status=${f.value}`}
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
            <table className="w-full min-w-[900px] text-left text-sm">
              <thead className="border-b border-line bg-surface-2/80 text-xs uppercase tracking-wide text-muted">
                <tr>
                  <th className="px-4 py-2.5 font-semibold">Score</th>
                  <th className="px-4 py-2.5 font-semibold">Level</th>
                  <th className="px-4 py-2.5 font-semibold">User</th>
                  <th className="px-4 py-2.5 font-semibold">Amount</th>
                  <th className="px-4 py-2.5 font-semibold">Action</th>
                  <th className="px-4 py-2.5 font-semibold">Signals</th>
                  <th className="px-4 py-2.5 font-semibold">Status</th>
                  <th className="px-4 py-2.5 font-semibold">When</th>
                  <th className="px-4 py-2.5 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((alert) => (
                  <tr key={alert.id} className="border-b border-line/80 last:border-0">
                    <td className="px-4 py-2.5 font-num font-bold">{alert.score}</td>
                    <td className="px-4 py-2.5">
                      <Pill tone={levelTone(alert.level)}>{alert.level}</Pill>
                    </td>
                    <td className="px-4 py-2.5">
                      {alert.user ? (
                        <>
                          <div className="font-medium">{alert.user.name}</div>
                          <div className="text-xs text-muted">{alert.user.email}</div>
                        </>
                      ) : (
                        <span className="text-muted">—</span>
                      )}
                    </td>
                    <td className="px-4 py-2.5 font-num">
                      {alert.amount != null ? ngn(Number(alert.amount)) : '—'}
                    </td>
                    <td className="px-4 py-2.5">
                      <div className="text-xs">{alert.action ?? '—'}</div>
                      {alert.recommended_action && (
                        <div className="mt-0.5 text-xs text-amber">{alert.recommended_action}</div>
                      )}
                    </td>
                    <td className="max-w-[180px] truncate px-4 py-2.5 font-mono text-xs text-muted" title={signalSummary(alert.signals)}>
                      {signalSummary(alert.signals)}
                    </td>
                    <td className="px-4 py-2.5">
                      <Pill tone={statusTone(alert.status)}>{alert.status}</Pill>
                    </td>
                    <td className="px-4 py-2.5 text-muted">{shortDate(alert.created_at)}</td>
                    <td className="px-4 py-2.5">
                      {alert.status !== 'resolved' ? (
                        <Button type="button" variant="ghost" className="h-8 px-2.5 text-xs" onClick={() => resolveAlert(alert)}>
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

          {rows.length === 0 && <p className="p-8 text-center text-sm text-muted">No fraud alerts for this filter.</p>}

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

Fraud.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>
