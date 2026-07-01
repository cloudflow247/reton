import type { ReactNode } from 'react'
import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { useForm } from 'react-hook-form'
import { AppShell } from '@/components/AppShell'
import { DigitalOrderEscrowCard } from '@/components/DigitalOrderEscrowCard'
import { ListingSharePanel } from '@/components/ListingSharePanel'
import { RhfField } from '@/components/forms/RhfField'
import { Button, Card, Modal } from '@/components/ui'
import { ShieldIcon } from '@/components/icons'
import { deviceHeaders } from '@/lib/device'
import { ngn } from '@/lib/format'
import {
  createListingSchema,
  purchaseListingSchema,
  type CreateListingValues,
  type PurchaseListingValues,
} from '@/lib/schemas/marketplace'
import type { DigitalListing, DigitalOrder, PageProps } from '@/types'

type MarketplaceProps = PageProps<{
  listings: DigitalListing[]
  myListings: DigitalListing[]
  orders: DigitalOrder[]
}>

export default function Marketplace() {
  const { listings, myListings, orders, flash } = usePage<MarketplaceProps>().props
  const [buyTarget, setBuyTarget] = useState<DigitalListing | null>(null)
  const [showCreate, setShowCreate] = useState(false)

  const activeOrders = orders.filter((o) => o.status !== 'completed' && o.status !== 'refunded')

  return (
    <div className="space-y-6 pb-4">
      <Head title="Digital marketplace" />

      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight">Digital marketplace</h1>
          <p className="mt-1 max-w-lg text-sm text-muted">
            Buy and sell digital items with Reton protection until both sides are satisfied.
          </p>
        </div>
        <Button onClick={() => setShowCreate(true)}>Sell an item</Button>
      </header>

      {flash.success && <p className="text-sm text-mint">{flash.success}</p>}
      {flash.error && <p className="text-sm text-danger">{flash.error}</p>}

      <Card className="border-mint/20 bg-mint/[0.04] p-4">
        <p className="flex items-center gap-2 text-sm font-semibold text-text">
          <ShieldIcon size={16} className="text-mint" /> Protected sale in 3 steps
        </p>
        <ol className="mt-2 grid gap-2 sm:grid-cols-3">
          {[
            ['1. Pay', 'Buyer pays — funds leave their available balance.'],
            ['2. Deliver', 'You deliver — buyer sees it; funds show as pending for you.'],
            ['3. Confirm', 'Buyer confirms or disputes — pending becomes spendable.'],
          ].map(([t, d]) => (
            <li key={t} className="rounded-lg border border-line/80 bg-surface px-3 py-2">
              <p className="text-xs font-semibold text-mint">{t}</p>
              <p className="text-[11px] text-muted">{d}</p>
            </li>
          ))}
        </ol>
      </Card>

      {activeOrders.length > 0 && (
        <section className="space-y-2">
          <h2 className="text-sm font-semibold">Active orders</h2>
          <div className="space-y-2">
            {activeOrders.map((order) => (
              <DigitalOrderEscrowCard key={order.id} order={order} />
            ))}
          </div>
        </section>
      )}

      {orders.length > activeOrders.length && (
        <section className="space-y-2">
          <h2 className="text-sm font-semibold text-muted">Past orders</h2>
          <div className="space-y-2">
            {orders
              .filter((o) => o.status === 'completed' || o.status === 'refunded')
              .map((order) => (
                <DigitalOrderEscrowCard key={order.id} order={order} compact />
              ))}
          </div>
        </section>
      )}

      <section className="space-y-2">
        <h2 className="text-sm font-semibold">Browse listings</h2>
        {listings.length === 0 ? (
          <Card className="p-8 text-center text-sm text-muted">No listings from other users yet.</Card>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2">
            {listings.map((listing) => (
              <Card key={listing.id} className="flex flex-col p-4">
                <p className="font-display text-base font-semibold">{listing.title}</p>
                <p className="mt-1 line-clamp-2 text-xs text-muted">{listing.description}</p>
                <p className="mt-3 font-num text-lg font-bold text-mint">{ngn(listing.price)}</p>
                <p className="text-xs text-muted">by {listing.seller_name ?? 'Seller'}</p>
                <div className="mt-4 flex flex-col gap-2">
                  <Button className="w-full" onClick={() => setBuyTarget(listing)}>
                    Buy with protection
                  </Button>
                  <Link
                    href={`/l/${listing.id}`}
                    className="btn w-full border border-line bg-surface py-2.5 text-center text-sm hover:border-mint/40"
                  >
                    View listing
                  </Link>
                </div>
              </Card>
            ))}
          </div>
        )}
      </section>

      {myListings.length > 0 && (
        <section className="space-y-2">
          <h2 className="text-sm font-semibold">Your listings</h2>
          <div className="space-y-2">
            {myListings.map((listing) => (
              <Card key={listing.id} className="space-y-3 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="font-medium">{listing.title}</p>
                    <p className="text-xs text-muted">
                      {ngn(listing.price)} · {listing.status}
                    </p>
                  </div>
                  {listing.status === 'active' && (
                    <Link
                      href={`/l/${listing.id}`}
                      className="text-xs font-semibold text-mint hover:underline"
                    >
                      Open page
                    </Link>
                  )}
                </div>
                {listing.status === 'active' && listing.share_url && (
                  <ListingSharePanel listing={listing} compact />
                )}
              </Card>
            ))}
          </div>
        </section>
      )}

      {showCreate && <CreateListingModal onClose={() => setShowCreate(false)} />}
      {buyTarget && <PurchaseModal listing={buyTarget} onClose={() => setBuyTarget(null)} />}
    </div>
  )
}

