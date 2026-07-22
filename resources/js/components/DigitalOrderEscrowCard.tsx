import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Link, router } from '@inertiajs/react'
import { useForm } from 'react-hook-form'
import { RhfField } from '@/components/forms/RhfField'
import { Button, Card, Modal, Pill } from '@/components/ui'
import { ShieldIcon } from '@/components/icons'
import { shortDate } from '@/lib/format'
import {
  confirmOrderSchema,
  deliverOrderSchema,
  disputeOrderSchema,
  shipOrderSchema,
  type ConfirmOrderValues,
  type DeliverOrderValues,
  type DisputeOrderValues,
  type ShipOrderValues,
} from '@/lib/schemas/marketplace'
import type { DigitalOrder } from '@/lib/types'

const digitalSteps = ['Paid', 'Delivered', 'Confirmed'] as const
const physicalSteps = ['Paid', 'Hub verify', 'In transit', 'Delivered', 'Confirmed'] as const

type Props = {
  order: DigitalOrder
  compact?: boolean
}

export function DigitalOrderEscrowCard({ order, compact = false }: Props) {
  const [mode, setMode] = useState<'deliver' | 'ship' | 'confirm' | 'dispute' | null>(null)
  const isBuyer = order.role === 'buyer'
  const isSeller = order.role === 'seller'
  const isPhysical = order.escrow?.item_type === 'physical' || order.listing?.item_type === 'physical'
  const title = order.listing?.title ?? (isPhysical ? 'Physical item' : 'Digital item')
  const escrow = order.escrow
  const step = escrow?.step ?? 1
  const steps = isPhysical ? physicalSteps : digitalSteps

  return (
    <Card className={`${compact ? 'p-3' : 'p-4'} ${order.status === 'disputed' ? 'border-amber/30' : ''}`}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold">{title}</p>
          <p className="text-xs text-muted">
            {isBuyer ? 'You bought' : 'You sold'} · {escrow?.step_label ?? (order.status ?? '').replace(/_/g, ' ')} ·{' '}
            {shortDate(order.created_at)}
          </p>
          {escrow?.seller_trust_score !== undefined && isBuyer && order.status === 'paid_held' && (
            <p className="mt-1 text-[11px] text-muted">
              Seller trust {escrow.seller_trust_score}/100
              {order.delivery_deadline_at ? ` · deliver by ${shortDate(order.delivery_deadline_at)}` : ''}
            </p>
          )}
        </div>
        {!compact && order.status !== 'completed' && order.status !== 'refunded' && (
          <Pill tone={order.status === 'disputed' ? 'amber' : 'mint'}>{escrow?.step_label ?? order.status}</Pill>
        )}
      </div>

      <EscrowStepper current={step} disputed={order.status === 'disputed'} steps={steps} />

      {isPhysical && escrow?.shipment && isSeller && order.status === 'awaiting_verification' && (
        <HubDropoffPanel shipment={escrow.shipment} snapshot={escrow.listing_snapshot} />
      )}

      {isPhysical && escrow?.shipment && isSeller && order.status === 'paid_held' && !escrow.shipment.dropoff_code && (
        <p className="mt-2 rounded-xl border border-amber/30 bg-amber/5 px-3 py-2 text-xs text-amber">
          Schedule hub drop-off, then bring the item to a verification hub before it ships to the buyer.
        </p>
      )}

      {escrow?.shipment && (
        <ShipmentPanel shipment={escrow.shipment} showHubReport={isBuyer || order.status === 'delivered'} />
      )}

      {isBuyer && (order.delivery?.content || (isPhysical && order.delivery?.description)) && (
        <DeliveryPanel order={order} isPhysical={isPhysical} />
      )}

      {order.status === 'paid_held' && isSeller && order.delivery_deadline_at && (
        <p className="mt-2 text-xs text-amber">
          {isPhysical
            ? `Schedule hub drop-off before ${shortDate(order.delivery_deadline_at)} - buyer is refunded if the item is not verified in time.`
            : `Deliver before ${shortDate(order.delivery_deadline_at)} - otherwise the buyer is refunded automatically.`}
        </p>
      )}

      {order.status === 'awaiting_verification' && isSeller && (
        <p className="mt-2 text-xs text-muted">
          Take the package to the verification hub with your drop-off code. They will verify it matches your listing before shipping.
        </p>
      )}

      {order.status === 'awaiting_verification' && isBuyer && (
        <p className="mt-2 text-xs text-muted">
          The hub is verifying the item against the description you accepted. You are only charged for delivery after it passes.
        </p>
      )}

      {order.status === 'shipped' && isBuyer && (
        <p className="mt-2 text-xs text-muted">
          Hub verified - your package is on the way. Confirm only after you receive and inspect the item.
        </p>
      )}

      {order.status === 'paid_held' && isBuyer && order.delivery_deadline_at && (
        <p className="mt-2 text-xs text-muted">
          If nothing is delivered by {shortDate(order.delivery_deadline_at)}, you&apos;re refunded automatically - no
          dispute needed.
          {escrow?.can_dispute_not_delivered ? ' You can also open a dispute sooner if you prefer.' : ''}
        </p>
      )}

      {order.status === 'paid_held' && isBuyer && !escrow?.can_dispute_not_delivered && escrow?.dispute_grace_ends_at && (
        <p className="mt-2 text-xs text-muted">
          Give the seller until {shortDate(escrow.dispute_grace_ends_at)} before opening an early non-delivery dispute.
        </p>
      )}

      {order.status === 'delivered' && isBuyer && escrow?.confirm_deadline_at && (
        <p className="mt-2 text-xs text-muted">
          Auto-releases to seller {shortDate(escrow.confirm_deadline_at)} if you take no action.
        </p>
      )}

      {order.status !== 'completed' && order.status !== 'refunded' && (
        <div className="mt-3 flex flex-wrap gap-2">
          {isSeller && order.status === 'paid_held' && isPhysical && (
            <Button className="px-4 py-2" onClick={() => setMode('ship')}>
              Schedule hub drop-off
            </Button>
          )}
          {isSeller && order.status === 'paid_held' && !isPhysical && (
            <Button className="px-4 py-2" onClick={() => setMode('deliver')}>
              Mark delivered
            </Button>
          )}
          {isBuyer && order.status === 'delivered' && (
            <>
              <Button className="px-4 py-2" onClick={() => setMode('confirm')}>
                Matches listing - release pay
              </Button>
              <Button variant="ghost" className="px-4 py-2" onClick={() => setMode('dispute')}>
                Something&apos;s wrong
              </Button>
            </>
          )}
          {isBuyer && (order.status === 'paid_held' || order.status === 'awaiting_verification' || order.status === 'shipped') && escrow?.can_dispute_not_delivered && (
            <Button variant="ghost" className="px-4 py-2" onClick={() => setMode('dispute')}>
              Item not delivered
            </Button>
          )}
          {(order.status === 'paid_held' || order.status === 'awaiting_verification' || order.status === 'shipped' || order.status === 'delivered' || order.status === 'disputed') && (
            <Link href="/protection" className="btn inline-flex items-center bg-surface-2 px-4 py-2 text-sm text-mint">
              Protection hub
            </Link>
          )}
        </div>
      )}

      {mode === 'ship' && <ShipModal order={order} onClose={() => setMode(null)} />}
      {mode === 'deliver' && <DeliverModal order={order} onClose={() => setMode(null)} />}
      {mode === 'confirm' && <ConfirmModal order={order} onClose={() => setMode(null)} />}
      {mode === 'dispute' && <DisputeModal order={order} onClose={() => setMode(null)} />}
    </Card>
  )
}

