import type { FormEvent, ReactNode } from 'react'
import { useState } from 'react'
import { Head, router, useForm, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { AmountField, Button, Card, CopyRow, Pill } from '@/components/ui'
import { LockIcon, PlusIcon } from '@/components/icons'
import { toMinor } from '@/lib/format'
import type { SharedProps } from '@/types'

export default function AddMoney() {
  const { auth, flash } = usePage<SharedProps>().props
  const wallet = auth.wallets[0]
  const deposit = flash.deposit

  const [amount, setAmount] = useState('')
  const [dismissed, setDismissed] = useState(false)
  const form = useForm({ wallet_id: wallet?.id ?? '', amount: 0 })

  function submit(e: FormEvent) {
    e.preventDefault()
    form.transform((data) => ({ ...data, amount: toMinor(amount) }))
    form.post('/deposits', { preserveScroll: true })
  }

  if (deposit && !dismissed) {
    const va = deposit.virtual_account
    return (
      <motion.div initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} className="mx-auto max-w-lg">
        <Head title="Add money" />
        <Card className="space-y-5">
          <div className="flex items-center justify-between">
            <h2 className="font-display text-lg font-bold tracking-tight">Transfer to fund</h2>
            <Pill tone="amber">Awaiting transfer</Pill>
          </div>
          <p className="text-sm leading-relaxed text-muted">
            Send a bank transfer to the one-time account below. Your wallet is credited automatically the moment
            it arrives.
          </p>
          <div className="divide-y divide-line rounded-2xl border border-line bg-surface-2/50 px-4">
            <CopyRow label="Bank" value={va?.bank_name ?? '—'} />
            <CopyRow label="Account number" value={va?.account_number ?? '—'} mono />
            <CopyRow label="Account name" value={va?.account_name ?? '—'} />
          </div>
          <p className="flex items-center gap-1.5 text-xs text-muted">
            <LockIcon size={13} /> One-time account · expires after this deposit · Ref {deposit.reference}
          </p>
          <Button
            variant="ghost"
            className="w-full"
            onClick={() => {
              setDismissed(true)
              setAmount('')
              // Drop the flashed deposit so a reload doesn't resurface it.
              router.get('/add-money', {}, { preserveState: false })
            }}
          >
            Fund a different amount
          </Button>
        </Card>
      </motion.div>
    )
  }

  return (
    <div className="mx-auto max-w-lg">
      <Head title="Add money" />
      <Card className="space-y-6">
        <div className="flex items-center gap-3">
          <span className="flex h-10 w-10 items-center justify-center rounded-full bg-mint/10 text-mint">
            <PlusIcon size={20} />
          </span>
          <div>
            <h2 className="font-display text-lg font-bold tracking-tight">Add money</h2>
            <p className="text-sm text-muted">We’ll create a one-time account to fund your wallet.</p>
          </div>
        </div>

        <form onSubmit={submit} className="space-y-5">
          <AmountField value={amount} onChange={setAmount} />
          {form.errors.amount && <p className="text-sm text-danger">{form.errors.amount}</p>}
          {flash.error && <p className="text-sm text-danger">{flash.error}</p>}
          <Button type="submit" loading={form.processing} disabled={toMinor(amount) <= 0} className="w-full">
            Generate account
          </Button>
        </form>

        <p className="flex items-center gap-1.5 border-t border-line pt-4 text-xs text-muted">
          <LockIcon size={13} /> Deposits are reconciled and ledger-backed — every kobo is accounted for.
        </p>
      </Card>
    </div>
  )
}

AddMoney.layout = (page: ReactNode) => <AppShell>{page}</AppShell>
