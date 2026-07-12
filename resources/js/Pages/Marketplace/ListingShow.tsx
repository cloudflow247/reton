import type { ReactNode } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { useForm } from 'react-hook-form'
import { AppShell } from '@/components/AppShell'
import { RhfField } from '@/components/forms/RhfField'
import { ListingSharePanel } from '@/components/ListingSharePanel'
import { PublicLayout } from '@/components/PublicLayout'
import { Button, Card, Pill } from '@/components/ui'
import { ShieldIcon } from '@/components/icons'
import { deviceHeaders } from '@/lib/device'
import { ngn } from '@/lib/format'
import {
  purchaseDigitalListingSchema,
  purchasePhysicalListingSchema,
  type PurchaseListingValues,
  type PurchasePhysicalListingValues,
} from '@/lib/schemas/marketplace'
import type { DigitalListing, PageProps } from '@/types'

type ListingShowProps = PageProps<{
  listing: DigitalListing
}>

export default function ListingShow() {
  const { listing, auth, flash } = usePage<ListingShowProps>().props
  const isPhysical = listing.item_type === 'physical'
  const listingPath = listing.item_code ? `/l/${listing.item_code}` : `/l/${listing.id}`
  const loginHref = `/login?redirect=${encodeURIComponent(listingPath)}`
  const registerHref = `/register?redirect=${encodeURIComponent(listingPath)}`

  const content = (
    <div className="mx-auto max-w-lg space-y-5 pb-8">
      <Head title={listing.title}>
        <meta head-key="description" name="description" content={listing.description} />
        {listing.share_url && (
          <>
            <link head-key="canonical" rel="canonical" href={listing.share_url} />
            <meta head-key="og:title" property="og:title" content={listing.title} />
            <meta head-key="og:description" property="og:description" content={listing.description} />
            <meta head-key="og:url" property="og:url" content={listing.share_url} />
            <meta head-key="og:type" property="og:type" content="product" />
          </>
        )}
      </Head>

      {flash.success && <p className="text-sm text-mint">{flash.success}</p>}
      {flash.error && <p className="text-sm text-danger">{flash.error}</p>}

      <Card className="p-5">
        <div className="flex flex-wrap items-center gap-2">
          <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-mint">
            <ShieldIcon size={14} /> {isPhysical ? 'Protected physical sale' : 'Protected digital sale'}
          </p>
          {isPhysical && <Pill tone="mint">Hub verified</Pill>}
        </div>
        <h1 className="mt-2 font-display text-2xl font-bold tracking-tight">{listing.title}</h1>
        <p className="mt-2 text-sm leading-relaxed text-muted">{listing.description}</p>

        {isPhysical && (
          <div className="mt-4 space-y-2 rounded-xl border border-line bg-surface-2 p-3 text-sm">
            {listing.condition_label && (
              <p>
                <span className="text-muted">Condition:</span> {listing.condition_label}
              </p>
            )}
            {listing.specs && (
              <ul className="space-y-1">
                {Object.entries(listing.specs).map(([k, v]) => (
                  <li key={k}>
                    <span className="capitalize text-muted">{k}:</span> {v}
                  </li>
                ))}
              </ul>
            )}
            {listing.verification_score !== undefined && (
              <p className="text-xs text-muted">
                Listing clarity score {listing.verification_score}/100 — detailed enough for fair hub inspection.
              </p>
            )}
          </div>
        )}

        <p className="mt-4 font-num text-2xl font-bold text-mint">{ngn(listing.price)}</p>
        <p className="mt-1 text-xs text-muted">Sold by {listing.seller_name ?? 'Seller'}</p>
        {listing.item_code && listing.status === 'active' && (
          <p className="mt-2 inline-flex items-center gap-2 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-xs">
            <span className="text-muted">Item code</span>
            <span className="font-mono font-bold tracking-wider text-text">{listing.item_code}</span>
          </p>
        )}

        {isPhysical && listing.status === 'active' && (
          <p className="mt-3 rounded-lg border border-mint/25 bg-mint/5 px-3 py-2 text-xs text-muted">
            After you pay, the seller takes the item to a verification hub. Reton locks this description — the partner
            verifies the physical item matches before it ships to you.
          </p>
        )}

        {listing.status !== 'active' && (
          <p className="mt-3 rounded-lg border border-amber/30 bg-amber/10 px-3 py-2 text-xs text-amber">
            This listing is no longer available for purchase.
          </p>
        )}
      </Card>

      {listing.is_owner && listing.status === 'active' && <ListingSharePanel listing={listing} />}

      {!listing.is_owner && listing.can_purchase && (
        <Card className="p-5">
          <p className="text-sm font-semibold">Buy with protection</p>
          <p className="mt-1 text-xs text-muted">
            {isPhysical
              ? 'Payment is held until the hub verifies the item, delivers to you, and you confirm — or you are auto-refunded if verification fails or delivery is missed.'
              : 'Payment is held until the seller delivers and you confirm — or you are auto-refunded if they miss the deadline.'}
          </p>
          <PurchaseForm listing={listing} className="mt-4" />
        </Card>
      )}

      {!listing.is_owner && !auth.user && listing.status === 'active' && (
        <Card className="space-y-3 p-5">
          <p className="text-sm font-semibold">Sign in to buy</p>
          <p className="text-xs text-muted">Create a free Reton wallet or sign in, then pay with protection.</p>
          <div className="flex flex-wrap gap-2">
            <Link href={loginHref} className="btn bg-mint px-5 py-2.5 text-sm text-white hover:bg-mint-strong">
              Sign in
            </Link>
            <Link href={registerHref} className="btn border border-line bg-surface px-5 py-2.5 text-sm hover:border-mint/40">
              Create account
            </Link>
          </div>
        </Card>
      )}

      {!listing.is_owner && auth.user && !listing.can_purchase && listing.status === 'active' && (
        <Card className="p-5 text-sm text-muted">You cannot purchase your own listing.</Card>
      )}

      {auth.user && (
        <p className="text-center text-xs text-muted">
          <Link href="/marketplace" className="text-mint hover:underline">
            Back to marketplace
          </Link>
        </p>
      )}
    </div>
  )

  if (auth.user) {
    return <AppShell>{content}</AppShell>
  }

  return <PublicLayout>{content}</PublicLayout>
}

