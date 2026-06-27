import type { FormEvent, ReactNode } from 'react'
import { useEffect, useMemo, useState } from 'react'
import { Head, router, useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { BillerLogo } from '@/components/BillerLogo'
import { AmountField, Button, Card, Field, Pill } from '@/components/ui'
import { BillIcon, CheckIcon, LockIcon, UserIcon } from '@/components/icons'
import { billersByCategory, type Biller } from '@/lib/billers'
import { deviceHeaders } from '@/lib/device'
import { ngn, shortDate, toMinor } from '@/lib/format'
import type { BillCategory, BillCategoryOption } from '@/lib/types'
import type { PageProps } from '@/types'

type Props = PageProps<{ categories: BillCategoryOption[]; bills: import('@/lib/types').Bill[] }>

/** The reference label per category (the biller-specific account being billed). */
const reference: Record<BillCategory, { label: string; placeholder: string; mode: 'numeric' | 'text' }> = {
  airtime: { label: 'Phone number', placeholder: '0803 000 0000', mode: 'numeric' },
  data: { label: 'Phone number', placeholder: '0803 000 0000', mode: 'numeric' },
  electricity: { label: 'Meter number', placeholder: '12-digit meter number', mode: 'numeric' },
  cable_tv: { label: 'Smartcard / IUC number', placeholder: 'Smartcard number', mode: 'numeric' },
  rrr: { label: 'Remita Retrieval Reference', placeholder: '12-digit RRR', mode: 'numeric' },
}

const phoneLike = (c: BillCategory) => c === 'airtime' || c === 'data'

export default function Bills({ categories, bills }: Props) {
  const { auth, flash } = usePage<Props>().props
  const wallet = auth.wallets[0]
  const done = flash.bill

  // Honour a ?category= deep-link (e.g. the dashboard service grid) when valid.
  const initialCategory = (() => {
    const fallback = categories[0]?.value ?? 'airtime'
    if (typeof window === 'undefined') return fallback
    const wanted = new URLSearchParams(window.location.search).get('category') as BillCategory | null
    return wanted && categories.some((c) => c.value === wanted) ? wanted : fallback
  })()
  const [category, setCategory] = useState<BillCategory>(initialCategory)
  const meta = useMemo(() => categories.find((c) => c.value === category), [categories, category])
  const fixed = meta?.fixed_amount ?? false
  const billers = billersByCategory[category] ?? []

  const [selected, setSelected] = useState<Biller | null>(null)
  const [customer, setCustomer] = useState('')
  const [amount, setAmount] = useState('')
  const [pin, setPin] = useState('')

  // RRR lookup: resolve the reference to its biller + fixed amount.
  const [inquiry, setInquiry] = useState<{ biller_name: string; amount: number } | null>(null)
  const [resolving, setResolving] = useState(false)
  const [lookupError, setLookupError] = useState('')

  const form = useForm({})

  // Contact Picker (Chrome on Android) — graceful no-op elsewhere.
  const canPickContacts =
    typeof navigator !== 'undefined' && 'contacts' in navigator && typeof window !== 'undefined' && 'ContactsManager' in window

  // Recent beneficiaries for this reference type, newest first, de-duplicated.
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
      .slice(0, 4)
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

  // Switching category resets the per-category inputs.
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
      /* user dismissed the picker */
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

  function submit(e: FormEvent) {
    e.preventDefault()
    if (!wallet || !canPay) return

    form.transform(() => {
      const base = {
        wallet_id: wallet.id,
        category,
        biller_code: fixed ? 'remita' : selected?.code ?? 'biller',
        customer_reference: customer.trim(),
        pin,
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

  if (done) {
    const ok = done.status === 'completed'
    return (
      <motion.div initial={{ opacity: 0, scale: 0.97 }} animate={{ opacity: 1, scale: 1 }}>
        <Head title="Bills" />
        <Card className="mx-auto max-w-lg text-center">
          <div
            className={`mx-auto mt-2 flex h-16 w-16 items-center justify-center rounded-full ${
              ok ? 'bg-mint/12 text-mint' : 'bg-danger/10 text-danger'
            }`}
          >
            {ok ? <CheckIcon size={30} /> : <BillIcon size={28} />}
          </div>
          <h2 className="mt-4 font-display text-2xl font-bold">{ngn(done.amount)}</h2>
          <p className="mt-1 text-sm text-muted">
            {ok ? `paid to ${done.biller_name}` : `could not be paid to ${done.biller_name}`}
          </p>
          <p className="mt-4 text-sm leading-relaxed text-muted">
            {ok
              ? `${done.category_label} · ${done.customer_reference}. Your wallet was debited and the bill is settled.`
              : done.failure_reason ?? 'The payment failed and your money was returned to your wallet.'}
          </p>
          <p className="mt-3 font-num text-xs text-muted">Ref {done.reference}</p>
          <Button className="mt-6" onClick={() => router.get('/bills', {}, { preserveState: false })}>
            Pay another bill
          </Button>
        </Card>
      </motion.div>
    )
  }

  const networkMode = phoneLike(category)

  return (
    <div className="mx-auto max-w-lg space-y-5 pb-4">
      <Head title="Bills" />

      {/* Header */}
      <div className="flex items-center gap-3">
        <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-mint/10 text-mint">
          <BillIcon size={22} />
        </span>
        <div>
          <h1 className="font-display text-xl font-bold tracking-tight">Pay a bill</h1>
          <p className="text-sm text-muted">Airtime, data, TV, electricity & Remita — from your wallet.</p>
        </div>
      </div>

      {/* Category — sleek scrollable segmented control */}
      <div className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
        {categories.map((c) => {
          const on = c.value === category
          return (
            <button
              key={c.value}
              type="button"
              onClick={() => pick(c.value)}
              className={`relative shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition ${
                on ? 'text-white' : 'border border-line bg-surface text-muted hover:text-text'
              }`}
            >
              {on && (
                <motion.span
                  layoutId="bill-cat"
                  className="absolute inset-0 rounded-full bg-mint shadow-sm"
                  transition={{ type: 'spring', stiffness: 380, damping: 32 }}
                />
              )}
              <span className="relative z-10">{c.label}</span>
            </button>
          )
        })}
      </div>

      <Card className="space-y-6">
        <form onSubmit={submit} className="space-y-6">
          {/* Network selector — circular, for airtime & data */}
          {networkMode && billers.length > 0 && (
            <div>
              <span className="mb-3 block text-xs font-medium uppercase tracking-wide text-muted">Select network</span>
              <div className="flex gap-4">
                {billers.map((b) => {
                  const on = selected?.code === b.code
                  return (
                    <motion.button
                      key={b.code}
                      type="button"
                      whileTap={{ scale: 0.92 }}
                      onClick={() => setSelected(b)}
                      className="flex flex-col items-center gap-1.5"
                      aria-pressed={on}
                    >
                      <span
                        className={`rounded-full p-0.5 transition ${
                          on ? 'ring-2 ring-mint ring-offset-2 ring-offset-surface' : 'ring-1 ring-line'
                        }`}
                      >
                        <BillerLogo biller={b} size={52} round />
                      </span>
                      <span className={`text-[11px] font-semibold ${on ? 'text-mint' : 'text-muted'}`}>{b.name}</span>
                    </motion.button>
                  )
                })}
              </div>
            </div>
          )}

          {/* Biller picker — brand tiles for electricity / cable */}
          {!networkMode && !fixed && billers.length > 0 && (
            <div>
              <span className="mb-2 block text-xs font-medium uppercase tracking-wide text-muted">
                {category === 'electricity' ? 'Disco' : 'Provider'}
              </span>
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {billers.map((b) => {
                  const on = selected?.code === b.code
                  return (
                    <motion.button
                      key={b.code}
                      type="button"
                      whileTap={{ scale: 0.97 }}
                      onClick={() => setSelected(b)}
                      className={`flex items-center gap-2.5 rounded-xl border p-2.5 text-left transition ${
                        on ? 'border-mint bg-mint/[0.06] shadow-sm' : 'border-line bg-surface hover:border-mint/40'
                      }`}
                    >
                      <BillerLogo biller={b} size={36} />
                      <span className="min-w-0 flex-1 truncate text-sm font-semibold text-text">{b.name}</span>
                      {on && <CheckIcon size={15} className="text-mint" />}
                    </motion.button>
                  )
                })}
              </div>
            </div>
          )}

          {/* Reference / phone number */}
          <div>
            <div className="mb-1.5 flex items-center justify-between">
              <span className="text-xs font-medium uppercase tracking-wide text-muted">{ref.label}</span>
              {networkMode && canPickContacts && (
                <button
                  type="button"
                  onClick={pickContact}
                  className="inline-flex items-center gap-1 text-xs font-semibold text-mint hover:underline"
                >
                  <UserIcon size={13} /> Select from contacts
                </button>
              )}
            </div>
            <input
              className="field w-full px-4 py-3.5 text-base"
              inputMode={ref.mode}
              placeholder={selected?.ref ?? ref.placeholder}
              maxLength={fixed ? 12 : 24}
              value={customer}
              onChange={(e) =>
                setCustomer(ref.mode === 'numeric' ? e.target.value.replace(/\D/g, '') : e.target.value)
              }
              autoComplete="off"
            />

            {/* Recent beneficiaries */}
            {beneficiaries.length > 0 && (
              <div className="mt-3">
                <span className="mb-2 block text-[11px] font-medium uppercase tracking-wide text-muted">Frequent</span>
                <div className="flex flex-wrap gap-2">
                  {beneficiaries.map((b) => (
                    <button
                      key={b.id}
                      type="button"
                      onClick={() => setCustomer(b.customer_reference)}
                      className={`flex items-center gap-2 rounded-full border py-1 pl-1 pr-3 text-xs font-medium transition ${
                        customer === b.customer_reference
                          ? 'border-mint bg-mint/[0.07] text-mint'
                          : 'border-line bg-surface text-muted hover:border-mint/40 hover:text-text'
                      }`}
                    >
                      <span className="flex h-6 w-6 items-center justify-center rounded-full bg-mint/12 text-[10px] font-bold text-mint">
                        {b.biller_name.charAt(0).toUpperCase()}
                      </span>
                      <span className="font-num tracking-wide">{b.customer_reference}</span>
                    </button>
                  ))}
                </div>
              </div>
            )}

            {fixed && resolving && <p className="mt-2 text-xs text-muted">Looking up bill…</p>}
            {fixed && inquiry && (
              <motion.div
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                className="mt-3 flex items-center justify-between rounded-xl border border-mint/30 bg-mint/[0.06] px-4 py-3"
              >
                <div className="min-w-0">
                  <div className="truncate text-sm font-semibold text-text">{inquiry.biller_name}</div>
                  <div className="text-xs text-muted">Amount due</div>
                </div>
                <span className="font-num text-sm font-semibold text-mint">{ngn(inquiry.amount)}</span>
              </motion.div>
            )}
            {fixed && lookupError && <p className="mt-2 text-sm text-danger">{lookupError}</p>}
          </div>

          {/* Amount */}
          {!fixed && (
            <div>
              <AmountField value={amount} onChange={setAmount} invalid={overBalance} />
              {overBalance && <p className="mt-2 text-sm text-danger">That’s more than your available balance.</p>}
            </div>
          )}

          {/* PIN */}
          <div>
            <Field
              label="Transaction PIN"
              type="password"
              inputMode="numeric"
              maxLength={6}
              placeholder="••••"
              value={pin}
              onChange={(e) => setPin(e.target.value.replace(/\D/g, ''))}
              autoComplete="off"
            />
            <p className="mt-2 flex items-center gap-1.5 text-xs text-muted">
              <LockIcon size={13} /> Authorised by your PIN and screened by Reton’s fraud engine.
            </p>
          </div>

          {flash.error && <p className="text-sm text-danger">{flash.error}</p>}
          <Button type="submit" loading={form.processing} disabled={!canPay} className="w-full py-3.5">
            {payable > 0 ? `Pay ${ngn(payable)}` : 'Continue'}
          </Button>
        </form>
      </Card>

      {bills.length > 0 && (
        <Card className="space-y-1">
          <h3 className="mb-2 font-display text-sm font-bold tracking-tight">Recent bills</h3>
          {bills.map((b) => (
            <div key={b.id} className="flex items-center justify-between gap-3 border-t border-line py-3 first:border-0">
              <div className="min-w-0">
                <div className="truncate text-sm font-semibold text-text">{b.biller_name}</div>
                <div className="text-xs text-muted">
                  {b.category_label} · {b.customer_reference} · {shortDate(b.created_at)}
                </div>
              </div>
              <div className="flex shrink-0 items-center gap-2">
                <span className="font-num text-sm">{ngn(b.amount)}</span>
                <Pill tone={b.status === 'completed' ? 'mint' : b.status === 'failed' ? 'danger' : 'amber'}>
                  {b.status}
                </Pill>
              </div>
            </div>
          ))}
        </Card>
      )}
    </div>
  )
}

Bills.layout = (page: ReactNode) => <AppShell>{page}</AppShell>
