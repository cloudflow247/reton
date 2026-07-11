import type { ReactNode } from 'react'
import { useState } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import {
  ArrowRightIcon,
  CheckIcon,
  ReceiveIcon,
  SendIcon,
  ShieldIcon,
} from '@/components/icons'
import { Page, pageItem } from '@/components/page-kit'
import { Button } from '@/components/ui'
import { ngn, shortDate } from '@/lib/format'
import { paintReceiptPng, shareOrDownloadPng } from '@/lib/receipt-export'
import type { StatementEntry, Transfer } from '@/lib/types'
import type { PageProps } from '@/types'

type ReceiptMeta = {
  issued_at: string
  app: string
  user_name: string
  user_email: string
}

type WalletSnapshot = {
  id: string
  account_number: string | null
  currency: string
  available_balance: number
  held_balance: number
  balance: number
}

type ReceiptParty = {
  kind: string
  label: string
  name: string | null
  reton_id: string | null
  bank_name: string | null
  account_number: string | null
  detail: string | null
}

type ReceiptParties = {
  channel: string
  channel_label: string
  from: ReceiptParty
  to: ReceiptParty
  funding_account?: string | null
}

function partyLines(party: ReceiptParty): string[] {
  const lines: string[] = []
  if (party.name) lines.push(party.name)
  if (party.reton_id) lines.push(`Reton ID ${party.reton_id}`)
  if (party.bank_name) lines.push(party.bank_name)
  if (party.account_number) lines.push(party.account_number)
  if (party.detail && !lines.includes(party.detail)) lines.push(party.detail)
  return lines.length > 0 ? lines : ['—']
}

