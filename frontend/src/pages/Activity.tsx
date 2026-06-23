import { ngn, shortDate } from '../lib/format'
import { useActivity, useMe, useTransfers } from '../lib/queries'
import { Card, Pill } from '../components/ui'
import { ReceiveIcon, SendIcon, ShieldIcon } from '../components/icons'

export function Activity() {
  const { data } = useMe()
  const wallet = data?.wallets?.[0]
  const { data: activity } = useActivity(wallet?.id)
  const { data: transfers } = useTransfers()

  return (
    <div className="space-y-7">
      <h1 className="font-display text-2xl font-bold tracking-tight">Activity</h1>

      <section className="space-y-3">
        <h2 className="font-display text-sm font-semibold uppercase tracking-wide text-muted">Transfers</h2>
        <Card className="divide-y divide-line p-0">
          {(transfers ?? []).map((t) => (
            <div key={t.id} className="flex items-center justify-between gap-3 px-5 py-3.5">
              <div className="flex items-center gap-3">
                <span
                  className={`flex h-9 w-9 items-center justify-center rounded-full ${
                    t.type === 'protected' ? 'bg-mint/10 text-mint' : 'bg-surface-2 text-muted'
                  }`}
                >
                  {t.type === 'protected' ? <ShieldIcon size={16} /> : <SendIcon size={16} />}
                </span>
                <div>
                  <div className="text-sm font-medium">
                    {t.note || (t.type === 'protected' ? 'Protected transfer' : 'Transfer')}
                  </div>
                  <div className="text-xs text-muted">
                    {shortDate(t.created_at)} · {t.status}
                  </div>
                </div>
              </div>
              <div className="flex items-center gap-2">
                {t.type === 'protected' && <Pill tone="mint">protected</Pill>}
                <span className="font-num text-sm">{ngn(t.amount)}</span>
              </div>
            </div>
          ))}
          {transfers && transfers.length === 0 && <Empty>No transfers yet.</Empty>}
        </Card>
      </section>

      <section className="space-y-3">
        <h2 className="font-display text-sm font-semibold uppercase tracking-wide text-muted">Wallet statement</h2>
        <Card className="divide-y divide-line p-0">
          {(activity ?? []).map((e) => (
            <div key={e.id} className="flex items-center justify-between gap-3 px-5 py-3.5">
              <div className="flex items-center gap-3">
                <span
                  className={`flex h-9 w-9 items-center justify-center rounded-full ${
                    e.direction === 'credit' ? 'bg-mint/10 text-mint' : 'bg-surface-2 text-muted'
                  }`}
                >
                  {e.direction === 'credit' ? <ReceiveIcon size={16} /> : <SendIcon size={16} />}
                </span>
                <div>
                  <div className="text-sm font-medium">
                    {e.transaction?.description ?? e.transaction?.type ?? 'Movement'}
                  </div>
                  <div className="text-xs text-muted">{shortDate(e.created_at)}</div>
                </div>
              </div>
              <div className={`font-num text-sm ${e.direction === 'credit' ? 'text-mint' : 'text-text'}`}>
                {e.direction === 'credit' ? '+' : '−'}
                {ngn(e.amount)}
              </div>
            </div>
          ))}
          {activity && activity.length === 0 && <Empty>No movements yet.</Empty>}
        </Card>
      </section>
    </div>
  )
}

function Empty({ children }: { children: React.ReactNode }) {
  return <div className="px-5 py-8 text-center text-sm text-muted">{children}</div>
}
