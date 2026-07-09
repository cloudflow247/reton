import type { ReactNode } from 'react'
import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { useForm } from 'react-hook-form'
import { AppShell } from '@/components/AppShell'
import { DigitalOrderEscrowCard } from '@/components/DigitalOrderEscrowCard'
import { ListingSharePanel } from '@/components/ListingSharePanel'
import { RhfField } from '@/components/forms/RhfField'
import { Button, Card, Modal, Pill } from '@/components/ui'
import { BankIcon, BoltIcon, GiftIcon, GridIcon, ShareIcon, ShieldIcon, SparkleIcon } from '@/components/icons'
import { ngn } from '@/lib/format'
import { parseListingRef } from '@/lib/marketplace'
import { createListingSchema, type CreateListingValues } from '@/lib/schemas/marketplace'
import type { DigitalListing, DigitalOrder, PageProps } from '@/types'

type MarketplaceProps = PageProps<{
  myListings: DigitalListing[]
  orders: DigitalOrder[]
}>

type Tab = 'open' | 'orders' | 'listings'

const list = { hidden: {}, show: { transition: { staggerChildren: 0.06, delayChildren: 0.04 } } }
const fadeUp = {
  hidden: { opacity: 0, y: 14 },
  show: { opacity: 1, y: 0, transition: { type: 'spring', stiffness: 320, damping: 28 } },
}
const tabSpring = { type: 'spring', stiffness: 400, damping: 34 } as const

const flowSteps = [
  { n: '1', title: 'Share', desc: 'Send your buyer the link or item code' },
  { n: '2', title: 'Pay', desc: 'Buyer locks terms — escrow holds funds' },
  { n: '3', title: 'Verify', desc: 'Giglogistics hub checks physical items' },
  { n: '4', title: 'Deliver', desc: 'Confirm or dispute with fair rules' },
]

