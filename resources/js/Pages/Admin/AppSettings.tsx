import type { FormEvent } from 'react'
import { useState } from 'react'
import { Head, Link, useForm, usePage } from '@inertiajs/react'
import { AdminFormErrors, AdminLayout } from '@/components/AdminLayout'
import { Button, Card } from '@/components/ui'
import { buildAdminUrl, useAdminBase } from '@/lib/admin'
import type { PageProps } from '@/types'

type AppSettingsProps = PageProps<{
  app: {
    demo_enabled: boolean
    demo_password: string
    demo_password_set?: boolean
    demo_pin: string
    demo_pin_set?: boolean
    public_url: string
    admin_path: string
    listing_path: string
    app_scheme: string
    ios_bundle_id: string
    apple_team_id: string
    android_package: string
    android_sha256: string
  }
  reservedAdminPaths: string[]
}>

function SecretField({
  label,
  value,
  isSet,
  onChange,
  hint,
}: {
  label: string
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
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={isSet && !show ? '•••••••• (saved — type to replace)' : 'Enter value'}
        className="field w-full px-4 py-3 font-mono text-sm"
        autoComplete="off"
      />
      {hint && <span className="mt-1 block text-xs text-muted">{hint}</span>}
    </label>
  )
}

export default function AppSettings() {
  const { app, reservedAdminPaths } = usePage<AppSettingsProps>().props
  const adminBase = useAdminBase()
  const form = useForm({
    demo_enabled: app.demo_enabled,
    demo_password: app.demo_password.includes('••••') ? '' : app.demo_password,
    demo_pin: app.demo_pin.includes('••••') ? '' : app.demo_pin,
    public_url: app.public_url,
    admin_path: app.admin_path,
    listing_path: app.listing_path,
    app_scheme: app.app_scheme,
    ios_bundle_id: app.ios_bundle_id,
    apple_team_id: app.apple_team_id,
    android_package: app.android_package,
    android_sha256: app.android_sha256,
  })

  const previewPath = form.data.admin_path.replace(/^\/+|\/+$/g, '').toLowerCase() || 'admin'
  const previewUrl =
    typeof window !== 'undefined'
      ? `${window.location.origin}/${previewPath}`
      : `/${previewPath}`

  function submit(e: FormEvent) {
    e.preventDefault()
    form.transform((data) => ({
      ...data,
      demo_enabled: Boolean(data.demo_enabled),
    }))
    form.put(buildAdminUrl(adminBase, 'app-settings'), {
      preserveScroll: true,
      onSuccess: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    })
  }

  return (
    <AdminLayout>
      <Head title="App settings" />

      <div className="mx-auto max-w-2xl space-y-6">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Application</h1>
          <p className="mt-1 text-sm text-muted">
            Platform toggles, admin URL, share links, and mobile deep-link identifiers.
          </p>
        </div>

        {Object.keys(form.errors).length > 0 && <AdminFormErrors errors={form.errors} />}

        <Card className="shield-glow">
          <h2 className="font-display text-lg font-semibold">Admin panel URL</h2>
          <p className="mt-1 text-sm text-muted">
            Hide the control panel behind a custom path. The default <code className="text-text">/admin</code> is easy
            to guess — change it to something only you know.
          </p>

          <form onSubmit={submit} className="mt-5 space-y-5">
            <label className="block">
              <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Path segment</span>
              <div className="field flex items-center overflow-hidden">
                <span className="shrink-0 border-r border-line bg-surface-2 px-3 py-3 text-sm text-muted">/</span>
                <input
                  type="text"
                  value={form.data.admin_path}
                  onChange={(e) => form.setData('admin_path', e.target.value.toLowerCase())}
                  placeholder="reton-control-x7k9"
                  className="w-full bg-transparent px-3 py-3 font-mono text-sm outline-none"
                  autoComplete="off"
                  spellCheck={false}
                />
              </div>
              <span className="mt-1 block text-xs text-muted">
                3–48 characters: lowercase letters, numbers, and hyphens. Cannot match app routes like dashboard or
                login.
              </span>
              {form.errors.admin_path && (
                <span className="mt-1 block text-xs text-danger">{form.errors.admin_path}</span>
              )}
            </label>

            <div className="rounded-xl border border-line bg-surface-2/50 px-4 py-3">
              <div className="text-xs text-muted">Your admin URL will be</div>
              <div className="mt-1 break-all font-mono text-sm font-semibold text-mint">{previewUrl}</div>
            </div>

            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-4">
              <input
                type="checkbox"
                checked={form.data.demo_enabled}
                onChange={(e) => form.setData('demo_enabled', e.target.checked)}
                className="mt-1 h-4 w-4 rounded border-line text-mint focus:ring-mint"
              />
              <div>
                <div className="text-sm font-semibold">Demo mode</div>
                <p className="mt-1 text-xs text-muted">
                  Show demo accounts on the sign-in screen. Turn off in production.
                </p>
              </div>
            </label>

            <div className="grid gap-4 sm:grid-cols-2">
              <SecretField
                label="Demo password"
                value={form.data.demo_password}
                isSet={app.demo_password_set}
                onChange={(v) => form.setData('demo_password', v)}
              />
              <SecretField
                label="Demo transaction PIN"
                value={form.data.demo_pin}
                isSet={app.demo_pin_set}
                onChange={(v) => form.setData('demo_pin', v)}
                hint="4–6 digits shared by demo accounts."
              />
            </div>

            <label className="block">
              <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">
                Public URL (share links)
              </span>
              <input
                type="url"
                value={form.data.public_url}
                onChange={(e) => form.setData('public_url', e.target.value)}
                placeholder="https://retonpay.com"
                className="field w-full px-4 py-3 text-sm"
              />
              <span className="mt-1 block text-xs text-muted">
                Used for marketplace share links and deep links. Leave blank to use APP_URL.
              </span>
            </label>

            <div className="grid gap-4 sm:grid-cols-2">
              <label className="block">
                <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Listing path</span>
                <input
                  type="text"
                  value={form.data.listing_path}
                  onChange={(e) => form.setData('listing_path', e.target.value)}
                  className="field w-full px-4 py-3 font-mono text-sm"
                />
              </label>
              <label className="block">
                <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">App scheme</span>
                <input
                  type="text"
                  value={form.data.app_scheme}
                  onChange={(e) => form.setData('app_scheme', e.target.value)}
                  className="field w-full px-4 py-3 font-mono text-sm"
                />
              </label>
            </div>

            <h3 className="pt-2 text-sm font-semibold">Mobile deep linking</h3>
            <div className="grid gap-4 sm:grid-cols-2">
              <label className="block">
                <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">iOS bundle ID</span>
                <input
                  type="text"
                  value={form.data.ios_bundle_id}
                  onChange={(e) => form.setData('ios_bundle_id', e.target.value)}
                  className="field w-full px-4 py-3 font-mono text-sm"
                />
              </label>
              <label className="block">
                <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Apple team ID</span>
                <input
                  type="text"
                  value={form.data.apple_team_id}
                  onChange={(e) => form.setData('apple_team_id', e.target.value)}
                  className="field w-full px-4 py-3 font-mono text-sm"
                />
              </label>
              <label className="block">
                <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Android package</span>
                <input
                  type="text"
                  value={form.data.android_package}
                  onChange={(e) => form.setData('android_package', e.target.value)}
                  className="field w-full px-4 py-3 font-mono text-sm"
                />
              </label>
              <label className="block">
                <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Android SHA-256</span>
                <input
                  type="text"
                  value={form.data.android_sha256}
                  onChange={(e) => form.setData('android_sha256', e.target.value)}
                  className="field w-full px-4 py-3 font-mono text-sm"
                />
              </label>
            </div>

            <Button type="submit" loading={form.processing}>
              Save application settings
            </Button>
          </form>
        </Card>

        <Card>
          <h2 className="font-display text-lg font-semibold">Reserved paths</h2>
          <p className="mt-1 text-xs text-muted">These cannot be used as your admin URL.</p>
          <p className="mt-3 flex flex-wrap gap-1.5">
            {reservedAdminPaths.map((path) => (
              <span key={path} className="rounded-full bg-surface-2 px-2 py-0.5 font-mono text-[11px] text-muted">
                /{path}
              </span>
            ))}
          </p>
        </Card>

        <Card>
          <h2 className="font-display text-lg font-semibold">KYC & business rules</h2>
          <p className="mt-1 text-sm text-muted">
            Tier limits, fraud scoring, callback windows, and marketplace timing are managed under{' '}
            <Link href={`${adminBase}/platform`} className="text-mint hover:underline">
              Platform settings
            </Link>
            .
          </p>
        </Card>
      </div>
    </AdminLayout>
  )
}
