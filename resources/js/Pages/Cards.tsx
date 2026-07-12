import type { FormEvent, ReactNode } from 'react'
import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { Head, useForm, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { FormPanel, InfoStrip, MorphTabs, Page, PageHero, PinField, SectionLabel, PAGE_SPRING, pageItem, pageList } from '@/components/page-kit'
import { Button, Pill } from '@/components/ui'
import {
  BankIcon,
  CardIcon,
  CheckIcon,
  ContactlessIcon,
  CopyIcon,
  EyeIcon,
  EyeOffIcon,
  LockIcon,
  PlusIcon,
  ShieldIcon,
  SnowIcon,
} from '@/components/icons'
import { deviceHeaders } from '@/lib/device'
import { money, ngn, toMinor, usd } from '@/lib/format'
import type { PageProps } from '@/types'

type BillingAddress = {
  line1: string
  city: string
  state: string
  postcode: string
  country: string
  country_code: string
}

type VirtualCardView = {
  id: string
  status: string
  scheme: string
  brand: string
  card_type: string
  currency: string
  pan_masked: string
  pan_last4: string
  expiry_display: string
  name_on_card: string
  is_blocked: boolean
  card_balance_minor: number | null
}

type CardCurrency = 'NGN' | 'USD'

type Props = PageProps<{
  cards: Record<CardCurrency, VirtualCardView | null>
  cardsReady: boolean
  cardsDriver: string
  fx: { usd_ngn_rate: number; spread_bps: number }
  minFunding: Record<string, number>
}>

type Revealed = {
  pan: string
  cvv: string
  cvv2: string | null
  expiry: string
  name_on_card: string
  currency: string
  brand: string
  card_type: string
  billing_address: BillingAddress
}

const CARD_TABS: { id: CardCurrency; label: string }[] = [
  { id: 'NGN', label: 'Naira' },
  { id: 'USD', label: 'Dollar' },
]

export default function Cards({ cards, cardsReady, cardsDriver, fx, minFunding }: Props) {
  const { auth, flash } = usePage<Props>().props
  const wallets = auth.wallets
  const ngnWallet = wallets.find((w) => w.currency === 'NGN') ?? wallets[0]
  const usdWallet = wallets.find((w) => w.currency === 'USD')

  const [activeCurrency, setActiveCurrency] = useState<CardCurrency>('NGN')
  const card = cards[activeCurrency]

  const [revealed, setRevealed] = useState<Revealed | null>(null)
  const [pinOpen, setPinOpen] = useState(false)
  const [fundOpen, setFundOpen] = useState(false)
  const [pinMode, setPinMode] = useState<'reveal' | 'freeze'>('reveal')
  const [pinValue, setPinValue] = useState('')
  const [pinError, setPinError] = useState('')
  const [pinLoading, setPinLoading] = useState(false)
  const [copiedField, setCopiedField] = useState<string | null>(null)
  const [fxPreview, setFxPreview] = useState<{ source_amount_minor: number; source_currency: string } | null>(null)

  const issueForm = useForm({
    wallet_id: ngnWallet?.id ?? '',
    currency: 'NGN' as CardCurrency,
    pin: '',
  })

  const fundForm = useForm({
    wallet_id: ngnWallet?.id ?? '',
    amount: '',
    pin: '',
  })

  const freezeForm = useForm({ pin: '', currency: 'NGN' as CardCurrency })

  useEffect(() => {
    setRevealed(null)
    issueForm.setData('currency', activeCurrency)
    freezeForm.setData('currency', activeCurrency)
    const defaultWallet = activeCurrency === 'USD' ? usdWallet ?? ngnWallet : ngnWallet
    if (defaultWallet) {
      fundForm.setData('wallet_id', defaultWallet.id)
      issueForm.setData('wallet_id', defaultWallet.id)
    }
  }, [activeCurrency])

  const blocked = card?.is_blocked ?? false
  const billing = revealed?.billing_address
  const isRevealed = revealed !== null
  const displayPan = isRevealed ? revealed.pan : `•••• •••• •••• ${card?.pan_last4 ?? '••••'}`
  const displayExp = isRevealed ? revealed.expiry : '••/••'
  const displayCvv = isRevealed ? revealed.cvv : '•••'
  const cardBalance = card?.card_balance_minor ?? null
  const minIssue = minFunding[activeCurrency] ?? (activeCurrency === 'USD' ? 300 : 100000)

  useEffect(() => {
    if (!fundOpen || !card) return
    const sourceWallet = wallets.find((w) => w.id === fundForm.data.wallet_id)
    const targetMinor = toMinor(fundForm.data.amount)
    if (!sourceWallet || targetMinor <= 0 || sourceWallet.currency === card.currency) {
      setFxPreview(null)
      return
    }
    const params = new URLSearchParams({
      source_currency: sourceWallet.currency,
      target_currency: card.currency,
      target_amount_minor: String(targetMinor),
    })
    fetch(`/cards/fund/quote?${params}`, { headers: { Accept: 'application/json', ...deviceHeaders() } })
      .then((r) => r.json())
      .then((data) => {
        if (data.source_amount_minor) {
          setFxPreview({ source_amount_minor: data.source_amount_minor, source_currency: data.source_currency })
        }
      })
      .catch(() => setFxPreview(null))
  }, [fundForm.data.amount, fundForm.data.wallet_id, fundOpen, card?.currency])

  function copyValue(field: string, value: string) {
    navigator.clipboard.writeText(value.replace(/\s/g, ''))
    setCopiedField(field)
    setTimeout(() => setCopiedField(null), 1400)
  }

  async function revealDetails(e: FormEvent) {
    e.preventDefault()
    setPinError('')
    setPinLoading(true)
    try {
      const res = await fetch('/cards/reveal', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
          ...deviceHeaders(),
        },
        body: JSON.stringify({ pin: pinValue, currency: activeCurrency }),
      })
      if (!res.ok) {
        const data = await res.json().catch(() => ({}))
        setPinError(data?.errors?.pin?.[0] ?? data?.message ?? 'Could not load card details.')
        return
      }
      setRevealed(await res.json())
      setPinOpen(false)
      setPinValue('')
    } finally {
      setPinLoading(false)
    }
  }

  function issueCard(e: FormEvent) {
    e.preventDefault()
    issueForm.post('/cards', { preserveScroll: true, onSuccess: () => issueForm.reset('pin') })
  }

  function submitFund(e: FormEvent) {
    e.preventDefault()
    if (!card) return
    fundForm.transform(() => ({
      wallet_id: fundForm.data.wallet_id,
      amount_minor: toMinor(fundForm.data.amount),
      pin: fundForm.data.pin,
    }))
    fundForm.post(`/cards/${card.id}/fund`, {
      preserveScroll: true,
      onSuccess: () => {
        fundForm.reset('pin', 'amount')
        setFundOpen(false)
      },
    })
  }

  function toggleFreeze() {
    if (!card) return
    freezeForm.setData('pin', pinValue)
    freezeForm.post(blocked ? '/cards/unfreeze' : '/cards/freeze', {
      preserveScroll: true,
      onSuccess: () => {
        freezeForm.reset('pin')
        setPinValue('')
        setPinOpen(false)
        setRevealed(null)
      },
      onError: () => setPinError(freezeForm.errors.pin ?? 'Could not update card status.'),
    })
  }

  const cardTone =
    activeCurrency === 'USD'
      ? 'from-[#1e1b4b] via-[#312e81] to-[#4c1d95]'
      : 'from-[#064e3b] via-[#047857] to-[#059669]'

  return (
    <Page narrow>
      <Head title="Cards" />
      <PageHero
        icon={CardIcon}
        title="Cards"
        subtitle="Virtual cards funded from your wallet"
        tone="violet"
      />

      <MorphTabs tabs={CARD_TABS} value={activeCurrency} onChange={setActiveCurrency} layoutId="cards-currency" />

      {flash.success && <InfoStrip tone="mint">{flash.success}</InfoStrip>}

      {!cardsReady && (
        <InfoStrip tone="amber">Virtual cards are not available yet. Check back soon or contact support.</InfoStrip>
      )}

      {!card ? (
        <FormPanel>
          <div className="flex items-start justify-between gap-3">
            <div>
              <h2 className="font-display text-lg font-bold">{activeCurrency} virtual card</h2>
              <p className="mt-1 text-sm text-muted">
                Minimum first load {money(minIssue, activeCurrency)}. Mastercard for global online payments.
              </p>
            </div>
            <Pill tone="muted">{activeCurrency}</Pill>
          </div>

          {activeCurrency === 'USD' && ngnWallet && !usdWallet && (
            <p className="rounded-xl border border-line bg-surface-2/80 px-3 py-2.5 text-xs text-muted">
              No USD wallet yet — we&apos;ll convert from your NGN balance at ₦{fx.usd_ngn_rate.toLocaleString()}/$.
            </p>
          )}

          {cardsDriver === 'fake' && (
            <p className="rounded-xl border border-dashed border-line px-3 py-2 text-xs text-muted">Demo environment — test cards only.</p>
          )}

          <form onSubmit={issueCard} className="space-y-4">
            {wallets.length > 1 && (
              <label className="block">
                <span className="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted">Fund from</span>
                <select
                  value={issueForm.data.wallet_id}
                  onChange={(e) => issueForm.setData('wallet_id', e.target.value)}
                  className="field w-full px-3 py-2.5 text-sm"
                >
                  {wallets.map((w) => (
                    <option key={w.id} value={w.id}>
                      {w.currency} · {money(w.available_balance, w.currency)} available
                    </option>
                  ))}
                </select>
              </label>
            )}
            <PinField label="Authorize with PIN" value={issueForm.data.pin} onChange={(v) => issueForm.setData('pin', v)} />
            {issueForm.errors.pin && <p className="text-sm text-danger">{issueForm.errors.pin}</p>}
            <Button type="submit" loading={issueForm.processing} disabled={!cardsReady || !ngnWallet} className="w-full py-3.5">
              Create {activeCurrency} card
            </Button>
          </form>
        </FormPanel>
      ) : (
        <motion.div layout className="space-y-4" variants={pageList} initial="hidden" animate="show">
          <motion.div
            layoutId={`card-face-${activeCurrency}`}
            whileHover={{ rotateX: 2, rotateY: -2 }}
            transition={PAGE_SPRING}
            style={{ transformPerspective: 1200 }}
            className={`relative flex h-[15.5rem] flex-col overflow-hidden rounded-[28px] bg-gradient-to-br p-6 text-white shadow-[0_32px_64px_-24px_rgba(0,0,0,0.45)] ${cardTone} ${
              blocked ? 'opacity-75 saturate-50' : ''
            }`}
          >
            <div aria-hidden className="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full bg-white/10 blur-3xl" />
            <div aria-hidden className="pointer-events-none absolute -bottom-28 -left-16 h-56 w-56 rounded-full bg-white/10 blur-3xl" />
            <div
              aria-hidden
              className="pointer-events-none absolute inset-0 bg-[linear-gradient(115deg,transparent_40%,rgba(255,255,255,0.08)_50%,transparent_60%)]"
            />

            <div className="relative flex items-start justify-between">
              <div>
                <span className="text-[10px] font-semibold uppercase tracking-[0.22em] text-white/55">Reton</span>
                <div className="mt-1 font-display text-xl font-bold tracking-tight">{card.brand}</div>
              </div>
              <div className="flex items-center gap-2">
                {blocked && (
                  <span className="rounded-full bg-amber/90 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-950">
                    Frozen
                  </span>
                )}
                <ContactlessIcon size={28} className="text-white/50" />
              </div>
            </div>

            <div className="relative mt-auto space-y-5">
              <motion.div
                layout
                className="flex items-center gap-2 font-num text-[1.35rem] tracking-[0.16em] text-white"
                key={isRevealed ? 'pan-open' : 'pan-closed'}
              >
                <AnimatePresence mode="wait">
                  <motion.span
                    key={displayPan}
                    initial={{ opacity: 0, filter: 'blur(6px)' }}
                    animate={{ opacity: 1, filter: 'blur(0px)' }}
                    exit={{ opacity: 0, filter: 'blur(6px)' }}
                    transition={{ duration: 0.25 }}
                    className="truncate"
                  >
                    {displayPan}
                  </motion.span>
                </AnimatePresence>
                {isRevealed && (
                  <button
                    type="button"
                    onClick={() => copyValue('pan', revealed.pan)}
                    className="shrink-0 text-white/60 transition hover:text-white"
                    aria-label="Copy card number"
                  >
                    {copiedField === 'pan' ? <CheckIcon size={16} /> : <CopyIcon size={16} />}
                  </button>
                )}
              </motion.div>

              <div className="flex items-end justify-between gap-3">
                <div className="min-w-0">
                  <div className="text-[10px] uppercase tracking-wider text-white/45">Name</div>
                  <div className="truncate text-sm font-semibold">{card.name_on_card}</div>
                </div>
                <div className="text-right">
                  <div className="text-[10px] uppercase tracking-wider text-white/45">Expires</div>
                  <div className="font-num text-sm font-medium">{displayExp}</div>
                </div>
                <div className="text-right">
                  <div className="text-[10px] uppercase tracking-wider text-white/45">CVV</div>
                  <div className="font-num text-sm font-medium">{displayCvv}</div>
                </div>
                <span className="rounded-lg bg-white/15 px-2.5 py-1 text-[11px] font-bold backdrop-blur">{card.currency}</span>
              </div>
            </div>
          </motion.div>

          <motion.div variants={pageItem} className="grid grid-cols-3 gap-2.5">
            <QuickAction
              Icon={isRevealed ? EyeOffIcon : EyeIcon}
              label={isRevealed ? 'Hide details' : 'Show details'}
              highlight={!isRevealed}
              onClick={() => {
                if (isRevealed) setRevealed(null)
                else {
                  setPinMode('reveal')
                  setPinOpen(true)
                }
              }}
            />
            <QuickAction
              Icon={SnowIcon}
              label={blocked ? 'Unfreeze' : 'Freeze'}
              active={blocked}
              onClick={() => {
                setPinMode('freeze')
                setPinOpen(true)
              }}
            />
            <QuickAction Icon={PlusIcon} label="Fund" onClick={() => setFundOpen(true)} />
          </motion.div>

          <motion.div variants={pageItem} className="card overflow-hidden p-0">
            <div className="flex items-center justify-between border-b border-line px-5 py-4">
              <div>
                <div className="text-xs text-muted">Available on card</div>
                <div className="font-num text-2xl font-bold">
                  {cardBalance !== null ? money(cardBalance, card.currency) : '—'}
                </div>
              </div>
              <Button type="button" onClick={() => setFundOpen(true)} className="shrink-0 gap-1.5 px-4 py-2.5 text-sm">
                <PlusIcon size={15} />
                Add money
              </Button>
            </div>
            <div className="grid grid-cols-2 gap-px bg-line sm:grid-cols-4">
              <Stat label="Wallet NGN" value={ngnWallet ? ngn(ngnWallet.available_balance) : '—'} />
              <Stat label="Wallet USD" value={usdWallet ? usd(usdWallet.available_balance) : '—'} />
              <Stat label="Type" value={card.card_type} />
              <Stat label="Status" value={blocked ? 'Frozen' : 'Active'} />
            </div>
          </motion.div>

          <AnimatePresence mode="wait">
            {!isRevealed ? (
              <motion.button
                key="locked"
                type="button"
                layoutId="card-details-panel"
                variants={pageItem}
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8, scale: 0.98 }}
                transition={PAGE_SPRING}
                onClick={() => {
                  setPinMode('reveal')
                  setPinOpen(true)
                }}
                className="group relative w-full overflow-hidden rounded-2xl border border-line bg-surface p-5 text-left shadow-sm transition hover:border-mint/30 hover:shadow-md"
              >
                <div
                  aria-hidden
                  className="pointer-events-none absolute inset-0 bg-gradient-to-br from-mint/[0.04] via-transparent to-violet-500/[0.04] opacity-0 transition group-hover:opacity-100"
                />
                <div className="relative flex items-center gap-4">
                  <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-surface-2 text-muted ring-1 ring-line transition group-hover:bg-mint/10 group-hover:text-mint">
                    <LockIcon size={22} />
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="font-display text-base font-bold">Card & billing details</div>
                    <p className="mt-0.5 text-sm text-muted">
                      Number, CVV, expiry and billing address are hidden. Tap to verify with PIN.
                    </p>
                  </div>
                  <span className="shrink-0 rounded-full bg-mint px-4 py-2 text-xs font-bold text-white shadow-sm transition group-hover:bg-mint-strong">
                    Show details
                  </span>
                </div>
                <div className="relative mt-4 grid grid-cols-3 gap-2">
                  {['•••• ••••', '••/••', '•••'].map((v) => (
                    <div key={v} className="rounded-xl bg-surface-2/80 px-3 py-2 font-num text-sm text-muted blur-[2px]">
                      {v}
                    </div>
                  ))}
                </div>
              </motion.button>
            ) : (
              <motion.div
                key="revealed"
                layoutId="card-details-panel"
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={PAGE_SPRING}
                className="overflow-hidden rounded-2xl border border-mint/25 bg-surface shadow-[0_8px_40px_-16px_rgba(9,79,57,0.25)]"
              >
                <div className="flex items-center justify-between border-b border-line bg-mint/[0.04] px-5 py-3.5">
                  <div className="flex items-center gap-2">
                    <ShieldIcon size={16} className="text-mint" />
                    <span className="text-sm font-semibold">Sensitive details visible</span>
                  </div>
                  <button
                    type="button"
                    onClick={() => setRevealed(null)}
                    className="text-xs font-semibold text-muted transition hover:text-text"
                  >
                    Hide
                  </button>
                </div>

                <motion.div
                  className="grid gap-px bg-line sm:grid-cols-2"
                  variants={pageList}
                  initial="hidden"
                  animate="show"
                >
                  <DetailTile
                    label="Card number"
                    value={revealed.pan.replace(/\s/g, '')}
                    mono
                    copied={copiedField === 'pan-full'}
                    onCopy={() => copyValue('pan-full', revealed.pan)}
                  />
                  <DetailTile
                    label="Expiry"
                    value={revealed.expiry}
                    mono
                    copied={copiedField === 'exp'}
                    onCopy={() => copyValue('exp', revealed.expiry)}
                  />
                  <DetailTile
                    label="CVV"
                    value={revealed.cvv}
                    mono
                    copied={copiedField === 'cvv'}
                    onCopy={() => copyValue('cvv', revealed.cvv)}
                  />
                  <DetailTile label="Name on card" value={revealed.name_on_card} />
                  <DetailTile label="Brand" value={revealed.brand} />
                  <DetailTile label="Currency" value={revealed.currency} />
                </motion.div>

                {billing && (
                  <>
                    <div className="flex items-center gap-2 border-t border-line bg-surface-2/40 px-5 py-3">
                      <BankIcon size={15} className="text-muted" />
                      <span className="text-xs font-semibold uppercase tracking-wide text-muted">Billing address</span>
                    </div>
                    <motion.div className="grid gap-px bg-line sm:grid-cols-2" variants={pageList} initial="hidden" animate="show">
                      <DetailTile label="Street" value={billing.line1} className="sm:col-span-2" />
                      <DetailTile label="City" value={billing.city} />
                      <DetailTile label="State" value={billing.state} />
                      <DetailTile
                        label="Postcode"
                        value={billing.postcode}
                        mono
                        copied={copiedField === 'postcode'}
                        onCopy={() => copyValue('postcode', billing.postcode)}
                      />
                      <DetailTile label="Country" value={`${billing.country} (${billing.country_code})`} />
                    </motion.div>
                  </>
                )}
              </motion.div>
            )}
          </AnimatePresence>
        </motion.div>
      )}

      <SectionLabel>All cards</SectionLabel>
      <div className="space-y-2">
        {CARD_TABS.map((tab) => {
          const c = cards[tab.id]
          const active = activeCurrency === tab.id
          return (
            <button
              key={tab.id}
              type="button"
              onClick={() => setActiveCurrency(tab.id)}
              className={`card flex w-full items-center gap-3 p-4 text-left transition ${
                active ? 'ring-2 ring-mint/35' : 'hover:border-mint/25'
              }`}
            >
              <span
                className={`flex h-11 w-11 items-center justify-center rounded-2xl ${
                  tab.id === 'USD' ? 'bg-violet-500/10 text-violet-600' : 'bg-mint/10 text-mint'
                }`}
              >
                <CardIcon size={22} />
              </span>
              <div className="min-w-0 flex-1">
                <div className="text-sm font-bold">{tab.label} card</div>
                <div className="text-xs text-muted">
                  {c
                    ? `${c.is_blocked ? 'Frozen' : 'Active'} · ${money(c.card_balance_minor ?? 0, c.currency)}`
                    : 'Not created yet'}
                </div>
              </div>
              <Pill tone={c ? (c.is_blocked ? 'amber' : 'mint') : 'muted'}>{c ? c.currency : '—'}</Pill>
            </button>
          )
        })}
      </div>

      <AnimatePresence>
        {pinOpen && (
          <Modal onClose={() => setPinOpen(false)}>
            <h3 className="font-display text-lg font-bold">
              {pinMode === 'freeze' ? (blocked ? 'Unfreeze card' : 'Freeze card') : 'Show details'}
            </h3>
            <p className="text-sm text-muted">Enter your Reton PIN to continue.</p>
            <form
              onSubmit={(e) => {
                e.preventDefault()
                if (pinMode === 'freeze') toggleFreeze()
                else revealDetails(e)
              }}
              className="space-y-3"
            >
              <input
                type="password"
                inputMode="numeric"
                maxLength={4}
                autoFocus
                placeholder="••••"
                value={pinValue}
                onChange={(e) => setPinValue(e.target.value.replace(/\D/g, ''))}
                className="field w-full px-4 py-3 font-num text-lg tracking-[0.3em]"
              />
              {pinError && <p className="text-sm text-danger">{pinError}</p>}
              <div className="flex gap-2">
                <Button type="button" variant="ghost" className="flex-1" onClick={() => setPinOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" loading={pinLoading || freezeForm.processing} className="flex-1">
                  Confirm
                </Button>
              </div>
            </form>
          </Modal>
        )}
      </AnimatePresence>

      <AnimatePresence>
        {fundOpen && card && (
          <Modal onClose={() => setFundOpen(false)}>
            <h3 className="font-display text-lg font-bold">Add to {card.currency} card</h3>
            <p className="text-sm text-muted">Move money from your wallet onto this card.</p>
            <form onSubmit={submitFund} className="space-y-4">
              <label className="block">
                <span className="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted">
                  Amount ({card.currency})
                </span>
                <div className="field flex items-center px-4">
                  <span className="font-num text-lg text-muted">{card.currency === 'USD' ? '$' : '₦'}</span>
                  <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    placeholder={card.currency === 'USD' ? '25.00' : '10000'}
                    value={fundForm.data.amount}
                    onChange={(e) => fundForm.setData('amount', e.target.value)}
                    className="w-full bg-transparent px-2 py-3 font-num text-lg outline-none"
                  />
                </div>
              </label>
              <label className="block">
                <span className="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted">From wallet</span>
                <select
                  value={fundForm.data.wallet_id}
                  onChange={(e) => fundForm.setData('wallet_id', e.target.value)}
                  className="field w-full px-3 py-2.5 text-sm"
                >
                  {wallets.map((w) => (
                    <option key={w.id} value={w.id}>
                      {w.currency} · {money(w.available_balance, w.currency)}
                    </option>
                  ))}
                </select>
              </label>
              {fxPreview && (
                <p className="rounded-xl border border-line bg-surface-2 px-3 py-2.5 text-xs text-muted">
                  Wallet debit ≈ {money(fxPreview.source_amount_minor, fxPreview.source_currency)} including conversion
                </p>
              )}
              <PinField label="PIN" value={fundForm.data.pin} onChange={(v) => fundForm.setData('pin', v)} />
              {fundForm.errors.pin && <p className="text-sm text-danger">{fundForm.errors.pin}</p>}
              <div className="flex gap-2">
                <Button type="button" variant="ghost" className="flex-1" onClick={() => setFundOpen(false)}>
                  Cancel
                </Button>
                <Button type="submit" loading={fundForm.processing} className="flex-1">
                  Confirm
                </Button>
              </div>
            </form>
          </Modal>
        )}
      </AnimatePresence>
    </Page>
  )
}

