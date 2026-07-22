import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { PublicLayout } from '@/components/PublicLayout'
import {
  ActivityIcon,
  ArrowRightIcon,
  CheckIcon,
  LockIcon,
  ScanIcon,
  ShieldIcon,
  UndoIcon,
} from '@/components/icons'

const pillars = [
  [ShieldIcon, 'Double-entry ledger', 'Every kobo is tracked in an immutable, balanced ledger. No balance changes outside it - ever.'],
  [ScanIcon, 'Real-time fraud scoring', 'Each transaction is scored on velocity, device, amount and beneficiary; high-risk moves are blocked before they settle.'],
  [LockIcon, 'Transaction PIN', 'A separate PIN authorises every payment, with lockout after repeated failures.'],
  [UndoIcon, 'Idempotent money movement', 'Retries and duplicate webhooks can never double-spend or double-credit.'],
  [CheckIcon, 'Signed webhooks', 'Inbound payment events are HMAC-verified and de-duplicated before a wallet is touched.'],
  [ActivityIcon, 'Full audit trail', 'Callbacks, recoveries and disputes record an immutable, timestamped history of every action.'],
] as const

const fade = { hidden: { opacity: 0, y: 16 }, show: { opacity: 1, y: 0 } }

export default function Security() {
  return (
    <div>
      <Head title="Security" />

      {/* Hero */}
      <section className="relative overflow-hidden">
        <div className="aurora" aria-hidden />
        <div className="relative mx-auto max-w-5xl px-5 pb-10 pt-20">
          <span className="inline-flex items-center gap-2 rounded-full border border-line bg-surface/70 px-3.5 py-1.5 text-xs font-medium text-mint shadow-sm backdrop-blur">
            <LockIcon size={14} /> Security
          </span>
          <h1 className="mt-5 max-w-3xl font-display text-4xl font-bold leading-[1.06] tracking-tight sm:text-5xl">
            Built like money <span className="gradient-text">depends on it</span>. Because it does.
          </h1>
          <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted">
            Every payment is ledger-backed, PIN-authorised, fraud-scored, and fully audited. Reton is engineered so
            regulators - and you - can trust what the balance says.
          </p>
        </div>
      </section>

      <div className="mx-auto max-w-5xl px-5 pb-20">
        <motion.div
          variants={{ show: { transition: { staggerChildren: 0.07 } } }}
          initial="hidden"
          animate="show"
          className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
          {pillars.map(([Icon, title, body]) => (
            <motion.div key={title} variants={fade} className="card elevate p-6">
              <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-mint/10 text-mint">
                <Icon size={22} />
              </span>
              <h2 className="mt-4 font-display text-lg font-semibold">{title}</h2>
              <p className="mt-2 text-sm leading-relaxed text-muted">{body}</p>
            </motion.div>
          ))}
        </motion.div>

        {/* Assurance band */}
        <div className="brand-card sheen relative mt-12 overflow-hidden p-8 text-white sm:p-10">
          <div className="pointer-events-none absolute -right-16 -top-20 h-72 w-72 rounded-full bg-white/10 blur-2xl" />
          <h2 className="max-w-xl font-display text-2xl font-bold tracking-tight sm:text-3xl">
            Provably correct, not just fast.
          </h2>
          <p className="mt-3 max-w-xl text-sm leading-relaxed text-white/80">
            Balances reconcile to the kobo, every action is timestamped, and money moves on a licensed bank rail.
            Security isn’t a feature here - it’s the foundation.
          </p>
        </div>

        <div className="mt-12">
          <Link
            href="/register"
            className="btn inline-flex items-center gap-1.5 bg-mint px-6 py-3.5 text-white shadow-sm hover:bg-mint-strong"
          >
            Open a protected wallet <ArrowRightIcon size={17} />
          </Link>
        </div>
      </div>
    </div>
  )
}

Security.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>
