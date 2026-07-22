import type { FormEvent } from 'react'
import { useState } from 'react'
import { Head, router, useForm, usePage } from '@inertiajs/react'
import { AdminFormErrors, AdminLayout } from '@/components/AdminLayout'
import { Button, Card, CopyRow, Field, Pill } from '@/components/ui'
import { CheckIcon } from '@/components/icons'
import { buildAdminUrl, useAdminBase } from '@/lib/admin'
import type { PageProps } from '@/types'

type IntegrationGroup = 'alatpay' | 'paystack' | 'interswitch' | 'bridgecard' | 'giglogistics' | 'dojah' | 'remita' | 'termii'

type GroupValues = Record<string, string | number | boolean>

type IntegrationsProps = PageProps<{
  integrations: Record<IntegrationGroup, GroupValues & { ready: boolean }>
  webhookUrls: Record<string, string>
  docsUrls?: Record<string, string>
}>

const tabs: { id: IntegrationGroup; label: string }[] = [
  { id: 'alatpay', label: 'ALATPay' },
  { id: 'paystack', label: 'Paystack (Withdraw)' },
  { id: 'interswitch', label: 'Interswitch (Bills)' },
  { id: 'remita', label: 'Remita (RRR)' },
  { id: 'bridgecard', label: 'Bridgecard (Cards)' },
  { id: 'dojah', label: 'Dojah (NIN / Tier 3)' },
  { id: 'termii', label: 'Termii (SMS)' },
  { id: 'giglogistics', label: 'Giglogistics' },
]

function SecretField({
  label,
  name,
  value,
  isSet,
  onChange,
  hint,
}: {
  label: string
  name: string
  value: string
  isSet?: boolean
  onChange: (v: string) => void
  hint?: string
}) {
  const [show, setShow] = useState(false)

  return (
    <label className="block">
      <span className="mb-1.5 flex items-center justify-between text-xs font-medium uppercase tracking-wide text-muted">
        {label}
        {isSet && (
          <button type="button" onClick={() => setShow((s) => !s)} className="normal-case text-mint hover:underline">
            {show ? 'Hide' : 'Replace'}
          </button>
        )}
      </span>
      <input
        type={show ? 'text' : 'password'}
        name={name}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={isSet && !show ? '•••••••• (saved - type to replace)' : 'Enter secret'}
        className="field w-full px-4 py-3 font-mono text-sm"
        autoComplete="off"
      />
      {hint && <span className="mt-1 block text-xs text-muted">{hint}</span>}
    </label>
  )
}

function cleanInitial(values: GroupValues & { ready: boolean }, group: IntegrationGroup): GroupValues {
  const { ready: _r, ...rest } = values
  const out: GroupValues = { integration: group }

  for (const [key, val] of Object.entries(rest)) {
    if (key.endsWith('_set')) continue
    if (typeof val === 'string' && val.includes('••••')) {
      out[key] = ''
    } else {
      out[key] = val
    }
  }

  return out
}

