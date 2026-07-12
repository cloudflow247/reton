import type { ReactNode } from 'react'
import { useState } from 'react'
import { Link, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { Wordmark } from './ui'
import { PoweredByAlatInline } from './PoweredByAlat'
import { GetTheAppSection } from './GetTheAppSection'
import { ArrowRightIcon, LockIcon } from './icons'

const nav: { label: string; to: string; soon?: boolean }[] = [
  { label: 'Business', to: '/business', soon: true },
  { label: 'How it works', to: '/how-it-works' },
  { label: 'Security', to: '/security' },
  { label: 'FAQ', to: '/faq' },
]

export function PublicLayout({ children }: { children: ReactNode }) {
  const pathname = usePage().url.split('?')[0]
  const [menuOpen, setMenuOpen] = useState(false)

  return (
    <div className="flex min-h-full flex-col bg-bg text-text">
      <header className="glass sticky top-0 z-30 border-b border-line/70">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-5 py-3">
          <Link href="/" className="transition-opacity hover:opacity-80" aria-label="Reton home">
            <Wordmark />
          </Link>

          <nav className="hidden items-center gap-1 lg:flex" aria-label="Primary">
            {nav.map(({ label, to, soon }) => {
              const active = pathname === to
              return (
                <Link
                  key={to}
                  href={to}
                  aria-current={active ? 'page' : undefined}
                  className={`relative inline-flex items-center gap-1.5 rounded-full px-3.5 py-2 text-sm font-medium transition-colors ${
                    active ? 'text-text' : 'text-muted hover:text-text'
                  }`}
                >
                  {label}
                  {soon && (
                    <span className="rounded-full bg-amber/15 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber">
                      Soon
                    </span>
                  )}
                  {active && (
                    <motion.span
                      layoutId="nav-active"
                      className="absolute inset-0 -z-10 rounded-full bg-mint/10"
                      transition={{ type: 'spring', stiffness: 380, damping: 30 }}
                    />
                  )}
                </Link>
              )
            })}
          </nav>

          <div className="flex items-center gap-2">
            <Link
              href="/login"
              className="hidden rounded-full px-3.5 py-2 text-sm font-medium text-muted transition-colors hover:text-text sm:inline-flex"
            >
              Sign in
            </Link>
            <Link
              href="/register"
              className="btn inline-flex items-center gap-1.5 bg-mint px-4 py-2 text-sm text-white shadow-sm transition hover:bg-mint-strong"
            >
              Get started <ArrowRightIcon size={15} />
            </Link>
            <button
              type="button"
              className="inline-flex h-10 items-center justify-center rounded-xl border border-line bg-surface px-3 text-xs font-bold uppercase tracking-wide text-text lg:hidden"
              aria-expanded={menuOpen}
              aria-controls="public-mobile-nav"
              onClick={() => setMenuOpen((v) => !v)}
            >
              {menuOpen ? 'Close' : 'Menu'}
            </button>
          </div>
        </div>

        <AnimatePresence>
          {menuOpen && (
            <motion.nav
              id="public-mobile-nav"
              initial={{ height: 0, opacity: 0 }}
              animate={{ height: 'auto', opacity: 1 }}
              exit={{ height: 0, opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="overflow-hidden border-t border-line lg:hidden"
              aria-label="Mobile"
            >
              <div className="space-y-1 px-4 py-3">
                {nav.map(({ label, to, soon }) => (
                  <Link
                    key={to}
                    href={to}
                    onClick={() => setMenuOpen(false)}
                    className={`flex items-center justify-between rounded-xl px-3 py-3 text-sm font-semibold ${
                      pathname === to ? 'bg-mint/10 text-mint' : 'text-text hover:bg-surface-2'
                    }`}
                  >
                    <span>{label}</span>
                    {soon && <span className="text-[10px] font-bold uppercase tracking-wide text-amber">Soon</span>}
                  </Link>
                ))}
                <Link
                  href="/about"
                  onClick={() => setMenuOpen(false)}
                  className="flex rounded-xl px-3 py-3 text-sm font-semibold text-text hover:bg-surface-2"
                >
                  About
                </Link>
                <Link
                  href="/contact"
                  onClick={() => setMenuOpen(false)}
                  className="flex rounded-xl px-3 py-3 text-sm font-semibold text-text hover:bg-surface-2"
                >
                  Contact
                </Link>
              </div>
            </motion.nav>
          )}
        </AnimatePresence>
      </header>

      <motion.main
        key={pathname}
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.3, ease: 'easeOut' }}
        className="flex-1"
      >
        {children}
      </motion.main>

      <GetTheAppSection />

      <footer className="border-t border-line bg-surface/70">
        <div className="border-b border-line">
          <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-5 py-6 sm:flex-row">
            <PoweredByAlatInline />
            <p className="text-center text-xs leading-relaxed text-muted sm:text-right">
              Funding &amp; settlement run on a licensed Nigerian bank rail.
            </p>
          </div>
        </div>
        <div className="mx-auto max-w-6xl px-5 py-14">
          <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
            <div className="space-y-4">
              <Wordmark />
              <p className="max-w-xs text-sm leading-relaxed text-muted">
                Trust-first digital banking for Africa — payments you can take back.
              </p>
              <p className="max-w-xs text-sm leading-relaxed text-muted">
                7, Greenland Estate, Ikorodu, Lagos State, Nigeria
              </p>
              <span className="inline-flex items-center gap-1.5 rounded-full bg-mint/10 px-3 py-1 text-xs font-medium text-mint">
                <LockIcon size={13} /> Bank-grade security
              </span>
            </div>
            <FooterCol
              title="Product"
              links={[
                ['How it works', '/how-it-works'],
                ['Security', '/security'],
                ['For business', '/business'],
              ]}
            />
            <FooterCol title="Company" links={[['About', '/about'], ['FAQ', '/faq'], ['Contact', '/contact']]} />
            <FooterCol title="Get started" links={[['Create wallet', '/register'], ['Sign in', '/login'], ['Support', '/contact']]} />
          </div>
          <div className="mt-12 flex flex-col gap-2 border-t border-line pt-6 text-xs text-muted sm:flex-row sm:items-center sm:justify-between">
            <span>© 2026 Reton · 7, Greenland Estate, Ikorodu, Lagos State, Nigeria</span>
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
      <h4 className="font-display text-sm font-semibold tracking-tight">{title}</h4>
      <ul className="mt-4 space-y-2.5 text-sm text-muted">
        {links.map(([label, to]) => (
          <li key={label}>
            <Link href={to} className="transition-colors hover:text-text">
              {label}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  )
}
