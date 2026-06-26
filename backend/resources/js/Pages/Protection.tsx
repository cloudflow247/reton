import type { ReactNode } from 'react'
import { useState } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { Button, Card, Field, Modal, Pill } from '@/components/ui'
import { ShieldIcon } from '@/components/icons'
import { ngn, shortDate } from '@/lib/format'
import type { Callback, ProtectionEvent, Recovery, Transfer } from '@/lib/types'
import type { PageProps } from '@/types'

type ActionDef = {
  title: string
  blurb: string
  confirm: string
  needPin?: boolean
  needReason?: boolean
  reasonLabel?: string
  path: string
}

type ProtectionProps = PageProps<{
  walletId: string | null
  transfers: Transfer[]
  callbacks: Callback[]
  recoveries: Recovery[]
}>

export default function Protection() {
  const { walletId, transfers, callbacks, recoveries, flash } = usePage<ProtectionProps>().props
  const [action, setAction] = useState<ActionDef | null>(null)

  const transferMap = Object.fromEntries(transfers.map((t) => [t.id, t])) as Record<string, Transfer>
  const myRole = (t?: Transfer) =>
    t && walletId ? (t.sender_wallet_id === walletId ? 'sender' : 'receiver') : 'unknown'

  const openRecoveryTransferIds = new Set(
    recoveries.filter((r) => r.status === 'held' || r.status === 'escalated').map((r) => r.transfer_id),
  )
  const openCallbackTransferIds = new Set(
    callbacks.filter((c) => c.status === 'pending' || c.status === 'escalated').map((c) => c.transfer_id),
  )

  const heldProtected = transfers.filter((t) => t.type === 'protected' && t.status === 'held')
  const reportable = transfers.filter(
    (t) => t.type === 'normal' && t.status === 'completed' && myRole(t) === 'sender' && !openRecoveryTransferIds.has(t.id),
  )

  return (
    <div className="space-y-8">
      <Head title="Protection" />

      <div className="inline-flex rounded-full border border-line bg-surface-2 p-1">
        <Link
          href="/send"
          className="relative z-10 rounded-full px-5 py-2 font-display text-sm font-semibold text-muted transition-colors hover:text-text"
        >
          Send money
        </Link>
        <span className="relative rounded-full px-5 py-2 font-display text-sm font-semibold">
          <motion.span
            layoutId="send-tab"
            className="absolute inset-0 rounded-full bg-mint shadow-sm"
            transition={{ type: 'spring', stiffness: 380, damping: 32 }}
          />
          <span className="relative z-10 flex items-center gap-1.5 text-white">
            <ShieldIcon size={15} /> Protection
          </span>
        </span>
      </div>

      <header>
        <h1 className="font-display text-2xl font-bold tracking-tight">Protection center</h1>
        <p className="mt-1 max-w-xl text-sm text-muted">
          Where Reton earns its name. Release or recall held transfers, settle disputes, and recover money sent
          to the wrong person.
        </p>
        {flash.success && <p className="mt-3 text-sm text-mint">{flash.success}</p>}
        {flash.error && <p className="mt-3 text-sm text-danger">{flash.error}</p>}
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
                          path: `/transfers/${t.id}/release`,
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
                          path: `/transfers/${t.id}/callbacks`,
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
        {callbacks.length === 0 && <Empty>No callbacks yet.</Empty>}
        {callbacks.map((c) => {
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
                            path: `/callbacks/${c.id}/accept`,
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
                            path: `/callbacks/${c.id}/reject`,
                          })
                        }
                      >
                        Reject
                      </Button>
                    </>
                  )}
                </div>
              </div>
              <Timeline events={c.events ?? []} />
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
                  path: `/transfers/${t.id}/recoveries`,
                })
              }
            >
              Report
            </Button>
          </Row>
        ))}

        {recoveries.length === 0 && reportable.length === 0 && <Empty>No recoveries in progress.</Empty>}

        {recoveries.map((r) => {
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
                            path: `/recoveries/${r.id}/return`,
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
                            path: `/recoveries/${r.id}/dispute`,
                          })
                        }
                      >
                        Dispute
                      </Button>
                    </>
                  )}
                </div>
              </div>
              <Timeline events={r.events ?? []} />
            </Row>
          )
        })}
      </Section>

      {action && <ActionDialog action={action} onClose={() => setAction(null)} />}
    </div>
  )
}

Protection.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function ActionDialog({ action, onClose }: { action: ActionDef; onClose: () => void }) {
  const [pin, setPin] = useState('')
  const [reason, setReason] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  function confirm() {
    setError('')
    setLoading(true)
    router.post(
      action.path,
      { pin, reason },
      {
        preserveScroll: true,
        onSuccess: () => onClose(),
        onError: (errors) =>
          setError(errors.pin ?? errors.reason ?? 'Something went wrong. Please try again.'),
        onFinish: () => setLoading(false),
      },
    )
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

function Timeline({ events }: { events: ProtectionEvent[] }) {
  const [open, setOpen] = useState(false)

  return (
    <div className="mt-3 w-full border-t border-line pt-3">
      <button onClick={() => setOpen((o) => !o)} className="text-xs text-mint hover:underline">
        {open ? 'Hide history' : 'Show history'}
      </button>
      {open && (
        <ol className="mt-3 space-y-2">
          {events.map((e) => (
            <li key={e.id} className="flex items-start gap-3 text-xs">
              <span className="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-mint" />
              <span className="text-text">
                <span className="font-medium capitalize">{e.action.replace(/_/g, ' ')}</span>
                {e.notes ? ` — ${e.notes}` : ''}
                <span className="text-muted"> · {shortDate(e.created_at)}</span>
              </span>
            </li>
          ))}
          {events.length === 0 && <li className="text-xs text-muted">No history yet.</li>}
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