Marketplace.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function CreateListingModal({ onClose }: { onClose: () => void }) {
  const {
    register,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<CreateListingValues>({
    resolver: zodResolver(createListingSchema),
    defaultValues: {
      title: '',
      description: '',
      delivery_payload: '',
    },
  })

  const price = watch('price')
  const priceMinor = typeof price === 'number' && !Number.isNaN(price) ? Math.round(price * 100) : null

  const submit = (values: CreateListingValues) => {
    router.post(
      '/marketplace/listings',
      {
        title: values.title,
        description: values.description,
        delivery_payload: values.delivery_payload,
        price: Math.round(values.price * 100),
      },
      { preserveScroll: true, onSuccess: () => onClose() },
    )
  }

  return (
    <Modal title="Sell a digital item" onClose={onClose} wide>
      <div className="mb-4 rounded-xl border border-mint/25 bg-mint/[0.06] p-3">
        <p className="flex items-center gap-2 text-sm font-semibold text-text">
          <ShieldIcon size={16} className="shrink-0 text-mint" />
          How selling works
        </p>
        <ol className="mt-2 space-y-1.5 text-xs text-muted">
          <li>
            <span className="font-semibold text-text">1. Someone buys</span> — payment leaves their wallet; you see it as{' '}
            <span className="font-semibold text-text">pending</span> (not spendable yet).
          </li>
          <li>
            <span className="font-semibold text-text">2. You deliver</span> — mark the order delivered when ready. The
            buyer then sees your delivery content.
          </li>
          <li>
            <span className="font-semibold text-text">3. They confirm</span> — funds release to you when the item matches
            your listing. Disputes use fair rules if something is wrong.
          </li>
          <li>
            <span className="font-semibold text-text">4. Share the link</span> — after publishing, copy your listing
            link into WhatsApp or social DMs so buyers can pay with protection.
          </li>
        </ol>
      </div>

      <form onSubmit={handleSubmit(submit)} className="space-y-4">
        <fieldset className="space-y-3">
          <legend className="text-xs font-semibold uppercase tracking-wide text-mint">What buyers see before paying</legend>

          <RhfField
            label="Title"
            placeholder="e.g. Lightroom preset pack — 50 filters"
            hint="Short and specific. Say exactly what the digital item is."
            error={errors.title?.message}
            {...register('title')}
          />

          <label className="block">
            <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Description</span>
            <textarea
              className="field w-full px-4 py-3 text-sm"
              rows={3}
              placeholder="Explain features, format, and what is included. Do not put license keys or private links here."
              {...register('description')}
            />
            <span className="mt-1 block text-xs text-muted">
              Shown on the listing page. Keys, passwords, and download links go in delivery content below — not here.
            </span>
            {errors.description && <p className="mt-1 text-sm text-danger">{errors.description.message}</p>}
          </label>

          <div className="grid gap-3 sm:grid-cols-2">
            <RhfField
              label="Price (NGN)"
              type="number"
              step="0.01"
              min="1"
              placeholder="2500"
              hint="Whole Naira amount. Buyer pays this; you receive it after they confirm."
              error={errors.price?.message}
              {...register('price')}
            />
            <div className="rounded-xl border border-line bg-surface-2 px-3 py-2.5">
              <p className="text-[10px] font-semibold uppercase tracking-wide text-muted">Buyer pays</p>
              <p className="mt-1 font-num text-lg font-bold text-mint">
                {priceMinor && priceMinor >= 100 ? ngn(priceMinor) : '—'}
              </p>
              <p className="mt-1 text-[11px] text-muted">Pending until buyer confirms delivery.</p>
            </div>
          </div>
        </fieldset>

        <fieldset className="space-y-3">
          <legend className="text-xs font-semibold uppercase tracking-wide text-mint">
            Private until you deliver
          </legend>

          <label className="block">
            <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">
              Delivery content
            </span>
            <textarea
              className="field w-full px-4 py-3 font-mono text-sm"
              rows={4}
              placeholder={'Paste exactly what the buyer should receive, for example:\n\nKEY: XXXX-YYYY-ZZZZ\nor https://your-download-link.com/file'}
              {...register('delivery_payload')}
            />
            <span className="mt-1 block text-xs text-muted">
              Hidden until you tap <strong className="font-semibold text-text">Mark delivered</strong> on the order.
              Must match your description — buyers can dispute if it does not.
            </span>
            {errors.delivery_payload && <p className="mt-1 text-sm text-danger">{errors.delivery_payload.message}</p>}
          </label>

          <div className="rounded-lg border border-line/80 bg-surface-2 px-3 py-2 text-[11px] text-muted">
            <p className="font-semibold text-text">Good examples</p>
            <ul className="mt-1 list-inside list-disc space-y-0.5">
              <li>Single license key or activation code</li>
              <li>Direct download URL (Google Drive, Dropbox, etc.)</li>
              <li>Account email + temporary password for access</li>
            </ul>
          </div>
        </fieldset>

        <label className="flex items-start gap-2.5 rounded-xl border border-line bg-surface px-3 py-3 text-sm">
          <input type="checkbox" className="mt-0.5" {...register('listing_accurate')} />
          <span>
            <span className="font-medium text-text">My listing is accurate</span>
            <span className="mt-0.5 block text-xs text-muted">
              The description and delivery content are honest, complete, and ready to hand over after purchase.
            </span>
          </span>
        </label>
        {errors.listing_accurate && <p className="text-sm text-danger">{errors.listing_accurate.message}</p>}

        <Button type="submit" loading={isSubmitting} className="w-full">
          Publish listing
        </Button>
      </form>
    </Modal>
  )
}

function PurchaseModal({ listing, onClose }: { onClose: () => void }) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<PurchaseListingValues>({
    resolver: zodResolver(purchaseListingSchema),
    defaultValues: { pin: '' },
  })

  const submit = (values: PurchaseListingValues) => {
    router.post(`/marketplace/listings/${listing.id}/purchase`, values, {
      headers: deviceHeaders(),
      onSuccess: () => onClose(),
    })
  }

  return (
    <Modal title={`Buy: ${listing.title}`} onClose={onClose}>
      <p className="text-sm text-muted">
        {ngn(listing.price)} leaves your available balance immediately. The seller sees it as pending until you
        confirm it matches the listing.
      </p>
      <form onSubmit={handleSubmit(submit)} className="mt-4 space-y-3">
        <RhfField
          label="Transaction PIN"
          type="password"
          inputMode="numeric"
          autoComplete="off"
          error={errors.pin?.message}
          {...register('pin')}
        />
        <Button type="submit" loading={isSubmitting} className="w-full">
          Pay with protection
        </Button>
      </form>
    </Modal>
  )
}
