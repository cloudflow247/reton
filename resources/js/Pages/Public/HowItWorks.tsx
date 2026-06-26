import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { PublicLayout } from '@/components/PublicLayout'
import { PoweredByAlat } from '@/components/PoweredByAlat'
import { ArrowRightIcon, ScanIcon, ShieldIcon, UndoIcon } from '@/components/icons'

const flows = [
  {
    Icon: ShieldIcon,
    name: 'Callback protection',
    steps: [
      'Pick Protected when you send. The money moves into escrow, not to the recipient.',
      'They see a pending payment. You can release it — or raise a callback to recall it.',
      'If you recall, they accept (you’re refunded) or reject (a Reton agent reviews and decides).',
    ],
  },
  {
    Icon: UndoIcon,
    name: 'Wrong-transfer recovery',
    steps: [
      'Sent a normal transfer to the wrong account? Report it from the Protection tab.',
      'If eligible, we freeze that amount in the recipient’s wallet and notify them.',
      'They return it, or dispute — and if they go quiet, it escalates for review.',
    ],
  },
  {
    Icon: ScanIcon,
    name: 'Adding & withdrawing money',
    steps: [
      'Add money by transferring to a one-time virtual account; your wallet credits on confirmation.',
      'Withdraw to any bank — funds are reserved, then settle once the bank confirms.',
      'Missed a webhook? Automatic reconciliation settles or reverses it safely.',
    ],
  },
]

export default function HowItWorks() {
  return (
    <div className="mx-auto max-w-5xl px-5 py-20">
      <Head title="How it works" />
      <h1 className="font-display text-4xl font-bold tracking-tight sm:text-5xl">How Reton works</h1>
      <p className="mt-5 max-w-2xl text-lg leading-relaxed text-muted">
        Three flows do the heavy lifting. Each one is fully logged, reversible where it should be, and final
        where it must be.
      </p>

      <div className="mt-14 space-y-10">
        {flows.map((flow, fi) => (
          <motion.section
            key={flow.name}
            initial={{ opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: '-60px' }}
            transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1], delay: fi * 0.05 }}
            className="card p-7"
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

      <div className="mt-14">
        <Link
          href="/register"
          className="btn inline-flex items-center gap-1.5 bg-mint px-6 py-3.5 text-white shadow-sm hover:bg-mint-strong"
        >
          Try it yourself <ArrowRightIcon size={17} />
        </Link>
      </div>
    </div>
  )
}

HowItWorks.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>
