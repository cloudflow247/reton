import type { ReactNode } from 'react'
import { Link, router, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { ActivityIcon, BoltIcon, GridIcon, MailIcon, ShieldIcon, UserIcon } from '@/components/icons'
import { Wordmark } from '@/components/ui'
import type { SharedProps } from '@/types'
import { useAdminBase } from '@/lib/admin'

type NavItem = {
  to: string
  label: string
  end?: boolean
  Icon: (p: { size?: number }) => JSX.Element
}

const spring = { type: 'spring', stiffness: 380, damping: 32 } as const

function useAdminNav(): NavItem[] {
  const base = useAdminBase()
  return [
    { to: base, label: 'Overview', end: true, Icon: GridIcon },
    { to: `${base}/users`, label: 'Users', Icon: UserIcon },
    { to: `${base}/integrations`, label: 'Integrations', Icon: BoltIcon },
    { to: `${base}/platform`, label: 'Platform', Icon: ActivityIcon },
    { to: `${base}/app-settings`, label: 'App', Icon: ShieldIcon },
    { to: `${base}/site`, label: 'Site', Icon: MailIcon },
  ]
}

export function AdminLayout({ children }: { children: ReactNode }) {
  const page = usePage<SharedProps>()
  const user = page.props.auth.user
  const pathname = page.url.split('?')[0]
  const nav = useAdminNav()

  const active = (to: string, end?: boolean) => (end ? pathname === to : pathname.startsWith(to))

  return (
    <div className="mx-auto flex min-h-full max-w-6xl flex-col px-4 pb-10 sm:px-6">
      <header className="dock sticky top-3 z-30 mt-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl px-4 py-3">
        <div className="flex items-center gap-4">
          <Link href="/dashboard" className="shrink-0">
            <Wordmark />
          </Link>
          <span className="hidden h-5 w-px bg-line sm:block" />
          <span className="hidden rounded-full bg-amber/12 px-2.5 py-1 text-xs font-semibold text-amber sm:inline">
            Platform admin
          </span>
        </div>

        <nav className="flex flex-1 items-center justify-end gap-1">
          {nav.map(({ to, label, end, Icon }) => {
            const on = active(to, end)
            return (
              <Link key={to} href={to} className="relative rounded-full px-3.5 py-2 text-sm font-medium">
                {on && (
                  <motion.span
                    layoutId="admin-nav-pill"
                    className="absolute inset-0 rounded-full bg-mint/[0.12]"
                    transition={spring}
                  />
                )}
                <span
                  className={`relative z-10 flex items-center gap-2 transition-colors ${
                    on ? 'text-mint' : 'text-muted hover:text-text'
                  }`}
                >
                  <Icon size={17} />
                  <span className="hidden sm:inline">{label}</span>
                </span>
              </Link>
            )
          })}
        </nav>

        <div className="flex shrink-0 items-center gap-1.5">
          <Link
            href="/dashboard"
            className="btn inline-flex h-9 whitespace-nowrap border border-line bg-surface px-3 text-xs font-semibold text-muted transition hover:border-mint/30 hover:text-mint"
          >
            Exit
          </Link>
          <button
            type="button"
            onClick={() => router.post('/logout')}
            className="btn inline-flex h-9 whitespace-nowrap border border-line bg-surface px-3 text-xs font-semibold text-muted transition hover:border-danger/30 hover:bg-danger/5 hover:text-danger"
          >
            Sign out
          </button>
        </div>
      </header>

      <main className="flex-1 pt-6">
        <motion.div
          key={pathname}
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.18, ease: 'easeOut' }}
        >
          {children}
        </motion.div>
      </main>

      <footer className="mt-8 border-t border-line pt-4 text-center text-xs text-muted">
        Signed in as {user?.email}. Secrets are encrypted in the database — never committed to git.
      </footer>
    </div>
  )
}
