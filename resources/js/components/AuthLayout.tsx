import type { ReactNode } from 'react'
import { motion } from 'framer-motion'
import { AuthBrand, Logo } from './AuthBrand'
import { AuthStepIndicator } from './AuthStepIndicator'
import { PoweredByAlatInline } from './PoweredByAlat'
import { LockIcon, ScanIcon, ShieldIcon, SparkleIcon, UndoIcon } from './icons'

const pillars = [
  { Icon: ShieldIcon, k: 'Callback protection', v: 'Funds held until you confirm.' },
  { Icon: UndoIcon, k: 'Transfer recovery', v: 'Claw back wrong transfers fast.' },
  { Icon: ScanIcon, k: 'Live fraud checks', v: 'Suspicious moves blocked instantly.' },
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
    <div className="relative min-h-full overflow-hidden bg-[#f6faf8]">
      <div className="aurora" aria-hidden />
      <div className="auth-mesh pointer-events-none absolute inset-0" aria-hidden />

      {/* Mobile brand strip */}
      <motion.header
        initial={{ opacity: 0, y: -8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45, ease }}
        className="relative z-10 flex justify-center px-6 pt-6 lg:hidden"
      >
        <AuthBrand size="md" layout="horizontal" />
      </motion.header>

      <div className="relative z-10 mx-auto grid min-h-full max-w-6xl items-center gap-8 px-5 py-8 sm:px-6 lg:grid-cols-[1.05fr_minmax(0,1fr)] lg:gap-16 lg:py-16">
        <motion.section
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, ease }}
          className="hidden lg:flex lg:flex-col"
        >
          <AuthBrand size="lg" layout="horizontal" />

          <div className="relative mt-12 inline-flex h-[4.5rem] w-[4.5rem] items-center justify-center">
            <span className="trust-ring absolute inset-0 rounded-full" aria-hidden />
            <motion.span
              className="absolute inset-[3px] flex items-center justify-center rounded-full bg-surface shadow-sm"
              animate={{ scale: [1, 1.03, 1] }}
              transition={{ duration: 4, repeat: Infinity, ease: 'easeInOut' }}
            >
              <Logo size={34} />
            </motion.span>
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
          transition={{ duration: 0.5, ease, delay: 0.08 }}
          className="auth-card glass shield-glow mx-auto w-full max-w-md rounded-[1.35rem] p-6 sm:p-8"
        >
          <div className="hidden lg:block">
            <AuthBrand size="md" layout="horizontal" />
          </div>

          {totalSteps !== undefined && step !== undefined && (
            <div className="mt-5 lg:mt-6">
              <AuthStepIndicator step={step} total={totalSteps} />
            </div>
          )}

          <motion.div
            key={title}
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.28, ease }}
          >
            <h2 className="mt-1 text-center font-display text-[1.65rem] font-bold leading-tight tracking-tight sm:text-[1.7rem] lg:text-left">
              {title}
            </h2>
            <p className="mt-1.5 text-center text-sm leading-relaxed text-muted lg:text-left">{sub}</p>
          </motion.div>

          <div className="mt-6 sm:mt-7">{children}</div>

          {/* Mobile trust strip */}
          <div className="mt-6 grid grid-cols-3 gap-2 lg:hidden">
            {pillars.map(({ Icon, k }) => (
              <div
                key={k}
                className="flex flex-col items-center gap-1.5 rounded-xl border border-line/80 bg-surface/70 px-2 py-2.5 text-center"
              >
                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-mint/10 text-mint">
                  <Icon size={15} />
                </span>
                <span className="text-[10px] font-semibold leading-tight text-muted">{k.split(' ')[0]}</span>
              </div>
            ))}
          </div>

          <p className="mt-6 flex items-center justify-center gap-1.5 border-t border-line/80 pt-5 text-xs text-muted lg:justify-start">
            <LockIcon size={13} className="text-mint" /> Bank-grade encryption · your money is protected
          </p>
        </motion.section>
      </div>
    </div>
  )
}
