import type { ReactNode } from 'react'
import { Link, router, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import type { SharedProps } from '@/types'
import { Wordmark } from './ui'
import {
  ActivityIcon,
  BellIcon,
  BillIcon,
  CardIcon,
  HomeIcon,
  SendIcon,
  ShieldIcon,
  UserIcon,
} from './icons'

type NavItem = {
  to: string
  label: string
  end?: boolean
  Icon: (p: { size?: number }) => JSX.Element
}

const nav: NavItem[] = [
  { to: '/dashboard', label: 'Home', end: true, Icon: HomeIcon },
  { to: '/send', label: 'Send', Icon: SendIcon },
  { to: '/marketplace', label: 'Shop', Icon: ShieldIcon },
  { to: '/cards', label: 'Cards', Icon: CardIcon },
  { to: '/bills', label: 'Bills', Icon: BillIcon },
  { to: '/activity', label: 'Activity', Icon: ActivityIcon },
  { to: '/profile', label: 'Profile', Icon: UserIcon },
]

// Mobile dock: two items, a centre FAB, then two more.
const dockLeft: NavItem[] = [
  { to: '/dashboard', label: 'Home', end: true, Icon: HomeIcon },
  { to: '/bills', label: 'Bills', Icon: BillIcon },
]
const dockRight: NavItem[] = [
  { to: '/activity', label: 'Activity', Icon: ActivityIcon },
  { to: '/profile', label: 'Profile', Icon: UserIcon },
]

const spring = { type: 'spring', stiffness: 380, damping: 32 } as const

export function AppShell({ children }: { children: ReactNode }) {
  const page = usePage<SharedProps>()
  const user = page.props.auth.user
  const pathname = page.url.split('?')[0]
  const initial = (user?.name ?? 'R').charAt(0).toUpperCase()

  const active = (to: string, end?: boolean) => (end ? pathname === to : pathname.startsWith(to))

  return (
    <div className="mx-auto flex min-h-full max-w-5xl flex-col px-4 pb-28 sm:px-6 sm:pb-10">
      <header className="dock sticky top-3 z-30 mt-3 flex items-center justify-between gap-2 rounded-2xl px-3 py-2.5 sm:px-4">
        <Link href="/dashboard" className="shrink-0">
          <Wordmark />
        </Link>

        <nav className="hidden items-center gap-1 sm:flex">
          {nav.map(({ to, label, end, Icon }) => {
            const on = active(to, end)
            return (
              <Link
                key={to}
                href={to}
                className="relative rounded-full px-3.5 py-2 text-sm font-medium"
              >
                {on && (
                  <motion.span
                    layoutId="nav-pill"
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
                  {label}
                </span>
              </Link>
            )
          })}
        </nav>

        <div className="flex items-center gap-1.5 sm:gap-2.5">
          <Link
            href="/pin"
            className="relative flex h-9 w-9 items-center justify-center rounded-full text-muted transition hover:bg-surface-2 hover:text-text"
            title={user?.has_transaction_pin ? 'PIN active' : 'Set your PIN'}
            aria-label="Security"
          >
            <BellIcon size={18} />
            {!user?.has_transaction_pin && (
              <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-amber ring-2 ring-white" />
            )}
          </Link>
          <Link
            href="/profile"
            className="flex h-9 w-9 items-center justify-center rounded-full bg-mint/[0.12] font-display text-sm font-bold text-mint transition hover:bg-mint/20"
            aria-label="Profile"
          >
            {initial}
          </Link>
          <button
            onClick={() => router.post('/logout')}
            className="hidden text-sm text-muted transition hover:text-danger sm:block sm:pl-1"
          >
            Sign out
          </button>
        </div>
      </header>

      <main className="flex-1 pt-5">
        {/* No AnimatePresence + mode="wait" — it leaves a blank frame during Inertia visits */}
        <motion.div
          key={pathname}
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.18, ease: 'easeOut' }}
        >
          {children}
        </motion.div>
      </main>

      {/* Floating mobile dock with a centre action */}
      <nav className="fixed inset-x-0 bottom-4 z-30 px-5 sm:hidden">
        <div className="dock relative mx-auto flex max-w-sm items-center justify-between rounded-[22px] px-3 py-2">
          {dockLeft.map((n) => (
            <DockItem key={n.to} {...n} on={active(n.to, n.end)} />
          ))}

          <Link
            href="/send"
            className="fab -mt-9 flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-white"
            aria-label="Send money"
          >
            <motion.span
              whileTap={{ scale: 0.88, rotate: 90 }}
              transition={{ type: 'spring', stiffness: 400, damping: 18 }}
            >
              <SendIcon size={24} />
            </motion.span>
          </Link>

          {dockRight.map((n) => (
            <DockItem key={n.to} {...n} on={active(n.to, n.end)} />
          ))}
        </div>
      </nav>
    </div>
  )
}

function DockItem({
  to,
  label,
  Icon,
  on,
}: {
  to: string
  label: string
  end?: boolean
  Icon: (p: { size?: number }) => JSX.Element
  on: boolean
}) {
  return (
    <Link
      href={to}
      className={`relative flex flex-1 flex-col items-center gap-0.5 rounded-xl py-1.5 text-[10px] font-semibold transition-colors ${
        on ? 'text-mint' : 'text-muted'
      }`}
    >
      <Icon size={21} />
      {label}
      {on && (
        <motion.span
          layoutId="dock-dot"
          className="absolute -bottom-0.5 h-1 w-1 rounded-full bg-mint"
          transition={spring}
        />
      )}
    </Link>
  )
}
