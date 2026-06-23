import { useState, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api, apiError } from '../lib/api'
import { ngn, shortDate } from '../lib/format'
import { useCallbacks, useMe, useRecoveries, useTransfers } from '../lib/queries'
import type { ProtectionEvent, Transfer } from '../lib/types'
import { Button, Card, Field, Modal, Pill } from '../components/ui'

type Action = {
  title: string
  blurb: string
  confirm: string
  needPin?: boolean
  needReason?: boolean
  reasonLabel?: string
  run: (v: { pin: string; reason: string }) => Promise<unknown>
}

export function Protection() {
  const { data: me } = useMe()
  const walletId = me?.wallets?.[0]?.id
  const transfers = useTransfers()
  const callbacks = useCallbacks()
  const recoveries = useRecoveries()
  const qc = useQueryClient()

  const [action, setAction] = useState<Action | null>(null)

  const list = transfers.data ?? []
  const transferMap = Object.fromEntries(list.map((t) => [t.id, t])) as Record<string, Transfer>
  const myRole = (t?: Transfer) =>
    t && walletId ? (t.sender_wallet_id === walletId ? 'sender' : 'receiver') : 'unknown'

  const openRecoveryTransferIds = new Set(
    (recoveries.data ?? []).filter((r) => r.status === 'held' || r.status === 'escalated').map((r) => r.transfer_id),
  )
  const openCallbackTransferIds = new Set(
    (callbacks.data ?? []).filter((c) => c.status === 'pending' || c.status === 'escalated').map((c) => c.transfer_id),
  )

  const heldProtected = list.filter((t) => t.type === 'protected' && t.status === 'held')
  const reportable = list.filter(
    (t) => t.type === 'normal' && t.status === 'completed' && myRole(t) === 'sender' && !openRecoveryTransferIds.has(t.id),
  )

  function refresh() {
    ;['transfers', 'callbacks', 'recoveries', 'me', 'activity'].forEach((k) =>
      qc.invalidateQueries({ queryKey: [k] }),
    )
  }

  const post = (path: string, body: Record<string, unknown>) => async () => {
    await api.post(path, body)
    refresh()
  }

  return (
    <div className="space-y-8">
      <header>
        <h1 className="font-display text-2xl font-bold tracking-tight">Protection center</h1>
        <p className="mt-1 max-w-xl text-sm text-muted">
          Where Reton earns its name. Release or recall held transfers, settle disputes, and recover money sent
          to the wrong person.
        </p>
      </header>

      {/* ── Held protected transfers ───────────────────────────── */}
      <Section title="Held transfers" hint="Protected money waiting to be released or recalled.">
        {heldProtected.length === 0 && <Empty>No protected transfers are currently held.</Empty>}
        {heldProtected.map((t) => {
          const role = myRole(t)
          const hasCallback = openCallbackTransferIds.has(t.id)
          return (
            <Row key={t.id}>
              <div>
                <div className="flex items-center gap-2 text-sm font-medium">
                  {role === 'sender' ? 'You sent (protected)' : 'Incoming (protected)'}
                  <Pill tone="amber">held</Pill>
                </div>
                <div className="mt-0.5 text-xs text-muted">
                  {ngn(t.amount)} · {shortDate(t.created_at)}
                  {t.hold?.expires_at ? ` · auto-releases ${shortDate(t.hold.expires_at)}` : ''}
                </div>
              </div>
              <div className="flex flex-wrap justify-end gap-2">
                {role === 'sender' && !hasCallback && (
                  <>
                    <Button
                      variant="ghost"
                      className="px-4 py-2"
                      onClick={() =>
                        setAction({
                          title: 'Release to recipient',
                          blurb: `Pay ${ngn(t.amount)} out to the recipient. This cannot be undone.`,
                          confirm: 'Release funds',
                          needPin: true,
                          run: ({ pin }) => post(`/transfers/${t.id}/release`, { pin })(),
                        })
                      }
                    >
                      Release
                    </Button>
                    <Button
                      className="px-4 py-2"
                      onClick={() =>
                        setAction({
                          title: 'Raise a callback',
                          blurb: 'Dispute this transfer. The recipient can accept (you are refunded) or reject (an agent reviews it).',
                          confirm: 'Raise callback',
                          needPin: true,
                          needReason: true,
                          reasonLabel: 'Why are you recalling this?',
                          run: ({ pin, reason }) => post(`/transfers/${t.id}/callbacks`, { pin, reason })(),
                        })
                      }
                    >
                      Raise callback
                    </Button>
                  </>
                )}
                {hasCallback && <Pill tone="mint">callback open</Pill>}
                {role === 'receiver' && <Pill tone="muted">pending protection</Pill>}
              </div>
            </Row>
          )
        })}
      </Section>

      {/* ── Callbacks ──────────────────────────────────────────── */}
      <Section title="Callbacks" hint="Disputes you raised or need to answer.">
        {(callbacks.data ?? []).length === 0 && <Empty>No callbacks yet.</Empty>}
        {(callbacks.data ?? []).map((c) => {
          const t = transferMap[c.transfer_id]
          const role = myRole(t)
          const canAnswer = role === 'receiver' && c.status === 'pending'
          return (
            <Row key={c.id} stack>
              <div className="flex w-full items-start justify-between">
                <div>
                  <div className="flex items-center gap-2 text-sm font-medium">
                    {role === 'receiver' ? 'Someone recalled a payment to you' : 'Your callback'}
                    <StatusPill status={c.status} />
                  </div>
                  <div className="mt-0.5 text-xs text-muted">
                    {t ? ngn(t.amount) : ''} · {c.reason ?? 'No reason given'}
                    {c.responds_by && c.status === 'pending' ? ` · respond by ${shortDate(c.responds_by)}` : ''}
                  </div>
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                  {canAnswer && (
                    <>
                      <Button
                        className="px-4 py-2"
                        onClick={() =>
                          setAction({
                            title: 'Accept and return',
                            blurb: 'Agree the payment was wrong and return the funds to the sender.',
                            confirm: 'Accept & refund',
                            needPin: true,
                            run: ({ pin }) => post(`/callbacks/${c.id}/accept`, { pin })(),
                          })
                        }
                      >
                        Accept
                      </Button>
                      <Button
                        variant="ghost"
                        className="px-4 py-2"
                        onClick={() =>
                          setAction({
                            title: 'Reject the callback',
                            blurb: 'Dispute the recall. A Reton agent will review the evidence and decide.',
                            confirm: 'Reject',
                            needReason: true,
                            reasonLabel: 'Why is this payment valid?',
                            run: ({ reason }) => post(`/callbacks/${c.id}/reject`, { reason })(),
                          })
                        }
                      >
                        Reject
                      </Button>
                    </>
                  )}
                </div>
              </div>
              <Timeline path={`/callbacks/${c.id}`} />
            </Row>
          )
        })}
      </Section>

      {/* ── Recovery ───────────────────────────────────────────── */}
      <Section title="Wrong-transfer recovery" hint="Report money sent to the wrong person, or respond to a claim.">
        {reportable.length > 0 && (
          <div className="mb-3 text-xs font-medium uppercase tracking-wide text-muted">Sent in error?</div>
        )}
        {reportable.map((t) => (
          <Row key={t.id}>
            <div>
              <div className="text-sm font-medium">You sent {ngn(t.amount)}</div>
              <div className="mt-0.5 text-xs text-muted">{shortDate(t.created_at)} · normal transfer</div>
            </div>
            <Button
              variant="ghost"
              className="px-4 py-2"
              onClick={() =>
                setAction({
                  title: 'Report a wrong transfer',
                  blurb: 'If you are eligible, the recipient’s funds are frozen and they’re asked to return them.',
                  confirm: 'Report transfer',
                  needPin: true,
                  needReason: true,
                  reasonLabel: 'What happened?',
                  run: ({ pin, reason }) => post(`/transfers/${t.id}/recoveries`, { pin, reason })(),
                })
              }
            >
              Report
            </Button>
          </Row>
        ))}

        {(recoveries.data ?? []).length === 0 && reportable.length === 0 && (
          <Empty>No recoveries in progress.</Empty>
        )}

        {(recoveries.data ?? []).map((r) => {
          const t = transferMap[r.transfer_id]
          const role = myRole(t)
          const canAnswer = role === 'receiver' && r.status === 'held'
          return (
            <Row key={r.id} stack>
              <div className="flex w-full items-start justify-between">
                <div>
                  <div className="flex items-center gap-2 text-sm font-medium">
                    {role === 'receiver' ? 'A recovery claim on funds you received' : 'Your recovery claim'}
                    <StatusPill status={r.status} />
                  </div>
                  <div className="mt-0.5 text-xs text-muted">
                    {ngn(r.amount)}
                    {r.fee ? ` · fee ${ngn(r.fee)}` : ''} · {r.reason ?? '—'}
                  </div>
                </div>
                <div className="flex flex-wrap justify-end gap-2">
                  {canAnswer && (
                    <>
                      <Button
                        className="px-4 py-2"
                        onClick={() =>
                          setAction({
                            title: 'Return the funds',
                            blurb: 'Send the money back to the original sender.',
                            confirm: 'Return funds',
                            needPin: true,
                            run: ({ pin }) => post(`/recoveries/${r.id}/return`, { pin })(),
                          })
                        }
                      >
                        Return
                      </Button>
                      <Button
                        variant="ghost"
                        className="px-4 py-2"
                        onClick={() =>
                          setAction({
                            title: 'Dispute the claim',
                            blurb: 'Keep the funds frozen and let a Reton agent review the claim.',
                            confirm: 'Dispute',
                            needReason: true,
                            reasonLabel: 'Why is this payment yours?',
                            run: ({ reason }) => post(`/recoveries/${r.id}/dispute`, { reason })(),
                          })
                        }
                      >
                        Dispute
                      </Button>
                    </>
                  )}
                </div>
              </div>
              <Timeline path={`/recoveries/${r.id}`} />
            </Row>
          )
        })}
      </Section>

      {action && <ActionDialog action={action} onClose={() => setAction(null)} />}
    </div>
  )
}

