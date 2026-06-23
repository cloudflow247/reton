import { useEffect, useRef, useState } from 'react'

/** Smoothly tween a number toward its target (used for balances). */
export function useCountUp(target: number, duration = 650): number {
  const [value, setValue] = useState(0)
  const prev = useRef(0)

  useEffect(() => {
    const from = prev.current
    const to = target
    if (from === to) return

    let raf = 0
    const start = performance.now()
    const tick = (now: number) => {
      const t = Math.min(1, (now - start) / duration)
      const eased = 1 - Math.pow(1 - t, 3)
      setValue(Math.round(from + (to - from) * eased))
      if (t < 1) raf = requestAnimationFrame(tick)
      else prev.current = to
    }
    raf = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(raf)
  }, [target, duration])

  return value
}
