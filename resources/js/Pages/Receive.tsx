import type { ReactNode } from 'react'
import { useMemo, useState } from 'react'
import { Head, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { QRCodeSVG } from 'qrcode.react'
import { AppShell } from '@/components/AppShell'
import { AmountField, Card } from '@/components/ui'
import { CheckIcon, CopyIcon, QrIcon, ShareIcon, ShieldIcon } from '@/components/icons'
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

      {/* QR + account card */}
      <motion.div
        initial={{ opacity: 0, y: 12, scale: 0.99 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
        className="brand-card relative overflow-hidden p-7 text-center text-white"
      >
        <motion.div
          aria-hidden
          className="pointer-events-none absolute -right-12 -top-16 h-56 w-56 rounded-full bg-white/10 blur-2xl"
          animate={{ scale: [1, 1.15, 1], opacity: [0.55, 0.85, 0.55] }}
          transition={{ duration: 8, repeat: Infinity, ease: 'easeInOut' }}
        />

        <div className="relative">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-xs font-medium">
            <QrIcon size={13} /> Scan to pay
          </span>

          {/* The QR sits on a white tile for reliable scanning. */}
          <motion.div
            key={payload}
            initial={{ opacity: 0, scale: 0.96 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.25 }}
            className="mx-auto mt-5 w-fit rounded-2xl bg-white p-4 shadow-lg"
          >
            <QRCodeSVG value={payload} size={168} level="M" fgColor="#0b2e25" bgColor="#ffffff" marginSize={0} />
          </motion.div>

          {minor > 0 && <div className="mt-4 font-num text-2xl font-bold">{ngn(minor)}</div>}

          <p className="mt-4 text-xs font-medium uppercase tracking-wider text-white/70">Account number</p>
          <div className="mt-1 font-num text-3xl font-bold tracking-[0.12em]">{account || '—'}</div>
          <p className="mt-1 text-sm text-white/80">{auth.user?.name}</p>

          <div className="mt-6 flex justify-center gap-2.5">
            <button
              onClick={copy}
              className="btn inline-flex items-center gap-2 border border-white/25 bg-white/10 px-4 py-2.5 text-sm text-white transition hover:bg-white/20"
            >
              {copied ? <CheckIcon size={16} /> : <CopyIcon size={16} />}
              {copied ? 'Copied' : 'Copy'}
            </button>
            <button
              onClick={share}
              className="btn inline-flex items-center gap-2 bg-white px-4 py-2.5 text-sm text-mint-strong shadow-sm transition hover:bg-white/90"
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
          className="flex w-full items-center justify-between"
        >
          <span className="font-display text-sm font-semibold">Request a specific amount</span>
          <span className="text-sm font-medium text-mint">{requesting ? 'Clear' : 'Add'}</span>
        </button>
        {requesting && (
          <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }}>
            <AmountField value={amount} onChange={setAmount} />
            <p className="mt-2 text-xs text-muted">The amount is baked into your QR and share link.</p>
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