Cards.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function QuickAction({
  Icon,
  label,
  active,
  highlight,
  onClick,
}: {
  Icon: (p: { size?: number }) => JSX.Element
  label: string
  active?: boolean
  highlight?: boolean
  onClick: () => void
}) {
  return (
    <motion.button
      type="button"
      whileTap={{ scale: 0.96 }}
      onClick={onClick}
      className={`flex flex-col items-center gap-2 rounded-2xl border px-3 py-3.5 transition ${
        active
          ? 'border-amber/40 bg-amber/[0.08] text-amber'
          : highlight
            ? 'border-mint/35 bg-mint/[0.06] text-mint shadow-sm'
            : 'border-line bg-surface hover:border-mint/35'
      }`}
    >
      <Icon size={20} />
      <span className="text-center text-[11px] font-semibold leading-tight">{label}</span>
    </motion.button>
  )
}

function DetailTile({
  label,
  value,
  mono,
  copied,
  onCopy,
  className = '',
}: {
  label: string
  value: string
  mono?: boolean
  copied?: boolean
  onCopy?: () => void
  className?: string
}) {
  return (
    <motion.div
      variants={pageItem}
      className={`group bg-surface px-4 py-3.5 ${className}`}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          <div className="text-[10px] font-semibold uppercase tracking-wide text-muted">{label}</div>
          <div className={`mt-1 break-all text-sm font-semibold ${mono ? 'font-num tracking-wider' : ''}`}>{value}</div>
        </div>
        {onCopy && (
          <button
            type="button"
            onClick={onCopy}
            className="mt-0.5 flex shrink-0 items-center gap-1 rounded-lg border border-transparent px-2 py-1 text-[10px] font-semibold text-muted opacity-0 transition hover:border-line hover:bg-surface-2 group-hover:opacity-100"
          >
            {copied ? <CheckIcon size={12} /> : <CopyIcon size={12} />}
            {copied ? 'Copied' : 'Copy'}
          </button>
        )}
      </div>
    </motion.div>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="bg-surface px-4 py-3">
      <div className="text-[10px] uppercase tracking-wide text-muted">{label}</div>
      <div className="mt-0.5 truncate text-sm font-semibold capitalize">{value}</div>
    </div>
  )
}

