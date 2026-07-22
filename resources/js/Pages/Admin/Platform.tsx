import type { FormEvent } from 'react'
import { useState } from 'react'
import { Head, useForm, usePage } from '@inertiajs/react'
import { AdminFormErrors, AdminLayout } from '@/components/AdminLayout'
import { Button, Card, Field } from '@/components/ui'
import { buildAdminUrl, useAdminBase } from '@/lib/admin'
import { ngn } from '@/lib/format'
import type { PageProps } from '@/types'

type PlatformGroup =
  | 'kyc'
  | 'pin'
  | 'callback'
  | 'recovery'
  | 'digital'
  | 'physical'
  | 'fraud'
  | 'fx'
  | 'cards'
  | 'bills'
  | 'payouts'
  | 'features'
  | 'fees'
  | 'horizon'

type GroupValues = Record<string, string | number | boolean>

type PlatformProps = PageProps<{
  groups: Record<PlatformGroup, GroupValues>
}>

const tabs: { id: PlatformGroup; label: string }[] = [
  { id: 'kyc', label: 'KYC limits' },
  { id: 'pin', label: 'Transaction PIN' },
  { id: 'callback', label: 'Callback protection' },
  { id: 'recovery', label: 'Wrong transfer' },
  { id: 'digital', label: 'Digital marketplace' },
  { id: 'physical', label: 'Physical marketplace' },
  { id: 'fraud', label: 'Fraud engine' },
  { id: 'fx', label: 'FX rates' },
  { id: 'cards', label: 'Virtual cards' },
  { id: 'bills', label: 'Bill payments' },
  { id: 'payouts', label: 'Withdrawals' },
  { id: 'features', label: 'Features' },
  { id: 'fees', label: 'Fees' },
  { id: 'horizon', label: 'Operations' },
]

function cleanInitial(values: GroupValues, group: PlatformGroup): GroupValues {
  const out: GroupValues = { group }

  for (const [key, val] of Object.entries(values)) {
    if (key.endsWith('_set')) continue
    if (typeof val === 'string' && val.includes('••••')) {
      out[key] = ''
    } else {
      out[key] = val
    }
  }

  return out
}

function KycTierFields({
  tier,
  prefix,
  form,
}: {
  tier: number
  prefix: string
  form: ReturnType<typeof useForm<GroupValues>>
}) {
  const single = `${prefix}_single_max`
  const daily = `${prefix}_daily_in_max`
  const balance = `${prefix}_balance_max`

  return (
    <div className="rounded-xl border border-line p-4">
      <div className="text-sm font-semibold">Tier {tier}</div>
      <p className="mt-1 text-xs text-muted">Amounts in kobo (minor units). ₦50,000 = 5000000.</p>
      <div className="mt-4 grid gap-4 sm:grid-cols-3">
        <Field
          label="Single transaction max"
          type="number"
          min={10000}
          value={String(form.data[single] ?? '')}
          onChange={(e) => form.setData(single, Number(e.target.value))}
          hint={typeof form.data[single] === 'number' ? ngn(form.data[single]) : undefined}
        />
        <Field
          label="Daily inflow max"
          type="number"
          min={10000}
          value={String(form.data[daily] ?? '')}
          onChange={(e) => form.setData(daily, Number(e.target.value))}
          hint={typeof form.data[daily] === 'number' ? ngn(form.data[daily]) : undefined}
        />
        <Field
          label="Wallet balance max"
          type="number"
          min={10000}
          value={String(form.data[balance] ?? '')}
          onChange={(e) => form.setData(balance, Number(e.target.value))}
          hint={typeof form.data[balance] === 'number' ? ngn(form.data[balance]) : undefined}
        />
      </div>
    </div>
  )
}