export default function Marketplace() {
  const { myListings, orders, flash } = usePage<MarketplaceProps>().props
  const [showCreate, setShowCreate] = useState(false)
  const [tab, setTab] = useState<Tab>('open')
  const [itemRef, setItemRef] = useState('')
  const [openError, setOpenError] = useState<string | null>(null)

  const activeOrders = orders.filter((o) => o.status !== 'completed' && o.status !== 'refunded')
  const pastOrders = orders.filter((o) => o.status === 'completed' || o.status === 'refunded')

  const tabs: { id: Tab; label: string; count?: number }[] = [
    { id: 'open', label: 'Open item' },
    { id: 'orders', label: 'Orders', count: activeOrders.length || undefined },
    { id: 'listings', label: 'My listings', count: myListings.length || undefined },
  ]

  function openItem() {
    const ref = parseListingRef(itemRef)
    if (!ref) {
      setOpenError('Paste a seller link or item code (e.g. RTN-7K3M9P).')
      return
    }
    setOpenError(null)
    router.visit(`/l/${encodeURIComponent(ref)}`)
  }

  return (
    <motion.div variants={list} initial="hidden" animate="show" className="space-y-5 pb-6">
      <Head title="Shop" />

      {/* Hero */}
      <motion.section variants={fadeUp} className="mesh relative overflow-hidden rounded-[24px] p-5 text-white sm:p-7">
        <div aria-hidden className="pointer-events-none absolute inset-0">
          <div className="blob absolute -left-10 -top-12 h-40 w-40 bg-white/10 blur-2xl" />
          <div className="blob-slow absolute -bottom-8 right-0 h-48 w-48 bg-emerald-300/15 blur-3xl" />
        </div>
        <div className="relative flex flex-wrap items-start justify-between gap-4">
          <div className="max-w-md">
            <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-white/70">
              <ShieldIcon size={14} /> Protected marketplace
            </p>
            <h1 className="mt-2 font-display text-2xl font-bold tracking-tight sm:text-3xl">Shop with confidence</h1>
            <p className="mt-2 text-sm leading-relaxed text-white/75">
              Listings are private — buyers need your share link or item code. Escrow and Giglogistics verification on
              every sale.
            </p>
          </div>
          <Button
            onClick={() => setShowCreate(true)}
            className="shrink-0 border border-white/20 bg-white/15 text-white backdrop-blur hover:bg-white/25"
          >
            <SparkleIcon size={16} /> Sell an item
          </Button>
        </div>
        <div className="relative mt-5 grid gap-2 sm:grid-cols-4">
          {flowSteps.map((s) => (
            <div
              key={s.n}
              className="rounded-xl border border-white/15 bg-white/10 px-3 py-2.5 backdrop-blur-sm transition hover:bg-white/15"
            >
              <p className="text-[10px] font-bold text-white/60">Step {s.n}</p>
              <p className="text-xs font-semibold">{s.title}</p>
              <p className="mt-0.5 text-[10px] leading-snug text-white/65">{s.desc}</p>
            </div>
          ))}
        </div>
      </motion.section>

      {flash.success && (
        <motion.p variants={fadeUp} className="rounded-xl border border-mint/25 bg-mint/5 px-4 py-2.5 text-sm text-mint">
          {flash.success}
        </motion.p>
      )}
      {flash.error && (
        <motion.p variants={fadeUp} className="rounded-xl border border-danger/25 bg-danger/5 px-4 py-2.5 text-sm text-danger">
          {flash.error}
        </motion.p>
      )}

      {/* Tabs */}
      <motion.nav variants={fadeUp} className="flex gap-1 rounded-2xl border border-line bg-surface-2/80 p-1">
        {tabs.map((t) => {
          const on = tab === t.id
          return (
            <button
              key={t.id}
              type="button"
              onClick={() => setTab(t.id)}
              className="relative flex flex-1 items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold transition"
            >
              {on && (
                <motion.span layoutId="shop-tab" className="absolute inset-0 rounded-xl bg-surface shadow-sm" transition={tabSpring} />
              )}
              <span className={`relative z-10 ${on ? 'text-mint' : 'text-muted'}`}>{t.label}</span>
              {t.count !== undefined && t.count > 0 && (
                <span
                  className={`relative z-10 rounded-full px-1.5 py-0.5 text-[10px] font-bold ${
                    on ? 'bg-mint/15 text-mint' : 'bg-line text-muted'
                  }`}
                >
                  {t.count}
                </span>
              )}
            </button>
          )
        })}
      </motion.nav>

      <AnimatePresence mode="wait">
        {tab === 'open' && (
          <motion.div
            key="open"
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -6 }}
            transition={{ duration: 0.2 }}
            className="space-y-4"
          >
            <Card className="space-y-4 p-5">
              <div className="flex items-start gap-3">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-mint/10 text-mint">
                  <ShareIcon size={22} />
                </span>
                <div>
                  <h2 className="font-semibold text-text">Open a shared item</h2>
                  <p className="mt-1 text-sm text-muted">
                    Sellers share a link or a short code like <span className="font-mono text-text">RTN-7K3M9P</span>.
                    There is no public catalog — you can only shop what was shared with you.
                  </p>
                </div>
              </div>

              <div className="space-y-2">
                <label htmlFor="item-ref" className="text-xs font-semibold uppercase tracking-wide text-muted">
                  Item link or code
                </label>
                <input
                  id="item-ref"
                  value={itemRef}
                  onChange={(e) => {
                    setItemRef(e.target.value)
                    if (openError) setOpenError(null)
                  }}
                  onKeyDown={(e) => e.key === 'Enter' && openItem()}
                  placeholder="https://retonpay.com/l/RTN-7K3M9P or RTN-7K3M9P"
                  className="field w-full px-4 py-3 text-sm"
                  autoComplete="off"
                />
                {openError && <p className="text-sm text-danger">{openError}</p>}
              </div>

              <Button type="button" className="w-full" onClick={openItem}>
                Open item
              </Button>
            </Card>

            <Card className="flex flex-col items-center gap-3 p-10 text-center">
              <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-2 text-muted">
                <GiftIcon size={28} />
              </span>
              <p className="font-medium text-text">Selling something?</p>
              <p className="max-w-sm text-sm text-muted">
                Publish a listing, then share the link or code with your buyer on WhatsApp, Instagram, or in person.
              </p>
              <Button onClick={() => setShowCreate(true)} className="mt-1">
                Sell an item
              </Button>
            </Card>
          </motion.div>
        )}

        {tab === 'orders' && (
          <motion.div
            key="orders"
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -6 }}
            className="space-y-5"
          >
            {activeOrders.length > 0 ? (
              <section className="space-y-3">
                <h2 className="flex items-center gap-2 text-sm font-semibold">
                  <BoltIcon size={16} className="text-amber" /> In progress
                </h2>
                <div className="space-y-3">
                  {activeOrders.map((order) => (
                    <motion.div key={order.id} layout initial={{ opacity: 0 }} animate={{ opacity: 1 }}>
                      <DigitalOrderEscrowCard order={order} />
                    </motion.div>
                  ))}
                </div>
              </section>
            ) : (
              <EmptyBlock
                icon={ShieldIcon}
                title="No active orders"
                desc="When you buy or sell, track hub verification and delivery here."
                action={{ label: 'Open shared item', onClick: () => setTab('open') }}
              />
            )}
            {pastOrders.length > 0 && (
              <section className="space-y-3">
                <h2 className="text-sm font-semibold text-muted">Completed</h2>
                <div className="space-y-2">
                  {pastOrders.map((order) => (
                    <DigitalOrderEscrowCard key={order.id} order={order} compact />
                  ))}
                </div>
              </section>
            )}
          </motion.div>
        )}

        {tab === 'listings' && (
          <motion.div
            key="listings"
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -6 }}
            className="space-y-4"
          >
            {myListings.length === 0 ? (
              <EmptyBlock
                icon={GridIcon}
                title="You haven't listed anything"
                desc="Publish a digital or physical item — Reton verifies descriptions and protects every sale."
                action={{ label: 'Create listing', onClick: () => setShowCreate(true) }}
              />
            ) : (
              <div className="space-y-3">
                {myListings.map((listing) => (
                  <motion.div key={listing.id} layout whileHover={{ scale: 1.005 }} className="elevate">
                    <Card className="space-y-3 p-4">
                      <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                          <div className="flex items-center gap-2">
                            <p className="font-semibold">{listing.title}</p>
                            <Pill tone={listing.item_type === 'physical' ? 'mint' : 'muted'}>
                              {listing.item_type === 'physical' ? 'Physical' : 'Digital'}
                            </Pill>
                          </div>
                          <p className="mt-1 text-sm text-muted">
                            {ngn(listing.price)} · <span className="capitalize">{listing.status}</span>
                          </p>
                        </div>
                        {listing.status === 'active' && (
                          <Link
                            href={`/l/${listing.item_code ?? listing.id}`}
                            className="text-xs font-semibold text-mint hover:underline"
                          >
                            Open page →
                          </Link>
                        )}
                      </div>
                      {listing.status === 'active' && listing.share_url && (
                        <ListingSharePanel listing={listing} compact />
                      )}
                    </Card>
                  </motion.div>
                ))}
              </div>
            )}
          </motion.div>
        )}
      </AnimatePresence>

      {showCreate && <CreateListingModal onClose={() => setShowCreate(false)} />}
    </motion.div>
  )
}

