import { useState } from 'react'
import { motion } from 'framer-motion'
import { useMe } from '../lib/queries'
import { CheckIcon, CopyIcon, ShieldIcon } from '../components/icons'

export function Receive() {
  const { data } = useMe()
  const wallet = data?.wallets?.[0]
  const [copied, setCopied] = useState(false)

  return (
    <div className="mx-auto max-w-lg space-y-5">
      <div>
        <h1 className="font-display text-2xl font-bold tracking-tight">Receive money</h1>
        <p className="mt-1 text-sm text-muted">Share your account number — anyone on Reton can pay you instantly.</p>
      </div>

      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="brand-card relative overflow-hidden p-8 text-center text-white"
      >
        <div className="pointer-events-none absolute -right-12 -top-16 h-56 w-56 rounded-full bg-white/10 blur-2xl" />
        <p className="text-xs font-medium uppercase tracking-wider text-white/70">Your Reton account number</p>
        <div className="mt-3 font-num text-5xl font-bold tracking-[0.12em]">{wallet?.account_number ?? '—'}</div>
        <p className="mt-2 text-sm text-white/80">{data?.user.name}</p>
        <button
          onClick={() => {
            navigator.clipboard.writeText(wallet?.account_number ?? '')
            setCopied(true)
            setTimeout(() => setCopied(false), 1500)
          }}
          className="btn mt-6 inline-flex items-center gap-2 border border-white/25 bg-white/10 px-5 py-3 text-sm text-white transition hover:bg-white/20"
        >
          {copied ? <CheckIcon size={16} /> : <CopyIcon size={16} />}
          {copied ? 'Copied' : 'Copy account number'}
        </button>
      </motion.div>

      <p className="flex items-center justify-center gap-1.5 text-center text-xs text-muted">
        <ShieldIcon size={13} /> Payments to you are screened by Reton’s fraud checks and recovery tools.
      </p>
    </div>
  )
}
