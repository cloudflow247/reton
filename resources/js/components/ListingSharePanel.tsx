import { useMemo, useState } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import { Button } from '@/components/ui'
import { CheckIcon, CopyIcon, QrIcon, ShareIcon } from '@/components/icons'
import { ngn } from '@/lib/format'
import type { DigitalListing } from '@/lib/types'

type Props = {
  listing: DigitalListing
  compact?: boolean
}

export function ListingSharePanel({ listing, compact = false }: Props) {
  const [copied, setCopied] = useState<'link' | 'code' | false>(false)
  const [showQr, setShowQr] = useState(!compact)
  const shareUrl = listing.share_url ?? ''
  const itemCode = listing.item_code ?? ''
  const shareText = `${listing.title} - ${ngn(listing.price)} on Reton (protected purchase).`

  const qrPayload = useMemo(
    () =>
      JSON.stringify({
        type: 'reton_listing',
        id: listing.id,
        code: itemCode || null,
        url: shareUrl,
        app: listing.app_url ?? null,
      }),
    [itemCode, listing.app_url, listing.id, shareUrl],
  )

  function markCopied(kind: 'link' | 'code') {
    setCopied(kind)
    setTimeout(() => setCopied(false), 1500)
  }

  function copyLink() {
    navigator.clipboard.writeText(shareUrl)
    markCopied('link')
  }

  function copyCode() {
    if (!itemCode) return
    navigator.clipboard.writeText(itemCode)
    markCopied('code')
  }

  async function shareLink() {
    const text = `${shareText}\n${shareUrl}`

    if (navigator.share) {
      try {
        await navigator.share({
          title: listing.title,
          text: shareText,
          url: shareUrl,
        })
        return
      } catch {
        /* dismissed */
      }
    }

    navigator.clipboard.writeText(text)
    markCopied('link')
  }

  if (!shareUrl) {
    return null
  }

  return (
    <div className={`rounded-xl border border-mint/25 bg-mint/[0.04] ${compact ? 'p-3' : 'p-4'}`}>
      <p className="text-sm font-semibold text-text">Share with your buyer</p>
      <p className="mt-1 text-xs text-muted">
        Send the link or item code in WhatsApp, Instagram, or email. Buyers cannot browse a public shop - they need one
        of these to open your listing.
      </p>

      {itemCode && (
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <span className="text-[11px] font-semibold uppercase tracking-wide text-muted">Item code</span>
          <code className="rounded-lg border border-line bg-surface px-3 py-2 font-mono text-sm font-bold tracking-wider text-text">
            {itemCode}
          </code>
          <Button type="button" variant="secondary" onClick={copyCode}>
            {copied === 'code' ? <CheckIcon size={16} /> : <CopyIcon size={16} />}
            {copied === 'code' ? 'Copied' : 'Copy code'}
          </Button>
        </div>
      )}

      <div className="mt-3 flex flex-wrap gap-2">
        <code className="min-w-0 flex-1 truncate rounded-lg border border-line bg-surface px-3 py-2 text-xs text-muted">
          {shareUrl}
        </code>
        <Button type="button" variant="secondary" onClick={copyLink}>
          {copied === 'link' ? <CheckIcon size={16} /> : <CopyIcon size={16} />}
          {copied === 'link' ? 'Copied' : 'Copy link'}
        </Button>
        <Button type="button" variant="secondary" onClick={shareLink}>
          <ShareIcon size={16} /> Share
        </Button>
        {compact && (
          <Button type="button" variant="ghost" onClick={() => setShowQr((v) => !v)}>
            <QrIcon size={16} /> QR
          </Button>
        )}
      </div>

      {showQr && (
        <div className="mt-4 flex flex-col items-center gap-3 sm:flex-row sm:items-start">
          <div className="rounded-2xl border border-line bg-white p-3 shadow-sm">
            <QRCodeSVG value={qrPayload} size={compact ? 140 : 168} level="M" includeMargin />
          </div>
          <div className="text-center sm:text-left">
            <p className="text-xs font-semibold text-text">QR for in-person handoff</p>
            <p className="mt-1 max-w-xs text-[11px] leading-relaxed text-muted">
              Buyer scans to open this listing{itemCode ? ` or types ${itemCode}` : ''}. Links use{' '}
              <span className="font-mono text-text">/l/…</span> so mobile apps can claim them.
            </p>
          </div>
        </div>
      )}
    </div>
  )
}
