import { motion, AnimatePresence } from 'framer-motion'
import { CheckIcon, ShieldIcon } from './icons'

export function AuthAlert({
  tone = 'error',
  message,
}: {
  tone?: 'error' | 'success' | 'info'
  message?: string
}) {
  const styles = {
    error: 'border-red-200/80 bg-red-50 text-red-800 dark:border-red-900/40 dark:bg-red-950/35 dark:text-red-300',
    success: 'border-mint/30 bg-mint/[0.07] text-mint-strong',
    info: 'border-line bg-surface-2 text-muted',
  }[tone]

  const Icon = tone === 'success' ? CheckIcon : ShieldIcon

  return (
    <AnimatePresence>
      {message && (
        <motion.div
          role="alert"
          initial={{ opacity: 0, y: -6, height: 0 }}
          animate={{ opacity: 1, y: 0, height: 'auto' }}
          exit={{ opacity: 0, y: -6, height: 0 }}
          className={`mb-4 flex items-start gap-2.5 overflow-hidden rounded-xl border px-4 py-3 text-sm ${styles}`}
        >
          <Icon size={16} className="mt-0.5 shrink-0 opacity-80" />
          <span className="leading-relaxed">{message}</span>
        </motion.div>
      )}
    </AnimatePresence>
  )
}
