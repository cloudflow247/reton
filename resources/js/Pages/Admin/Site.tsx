import type { FormEvent } from 'react'
import { useState } from 'react'
import { Head, router, useForm, usePage } from '@inertiajs/react'
import { AdminLayout } from '@/components/AdminLayout'
import { Button, Card, Field } from '@/components/ui'
import { buildAdminUrl, useAdminBase } from '@/lib/admin'
import type { PageProps } from '@/types'

type SiteGroup = 'mail' | 'sms' | 'seo' | 'security'
type GroupValues = Record<string, string | number | boolean>

type SiteProps = PageProps<{
  groups: Record<SiteGroup, GroupValues>
}>

const tabs: { id: SiteGroup; label: string }[] = [
  { id: 'mail', label: 'Email' },
  { id: 'sms', label: 'SMS & OTP' },
  { id: 'seo', label: 'SEO & social' },
  { id: 'security', label: 'Security' },
]

function cleanInitial(values: GroupValues, group: SiteGroup): GroupValues {
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

function SecretField({
  label,
  value,
  isSet,
  onChange,
}: {
  label: string
  value: string
  isSet?: boolean
  onChange: (v: string) => void
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
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={isSet && !show ? '•••••••• (saved — type to replace)' : 'Enter value'}
        className="field w-full px-4 py-3 font-mono text-sm"
        autoComplete="off"
      />
    </label>
  )
}

function Toggle({
  label,
  hint,
  checked,
  onChange,
}: {
  label: string
  hint?: string
  checked: boolean
  onChange: (v: boolean) => void
}) {
  return (
    <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-4">
      <input
        type="checkbox"
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
        className="mt-0.5 h-4 w-4 rounded border-line text-mint focus:ring-mint/30"
      />
      <span>
        <span className="block text-sm font-medium">{label}</span>
        {hint && <span className="mt-0.5 block text-xs text-muted">{hint}</span>}
      </span>
    </label>
  )
}

export default function Site() {
  const { groups, flash } = usePage<SiteProps>().props
  const adminBase = useAdminBase()
  const [tab, setTab] = useState<SiteGroup>('mail')
  const form = useForm(cleanInitial(groups[tab], tab))

  const formErrors = Object.entries(form.errors).flatMap(([key, message]) => {
    if (!message) return []
    const text = Array.isArray(message) ? message[0] : message
    return [`${key.replace(/_/g, ' ')}: ${text}`]
  })

  function switchTab(next: SiteGroup) {
    setTab(next)
    form.clearErrors()
    form.setData(cleanInitial(groups[next], next))
  }

  function submit(e: FormEvent) {
    e.preventDefault()
    form.transform((data) => ({ ...data, group: tab }))
    form.put(`${buildAdminUrl(adminBase)}/site`, { preserveScroll: true })
  }

  function sendTestMail() {
    router.post(`${buildAdminUrl(adminBase)}/site/test-mail`, {}, { preserveScroll: true })
  }

  const ogPreview =
    typeof window !== 'undefined' && form.data.og_image
      ? String(form.data.og_image).startsWith('http')
        ? String(form.data.og_image)
        : `${window.location.origin}${String(form.data.og_image)}`
      : undefined

  return (
    <AdminLayout>
      <Head title="Site settings" />

      <div className="mx-auto max-w-3xl space-y-6">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Site</h1>
          <p className="mt-1 text-sm text-muted">
            Email, SMS/OTP, SEO previews, and platform security headers.
          </p>
        </div>

        {flash.success && (
          <p className="rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-sm text-mint">{flash.success}</p>
        )}
        {flash.error && (
          <p className="rounded-xl border border-danger/25 bg-danger/5 px-4 py-2.5 text-sm text-danger">{flash.error}</p>
        )}
        {formErrors.length > 0 && (
          <div className="rounded-xl border border-danger/25 bg-danger/5 px-4 py-2.5 text-sm text-danger">
            {formErrors.map((line) => (
              <p key={line}>{line}</p>
            ))}
          </div>
        )}

        <div className="flex flex-wrap gap-2">
          {tabs.map(({ id, label }) => (
            <button
              key={id}
              type="button"
              onClick={() => switchTab(id)}
              className={`rounded-full px-4 py-2 text-sm font-medium transition ${
                tab === id ? 'bg-mint/15 text-mint' : 'text-muted hover:bg-surface-2 hover:text-text'
              }`}
            >
              {label}
            </button>
          ))}
        </div>

        <Card className="shield-glow">
          <form onSubmit={submit} className="space-y-5">
            {tab === 'mail' && (
              <>
                <Toggle
                  label="Email notifications enabled"
                  hint="When off, no platform emails are sent (support tickets, etc.)."
                  checked={Boolean(form.data.notifications_enabled)}
                  onChange={(v) => form.setData('notifications_enabled', v)}
                />
                <label className="block">
                  <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Mail driver</span>
                  <select
                    value={String(form.data.mailer ?? 'log')}
                    onChange={(e) => form.setData('mailer', e.target.value)}
                    className="field w-full px-4 py-3 text-sm"
                  >
                    <option value="log">Log (development)</option>
                    <option value="smtp">SMTP (production)</option>
                    <option value="array">Array (testing)</option>
                  </select>
                </label>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field
                    label="From address"
                    type="email"
                    value={String(form.data.from_address ?? '')}
                    onChange={(e) => form.setData('from_address', e.target.value)}
                  />
                  <Field
                    label="From name"
                    value={String(form.data.from_name ?? '')}
                    onChange={(e) => form.setData('from_name', e.target.value)}
                  />
                  <Field
                    label="Support inbox"
                    type="email"
                    value={String(form.data.support_address ?? '')}
                    onChange={(e) => form.setData('support_address', e.target.value)}
                    hint="Receives escalated support tickets."
                  />
                  <Field
                    label="Reply-to address"
                    type="email"
                    value={String(form.data.reply_to_address ?? '')}
                    onChange={(e) => form.setData('reply_to_address', e.target.value)}
                  />
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                  <Toggle
                    label="Notify support on ticket escalation"
                    checked={Boolean(form.data.notify_on_support_ticket)}
                    onChange={(v) => form.setData('notify_on_support_ticket', v)}
                  />
                  <Toggle
                    label="Confirm ticket to user by email"
                    checked={Boolean(form.data.notify_user_on_ticket)}
                    onChange={(v) => form.setData('notify_user_on_ticket', v)}
                  />
                </div>
                {form.data.mailer === 'smtp' && (
                  <div className="space-y-4 rounded-xl border border-line p-4">
                    <p className="text-sm font-semibold">SMTP credentials</p>
                    <div className="grid gap-4 sm:grid-cols-2">
                      <Field
                        label="Host"
                        value={String(form.data.smtp_host ?? '')}
                        onChange={(e) => form.setData('smtp_host', e.target.value)}
                        error={form.errors.smtp_host}
                      />
                      <Field
                        label="Port"
                        type="number"
                        value={String(form.data.smtp_port ?? 587)}
                        onChange={(e) => form.setData('smtp_port', Number(e.target.value))}
                        error={form.errors.smtp_port}
                      />
                      <Field
                        label="Username"
                        value={String(form.data.smtp_username ?? '')}
                        onChange={(e) => form.setData('smtp_username', e.target.value)}
                        error={form.errors.smtp_username}
                      />
                      <SecretField
                        label="Password"
                        value={String(form.data.smtp_password ?? '')}
                        isSet={Boolean(groups.mail.smtp_password_set)}
                        onChange={(v) => form.setData('smtp_password', v)}
                      />
                    </div>
                    <label className="block">
                      <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">
                        Encryption
                      </span>
                      <select
                        value={String(form.data.smtp_encryption ?? 'tls')}
                        onChange={(e) => form.setData('smtp_encryption', e.target.value)}
                        className="field w-full px-4 py-3 text-sm"
                      >
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="none">None</option>
                      </select>
                    </label>
                  </div>
                )}
                <div className="flex flex-wrap gap-3 pt-2">
                  <Button type="submit" loading={form.processing}>
                    Save email settings
                  </Button>
                  <Button type="button" variant="secondary" onClick={sendTestMail}>
                    Send test email to me
                  </Button>
                </div>
              </>
            )}

            {tab === 'sms' && (
              <>
                <p className="rounded-xl border border-mint/20 bg-mint/[0.06] px-4 py-3 text-sm text-muted">
                  Termii API credentials are configured under{' '}
                  <span className="font-semibold text-text">Integrations → Termii</span>. Enable SMS here when your
                  sender ID is approved.
                </p>
                <Toggle
                  label="SMS notifications enabled"
                  hint="Master switch for Termii SMS and OTP delivery."
                  checked={Boolean(form.data.notifications_enabled)}
                  onChange={(v) => form.setData('notifications_enabled', v)}
                />
                <Toggle
                  label="OTP via SMS enabled"
                  hint="Send one-time codes for phone verification and security alerts."
                  checked={Boolean(form.data.otp_enabled)}
                  onChange={(v) => form.setData('otp_enabled', v)}
                />
                <Toggle
                  label="WhatsApp OTP (optional)"
                  hint="Deliver OTP over WhatsApp when Termii WhatsApp is enabled on your account."
                  checked={Boolean(form.data.whatsapp_otp_enabled)}
                  onChange={(v) => form.setData('whatsapp_otp_enabled', v)}
                />
                <label className="block">
                  <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">
                    Default OTP channel
                  </span>
                  <select
                    value={String(form.data.default_channel ?? 'sms')}
                    onChange={(e) => form.setData('default_channel', e.target.value)}
                    className="field w-full px-4 py-3 text-sm"
                  >
                    <option value="sms">SMS</option>
                    <option value="whatsapp">WhatsApp</option>
                  </select>
                </label>
                <Button type="submit" disabled={form.processing}>
                  Save SMS settings
                </Button>
              </>
            )}

            {tab === 'seo' && (
              <>
                <Field
                  label="Site name"
                  value={String(form.data.site_name ?? '')}
                  onChange={(e) => form.setData('site_name', e.target.value)}
                />
                <Field
                  label="Default page title"
                  value={String(form.data.title ?? '')}
                  onChange={(e) => form.setData('title', e.target.value)}
                />
                <label className="block">
                  <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">
                    Meta description
                  </span>
                  <textarea
                    value={String(form.data.description ?? '')}
                    onChange={(e) => form.setData('description', e.target.value)}
                    rows={3}
                    className="field w-full px-4 py-3 text-sm"
                  />
                </label>
                <Field
                  label="Keywords"
                  value={String(form.data.keywords ?? '')}
                  onChange={(e) => form.setData('keywords', e.target.value)}
                  hint="Comma-separated. Used in meta keywords and JSON-LD."
                />
                <Field
                  label="Open Graph image path"
                  value={String(form.data.og_image ?? '')}
                  onChange={(e) => form.setData('og_image', e.target.value)}
                  hint="PNG or JPG at 1200×630 (e.g. /og-banner.png) or full HTTPS URL. Required for WhatsApp & social previews."
                />
                {ogPreview && (
                  <div className="overflow-hidden rounded-xl border border-line">
                    <img src={ogPreview} alt="OG preview" className="h-auto w-full max-h-48 object-cover" />
                  </div>
                )}
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field
                    label="Twitter @handle"
                    value={String(form.data.twitter_site ?? '')}
                    onChange={(e) => form.setData('twitter_site', e.target.value)}
                  />
                  <Field
                    label="Locale"
                    value={String(form.data.locale ?? 'en_NG')}
                    onChange={(e) => form.setData('locale', e.target.value)}
                  />
                  <Field
                    label="Robots directive"
                    value={String(form.data.robots ?? 'index,follow')}
                    onChange={(e) => form.setData('robots', e.target.value)}
                    hint="Also drives /robots.txt"
                  />
                  <Field
                    label="Google site verification"
                    value={String(form.data.google_site_verification ?? '')}
                    onChange={(e) => form.setData('google_site_verification', e.target.value)}
                  />
                </div>
                <Button type="submit" disabled={form.processing}>
                  Save SEO settings
                </Button>
              </>
            )}

            {tab === 'security' && (
              <>
                <Toggle
                  label="Force HTTPS"
                  hint="301 redirect all HTTP requests to HTTPS."
                  checked={Boolean(form.data.force_https)}
                  onChange={(v) => form.setData('force_https', v)}
                />
                <Toggle
                  label="HSTS (Strict-Transport-Security)"
                  checked={Boolean(form.data.hsts_enabled)}
                  onChange={(v) => form.setData('hsts_enabled', v)}
                />
                <Field
                  label="HSTS max-age (seconds)"
                  type="number"
                  value={String(form.data.hsts_max_age ?? 31536000)}
                  onChange={(e) => form.setData('hsts_max_age', Number(e.target.value))}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                  <label className="block">
                    <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">
                      X-Frame-Options
                    </span>
                    <select
                      value={String(form.data.frame_options ?? 'DENY')}
                      onChange={(e) => form.setData('frame_options', e.target.value)}
                      className="field w-full px-4 py-3 text-sm"
                    >
                      <option value="DENY">DENY</option>
                      <option value="SAMEORIGIN">SAMEORIGIN</option>
                    </select>
                  </label>
                  <Field
                    label="Auth rate limit (per minute / IP)"
                    type="number"
                    min={3}
                    max={60}
                    value={String(form.data.auth_rate_limit ?? 10)}
                    onChange={(e) => form.setData('auth_rate_limit', Number(e.target.value))}
                  />
                </div>
                <Field
                  label="Referrer-Policy"
                  value={String(form.data.referrer_policy ?? '')}
                  onChange={(e) => form.setData('referrer_policy', e.target.value)}
                />
                <Field
                  label="Permissions-Policy"
                  value={String(form.data.permissions_policy ?? '')}
                  onChange={(e) => form.setData('permissions_policy', e.target.value)}
                />
                <Toggle
                  label="Content-Security-Policy"
                  checked={Boolean(form.data.csp_enabled)}
                  onChange={(v) => form.setData('csp_enabled', v)}
                />
                <Toggle
                  label="CSP report-only mode"
                  hint="When on, violations are logged but not blocked — safe for rollout."
                  checked={Boolean(form.data.csp_report_only)}
                  onChange={(v) => form.setData('csp_report_only', v)}
                />
                <Toggle
                  label="Secure session cookies"
                  hint="Sets the Secure flag on session cookies. Enable with Force HTTPS in production."
                  checked={Boolean(form.data.session_secure_cookie)}
                  onChange={(v) => form.setData('session_secure_cookie', v)}
                />
                <Button type="submit" disabled={form.processing}>
                  Save security settings
                </Button>
              </>
            )}
          </form>
        </Card>
      </div>
    </AdminLayout>
  )
}
