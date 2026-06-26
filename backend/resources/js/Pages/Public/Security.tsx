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
  [ShieldIcon, 'Double-entry ledger', 'Every kobo is tracked in an immutable, balanced ledger. No balance changes outside it — ever.'],
  [ScanIcon, 'Real-time fraud scoring', 'Each transaction is scored on velocity, device, amount and beneficiary; high-risk moves are blocked before they settle.'],
  [LockIcon, 'Transaction PIN', 'A separate PIN authorises every payment, with lockout after repeated failures.'],
  [UndoIcon, 'Idempotent money movement', 'Retries and duplicate webhooks can never double-spend or double-credit.'],
  [CheckIcon, 'Signed webhooks', 'Inbound payment events are HMAC-verified and de-duplicated before a wallet is touched.'],
  [ActivityIcon, 'Full audit trail', 'Callbacks, recoveries and disputes record an immutable, timestamped history of every action.'],
] as const

const fade = { hidden: { opacity: 0, y: 16 }, show: { opacity: 1, y: 0 } }

export default function Security() {
  return (
    <div className="mx-auto max-w-5xl px-5 py-20">
      <Head title="Security" />
      <span className="inline-flex items-center gap-2 rounded-full border border-line bg-mint/10 px-3 py-1.5 text-xs font-medium text-mint">
        <LockIcon size={14} /> Security
      </span>
      <h1 className="mt-4 font-display text-4xl font-bold tracking-tight sm:text-5xl">
        Built like money depends on it. Because it does.
      </h1>
      <p className="mt-5 max-w-2xl text-lg leading-relaxed text-muted">
        Reton is engineered for regulatory scrutiny from day one — financial consistency first, throughput second.
      </p>

      <motion.div
        variants={{ show: { transition: { staggerChildren: 0.07 } } }}
        initial="hidden"
        whileInView="show"
        viewport={{ once: true }}
        className="mt-12 grid gap-4 sm:grid-cols-2"
      >
        {pillars.map(([Icon, title, body]) => (
          <motion.div key={title} variants={fade} className="card p-6">
            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-mint/10 text-mint">
              <Icon size={22} />
            </span>
            <h2 className="mt-4 font-display text-lg font-semibold">{title}</h2>
            <p className="mt-2 text-sm leading-relaxed text-muted">{body}</p>
          </motion.div>
        ))}
      </motion.div>

      <div className="mt-12">
        <Link
          href="/register"
          className="btn inline-flex items-center gap-1.5 bg-mint px-6 py-3.5 text-white shadow-sm hover:bg-mint-strong"
        >
          Open a protected wallet <ArrowRightIcon size={17} />
        </Link>
      </div>
    </div>
  )
}

Security.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>
