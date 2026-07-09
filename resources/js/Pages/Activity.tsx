import type { ReactNode } from 'react'
import { useMemo, useState } from 'react'
import { Head, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { ActivityIcon, ReceiveIcon, SendIcon, TrendIcon } from '@/components/icons'
import { FormPanel, MorphTabs, Page, PageHero, pageItem } from '@/components/page-kit'
import { Pill } from '@/components/ui'
import { ngn, shortDate } from '@/lib/format'
import type { StatementEntry, Transfer } from '@/lib/types'
import type { PageProps } from '@/types'

type Tab = 'statement' | 'transfers'

export default function Activity() {
  const { transfers, statement } = usePage<PageProps<{ transfers: Transfer[]; statement: StatementEntry[] }>>().props
  const [tab, setTab] = useState<Tab>('statement')

  const flow = useMemo(() => {
    const entries = statement ?? []
    const inflow = entries.filter((e) => e.direction === 'credit').reduce((s, e) => s + e.amount, 0)
    const outflow = entries.filter((e) => e.direction === 'debit').reduce((s, e) => s + e.amount, 0)
    const total = Math.max(inflow + outflow, 1)
    return { inflow, outflow, inPct: (inflow / total) * 100, outPct: (outflow / total) * 100 }
  }, [statement])

  return (
    <Page>
      <Head title="Activity" />
      <PageHero
        icon={ActivityIcon}
        title="Activity"
        subtitle="Every movement in and out of your wallet — tap a row for details."
        tone="sky"
      />

      <FormPanel className="!space-y-4">
        <span className="inline-flex items-center gap-2 text-sm font-semibold">
          <TrendIcon size={16} className="text-mint" /> Money flow
        </span>
        <div className="grid gap-4 sm:grid-cols-2">
          <FlowBar label="Money in" value={ngn(flow.inflow)} pct={flow.inPct} tone="mint" />
          <FlowBar label="Money out" value={ngn(flow.outflow)} pct={flow.outPct} tone="muted" />
        </div>
      </FormPanel>

      <MorphTabs
        layoutId="activity-tab"
        value={tab}
        onChange={setTab}
        tabs={[
          { id: 'statement', label: 'Statement' },
          { id: 'transfers', label: 'Transfers', count: transfers?.length },
        ]}
      />

      <motion.div variants={pageItem} className="panel divide-y divide-line overflow-hidden p-0">
        {tab === 'transfers' ? (
          <>
            {(transfers ?? []).map((t) => (
              <Row
                key={t.id}
                icon={<SendIcon size={16} />}
                accent={t.type === 'protected'}
                title={t.note || (t.type === 'protected' ? 'Protected transfer' : 'Transfer')}
                sub={`${shortDate(t.created_at)} · ${t.status}`}
                right={
                  <div className="flex items-center gap-2">
                    {t.type === 'protected' && <Pill tone="mint">protected</Pill>}
                    <span className="font-num text-sm font-semibold">{ngn(t.amount)}</span>
                  </div>
                }
              />
            ))}
            {transfers && transfers.length === 0 && <Empty>No transfers yet.</Empty>}
          </>
        ) : (
          <>
            {(statement ?? []).map((e) => (
              <Row
                key={e.id}
                icon={e.direction === 'credit' ? <ReceiveIcon size={16} /> : <SendIcon size={16} />}
                accent={e.direction === 'credit'}
                title={e.transaction?.description ?? e.transaction?.type ?? 'Movement'}
                sub={shortDate(e.created_at)}
                right={
                  <span className={`font-num text-sm font-semibold ${e.direction === 'credit' ? 'text-mint' : 'text-text'}`}>
                    {e.direction === 'credit' ? '+' : '−'}
                    {ngn(e.amount)}
                  </span>
                }
              />
            ))}
            {statement && statement.length === 0 && <Empty>No movements yet.</Empty>}
          </>
        )}
      </motion.div>
    </Page>
  )
}

Activity.layout = (page: ReactNode) => <AppShell>{page}</AppShell>

function Row({
  icon,
  accent,
  title,
  sub,
  right,
}: {
  icon: ReactNode
  accent?: boolean
  title: string
  sub: string
  right: ReactNode
}) {
  return (
    <div className="flex items-center justify-between gap-3 px-4 py-3.5 transition hover:bg-surface-2/50 sm:px-5">
      <div className="flex min-w-0 items-center gap-3">
        <span
          className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${
            accent ? 'bg-mint/10 text-mint' : 'bg-surface-2 text-muted'
          }`}
        >
          {icon}
        </span>
        <div className="min-w-0">
          <div className="truncate text-sm font-medium">{title}</div>
          <div className="text-xs text-muted">{sub}</div>
        </div>
      </div>
      <div className="shrink-0">{right}</div>
    </div>
  )
}

function FlowBar({
  label,
  value,
  pct,
  tone,
}: {
  label: string
  value: string
  pct: number
  tone: 'mint' | 'muted'
}) {
  return (
    <div>
      <div className="mb-1 flex items-center justify-between text-xs">
        <span className="text-muted">{label}</span>
        <span className="font-num font-semibold text-text">{value}</span>
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-surface-2">
        <motion.div
          className={`h-full rounded-full ${tone === 'mint' ? 'bg-mint' : 'bg-muted/50'}`}
          initial={{ width: 0 }}
          animate={{ width: `${Math.max(pct, 3)}%` }}
          transition={{ type: 'spring', stiffness: 120, damping: 22, delay: 0.1 }}
        />
      </div>
    </div>
  )
}

function Empty({ children }: { children: ReactNode }) {
  return <div className="px-5 py-10 text-center text-sm text-muted">{children}</div>
}