function PurchaseForm({ listing, className }: { listing: DigitalListing; className?: string }) {
  const isPhysical = listing.item_type === 'physical'
  const schema = isPhysical ? purchasePhysicalListingSchema : purchaseDigitalListingSchema

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm({
    resolver: zodResolver(schema),
    defaultValues: isPhysical
      ? {
          pin: '',
          buyer_accepts_description: false,
          shipping_line1: '',
          shipping_city: '',
          shipping_state: '',
          shipping_phone: '',
        }
      : { pin: '' },
  })

  const submit = (values: PurchasePhysicalListingValues | PurchaseListingValues) => {
    router.post(`/marketplace/listings/${listing.id}/purchase`, values, {
      headers: deviceHeaders(),
    })
  }

  return (
    <form onSubmit={handleSubmit(submit)} className={`space-y-3 ${className ?? ''}`}>
      {isPhysical && (
        <>
          <div className="rounded-xl border border-line bg-surface-2 p-3 text-xs text-muted">
            <p className="font-semibold text-text">Order description (locked at purchase)</p>
            <p className="mt-1">{listing.description}</p>
          </div>
          <RhfField label="Street address" error={errors.shipping_line1?.message as string} {...register('shipping_line1')} />
          <div className="grid gap-3 sm:grid-cols-2">
            <RhfField label="City" error={errors.shipping_city?.message as string} {...register('shipping_city')} />
            <RhfField label="State" error={errors.shipping_state?.message as string} {...register('shipping_state')} />
          </div>
          <RhfField label="Phone" error={errors.shipping_phone?.message as string} {...register('shipping_phone')} />
          <label className="flex items-start gap-2 rounded-xl border border-line bg-surface-2 p-3 text-sm">
            <input type="checkbox" className="mt-1" {...register('buyer_accepts_description')} />
            <span>I have read and accept this item description — it will be locked for fair dispute resolution.</span>
          </label>
          {errors.buyer_accepts_description && (
            <p className="text-sm text-danger">{errors.buyer_accepts_description.message as string}</p>
          )}
        </>
      )}
      <RhfField
        label="Transaction PIN"
        type="password"
        inputMode="numeric"
        autoComplete="off"
        error={errors.pin?.message as string}
        {...register('pin')}
      />
      <Button type="submit" loading={isSubmitting} className="w-full">
        Pay {ngn(listing.price)} with protection
      </Button>
    </form>
  )
}

ListingShow.layout = (page: ReactNode) => page