export default function ActivityShow() {
  const { entry, transfer, parties, wallet, receipt } = usePage<
    PageProps<{
      entry: StatementEntry
      transfer: Transfer | null
      parties: ReceiptParties | null
      wallet: WalletSnapshot | null
      receipt: ReceiptMeta
    }>
  >().props

  const [busy, setBusy] = useState<'image' | 'pdf' | null>(null)
  const [status, setStatus] = useState<string | null>(null)

  const isCredit = entry.direction === 'credit'
  const title = entry.transaction?.description ?? entry.transaction?.type ?? 'Wallet movement'
  const reference = entry.transaction?.reference ?? entry.id
  const amountLabel = `${isCredit ? '+' : '−'}${ngn(entry.amount)}`
  const safeFileRef = reference.replace(/[^a-zA-Z0-9_-]+/g, '_').slice(0, 48)

  async function shareImage() {
    setBusy('image')
    setStatus(null)
    try {
      const blob = await paintReceiptPng({
        app: receipt.app,
        title,
        amountLabel,
        isCredit,
        reference,
        status: entry.transaction?.status ?? 'posted',
        type: entry.transaction?.type ?? 'movement',
        dateLabel: shortDate(entry.created_at),
        customer: receipt.user_name,
        retonId: wallet?.account_number,
        transferRef: transfer ? `${transfer.reference} (${transfer.status})` : null,
        channelLabel: parties?.channel_label ?? null,
        fromLines: parties ? partyLines(parties.from) : null,
        toLines: parties ? partyLines(parties.to) : null,
      })
      const result = await shareOrDownloadPng(
        blob,
        `reton-receipt-${safeFileRef}.png`,
        `${receipt.app} receipt`,
      )
      setStatus(result === 'shared' ? 'Image shared' : 'Image saved')
    } catch (error) {
      if ((error as Error)?.name === 'AbortError') {
        return
      }
      setStatus('Could not export image. Try again.')
    } finally {
      setBusy(null)
      window.setTimeout(() => setStatus(null), 2200)
    }
  }

  function savePdf() {
    setBusy('pdf')
    setStatus(null)
    window.print()
    setBusy(null)
    setStatus('Use your printer dialog → Save as PDF')
    window.setTimeout(() => setStatus(null), 2800)
  }

  return (
    <Page className="!px-3 sm:!px-4">
      <Head title={`Receipt · ${reference}`} />

      <motion.div variants={pageItem} className="print:hidden mb-3 flex items-center justify-between gap-3">
        <Link href="/activity" className="inline-flex items-center gap-1 text-sm font-medium text-mint hover:underline">
          ← Activity
        </Link>
        {transfer && (
          <Link
            href="/protection"
            className="inline-flex items-center gap-1 text-xs font-semibold text-muted hover:text-mint"
          >
            Protection <ArrowRightIcon size={12} />
          </Link>
        )}
      </motion.div>

      <motion.article
        id="receipt-card"
        variants={pageItem}
        className="receipt-card relative mx-auto w-full max-w-md overflow-hidden rounded-[28px] bg-surface shadow-[0_24px_60px_-28px_rgba(9,79,57,0.45)] ring-1 ring-mint/15"
      >
        <header className="relative overflow-hidden bg-gradient-to-br from-[#0a6a4d] via-[#0e7e5c] to-[#094f39] px-5 pb-10 pt-5 text-white sm:px-6 sm:pt-6">
          <WaveLayer className="pointer-events-none absolute inset-x-0 bottom-0 h-16 opacity-90" />
          <div className="relative flex items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/80">
                {receipt.app}
              </p>
              <h1 className="mt-1 font-display text-xl font-bold tracking-tight sm:text-2xl">
                Payment receipt
              </h1>
              <p className="mt-1 truncate text-sm text-white/90">{title}</p>
              {parties?.channel_label && (
                <p className="mt-2 inline-flex rounded-full bg-white/15 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white">
                  {parties.channel_label}
                </p>
              )}
            </div>
            <span
              className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 ${
                isCredit ? 'text-emerald-100' : 'text-white'
              }`}
            >
              {isCredit ? <ReceiveIcon size={20} /> : <SendIcon size={20} />}
            </span>
          </div>
        </header>

        <div className="relative -mt-5 space-y-4 px-5 pb-6 pt-1 sm:px-6">
          <div className="rounded-2xl border border-mint/15 bg-surface px-4 py-4 shadow-sm sm:px-5">
            <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-text/70">
              {isCredit ? 'Amount received' : 'Amount sent'}
            </p>
            <p
              className={`mt-1 font-num text-[2rem] font-bold leading-none tracking-tight sm:text-[2.35rem] ${
                isCredit ? 'text-mint' : 'text-text'
              }`}
            >
              {amountLabel}
            </p>
            <p className="mt-2 text-xs font-medium text-text/70">{shortDate(entry.created_at)}</p>
          </div>

          {parties && (
            <div className="space-y-2">
              <PartyCard party={parties.from} />
              <div className="flex justify-center">
                <span className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-mint/15 text-mint">
                  <ArrowRightIcon size={14} className="rotate-90" />
                </span>
              </div>
              <PartyCard party={parties.to} />
              {parties.funding_account && (
                <p className="px-1 text-[11px] font-medium text-text/70">
                  Funded to account <span className="font-num text-text">{parties.funding_account}</span>
                </p>
              )}
            </div>
          )}

          <dl className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
            <Detail label="Reference" value={reference} mono />
            <Detail label="Status" value={entry.transaction?.status ?? 'posted'} />
            <Detail label="Type" value={entry.transaction?.type ?? 'movement'} />
            <Detail label="Currency" value={entry.currency} />
            {transfer && <Detail label="Transfer" value={`${transfer.reference} · ${transfer.status}`} />}
            <Detail label="Issued to" value={receipt.user_name} />
            <Detail label="Issued" value={shortDate(receipt.issued_at)} />
          </dl>

          {transfer?.type === 'protected' && (
            <div className="flex items-start gap-2 rounded-2xl border border-mint/20 bg-mint/[0.06] px-3.5 py-3 text-xs leading-relaxed text-text/80">
              <ShieldIcon size={14} className="mt-0.5 shrink-0 text-mint" />
              <span>
                Protected transfer — funds stayed in escrow until release or callback resolution.
              </span>
            </div>
          )}

          <footer className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-mint/[0.08] via-surface-2 to-mint/[0.08] px-4 py-3">
            <WaveLayer className="pointer-events-none absolute inset-0 opacity-40" muted />
            <p className="relative text-center text-[11px] font-semibold text-text/75">
              Trust-first payments · Immutable ledger entry
            </p>
          </footer>
        </div>
      </motion.article>

      <motion.div
        variants={pageItem}
        className="receipt-actions print:hidden mx-auto mt-4 flex w-full max-w-md flex-col gap-2 sm:flex-row"
      >
        <Button
          type="button"
          className="w-full sm:flex-1"
          loading={busy === 'image'}
          disabled={busy !== null}
          onClick={() => void shareImage()}
        >
          Share image
        </Button>
        <Button
          type="button"
          variant="ghost"
          className="w-full sm:flex-1"
          loading={busy === 'pdf'}
          disabled={busy !== null}
          onClick={savePdf}
        >
          Save PDF
        </Button>
      </motion.div>

      {status && (
        <p className="print:hidden mx-auto mt-3 flex max-w-md items-center justify-center gap-1.5 text-center text-xs font-medium text-mint">
          <CheckIcon size={12} /> {status}
        </p>
      )}

      <style>{`
        @media print {
          @page { margin: 12mm; size: A4; }
          html, body { background: #fff !important; }
          body * { visibility: hidden !important; }
          #receipt-card, #receipt-card * { visibility: visible !important; }
          #receipt-card {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            box-shadow: none !important;
            border-radius: 16px !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
          }
          .receipt-actions, .print\\:hidden { display: none !important; }
        }
      `}</style>
    </Page>
  )
}

ActivityShow.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function PartyCard({ party }: { party: ReceiptParty }) {
  const lines = partyLines(party)

  return (
    <div className="rounded-2xl border border-line bg-surface-2/60 px-3.5 py-3">
      <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-text/65">{party.label}</p>
      <p className="mt-1 text-sm font-bold text-text">{lines[0]}</p>
      {lines.slice(1).map((line) => (
        <p key={line} className="mt-0.5 font-num text-xs font-semibold tracking-wide text-text/80">
          {line}
        </p>
      ))}
    </div>
  )
}

function Detail({
  label,
  value,
  mono = false,
}: {
  label: string
  value: string | number | null | undefined
  mono?: boolean
}) {
  return (
    <div className="rounded-2xl border border-line/80 bg-surface-2/50 px-3.5 py-3">
      <dt className="text-[10px] font-bold uppercase tracking-[0.14em] text-text/65">{label}</dt>
      <dd className={`mt-1 break-all text-sm font-bold text-text ${mono ? 'font-num tracking-wide' : ''}`}>
        {value ?? '—'}
      </dd>
    </div>
  )
}

function WaveLayer({ className, muted = false }: { className?: string; muted?: boolean }) {
  const a = muted ? '#0e7e5c22' : '#ffffff33'
  const b = muted ? '#0a6a4d18' : '#ffffff22'
  return (
    <svg className={className} viewBox="0 0 400 80" preserveAspectRatio="none" aria-hidden>
      <path fill={a} d="M0 40 Q50 10 100 40 T200 40 T300 40 T400 40 V80 H0 Z" />
      <path fill={b} d="M0 52 Q50 28 100 52 T200 52 T300 52 T400 52 V80 H0 Z" />
    </svg>
  )
}