function PlatformForm({ group, initial }: { group: PlatformGroup; initial: GroupValues }) {
  const form = useForm(cleanInitial(initial, group))
  const adminBase = useAdminBase()

  const errorMessages = Object.keys(form.errors).length > 0

  function submit(e: FormEvent) {
    e.preventDefault()

    form.transform((data) => {
      const payload: GroupValues = { ...data, group }

      if (group === 'features') {
        for (const key of [
          'withdraw',
          'bills',
          'cards',
          'checkout',
          'card_pay',
          'one_time',
          'physical_listings',
        ] as const) {
          payload[key] = Boolean(payload[key])
        }
      }

      if (group === 'fees' || group === 'kyc' || group === 'pin' || group === 'fraud' || group === 'fx' || group === 'cards') {
        for (const [key, val] of Object.entries(payload)) {
          if (key === 'group' || typeof val === 'boolean' || typeof val === 'string') continue
          const n = Number(val)
          payload[key] = Number.isFinite(n) ? n : 0
        }
      }

      return payload
    })

    form.put(buildAdminUrl(adminBase, 'platform'), {
      preserveScroll: true,
      onSuccess: () => {
        window.scrollTo({ top: 0, behavior: 'smooth' })
      },
    })
  }

  return (
    <form onSubmit={submit} className="space-y-5">
      {errorMessages && <AdminFormErrors errors={form.errors} />}

      {group === 'kyc' && (
        <div className="space-y-4">
          <p className="text-sm text-muted">
            CBN-style tier limits. Tier 1 applies at signup; Tier 2/3 unlock after identity verification.
          </p>
          <KycTierFields tier={1} prefix="tier1" form={form} />
          <KycTierFields tier={2} prefix="tier2" form={form} />
          <KycTierFields tier={3} prefix="tier3" form={form} />
        </div>
      )}

      {group === 'pin' && (
        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="Max failed attempts"
            type="number"
            min={3}
            max={20}
            value={String(form.data.max_attempts ?? 5)}
            onChange={(e) => form.setData('max_attempts', Number(e.target.value))}
          />
          <Field
            label="Lockout (minutes)"
            type="number"
            min={1}
            max={1440}
            value={String(form.data.lockout_minutes ?? 15)}
            onChange={(e) => form.setData('lockout_minutes', Number(e.target.value))}
          />
        </div>
      )}

      {group === 'callback' && (
        <>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field
              label="Hold window (hours)"
              type="number"
              min={1}
              value={String(form.data.hold_hours ?? 72)}
              onChange={(e) => form.setData('hold_hours', Number(e.target.value))}
            />
            <Field
              label="Receiver response (hours)"
              type="number"
              min={1}
              value={String(form.data.response_hours ?? 72)}
              onChange={(e) => form.setData('response_hours', Number(e.target.value))}
            />
          </div>
          <label className="block">
            <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">
              Unanswered callback resolution
            </span>
            <select
              value={String(form.data.unanswered_resolution ?? 'refund')}
              onChange={(e) => form.setData('unanswered_resolution', e.target.value)}
              className="field w-full px-4 py-3 text-sm"
            >
              <option value="refund">Refund sender</option>
              <option value="release">Release to receiver</option>
            </select>
          </label>
        </>
      )}

      {group === 'recovery' && (
        <div className="grid gap-4 sm:grid-cols-3">
          <Field
            label="Report window (hours)"
            type="number"
            min={1}
            value={String(form.data.report_window_hours ?? 48)}
            onChange={(e) => form.setData('report_window_hours', Number(e.target.value))}
          />
          <Field
            label="Receiver response (hours)"
            type="number"
            min={1}
            value={String(form.data.response_hours ?? 48)}
            onChange={(e) => form.setData('response_hours', Number(e.target.value))}
          />
          <Field
            label="Recovery fee (basis points)"
            type="number"
            min={0}
            value={String(form.data.fee_bps ?? 0)}
            onChange={(e) => form.setData('fee_bps', Number(e.target.value))}
            hint="100 bps = 1%"
          />
        </div>
      )}

      {group === 'digital' && (
        <div className="grid gap-4 sm:grid-cols-3">
          <Field
            label="Buyer confirm window (hours)"
            type="number"
            min={1}
            value={String(form.data.confirm_hours ?? 48)}
            onChange={(e) => form.setData('confirm_hours', Number(e.target.value))}
          />
          <Field
            label="Delivery deadline (hours)"
            type="number"
            min={1}
            value={String(form.data.delivery_deadline_hours ?? 72)}
            onChange={(e) => form.setData('delivery_deadline_hours', Number(e.target.value))}
          />
          <Field
            label="Dispute grace (hours)"
            type="number"
            min={0}
            value={String(form.data.dispute_grace_hours ?? 24)}
            onChange={(e) => form.setData('dispute_grace_hours', Number(e.target.value))}
          />
        </div>
      )}

      {group === 'physical' && (
        <>
          <div className="grid gap-4 sm:grid-cols-3">
            <Field
              label="Ship deadline (hours)"
              type="number"
              min={1}
              value={String(form.data.ship_deadline_hours ?? 48)}
              onChange={(e) => form.setData('ship_deadline_hours', Number(e.target.value))}
            />
            <Field
              label="Buyer confirm (hours)"
              type="number"
              min={1}
              value={String(form.data.confirm_hours ?? 72)}
              onChange={(e) => form.setData('confirm_hours', Number(e.target.value))}
            />
            <Field
              label="Dispute grace (hours)"
              type="number"
              min={0}
              value={String(form.data.dispute_grace_hours ?? 48)}
              onChange={(e) => form.setData('dispute_grace_hours', Number(e.target.value))}
            />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field
              label="Verification pass score"
              type="number"
              min={0}
              max={100}
              value={String(form.data.verification_pass_score ?? 70)}
              onChange={(e) => form.setData('verification_pass_score', Number(e.target.value))}
            />
            <Field
              label="Hub verification pass score"
              type="number"
              min={0}
              max={100}
              value={String(form.data.hub_verification_pass_score ?? 80)}
              onChange={(e) => form.setData('hub_verification_pass_score', Number(e.target.value))}
            />
          </div>
          <Field
            label="Default hub name"
            value={String(form.data.default_hub_name ?? '')}
            onChange={(e) => form.setData('default_hub_name', e.target.value)}
          />
          <Field
            label="Hub address line 1"
            value={String(form.data.default_hub_line1 ?? '')}
            onChange={(e) => form.setData('default_hub_line1', e.target.value)}
          />
          <div className="grid gap-4 sm:grid-cols-3">
            <Field
              label="City"
              value={String(form.data.default_hub_city ?? '')}
              onChange={(e) => form.setData('default_hub_city', e.target.value)}
            />
            <Field
              label="State"
              value={String(form.data.default_hub_state ?? '')}
              onChange={(e) => form.setData('default_hub_state', e.target.value)}
            />
            <Field
              label="Phone"
              value={String(form.data.default_hub_phone ?? '')}
              onChange={(e) => form.setData('default_hub_phone', e.target.value)}
            />
          </div>
        </>
      )}

      {group === 'fraud' && (
        <div className="space-y-4">
          <p className="text-sm text-muted">Rule-based scoring thresholds. Changes apply immediately to new transfers.</p>
          <div className="grid gap-4 sm:grid-cols-3">
            {(
              [
                ['velocity_window_minutes', 'Velocity window (min)'],
                ['velocity_max_count', 'Velocity max count'],
                ['velocity_points', 'Velocity points'],
                ['large_amount_threshold', 'Large amount (kobo)'],
                ['large_amount_points', 'Large amount points'],
                ['new_device_points', 'New device points'],
                ['failed_pin_threshold', 'Failed PIN threshold'],
                ['failed_pin_points', 'Failed PIN points'],
                ['new_beneficiary_points', 'New beneficiary points'],
                ['medium_min', 'Medium alert min'],
                ['high_min', 'High alert min'],
                ['escalate_min', 'Escalate min'],
              ] as const
            ).map(([key, label]) => (
              <Field
                key={key}
                label={label}
                type="number"
                value={String(form.data[key] ?? '')}
                onChange={(e) => form.setData(key, Number(e.target.value))}
              />
            ))}
          </div>
        </div>
      )}

      {group === 'fx' && (
        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="USD/NGN rate (major units)"
            type="number"
            step="0.01"
            min={1}
            value={String(form.data.usd_ngn_rate ?? 1600)}
            onChange={(e) => form.setData('usd_ngn_rate', Number(e.target.value))}
            hint="e.g. 1600 = ₦1,600 per $1"
          />
          <Field
            label="FX spread (basis points)"
            type="number"
            min={0}
            value={String(form.data.spread_bps ?? 150)}
            onChange={(e) => form.setData('spread_bps', Number(e.target.value))}
          />
        </div>
      )}

      {group === 'cards' && (
        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="Card provider"
            value={String(form.data.provider ?? 'bridgecard')}
            onChange={(e) => form.setData('provider', e.target.value)}
            hint="Bridgecard is the supported issuer."
          />
          <Field
            label="Min funding NGN (kobo)"
            type="number"
            value={String(form.data.min_funding_ngn ?? '')}
            onChange={(e) => form.setData('min_funding_ngn', Number(e.target.value))}
          />
          <Field
            label="Min funding USD (cents)"
            type="number"
            value={String(form.data.min_funding_usd ?? '')}
            onChange={(e) => form.setData('min_funding_usd', Number(e.target.value))}
          />
          <Field
            label="Default USD limit"
            value={String(form.data.default_usd_limit ?? '')}
            onChange={(e) => form.setData('default_usd_limit', e.target.value)}
          />
        </div>
      )}

      {group === 'bills' && (
        <label className="block">
          <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Bill payment provider</span>
          <select
            value={String(form.data.provider ?? 'interswitch')}
            onChange={(e) => form.setData('provider', e.target.value)}
            className="field w-full px-4 py-3 text-sm"
          >
            <option value="interswitch">Interswitch Quickteller</option>
            <option value="remita">Remita RRR</option>
          </select>
          <span className="mt-1 block text-xs text-muted">
            Configure credentials under Integrations. Biller payment codes remain in config for now.
          </span>
        </label>
      )}

      {group === 'payouts' && (
        <label className="block">
          <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Withdrawal provider</span>
          <select
            value={String(form.data.provider ?? 'paystack')}
            onChange={(e) => form.setData('provider', e.target.value)}
            className="field w-full px-4 py-3 text-sm"
          >
            <option value="paystack">Paystack Transfers</option>
            <option value="alatpay">ALATPay Debit Wallet</option>
          </select>
          <span className="mt-1 block text-xs text-muted">
            Paystack is the default for bank withdrawals. Configure keys under Integrations → Paystack. ALATPay requires
            Debit Wallet enablement.
          </span>
        </label>
      )}

      {group === 'features' && (
        <div className="space-y-4">
          {(
            [
              ['withdraw', 'Bank withdrawals (Cash)', 'Cash-out to Nigerian banks. Off = Coming Soon for customers until Debit Wallet / payout access is live.'],
              ['bills', 'Bill payments', 'Airtime, data, power, TV, and betting.'],
              ['cards', 'Virtual cards', 'Bridgecard NGN / USD virtual cards.'],
              ['checkout', 'Add Money - Checkout', 'Hosted checkout (card · transfer · USSD). Requires Payment Link on the merchant.'],
              ['card_pay', 'Add Money - Card', 'Card-only payment link. Requires Payment Link on the merchant.'],
              ['one_time', 'Add Money - One-time', 'Temporary virtual account for a single amount. Permanent deposit account stays available.'],
              ['physical_listings', 'Shop - Physical listings', 'Hub-verified physical goods. Digital listings stay available when this is off.'],
            ] as const
          ).map(([key, label, hint]) => (
            <label key={key} className="flex items-start gap-3 rounded-xl border border-line bg-surface-2/40 px-4 py-3">
              <input
                type="checkbox"
                className="mt-1 size-4 accent-[var(--mint)]"
                checked={Boolean(form.data[key])}
                onChange={(e) => form.setData(key, e.target.checked)}
              />
              <span>
                <span className="block text-sm font-medium text-text">{label}</span>
                <span className="mt-0.5 block text-xs text-muted">{hint}</span>
              </span>
            </label>
          ))}
          <p className="text-xs text-muted">Off = Coming Soon page for customers. Credentials still live under Integrations.</p>
        </div>
      )}

      {group === 'fees' && (
        <div className="space-y-5">
          <p className="text-sm text-muted">
            Basis points (bps) are percent of the principal (100 bps = 1%). Flat amounts are in kobo. Leave at 0 for free rails.
          </p>
          {(
            [
              ['transfer_instant', 'Instant transfer'],
              ['transfer_protected', 'Protected transfer'],
              ['withdraw', 'Bank withdrawal'],
              ['deposit', 'Wallet deposit'],
              ['callback', 'Callback protection'],
              ['listing_publish', 'Listing publish'],
              ['marketplace_sale', 'Marketplace sale'],
              ['recovery', 'Wrong-transfer recovery'],
              ['sms_alert', 'SMS alert'],
            ] as const
          ).map(([rail, label]) => (
            <div key={rail} className="rounded-xl border border-line bg-surface-2/40 p-4">
              <p className="text-sm font-semibold text-text">{label}</p>
              <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <Field
                  label="BPS"
                  type="number"
                  min={0}
                  value={String(form.data[`${rail}_bps`] ?? 0)}
                  onChange={(e) => form.setData(`${rail}_bps`, Number(e.target.value))}
                />
                <Field
                  label="Flat (kobo)"
                  type="number"
                  min={0}
                  value={String(form.data[`${rail}_flat_minor`] ?? 0)}
                  onChange={(e) => form.setData(`${rail}_flat_minor`, Number(e.target.value))}
                  hint={rail === 'sms_alert' ? `≈ ${ngn(Number(form.data.sms_alert_flat_minor ?? 0))}` : undefined}
                />
              </div>
            </div>
          ))}
        </div>
      )}

      {group === 'horizon' && (
        <Field
          label="Horizon allowed emails"
          value={String(form.data.allowed_emails ?? '')}
          onChange={(e) => form.setData('allowed_emails', e.target.value)}
          hint="Comma-separated admin emails for queue dashboard access outside local."
        />
      )}

      <Button type="submit" loading={form.processing}>
        Save {tabs.find((t) => t.id === group)?.label.toLowerCase()}
      </Button>
    </form>
  )
}

export default function Platform() {
  const { groups } = usePage<PlatformProps>().props
  const [tab, setTab] = useState<PlatformGroup>('kyc')

  return (
    <AdminLayout>
      <Head title="Platform settings" />

      <div className="mx-auto max-w-3xl space-y-6">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Platform</h1>
          <p className="mt-1 text-sm text-muted">
            Business rules, KYC limits, fraud scoring, and marketplace timing. Encrypted at rest - env vars are fallbacks until saved here.
          </p>
        </div>

        <div className="flex gap-2 overflow-x-auto pb-1">
          {tabs.map((t) => (
            <button
              key={t.id}
              type="button"
              onClick={() => setTab(t.id)}
              className={`shrink-0 rounded-full px-4 py-2 text-sm font-medium transition ${
                tab === t.id ? 'bg-mint text-white' : 'bg-surface-2 text-muted hover:text-text'
              }`}
            >
              {t.label}
            </button>
          ))}
        </div>

        <Card className="shield-glow">
          <PlatformForm key={tab} group={tab} initial={groups[tab]} />
        </Card>
      </div>
    </AdminLayout>
  )
}
