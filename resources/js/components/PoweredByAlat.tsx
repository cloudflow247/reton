import { motion } from 'framer-motion'
import { BankIcon, BoltIcon, ShieldIcon } from './icons'

/**
 * The partner lockup: Reton settles real money on ALAT — Wema Bank's licensed
 * digital-banking rail. Surfaced prominently so visitors trust that funds move
 * on a regulated bank, not a closed wallet.
 */

/** The ALAT-by-Wema mark: a bank glyph in a branded emerald tile. */
export function AlatMark({ size = 44 }: { size?: number }) {
  return (
    <span
      className="flex items-center justify-center rounded-2xl bg-gradient-to-br from-[#0e7e5c] to-[#094f39] text-white shadow-[0_10px_24px_-12px_rgba(9,79,57,0.7)]"
      style={{ width: size, height: size }}
    >
      <BankIcon size={size * 0.5} />
    </span>
  )
}

/** Compact lockup for footers and tight spaces. */
export function PoweredByAlatInline() {
  return (
    <span className="inline-flex items-center gap-2.5">
      <AlatMark size={30} />
      <span className="leading-tight">
        <span className="block text-[10px] font-medium uppercase tracking-[0.18em] text-muted">Powered by</span>
        <span className="block font-display text-sm font-bold tracking-tight text-text">
          ALAT <span className="text-muted">by</span> Wema
        </span>
      </span>
    </span>
  )
}

/** A full-width trust band — the powerful, primary placement. */
export function PoweredByAlat() {
  const chips = [
    { Icon: ShieldIcon, label: 'Licensed bank rail' },
    { Icon: BoltIcon, label: 'Real-time settlement' },
    { Icon: BankIcon, label: 'Wema Bank · est. 1945' },
  ]

  return (
    <motion.section
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-80px' }}
      transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
      className="card shield-glow relative overflow-hidden p-8 sm:p-10"
    >
      {/* Soft brand wash + drifting glow */}
      <div className="pointer-events-none absolute inset-0 bg-gradient-to-br from-mint/[0.06] via-transparent to-mint/[0.04]" />
      <motion.div
        aria-hidden
        className="pointer-events-none absolute -right-10 -top-16 h-60 w-60 rounded-full bg-mint/10 blur-3xl"
        animate={{ scale: [1, 1.15, 1], opacity: [0.6, 0.9, 0.6] }}
        transition={{ duration: 7, repeat: Infinity, ease: 'easeInOut' }}
      />

      <div className="relative flex flex-col items-start gap-7 lg:flex-row lg:items-center lg:justify-between">
        <div className="flex items-center gap-5">
          <motion.div
            initial={{ scale: 0.8, rotate: -6, opacity: 0 }}
            whileInView={{ scale: 1, rotate: 0, opacity: 1 }}
            viewport={{ once: true }}
            transition={{ type: 'spring', stiffness: 260, damping: 18, delay: 0.05 }}
          >
            <AlatMark size={56} />
          </motion.div>
          <div>
            <span className="text-xs font-semibold uppercase tracking-[0.2em] text-mint">Powered by</span>
            <h2 className="mt-1 font-display text-3xl font-bold tracking-tight sm:text-4xl">
              ALAT <span className="text-muted">by</span> Wema
            </h2>
            <p className="mt-2 max-w-md text-sm leading-relaxed text-muted">
              Every naira moves on a licensed bank rail. Funding, payouts and settlement are powered by ALAT —
              Wema Bank’s regulated digital-banking platform.
            </p>
          </div>
        </div>

        <div className="flex flex-wrap gap-2.5">
          {chips.map(({ Icon, label }, i) => (
            <motion.span
              key={label}
              initial={{ opacity: 0, y: 8 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: 0.15 + i * 0.08 }}
              className="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3.5 py-2 text-sm font-medium text-text shadow-sm"
            >
              <Icon size={16} className="text-mint" />
              {label}
            </motion.span>
          ))}
        </div>
      </div>
    </motion.section>
  )
}