function Modal({ children, onClose }: { children: ReactNode; onClose: () => void }) {
  const [mounted, setMounted] = useState(false)

  useEffect(() => {
    setMounted(true)
    const previousOverflow = document.body.style.overflow
    const previousCount = Number(document.body.getAttribute('data-reton-modal-open') ?? '0')
    document.body.style.overflow = 'hidden'
    document.body.setAttribute('data-reton-modal-open', String(previousCount + 1))

    return () => {
      const next = Math.max(0, Number(document.body.getAttribute('data-reton-modal-open') ?? '1') - 1)
      if (next === 0) {
        document.body.removeAttribute('data-reton-modal-open')
        document.body.style.overflow = previousOverflow
      } else {
        document.body.setAttribute('data-reton-modal-open', String(next))
      }
    }
  }, [])

  if (!mounted) {
    return null
  }

  return createPortal(
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      className="fixed inset-0 z-[200] flex items-end justify-center bg-black/45 p-4 backdrop-blur-[2px] sm:items-center"
      onClick={onClose}
    >
      <motion.div
        initial={{ y: 24, opacity: 0 }}
        animate={{ y: 0, opacity: 1 }}
        exit={{ y: 16, opacity: 0 }}
        className="card w-full max-w-sm space-y-4 p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
        onClick={(e) => e.stopPropagation()}
      >
        {children}
      </motion.div>
    </motion.div>,
    document.body,
  )
}
