import type { ReactNode } from 'react'
import { useMemo, useState } from 'react'
import { Head, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { QRCodeSVG } from 'qrcode.react'
import { AppShell } from '@/components/AppShell'
import { AmountField, Card } from '@/components/ui'
import { CheckIcon, CopyIcon, PlusIcon, QrIcon, ShareIcon, ShieldIcon } from '@/components/icons'
import { ngn, toMinor } from '@/lib/format'
import type { SharedProps } from '@/types'

export default function Receive() {
  const { auth } = usePage<SharedProps>().props
  const wallet = auth.wallets[0]
  const account = wallet?.account_number ?? ''

  const [copied, setCopied] = useState(false)
  const [requesting, setRequesting] = useState(false)
  const [amount, setAmount] = useState('')
  const minor = toMinor(amount)

  // A scannable pay link. Encodes the account (and an optional requested
  // amount) so a future deep-link / scanner can prefill a transfer.
  const payload = useMemo(() => {
    const base = `https://reton.ng/pay/${account}`
    return minor > 0 ? `${base}?amount=${minor}` : base
  }, [account, minor])

  function copy() {
    navigator.clipboard.writeText(minor > 0 ? `${account} · ${ngn(minor)}` : account)
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }

  async function share() {
    const text =
      minor > 0
        ? `Pay me ${ngn(minor)} on Reton — account ${account} (${auth.user?.name ?? ''}).`
        : `Pay me on Reton — account ${account} (${auth.user?.name ?? ''}).`
    if (navigator.share) {
      try {
        await navigator.share({ title: 'Pay me on Reton', text, url: payload })
        return
      } catch {
        /* user dismissed the share sheet — fall through to copy */
      }
    }
    navigator.clipboard.writeText(`${text} ${payload}`)
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }

  return (
    <div className="mx-auto max-w-lg space-y-5">
      <Head title="Receive" />
      <div>
        <h1 className="font-display text-2xl font-bold tracking-tight">Receive money</h1>
        <p className="mt-1 text-sm text-muted">Share your account or QR — anyone on Reton can pay you instantly.</p>
      </div>

      {/* QR + account hero — a living emerald mesh with morphing light. */}
      <motion.div
        initial={{ opacity: 0, y: 14, scale: 0.99 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
        className="mesh sheen relative overflow-hidden rounded-3xl p-7 text-center text-white shield-glow"
      >
        <div aria-hidden className="pointer-events-none absolute inset-0">
          <div className="blob absolute -right-16 -top-20 h-56 w-56 bg-white/10 blur-2xl" />
          <div className="blob-slow absolute -bottom-20 -left-16 h-52 w-52 bg-mint/30 blur-2xl" />
        </div>

        <div className="relative">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium backdrop-blur-sm">
            <QrIcon size={13} /> Scan to pay
          </span>

          {/* The QR sits on a white tile for reliable scanning. */}
          <motion.div
            key={payload}
            initial={{ opacity: 0, scale: 0.96 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.25 }}
            className="mx-auto mt-6 w-fit rounded-3xl bg-white p-4 shadow-2xl ring-1 ring-white/40"
          >
            <QRCodeSVG value={payload} size={172} level="M" fgColor="#0b2e25" bgColor="#ffffff" marginSize={0} />
          </motion.div>

          {minor > 0 && (
            <motion.div
              initial={{ opacity: 0, y: 4 }}
              animate={{ opacity: 1, y: 0 }}
              className="mt-5 font-num text-3xl font-bold tracking-tight"
            >
              {ngn(minor)}
            </motion.div>
          )}

          <p className="mt-5 text-[0.7rem] font-medium uppercase tracking-[0.18em] text-white/65">Account number</p>
          <div className="mt-1.5 font-num text-3xl font-bold tracking-[0.14em]">{account || '—'}</div>
          <p className="mt-1.5 text-sm font-medium text-white/85">{auth.user?.name}</p>

          <div className="mt-7 grid grid-cols-2 gap-3">
            <button
              onClick={copy}
              className="btn inline-flex items-center justify-center gap-2 border border-white/25 bg-white/10 px-4 py-3 text-sm text-white backdrop-blur-sm transition hover:bg-white/20"
            >
              {copied ? <CheckIcon size={16} /> : <CopyIcon size={16} />}
              {copied ? 'Copied' : 'Copy'}
            </button>
            <button
              onClick={share}
              className="btn inline-flex items-center justify-center gap-2 bg-white px-4 py-3 text-sm text-mint-strong shadow-sm transition hover:bg-white/90"
            >
              <ShareIcon size={16} /> Share
            </button>
          </div>
        </div>
      </motion.div>

      {/* Optional: request a specific amount */}
      <Card className="space-y-4">
        <button
          type="button"
          onClick={() => {
            setRequesting((v) => !v)
            if (requesting) setAmount('')
          }}
          className="flex w-full items-center justify-between gap-3"
        >
          <span className="flex items-center gap-3">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-mint/10 text-mint">
              <PlusIcon size={18} />
            </span>
            <span className="text-left">
              <span className="block font-display text-sm font-semibold">Request a specific amount</span>
              <span className="block text-xs text-muted">Bake a figure into your QR &amp; share link</span>
            </span>
          </span>
          <span className="shrink-0 text-sm font-semibold text-mint">{requesting ? 'Clear' : 'Add'}</span>
        </button>
        {requesting && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            className="overflow-hidden"
          >
            <div className="border-t border-line pt-4">
              <AmountField value={amount} onChange={setAmount} />
            </div>
          </motion.div>
        )}
      </Card>

      <p className="flex items-center justify-center gap-1.5 text-center text-xs text-muted">
        <ShieldIcon size={13} /> Payments to you are screened by Reton’s fraud checks and recovery tools.
      </p>
    </div>
  )
}

Receive.layout = (page: ReactNode) => <AppShell>{page}</AppShell>
