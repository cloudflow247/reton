import type { ReactNode } from 'react'
import { motion } from 'framer-motion'
import { AppleLogoIcon, GooglePlayLogoIcon } from './icons'

/** Centered mobile-app promo — App Store & Google Play badges (Coming soon). */
export function GetTheAppSection() {
  return (
    <section className="relative overflow-hidden px-5 py-14 sm:py-16" aria-labelledby="get-the-app-heading">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-1/2 mx-auto h-40 max-w-xl -translate-y-1/2 rounded-full bg-mint/10 blur-3xl"
      />
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true, margin: '-60px' }}
        transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
        className="relative mx-auto flex max-w-xl flex-col items-center text-center"
      >
        <span className="inline-flex items-center gap-2 rounded-full border border-amber/25 bg-amber/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-amber">
          Coming soon
        </span>
        <h2 id="get-the-app-heading" className="mt-4 font-display text-2xl font-bold tracking-tight sm:text-3xl">
          Get the app
        </h2>
        <p className="mt-3 max-w-md text-sm leading-relaxed text-muted sm:text-base">
          Reton on iOS and Android — protect payments, recover mistakes, and bank with trust from your phone.
        </p>
        <div className="mt-7 flex flex-wrap items-center justify-center gap-3">
          <StoreBadge
            href="#"
            label="Download on the App Store — Coming soon"
            eyebrow="Download on the"
            title="App Store"
            icon={<AppleLogoIcon size={24} />}
          />
          <StoreBadge
            href="#"
            label="Get it on Google Play — Coming soon"
            eyebrow="Get it on"
            title="Google Play"
            icon={<GooglePlayLogoIcon size={24} />}
          />
        </div>
      </motion.div>
    </section>
  )
}

function StoreBadge({
  href,
  label,
  eyebrow,
  title,
  icon,
}: {
  href: string
  label: string
  eyebrow: string
  title: string
  icon: ReactNode
}) {
  return (
    <a
      href={href}
      aria-label={label}
      title="Coming soon"
      className="group inline-flex min-w-[168px] items-center gap-3 rounded-2xl border border-white/10 bg-[#0b0d10] px-4 py-3 text-white shadow-[0_12px_32px_-16px_rgba(11,13,16,0.55)] transition hover:-translate-y-0.5 hover:border-mint/40 hover:bg-[#12151a] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mint"
      onClick={(e) => {
        e.preventDefault()
      }}
    >
      <span className="shrink-0 text-white transition group-hover:scale-105">{icon}</span>
      <span className="text-left leading-none">
        <span className="block text-[9px] font-medium uppercase tracking-[0.14em] text-white/65">{eyebrow}</span>
        <span className="mt-1 block font-display text-[16px] font-semibold tracking-tight">{title}</span>
      </span>
    </a>
  )
}
