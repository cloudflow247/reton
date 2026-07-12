import { Head, Link, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AdminLayout } from '@/components/AdminLayout'
import {
  BoltIcon,
  ChatIcon,
  ChevronRightIcon,
  ShieldIcon,
  UndoIcon,
  UserIcon,
  WalletIcon,
} from '@/components/icons'
import { Card, Pill } from '@/components/ui'
import { shortDate } from '@/lib/format'
import { useAdminBase } from '@/lib/admin'
import type { PageProps } from '@/types'

type IntegrationStatus = {
  ready: boolean
  driver: string
  subtitle?: string
  bvn_ready?: boolean
  provider?: string
}

type IntegrationRow = IntegrationStatus & { key: string }

type GoLiveItem = {
  id: string
  label: string
  ready: boolean
  href: string
  detail: string
}

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
    alatpay: IntegrationStatus
    paystack: IntegrationStatus
    bvn?: IntegrationStatus
    interswitch: IntegrationStatus
    termii: IntegrationStatus
    bridgecard: IntegrationStatus
    dojah: IntegrationStatus
    remita: IntegrationStatus
    giglogistics: IntegrationStatus
  }
  recentAudit: Array<{
    id: string
    action: string
    group: string | null
    user_name: string | null
    created_at: string
  }>
  goLive?: GoLiveItem[]
  queues?: {
    support: number
    fraud: number
    callbacks: number
    recoveries: number
  }
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
  const { stats, integrations, recentAudit, flash, goLive = [], queues } = usePage<AdminDashboardProps>().props
  const adminBase = useAdminBase()
  const readyCount = goLive.filter((g) => g.ready).length

  const statCards = [
    { label: 'Users', value: stats.users, Icon: UserIcon, href: `${adminBase}/users` },
    { label: 'Money desk', value: stats.deposits_today, Icon: WalletIcon, href: `${adminBase}/money` },
    { label: 'Callbacks', value: stats.open_callbacks, Icon: ShieldIcon, href: `${adminBase}/callbacks` },
    { label: 'Recoveries', value: stats.open_recoveries, Icon: UndoIcon, href: `${adminBase}/recoveries` },
    { label: 'Fraud', value: stats.fraud_alerts, Icon: BoltIcon, href: `${adminBase}/fraud` },
    { label: 'Support', value: stats.open_support_tickets, Icon: ChatIcon, href: `${adminBase}/support` },
  ]

  const integrationRows: IntegrationRow[] = [
    { key: 'ALATPay', ...integrations.alatpay },
    { key: 'Paystack', ...integrations.paystack },
    {
      key: 'BVN verification',
      ready: integrations.bvn?.ready ?? false,
      driver: integrations.bvn?.provider ?? 'ALATPay',
      subtitle: `via ${integrations.bvn?.provider ?? 'ALATPay'}`,
    },
    { key: 'Interswitch', ...integrations.interswitch },
    { key: 'Termii', ...integrations.termii },
    { key: 'Bridgecard', ...integrations.bridgecard },
    { key: 'Dojah', ...integrations.dojah },
    { key: 'Remita', ...integrations.remita },
    { key: 'Giglogistics', ...integrations.giglogistics },
  ]

  return (
    <AdminLayout>
      <Head title="Admin" />

      <div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="font-display text-2xl font-bold tracking-tight">Control center</h1>
            <p className="mt-1 text-sm text-muted">
              Ops queues, live integrations, fees, and go-live readiness — encrypted secrets stay in the database.
            </p>
          </div>
          <Pill tone={readyCount === goLive.length ? 'mint' : 'amber'}>
            Go-live {readyCount}/{goLive.length || '—'}
          </Pill>
        </div>

        {flash.success && (
          <p className="rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-sm text-mint">{flash.success}</p>
        )}
        {flash.error && (
          <p className="rounded-xl border border-danger/25 bg-danger/5 px-4 py-2.5 text-sm text-danger">{flash.error}</p>
        )}

        {queues && (
          <div className="grid gap-2 sm:grid-cols-4">
            {(
              [
                ['Callbacks', queues.callbacks, `${adminBase}/callbacks`],
                ['Recoveries', queues.recoveries, `${adminBase}/recoveries`],
                ['Fraud', queues.fraud, `${adminBase}/fraud`],
                ['Support', queues.support, `${adminBase}/support`],
              ] as const
            ).map(([label, count, href]) => (
              <Link
                key={label}
                href={href}
                className="rounded-xl border border-line bg-surface px-3 py-3 transition hover:border-mint/35"
              >
                <p className="text-[10px] font-bold uppercase tracking-wide text-muted">{label}</p>
                <p className={`mt-1 font-num text-2xl font-bold ${count > 0 ? 'text-amber' : 'text-text'}`}>{count}</p>
              </Link>
            ))}
          </div>
        )}

        <motion.div variants={list} initial="hidden" animate="show" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {statCards.map(({ label, value, Icon, href }) => (
            <motion.div key={label} variants={item}>
              <Link href={href} className="block transition hover:opacity-90">
                <Card className="flex items-center gap-4">
                  <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-mint/10 text-mint">
                    <Icon size={22} />
                  </div>
                  <div>
                    <div className="font-num text-2xl font-bold">{value.toLocaleString()}</div>
                    <div className="text-xs text-muted">{label}</div>
                  </div>
                </Card>
              </Link>
            </motion.div>
          ))}
        </motion.div>

        <div className="grid gap-5 lg:grid-cols-2">
          <Card>
            <div className="mb-4 flex items-center justify-between gap-3">
              <h2 className="font-display text-lg font-semibold">Go-live checklist</h2>
              <Link href={`${adminBase}/platform`} className="text-sm font-medium text-mint hover:underline">
                Fees & rules
              </Link>
            </div>
            <ul className="divide-y divide-line">
              {goLive.map((row) => (
                <li key={row.id}>
                  <Link href={row.href} className="flex items-center justify-between gap-3 py-3 transition hover:bg-surface-2/40">
                    <div className="min-w-0">
                      <div className="text-sm font-semibold">{row.label}</div>
                      <div className="truncate text-xs text-muted">{row.detail}</div>
                    </div>
                    <Pill tone={row.ready ? 'mint' : 'amber'}>{row.ready ? 'Ready' : 'Action'}</Pill>
                  </Link>
                </li>
              ))}
            </ul>
          </Card>

          <Card>
            <div className="mb-4 flex items-center justify-between gap-3">
              <h2 className="font-display text-lg font-semibold">Integrations</h2>
              <Link
                href={`${adminBase}/integrations`}
                className="flex shrink-0 items-center gap-1 text-sm font-medium text-mint hover:underline"
              >
                Configure <ChevronRightIcon size={16} />
              </Link>
            </div>
            <ul className="divide-y divide-line">
              {integrationRows.map((row) => (
                <li key={row.key} className="flex items-center justify-between gap-3 py-3">
                  <div className="min-w-0">
                    <div className="text-sm font-semibold">{row.key}</div>
                    <div className="truncate text-xs capitalize text-muted">
                      {row.subtitle ? `${row.subtitle} · ` : ''}
                      {row.driver}{' '}
                      {row.key === 'ALATPay' && row.bvn_ready === false ? '· BVN needs setup' : ''}
                    </div>
                  </div>
                  <Pill tone={row.ready ? 'mint' : 'amber'}>{row.ready ? 'Connected' : 'Setup'}</Pill>
                </li>
              ))}
            </ul>
          </Card>
        </div>

        <Card>
          <h2 className="mb-3 font-display text-lg font-semibold">Recent admin activity</h2>
          {recentAudit.length === 0 ? (
            <p className="text-sm text-muted">No audit events yet.</p>
          ) : (
            <ul className="divide-y divide-line">
              {recentAudit.map((log) => (
                <li key={log.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                  <div className="min-w-0">
                    <p className="font-medium">{log.action}</p>
                    <p className="text-xs text-muted">
                      {log.user_name ?? 'System'}
                      {log.group ? ` · ${log.group}` : ''}
                    </p>
                  </div>
                  <span className="shrink-0 text-xs text-muted">{shortDate(log.created_at)}</span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>
    </AdminLayout>
  )
}
