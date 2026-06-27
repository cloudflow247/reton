import type { ReactNode } from 'react'
import { useState } from 'react'
import { Head, Link } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { PublicLayout } from '@/components/PublicLayout'
import { ChevronRightIcon } from '@/components/icons'

const faqs: [string, string][] = [
  ['What makes Reton different from other wallets?', 'Reton is built around trust, not just speed. Protected (callback) transfers let you hold a payment until you confirm, and wrong-transfer recovery helps you claw back money sent to the wrong person — backed by a real-time fraud engine.'],
  ['How does a protected transfer work?', 'When you send protected, the money moves into escrow instead of straight to the recipient. You can release it, or raise a callback to pull it back. If there’s a dispute, our decision engine reviews it — and every step is logged.'],
  ['Is my money safe? Who holds it?', 'Funding, payouts and settlement run on ALAT by Wema, a licensed bank platform. Inside Reton, every naira is tracked in an immutable double-entry ledger, so balances are always provably correct.'],
  ['How do I fund my wallet?', 'Add money by transferring to a one-time virtual account, or use your dedicated static account number. Your wallet is credited automatically once the bank confirms the transfer.'],
  ['What can I pay for?', 'Send money to any Reton account, withdraw to any bank, and pay bills — airtime, data, electricity, cable TV (DStv, GOtv, StarTimes) and Remita (RRR) — straight from your wallet.'],
  ['What is the transaction PIN for?', 'Your PIN is a second factor that authorises every payment. It locks temporarily after repeated wrong attempts, and it’s never stored in plain text.'],
  ['Does Reton charge fees?', 'Opening a wallet is free. Any applicable transfer or bill fees are always shown before you confirm — no surprises.'],
]

function Item({ q, a }: { q: string; a: string }) {
  const [open, setOpen] = useState(false)
  return (
    <div className="card overflow-hidden">
      <button
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left"
        aria-expanded={open}
      >
        <span className="font-display text-base font-semibold">{q}</span>
        <motion.span animate={{ rotate: open ? 90 : 0 }} transition={{ duration: 0.2 }} className="text-mint">
          <ChevronRightIcon size={18} />
        </motion.span>
      </button>
      <AnimatePresence initial={false}>
        {open && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.22, ease: 'easeOut' }}
          >
            <p className="px-5 pb-5 text-sm leading-relaxed text-muted">{a}</p>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

export default function Faq() {
  return (
    <div className="mx-auto max-w-3xl px-5 py-20">
      <Head title="FAQ" />
      <span className="inline-flex items-center gap-2 rounded-full border border-line bg-mint/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-mint">
        FAQ
      </span>
      <h1 className="mt-5 font-display text-4xl font-bold tracking-tight sm:text-5xl">Questions, answered.</h1>
      <p className="mt-5 text-lg leading-relaxed text-muted">
        Everything you need to know about keeping your money safe with Reton.
      </p>

      <div className="mt-10 space-y-3">
        {faqs.map(([q, a]) => (
          <Item key={q} q={q} a={a} />
        ))}
      </div>

      <div className="mt-10 flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-surface-2/50 p-6">
        <span className="text-sm text-muted">Still have a question?</span>
        <Link href="/contact" className="text-sm font-semibold text-mint hover:underline">
          Talk to our team →
        </Link>
      </div>
    </div>
  )
}

Faq.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>
