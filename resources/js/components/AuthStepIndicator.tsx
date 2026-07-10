import { motion } from 'framer-motion'

const labels = ['Account', 'Contact', 'Security', 'Verify', 'Reset']

export function AuthStepIndicator({ step, total }: { step: number; total: number }) {
  const progressPct =
    total <= 1 ? 100 : Math.min(99, Math.round(((step + 0.5) / total) * 100))

  return (
    <div aria-label={`Step ${step + 1} of ${total}`}>
      <div className="mb-2 flex items-center justify-between text-[10px] font-semibold uppercase tracking-wider text-muted">
        <span>Step {step + 1} of {total}</span>
        <span className="text-mint">{progressPct}%</span>
      </div>
      <div className="flex items-center gap-2">
        {Array.from({ length: total }, (_, i) => (
          <div key={i} className="flex flex-1 items-center gap-2">
            <motion.span
              layout
              className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-all duration-300 ${
                i < step
                  ? 'bg-mint text-white shadow-[0_6px_16px_-8px_rgba(9,79,57,0.8)]'
                  : i === step
                    ? 'bg-mint/12 text-mint ring-2 ring-mint/35'
                    : 'bg-surface-2 text-muted'
              }`}
            >
              {i < step ? (
                <motion.span initial={{ scale: 0.5 }} animate={{ scale: 1 }}>
                  ✓
                </motion.span>
              ) : (
                i + 1
              )}
            </motion.span>
            {i < total - 1 && (
              <motion.span
                layout
                className="relative h-1 flex-1 overflow-hidden rounded-full bg-line"
              >
                <motion.span
                  className="absolute inset-y-0 left-0 rounded-full bg-mint"
                  initial={false}
                  animate={{ width: i < step ? '100%' : '0%' }}
                  transition={{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }}
                />
              </motion.span>
            )}
          </div>
        ))}
      </div>
      <p className="mt-2 hidden text-xs text-muted sm:block">{labels[step] ?? 'Continue'}</p>
    </div>
  )
}