function EscrowStepper({
  current,
  disputed,
  steps,
}: {
  current: number
  disputed: boolean
  steps: readonly string[]
}) {
  return (
    <ol className="mt-3 flex items-center gap-1">
      {steps.map((label, i) => {
        const n = i + 1
        const done = disputed ? n < steps.length : n < current
        const active = !disputed && n === Math.min(current, steps.length)
        return (
          <li key={label} className="flex flex-1 items-center gap-1">
            <span
              className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ${
                done ? 'bg-mint text-white' : active ? 'bg-mint/15 text-mint ring-2 ring-mint/30' : 'bg-surface-2 text-muted'
              }`}
            >
              {done ? '✓' : n}
            </span>
            <span className={`hidden text-[10px] font-medium sm:inline ${active ? 'text-text' : 'text-muted'}`}>{label}</span>
            {i < steps.length - 1 && <span className="mx-1 h-px flex-1 bg-line" />}
          </li>
        )
      })}
      {disputed && (
        <Pill tone="amber">
          Dispute
        </Pill>
      )}
    </ol>
  )
}

function HubDropoffPanel({
  shipment,
  snapshot,
}: {
  shipment: NonNullable<NonNullable<DigitalOrder['escrow']>['shipment']>
  snapshot?: Record<string, unknown> | null
}) {
  const hub = shipment.hub_address

  return (
    <div className="mt-3 space-y-3 rounded-xl border border-mint/30 bg-mint/[0.06] p-4">
      <p className="text-xs font-semibold uppercase tracking-wide text-mint">Take item to verification hub</p>
      <div className="rounded-lg border border-line bg-surface p-3">
        <p className="font-display text-lg font-bold tracking-widest text-text">{shipment.dropoff_code ?? '-'}</p>
        <p className="mt-1 text-[11px] text-muted">Show this drop-off code at the hub</p>
      </div>
      <div className="text-sm">
        <p className="font-semibold text-text">{shipment.hub_name}</p>
        {hub && (
          <p className="mt-1 text-xs text-muted">
            {hub.line1}, {hub.city}, {hub.state}
            {hub.phone ? ` · ${hub.phone}` : ''}
          </p>
        )}
      </div>
      {snapshot && (
        <div className="rounded-lg border border-line/80 bg-surface-2 px-3 py-2 text-[11px] text-muted">
          <p className="font-semibold text-text">Hub will verify against</p>
          <p className="mt-1 line-clamp-3">{String(snapshot.description ?? '')}</p>
          {typeof snapshot.specs === 'object' && snapshot.specs !== null ? (
            <ul className="mt-1 space-y-0.5">
              {Object.entries(snapshot.specs as Record<string, string>).map(([k, v]) => (
                <li key={k}>
                  <span className="capitalize">{k}:</span> {v}
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      )}
    </div>
  )
}

function ShipmentPanel({
  shipment,
  showHubReport = false,
}: {
  shipment: NonNullable<NonNullable<DigitalOrder['escrow']>['shipment']>
  showHubReport?: boolean
}) {
  const hubPassed = shipment.hub_verification_status === 'passed'
  const hubFailed = shipment.hub_verification_status === 'failed'

  return (
    <div className="mt-3 space-y-2 rounded-xl border border-line bg-surface-2 p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-xs font-semibold uppercase tracking-wide text-mint">
          {shipment.carrier} · {shipment.tracking_number}
        </p>
        {hubPassed && (
          <span className="rounded-full bg-mint/15 px-2 py-0.5 text-[10px] font-semibold text-mint">Hub verified</span>
        )}
        {hubFailed && (
          <span className="rounded-full bg-danger/10 px-2 py-0.5 text-[10px] font-semibold text-danger">Hub failed</span>
        )}
      </div>
      <p className="text-sm font-medium text-text">{shipment.status_label}</p>
      <ol className="space-y-1.5 border-l-2 border-mint/20 pl-3">
        {(shipment.events ?? []).map((event) => (
          <li key={`${event.at}-${event.status}`} className="text-[11px] text-muted">
            <span className="font-medium text-text">{(event.status ?? '').replace(/_/g, ' ')}</span>
            <span className="mx-1">·</span>
            {shortDate(event.at)}
            <span className="mt-0.5 block">{event.note}</span>
          </li>
        ))}
      </ol>
      {showHubReport && shipment.hub_verification_report && (
        <div className="mt-2 rounded-lg border border-line bg-surface px-3 py-2">
          <p className="text-[10px] font-semibold uppercase text-muted">
            Hub inspection {shipment.hub_verification_score ?? 0}/100
          </p>
          <p className="mt-1 text-xs text-text">
            {(shipment.hub_verification_report as { inspector_notes?: string }).inspector_notes}
          </p>
        </div>
      )}
    </div>
  )
}

function DeliveryPanel({ order, isPhysical }: { order: DigitalOrder; isPhysical: boolean }) {
  return (
    <div className="mt-3 space-y-2 rounded-xl border border-mint/25 bg-mint/[0.06] p-3">
      <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-mint">
        <ShieldIcon size={14} /> {isPhysical ? 'Compare to your order' : 'What you received'}
      </p>
      {order.escrow?.listing_description && (
        <div className="rounded-lg border border-line bg-surface p-2">
          <p className="text-[10px] font-semibold uppercase text-muted">Locked order description</p>
          <p className="mt-1 text-xs text-text">{order.escrow.listing_description}</p>
        </div>
      )}
      {!isPhysical && order.delivery?.content && (
        <pre className="whitespace-pre-wrap break-all rounded-lg border border-line bg-surface p-2 font-mono text-xs text-text">
          {order.delivery.content}
        </pre>
      )}
      {order.delivery?.integrity_verified && (
        <p className="text-[11px] text-mint">
          {isPhysical
            ? 'Description integrity verified against your purchase snapshot.'
            : 'Delivery verified - matches what the seller attested.'}
        </p>
      )}
    </div>
  )
}

function ShipModal({ order, onClose }: { order: DigitalOrder; onClose: () => void }) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ShipOrderValues>({
    resolver: zodResolver(shipOrderSchema),
  })

  return (
    <Modal title="Schedule verification hub drop-off" onClose={onClose} wide>
      <p className="text-sm text-muted">
        After scheduling, take the physical item to a verification hub. They verify it matches the locked
        order description before it ships to the buyer. Funds stay in escrow until verification passes.
      </p>
      <form
        onSubmit={handleSubmit((values) => {
          router.post(`/marketplace/orders/${order.id}/ship`, {
            ...values,
            attest_matches_listing: true,
          }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
          })
        })}
        className="mt-4 space-y-3"
      >
        <RhfField label="Pickup street" error={errors.pickup_line1?.message} {...register('pickup_line1')} />
        <div className="grid gap-3 sm:grid-cols-2">
          <RhfField label="City" error={errors.pickup_city?.message} {...register('pickup_city')} />
          <RhfField label="State" error={errors.pickup_state?.message} {...register('pickup_state')} />
        </div>
        <RhfField label="Pickup phone" error={errors.pickup_phone?.message} {...register('pickup_phone')} />
        <label className="flex items-start gap-2 rounded-xl border border-line bg-surface-2 p-3 text-sm">
          <input type="checkbox" className="mt-1" {...register('attest_matches_listing')} />
          <span>The package matches my listing description exactly - brand, condition, and specs.</span>
        </label>
        {errors.attest_matches_listing && (
          <p className="text-sm text-danger">{errors.attest_matches_listing.message}</p>
        )}
        <Button type="submit" loading={isSubmitting} className="w-full">
          Schedule hub drop-off
        </Button>
      </form>
    </Modal>
  )
}

function DeliverModal({ order, onClose }: { order: DigitalOrder; onClose: () => void }) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<DeliverOrderValues>({
    resolver: zodResolver(deliverOrderSchema),
  })

  const submit = () => {
    router.post(
      `/marketplace/orders/${order.id}/deliver`,
      { attest_matches_listing: true },
      { preserveScroll: true, onSuccess: () => onClose() },
    )
  }

  return (
    <Modal title="Deliver to buyer" onClose={onClose}>
      <p className="text-sm text-muted">
        The buyer will see your delivery content and compare it to your listing. Only deliver when it is ready and
        accurate.
      </p>
      <form onSubmit={handleSubmit(submit)} className="mt-4 space-y-3">
        <label className="flex items-start gap-2 rounded-xl border border-line bg-surface-2 p-3 text-sm">
          <input type="checkbox" className="mt-1" {...register('attest_matches_listing')} />
          <span>I confirm the delivery matches my listing description exactly.</span>
        </label>
        {errors.attest_matches_listing && (
          <p className="text-sm text-danger">{errors.attest_matches_listing.message}</p>
        )}
        <Button type="submit" loading={isSubmitting} className="w-full">
          Deliver now
        </Button>
      </form>
    </Modal>
  )
}

function ConfirmModal({ order, onClose }: { order: DigitalOrder; onClose: () => void }) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ConfirmOrderValues>({
    resolver: zodResolver(confirmOrderSchema),
    defaultValues: { pin: '' },
  })

  const submit = (values: ConfirmOrderValues) => {
    router.post(`/marketplace/orders/${order.id}/confirm`, values, {
      onSuccess: () => onClose(),
    })
  }

  return (
    <Modal title="Release payment to seller" onClose={onClose}>
      <p className="text-sm text-muted">
        Only confirm if the item matches the listing and works as described. Payment goes to the seller immediately.
      </p>
      <form onSubmit={handleSubmit(submit)} className="mt-4 space-y-3">
        <RhfField
          label="Transaction PIN"
          type="password"
          inputMode="numeric"
          autoComplete="off"
          error={errors.pin?.message}
          {...register('pin')}
        />
        <Button type="submit" loading={isSubmitting} className="w-full">
          Confirm &amp; pay seller
        </Button>
      </form>
    </Modal>
  )
}

function DisputeModal({ order, onClose }: { order: DigitalOrder; onClose: () => void }) {
  const allowed = order.escrow?.allowed_disputes ?? []
  const defaultCategory = allowed[0]?.value ?? 'not_delivered'

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<DisputeOrderValues>({
    resolver: zodResolver(disputeOrderSchema),
    defaultValues: { pin: '', category: defaultCategory as DisputeOrderValues['category'], details: '' },
  })

  const category = watch('category')
  const selected = allowed.find((d) => d.value === category)

  const submit = (values: DisputeOrderValues) => {
    router.post(`/marketplace/orders/${order.id}/dispute`, values, {
      onSuccess: () => onClose(),
    })
  }

  return (
    <Modal title="Open a dispute" onClose={onClose}>
      <p className="text-sm text-muted">
        Pick the closest reason. The seller can accept (refund you) or reject (Reton reviews with fair escrow rules).
      </p>
      <form onSubmit={handleSubmit(submit)} className="mt-4 space-y-3">
        <fieldset className="space-y-2">
          <legend className="text-xs font-medium uppercase tracking-wide text-muted">What went wrong?</legend>
          {allowed.map((d) => (
            <label
              key={d.value}
              className={`flex cursor-pointer gap-2 rounded-xl border p-3 text-sm ${
                category === d.value ? 'border-mint/40 bg-mint/[0.06]' : 'border-line'
              }`}
            >
              <input type="radio" value={d.value} {...register('category')} className="mt-1" />
              <span>
                <span className="block font-medium">{d.label}</span>
                <span className="block text-xs text-muted">{d.hint}</span>
              </span>
            </label>
          ))}
        </fieldset>
        {selected && (
          <p className="rounded-lg bg-surface-2 px-3 py-2 text-xs text-muted">{selected.hint}</p>
        )}
        <label className="block">
          <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Details</span>
          <textarea className="field w-full px-4 py-3 text-sm" rows={3} {...register('details')} />
          {errors.details && <p className="mt-1 text-sm text-danger">{errors.details.message}</p>}
        </label>
        <RhfField
          label="Transaction PIN"
          type="password"
          inputMode="numeric"
          autoComplete="off"
          error={errors.pin?.message}
          {...register('pin')}
        />
        <Button type="submit" loading={isSubmitting} variant="ghost" className="w-full border border-danger/30 text-danger">
          Open dispute
        </Button>
      </form>
    </Modal>
  )
}