function IntegrationForm({
  group,
  initial,
  webhookUrl,
}: {
  group: IntegrationGroup
  initial: GroupValues & { ready: boolean }
  webhookUrl?: string
}) {
  const form = useForm(cleanInitial(initial, group))
  const adminBase = useAdminBase()

  function submit(e: FormEvent) {
    e.preventDefault()
    form.transform((data) => ({ ...data, integration: group }))
    form.post(buildAdminUrl(adminBase, 'integrations/save'), {
      preserveScroll: true,
      onSuccess: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    })
  }

  function testConnection() {
    router.post(buildAdminUrl(adminBase, `integrations/${group}/test`), {}, { preserveScroll: true })
  }

  function syncVaDeposits() {
    router.post(buildAdminUrl(adminBase, 'integrations/alatpay/sync-deposits'), {}, { preserveScroll: true })
  }

  const driver = String(form.data.driver ?? 'http')
  const hasErrors = Object.keys(form.errors).length > 0

  return (
    <form onSubmit={submit} className="space-y-5">
      <div className="flex flex-wrap items-center gap-2">
        {initial.ready ? (
          <Pill tone="mint">
            <CheckIcon size={12} /> Ready
          </Pill>
        ) : (
          <Pill tone="amber">Incomplete</Pill>
        )}
        <Pill tone="muted">{driver} driver</Pill>
      </div>

      {hasErrors && <AdminFormErrors errors={form.errors} />}

      <label className="block">
        <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Driver</span>
        <select
          value={driver}
          onChange={(e) => form.setData('driver', e.target.value)}
          className="field w-full px-4 py-3 text-sm"
        >
          <option value="fake">Demo / fake (no live API)</option>
          <option value="http">Live HTTP API</option>
        </select>
      </label>

      <Field
        label="Base URL"
        value={String(form.data.base_url ?? '')}
        onChange={(e) => form.setData('base_url', e.target.value)}
        hint={
          group === 'alatpay'
            ? 'Official host: https://apibox.alatpay.ng - do not use api.alatpay.ng'
            : undefined
        }
      />

      {group === 'alatpay' && (
        <>
          <Field
            label="Merchant email"
            type="email"
            value={String(form.data.merchant_email ?? '')}
            onChange={(e) => form.setData('merchant_email', e.target.value)}
            hint="ALATPay merchant portal login email (required for Static Wallet API session)."
          />
          <SecretField
            label="Merchant password"
            name="merchant_password"
            value={String(form.data.merchant_password ?? '')}
            isSet={!!form.data.merchant_password_set}
            onChange={(v) => form.setData('merchant_password', v)}
            hint="ALATPay merchant portal password. Reton logs in to start an API session before Static Wallet calls."
          />
          <Field
            label="Business ID"
            value={String(form.data.business_id ?? '')}
            onChange={(e) => form.setData('business_id', e.target.value)}
            hint="UUID of your business in the ALATPay merchant portal."
          />
          <SecretField
            label="Bootstrap subscription key (optional)"
            name="api_key"
            value={String(form.data.api_key ?? '')}
            isSet={!!form.data.api_key_set}
            onChange={(v) => form.setData('api_key', v)}
            hint="Optional. Reton prefers subscriptionPrimaryKey returned by merchant login for your Business ID."
          />
          <SecretField
            label="Business BVN"
            name="business_bvn"
            value={String(form.data.business_bvn ?? '')}
            isSet={!!form.data.business_bvn_set}
            onChange={(v) => form.setData('business_bvn', v)}
            hint="Director / shareholder BVN for Collection wallets only - not used for customer Tier-2 BVN OTP."
          />
          <SecretField
            label="Webhook secret"
            name="webhook_secret"
            value={String(form.data.webhook_secret ?? '')}
            isSet={!!form.data.webhook_secret_set}
            onChange={(v) => form.setData('webhook_secret', v)}
          />
          <Field
            label="Timeout (seconds)"
            type="number"
            min={5}
            max={120}
            value={String(form.data.timeout ?? 15)}
            onChange={(e) => form.setData('timeout', Number(e.target.value))}
          />
          {webhookUrl && (
            <div className="rounded-xl border border-line bg-surface-2/50 px-4">
              <CopyRow label="Webhook URL (register in ALATPay)" value={webhookUrl} mono />
            </div>
          )}
          <p className="rounded-xl border border-mint/20 bg-mint/[0.04] px-4 py-3 text-xs leading-relaxed text-muted">
            <span className="font-semibold text-text">BVN verification</span> logs into ALATPay with your merchant
            email/password, uses the session + subscriptionPrimaryKey, then creates an Individual Static Wallet OTP for
            the customer BVN. Test connection exercises that same path.
          </p>
        </>
      )}

      {group === 'paystack' && (
        <>
          <SecretField
            label="Secret key"
            name="secret_key"
            value={String(form.data.secret_key ?? '')}
            isSet={!!form.data.secret_key_set}
            onChange={(v) => form.setData('secret_key', v)}
            hint="sk_live_… or sk_test_… from Paystack Settings → API Keys."
          />
          <Field
            label="Public key"
            value={String(form.data.public_key ?? '')}
            onChange={(e) => form.setData('public_key', e.target.value)}
            hint="Optional pk_live_… / pk_test_… (not required for Transfers)."
          />
          <SecretField
            label="Webhook secret"
            name="webhook_secret"
            value={String(form.data.webhook_secret ?? '')}
            isSet={!!form.data.webhook_secret_set}
            onChange={(v) => form.setData('webhook_secret', v)}
            hint="Optional. Defaults to the secret key when empty - HMAC SHA512 on x-paystack-signature."
          />
          <Field
            label="Timeout (seconds)"
            type="number"
            min={5}
            max={120}
            value={String(form.data.timeout ?? 15)}
            onChange={(e) => form.setData('timeout', Number(e.target.value))}
          />
          {webhookUrl && (
            <div className="rounded-xl border border-line bg-surface-2/50 px-4">
              <CopyRow label="Webhook URL (register in Paystack)" value={webhookUrl} mono />
            </div>
          )}
          <p className="rounded-xl border border-mint/20 bg-mint/[0.04] px-4 py-3 text-xs leading-relaxed text-muted">
            <span className="font-semibold text-text">Bank withdrawals</span> use Paystack Transfers. Disable Transfer OTP
            in the Paystack dashboard for automated payouts, fund your Paystack balance, and set Platform → Payouts to
            Paystack. Enable the withdraw feature under Platform → Features.
          </p>
        </>
      )}

        {group === 'interswitch' && (
          <>
            <Field
              label="OAuth / Passport URL"
              value={String(form.data.passport_url ?? '')}
              onChange={(e) => form.setData('passport_url', e.target.value)}
              hint="Production: https://passport.interswitchng.com/passport/oauth/token"
            />
            <Field
              label="Quickteller VAS base URL"
              value={String(form.data.base_url ?? '')}
              onChange={(e) => form.setData('base_url', e.target.value)}
              hint="Production: https://interswitchng.com/quicktellerservice/api/v5"
            />
            <Field
              label="Terminal ID"
              value={String(form.data.terminal_id ?? '')}
              onChange={(e) => form.setData('terminal_id', e.target.value)}
            />
            <SecretField
              label="Client ID"
              name="client_id"
              value={String(form.data.client_id ?? '')}
              isSet={!!form.data.client_id_set}
              onChange={(v) => form.setData('client_id', v)}
            />
            <SecretField
              label="Client secret"
              name="client_secret"
              value={String(form.data.client_secret ?? '')}
              isSet={!!form.data.client_secret_set}
              onChange={(v) => form.setData('client_secret', v)}
            />
            <Field
              label="Request reference prefix"
              value={String(form.data.request_reference_prefix ?? '1453')}
              onChange={(e) => form.setData('request_reference_prefix', e.target.value)}
              hint="Max 20-char transaction refs for Quickteller (e.g. 1453)."
            />
            <Field
              label="Timeout (seconds)"
              type="number"
              min={5}
              max={120}
              value={String(form.data.timeout ?? 15)}
              onChange={(e) => form.setData('timeout', Number(e.target.value))}
            />
            <p className="text-xs text-muted">
              Bill payments only - airtime, data, power, TV & betting via{' '}
              <a
                href="https://docs.interswitchgroup.com/docs/bills-payment-1"
                target="_blank"
                rel="noreferrer"
                className="text-mint hover:underline"
              >
                Quickteller VAS
              </a>
              . Virtual cards are issued via Bridgecard.
            </p>
          </>
        )}

        {group === 'bridgecard' && (
          <>
            <Field
              label="Issuing API base URL"
              value={String(form.data.base_url ?? '')}
              onChange={(e) => form.setData('base_url', e.target.value)}
              hint="Sandbox: https://issuecards.api.bridgecard.co/v1/issuing/sandbox"
            />
            <SecretField
              label="Access token"
              name="access_token"
              value={String(form.data.access_token ?? '')}
              isSet={!!form.data.access_token_set}
              onChange={(v) => form.setData('access_token', v)}
            />
            <SecretField
              label="Secret key (PIN encryption)"
              name="secret_key"
              value={String(form.data.secret_key ?? '')}
              isSet={!!form.data.secret_key_set}
              onChange={(v) => form.setData('secret_key', v)}
              hint="Test keys start with test - used to AES-encrypt card PINs."
            />
            <Field
              label="Timeout (seconds)"
              type="number"
              min={5}
              max={120}
              value={String(form.data.timeout ?? 20)}
              onChange={(e) => form.setData('timeout', Number(e.target.value))}
            />
            <p className="text-xs text-muted">
              NGN & USD virtual cards, FX wallet funding, freeze/unfreeze -{' '}
              <a href="https://docs.bridgecard.co/" target="_blank" rel="noreferrer" className="text-mint hover:underline">
                Bridgecard Issuing API
              </a>
              .
            </p>
          </>
        )}

        {group === 'giglogistics' && (
        <>
          <SecretField
            label="API key"
            name="api_key"
            value={String(form.data.api_key ?? '')}
            isSet={!!form.data.api_key_set}
            onChange={(v) => form.setData('api_key', v)}
          />
          <SecretField
            label="Webhook secret"
            name="webhook_secret"
            value={String(form.data.webhook_secret ?? '')}
            isSet={!!form.data.webhook_secret_set}
            onChange={(v) => form.setData('webhook_secret', v)}
          />
          <Field
            label="Fake advance minutes (demo driver)"
            type="number"
            min={0}
            max={1440}
            value={String(form.data.fake_advance_minutes ?? 1)}
            onChange={(e) => form.setData('fake_advance_minutes', Number(e.target.value))}
            hint="Only used when driver is fake - simulates shipment stages."
          />
          {webhookUrl && (
            <div className="rounded-xl border border-line bg-surface-2/50 px-4">
              <CopyRow label="Webhook URL" value={webhookUrl} mono />
            </div>
          )}
        </>
      )}

        {group === 'dojah' && (
          <>
            <Field
              label="API base URL"
              value={String(form.data.base_url ?? '')}
              onChange={(e) => form.setData('base_url', e.target.value)}
              hint="Sandbox: https://sandbox.dojah.io - Production: https://api.dojah.io"
            />
            <SecretField
              label="App ID"
              name="app_id"
              value={String(form.data.app_id ?? '')}
              isSet={!!form.data.app_id_set}
              onChange={(v) => form.setData('app_id', v)}
            />
            <SecretField
              label="Secret key"
              name="secret_key"
              value={String(form.data.secret_key ?? '')}
              isSet={!!form.data.secret_key_set}
              onChange={(v) => form.setData('secret_key', v)}
            />
            <Field
              label="Timeout (seconds)"
              type="number"
              min={5}
              max={120}
              value={String(form.data.timeout ?? 20)}
              onChange={(e) => form.setData('timeout', Number(e.target.value))}
            />
            <p className="text-xs text-muted">
              Optional Tier 3 NIN verification - BVN for funding is handled by ALATPay.{' '}
              <a href="https://docs.dojah.io" target="_blank" rel="noreferrer" className="text-mint hover:underline">
                Dojah API
              </a>
              .
            </p>
          </>
        )}

        {group === 'remita' && (
          <>
            <Field
              label="Base URL"
              value={String(form.data.base_url ?? '')}
              onChange={(e) => form.setData('base_url', e.target.value)}
            />
            <Field
              label="Merchant ID"
              value={String(form.data.merchant_id ?? '')}
              onChange={(e) => form.setData('merchant_id', e.target.value)}
            />
            <SecretField
              label="API key"
              name="api_key"
              value={String(form.data.api_key ?? '')}
              isSet={!!form.data.api_key_set}
              onChange={(v) => form.setData('api_key', v)}
            />
            <SecretField
              label="API secret"
              name="api_secret"
              value={String(form.data.api_secret ?? '')}
              isSet={!!form.data.api_secret_set}
              onChange={(v) => form.setData('api_secret', v)}
            />
            <Field
              label="Timeout (seconds)"
              type="number"
              min={5}
              max={120}
              value={String(form.data.timeout ?? 15)}
              onChange={(e) => form.setData('timeout', Number(e.target.value))}
            />
            <p className="text-xs text-muted">
              Remita RRR bill payments - select Remita under Platform → Bill payments when live.
            </p>
          </>
        )}

        {group === 'termii' && (
          <>
            <Field
              label="API base URL"
              value={String(form.data.base_url ?? '')}
              onChange={(e) => form.setData('base_url', e.target.value)}
              hint="Production: https://api.ng.termii.com"
            />
            <SecretField
              label="API key"
              name="api_key"
              value={String(form.data.api_key ?? '')}
              isSet={!!form.data.api_key_set}
              onChange={(v) => form.setData('api_key', v)}
            />
            <Field
              label="Sender ID"
              value={String(form.data.sender_id ?? '')}
              onChange={(e) => form.setData('sender_id', e.target.value)}
              hint="Approved Termii sender name (max 11 characters)."
            />
            <Field
              label="SMS channel"
              value={String(form.data.channel ?? 'generic')}
              onChange={(e) => form.setData('channel', e.target.value)}
              hint="generic or dnd for Nigeria."
            />
            <Field
              label="Timeout (seconds)"
              type="number"
              min={5}
              max={120}
              value={String(form.data.timeout ?? 15)}
              onChange={(e) => form.setData('timeout', Number(e.target.value))}
            />
            <p className="text-xs text-muted">
              Enable delivery under Site → SMS & OTP. WhatsApp OTP is toggled there when Termii WhatsApp is active.
            </p>
          </>
        )}

      <div className="flex flex-wrap gap-3 pt-2">
        <Button type="submit" loading={form.processing}>
          Save {group}
        </Button>
        {group === 'alatpay' && driver === 'http' && (
          <>
            <Button type="button" variant="ghost" onClick={testConnection}>
              Test connection
            </Button>
            <Button type="button" variant="ghost" onClick={syncVaDeposits}>
              Sync VA deposits
            </Button>
          </>
        )}
        {group === 'alatpay' && driver === 'fake' && (
          <p className="w-full text-xs text-amber">
            Driver is Demo/fake - live NIP deposits will not credit Reton wallets. Switch to Live HTTP, save, then Sync VA
            deposits.
          </p>
        )}
        {group === 'paystack' && driver === 'http' && (
          <Button type="button" variant="ghost" onClick={testConnection}>
            Test Paystack
          </Button>
        )}
        {group === 'interswitch' && driver === 'http' && (
          <Button type="button" variant="ghost" onClick={testConnection}>
            Test Quickteller
          </Button>
        )}
        {group === 'bridgecard' && driver === 'http' && (
          <Button type="button" variant="ghost" onClick={testConnection}>
            Test Bridgecard
          </Button>
        )}
        {group === 'termii' && driver === 'http' && (
          <Button type="button" variant="ghost" onClick={testConnection}>
            Test Termii
          </Button>
        )}
        {group === 'dojah' && driver === 'http' && (
          <Button type="button" variant="ghost" onClick={testConnection}>
            Verify Dojah config
          </Button>
        )}
        {group === 'remita' && driver === 'http' && (
          <Button type="button" variant="ghost" onClick={testConnection}>
            Verify Remita config
          </Button>
        )}
      </div>
    </form>
  )
}

export default function Integrations() {
  const { integrations, webhookUrls } = usePage<IntegrationsProps>().props
  const [tab, setTab] = useState<IntegrationGroup>('alatpay')

  return (
    <AdminLayout>
      <Head title="Integrations" />

      <div className="mx-auto max-w-2xl space-y-6">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Integrations</h1>
          <p className="mt-1 text-sm text-muted">
            Store payment, KYC, card, and logistics credentials securely. Values are encrypted at rest - env vars are fallbacks until saved here.
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
          <IntegrationForm
            key={tab}
            group={tab}
            initial={integrations[tab]}
            webhookUrl={webhookUrls[tab]}
          />
        </Card>
      </div>
    </AdminLayout>
  )
}
