import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { PublicLayout } from '@/components/PublicLayout'
import { AlatMark, PoweredByAlat } from '@/components/PoweredByAlat'
import {
  ArrowRightIcon,
  BankIcon,
  BoltIcon,
  CheckIcon,
  LockIcon,
  ScanIcon,
  ShieldIcon,
  SparkleIcon,
  UndoIcon,
} from '@/components/icons'

const features = [
  {
    Icon: ShieldIcon,
    title: 'Callback protection',
    body: 'Hold a payment in escrow until you confirm. Change your mind, and the money never leaves your wallet.',
  },
  {
    Icon: UndoIcon,
    title: 'Wrong-transfer recovery',
    body: 'Sent to the wrong number? Report it and we freeze the funds, then ask the recipient to return them.',
  },
  {
    Icon: ScanIcon,
    title: 'Real-time fraud engine',
    body: 'Velocity, new devices and odd patterns are scored and blocked before a single kobo settles.',
  },
]

const marquee = [
  'Double-entry ledger',
  'Real-time fraud scoring',
  'Licensed bank rail',
  'PIN-authorised payments',
  'Idempotent & reversible',
  'Signed webhooks',
  'Full audit trail',
]

const fade = {
  hidden: { opacity: 0, y: 16 },
  show: { opacity: 1, y: 0 },
}

