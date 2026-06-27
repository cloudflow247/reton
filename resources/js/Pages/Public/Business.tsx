import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { PublicLayout } from '@/components/PublicLayout'
import { PoweredByAlat } from '@/components/PoweredByAlat'
import { ArrowRightIcon, BoltIcon, CheckIcon, ScanIcon, ShieldIcon, WalletIcon } from '@/components/icons'

const features = [
  [WalletIcon, 'Request money & payment links', 'Generate a Reton payment link or static account and get paid by anyone — on Reton or any bank.'],
  [BoltIcon, 'Instant bank settlement', 'Collections settle to your wallet on ALAT by Wema and reconcile to the kobo — no guesswork.'],
  [ShieldIcon, 'Buyer trust, built in', 'Customers can pay protected. The confidence to recall a payment makes them more likely to buy.'],
  [ScanIcon, 'Fraud-screened by default', 'Every inbound and outbound movement is risk-scored before it settles — chargebacks designed out.'],
] as const

const fade = { hidden: { opacity: 0, y: 16 }, show: { opacity: 1, y: 0 } }

export default function Business() {
  return (
    <div>
      <Head title="For business" />

      {/* Hero */}
      <section className="relative overflow-hidden">
        <div className="aurora" aria-hidden />
        <div className="relative mx-auto max-w-5xl px-5 pb-10 pt-20">
          <span className="inline-flex items-center gap-2 rounded-full border border-line bg-surface/70 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-mint shadow-sm backdrop-blur">
            For business
          </span>
          <h1 className="mt-5 max-w-3xl font-display text-4xl font-bold leading-[1.06] tracking-tight sm:text-5xl">
            Get paid with <span className="gradient-text">trust on both sides</span>.
          </h1>
          <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted">
            Collect payments, settle on a licensed bank rail, and give your customers the confidence of protected
            payments — all from one wallet built for how Africa actually trades.
          </p>

          <div className="mt-9 flex flex-wrap gap-3">
            <Link
              href="/register"
              className="btn inline-flex items-center gap-1.5 bg-mint px-6 py-3.5 text-white shadow-sm hover:bg-mint-strong"
            >
              Start collecting <ArrowRightIcon size={17} />
            </Link>
            <Link href="/contact" className="btn border border-line bg-surface px-6 py-3.5 hover:border-mint/40">
              Talk to us
            </Link>
          </div>
        </div>
      </section>

      <div className="mx-auto max-w-5xl px-5 pb-20">
        <motion.div
          variants={{ show: { transition: { staggerChildren: 0.08 } } }}
          initial="hidden"
          animate="show"
          className="grid gap-4 sm:grid-cols-2"
        >
          {features.map(([Icon, title, body]) => (
            <motion.div key={title} variants={fade} className="card elevate p-6">
              <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-mint/10 text-mint">
                <Icon size={22} />
              </span>
              <h3 className="mt-4 font-display text-lg font-semibold">{title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-muted">{body}</p>
            </motion.div>
          ))}
        </motion.div>

        <div className="mt-16">
          <span className="text-xs font-semibold uppercase tracking-[0.18em] text-mint">How you get paid</span>
          <h2 className="mt-3 font-display text-2xl font-bold tracking-tight sm:text-3xl">Three steps to settled funds</h2>
          <div className="mt-8 grid gap-8 sm:grid-cols-3">
            {[
              ['Share a link or account', 'Send a Reton payment link, or share your dedicated static account number.'],
              ['Customer pays', 'They pay from any bank or Reton wallet — protected if they choose.'],
              ['You settle instantly', 'Funds land in your wallet on ALAT by Wema, reconciled and audit-logged.'],
            ].map(([t, b], i) => (
              <motion.div
                key={t}
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.45, delay: i * 0.06 }}
              >
                <span className="flex h-10 w-10 items-center justify-center rounded-full bg-mint font-display text-sm font-bold text-white shadow-sm">
                  {i + 1}
                </span>
                <h3 className="mt-4 font-display text-lg font-semibold">{t}</h3>
                <p className="mt-2 text-sm leading-relaxed text-muted">{b}</p>
              </motion.div>
            ))}
          </div>
        </div>

        <div className="mt-12 flex flex-wrap gap-x-6 gap-y-2 text-sm">
          {['No setup fees', 'Same-day settlement', 'Dashboard & statements'].map((t) => (
            <span key={t} className="inline-flex items-center gap-1.5 font-medium text-text">
              <CheckIcon size={15} className="text-mint" /> {t}
            </span>
          ))}
        </div>

        <div className="mt-16">
          <PoweredByAlat />
        </div>
      </div>
    </div>
  )
}

Business.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>
