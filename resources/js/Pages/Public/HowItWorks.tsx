import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { PublicLayout } from '@/components/PublicLayout'
import { PoweredByAlat } from '@/components/PoweredByAlat'
import { ArrowRightIcon, ScanIcon, ShareIcon, ShieldIcon, UndoIcon } from '@/components/icons'

const flows = [
  {
    Icon: ShieldIcon,
    name: 'Callback protection',
    steps: [
      'Pick Protected when you send. The money moves into escrow, not to the recipient.',
      'They see a pending payment. You can release it - or raise a callback to recall it.',
      'If you recall, they accept (you’re refunded) or reject (a Reton agent reviews and decides).',
    ],
  },
  {
    Icon: UndoIcon,
    name: 'Wrong-transfer recovery',
    steps: [
      'Sent a normal transfer to the wrong account? Report it from the Protection tab.',
      'If eligible, we freeze that amount in the recipient’s wallet and notify them.',
      'They return it, or dispute - and if they go quiet, it escalates for review.',
    ],
  },
  {
    Icon: ShareIcon,
    name: 'Sell with protection (listings)',
    steps: [
      'Create a listing and share the link - WhatsApp, Instagram, or in person. Buyers don’t browse a public mall; they need your link.',
      'They pay with Callback Protection. Funds stay held until you deliver and they confirm.',
      'If something’s off, they can dispute. Every step sits on the same protection timeline as a normal transfer.',
    ],
  },
  {
    Icon: ScanIcon,
    name: 'Adding money & cash out',
    steps: [
      'Fund your wallet with a dedicated deposit account - credits land when the bank confirms.',
      'Send protected or instant transfers to any Reton user with a full timeline on every move.',
      'Bank withdrawals are coming soon. Until then, your balance stays safe inside Reton.',
    ],
  },
]

export default function HowItWorks() {
  return (
    <div>
      <Head title="How it works" />

      {/* Hero */}
      <section className="relative overflow-hidden">
        <div className="aurora" aria-hidden />
        <div className="relative mx-auto max-w-5xl px-5 pb-10 pt-20">
          <span className="inline-flex items-center gap-2 rounded-full border border-line bg-surface/70 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-mint shadow-sm backdrop-blur">
            How it works
          </span>
          <h1 className="mt-5 font-display text-4xl font-bold tracking-tight sm:text-5xl">
            Clear flows. <span className="gradient-text">Safer money.</span>
          </h1>
          <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted">
            Reton protects transfers before they finalize, helps recover mistakes, and watches every payment for fraud -
            so speed never costs you your money.
          </p>
        </div>
      </section>

      <div className="mx-auto max-w-5xl px-5 pb-20">
        <div className="space-y-6">
          {flows.map((flow, fi) => (
            <motion.section
              key={flow.name}
              initial={{ opacity: 0, y: 24 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1], delay: fi * 0.05 }}
              className="card elevate p-7 sm:p-8"
            >
              <div className="flex items-center gap-3">
                <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-mint/10 text-mint">
                  <flow.Icon size={22} />
                </span>
                <h2 className="font-display text-xl font-bold tracking-tight">{flow.name}</h2>
              </div>
              <div className="mt-6 grid gap-6 sm:grid-cols-3">
                {flow.steps.map((step, i) => (
                  <div key={i} className="border-t-2 border-mint/20 pt-4">
                    <span className="flex h-7 w-7 items-center justify-center rounded-full bg-mint/10 font-num text-xs font-bold text-mint">
                      {i + 1}
                    </span>
                    <p className="mt-3 text-sm leading-relaxed text-muted">{step}</p>
                  </div>
                ))}
              </div>
            </motion.section>
          ))}
        </div>

        <div className="mt-12">
          <PoweredByAlat />
        </div>

        <div className="mt-12">
          <Link
            href="/register"
            className="btn inline-flex items-center gap-1.5 bg-mint px-6 py-3.5 text-white shadow-sm hover:bg-mint-strong"
          >
            Try it yourself <ArrowRightIcon size={17} />
          </Link>
        </div>
      </div>
    </div>
  )
}

HowItWorks.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>