export default function Home() {
  return (
    <div>
      <Head title="Payments with a safety net" />

      {/* Hero */}
      <section className="relative overflow-hidden">
        <div className="aurora" aria-hidden />
        <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-5 py-16 lg:grid-cols-[1.1fr_1fr] lg:py-24">
          <motion.div initial="hidden" animate="show" variants={fade} transition={{ duration: 0.5, ease: 'easeOut' }}>
            <span className="inline-flex items-center gap-2 rounded-full border border-line bg-surface/70 px-3.5 py-1.5 text-xs font-medium text-mint shadow-sm backdrop-blur">
              <SparkleIcon size={14} /> Trust-first banking for Africa
            </span>
            <h1 className="mt-6 font-display text-5xl font-bold leading-[1.04] tracking-tight sm:text-6xl">
              The first wallet with an <span className="gradient-text">undo button</span> for money.
            </h1>
            <p className="mt-6 max-w-xl text-lg leading-relaxed text-muted">
              Send with callback protection, recover a wrong transfer, and let real-time fraud checks watch every
              move. When something goes wrong, Reton brings your money back.
            </p>
            <div className="mt-9 flex flex-wrap gap-3">
              <Link
                href="/register"
                className="btn inline-flex items-center gap-1.5 bg-mint px-6 py-3.5 text-white shadow-sm hover:bg-mint-strong"
              >
                Open a free wallet <ArrowRightIcon size={17} />
              </Link>
              <Link
                href="/how-it-works"
                className="btn border border-line bg-surface px-6 py-3.5 hover:border-mint/40"
              >
                See how it works
              </Link>
            </div>
            <div className="mt-10 grid max-w-md grid-cols-3 gap-3">
              <Stat value="100%" label="ledger-backed" />
              <Stat value="<50ms" label="fraud checks" />
              <Stat value="72h" label="callback window" />
            </div>

            {/* Partner trust line — money settles on a real bank rail. */}
            <div className="mt-8 inline-flex items-center gap-2.5 rounded-full border border-line bg-surface px-3.5 py-2 shadow-sm">
              <AlatMark size={26} />
              <span className="text-sm text-muted">
                Settlement powered by <span className="font-semibold text-text">ALAT by Wema</span>
              </span>
            </div>
          </motion.div>

          <HeroPreview />
        </div>
      </section>

      {/* Trust marquee */}
      <section aria-hidden className="relative overflow-hidden border-y border-line bg-surface/60 py-4">
        <div className="pointer-events-none absolute inset-y-0 left-0 z-10 w-20 bg-gradient-to-r from-bg to-transparent" />
        <div className="pointer-events-none absolute inset-y-0 right-0 z-10 w-20 bg-gradient-to-l from-bg to-transparent" />
        <div className="flex w-max marquee gap-3">
          {[...marquee, ...marquee].map((label, i) => (
            <span
              key={i}
              className="inline-flex shrink-0 items-center gap-2 rounded-full border border-line bg-surface px-4 py-1.5 text-sm font-medium text-text"
            >
              <CheckIcon size={14} className="text-mint" /> {label}
            </span>
          ))}
        </div>
      </section>

      <div className="mx-auto max-w-6xl px-5">
        {/* Features */}
        <section className="py-16 sm:py-20">
          <SectionHead
            eyebrow="Why Reton"
            title="Built to protect every transfer."
            lead="Three layers of safety work on every payment — so a mistake or a scam doesn’t have to be final."
          />
          <motion.div
            variants={{ show: { transition: { staggerChildren: 0.1 } } }}
            initial="hidden"
            animate="show"
            className="mt-12 grid gap-4 sm:grid-cols-3"
          >
            {features.map(({ Icon, title, body }) => (
              <motion.div key={title} variants={fade} className="card elevate p-6">
                <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-mint/10 text-mint">
                  <Icon size={22} />
                </span>
                <h3 className="mt-4 font-display text-lg font-semibold">{title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-muted">{body}</p>
              </motion.div>
            ))}
          </motion.div>
        </section>

        {/* How it works */}
        <section className="py-16 sm:py-20">
          <SectionHead
            eyebrow="How it works"
            title="How a protected transfer works"
            lead="Pay protected, stay in control, and settle only when you’re sure."
          />
          <div className="mt-12 grid gap-8 sm:grid-cols-3">
            {[
              ['Send protected', 'Choose Protected when you pay. The money moves into escrow — not to the recipient yet.'],
              ['Hold or recall', 'They see it as pending. You can release it, or raise a callback to pull it back.'],
              ['Settled or returned', 'Confirm and it settles instantly. Dispute, and our engine decides — every step logged.'],
            ].map(([title, body], i) => (
              <motion.div
                key={title}
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.45, delay: i * 0.06 }}
                className="relative"
              >
                <span className="flex h-10 w-10 items-center justify-center rounded-full bg-mint font-display text-sm font-bold text-white shadow-sm">
                  {i + 1}
                </span>
                <h3 className="mt-4 font-display text-lg font-semibold">{title}</h3>
                <p className="mt-2 text-sm leading-relaxed text-muted">{body}</p>
              </motion.div>
            ))}
          </div>
        </section>

        {/* Security band */}
        <section className="py-6">
          <div className="brand-card sheen relative overflow-hidden p-8 text-white sm:p-12">
            <div className="pointer-events-none absolute -right-16 -top-20 h-72 w-72 rounded-full bg-white/10 blur-2xl" />
            <span className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]">
              <LockIcon size={13} /> Security
            </span>
            <h2 className="mt-4 max-w-xl font-display text-3xl font-bold tracking-tight">
              Engineered for regulators, not just users.
            </h2>
            <p className="mt-3 max-w-xl text-sm leading-relaxed text-white/80">
              Financial consistency comes first. Every naira is double-entry ledgered, every webhook signed, every
              action audited.
            </p>
            <div className="mt-8 grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
              {[
                { Icon: ShieldIcon, label: 'Double-entry ledger' },
                { Icon: ScanIcon, label: 'Real-time fraud scoring' },
                { Icon: LockIcon, label: 'PIN-authorised payments' },
                { Icon: UndoIcon, label: 'Idempotent & reversible' },
              ].map(({ Icon, label }) => (
                <div key={label} className="flex items-center gap-2.5 text-sm font-medium">
                  <Icon size={18} />
                  {label}
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Powered by ALAT by Wema — the licensed bank rail */}
        <section className="py-12">
          <PoweredByAlat />
        </section>

        {/* CTA */}
        <section className="py-16 sm:py-20">
          <div className="card shield-glow relative flex flex-col items-center gap-5 overflow-hidden p-12 text-center sm:p-16">
            <div className="pointer-events-none absolute -left-20 -top-24 h-64 w-64 rounded-full bg-mint/10 blur-3xl" />
            <span className="relative flex h-14 w-14 items-center justify-center rounded-2xl bg-mint/10 text-mint">
              <ShieldIcon size={26} />
            </span>
            <h2 className="relative font-display text-3xl font-bold tracking-tight sm:text-4xl">
              Money you can take back.
            </h2>
            <p className="relative max-w-md text-muted">
              Join the platform built so a mistake or a scam doesn’t have to be final.
            </p>
            <div className="relative mt-1 flex flex-wrap items-center justify-center gap-3">
              <Link
                href="/register"
                className="btn inline-flex items-center gap-1.5 bg-mint px-7 py-3.5 text-white shadow-sm hover:bg-mint-strong"
              >
                Get started — it’s free <ArrowRightIcon size={17} />
              </Link>
              <Link href="/login" className="btn border border-line bg-surface px-7 py-3.5 hover:border-mint/40">
                Sign in
              </Link>
            </div>
            <div className="relative mt-2 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-muted">
              <span className="inline-flex items-center gap-1.5">
                <CheckIcon size={14} className="text-mint" /> Free to open
              </span>
              <span className="inline-flex items-center gap-1.5">
                <BoltIcon size={14} className="text-mint" /> Instant settlement
              </span>
              <span className="inline-flex items-center gap-1.5">
                <BankIcon size={14} className="text-mint" /> Licensed bank rail
              </span>
            </div>
          </div>
        </section>
      </div>
    </div>
  )
}

