import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { PublicLayout } from '@/components/PublicLayout'
import { PoweredByAlat } from '@/components/PoweredByAlat'
import { ArrowRightIcon, ScanIcon, ShieldIcon, UndoIcon } from '@/components/icons'

const values = [
  [ShieldIcon, 'Safety is the product', 'Most wallets move money fast. We move it safely — protection and recovery are the default, not an afterthought.'],
  [UndoIcon, 'Mistakes shouldn’t be final', 'A wrong number or a scam is a moment, not a life sentence. Reton is built so money can come back.'],
  [ScanIcon, 'Earn trust with proof', 'Every naira is double-entry ledgered, every action audited. We’d rather be provably correct than merely fast.'],
] as const

const fade = { hidden: { opacity: 0, y: 16 }, show: { opacity: 1, y: 0 } }

export default function About() {
  return (
    <div>
      <Head title="About" />

      {/* Hero */}
      <section className="relative overflow-hidden">
        <div className="aurora" aria-hidden />
        <div className="relative mx-auto max-w-5xl px-5 pb-10 pt-20">
          <span className="inline-flex items-center gap-2 rounded-full border border-line bg-surface/70 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-mint shadow-sm backdrop-blur">
            About Reton
          </span>
          <h1 className="mt-5 max-w-3xl font-display text-4xl font-bold leading-[1.06] tracking-tight sm:text-5xl">
            We’re building the wallet Africa can <span className="gradient-text">actually trust</span>.
          </h1>
          <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted">
            Reton is a trust-first digital wallet. While others compete on speed, we compete on something harder and
            more valuable — making sure your money is safe, and recoverable when something goes wrong.
          </p>
        </div>
      </section>

      <div className="mx-auto max-w-5xl px-5 pb-20">
        <motion.div
          variants={{ show: { transition: { staggerChildren: 0.08 } } }}
          initial="hidden"
          animate="show"
          className="grid gap-4 sm:grid-cols-3"
        >
          {values.map(([Icon, title, body]) => (
            <motion.div key={title} variants={fade} className="card elevate p-6">
              <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-mint/10 text-mint">
                <Icon size={22} />
              </span>
              <h3 className="mt-4 font-display text-lg font-semibold">{title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-muted">{body}</p>
            </motion.div>
          ))}
        </motion.div>

        <div className="mt-4 grid gap-4 sm:grid-cols-3">
          {[
            ['NG', 'Built for Nigeria first, Africa next'],
            ['100%', 'Ledger-backed, immutable accounting'],
            ['24/7', 'Fraud screening on every transaction'],
          ].map(([v, l]) => (
            <div key={l} className="rounded-2xl border border-line bg-surface-2/50 p-6">
              <div className="font-num text-3xl font-bold tracking-tight text-mint">{v}</div>
              <div className="mt-1 text-sm text-muted">{l}</div>
            </div>
          ))}
        </div>

        <div className="mt-16">
          <PoweredByAlat />
        </div>

        <div className="mt-12">
          <Link
            href="/register"
            className="btn inline-flex items-center gap-1.5 bg-mint px-6 py-3.5 text-white shadow-sm hover:bg-mint-strong"
          >
            Open a free wallet <ArrowRightIcon size={17} />
          </Link>
        </div>
      </div>
    </div>
  )
}

About.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>
