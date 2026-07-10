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
    error: 'auth-alert auth-alert-error',
    success: 'auth-alert auth-alert-success',
    info: 'auth-alert auth-alert-info',
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
          <Icon size={16} className="mt-0.5 shrink-0 opacity-90" />
          <span className="leading-relaxed font-medium">{message}</span>
        </motion.div>
      )}
    </AnimatePresence>
  )
}
