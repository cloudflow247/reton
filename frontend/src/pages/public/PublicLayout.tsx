import type { ReactNode } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import { motion } from 'framer-motion'
import { Wordmark } from '../../components/ui'
import { ArrowRightIcon, LockIcon } from '../../components/icons'

export function PublicLayout({ children }: { children: ReactNode }) {
  const { pathname } = useLocation()

  const link = ({ isActive }: { isActive: boolean }) =>
    `transition-colors ${isActive ? 'text-text' : 'text-muted hover:text-text'}`

  return (
    <div className="min-h-full">
      <header className="sticky top-0 z-30 border-b border-line bg-surface/80 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-5 py-4">
          <Link to="/">
            <Wordmark />
          </Link>
          <nav className="hidden items-center gap-8 text-sm font-medium sm:flex">
            <NavLink to="/security" className={link}>
              Security
            </NavLink>
            <NavLink to="/how-it-works" className={link}>
              How it works
            </NavLink>
            <Link to="/login" className="text-muted transition-colors hover:text-text">
              Sign in
            </Link>
          </nav>
          <Link
            to="/register"
            className="btn inline-flex items-center gap-1.5 bg-mint px-4 py-2 text-sm text-white shadow-sm hover:bg-mint-strong"
          >
            Get started <ArrowRightIcon size={15} />
          </Link>
        </div>
      </header>

      <motion.main
        key={pathname}
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.3, ease: 'easeOut' }}
      >
        {children}
      </motion.main>

      <footer className="mt-24 border-t border-line bg-surface/60">
        <div className="mx-auto max-w-6xl px-5 py-12">
          <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-3">
              <Wordmark />
              <p className="max-w-xs text-sm leading-relaxed text-muted">
                Trust-first digital banking for Africa — payments you can take back.
              </p>
              <span className="inline-flex items-center gap-1.5 rounded-full bg-mint/10 px-3 py-1 text-xs font-medium text-mint">
                <LockIcon size={13} /> Bank-grade security
              </span>
            </div>
            <FooterCol title="Product" links={[['How it works', '/how-it-works'], ['Security', '/security'], ['Sign in', '/login']]} />
            <FooterCol title="Company" links={[['About', '/'], ['Careers', '/'], ['Contact', '/']]} />
            <FooterCol title="Legal" links={[['Terms', '/'], ['Privacy', '/'], ['Compliance', '/security']]} />
          </div>
          <div className="mt-10 flex flex-col gap-2 border-t border-line pt-6 text-xs text-muted sm:flex-row sm:items-center sm:justify-between">
            <span>© 2026 Reton Pte Ltd · reton.ng</span>
            <span>Made for a safer Africa.</span>
          </div>
        </div>
      </footer>
    </div>
  )
}

function FooterCol({ title, links }: { title: string; links: [string, string][] }) {
  return (
    <div>
      <h4 className="font-display text-sm font-semibold">{title}</h4>
      <ul className="mt-3 space-y-2 text-sm text-muted">
        {links.map(([label, to]) => (
          <li key={label}>
            <Link to={to} className="transition-colors hover:text-text">
              {label}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  )
}
