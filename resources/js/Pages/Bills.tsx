import type { FormEvent, ReactNode } from 'react'
import { useEffect, useMemo, useState } from 'react'
import { Head, router, useForm, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { BillerLogo } from '@/components/BillerLogo'
import { Page, SuccessScreen } from '@/components/page-kit'
import { AmountField, Button, Card, Pill } from '@/components/ui'
import {
  CheckIcon,
  ChevronRightIcon,
  LockIcon,
  PhoneIcon,
  SparkleIcon,
  UserIcon,
} from '@/components/icons'
import { billersByCategory, findBiller, type Biller } from '@/lib/billers'
import { billCategoryMeta, categoryMeta } from '@/lib/bill-categories'
import { deviceHeaders } from '@/lib/device'
import { ngn, shortDate, toMinor } from '@/lib/format'
import type { BillCategory, BillCategoryOption } from '@/lib/types'
import type { PageProps } from '@/types'

type Props = PageProps<{
  categories: BillCategoryOption[]
  bills: import('@/lib/types').Bill[]
  billsProvider?: string
}>

const reference: Record<BillCategory, { label: string; placeholder: string; mode: 'numeric' | 'text' }> = {
  airtime: { label: 'Phone number', placeholder: '0803 000 0000', mode: 'numeric' },
  data: { label: 'Phone number', placeholder: '0803 000 0000', mode: 'numeric' },
  electricity: { label: 'Meter number', placeholder: 'Enter meter number', mode: 'numeric' },
  cable_tv: { label: 'Smartcard / IUC', placeholder: 'Smartcard number', mode: 'numeric' },
  betting: { label: 'Betting user ID', placeholder: 'Your platform user ID', mode: 'text' },
  rrr: { label: 'Remita reference (RRR)', placeholder: '12-digit RRR', mode: 'numeric' },
}

const spring = { type: 'spring', stiffness: 380, damping: 32 } as const
const list = { hidden: {}, show: { transition: { staggerChildren: 0.04, delayChildren: 0.02 } } }
const item = { hidden: { opacity: 0, y: 10 }, show: { opacity: 1, y: 0 } }

const phoneLike = (c: BillCategory) => c === 'airtime' || c === 'data'

function stepFor(
  fixed: boolean,
  selected: Biller | null,
  customer: string,
  hasInquiry: boolean,
): number {
  if (fixed) {
    if (!hasInquiry) return customer.length >= 12 ? 2 : 1
    return 3
  }
  if (!selected) return 1
  if (!customer.trim()) return 2
  return 3
}

export default function Bills({ categories: categoriesProp, bills: billsProp }: Props) {
  const { auth, flash } = usePage<Props>().props
  const categories = Array.isArray(categoriesProp) ? categoriesProp : []
  const bills = Array.isArray(billsProp) ? billsProp : []
  const wallet = auth.wallets[0]
  const done = flash.bill

  const initialCategory = (() => {
    const fallback = categories[0]?.value ?? 'airtime'
    if (typeof window === 'undefined') return fallback
    const wanted = new URLSearchParams(window.location.search).get('category') as BillCategory | null
    return wanted && categories.some((c) => c.value === wanted) ? wanted : fallback
  })()

  const [category, setCategory] = useState<BillCategory>(initialCategory)
  const meta = useMemo(() => categories.find((c) => c.value === category), [categories, category])
  const cat = categoryMeta(category)
  const fixed = meta?.fixed_amount ?? false
  const billers = billersByCategory[category] ?? []

  const [selected, setSelected] = useState<Biller | null>(null)
  const [customer, setCustomer] = useState('')
  const [amount, setAmount] = useState('')
  const [pin, setPin] = useState('')
  const [showPin, setShowPin] = useState(false)

  const [inquiry, setInquiry] = useState<{ biller_name: string; amount: number } | null>(null)
  const [resolving, setResolving] = useState(false)
  const [lookupError, setLookupError] = useState('')

  const form = useForm({})

  const canPickContacts =
    typeof navigator !== 'undefined' && 'contacts' in navigator && typeof window !== 'undefined' && 'ContactsManager' in window

  const beneficiaries = useMemo(() => {
    const seen = new Set<string>()
    return (bills ?? [])
      .filter((b) => (phoneLike(category) ? phoneLike(b.category) : b.category === category))
      .filter((b) => {
        const k = b.customer_reference
        if (!k || seen.has(k)) return false
        seen.add(k)
        return true
      })
      .slice(0, 5)
  }, [bills, category])

  useEffect(() => {
    if (!fixed) return
    setInquiry(null)
    setLookupError('')
    if (!/^\d{12}$/.test(customer)) return
    setResolving(true)
    let cancelled = false
    fetch(`/bills/rrr?rrr=${customer}`, { headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((data) => !cancelled && setInquiry({ biller_name: data.biller_name, amount: data.amount }))
      .catch(() => !cancelled && setLookupError('No outstanding bill found for that RRR.'))
      .finally(() => !cancelled && setResolving(false))
    return () => {
      cancelled = true
    }
  }, [customer, fixed])

  function pick(next: BillCategory) {
    setCategory(next)
    setSelected(null)
    setCustomer('')
    setAmount('')
    setInquiry(null)
    setLookupError('')
  }

  async function pickContact() {
    try {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const picked = await (navigator as any).contacts.select(['tel'], { multiple: false })
      const tel: string | undefined = picked?.[0]?.tel?.[0]
      if (tel) setCustomer(tel.replace(/[^\d]/g, '').replace(/^234/, '0'))
    } catch {
      /* dismissed */
    }
  }

  const ref = reference[category]
  const payable = fixed ? inquiry?.amount ?? 0 : toMinor(amount)
  const overBalance = wallet ? payable > wallet.available_balance : false
  const canPay =
    !!wallet &&
    payable > 0 &&
    !overBalance &&
    pin.length >= 4 &&
    customer.trim().length > 0 &&
    (fixed ? !!inquiry : !!selected)

  const step = stepFor(fixed, selected, customer, !!inquiry)
  const networkMode = phoneLike(category)

  function pay() {
    if (!wallet || !canPay) return

    form.transform(() => {
      const base = {
        wallet_id: wallet.id,
        category,
        biller_code: fixed ? 'remita' : selected?.code ?? 'biller',
        customer_reference: customer.trim(),
        pin,
        ...(selected?.paymentCode ? { payment_code: selected.paymentCode } : {}),
      }
      return fixed ? base : { ...base, biller_name: selected?.name ?? '', amount: toMinor(amount) }
    })
    form.post('/bills', {
      headers: deviceHeaders(),
      preserveScroll: true,
      onSuccess: () => {
        setSelected(null)
        setCustomer('')
        setAmount('')
        setPin('')
        setInquiry(null)
      },
    })
  }

  function submit(e: FormEvent) {
    e.preventDefault()
    pay()
  }

  if (done) {
    const ok = done.status === 'completed'
    return (
      <>
        <Head title="Bill paid" />
        <SuccessScreen
          ok={ok}
          amount={done.amount}
          title={ok ? `Paid to ${done.biller_name}` : `Could not pay ${done.biller_name}`}
          primaryLabel="Pay another bill"
          onPrimary={() => router.get('/bills', {}, { preserveState: false })}
          secondaryHref="/dashboard"
        >
          <div className="rounded-xl border border-line bg-surface-2/60 px-4 py-3 text-sm">
            <div className="flex justify-between gap-2">
              <span className="text-muted">Service</span>
              <span className="font-medium">{done.category_label}</span>
            </div>
            <div className="mt-2 flex justify-between gap-2">
              <span className="text-muted">Reference</span>
              <span className="truncate font-num text-xs">{done.customer_reference}</span>
            </div>
          </div>
          <p className="text-sm text-muted">
            {ok ? 'Bill settled instantly from your wallet.' : done.failure_reason ?? 'Payment failed — funds returned.'}
          </p>
        </SuccessScreen>
      </>
    )
  }

  return (
    <Page narrow className="max-w-lg pb-32 sm:pb-8">
      <Head title="Bills" />

      <div className="flex items-end justify-between gap-3 px-0.5">
        <div className="min-w-0">
          <h1 className="font-display text-2xl font-bold tracking-tight text-text">Bills</h1>
          <p className="mt-0.5 text-sm text-muted">Airtime · data · power · TV</p>
        </div>
        {wallet && (
          <div className="text-right">
            <p className="text-[10px] font-semibold uppercase tracking-wide text-muted">Available</p>
            <p className="font-num text-sm font-bold text-mint">{ngn(wallet.available_balance)}</p>
          </div>
        )}
      </div>

      {/* Category grid */}
      <div>
        <p className="mb-2.5 px-0.5 text-xs font-semibold uppercase tracking-wide text-muted">Choose service</p>
        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
          {categories.map((c) => {
            const m = billCategoryMeta[c.value] ?? billCategoryMeta.airtime
            const on = c.value === category
            const Icon = m.Icon
            return (
              <motion.button
                key={c.value}
                type="button"
                whileTap={{ scale: 0.96 }}
                onClick={() => pick(c.value)}
                className={`relative flex flex-col items-center gap-2 rounded-2xl border p-3.5 text-center transition ${
                  on
                    ? `border-mint/50 bg-mint/[0.08] shadow-sm ring-2 ${m.ring}`
                    : 'border-line bg-surface hover:border-mint/30 hover:bg-surface-2'
                }`}
              >
                {on && (
                  <motion.span
                    layoutId="bill-cat-glow"
                    className="absolute inset-0 rounded-2xl bg-mint/[0.06]"
                    transition={spring}
                  />
                )}
                <span className={`relative z-10 flex h-11 w-11 items-center justify-center rounded-xl ${m.iconBg}`}>
                  <Icon size={22} />
                </span>
                <span className={`relative z-10 text-xs font-bold ${on ? 'text-mint' : 'text-text'}`}>{m.label}</span>
                <span className="relative z-10 hidden text-[10px] text-muted sm:block">{m.tagline}</span>
              </motion.button>
            )
          })}
        </div>
      </div>

      {/* Progress steps */}
      <div className="mt-5 flex items-center gap-2 px-1">
        {(fixed ? ['Reference', 'Verify', 'Pay'] : ['Provider', 'Details', 'Pay']).map((label, i) => {
          const n = i + 1
          const active = step >= n
          const current = step === n
          return (
            <div key={label} className="flex flex-1 items-center gap-2">
              <span
                className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ${
                  active ? 'bg-mint text-white' : 'bg-surface-2 text-muted'
                } ${current ? 'ring-2 ring-mint/30 ring-offset-2 ring-offset-surface' : ''}`}
              >
                {step > n ? <CheckIcon size={12} /> : n}
              </span>
              <span className={`hidden text-[11px] font-medium sm:inline ${current ? 'text-text' : 'text-muted'}`}>
                {label}
              </span>
              {i < 2 && <div className={`h-px flex-1 ${step > n ? 'bg-mint/40' : 'bg-line'}`} />}
            </div>
          )
        })}
      </div>

      <AnimatePresence mode="wait">
        <motion.div
          key={category}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -6 }}
          transition={{ duration: 0.2 }}
          className="mt-4"
        >
          <Card className="shield-glow space-y-5 p-4 sm:p-5">
            <form onSubmit={submit} className="space-y-5">
              {/* Networks — airtime / data */}
              {networkMode && billers.length > 0 && (
                <motion.div variants={list} initial="hidden" animate="show">
                  <span className="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted">
                    <PhoneIcon size={14} /> Network
                  </span>
                  <div className="flex justify-between gap-2 sm:justify-start sm:gap-4">
                    {billers.map((b) => {
                      const on = selected?.code === b.code
                      return (
                        <motion.button
                          key={b.code}
                          type="button"
                          variants={item}
                          whileTap={{ scale: 0.9 }}
                          onClick={() => setSelected(b)}
                          className="flex flex-1 flex-col items-center gap-2 sm:flex-none"
                        >
                          <span className="relative">
                            {on && (
                              <motion.span
                                layoutId="network-ring"
                                className="absolute -inset-1 rounded-full bg-mint/20 blur-sm"
                                transition={spring}
                              />
                            )}
                            <span
                              className={`relative block rounded-full p-0.5 transition ${
                                on ? 'ring-2 ring-mint ring-offset-2 ring-offset-surface' : 'ring-1 ring-line'
                              }`}
                            >
                              <BillerLogo biller={b} size={56} round />
                            </span>
                          </span>
                          <span className={`text-[11px] font-bold ${on ? 'text-mint' : 'text-muted'}`}>{b.name}</span>
                        </motion.button>
                      )
                    })}
                  </div>
                </motion.div>
              )}

              {/* Disco / cable providers */}
              {!networkMode && !fixed && billers.length > 0 && (
                <motion.div variants={list} initial="hidden" animate="show">
                  <span className="mb-3 block text-xs font-semibold uppercase tracking-wide text-muted">
                    {category === 'electricity' ? 'Select disco' : category === 'betting' ? 'Select platform' : 'Select provider'}
                  </span>
                  <div className="grid grid-cols-2 gap-2">
                    {billers.map((b) => {
                      const on = selected?.code === b.code
                      return (
                        <motion.button
                          key={b.code}
                          type="button"
                          variants={item}
                          whileTap={{ scale: 0.97 }}
                          onClick={() => setSelected(b)}
                          className={`group flex items-center gap-3 rounded-2xl border p-3 text-left transition ${
                            on
                              ? 'border-mint bg-gradient-to-br from-mint/[0.12] to-transparent shadow-sm'
                              : 'border-line bg-surface hover:border-mint/35 hover:shadow-sm'
                          }`}
                        >
                          <BillerLogo biller={b} size={40} />
                          <div className="min-w-0 flex-1">
                            <div className="truncate text-sm font-bold">{b.name}</div>
                            <div className="text-[10px] text-muted">{b.ref ?? 'Enter account'}</div>
                          </div>
                          {on && (
                            <span className="flex h-5 w-5 items-center justify-center rounded-full bg-mint text-white">
                              <CheckIcon size={12} />
                            </span>
                          )}
                        </motion.button>
                      )
                    })}
                  </div>
                </motion.div>
              )}

              {/* Customer reference */}
              <div>
                <div className="mb-2 flex items-center justify-between">
                  <span className="text-xs font-semibold uppercase tracking-wide text-muted">{ref.label}</span>
                  {networkMode && canPickContacts && (
                    <button
                      type="button"
                      onClick={pickContact}
                      className="inline-flex items-center gap-1 rounded-full bg-mint/10 px-2.5 py-1 text-[11px] font-semibold text-mint"
                    >
                      <UserIcon size={12} /> Contacts
                    </button>
                  )}
                </div>
                <div className="field flex items-center gap-2 px-3 py-1 focus-within:border-mint focus-within:ring-2 focus-within:ring-mint/15">
                  <cat.Icon size={18} className="shrink-0 text-muted" />
                  <input
                    className="w-full bg-transparent py-3 text-base outline-none"
                    inputMode={ref.mode}
                    placeholder={selected?.ref ?? ref.placeholder}
                    maxLength={fixed ? 12 : 24}
                    value={customer}
                    onChange={(e) =>
                      setCustomer(ref.mode === 'numeric' ? e.target.value.replace(/\D/g, '') : e.target.value)
                    }
                    autoComplete="off"
                  />
                </div>

                {beneficiaries.length > 0 && (
                  <div className="mt-3">
                    <span className="mb-2 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-muted">
                      <SparkleIcon size={12} /> Frequent
                    </span>
                    <div className="flex gap-2 overflow-x-auto pb-1">
                      {beneficiaries.map((b) => {
                        const biller =
                          findBiller(b.biller_code) ?? ({ code: b.biller_code, brand: b.biller_code, bg: '#64748b' } as const)
                        return (
                          <button
                          key={b.id}
                          type="button"
                          onClick={() => setCustomer(b.customer_reference)}
                          className={`flex shrink-0 items-center gap-2 rounded-full border py-1.5 pl-1.5 pr-3 text-xs font-medium transition ${
                            customer === b.customer_reference
                              ? 'border-mint bg-mint/10 text-mint'
                              : 'border-line bg-surface-2 text-muted hover:border-mint/40'
                          }`}
                        >
                          <BillerLogo biller={biller} size={28} round />
                          <span className="font-num">{b.customer_reference}</span>
                          </button>
                        )
                      })}
                    </div>
                  </div>
                )}

                {fixed && resolving && (
                  <p className="mt-2 flex items-center gap-2 text-xs text-muted">
                    <span className="h-3 w-3 animate-spin rounded-full border-2 border-mint border-t-transparent" />
                    Looking up bill…
                  </p>
                )}
                {fixed && inquiry && (
                  <motion.div
                    initial={{ opacity: 0, y: 6 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="mt-3 overflow-hidden rounded-2xl border border-mint/30 bg-gradient-to-r from-mint/10 to-transparent"
                  >
                    <div className="flex items-center justify-between gap-3 px-4 py-3.5">
                      <div className="min-w-0">
                        <div className="truncate text-sm font-bold">{inquiry.biller_name}</div>
                        <div className="text-xs text-muted">Amount due</div>
                      </div>
                      <span className="font-num text-lg font-bold text-mint">{ngn(inquiry.amount)}</span>
                    </div>
                  </motion.div>
                )}
                {fixed && lookupError && <p className="mt-2 text-sm text-danger">{lookupError}</p>}
              </div>

              {!fixed && (
                <div>
                  <AmountField value={amount} onChange={setAmount} invalid={overBalance} quick={[500, 1000, 2000, 5000]} />
                  {overBalance && (
                    <p className="mt-2 text-sm text-danger">Exceeds your available balance ({ngn(wallet?.available_balance ?? 0)}).</p>
                  )}
                </div>
              )}

              {/* PIN */}
              <div>
                <span className="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted">Authorize with PIN</span>
                <div className="field flex items-center gap-2 px-3 py-1">
                  <LockIcon size={18} className="shrink-0 text-muted" />
                  <input
                    type={showPin ? 'text' : 'password'}
                    inputMode="numeric"
                    maxLength={4}
                    placeholder="••••"
                    value={pin}
                    onChange={(e) => setPin(e.target.value.replace(/\D/g, ''))}
                    autoComplete="off"
                    className="w-full bg-transparent py-3 font-num text-lg tracking-[0.3em] outline-none"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPin((s) => !s)}
                    className="text-xs font-medium text-muted hover:text-text"
                  >
                    {showPin ? 'Hide' : 'Show'}
                  </button>
                </div>
              </div>

              {flash.error && (
                <p className="rounded-xl border border-danger/25 bg-danger/5 px-3 py-2 text-sm text-danger">{flash.error}</p>
              )}

              {/* Desktop submit */}
              <Button type="submit" loading={form.processing} disabled={!canPay} className="hidden w-full py-3.5 sm:flex">
                {payable > 0 ? `Pay ${ngn(payable)}` : 'Continue'}
                {canPay && <ChevronRightIcon size={18} className="ml-1 inline opacity-80" />}
              </Button>
            </form>
          </Card>
        </motion.div>
      </AnimatePresence>

      {/* Mobile sticky pay bar */}
      <div className="fixed inset-x-0 bottom-[5.5rem] z-20 px-4 sm:hidden">
        <div className="dock rounded-2xl p-2 shadow-lg">
          <Button type="button" loading={form.processing} disabled={!canPay} className="w-full py-3.5" onClick={pay}>
            {payable > 0 ? `Pay ${ngn(payable)}` : 'Enter details to continue'}
          </Button>
        </div>
      </div>

      {/* Recent bills */}
      {bills.length > 0 && (
        <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.15 }} className="mt-6">
          <div className="mb-3 flex items-center justify-between px-0.5">
            <h3 className="font-display text-sm font-bold tracking-tight">Recent payments</h3>
            <span className="text-xs text-muted">{bills.length} total</span>
          </div>
          <div className="space-y-2">
            {bills.slice(0, 6).map((b, i) => {
              const catKey = b.category as BillCategory
              const cm = billCategoryMeta[catKey] ?? billCategoryMeta.airtime
              const Icon = cm.Icon
              const biller = findBiller(b.biller_code)
              return (
                <motion.div
                  key={b.id}
                  initial={{ opacity: 0, x: -8 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: i * 0.04 }}
                  className="flex items-center gap-3 rounded-2xl border border-line bg-surface px-3 py-3 transition hover:border-mint/25 hover:bg-surface-2"
                >
                  {biller ? (
                    <BillerLogo biller={biller} size={40} round={phoneLike(catKey)} />
                  ) : (
                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${cm.iconBg}`}>
                      <Icon size={18} />
                    </span>
                  )}
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <span className="truncate text-sm font-bold">{biller?.name ?? b.biller_name}</span>
                      <Pill tone={b.status === 'completed' ? 'mint' : b.status === 'failed' ? 'danger' : 'amber'}>
                        {b.status}
                      </Pill>
                    </div>
                    <div className="mt-0.5 truncate text-xs text-muted">
                      {b.category_label} · <span className="font-num">{b.customer_reference}</span>
                    </div>
                  </div>
                  <div className="shrink-0 text-right">
                    <div className="font-num text-sm font-bold">{ngn(b.amount)}</div>
                    <div className="text-[10px] text-muted">{shortDate(b.created_at)}</div>
                  </div>
                </motion.div>
              )
            })}
          </div>
        </motion.div>
      )}
    </Page>
  )
}

Bills.layout = (page: ReactNode) => <AppShell>{page}</AppShell>
