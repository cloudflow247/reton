import type { FormEvent } from 'react'
import { Head, useForm, usePage } from '@inertiajs/react'
import { AdminLayout } from '@/components/AdminLayout'
import { Button, Card } from '@/components/ui'
import { adminUrl } from '@/lib/admin'
import { ngn } from '@/lib/format'
import type { PageProps } from '@/types'

type AppSettingsProps = PageProps<{
  app: {
    demo_enabled: boolean
    public_url: string
    admin_path: string
  }
  reservedAdminPaths: string[]
  kyc: Record<
    string,
    {
      single_transaction_max: number
      daily_inflow_max: number
      wallet_balance_max: number
    }
  >
}>

export default function AppSettings() {
  const { app, kyc, reservedAdminPaths, flash } = usePage<AppSettingsProps>().props
  const form = useForm({
    demo_enabled: app.demo_enabled,
    public_url: app.public_url,
    admin_path: app.admin_path,
  })

  const previewPath = form.data.admin_path.replace(/^\/+|\/+$/g, '').toLowerCase() || 'admin'
  const previewUrl =
    typeof window !== 'undefined'
      ? `${window.location.origin}/${previewPath}`
      : `/${previewPath}`

  function submit(e: FormEvent) {
    e.preventDefault()
    form.put(`${adminUrl()}/app-settings`)
  }

  return (
    <AdminLayout>
      <Head title="App settings" />

      <div className="mx-auto max-w-2xl space-y-6">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Application</h1>
          <p className="mt-1 text-sm text-muted">Platform-wide toggles, admin URL, and share-link configuration.</p>
        </div>

        {flash.success && (
          <p className="rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-sm text-mint">{flash.success}</p>
        )}

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

            <label className="block">
              <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">
                Public URL (share links)
              </span>
              <input
                type="url"
                value={form.data.public_url}
                onChange={(e) => form.setData('public_url', e.target.value)}
                placeholder="https://reton.ng"
                className="field w-full px-4 py-3 text-sm"
              />
              <span className="mt-1 block text-xs text-muted">
                Used for marketplace share links and deep links. Leave blank to use APP_URL.
              </span>
            </label>

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
          <h2 className="font-display text-lg font-semibold">KYC tier limits</h2>
          <p className="mt-1 text-xs text-muted">Read-only — defined in config/reton.php. Shown for reference.</p>
          <div className="mt-4 space-y-4">
            {Object.entries(kyc).map(([tier, limits]) => (
              <div key={tier} className="rounded-xl border border-line p-4">
                <div className="text-sm font-semibold">Tier {tier}</div>
                <ul className="mt-2 space-y-1 text-xs text-muted">
                  <li>Single transaction max: {ngn(limits.single_transaction_max)}</li>
                  <li>Daily inflow max: {ngn(limits.daily_inflow_max)}</li>
                  <li>Wallet balance max: {ngn(limits.wallet_balance_max)}</li>
                </ul>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </AdminLayout>
  )
}
