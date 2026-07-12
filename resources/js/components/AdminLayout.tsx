import type { ReactElement, ReactNode } from 'react'
import { Link, router, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import {
  ActivityIcon,
  BankIcon,
  BoltIcon,
  ChatIcon,
  ClockIcon,
  GridIcon,
  MailIcon,
  ShieldIcon,
  UndoIcon,
  UserIcon,
  WalletIcon,
} from '@/components/icons'
import { Wordmark } from '@/components/ui'
import type { SharedProps } from '@/types'
import { useAdminBase } from '@/lib/admin'

type NavItem = {
  to: string
  label: string
  end?: boolean
  Icon: (p: { size?: number; className?: string }) => ReactElement
  section?: string
}

const spring = { type: 'spring', stiffness: 420, damping: 34 } as const

function useAdminNav(): NavItem[] {
  const base = useAdminBase()
  return [
    { to: base, label: 'Control center', end: true, Icon: GridIcon, section: 'Command' },
    { to: `${base}/users`, label: 'Users', Icon: UserIcon, section: 'Command' },
    { to: `${base}/money`, label: 'Money', Icon: WalletIcon, section: 'Ops' },
    { to: `${base}/callbacks`, label: 'Callbacks', Icon: ShieldIcon, section: 'Ops' },
    { to: `${base}/recoveries`, label: 'Recoveries', Icon: UndoIcon, section: 'Ops' },
    { to: `${base}/fraud`, label: 'Fraud', Icon: BoltIcon, section: 'Ops' },
    { to: `${base}/support`, label: 'Support', Icon: ChatIcon, section: 'Ops' },
    { to: `${base}/integrations`, label: 'Integrations', Icon: BankIcon, section: 'Config' },
    { to: `${base}/platform`, label: 'Platform', Icon: ActivityIcon, section: 'Config' },
    { to: `${base}/app-settings`, label: 'App', Icon: ClockIcon, section: 'Config' },
    { to: `${base}/site`, label: 'Site', Icon: MailIcon, section: 'Config' },
  ]
}

export function AdminLayout({ children }: { children: ReactNode }) {
  const page = usePage<SharedProps>()
  const user = page.props.auth.user
  const pathname = page.url.split('?')[0]
  const nav = useAdminNav()
  const active = (to: string, end?: boolean) => (end ? pathname === to : pathname.startsWith(to))

  const sections = ['Command', 'Ops', 'Config'] as const

  return (
    <div className="min-h-full bg-[linear-gradient(180deg,#0b1210_0%,#101a17_42%,#0e1513_100%)] text-zinc-100">
      <div className="mx-auto flex min-h-full max-w-[1400px] gap-0 lg:gap-6 lg:px-4 lg:py-4">
        <aside className="hidden w-64 shrink-0 flex-col border-r border-white/10 bg-black/25 lg:flex lg:rounded-2xl lg:border">
          <div className="border-b border-white/10 px-4 py-4">
            <Link href="/dashboard" className="inline-flex items-center gap-2">
              <Wordmark />
            </Link>
            <p className="mt-3 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-300/90">Ops control</p>
            <p className="mt-1 text-xs text-zinc-400">Production rails · encrypted secrets</p>
          </div>
          <nav className="flex-1 space-y-5 overflow-y-auto px-2 py-4">
            {sections.map((section) => (
              <div key={section}>
                <p className="mb-1.5 px-2 text-[10px] font-bold uppercase tracking-[0.16em] text-zinc-500">{section}</p>
                <div className="space-y-0.5">
                  {nav
                    .filter((item) => item.section === section)
                    .map(({ to, label, end, Icon }) => {
                      const on = active(to, end)
                      return (
                        <Link
                          key={to}
                          href={to}
                          className={`relative flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-semibold transition ${
                            on ? 'bg-emerald-500/15 text-emerald-300' : 'text-zinc-400 hover:bg-white/5 hover:text-zinc-100'
                          }`}
                        >
                          {on && (
                            <motion.span
                              layoutId="admin-side-pill"
                              className="absolute inset-0 rounded-xl border border-emerald-400/20 bg-emerald-500/10"
                              transition={spring}
                            />
                          )}
                          <Icon size={16} className="relative z-10 shrink-0" />
                          <span className="relative z-10">{label}</span>
                        </Link>
                      )
                    })}
                </div>
              </div>
            ))}
          </nav>
          <div className="space-y-2 border-t border-white/10 p-3">
            <Link
              href="/dashboard"
              className="flex h-9 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-xs font-semibold text-zinc-300 hover:bg-white/10"
            >
              Exit to app
            </Link>
            <button
              type="button"
              onClick={() => router.post('/logout')}
              className="flex h-9 w-full items-center justify-center rounded-xl border border-rose-500/20 bg-rose-500/10 text-xs font-semibold text-rose-300 hover:bg-rose-500/15"
            >
              Sign out
            </button>
          </div>
        </aside>

        <div className="flex min-w-0 flex-1 flex-col">
          <header className="sticky top-0 z-30 border-b border-white/10 bg-black/40 px-4 py-3 backdrop-blur-md lg:hidden">
            <div className="flex items-center justify-between gap-3">
              <div>
                <Wordmark />
                <p className="mt-0.5 text-[10px] font-bold uppercase tracking-[0.16em] text-amber-300/90">Admin</p>
              </div>
              <Link href="/dashboard" className="rounded-lg border border-white/10 px-2.5 py-1.5 text-xs font-semibold text-zinc-300">
                Exit
              </Link>
            </div>
            <nav className="mt-3 flex gap-1 overflow-x-auto pb-1">
              {nav.map(({ to, label, end, Icon }) => {
                const on = active(to, end)
                return (
                  <Link
                    key={to}
                    href={to}
                    className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ${
                      on ? 'bg-emerald-500/20 text-emerald-300' : 'bg-white/5 text-zinc-400'
                    }`}
                  >
                    <Icon size={14} />
                    {label}
                  </Link>
                )
              })}
            </nav>
          </header>

          <main className="flex-1 px-4 py-5 sm:px-6 lg:px-2 lg:py-2">
            <AdminGlobalFlash />
            <motion.div
              key={pathname}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.16, ease: 'easeOut' }}
              className="min-h-[70vh] rounded-2xl border border-line bg-bg p-4 text-text shadow-[0_24px_80px_-40px_rgba(0,0,0,0.55)] sm:p-6"
            >
              {children}
            </motion.div>
          </main>

          <footer className="px-4 pb-6 text-center text-[11px] text-zinc-500 sm:px-6">
            Signed in as {user?.email}. Secrets stay encrypted in the database — never in git.
          </footer>
        </div>
      </div>
    </div>
  )
}

/** Shared rugged panel for ops tables. */
export function AdminGlobalFlash() {
  const { flash } = usePage<SharedProps>().props

  if (!flash.success && !flash.error) {
    return null
  }

  return (
    <div className="mb-4 space-y-2 px-0 sm:px-0 lg:px-2">
      {flash.success && (
        <p
          role="status"
          className="rounded-xl border border-emerald-400/30 bg-emerald-500/15 px-4 py-3 text-sm font-semibold text-emerald-200 shadow-lg shadow-black/20"
        >
          {flash.success}
        </p>
      )}
      {flash.error && (
        <p
          role="alert"
          className="rounded-xl border border-rose-400/30 bg-rose-500/15 px-4 py-3 text-sm font-semibold text-rose-200 shadow-lg shadow-black/20"
        >
          {flash.error}
        </p>
      )}
    </div>
  )
}

export function AdminFormErrors({ errors }: { errors: Record<string, string | string[] | undefined> }) {
  const messages = Object.entries(errors).flatMap(([key, message]) => {
    if (!message) return []
    const text = Array.isArray(message) ? message[0] : message
    if (!text) return []
    return [`${key.replace(/_/g, ' ')}: ${text}`]
  })

  if (messages.length === 0) {
    return null
  }

  return (
    <div role="alert" className="space-y-1 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-sm text-danger">
      <p className="font-semibold">Could not save — fix the following:</p>
      <ul className="list-inside list-disc space-y-0.5 text-xs">
        {messages.map((m) => (
          <li key={m}>{m}</li>
        ))}
      </ul>
    </div>
  )
}

export function AdminPanel({
  title,
  subtitle,
  actions,
  children,
}: {
  title: string
  subtitle?: string
  actions?: ReactNode
  children: ReactNode
}) {
  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight text-text">{title}</h1>
          {subtitle && <p className="mt-1 text-sm text-muted">{subtitle}</p>}
        </div>
        {actions}
      </div>
      {children}
    </div>
  )
}

export function AdminTable({ children }: { children: ReactNode }) {
  return (
    <div className="overflow-hidden rounded-xl border border-white/10 bg-black/20">
      <div className="overflow-x-auto">{children}</div>
    </div>
  )
}
