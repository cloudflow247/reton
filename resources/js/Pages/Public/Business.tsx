import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { PublicLayout } from '@/components/PublicLayout'
import { PoweredByAlat } from '@/components/PoweredByAlat'
import {
  ArrowRightIcon,
  BoltIcon,
  ChatIcon,
  CheckIcon,
  ClockIcon,
  MailIcon,
  ScanIcon,
  ShieldIcon,
  WalletIcon,
} from '@/components/icons'

const comingFeatures = [
  {
    Icon: WalletIcon,
    title: 'Payment links & invoices',
    body: 'Share a link or dedicated account and get paid by anyone - Reton wallet or any Nigerian bank.',
  },
  {
    Icon: ShieldIcon,
    title: 'Buyer protection that sells',
    body: 'Customers can pay with Callback Protection. Trust increases conversion; disputes stay structured.',
  },
  {
    Icon: BoltIcon,
    title: 'Same-day settlement',
    body: 'Collections reconcile to the kobo and land in your Reton wallet with a full audit trail.',
  },
  {
    Icon: ScanIcon,
    title: 'Fraud-screened by default',
    body: 'Inbound and outbound movements are risk-scored before they settle - chargebacks designed out.',
  },
] as const

const timeline = [
  ['Personal wallets', 'Live today - send, protect, recover, and fund with confidence.'],
  ['Business tools', 'Coming soon - merchant collection, payment links, and trust badges.'],
  ['Team & API access', 'Next - roles, statements, and programmatic payouts for growing brands.'],
] as const

const fade = {
  hidden: { opacity: 0, y: 18 },
  show: { opacity: 1, y: 0 },
}

