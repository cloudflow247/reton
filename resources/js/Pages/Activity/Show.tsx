import type { ReactNode } from 'react'
import { useState } from 'react'
import { Head, Link, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import {
  ArrowRightIcon,
  CheckIcon,
  CopyIcon,
  ReceiveIcon,
  SendIcon,
  ShieldIcon,
} from '@/components/icons'
import { FormPanel, Page, PageHero, pageItem } from '@/components/page-kit'
import { Button, Pill } from '@/components/ui'
import { ngn, shortDate } from '@/lib/format'
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

export default function ActivityShow() {
  const { entry, transfer, wallet, receipt } = usePage<
    PageProps<{
      entry: StatementEntry
      transfer: Transfer | null
      wallet: WalletSnapshot | null
      receipt: ReceiptMeta
    }>
  >().props

  const [copied, setCopied] = useState(false)
  const [shared, setShared] = useState(false)
  const isCredit = entry.direction === 'credit'
  const title = entry.transaction?.description ?? entry.transaction?.type ?? 'Wallet movement'
  const reference = entry.transaction?.reference ?? entry.id

  const receiptText = [
    `${receipt.app} receipt`,
    `Reference: ${reference}`,
    `Type: ${entry.transaction?.type ?? 'movement'}`,
    `Status: ${entry.transaction?.status ?? 'posted'}`,
    `Amount: ${isCredit ? '+' : '−'}${ngn(entry.amount)}`,
    `Date: ${shortDate(entry.created_at)}`,
    wallet?.account_number ? `Reton ID: ${wallet.account_number}` : null,
    transfer ? `Transfer: ${transfer.reference} (${transfer.status})` : null,
    `Customer: ${receipt.user_name}`,
    `Issued: ${shortDate(receipt.issued_at)}`,
  ]
    .filter(Boolean)
    .join('\n')

  async function shareReceipt() {
    try {
      if (navigator.share) {
        await navigator.share({
          title: `${receipt.app} receipt`,
          text: receiptText,
        })
        setShared(true)
        return
      }
      await navigator.clipboard.writeText(receiptText)
      setCopied(true)
      setTimeout(() => setCopied(false), 1600)
    } catch {
      // User cancelled share — ignore.
    }
  }

  async function copyReceipt() {
    await navigator.clipboard.writeText(receiptText)
    setCopied(true)
    setTimeout(() => setCopied(false), 1600)
  }

  function printReceipt() {
    window.print()
  }

  return (
    <Page>
      <Head title={`Receipt · ${reference}`} />
      <PageHero
        icon={isCredit ? ReceiveIcon : SendIcon}
        title="Transaction details"
        subtitle="Immutable ledger entry — print or share your receipt."
        tone={isCredit ? 'mint' : 'sky'}
      />

      <motion.div variants={pageItem} className="print:hidden">
        <Link href="/activity" className="inline-flex items-center gap-1 text-sm font-medium text-mint hover:underline">
          ← Back to activity
        </Link>
      </motion.div>

      <FormPanel className="!space-y-5 print:border-0 print:shadow-none" id="receipt-panel">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">Receipt</p>
            <h2 className="mt-1 font-display text-xl font-bold">{title}</h2>
            <p className="mt-1 text-xs text-muted">{shortDate(entry.created_at)}</p>
          </div>
          <Pill tone={isCredit ? 'mint' : 'muted'}>{isCredit ? 'Credit' : 'Debit'}</Pill>
        </div>

        <p
          className={`font-num text-3xl font-bold tracking-tight ${isCredit ? 'text-mint' : 'text-text'}`}
        >
          {isCredit ? '+' : '−'}
          {ngn(entry.amount)}
        </p>

        <dl className="grid gap-3 rounded-2xl border border-line bg-surface-2/40 px-4 py-3 text-sm sm:grid-cols-2">
          <Detail label="Reference" value={reference} mono />
          <Detail label="Ledger status" value={entry.transaction?.status ?? 'posted'} />
          <Detail label="Type" value={entry.transaction?.type ?? 'movement'} />
          <Detail label="Currency" value={entry.currency} />
          {wallet?.account_number && <Detail label="Reton ID" value={wallet.account_number} mono />}
          {transfer && <Detail label="Transfer status" value={transfer.status} />}
          {transfer?.type === 'protected' && (
            <Detail label="Protection" value="Callback Protection hold" />
          )}
          <Detail label="Customer" value={receipt.user_name} />
        </dl>

        {transfer?.type === 'protected' && (
          <div className="flex items-start gap-2 rounded-xl border border-mint/20 bg-mint/[0.05] px-3 py-2.5 text-xs text-muted">
            <ShieldIcon size={14} className="mt-0.5 shrink-0 text-mint" />
            <span>
              Protected transfers credit the ledger immediately and hold spendable balance in escrow until
              released or resolved.
            </span>
          </div>
        )}

        <div className="flex flex-wrap gap-2 print:hidden">
          <Button type="button" onClick={printReceipt}>
            Print receipt
          </Button>
          <Button type="button" variant="ghost" onClick={shareReceipt}>
            {shared ? 'Shared' : 'Share receipt'}
          </Button>
          <Button type="button" variant="ghost" onClick={copyReceipt}>
            {copied ? (
              <>
                <CheckIcon size={14} /> Copied
              </>
            ) : (
              <>
                <CopyIcon size={14} /> Copy text
              </>
            )}
          </Button>
          {transfer && (
            <Link
              href="/protection"
              className="inline-flex items-center gap-1 rounded-xl border border-line px-4 py-2 text-sm font-semibold transition hover:border-mint/30 hover:text-mint"
            >
              Protection hub <ArrowRightIcon size={14} />
            </Link>
          )}
        </div>
      </FormPanel>

      <style>{`
        @media print {
          body { background: white !important; }
          nav, header, aside, .print\\:hidden { display: none !important; }
          #receipt-panel { box-shadow: none !important; border: 1px solid #ddd !important; }
        }
      `}</style>
    </Page>
  )
}

ActivityShow.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function Detail({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
  return (
    <div>
      <dt className="text-[10px] font-semibold uppercase tracking-wide text-muted">{label}</dt>
      <dd className={`mt-0.5 break-all font-medium text-text ${mono ? 'font-num' : ''}`}>{value}</dd>
    </div>
  )
}
