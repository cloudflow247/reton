import type { FormEvent, ReactNode } from 'react'
import { useEffect, useState } from 'react'
import { Head, Link, router, useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { AmountField, Button, Card, Field } from '@/components/ui'
import { CheckIcon, LockIcon, ShieldIcon } from '@/components/icons'
import { ngn, toMinor } from '@/lib/format'
import { deviceHeaders } from '@/lib/device'
import type { SharedProps } from '@/types'

export default function Send() {
  return (
    <div className="space-y-6">
      <Head title="Send money" />
      <div className="inline-flex rounded-full border border-line bg-surface-2 p-1">
        <span className="relative rounded-full px-5 py-2 font-display text-sm font-semibold">
          <motion.span
            layoutId="send-tab"
            className="absolute inset-0 rounded-full bg-mint shadow-sm"
            transition={{ type: 'spring', stiffness: 380, damping: 32 }}
          />
          <span className="relative z-10 text-white">Send money</span>
        </span>
        <Link
          href="/protection"
          className="relative z-10 flex items-center gap-1.5 rounded-full px-5 py-2 font-display text-sm font-semibold text-muted transition-colors hover:text-text"
        >
          <ShieldIcon size={15} /> Protection
        </Link>
      </div>

      <SendForm />
    </div>
  )
}

Send.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function SendForm() {
  const { auth, flash } = usePage<SharedProps>().props
  const wallet = auth.wallets[0]
  const done = flash.transfer

  const [protectedMode, setProtectedMode] = useState(false)
  const [account, setAccount] = useState('')
  const [recipient, setRecipient] = useState<{ wallet_id: string; name: string } | null>(null)
  const [resolving, setResolving] = useState(false)
  const [lookupError, setLookupError] = useState('')
  const [amount, setAmount] = useState('')
  const [pin, setPin] = useState('')

  const form = useForm({ from_wallet_id: wallet?.id ?? '', to_wallet_id: '', amount: 0, type: 'normal', pin: '' })

  const minor = toMinor(amount)
  const overBalance = wallet ? minor > wallet.available_balance : false
  const canSend = !!recipient && minor > 0 && !overBalance && pin.length >= 4

  // Name enquiry: resolve a 10-digit account number to its holder.
  useEffect(() => {
    setRecipient(null)
    setLookupError('')
    if (!/^\d{10}$/.test(account)) return
    setResolving(true)
    let cancelled = false
    fetch(`/lookup?account_number=${account}`, { headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((data) => !cancelled && setRecipient({ wallet_id: data.wallet_id, name: data.account_name }))
      .catch(() => !cancelled && setLookupError('No Reton account found with that number.'))
      .finally(() => !cancelled && setResolving(false))
    return () => {
      cancelled = true
    }
  }, [account])

  function submit(e: FormEvent) {
    e.preventDefault()
    if (!wallet || !recipient) return
    form.transform((data) => ({
      ...data,
      to_wallet_id: recipient.wallet_id,
      amount: minor,
      type: protectedMode ? 'protected' : 'normal',
      pin,
    }))
    form.post('/transfers', {
      headers: deviceHeaders(),
      preserveScroll: true,
      onSuccess: () => {
        setAccount('')
        setAmount('')
        setPin('')
        setRecipient(null)
      },
    })
  }

  if (done) {
    return (
      <motion.div initial={{ opacity: 0, scale: 0.97 }} animate={{ opacity: 1, scale: 1 }}>
        <Card className="mx-auto max-w-lg text-center shield-glow">
          <div className="mx-auto mt-2 flex h-16 w-16 items-center justify-center rounded-full bg-mint/12 text-mint">
            {done.type === 'protected' ? <ShieldIcon size={30} /> : <CheckIcon size={30} />}
          </div>
          <h2 className="mt-4 font-display text-2xl font-bold">{ngn(done.amount)}</h2>
          <p className="mt-1 text-sm text-muted">
            {done.type === 'protected' ? `held for ${done.recipient_name}` : `sent to ${done.recipient_name}`}
          </p>
          <p className="mt-4 text-sm leading-relaxed text-muted">
            {done.type === 'protected'
              ? 'The money is safely held. Release it or raise a callback any time from the Protection tab.'
              : 'Your transfer settled instantly.'}
          </p>
          <p className="mt-3 font-num text-xs text-muted">Ref {done.reference}</p>
          <Button className="mt-6" onClick={() => router.get('/send', {}, { preserveState: false })}>
            Make another transfer
          </Button>
        </Card>
      </motion.div>
    )
  }

  return (
    <div className="mx-auto max-w-lg">
      <Card className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="font-display text-lg font-bold tracking-tight">Send money</h2>
          <span className="rounded-full bg-surface-2 px-3 py-1 text-xs text-muted">
            Balance {wallet ? ngn(wallet.available_balance) : '—'}
          </span>
        </div>

        <form onSubmit={submit} className="space-y-6">
          {/* Recipient — with bank-style name enquiry */}
          <div>
            <Field
              label="Recipient account number"
              inputMode="numeric"
              maxLength={10}
              placeholder="10-digit Reton account number"
              value={account}
              onChange={(e) => setAccount(e.target.value.replace(/\D/g, ''))}
              autoComplete="off"
            />
            {resolving && <p className="mt-2 text-xs text-muted">Checking account…</p>}
            {recipient && (
              <motion.div
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                className="mt-3 flex items-center gap-3 rounded-xl border border-mint/30 bg-mint/[0.06] px-4 py-3"
              >
                <span className="flex h-10 w-10 items-center justify-center rounded-full bg-mint font-display font-bold text-white">
                  {recipient.name.charAt(0)}
                </span>
                <div className="min-w-0 flex-1">
                  <div className="truncate text-sm font-semibold text-text">{recipient.name}</div>
                  <div className="font-num text-xs tracking-wider text-muted">Reton · {account}</div>
                </div>
                <CheckIcon size={18} className="text-mint" />
              </motion.div>
            )}
            {lookupError && <p className="mt-2 text-sm text-danger">{lookupError}</p>}
            {form.errors.to_wallet_id && <p className="mt-2 text-sm text-danger">{form.errors.to_wallet_id}</p>}
          </div>

          {/* Amount */}
          <div>
            <AmountField value={amount} onChange={setAmount} invalid={overBalance} />
            {overBalance && <p className="mt-2 text-sm text-danger">That’s more than your available balance.</p>}
            {form.errors.amount && <p className="mt-2 text-sm text-danger">{form.errors.amount}</p>}
          </div>

          {/* Protection choice */}
          <div className="space-y-2">
            <span className="block text-xs font-medium uppercase tracking-wide text-muted">How to send</span>
            <Option
              active={!protectedMode}
              onClick={() => setProtectedMode(false)}
              title="Standard"
              desc="Arrives instantly. Final once sent."
            />
            <Option
              active={protectedMode}
              onClick={() => setProtectedMode(true)}
              title="Protected"
              desc="Held in escrow until you confirm — recall it any time."
              recommended
            />
          </div>

          {/* PIN + trust */}
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
            {form.errors.pin && <p className="mt-2 text-sm text-danger">{form.errors.pin}</p>}
          </div>

          {flash.error && <p className="text-sm text-danger">{flash.error}</p>}
          <Button type="submit" loading={form.processing} disabled={!canSend} className="w-full">
            {protectedMode ? 'Send with protection' : 'Send'}
            {minor > 0 ? ` ${ngn(minor)}` : ''}
          </Button>
        </form>
      </Card>
    </div>
  )
}

function Option({
  active,
  onClick,
  title,
  desc,
  recommended,
}: {
  active: boolean
  onClick: () => void
  title: string
  desc: string
  recommended?: boolean
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`flex w-full items-center gap-3 rounded-2xl border p-4 text-left transition ${
        active ? 'border-mint bg-mint/[0.06]' : 'border-line bg-surface hover:border-mint/30'
      }`}
    >
      {recommended ? (
        <ShieldIcon size={20} className={active ? 'text-mint' : 'text-muted'} />
      ) : (
        <span className={`h-5 w-5 rounded-full border-2 ${active ? 'border-mint' : 'border-line'}`} />
      )}
      <div className="flex-1">
        <div className="flex items-center gap-2">
          <span className="font-display text-sm font-bold">{title}</span>
          {recommended && (
            <span className="rounded-full bg-mint/12 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-mint">
              Recommended
            </span>
          )}
        </div>
        <div className="mt-0.5 text-xs leading-snug text-muted">{desc}</div>
      </div>
      <span
        className={`flex h-5 w-5 items-center justify-center rounded-full border-2 ${
          active ? 'border-mint bg-mint' : 'border-line'
        }`}
      >
        {active && <CheckIcon size={12} className="text-white" />}
      </span>
    </button>
  )
}
