import type { ReactNode } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { AnimatePresence, motion } from 'framer-motion'
import { useAuth } from '../store/auth'
import { Wordmark } from './ui'
import { ActivityIcon, HomeIcon, LockIcon, PlusIcon, SendIcon, UserIcon } from './icons'

const nav = [
  { to: '/', label: 'Home', end: true, Icon: HomeIcon },
  { to: '/send', label: 'Send', Icon: SendIcon },
  { to: '/add-money', label: 'Add money', Icon: PlusIcon },
  { to: '/activity', label: 'Activity', Icon: ActivityIcon },
  { to: '/profile', label: 'Profile', Icon: UserIcon },
]

const spring = { type: 'spring', stiffness: 380, damping: 32 } as const

export function AppShell({ children }: { children: ReactNode }) {
  const user = useAuth((s) => s.user)
  const logout = useAuth((s) => s.logout)
  const navigate = useNavigate()
  const { pathname } = useLocation()

  const active = (to: string, end?: boolean) => (end ? pathname === to : pathname.startsWith(to))

  return (
    <div className="mx-auto flex min-h-full max-w-5xl flex-col px-4 pb-24 sm:px-6">
      <header className="flex items-center justify-between py-5">
        <Link to="/">
          <Wordmark />
        </Link>
        <nav className="hidden items-center gap-1 sm:flex">
          {nav.map(({ to, label, end, Icon }) => {
            const on = active(to, end)
            return (
              <Link key={to} to={to} className="relative rounded-full px-3.5 py-2 text-sm font-medium">
                {on && (
                  <motion.span
                    layoutId="nav-pill"
                    className="absolute inset-0 rounded-full bg-mint/10"
                    transition={spring}
                  />
                )}
                <span
                  className={`relative z-10 flex items-center gap-2 transition-colors ${
                    on ? 'text-mint' : 'text-muted hover:text-text'
                  }`}
                >
                  <Icon size={17} />
                  {label}
                </span>
              </Link>
            )
          })}
        </nav>
        <div className="flex items-center gap-4">
          <Link
            to="/pin"
            className="hidden items-center gap-1.5 text-sm text-muted hover:text-text sm:flex"
          >
            <LockIcon size={15} />
            {user?.has_transaction_pin ? 'PIN' : 'Set PIN'}
          </Link>
          <button
            onClick={() => {
              logout()
              navigate('/login')
            }}
            className="text-sm text-muted hover:text-danger"
          >
            Sign out
          </button>
        </div>
      </header>

      <main className="flex-1">
        <AnimatePresence mode="wait">
          <motion.div
            key={pathname}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -6 }}
            transition={{ duration: 0.22, ease: 'easeOut' }}
          >
            {children}
          </motion.div>
        </AnimatePresence>
      </main>

      {/* Mobile bottom nav */}
      <nav className="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-surface/90 backdrop-blur sm:hidden">
        <div className="mx-auto flex max-w-5xl items-center justify-around px-2 py-2">
          {nav.map(({ to, label, end, Icon }) => {
            const on = active(to, end)
            return (
              <Link
                key={to}
                to={to}
                className={`flex flex-col items-center gap-1 rounded-xl px-3 py-1.5 text-[11px] font-medium ${
                  on ? 'text-mint' : 'text-muted'
                }`}
              >
                <Icon size={20} />
                {label}
              </Link>
            )
          })}
        </div>
      </nav>
    </div>
  )
}
