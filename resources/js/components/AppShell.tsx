import { useEffect, useRef, useState, type ReactNode } from 'react'
import { Link, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import type { SharedProps } from '@/types'
import { useAdminBase } from '@/lib/admin'
import { PAGE_SPRING } from '@/components/page-kit'
import { Wordmark } from './ui'
import { ProfileMenu } from './ProfileMenu'
import {
  ActivityIcon,
  BankIcon,
  BillIcon,
  CardIcon,
  ChatIcon,
  ChevronDownIcon,
  GiftIcon,
  HomeIcon,
  LockIcon,
  SendIcon,
  ShieldIcon,
  UserIcon,
} from './icons'

type NavItem = {
  to: string
  label: string
  end?: boolean
  Icon: (p: { size?: number; className?: string }) => JSX.Element
  hint?: string
}

/** Always visible — core money actions with labels. */
const primaryNav: NavItem[] = [
  { to: '/dashboard', label: 'Home', end: true, Icon: HomeIcon, hint: 'Wallet & overview' },
  { to: '/send', label: 'Send', Icon: SendIcon, hint: 'Transfer money' },
  { to: '/withdraw', label: 'Withdraw', Icon: BankIcon, hint: 'Cash out to bank' },
  { to: '/bills', label: 'Bills', Icon: BillIcon, hint: 'Airtime, power & more' },
  { to: '/cards', label: 'Cards', Icon: CardIcon, hint: 'Virtual cards' },
]

/** Nested under More — still labeled, keeps the bar readable. */
const moreNav: NavItem[] = [
  { to: '/activity', label: 'Activity', Icon: ActivityIcon, hint: 'Transaction history' },
  { to: '/marketplace', label: 'Shop', Icon: GiftIcon, hint: 'Protected marketplace' },
  { to: '/protection', label: 'Protection', Icon: ShieldIcon, hint: 'Callbacks & recoveries' },
]

const dockLeft: NavItem[] = [
  { to: '/dashboard', label: 'Home', end: true, Icon: HomeIcon },
  { to: '/bills', label: 'Bills', Icon: BillIcon },
]
const dockRight: NavItem[] = [
  { to: '/withdraw', label: 'Withdraw', Icon: BankIcon },
  { to: '/profile', label: 'Profile', Icon: UserIcon },
]

export function AppShell({ children }: { children: ReactNode }) {
  const page = usePage<SharedProps>()
  const user = page.props.auth?.user
  const adminBase = useAdminBase()
  const pathname = page.url.split('?')[0]
  const needsPin = !user?.has_transaction_pin
  const [moreOpen, setMoreOpen] = useState(false)
  const moreRef = useRef<HTMLDivElement>(null)

  const active = (to: string, end?: boolean) => (end ? pathname === to : pathname.startsWith(to))
  const moreActive = moreNav.some(({ to, end }) => active(to, end))

  useEffect(() => {
    setMoreOpen(false)
  }, [pathname])

  useEffect(() => {
    if (!moreOpen) return
    const onPointer = (e: MouseEvent) => {
      if (moreRef.current && !moreRef.current.contains(e.target as Node)) {
        setMoreOpen(false)
      }
    }
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setMoreOpen(false)
    }
    document.addEventListener('mousedown', onPointer)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onPointer)
      document.removeEventListener('keydown', onKey)
    }
  }, [moreOpen])

  return (
    <div className="mx-auto flex min-h-full max-w-6xl flex-col px-4 pb-[calc(7.5rem+env(safe-area-inset-bottom))] sm:px-6 sm:pb-10">
      <header className="dock sticky top-3 z-30 mt-3 flex items-center gap-3 rounded-2xl px-3 py-2.5 sm:gap-4 sm:px-4">
        <Link href="/dashboard" className="relative z-20 shrink-0 transition-opacity hover:opacity-90">
          <Wordmark />
        </Link>

        <nav className="hidden min-w-0 flex-1 items-center justify-start gap-0.5 pl-1 lg:flex" aria-label="Primary">
          {primaryNav.map((item) => (
            <NavLink key={item.to} item={item} on={active(item.to, item.end)} />
          ))}

          <div className="relative" ref={moreRef}>
            <button
              type="button"
              onClick={() => setMoreOpen((v) => !v)}
              aria-expanded={moreOpen}
              aria-haspopup="menu"
              className={`relative inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-2 text-sm font-medium transition ${
                moreActive || moreOpen ? 'bg-mint/[0.12] text-mint' : 'text-muted hover:bg-surface-2 hover:text-text'
              }`}
            >
              <span>More</span>
              <ChevronDownIcon
                size={14}
                className={`transition-transform ${moreOpen ? 'rotate-180' : ''}`}
              />
            </button>

            {moreOpen && (
              <div
                role="menu"
                className="absolute left-0 top-[calc(100%+0.5rem)] z-40 w-64 overflow-hidden rounded-2xl border border-line bg-surface p-1.5 shadow-[0_18px_44px_-22px_rgba(16,40,33,0.45)]"
              >
                <p className="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-muted">
                  More places
                </p>
                {moreNav.map(({ to, label, end, Icon, hint }) => {
                  const on = active(to, end)
                  return (
                    <Link
                      key={to}
                      href={to}
                      role="menuitem"
                      onClick={() => setMoreOpen(false)}
                      className={`flex items-start gap-3 rounded-xl px-3 py-2.5 transition ${
                        on ? 'bg-mint/[0.1] text-mint' : 'text-text hover:bg-surface-2'
                      }`}
                    >
                      <span
                        className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${
                          on ? 'bg-mint/15 text-mint' : 'bg-surface-2 text-muted'
                        }`}
                      >
                        <Icon size={16} />
                      </span>
                      <span className="min-w-0">
                        <span className="block text-sm font-semibold">{label}</span>
                        {hint && <span className="block text-xs text-muted">{hint}</span>}
                      </span>
                    </Link>
                  )
                })}
              </div>
            )}
          </div>
        </nav>

        <div className="relative z-20 flex shrink-0 items-center gap-1">
          {user?.is_admin && page.props.adminPath && (
            <Link
              href={adminBase}
              className="hidden whitespace-nowrap rounded-full border border-amber/30 bg-amber/10 px-3 py-1.5 text-xs font-semibold text-amber transition hover:bg-amber/15 md:inline-flex"
            >
              Admin
            </Link>
          )}
          <Link
            href="/support"
            className={`relative hidden h-9 items-center gap-1.5 rounded-full px-3 text-xs font-semibold transition hover:bg-surface-2 xl:inline-flex ${
              active('/support') ? 'bg-mint/[0.1] text-mint' : 'text-muted hover:text-text'
            }`}
            title="Help & support"
          >
            <ChatIcon size={15} />
            Support
          </Link>
          <Link
            href="/support"
            className={`relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition hover:bg-surface-2 xl:hidden ${
              active('/support') ? 'bg-mint/[0.1] text-mint' : 'text-muted hover:text-text'
            }`}
            title="Help & support"
            aria-label="Help and support"
          >
            <ChatIcon size={17} />
          </Link>
          <Link
            href="/pin"
            className="relative hidden h-9 w-9 shrink-0 items-center justify-center rounded-full text-muted transition hover:bg-surface-2 hover:text-text md:inline-flex lg:hidden"
            title={needsPin ? 'Set your PIN' : 'Transaction PIN'}
            aria-label="Transaction PIN"
          >
            <LockIcon size={17} />
            {needsPin && (
              <span className="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-amber ring-2 ring-surface" />
            )}
          </Link>
          {user && (
            <ProfileMenu
              user={user}
              needsPin={needsPin}
              profileActive={active('/profile')}
            />
          )}
        </div>
      </header>

      <main className="flex-1 pt-4 sm:pt-5">
        <motion.div
          key={pathname}
          initial={{ opacity: 0, y: 4 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.12, ease: [0.22, 1, 0.36, 1] }}
        >
          {children}
        </motion.div>
      </main>

      <nav
        className="fixed inset-x-0 bottom-0 z-30 px-5 pb-[max(1rem,env(safe-area-inset-bottom))] pt-2 sm:hidden"
        aria-label="Main"
      >
        <div className="dock relative mx-auto flex max-w-sm items-center justify-between rounded-[22px] px-2 py-2">
          {dockLeft.map((n) => (
            <DockItem key={n.to} {...n} on={active(n.to, n.end)} />
          ))}

          <Link href="/send" className="fab -mt-9 flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-white" aria-label="Send money">
            <motion.span whileTap={{ scale: 0.88 }} transition={PAGE_SPRING}>
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

function NavLink({ item, on }: { item: NavItem; on: boolean }) {
  const { to, label, Icon } = item
  return (
    <Link
      href={to}
      title={item.hint ?? label}
      className="relative shrink-0 rounded-full px-3 py-2 text-sm font-medium"
    >
      {on && (
        <motion.span
          layoutId="nav-pill"
          className="absolute inset-0 rounded-full bg-mint/[0.12]"
          transition={PAGE_SPRING}
        />
      )}
      <span
        className={`relative z-10 flex items-center gap-1.5 whitespace-nowrap transition-colors ${
          on ? 'text-mint' : 'text-muted hover:text-text'
        }`}
      >
        <Icon size={16} />
        <span>{label}</span>
      </span>
    </Link>
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
        <motion.span layoutId="dock-dot" className="absolute -bottom-0.5 h-1 w-1 rounded-full bg-mint" transition={PAGE_SPRING} />
      )}
    </Link>
  )
}
