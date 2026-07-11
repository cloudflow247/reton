import type { ReactNode } from 'react'
import { useMemo, useState } from 'react'
import { Head, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { QRCodeSVG } from 'qrcode.react'
import { AppShell } from '@/components/AppShell'
import { StaticWalletCard } from '@/components/StaticWalletCard'
import { FormPanel, Page, PageHeader } from '@/components/page-kit'
import { AmountField, Card } from '@/components/ui'
import { CheckIcon, CopyIcon, PlusIcon, QrIcon, ShareIcon, ShieldIcon } from '@/components/icons'
import { ngn, toMinor } from '@/lib/format'
import type { KycProfile, SharedProps, StaticAccount } from '@/types'

type ReceiveProps = SharedProps & {
  kyc: KycProfile
  staticAccount: StaticAccount | null
}

export default function Receive() {
  const { auth, kyc, staticAccount } = usePage<ReceiveProps>().props
  const wallet = auth.wallets[0]
  const account = wallet?.account_number ?? ''
  const profileName = auth.user?.name ?? null

  const [copied, setCopied] = useState(false)
  const [requesting, setRequesting] = useState(false)
  const [amount, setAmount] = useState('')
  const minor = toMinor(amount)

  const payload = useMemo(() => {
    const base = `https://retonpay.com/pay/${account}`
    return minor > 0 ? `${base}?amount=${minor}` : base
  }, [account, minor])

  function copy() {
    void navigator.clipboard.writeText(minor > 0 ? `${account} · ${ngn(minor)}` : account)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 1500)
  }

  async function share() {
    const text =
      minor > 0
        ? `Pay me ${ngn(minor)} on Reton — RETON ID ${account} (${profileName ?? ''}).`
        : `Pay me on Reton — RETON ID ${account} (${profileName ?? ''}).`
    if (navigator.share) {
      try {
        await navigator.share({ title: 'Pay me on Reton', text, url: payload })
        return
      } catch {
        /* dismissed */
      }
    }
    void navigator.clipboard.writeText(`${text} ${payload}`)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 1500)
  }

  return (
    <Page narrow>
      <Head title="Receive" />
      <PageHeader title="Receive" subtitle="Bank transfer or Reton ID" />

      <StaticWalletCard
        kyc={kyc}
        staticAccount={staticAccount}
        wallet={wallet}
        profileName={profileName}
      />

      <Card className="overflow-hidden p-0">
        <div className="flex items-center justify-between gap-2 border-b border-line/70 px-4 py-2.5">
          <div className="flex items-center gap-2 text-[11px] font-semibold text-muted">
            <ShieldIcon size={13} className="text-mint" />
            Reton ID
          </div>
          <span className="inline-flex items-center gap-1 text-[11px] font-medium text-muted">
            <QrIcon size={12} /> Scan to pay
          </span>
        </div>

        <div className="flex flex-col items-center px-4 py-5">
          <div className="rounded-2xl border border-line bg-white p-3 shadow-sm">
            <QRCodeSVG value={payload} size={156} level="M" fgColor="#0b2e25" bgColor="#ffffff" marginSize={0} />
          </div>

          {minor > 0 && (
            <p className="mt-3 font-num text-xl font-bold text-text">{ngn(minor)}</p>
          )}

          <button
            type="button"
            onClick={copy}
            className="mt-4 flex w-full max-w-xs items-center gap-3 rounded-xl border border-line bg-surface-2/50 px-3.5 py-3 text-left transition hover:border-mint/30"
          >
            <span className="min-w-0 flex-1">
              <span className="block text-[10px] font-semibold uppercase tracking-wide text-muted">Account</span>
              <span className="mt-0.5 block font-num text-lg font-bold tracking-wider text-text">
                {account || '—'}
              </span>
              <span className="mt-0.5 block truncate text-xs text-muted">{profileName}</span>
            </span>
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-mint/10 text-mint">
              {copied ? <CheckIcon size={15} /> : <CopyIcon size={15} />}
            </span>
          </button>

          <div className="mt-3 grid w-full max-w-xs grid-cols-2 gap-2">
            <button
              type="button"
              onClick={copy}
              className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-line bg-surface px-3 py-2 text-xs font-semibold transition hover:border-mint/30"
            >
              {copied ? <CheckIcon size={13} className="text-mint" /> : <CopyIcon size={13} className="text-mint" />}
              {copied ? 'Copied' : 'Copy'}
            </button>
            <button
              type="button"
              onClick={share}
              className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-mint/25 bg-mint/10 px-3 py-2 text-xs font-semibold text-mint transition hover:bg-mint/15"
            >
              <ShareIcon size={13} /> Share
            </button>
          </div>
        </div>
      </Card>

      <FormPanel className="!space-y-3 !p-3.5">
        <button
          type="button"
          onClick={() => {
            setRequesting((v) => !v)
            if (requesting) setAmount('')
          }}
          className="flex w-full items-center justify-between gap-3"
        >
          <span className="flex items-center gap-2.5">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-mint/10 text-mint">
              <PlusIcon size={16} />
            </span>
            <span className="text-left">
              <span className="block text-sm font-semibold">Request amount</span>
              <span className="block text-xs text-muted">Add to QR & share link</span>
            </span>
          </span>
          <span className="text-xs font-semibold text-mint">{requesting ? 'Clear' : 'Add'}</span>
        </button>
        {requesting && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            className="overflow-hidden border-t border-line pt-3"
          >
            <AmountField value={amount} onChange={setAmount} />
          </motion.div>
        )}
      </FormPanel>
    </Page>
  )
}

Receive.layout = (page: ReactNode) => <AppShell>{page}</AppShell>
