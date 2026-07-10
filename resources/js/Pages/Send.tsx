import type { ReactNode } from 'react'
import { useEffect, useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { Controller, useForm } from 'react-hook-form'
import { AppShell } from '@/components/AppShell'
import { FieldError, RhfField } from '@/components/forms/RhfField'
import { FormPanel, InfoStrip, Page, PageHero, SuccessScreen } from '@/components/page-kit'
import { fieldErrorMessage, useServerErrors } from '@/hooks/useServerErrors'
import { AmountField, Button } from '@/components/ui'
import { CheckIcon, ClockIcon, LockIcon, SendIcon, ShieldIcon } from '@/components/icons'
import { ngn, toMinor } from '@/lib/format'
import { deviceHeaders } from '@/lib/device'
import {
  sendTransferRefinements,
  sendTransferSchema,
  type SendTransferFormValues,
} from '@/lib/schemas/transfer'
import type { SharedProps } from '@/types'

export default function Send() {
  const { auth } = usePage<SharedProps>().props
  const wallet = auth.wallets[0]

  return (
    <Page narrow>
      <Head title="Send money" />
      <PageHero
        icon={SendIcon}
        title="Send money"
        subtitle="Protected by default — funds stay in escrow until you release. Switch to Standard for instant sends."
        balance={wallet?.available_balance}
        tone="mint"
      />
      <SendForm key="send" />
    </Page>
  )
}

Send.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

type Recent = { account: string; name: string }
const RECENTS_KEY = 'reton:recents'
const RECENTS_MAX = 5

function readRecents(): Recent[] {
  if (typeof window === 'undefined') return []
  try {
    const raw = window.localStorage.getItem(RECENTS_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) return []
    return parsed
      .filter((r): r is Recent => !!r && typeof r.account === 'string' && typeof r.name === 'string')
      .slice(0, RECENTS_MAX)
  } catch {
    return []
  }
}

function pushRecent(account: string, name: string): Recent[] {
  const next = [{ account, name }, ...readRecents().filter((r) => r.account !== account)].slice(0, RECENTS_MAX)
  if (typeof window !== 'undefined') {
    try {
      window.localStorage.setItem(RECENTS_KEY, JSON.stringify(next))
    } catch {
      /* storage unavailable */
    }
  }
  return next
}

const maskAccount = (account: string) => `•••• ${account.slice(-4)}`

function SendForm() {
  const { auth, flash } = usePage<SharedProps>().props
  const wallet = auth.wallets[0]
  const done = flash.transfer

  const [serverErrors, setServerErrors] = useState<Record<string, string>>({})
  const [processing, setProcessing] = useState(false)
  const [recipient, setRecipient] = useState<{ wallet_id: string; name: string } | null>(null)
  const [resolving, setResolving] = useState(false)
  const [lookupError, setLookupError] = useState('')
  const [recents, setRecents] = useState<Recent[]>([])

  const {
    control,
    register,
    handleSubmit,
    watch,
    setValue,
    setError,
    reset,
    formState: { errors },
  } = useForm<SendTransferFormValues>({
    resolver: zodResolver(sendTransferSchema),
    defaultValues: {
      from_wallet_id: wallet?.id ?? '',
      to_wallet_id: '',
      account: '',
      amount: '',
      pin: '',
      type: 'protected',
    },
    mode: 'onBlur',
  })

  useServerErrors(serverErrors, setError)

  const account = watch('account')
  const amount = watch('amount')
  const transferType = watch('type')
  const minor = toMinor(amount)
  const overBalance = wallet ? minor > wallet.available_balance : false
  const protectedMode = transferType === 'protected'

  useEffect(() => {
    setRecents(readRecents())
  }, [])

  useEffect(() => {
    if (wallet?.id) setValue('from_wallet_id', wallet.id)
  }, [wallet?.id, setValue])

  useEffect(() => {
    setRecipient(null)
    setLookupError('')
    setValue('to_wallet_id', '')

    if (!/^\d{10}$/.test(account)) return

    setResolving(true)
    let cancelled = false
    fetch(`/lookup?account_number=${account}`, { headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then((data) => {
        if (cancelled) return
        setRecipient({ wallet_id: data.wallet_id, name: data.account_name })
        setValue('to_wallet_id', data.wallet_id, { shouldValidate: true })
      })
      .catch(() => {
        if (!cancelled) {
          setLookupError('No Reton account found with that number.')
          setError('account', { type: 'manual', message: 'No Reton account found with that number.' })
        }
      })
      .finally(() => !cancelled && setResolving(false))

    return () => {
      cancelled = true
    }
  }, [account, setError, setValue])

  const onSubmit = handleSubmit((values) => {
    if (!wallet || !recipient) return

    const balanceErrors = sendTransferRefinements(values, wallet.available_balance)
    const balanceMessage = balanceErrors.amount
    if (balanceMessage) {
      setError('amount', { type: 'manual', message: balanceMessage })
      return
    }

    setProcessing(true)
    setServerErrors({})

    router.post(
      '/transfers',
      {
        from_wallet_id: values.from_wallet_id,
        to_wallet_id: values.to_wallet_id,
        amount: toMinor(values.amount),
        type: values.type,
        pin: values.pin,
      },
      {
        headers: deviceHeaders(),
        preserveScroll: true,
        onError: (errs) => setServerErrors(errs as Record<string, string>),
        onSuccess: () => {
          setRecents(pushRecent(values.account, recipient.name))
          reset({
            from_wallet_id: wallet.id,
            to_wallet_id: '',
            account: '',
            amount: '',
            pin: '',
            type: 'protected',
          })
          setRecipient(null)
        },
        onFinish: () => setProcessing(false),
      },
    )
  })

  if (done) {
    return (
      <SuccessScreen
        amount={done.amount}
        title={done.type === 'protected' ? `Held for ${done.recipient_name}` : `Sent to ${done.recipient_name}`}
        subtitle={done.type === 'protected' ? 'Funds are safely held until you release.' : 'Transfer settled instantly.'}
        primaryLabel="Send again"
        onPrimary={() => router.get('/send', {}, { preserveState: false })}
        secondaryHref="/dashboard"
      >
        <div className="rounded-xl border border-line bg-surface-2/60 px-4 py-3 text-sm">
          <div className="flex justify-between gap-2">
            <span className="text-muted">Reference</span>
            <span className="truncate font-num text-xs">{done.reference}</span>
          </div>
          {done.type === 'protected' && (
            <p className="mt-2 text-xs text-muted">
              Release or raise a callback anytime from{' '}
              <Link href="/protection" className="font-semibold text-mint hover:underline">
                Protection
              </Link>
              .
            </p>
          )}
        </div>
      </SuccessScreen>
    )
  }

  const canSend = !!recipient && minor > 0 && !overBalance && watch('pin').length >= 4

  return (
    <FormPanel>
        <form onSubmit={onSubmit} className="space-y-6" noValidate>
          <input type="hidden" {...register('from_wallet_id')} />
          <input type="hidden" {...register('to_wallet_id')} />

          <div className="space-y-3">
            <RhfField
              label="Recipient account number"
              inputMode="numeric"
              maxLength={10}
              placeholder="10-digit Reton account number"
              autoComplete="off"
              error={fieldErrorMessage(errors.account, serverErrors.to_wallet_id ?? lookupError)}
              {...register('account', {
                onChange: (e) => {
                  e.target.value = e.target.value.replace(/\D/g, '')
                },
              })}
            />

            {recents.length > 0 && !recipient && (
              <div>
                <div className="mb-2 flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-muted">
                  <ClockIcon size={12} /> Recent
                </div>
                <div className="flex flex-wrap gap-2">
                  {recents.map((r) => (
                    <button
                      key={r.account}
                      type="button"
                      onClick={() => setValue('account', r.account, { shouldValidate: true })}
                      className="group flex items-center gap-2 rounded-full border border-line bg-surface py-1 pl-1 pr-3 text-left transition hover:border-mint/40 hover:bg-mint/[0.05] active:scale-[0.98]"
                    >
                      <span className="flex h-7 w-7 items-center justify-center rounded-full bg-mint/12 font-display text-xs font-bold text-mint">
                        {r.name.charAt(0).toUpperCase()}
                      </span>
                      <span className="min-w-0">
                        <span className="block max-w-[8rem] truncate text-xs font-semibold text-text">{r.name}</span>
                        <span className="block font-num text-[10px] tracking-wider text-muted">
                          {maskAccount(r.account)}
                        </span>
                      </span>
                    </button>
                  ))}
                </div>
              </div>
            )}

            {resolving && <p className="text-xs text-muted">Checking account…</p>}
            {recipient && (
              <motion.div
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                className="flex items-center gap-3 rounded-2xl border border-mint/30 bg-mint/[0.06] px-4 py-3"
              >
                <span className="flex h-10 w-10 items-center justify-center rounded-full bg-mint font-display font-bold text-white">
                  {recipient.name.charAt(0).toUpperCase()}
                </span>
                <div className="min-w-0 flex-1">
                  <div className="truncate text-sm font-semibold text-text">{recipient.name}</div>
                  <div className="font-num text-xs tracking-wider text-muted">Reton · {account}</div>
                </div>
                <CheckIcon size={18} className="text-mint" />
              </motion.div>
            )}
          </div>

          <div>
            <div className="mb-3 rounded-2xl border border-line bg-surface-2/60 px-5 py-5 text-center">
              <div
                className={`font-num text-4xl font-bold tracking-tight tabular-nums sm:text-5xl ${
                  minor > 0 ? (overBalance ? 'text-danger' : 'text-text') : 'text-muted/50'
                }`}
              >
                {ngn(minor)}
              </div>
              <div className="mt-1 text-xs text-muted">
                {overBalance
                  ? 'Above available balance'
                  : minor > 0
                    ? `${wallet ? ngn(wallet.available_balance - minor) : '—'} left after this`
                    : 'Enter an amount to send'}
              </div>
            </div>
            <Controller
              name="amount"
              control={control}
              render={({ field }) => (
                <AmountField
                  value={field.value}
                  onChange={field.onChange}
                  invalid={overBalance || !!errors.amount}
                />
              )}
            />
            <FieldError error={fieldErrorMessage(errors.amount, serverErrors.amount)} />
          </div>

          <Controller
            name="type"
            control={control}
            render={({ field }) => (
              <div className="space-y-2">
                <span className="block text-xs font-medium uppercase tracking-wide text-muted">How to send</span>
                <Option
                  active={field.value === 'protected'}
                  onClick={() => field.onChange('protected')}
                  title="Protected"
                  desc="Funds held in escrow — you can recall until you release."
                  recommended
                />
                <Option
                  active={field.value === 'normal'}
                  onClick={() => field.onChange('normal')}
                  title="Standard"
                  desc="Arrives instantly. Final once sent."
                />
              </div>
            )}
          />

          <div>
            <RhfField
              label="Transaction PIN"
              type="password"
              inputMode="numeric"
              maxLength={4}
              placeholder="••••"
              autoComplete="off"
              error={fieldErrorMessage(errors.pin, serverErrors.pin)}
              {...register('pin', {
                onChange: (e) => {
                  e.target.value = e.target.value.replace(/\D/g, '')
                },
              })}
            />
            <p className="mt-2 flex items-center gap-1.5 text-xs text-muted">
              <LockIcon size={13} /> Authorised by your PIN and screened by Reton’s fraud engine.
            </p>
          </div>

          {flash.error && <p className="text-sm text-danger">{flash.error}</p>}
          <Button type="submit" loading={processing} disabled={!canSend} className="w-full">
            {protectedMode ? 'Send with protection' : 'Send'}
            {minor > 0 ? ` ${ngn(minor)}` : ''}
          </Button>
        </form>

        <InfoStrip tone="mint">
          Protected transfers hold funds until you release — great for marketplace sales.
        </InfoStrip>
    </FormPanel>
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
      className={`flex w-full items-center gap-3 rounded-2xl border p-4 text-left transition active:scale-[0.99] ${
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
              {active ? 'Selected' : 'Default'}
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
