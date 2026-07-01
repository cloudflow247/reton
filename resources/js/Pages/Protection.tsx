import type { ReactNode } from 'react'
import { useMemo, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { useForm } from 'react-hook-form'
import { AppShell } from '@/components/AppShell'
import { DigitalOrderEscrowCard } from '@/components/DigitalOrderEscrowCard'
import { RhfField } from '@/components/forms/RhfField'
import { TrustProtectionListener } from '@/components/TrustProtectionListener'
import { Button, Card, Modal, Pill } from '@/components/ui'
import { ArrowRightIcon, ClockIcon, ShieldIcon } from '@/components/icons'
import { ngn, shortDate } from '@/lib/format'
import {
  protectionActionSchema,
  type ProtectionActionValues,
} from '@/lib/schemas/protection'
import type { Callback, DigitalOrder, ProtectionEvent, Recovery, Transfer } from '@/lib/types'
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

type Filter = 'all' | 'action' | 'held' | 'callbacks' | 'recovery'

type ProtectionProps = PageProps<{
  walletId: string | null
  transfers: Transfer[]
  callbacks: Callback[]
  recoveries: Recovery[]
  digitalOrders: DigitalOrder[]
}>

export default function Protection() {
  const { auth, walletId, transfers, callbacks, recoveries, digitalOrders, flash } = usePage<ProtectionProps>().props
  const [action, setAction] = useState<ActionDef | null>(null)
  const [filter, setFilter] = useState<Filter>('all')

  const transferMap = useMemo(
    () => Object.fromEntries(transfers.map((t) => [t.id, t])) as Record<string, Transfer>,
    [transfers],
  )

  const orderByTransfer = useMemo(
    () => Object.fromEntries(digitalOrders.filter((o) => o.transfer_id).map((o) => [o.transfer_id!, o])) as Record<string, DigitalOrder>,
    [digitalOrders],
  )

  const myRole = (t?: Transfer) =>
    t && walletId ? (t.sender_wallet_id === walletId ? 'sender' : 'receiver') : 'unknown'

  const openRecoveryTransferIds = useMemo(
    () =>
      new Set(
        recoveries.filter((r) => r.status === 'held' || r.status === 'escalated').map((r) => r.transfer_id),
      ),
    [recoveries],
  )

  const openCallbackTransferIds = useMemo(
    () =>
      new Set(
        callbacks.filter((c) => c.status === 'pending' || c.status === 'escalated').map((c) => c.transfer_id),
      ),
    [callbacks],
  )

  const heldProtected = useMemo(
    () => transfers.filter((t) => t.type === 'protected' && t.status === 'held'),
    [transfers],
  )

  const reportable = useMemo(
    () =>
      transfers.filter(
        (t) =>
          t.type === 'normal' &&
          t.status === 'completed' &&
          myRole(t) === 'sender' &&
          !openRecoveryTransferIds.has(t.id),
      ),
    [transfers, walletId, openRecoveryTransferIds],
  )

  const actionCallbacks = useMemo(
    () => callbacks.filter((c) => myRole(transferMap[c.transfer_id]) === 'receiver' && c.status === 'pending'),
    [callbacks, transferMap, walletId],
  )

  const actionRecoveries = useMemo(
    () => recoveries.filter((r) => myRole(transferMap[r.transfer_id]) === 'receiver' && r.status === 'held'),
    [recoveries, transferMap, walletId],
  )

  const actionCount = actionCallbacks.length + actionRecoveries.length

  const activeDigitalOrders = useMemo(
    () => digitalOrders.filter((o) => o.status !== 'completed' && o.status !== 'refunded'),
    [digitalOrders],
  )

  const filters: { id: Filter; label: string; count?: number }[] = [
    { id: 'all', label: 'All' },
    { id: 'action', label: 'Needs you', count: actionCount },
    { id: 'held', label: 'Held', count: heldProtected.length },
    { id: 'callbacks', label: 'Callbacks', count: callbacks.length },
    { id: 'recovery', label: 'Recovery', count: recoveries.length },
  ]

  const showHeld = filter === 'all' || filter === 'held'
  const showCallbacks = filter === 'all' || filter === 'callbacks' || filter === 'action'
  const showRecovery = filter === 'all' || filter === 'recovery' || filter === 'action'

  return (
    <div className="space-y-5 pb-4">
      <Head title="Protection" />
      {auth.user?.id && (
        <TrustProtectionListener userId={auth.user.id} only={['callbacks', 'recoveries', 'transfers']} />
      )}

      <SendProtectionTabs active="protection" />

      <header className="space-y-1">
        <h1 className="font-display text-2xl font-bold tracking-tight">Protection hub</h1>
        <p className="max-w-lg text-sm text-muted">
          Recall protected payments, settle disputes, and recover money sent by mistake — your undo button for money.
        </p>
        {flash.success && <p className="text-sm text-mint">{flash.success}</p>}
        {flash.error && <p className="text-sm text-danger">{flash.error}</p>}
      </header>

      {/* Summary */}
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <StatCard label="Needs you" value={actionCount} highlight={actionCount > 0} />
        <StatCard label="Held" value={heldProtected.length} />
        <StatCard label="Callbacks" value={callbacks.length} />
        <StatCard label="Recoveries" value={recoveries.length} />
      </div>

      {/* Urgent queue */}
      {actionCount > 0 && (
        <Card className="border-amber/30 bg-amber/[0.05] p-4">
          <div className="flex items-start gap-3">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber/15 text-amber">
              <ClockIcon size={20} />
            </span>
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-text">
                {actionCount} {actionCount === 1 ? 'request needs' : 'requests need'} your response
              </p>
              <p className="mt-0.5 text-xs text-muted">
                Someone recalled a payment or reported a wrong transfer to you. Respond before the window closes.
              </p>
              <button
                type="button"
                onClick={() => setFilter('action')}
                className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-mint hover:underline"
              >
                Review now <ArrowRightIcon size={14} />
              </button>
            </div>
          </div>
        </Card>
      )}

      {/* Filters */}
      <div className="flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {filters.map((f) => (
          <button
            key={f.id}
            type="button"
            onClick={() => setFilter(f.id)}
            className={`shrink-0 rounded-full border px-3.5 py-1.5 text-xs font-semibold transition ${
              filter === f.id
                ? 'border-mint/40 bg-mint/10 text-mint'
                : 'border-line bg-surface text-muted hover:border-mint/25 hover:text-text'
            }`}
          >
            {f.label}
            {f.count !== undefined && f.count > 0 ? ` (${f.count})` : ''}
          </button>
        ))}
      </div>

      {activeDigitalOrders.length > 0 && (
        <Section title="Digital purchases & sales" hint="Guided escrow — confirm, deliver, or dispute with fair rules.">
          <div className="space-y-2 px-4 pb-4">
            {activeDigitalOrders.map((order) => (
              <DigitalOrderEscrowCard key={order.id} order={order} />
            ))}
          </div>
        </Section>
      )}

      {/* Held transfers */}
      {showHeld && (
        <Section title="Held transfers" hint="Protected money waiting to be released or recalled.">
          {heldProtected.length === 0 ? (
            <EmptyState
              title="No held transfers"
              body="When you send with protection, funds stay here until you release them or raise a callback."
              cta={{ label: 'Send protected', href: '/send' }}
            />
          ) : (
            heldProtected.map((t) => {
              const role = myRole(t)
              const hasCallback = openCallbackTransferIds.has(t.id)
              const order = orderByTransfer[t.id]
              const isDigital = t.metadata?.purpose === 'digital_item' || !!order
              const awaitingDelivery = isDigital && order?.status === 'paid_held'
              const itemTitle = order?.listing?.title ?? t.metadata?.listing_title

              return (
                <CaseCard key={t.id}>
                  <CaseHeader
                    title={
                      isDigital
                        ? role === 'sender'
                          ? `Digital purchase${itemTitle ? `: ${itemTitle}` : ''}`
                          : `Digital sale${itemTitle ? `: ${itemTitle}` : ''}`
                        : role === 'sender'
                          ? 'You sent (protected)'
                          : 'Incoming (protected)'
                    }
                    amount={t.amount}
                    meta={`${shortDate(t.created_at)}${t.hold?.expires_at ? ` · auto-releases ${shortDate(t.hold.expires_at)}` : isDigital && !t.hold?.expires_at ? ' · held until delivery' : ''}`}
                    badge={<Pill tone="amber">{awaitingDelivery ? 'awaiting delivery' : 'held'}</Pill>}
                  />
                  {isBuyerDigitalNote(role, order, awaitingDelivery)}
                  <CaseActions>
                    {role === 'sender' && !hasCallback && isDigital && (
                      <Link href="/marketplace" className="text-xs font-semibold text-mint hover:underline">
                        {order?.status === 'delivered'
                          ? 'Review & confirm in marketplace →'
                          : order?.escrow?.can_dispute_not_delivered
                            ? 'Dispute in marketplace →'
                            : 'Track in marketplace →'}
                      </Link>
                    )}
                    {role === 'sender' && !hasCallback && !isDigital && (
                      <>
                        <Button
                          variant="ghost"
                          className="px-4 py-2"
                          onClick={() =>
                            setAction({
                              title: 'Release to recipient',
                              blurb: `Pay ${ngn(t.amount)} to the recipient.`,
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
                    {role === 'sender' && !hasCallback && isDigital && order?.status === 'delivered' && (
                      <Button
                        variant="ghost"
                        className="px-4 py-2"
                        onClick={() =>
                          setAction({
                            title: 'Release to seller',
                            blurb: `Pay ${ngn(t.amount)} to the seller. This confirms you received the digital item.`,
                            confirm: 'Release funds',
                            needPin: true,
                            path: `/transfers/${t.id}/release`,
                          })
                        }
                      >
                        Quick release
                      </Button>
                    )}
                    {hasCallback && <Pill tone="mint">callback open</Pill>}
                    {role === 'receiver' && !hasCallback && awaitingDelivery && (
                      <Link href="/marketplace" className="text-xs font-semibold text-mint hover:underline">
                        Deliver in marketplace →
                      </Link>
                    )}
                    {role === 'receiver' && !hasCallback && !awaitingDelivery && <Pill tone="muted">awaiting buyer</Pill>}
                  </CaseActions>
                </CaseCard>
              )
            })
          )}
        </Section>
      )}

      {/* Callbacks */}
      {showCallbacks && (
        <Section title="Callbacks" hint="Disputes you raised or need to answer.">
          {callbacks.length === 0 ? (
            <EmptyState
              title="No callbacks yet"
              body="Callbacks let senders recall protected transfers if something goes wrong."
            />
          ) : (
            callbacks
              .filter((c) => filter !== 'action' || actionCallbacks.some((a) => a.id === c.id))
              .map((c) => {
                const t = transferMap[c.transfer_id]
                const role = myRole(t)
                const canAnswer = role === 'receiver' && c.status === 'pending'
                const isActive = c.status === 'pending' || c.status === 'escalated'
                return (
                  <CaseCard key={c.id} stack highlight={canAnswer}>
                    <CaseHeader
                      title={role === 'receiver' ? 'Payment recalled to you' : 'Your callback'}
                      amount={t?.amount}
                      meta={`${c.reason ?? 'No reason given'}${c.responds_by && c.status === 'pending' ? ` · respond by ${shortDate(c.responds_by)}` : ''}`}
                      badge={<StatusPill status={c.status} />}
                    />
                    {canAnswer && (
                      <CaseActions>
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
                      </CaseActions>
                    )}
                    <Timeline events={c.events ?? []} defaultOpen={isActive} />
                  </CaseCard>
                )
              })
          )}
        </Section>
      )}

      {/* Recovery */}
      {showRecovery && (
        <Section title="Wrong-transfer recovery" hint="Report money sent to the wrong person, or respond to a claim.">
          {filter !== 'action' && reportable.length > 0 && (
            <>
              <p className="px-5 pt-4 text-xs font-medium uppercase tracking-wide text-muted">Sent in error?</p>
              {reportable.map((t) => (
                <CaseCard key={t.id}>
                  <CaseHeader
                    title="You sent"
                    amount={t.amount}
                    meta={`${shortDate(t.created_at)} · normal transfer`}
                  />
                  <CaseActions>
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
                  </CaseActions>
                </CaseCard>
              ))}
            </>
          )}

          {recoveries.length === 0 && reportable.length === 0 && filter !== 'action' ? (
            <EmptyState
              title="No recoveries in progress"
              body="Made a mistake on a normal transfer? You can report it here within the recovery window."
            />
          ) : (
            recoveries
              .filter((r) => filter !== 'action' || actionRecoveries.some((a) => a.id === r.id))
              .map((r) => {
                const t = transferMap[r.transfer_id]
                const role = myRole(t)
                const canAnswer = role === 'receiver' && r.status === 'held'
                const isActive = r.status === 'held' || r.status === 'escalated'
                return (
                  <CaseCard key={r.id} stack highlight={canAnswer}>
                    <CaseHeader
                      title={role === 'receiver' ? 'Recovery claim on funds you received' : 'Your recovery claim'}
                      amount={r.amount}
                      meta={`${r.reason ?? '—'}${r.fee ? ` · fee ${ngn(r.fee)}` : ''}`}
                      badge={<StatusPill status={r.status} />}
                    />
                    {canAnswer && (
                      <CaseActions>
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
                      </CaseActions>
                    )}
                    <Timeline events={r.events ?? []} defaultOpen={isActive} />
                  </CaseCard>
                )
              })
          )}

          {filter === 'action' && actionRecoveries.length === 0 && actionCallbacks.length === 0 && (
            <EmptyState title="Nothing needs you right now" body="You’re all caught up on protection requests." />
          )}
        </Section>
      )}

      {/* Demo helper */}
      <Card className="border-mint/15 bg-mint/[0.04] p-4">
        <p className="text-xs font-semibold uppercase tracking-wide text-mint">How it works</p>
        <ol className="mt-2 space-y-1.5 text-xs leading-relaxed text-muted">
          <li>
            <strong className="text-text">Protected send</strong> — funds stay in escrow until the sender releases them.
          </li>
          <li>
            <strong className="text-text">Callback</strong> — sender recalls; receiver accepts (refund) or rejects (review).
          </li>
          <li>
            <strong className="text-text">Recovery</strong> — report a wrong normal transfer; eligible funds are held pending return.
          </li>
        </ol>
      </Card>

      {action && <ActionDialog action={action} onClose={() => setAction(null)} />}
    </div>
  )
}

Protection.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function SendProtectionTabs({ active }: { active: 'send' | 'protection' }) {
  return (
    <div className="inline-flex rounded-full border border-line bg-surface-2 p-1">
      <Link
        href="/send"
        className={`relative z-10 rounded-full px-5 py-2 font-display text-sm font-semibold transition-colors ${
          active === 'send' ? 'text-white' : 'text-muted hover:text-text'
        }`}
      >
        {active === 'send' && (
          <motion.span
            layoutId="send-tab"
            className="absolute inset-0 rounded-full bg-mint shadow-sm"
            transition={{ type: 'spring', stiffness: 380, damping: 32 }}
          />
        )}
        <span className="relative z-10">Send money</span>
      </Link>
      <span
        className={`relative rounded-full px-5 py-2 font-display text-sm font-semibold ${
          active === 'protection' ? 'text-white' : 'text-muted'
        }`}
      >
        {active === 'protection' && (
          <motion.span
            layoutId="send-tab"
            className="absolute inset-0 rounded-full bg-mint shadow-sm"
            transition={{ type: 'spring', stiffness: 380, damping: 32 }}
          />
        )}
        <span className="relative z-10 flex items-center gap-1.5">
          <ShieldIcon size={15} /> Protection
        </span>
      </span>
    </div>
  )
}

function ActionDialog({ action, onClose }: { action: ActionDef; onClose: () => void }) {
  const [serverError, setServerError] = useState('')

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ProtectionActionValues>({
    resolver: zodResolver(
      protectionActionSchema({ needReason: !!action.needReason, needPin: !!action.needPin }),
    ),
    defaultValues: { pin: '', reason: '' },
  })

  const submit = (values: ProtectionActionValues) => {
    setServerError('')
    router.post(action.path, values, {
      preserveScroll: true,
      onSuccess: () => onClose(),
      onError: (errs) =>
        setServerError(
          (errs.pin as string) ?? (errs.reason as string) ?? 'Something went wrong. Please try again.',
        ),
      onFinish: () => undefined,
    })
  }

  return (
    <Modal title={action.title} onClose={onClose}>
      <p className="text-sm text-muted">{action.blurb}</p>
      <form onSubmit={handleSubmit(submit)} className="mt-4 space-y-3">
        {action.needReason && (
          <RhfField
            label={action.reasonLabel ?? 'Reason'}
            placeholder="Add a short note"
            error={errors.reason?.message}
            {...register('reason')}
          />
        )}
        {action.needPin && (
          <RhfField
            label="Transaction PIN"
            type="password"
            inputMode="numeric"
            autoComplete="off"
            placeholder="••••"
            error={errors.pin?.message}
            {...register('pin')}
          />
        )}
        {serverError && <p className="text-sm text-danger">{serverError}</p>}
        <Button type="submit" loading={isSubmitting} className="w-full">
          {action.confirm}
        </Button>
      </form>
    </Modal>
  )
}

function Timeline({ events, defaultOpen = false }: { events: ProtectionEvent[]; defaultOpen?: boolean }) {
  const [open, setOpen] = useState(defaultOpen)

  return (
    <div className="mt-3 w-full border-t border-line pt-3">
      <button type="button" onClick={() => setOpen((o) => !o)} className="text-xs font-medium text-mint hover:underline">
        {open ? 'Hide timeline' : 'Show timeline'}
        {events.length > 0 ? ` (${events.length})` : ''}
      </button>
      {open && (
        <ol className="mt-3 space-y-2.5 border-l-2 border-mint/20 pl-4">
          {events.map((e) => (
            <li key={e.id} className="relative text-xs">
              <span className="absolute -left-[1.3rem] top-1.5 h-2 w-2 rounded-full bg-mint" />
              <span className="font-medium capitalize text-text">{e.action.replace(/_/g, ' ')}</span>
              {e.notes ? <span className="text-muted"> — {e.notes}</span> : null}
              <span className="block text-muted">{shortDate(e.created_at)}</span>
            </li>
          ))}
          {events.length === 0 && <li className="text-xs text-muted">No events logged yet.</li>}
        </ol>
      )}
    </div>
  )
}

function Section({ title, hint, children }: { title: string; hint: string; children: ReactNode }) {
  return (
    <section className="space-y-2">
      <div>
        <h2 className="font-display text-base font-semibold">{title}</h2>
        <p className="text-xs text-muted">{hint}</p>
      </div>
      <Card className="divide-y divide-line p-0">{children}</Card>
    </section>
  )
}

function CaseCard({
  children,
  stack,
  highlight,
}: {
  children: ReactNode
  stack?: boolean
  highlight?: boolean
}) {
  return (
    <div
      className={`px-4 py-4 sm:px-5 ${stack ? 'block' : ''} ${
        highlight ? 'bg-amber/[0.04]' : ''
      }`}
    >
      {children}
    </div>
  )
}

function CaseHeader({
  title,
  amount,
  meta,
  badge,
}: {
  title: string
  amount?: number
  meta?: string
  badge?: ReactNode
}) {
  return (
    <div className="flex items-start justify-between gap-3">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-sm font-semibold text-text">{title}</span>
          {badge}
        </div>
        {amount !== undefined && (
          <p className="mt-0.5 font-num text-lg font-bold text-text">{ngn(amount)}</p>
        )}
        {meta && <p className="mt-0.5 text-xs text-muted">{meta}</p>}
      </div>
    </div>
  )
}

function CaseActions({ children }: { children: ReactNode }) {
  return <div className="mt-3 flex flex-wrap gap-2">{children}</div>
}

function StatCard({
  label,
  value,
  highlight = false,
}: {
  label: string
  value: number
  highlight?: boolean
}) {
  return (
    <div
      className={`rounded-2xl border px-3 py-2.5 text-center ${
        highlight ? 'border-amber/35 bg-amber/[0.06]' : 'border-line bg-surface'
      }`}
    >
      <div className="text-[10px] font-medium uppercase tracking-wide text-muted">{label}</div>
      <div className={`mt-0.5 font-num text-xl font-bold ${highlight && value > 0 ? 'text-amber' : 'text-text'}`}>
        {value}
      </div>
    </div>
  )
}

function EmptyState({
  title,
  body,
  cta,
}: {
  title: string
  body: string
  cta?: { label: string; href: string }
}) {
  return (
    <div className="px-5 py-10 text-center">
      <p className="text-sm font-semibold text-text">{title}</p>
      <p className="mt-1 text-xs text-muted">{body}</p>
      {cta && (
        <Link
          href={cta.href}
          className="btn mt-4 inline-flex items-center gap-1.5 bg-mint px-4 py-2 text-sm text-white hover:bg-mint-strong"
        >
          {cta.label} <ArrowRightIcon size={14} />
        </Link>
      )}
    </div>
  )
}

function isBuyerDigitalNote(role: string, order: DigitalOrder | undefined, awaiting: boolean) {
  if (role !== 'sender' || !order) return null
  if (awaiting) {
    return (
      <p className="mt-2 text-xs text-muted">
        Waiting for seller delivery.
        {order.escrow?.can_dispute_not_delivered
          ? ' You can dispute non-delivery from the digital order card above.'
          : order.escrow?.dispute_grace_ends_at
            ? ` Disputes open after ${shortDate(order.escrow.dispute_grace_ends_at)}.`
            : ''}
      </p>
    )
  }
  if (order.delivery?.content) {
    return (
      <div className="mt-2 rounded-lg border border-mint/20 bg-mint/[0.05] p-2">
        <p className="text-[10px] font-semibold uppercase text-mint">Delivered item</p>
        {order.escrow?.listing_description && (
          <p className="mt-1 text-[11px] text-muted">Listing: {order.escrow.listing_description}</p>
        )}
        <pre className="mt-1 whitespace-pre-wrap break-all font-mono text-[11px]">{order.delivery.content}</pre>
        <p className="mt-1 text-[11px] text-muted">Use the digital order card to confirm or dispute fairly.</p>
      </div>
    )
  }
  return null
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