function EmptyBlock({
  icon: Icon,
  title,
  desc,
  action,
}: {
  icon: (p: { size?: number }) => JSX.Element
  title: string
  desc: string
  action: { label: string; onClick: () => void }
}) {
  return (
    <Card className="flex flex-col items-center gap-3 p-12 text-center">
      <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-2 text-muted">
        <Icon size={28} />
      </span>
      <p className="font-semibold">{title}</p>
      <p className="max-w-sm text-sm text-muted">{desc}</p>
      <Button variant="secondary" onClick={action.onClick}>
        {action.label}
      </Button>
    </Card>
  )
}

Marketplace.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function CreateListingModal({ onClose }: { onClose: () => void }) {
  const [itemType, setItemType] = useState<'digital' | 'physical'>('digital')

  const {
    register,
    handleSubmit,
    watch,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<CreateListingValues>({
    resolver: zodResolver(createListingSchema),
    defaultValues: {
      item_type: 'digital',
      title: '',
      description: '',
      delivery_payload: '',
      listing_accurate: false,
    },
  })

  const switchType = (type: 'digital' | 'physical') => {
    setItemType(type)
    reset(
      type === 'digital'
        ? { item_type: 'digital', title: '', description: '', delivery_payload: '', listing_accurate: false }
        : {
            item_type: 'physical',
            title: '',
            description: '',
            condition: 'good',
            weight_grams: 500,
            spec_brand: '',
            spec_detail: '',
            handling_notes: '',
            listing_accurate: false,
          },
    )
  }

  const price = watch('price')
  const priceMinor = typeof price === 'number' && !Number.isNaN(price) ? Math.round(price * 100) : null

  const submit = (values: CreateListingValues) => {
    const payload =
      values.item_type === 'digital'
        ? {
            item_type: 'digital',
            title: values.title,
            description: values.description,
            delivery_payload: values.delivery_payload,
            price: Math.round(values.price * 100),
            listing_accurate: values.listing_accurate,
          }
        : {
            item_type: 'physical',
            title: values.title,
            description: values.description,
            condition: values.condition,
            weight_grams: values.weight_grams,
            spec_brand: values.spec_brand,
            spec_detail: values.spec_detail,
            handling_notes: values.handling_notes ?? '',
            price: Math.round(values.price * 100),
            listing_accurate: values.listing_accurate,
          }

    router.post('/marketplace/listings', payload, { preserveScroll: true, onSuccess: () => onClose() })
  }

  return (
    <Modal title="Sell an item" onClose={onClose} wide>
      <div className="mb-4 flex gap-2 rounded-xl bg-surface-2 p-1">
        {(['digital', 'physical'] as const).map((type) => (
          <button
            key={type}
            type="button"
            onClick={() => switchType(type)}
            className={`flex-1 rounded-lg py-2 text-xs font-semibold capitalize transition ${
              itemType === type ? 'bg-surface text-mint shadow-sm' : 'text-muted hover:text-text'
            }`}
          >
            {type === 'physical' ? (
              <span className="inline-flex items-center justify-center gap-1">
                <BankIcon size={14} /> Physical
              </span>
            ) : (
              'Digital'
            )}
          </button>
        ))}
      </div>

      <input type="hidden" value={itemType} {...register('item_type')} />

      <div className="mb-4 rounded-xl border border-mint/25 bg-mint/[0.06] p-3">
        <p className="flex items-center gap-2 text-sm font-semibold text-text">
          <ShieldIcon size={16} className="shrink-0 text-mint" />
          {itemType === 'physical' ? 'Hub-verified physical sale' : 'Instant digital delivery'}
        </p>
        <p className="mt-2 text-xs leading-relaxed text-muted">
          {itemType === 'physical'
            ? 'Buyers lock the description before paying. You drop off at Giglogistics for verification, then delivery.'
            : 'Escrow holds payment until you deliver and the buyer confirms.'}
        </p>
      </div>

      <form onSubmit={handleSubmit(submit)} className="space-y-4">
        <fieldset className="space-y-3">
          <legend className="text-xs font-semibold uppercase tracking-wide text-mint">Listing details</legend>

          <RhfField
            label="Title"
            placeholder="e.g. Lightroom preset pack — 50 filters"
            error={errors.title?.message}
            {...register('title')}
          />

          <label className="block">
            <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Description</span>
            <textarea
              className="field w-full px-4 py-3 text-sm"
              rows={3}
              placeholder="What the buyer gets — be specific and honest."
              {...register('description')}
            />
            {errors.description && <p className="mt-1 text-sm text-danger">{errors.description.message}</p>}
          </label>

          <div className="grid gap-3 sm:grid-cols-2">
            <RhfField label="Price (NGN)" type="number" step="0.01" min="1" error={errors.price?.message} {...register('price')} />
            <div className="rounded-xl border border-line bg-surface-2 px-3 py-2.5">
              <p className="text-[10px] font-semibold uppercase tracking-wide text-muted">Buyer pays</p>
              <p className="mt-1 font-num text-lg font-bold text-mint">
                {priceMinor && priceMinor >= 100 ? ngn(priceMinor) : '—'}
              </p>
            </div>
          </div>
        </fieldset>

        {itemType === 'digital' ? (
          <fieldset className="space-y-3">
            <legend className="text-xs font-semibold uppercase tracking-wide text-mint">Delivery (private)</legend>
            <textarea
              className="field w-full px-4 py-3 font-mono text-sm"
              rows={4}
              placeholder="License key, download link, or access code"
              {...register('delivery_payload')}
            />
            {errors.delivery_payload && <p className="text-sm text-danger">{errors.delivery_payload.message}</p>}
          </fieldset>
        ) : (
          <fieldset className="space-y-3">
            <legend className="text-xs font-semibold uppercase tracking-wide text-mint">Physical details</legend>
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="block">
                <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Condition</span>
                <select className="field w-full px-4 py-3 text-sm" {...register('condition')}>
                  <option value="new">Brand new</option>
                  <option value="like_new">Like new</option>
                  <option value="good">Good</option>
                  <option value="fair">Fair</option>
                </select>
              </label>
              <RhfField
                label="Weight (grams)"
                type="number"
                min="100"
                error={'weight_grams' in errors ? errors.weight_grams?.message : undefined}
                {...register('weight_grams')}
              />
            </div>
            <RhfField label="Brand / maker" error={'spec_brand' in errors ? errors.spec_brand?.message : undefined} {...register('spec_brand')} />
            <RhfField label="Size, colour, or model" error={'spec_detail' in errors ? errors.spec_detail?.message : undefined} {...register('spec_detail')} />
            <textarea className="field w-full px-4 py-3 text-sm" rows={2} placeholder="Handling notes (optional)" {...register('handling_notes')} />
          </fieldset>
        )}

        <label className="flex items-start gap-2.5 rounded-xl border border-line bg-surface px-3 py-3 text-sm">
          <input type="checkbox" className="mt-0.5" {...register('listing_accurate')} />
          <span>
            <span className="font-medium text-text">My listing is accurate</span>
            <span className="mt-0.5 block text-xs text-muted">Honest description, ready to fulfil after purchase.</span>
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
