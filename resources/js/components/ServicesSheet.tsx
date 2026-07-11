import { useEffect, useRef } from 'react'
import { Link } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import {
  APP_SERVICES,
  SERVICE_GROUPS,
  isServiceSoon,
  type AppService,
} from '@/lib/app-services'
import type { FeatureFlags } from '@/types'
import { PAGE_SPRING } from '@/components/page-kit'

type Props = {
  open: boolean
  onClose: () => void
  features?: FeatureFlags
  pathname: string
  needsPin?: boolean
}

export function ServicesSheet({ open, onClose, features, pathname, needsPin = false }: Props) {
  const scrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return

    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }

    const scrollY = window.scrollY
    const { style } = document.body
    const prev = {
      overflow: style.overflow,
      position: style.position,
      top: style.top,
      width: style.width,
    }

    // Lock background without breaking nested touch scroll on iOS.
    style.overflow = 'hidden'
    style.position = 'fixed'
    style.top = `-${scrollY}px`
    style.width = '100%'

    document.addEventListener('keydown', onKey)

    // Ensure the sheet list can receive the first touch scroll.
    requestAnimationFrame(() => {
      scrollRef.current?.focus({ preventScroll: true })
    })

    return () => {
      style.overflow = prev.overflow
      style.position = prev.position
      style.top = prev.top
      style.width = prev.width
      window.scrollTo(0, scrollY)
      document.removeEventListener('keydown', onKey)
    }
  }, [open, onClose])

  const active = (to: string) => (to === '/dashboard' ? pathname === to : pathname.startsWith(to))

  return (
    <AnimatePresence>
      {open && (
        <div className="fixed inset-0 z-[60] sm:hidden" role="dialog" aria-modal="true" aria-label="All services">
          <motion.button
            type="button"
            aria-label="Close services"
            className="absolute inset-0 bg-[#0a2a1f]/50"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
          />

          <motion.div
            className="absolute inset-x-0 bottom-0 flex h-[min(90dvh,42rem)] max-h-[90dvh] flex-col overflow-hidden rounded-t-[1.75rem] border border-line bg-surface shadow-[0_-24px_60px_-28px_rgba(16,40,33,0.55)]"
            initial={{ y: '100%' }}
            animate={{ y: 0 }}
            exit={{ y: '100%' }}
            transition={PAGE_SPRING}
            onClick={(e) => e.stopPropagation()}
          >
            <header className="shrink-0 px-4 pb-3 pt-3">
              <div className="mx-auto mb-3 h-1 w-10 rounded-full bg-line" />
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-display text-lg font-bold tracking-tight">Services</p>
                  <p className="mt-0.5 text-xs text-muted">Security first · everything in one place</p>
                </div>
                <button
                  type="button"
                  onClick={onClose}
                  className="rounded-full border border-line bg-surface-2 px-3 py-1.5 text-xs font-semibold text-muted transition hover:text-text"
                >
                  Close
                </button>
              </div>
            </header>

            <div
              ref={scrollRef}
              tabIndex={-1}
              className="min-h-0 flex-1 overflow-y-auto overscroll-y-contain px-4 [-webkit-overflow-scrolling:touch] [touch-action:pan-y]"
              style={{ WebkitOverflowScrolling: 'touch' }}
            >
              <div className="space-y-5 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
                {SERVICE_GROUPS.map((group) => {
                  const items = APP_SERVICES.filter((s) => s.group === group.id)
                  return (
                    <section key={group.id}>
                      <header className="mb-2 px-0.5">
                        <h2 className="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">
                          {group.title}
                        </h2>
                        <p className="text-xs text-muted/90">{group.blurb}</p>
                      </header>
                      <ul className="grid grid-cols-2 gap-2">
                        {items.map((service) => (
                          <ServiceTile
                            key={service.to}
                            service={service}
                            on={active(service.to)}
                            soon={isServiceSoon(service, features)}
                            badge={service.to === '/pin' && needsPin ? 'Set up' : undefined}
                            onNavigate={onClose}
                          />
                        ))}
                      </ul>
                    </section>
                  )
                })}
              </div>
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}

function ServiceTile({
  service,
  on,
  soon,
  badge,
  onNavigate,
}: {
  service: AppService
  on: boolean
  soon: boolean
  badge?: string
  onNavigate: () => void
}) {
  const { to, label, hint, Icon } = service

  return (
    <li>
      <Link
        href={to}
        prefetch
        onClick={onNavigate}
        className={`flex h-full min-h-[5.25rem] flex-col gap-2 rounded-2xl border p-3 transition active:scale-[0.98] ${
          on
            ? 'border-mint/35 bg-mint/[0.1] text-mint'
            : 'border-line/80 bg-surface-2/50 text-text active:border-mint/25'
        }`}
      >
        <span className="flex items-start justify-between gap-2">
          <span
            className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ${
              on ? 'bg-mint/15 text-mint' : 'bg-surface text-mint'
            }`}
          >
            <Icon size={17} />
          </span>
          <span className="flex flex-wrap justify-end gap-1">
            {soon && (
              <span className="rounded-md bg-amber/15 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber">
                Soon
              </span>
            )}
            {badge && (
              <span className="rounded-md bg-mint/15 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-mint">
                {badge}
              </span>
            )}
          </span>
        </span>
        <span className="min-w-0">
          <span className="block text-sm font-semibold leading-tight text-text">{label}</span>
          <span className="mt-0.5 block text-[11px] leading-snug text-muted">{hint}</span>
        </span>
      </Link>
    </li>
  )
}
