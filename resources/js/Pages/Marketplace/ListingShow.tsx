import type { ReactNode } from 'react'
import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { useForm } from 'react-hook-form'
import { AppShell } from '@/components/AppShell'
import { RhfField } from '@/components/forms/RhfField'
import { ListingSharePanel } from '@/components/ListingSharePanel'
import { PublicLayout } from '@/components/PublicLayout'
import { Button, Card } from '@/components/ui'
import { ShieldIcon } from '@/components/icons'
import { deviceHeaders } from '@/lib/device'
import { ngn } from '@/lib/format'
import { purchaseListingSchema, type PurchaseListingValues } from '@/lib/schemas/marketplace'
import type { DigitalListing, PageProps } from '@/types'

type ListingShowProps = PageProps<{
  listing: DigitalListing
}>

export default function ListingShow() {
  const { listing, auth, flash } = usePage<ListingShowProps>().props
  const listingPath = `/l/${listing.id}`
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
        <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-mint">
          <ShieldIcon size={14} /> Protected digital sale
        </p>
        <h1 className="mt-2 font-display text-2xl font-bold tracking-tight">{listing.title}</h1>
        <p className="mt-2 text-sm leading-relaxed text-muted">{listing.description}</p>
        <p className="mt-4 font-num text-2xl font-bold text-mint">{ngn(listing.price)}</p>
        <p className="mt-1 text-xs text-muted">Sold by {listing.seller_name ?? 'Seller'}</p>

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
            Payment is held until the seller delivers and you confirm — or you&apos;re auto-refunded if they miss the
            deadline.
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
    })
  }

  return (
    <form onSubmit={handleSubmit(submit)} className={className}>
      <RhfField
        label="Transaction PIN"
        type="password"
        inputMode="numeric"
        autoComplete="off"
        error={errors.pin?.message}
        {...register('pin')}
      />
      <Button type="submit" loading={isSubmitting} className="mt-3 w-full">
        Pay {ngn(listing.price)} with protection
      </Button>
    </form>
  )
}

ListingShow.layout = (page: ReactNode) => page