Home.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>

function SectionHead({ eyebrow, title, lead }: { eyebrow: string; title: string; lead?: string }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 14 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.45 }}
      className="max-w-2xl"
    >
      <span className="text-xs font-semibold uppercase tracking-[0.18em] text-mint">{eyebrow}</span>
      <h2 className="mt-3 font-display text-3xl font-bold tracking-tight sm:text-4xl">{title}</h2>
      {lead && <p className="mt-4 text-base leading-relaxed text-muted">{lead}</p>}
    </motion.div>
  )
}

function Stat({ value, label }: { value: string; label: string }) {
  return (
    <div className="rounded-2xl border border-line bg-surface/70 px-4 py-3">
      <div className="font-num text-xl font-bold tracking-tight text-text">{value}</div>
      <div className="mt-0.5 text-xs text-muted">{label}</div>
    </div>
  )
}

/** Layered app preview: the brand balance card with a floating protected-transfer note. */
function HeroPreview() {
  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.96 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.55, delay: 0.15, ease: [0.22, 1, 0.36, 1] }}
      className="relative mx-auto w-full max-w-sm"
    >
      <div className="brand-card sheen relative overflow-hidden p-6 text-white">
        <div className="pointer-events-none absolute -right-12 -top-16 h-56 w-56 rounded-full bg-white/10 blur-2xl" />
        <div className="flex items-center justify-between">
          <span className="text-xs font-medium uppercase tracking-wider text-white/70">Protected balance</span>
          <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-xs font-medium">
            <ShieldIcon size={13} /> Protected
          </span>
        </div>
        <div className="mt-3 font-num text-4xl font-bold">₦ 248,500.00</div>
        <div className="mt-5 space-y-2.5">
          <Line who="To Ada — protected" amount="−₦ 40,000" held />
          <Line who="From AlatPay" amount="+₦ 100,000" />
        </div>
      </div>

      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        className="absolute -bottom-6 -left-6 hidden sm:block"
      >
        <motion.div
          animate={{ y: [0, -7, 0] }}
          transition={{ duration: 4.5, repeat: Infinity, ease: 'easeInOut' }}
          className="card flex w-56 items-center gap-3 p-4"
        >
          <span className="flex h-9 w-9 items-center justify-center rounded-full bg-mint/10 text-mint">
            <UndoIcon size={18} />
          </span>
          <div>
            <div className="text-sm font-semibold">Callback raised</div>
            <div className="text-xs text-muted">₦40,000 on its way back</div>
          </div>
        </motion.div>
      </motion.div>
    </motion.div>
  )
}

function Line({ who, amount, held }: { who: string; amount: string; held?: boolean }) {
  return (
    <div className="flex items-center justify-between rounded-xl border border-white/15 bg-white/[0.08] px-4 py-3">
      <span className="text-sm">{who}</span>
      <span className="flex items-center gap-2">
        {held && <span className="rounded-full bg-white/15 px-2 py-0.5 text-[11px]">held</span>}
        <span className="font-num text-sm">{amount}</span>
      </span>
    </div>
  )
}
