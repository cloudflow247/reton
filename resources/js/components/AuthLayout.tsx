import type { ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AuthStepIndicator } from './AuthStepIndicator'
import { Logo, Wordmark } from './ui'
import { PoweredByAlatInline } from './PoweredByAlat'
import { LockIcon, ScanIcon, ShieldIcon, SparkleIcon, UndoIcon } from './icons'

const pillars = [
  { Icon: ShieldIcon, k: 'Callback protection', v: 'Funds are held until you confirm the transfer.' },
  { Icon: UndoIcon, k: 'Transfer recovery', v: 'Claw back money sent to the wrong account.' },
  { Icon: ScanIcon, k: 'Live fraud checks', v: 'Suspicious moves are blocked before they settle.' },
]

const ease = [0.22, 1, 0.36, 1] as const

export function AuthLayout({
  children,
  title,
  sub,
  step,
  totalSteps,
}: {
  children: ReactNode
  title: string
  sub: string
  step?: number
  totalSteps?: number
}) {
  return (
    <div className="relative min-h-full overflow-hidden">
      <div className="aurora" aria-hidden />

      <div className="relative mx-auto grid min-h-full max-w-6xl items-center gap-12 px-6 py-10 lg:grid-cols-[1.05fr_minmax(0,1fr)] lg:gap-16 lg:py-16">
        <motion.section
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, ease }}
          className="hidden lg:flex lg:flex-col"
        >
          <Link href="/" className="inline-flex w-fit">
            <Wordmark size={34} />
          </Link>

          <div className="relative mt-12 inline-flex h-16 w-16 items-center justify-center">
            <span className="trust-ring absolute inset-0 rounded-full" aria-hidden />
            <span className="absolute inset-[2px] flex items-center justify-center rounded-full bg-surface shadow-sm">
              <Logo size={30} />
            </span>
          </div>

          <div className="mt-6 inline-flex w-fit items-center gap-1.5 rounded-full border border-mint/20 bg-mint/[0.07] px-3 py-1.5 text-xs font-semibold tracking-wide text-mint">
            <SparkleIcon size={14} /> Bank-grade security, by design
          </div>

          <h1 className="mt-5 max-w-xl font-display text-[3.25rem] font-bold leading-[1.04] tracking-tight">
            The first wallet with an <span className="gradient-text">undo button</span> for money.
          </h1>
          <p className="mt-5 max-w-md text-[15px] leading-relaxed text-muted">
            Send with callback protection, recover wrong transfers, and let real-time fraud checks watch every move.
          </p>

          <div className="mt-10 space-y-3">
            {pillars.map(({ Icon, k, v }, i) => (
              <motion.div
                key={k}
                initial={{ opacity: 0, x: -12 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: 0.22 + i * 0.08, ease }}
                className="card elevate group flex items-center gap-4 p-4"
              >
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-mint/10 text-mint transition-colors group-hover:bg-mint group-hover:text-white">
                  <Icon size={20} />
                </span>
                <div className="min-w-0">
                  <div className="font-display text-sm font-semibold text-text">{k}</div>
                  <div className="mt-0.5 text-xs leading-snug text-muted">{v}</div>
                </div>
              </motion.div>
            ))}
          </div>

          <div className="mt-10 border-t border-line pt-6">
            <PoweredByAlatInline />
          </div>
        </motion.section>

        <motion.section
          initial={{ opacity: 0, y: 18, scale: 0.99 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          transition={{ duration: 0.5, ease, delay: 0.1 }}
          className="card shield-glow mx-auto w-full max-w-md p-7 sm:p-8"
        >
          <Link href="/" className="mx-auto flex w-fit flex-col items-center gap-2 lg:mx-0 lg:items-start">
            <Logo size={44} />
            <Wordmark size={26} />
          </Link>

          {totalSteps !== undefined && step !== undefined && (
            <div className="mt-6">
              <AuthStepIndicator step={step} total={totalSteps} />
            </div>
          )}

          <h2 className="mt-4 text-center font-display text-[1.7rem] font-bold leading-tight tracking-tight lg:text-left">
            {title}
          </h2>
          <p className="mt-1.5 text-center text-sm text-muted lg:text-left">{sub}</p>
          <div className="mt-7">{children}</div>
          <p className="mt-7 flex items-center justify-center gap-1.5 border-t border-line pt-5 text-xs text-muted lg:justify-start">
            <LockIcon size={13} className="text-mint" /> Bank-grade encryption · your money is protected
          </p>
        </motion.section>
      </div>
    </div>
  )
}
