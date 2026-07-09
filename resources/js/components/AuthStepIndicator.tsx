import { motion } from 'framer-motion'

export function AuthStepIndicator({ step, total }: { step: number; total: number }) {
  return (
    <div className="mb-6 flex items-center gap-2" aria-label={`Step ${step + 1} of ${total}`}>
      {Array.from({ length: total }, (_, i) => (
        <div key={i} className="flex flex-1 items-center gap-2">
          <motion.span
            layout
            className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors ${
              i < step
                ? 'bg-mint text-white'
                : i === step
                  ? 'bg-mint/15 text-mint ring-2 ring-mint/40'
                  : 'bg-surface-2 text-muted'
            }`}
          >
            {i < step ? '✓' : i + 1}
          </motion.span>
          {i < total - 1 && (
            <span className={`h-0.5 flex-1 rounded-full ${i < step ? 'bg-mint' : 'bg-line'}`} />
          )}
        </div>
      ))}
    </div>
  )
}
