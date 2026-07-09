import { Head, Link, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AdminLayout } from '@/components/AdminLayout'
import { BoltIcon, CheckIcon, ChevronRightIcon, ClockIcon, ShieldIcon, UserIcon, ChatIcon } from '@/components/icons'
import { Card, Pill } from '@/components/ui'
import { shortDate } from '@/lib/format'
import { useAdminBase } from '@/lib/admin'
import type { PageProps } from '@/types'

type AdminDashboardProps = PageProps<{
  stats: {
    users: number
    wallets: number
    deposits_today: number
    open_callbacks: number
    open_recoveries: number
    fraud_alerts: number
    open_support_tickets: number
  }
  integrations: {
    alatpay: { ready: boolean; driver: string }
    interswitch: { ready: boolean; driver: string }
    giglogistics: { ready: boolean; driver: string }
    dojah: { ready: boolean; driver: string }
  }
  recentAudit: {
    id: string
    action: string
    group: string | null
    user_name: string | null
    created_at: string
  }[]
}>

const list = {
  hidden: {},
  show: { transition: { staggerChildren: 0.05 } },
}
const item = {
  hidden: { opacity: 0, y: 10 },
  show: { opacity: 1, y: 0 },
}

export default function AdminDashboard() {
  const { stats, integrations, recentAudit, flash } = usePage<AdminDashboardProps>().props
  const adminBase = useAdminBase()

  const statCards = [
    { label: 'Users', value: stats.users, Icon: UserIcon },
    { label: 'Wallets', value: stats.wallets, Icon: ShieldIcon },
    { label: 'Deposits today', value: stats.deposits_today, Icon: BoltIcon },
    { label: 'Open callbacks', value: stats.open_callbacks, Icon: ClockIcon },
    { label: 'Held recoveries', value: stats.open_recoveries, Icon: ShieldIcon },
    { label: 'Fraud alerts', value: stats.fraud_alerts, Icon: BoltIcon },
    { label: 'Support tickets', value: stats.open_support_tickets, Icon: ChatIcon },
  ]

  const integrationRows = [
    { key: 'ALATPay', ...integrations.alatpay },
    { key: 'Interswitch', ...integrations.interswitch },
    { key: 'Giglogistics', ...integrations.giglogistics },
    { key: 'Dojah KYC', ...integrations.dojah },
  ]

  return (
    <AdminLayout>
      <Head title="Admin" />

      <div className="space-y-6">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Control center</h1>
          <p className="mt-1 text-sm text-muted">
            Configure live APIs, monitor platform health, and manage Reton without exposing secrets in your repo.
          </p>
        </div>

        {flash.success && (
          <p className="rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-sm text-mint">{flash.success}</p>
        )}
        {flash.error && (
          <p className="rounded-xl border border-danger/25 bg-danger/5 px-4 py-2.5 text-sm text-danger">{flash.error}</p>
        )}

        <motion.div variants={list} initial="hidden" animate="show" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {statCards.map(({ label, value, Icon }) => (
            <motion.div key={label} variants={item}>
              <Card className="flex items-center gap-4">
                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-mint/10 text-mint">
                  <Icon size={22} />
                </div>
                <div>
                  <div className="text-2xl font-num font-bold">{value.toLocaleString()}</div>
                  <div className="text-xs text-muted">{label}</div>
                </div>
              </Card>
            </motion.div>
          ))}
        </motion.div>

        <div className="grid gap-5 lg:grid-cols-2">
          <Card>
            <div className="mb-4 flex items-center justify-between">
              <h2 className="font-display text-lg font-semibold">Integrations</h2>
              <Link
                href={`${adminBase}/integrations`}
                className="flex items-center gap-1 text-sm font-medium text-mint hover:underline"
              >
                Configure <ChevronRightIcon size={16} />
              </Link>
            </div>
            <ul className="divide-y divide-line">
              {integrationRows.map((row) => (
                <li key={row.key} className="flex items-center justify-between py-3">
                  <div>
                    <div className="text-sm font-semibold">{row.key}</div>
                    <div className="text-xs text-muted capitalize">{row.driver} driver</div>
                  </div>
                  {row.ready ? (
                    <Pill tone="mint">
                      <CheckIcon size={12} /> Ready
                    </Pill>
                  ) : (
                    <Pill tone="amber">Needs setup</Pill>
                  )}
                </li>
              ))}
            </ul>
          </Card>

          <Card>
            <h2 className="mb-4 font-display text-lg font-semibold">Recent admin activity</h2>
            {recentAudit.length === 0 ? (
              <p className="text-sm text-muted">No configuration changes yet.</p>
            ) : (
              <ul className="space-y-3">
                {recentAudit.map((log) => (
                  <li key={log.id} className="flex items-start justify-between gap-3 text-sm">
                    <div>
                      <div className="font-medium">{log.action}</div>
                      <div className="text-xs text-muted">
                        {log.user_name ?? 'Admin'}
                        {log.group ? ` · ${log.group}` : ''}
                      </div>
                    </div>
                    <time className="shrink-0 text-xs text-muted">{shortDate(log.created_at)}</time>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>

        <Card className="border-mint/20 bg-mint/[0.03]">
          <h2 className="font-display text-lg font-semibold">Security model</h2>
          <ul className="mt-3 space-y-2 text-sm text-muted">
            <li>API keys and webhook secrets are encrypted with your server&apos;s APP_KEY before storage.</li>
            <li>The admin UI only shows masked values (last 4 characters). Leave a secret field blank to keep the current value.</li>
            <li>Audit logs record who changed what — never the secret values themselves.</li>
            <li>Customize the admin URL under App settings so <code className="text-text">/admin</code> is not guessable.</li>
            <li>Keep .env out of git. Promote admins with: php artisan reton:admin your@email.com</li>
          </ul>
        </Card>
      </div>
    </AdminLayout>
  )
}