export default function Business() {
  return (
    <div>
      <Head title="Reton Business - Coming soon" />

      <section className="relative overflow-hidden">
        <div className="aurora" aria-hidden />
        <div
          className="pointer-events-none absolute inset-0 opacity-[0.35]"
          style={{
            backgroundImage:
              'radial-gradient(circle at 1px 1px, color-mix(in oklab, var(--color-mint) 18%, transparent) 1px, transparent 0)',
            backgroundSize: '28px 28px',
          }}
          aria-hidden
        />

        <div className="relative mx-auto max-w-6xl px-5 pb-14 pt-16 sm:pb-20 sm:pt-24">
          <motion.div initial="hidden" animate="show" variants={fade} transition={{ duration: 0.45, ease: 'easeOut' }}>
            <div className="flex flex-wrap items-center gap-2">
              <span className="inline-flex items-center gap-2 rounded-full border border-line bg-surface/80 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-mint shadow-sm backdrop-blur">
                Reton Business
              </span>
              <span className="inline-flex items-center gap-1.5 rounded-full border border-amber/30 bg-amber/10 px-3 py-1.5 text-xs font-semibold text-amber">
                <ClockIcon size={13} /> Coming soon
              </span>
            </div>

            <h1 className="mt-6 max-w-3xl font-display text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">
              Merchant tools are almost ready. <span className="gradient-text">Personal wallets are live.</span>
            </h1>
            <p className="mt-6 max-w-2xl text-lg leading-relaxed text-muted">
              Reton Business will help you collect payments, settle cleanly, and give buyers Callback Protection -
              the same trust stack people already use for personal money. We’re finishing the merchant desk before
              we open the doors.
            </p>

            <div className="mt-9 flex flex-wrap gap-3">
              <a
                href="mailto:support@retonpay.com?subject=Reton%20Business%20waitlist"
                className="btn inline-flex items-center gap-1.5 bg-mint px-6 py-3.5 text-white shadow-sm hover:bg-mint-strong"
              >
                <MailIcon size={17} /> Join the waitlist
              </a>
              <Link href="/register" className="btn border border-line bg-surface px-6 py-3.5 hover:border-mint/40">
                Open a personal wallet
              </Link>
              <Link href="/contact" className="btn inline-flex items-center gap-1.5 border border-transparent px-4 py-3.5 text-mint hover:bg-mint/5">
                <ChatIcon size={16} /> Talk to us
              </Link>
            </div>

            <p className="mt-5 max-w-xl text-sm text-muted">
              No spam - waitlist emails go to our team. We’ll invite early merchants when Reton Business launches.
            </p>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.12, ease: [0.22, 1, 0.36, 1] }}
            className="mt-12 overflow-hidden rounded-3xl border border-line bg-surface shadow-[0_28px_80px_-48px_rgba(9,79,57,0.45)]"
          >
            <div className="grid lg:grid-cols-[1.15fr_0.85fr]">
              <div className="border-b border-line p-6 sm:p-8 lg:border-b-0 lg:border-r">
                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-muted">What’s coming</p>
                <ul className="mt-5 space-y-4">
                  {comingFeatures.map(({ Icon, title, body }, i) => (
                    <motion.li
                      key={title}
                      initial={{ opacity: 0, x: -8 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ delay: 0.18 + i * 0.05 }}
                      className="flex gap-3"
                    >
                      <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-mint/10 text-mint">
                        <Icon size={18} />
                      </span>
                      <span>
                        <span className="block font-display text-sm font-semibold text-text">{title}</span>
                        <span className="mt-0.5 block text-sm leading-relaxed text-muted">{body}</span>
                      </span>
                    </motion.li>
                  ))}
                </ul>
              </div>

              <div className="relative bg-[linear-gradient(165deg,rgba(9,79,57,0.12),transparent_55%)] p-6 sm:p-8">
                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-muted">Roadmap</p>
                <ol className="mt-5 space-y-5">
                  {timeline.map(([title, body], i) => (
                    <li key={title} className="relative flex gap-3 pl-1">
                      <span
                        className={`mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                          i === 0 ? 'bg-mint text-white' : i === 1 ? 'bg-amber/20 text-amber ring-1 ring-amber/30' : 'bg-surface-2 text-muted ring-1 ring-line'
                        }`}
                      >
                        {i === 0 ? <CheckIcon size={14} /> : i + 1}
                      </span>
                      <span>
                        <span className="block font-display text-sm font-semibold text-text">{title}</span>
                        <span className="mt-0.5 block text-sm leading-relaxed text-muted">{body}</span>
                      </span>
                    </li>
                  ))}
                </ol>

                <div className="mt-8 rounded-2xl border border-mint/20 bg-mint/5 p-4">
                  <p className="text-sm font-semibold text-text">Need to collect today?</p>
                  <p className="mt-1 text-sm leading-relaxed text-muted">
                    Use a personal Reton wallet with protected transfers while Business tools roll out - then upgrade
                    when we invite you.
                  </p>
                  <Link href="/how-it-works" className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-mint hover:underline">
                    See how protection works <ArrowRightIcon size={14} />
                  </Link>
                </div>
              </div>
            </div>
          </motion.div>
        </div>
      </section>

      <section className="border-y border-line bg-surface/50">
        <div className="mx-auto max-w-6xl px-5 py-14 sm:py-16">
          <div className="max-w-2xl">
            <p className="text-xs font-semibold uppercase tracking-[0.16em] text-mint">Why businesses will choose Reton</p>
            <h2 className="mt-3 font-display text-3xl font-bold tracking-tight sm:text-4xl">
              Trust on both sides of every payment.
            </h2>
            <p className="mt-4 text-base leading-relaxed text-muted">
              Buyers get Callback Protection. Sellers get cleaner settlement and fewer “I didn’t get it” arguments.
              That’s Africa’s trust-first payments stack - merchant edition.
            </p>
          </div>

          <div className="mt-10 grid gap-3 sm:grid-cols-3">
            {[
              ['Protected checkout', 'Buyers can hold funds until delivery is confirmed.'],
              ['Clear timelines', 'Every dispute leaves a visible audit trail.'],
              ['Ledger certainty', 'Balances reconcile to the kobo - always.'],
            ].map(([title, body]) => (
              <div key={title} className="rounded-2xl border border-line bg-bg/80 p-5">
                <p className="font-display text-base font-semibold text-text">{title}</p>
                <p className="mt-2 text-sm leading-relaxed text-muted">{body}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <div className="mx-auto max-w-6xl px-5 py-14">
        <div className="brand-card sheen relative overflow-hidden p-8 text-white sm:p-10">
          <div className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-white/10 blur-2xl" />
          <AnimatePresence>
            <motion.div initial={{ opacity: 0, y: 10 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true }}>
              <p className="text-xs font-semibold uppercase tracking-[0.16em] text-white/70">Early access</p>
              <h2 className="mt-3 max-w-xl font-display text-2xl font-bold tracking-tight sm:text-3xl">
                Be first in line when Reton Business launches.
              </h2>
              <p className="mt-3 max-w-xl text-sm leading-relaxed text-white/80">
                Tell us about your store or agency. We’ll reach out when merchant collection, payment links, and
                business dashboards go live.
              </p>
              <div className="mt-7 flex flex-wrap gap-3">
                <a
                  href="mailto:support@retonpay.com?subject=Reton%20Business%20waitlist&body=Business%20name%3A%0AWhat%20you%20sell%3A%0ACity%3A%0A"
                  className="btn inline-flex items-center gap-1.5 bg-white px-5 py-3 text-sm font-semibold text-mint hover:bg-white/90"
                >
                  Request early access <ArrowRightIcon size={15} />
                </a>
                <Link href="/" className="btn border border-white/25 bg-white/5 px-5 py-3 text-sm text-white hover:bg-white/10">
                  Back to home
                </Link>
              </div>
            </motion.div>
          </AnimatePresence>
        </div>

        <div className="mt-12">
          <PoweredByAlat />
        </div>
      </div>
    </div>
  )
}

Business.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>
