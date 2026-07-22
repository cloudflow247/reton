import { motion } from 'framer-motion'

function score(password: string): number {
  if (!password) return 0
  let s = 0
  if (password.length >= 8) s++
  if (password.length >= 12) s++
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) s++
  if (/\d/.test(password)) s++
  if (/[^a-zA-Z0-9]/.test(password)) s++
  return Math.min(s, 4)
}

const labels = ['Too weak', 'Fair', 'Good', 'Strong', 'Excellent'] as const
const colors = ['bg-danger', 'bg-amber', 'bg-mint/60', 'bg-mint', 'bg-mint-strong'] as const

export function PasswordStrength({ password }: { password: string }) {
  if (!password) return null

  const s = score(password)
  const label = labels[s]

  return (
    <motion.div
      initial={{ opacity: 0, height: 0 }}
      animate={{ opacity: 1, height: 'auto' }}
      className="space-y-2 overflow-hidden"
    >
      <div className="flex gap-1" aria-hidden>
        {Array.from({ length: 4 }, (_, i) => (
          <span
            key={i}
            className={`h-1 flex-1 rounded-full transition-colors duration-300 ${
              i < s ? colors[s] : 'bg-line'
            }`}
          />
        ))}
      </div>
      <p className={`text-xs font-medium ${s >= 3 ? 'text-mint' : s >= 2 ? 'text-amber' : 'text-danger'}`}>
        {label}
        {s < 2 && ' - use 8+ characters with letters and numbers'}
      </p>
    </motion.div>
  )
}
