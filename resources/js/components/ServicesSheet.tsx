import { useEffect } from 'react'
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
  useEffect(() => {
    if (!open) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    const prev = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', onKey)
    return () => {
      document.body.style.overflow = prev
      document.removeEventListener('keydown', onKey)
    }
  }, [open, onClose])

  const active = (to: string) => (to === '/dashboard' ? pathname === to : pathname.startsWith(to))

  return (
    <AnimatePresence>
      {open && (
        <div className="fixed inset-0 z-50 sm:hidden" role="dialog" aria-modal="true" aria-label="All services">
          <motion.button
            type="button"
            aria-label="Close services"
            className="absolute inset-0 bg-[#0a2a1f]/45 backdrop-blur-[2px]"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
          />

          <motion.div
            className="absolute inset-x-0 bottom-0 max-h-[min(88dvh,40rem)] overflow-hidden rounded-t-[1.75rem] border border-line bg-surface shadow-[0_-24px_60px_-28px_rgba(16,40,33,0.55)]"
            initial={{ y: '100%' }}
            animate={{ y: 0 }}
            exit={{ y: '100%' }}
            transition={PAGE_SPRING}
          >
            <div className="flex flex-col">
              <div className="shrink-0 px-4 pb-2 pt-3">
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
              </div>

              <div className="overflow-y-auto overscroll-contain px-4 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                {SERVICE_GROUPS.map((group) => {
                  const items = APP_SERVICES.filter((s) => s.group === group.id)
                  return (
                    <section key={group.id} className="mb-5">
                      <header className="mb-2 px-0.5">
                        <h2 className="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted">
                          {group.title}
                        </h2>
                        <p className="text-xs text-muted/90">{group.blurb}</p>
                      </header>
                      <ul className="grid grid-cols-1 gap-1.5">
                        {items.map((service) => (
                          <ServiceRow
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

function ServiceRow({
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
        className={`flex items-center gap-3 rounded-2xl border px-3 py-3 transition ${
          on
            ? 'border-mint/30 bg-mint/[0.08] text-mint'
            : 'border-line/80 bg-surface-2/40 text-text hover:border-mint/25'
        }`}
      >
        <span
          className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${
            on ? 'bg-mint/15 text-mint' : 'bg-surface text-mint'
          }`}
        >
          <Icon size={18} />
        </span>
        <span className="min-w-0 flex-1">
          <span className="flex items-center gap-2">
            <span className="block text-sm font-semibold text-text">{label}</span>
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
          <span className="block truncate text-xs text-muted">{hint}</span>
        </span>
      </Link>
    </li>
  )
}
