import type { Biller } from '@/lib/billers'

/** A brand-coloured biller tile (MTN, Glo, DStv, …). Pass `round` for the
 *  circular network-selector style used on the airtime/data flow. */
export function BillerLogo({
  biller,
  size = 44,
  round,
}: {
  biller: Biller
  size?: number
  round?: boolean
}) {
  return (
    <span
      className={`flex shrink-0 items-center justify-center font-display font-bold leading-none shadow-sm ${
        round ? 'rounded-full' : 'rounded-xl'
      }`}
      style={{
        width: size,
        height: size,
        background: biller.bg,
        color: biller.fg,
        fontSize: Math.max(10, size * (biller.short.length > 3 ? 0.26 : 0.34)),
        letterSpacing: '-0.02em',
      }}
      aria-hidden
    >
      {biller.short}
    </span>
  )
}
