import type { ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { Logo, Wordmark } from './ui'
import { PoweredByAlatInline } from './PoweredByAlat'
import { LockIcon, ScanIcon, ShieldIcon, UndoIcon } from './icons'

const pillars = [
  { Icon: ShieldIcon, k: 'Callback', v: 'Hold funds until you confirm' },
  { Icon: UndoIcon, k: 'Recovery', v: 'Claw back a wrong transfer' },
  { Icon: ScanIcon, k: 'Fraud', v: 'Blocked before it settles' },
]

export function AuthLayout({ children, title, sub }: { children: ReactNode; title: string; sub: string }) {
  return (
    <div className="relative min-h-full overflow-hidden">
      <div className="aurora" aria-hidden />

      <div className="relative mx-auto grid min-h-full max-w-6xl items-center gap-12 px-6 py-10 lg:grid-cols-2">
        {/* Thesis: the most characteristic thing about Reton is that money can come back. */}
        <motion.section
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
          className="hidden lg:block"
        >
          <Link href="/">
            <Wordmark />
          </Link>

          {/* Animated trust mark */}
          <div className="relative mt-10 inline-flex h-16 w-16 items-center justify-center">
            <span className="trust-ring absolute inset-0 rounded-full" aria-hidden />
            <span className="absolute inset-[2px] flex items-center justify-center rounded-full bg-surface">
              <Logo size={30} />
            </span>
          </div>

          <h1 className="mt-7 font-display text-5xl font-bold leading-[1.05] tracking-tight">
            The first wallet with an <span className="gradient-text">undo button</span> for money.
          </h1>
          <p className="mt-5 max-w-md text-[15px] leading-relaxed text-muted">
            Send with callback protection, recover wrong transfers, and let real-time fraud checks watch every
            move. If something goes wrong, Reton can bring your money back.
          </p>

          <div className="mt-10 flex gap-3">
            {pillars.map(({ Icon, k, v }, i) => (
              <motion.div
                key={k}
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.2 + i * 0.08 }}
                className="card elevate flex-1 p-4"
              >
                <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-mint/10 text-mint">
                  <Icon size={18} />
                </span>
                <div className="mt-3 font-display text-sm font-semibold text-text">{k}</div>
                <div className="mt-0.5 text-xs leading-snug text-muted">{v}</div>
              </motion.div>
            ))}
          </div>

          <div className="mt-10">
            <PoweredByAlatInline />
          </div>
        </motion.section>

        <motion.section
          initial={{ opacity: 0, y: 18, scale: 0.99 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1], delay: 0.1 }}
          className="card shield-glow mx-auto w-full max-w-md p-7"
        >
          <Link href="/" className="lg:hidden">
            <Wordmark />
          </Link>
          <h2 className="mt-6 font-display text-2xl font-bold tracking-tight lg:mt-0">{title}</h2>
          <p className="mt-1 text-sm text-muted">{sub}</p>
          <div className="mt-6">{children}</div>
          <p className="mt-6 flex items-center justify-center gap-1.5 border-t border-line pt-4 text-xs text-muted">
            <LockIcon size={13} /> Bank-grade encryption · your money is protected
          </p>
        </motion.section>
      </div>
    </div>
  )
}