function ActionDialog({ action, onClose }: { action: Action; onClose: () => void }) {
  const [pin, setPin] = useState('')
  const [reason, setReason] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function confirm() {
    setError('')
    setLoading(true)
    try {
      await action.run({ pin, reason })
      onClose()
    } catch (err) {
      setError(apiError(err))
    } finally {
      setLoading(false)
    }
  }

  return (
    <Modal title={action.title} onClose={onClose}>
      <p className="text-sm text-muted">{action.blurb}</p>
      <div className="mt-4 space-y-3">
        {action.needReason && (
          <Field
            label={action.reasonLabel ?? 'Reason'}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Add a short note"
          />
        )}
        {action.needPin && (
          <Field
            label="Transaction PIN"
            type="password"
            inputMode="numeric"
            value={pin}
            onChange={(e) => setPin(e.target.value)}
            placeholder="••••"
          />
        )}
        {error && <p className="text-sm text-danger">{error}</p>}
        <Button onClick={confirm} loading={loading} className="w-full">
          {action.confirm}
        </Button>
      </div>
    </Modal>
  )
}

function Timeline({ path }: { path: string }) {
  const [open, setOpen] = useState(false)
  const { data } = useQuery({
    queryKey: ['detail', path],
    enabled: open,
    queryFn: async () => {
      const { data } = await api.get(path)
      return (data.data.events ?? []) as ProtectionEvent[]
    },
  })

  return (
    <div className="mt-3 w-full border-t border-line pt-3">
      <button onClick={() => setOpen((o) => !o)} className="text-xs text-mint hover:underline">
        {open ? 'Hide history' : 'Show history'}
      </button>
      {open && (
        <ol className="mt-3 space-y-2">
          {(data ?? []).map((e) => (
            <li key={e.id} className="flex items-start gap-3 text-xs">
              <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-mint" />
              <span className="text-text">
                <span className="font-medium capitalize">{e.action.replace(/_/g, ' ')}</span>
                {e.notes ? ` — ${e.notes}` : ''}
                <span className="text-muted"> · {shortDate(e.created_at)}</span>
              </span>
            </li>
          ))}
          {data && data.length === 0 && <li className="text-xs text-muted">No history yet.</li>}
        </ol>
      )}
    </div>
  )
}

function Section({ title, hint, children }: { title: string; hint: string; children: ReactNode }) {
  return (
    <section className="space-y-3">
      <div>
        <h2 className="font-display text-lg font-semibold">{title}</h2>
        <p className="text-sm text-muted">{hint}</p>
      </div>
      <Card className="divide-y divide-line p-0">{children}</Card>
    </section>
  )
}

function Row({ children, stack }: { children: ReactNode; stack?: boolean }) {
  return (
    <div className={`px-5 py-4 ${stack ? 'block' : 'flex items-center justify-between gap-4'}`}>{children}</div>
  )
}

function Empty({ children }: { children: ReactNode }) {
  return <div className="px-5 py-8 text-center text-sm text-muted">{children}</div>
}

function StatusPill({ status }: { status: string }) {
  const tone =
    status === 'refunded' || status === 'returned'
      ? 'mint'
      : status === 'escalated' || status === 'held' || status === 'pending'
        ? 'amber'
        : status === 'declined'
          ? 'danger'
          : 'muted'
  return <Pill tone={tone as 'mint' | 'amber' | 'danger' | 'muted'}>{status}</Pill>
}
