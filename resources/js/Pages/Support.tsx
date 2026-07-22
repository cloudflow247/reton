import type { FormEvent, ReactNode } from 'react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { AppShell } from '@/components/AppShell'
import { ChatIcon, SendIcon, SparkleIcon } from '@/components/icons'
import { Page, PageHero } from '@/components/page-kit'
import { Button, Card, Modal, Pill } from '@/components/ui'
import { shortDate } from '@/lib/format'
import type { PageProps } from '@/types'

type SupportAction = { label: string; href: string }

type SupportMessage = {
  id: string
  role: 'user' | 'assistant'
  body: string
  actions: SupportAction[]
  created_at: string | null
}

type SupportTicket = {
  id: string
  reference: string
  subject: string
  status: string
  created_at: string | null
}

type QuickPrompt = { label: string; message: string }

type SupportProps = PageProps<{
  messages: SupportMessage[]
  openTickets: SupportTicket[]
  welcome: string
  quickPrompts: QuickPrompt[]
}>

function renderBody(body: string | null | undefined) {
  const parts = (body ?? '').split(/(\*\*[^*]+\*\*)/g)

  return parts.map((part, index) => {
    if (part.startsWith('**') && part.endsWith('**')) {
      return (
        <strong key={index} className="font-semibold text-text">
          {part.slice(2, -2)}
        </strong>
      )
    }

    return part.split('\n').map((line, lineIndex, lines) => (
      <span key={`${index}-${lineIndex}`}>
        {line}
        {lineIndex < lines.length - 1 ? <br /> : null}
      </span>
    ))
  })
}

function MessageBubble({ message }: { message: SupportMessage }) {
  const isUser = message.role === 'user'

  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}
    >
      <div
        className={`max-w-[88%] rounded-2xl px-4 py-3 text-sm leading-relaxed sm:max-w-[75%] ${
          isUser
            ? 'rounded-br-md bg-mint text-white'
            : 'rounded-bl-md border border-line bg-surface-2/80 text-text'
        }`}
      >
        <div className={isUser ? 'text-white' : 'text-text'}>{renderBody(message.body)}</div>
        {!isUser && (message.actions ?? []).length > 0 && (
          <div className="mt-3 flex flex-wrap gap-2">
            {(message.actions ?? []).map((action) => (
              <Link
                key={action.href + action.label}
                href={action.href}
                className="rounded-full border border-mint/30 bg-mint/10 px-3 py-1 text-xs font-semibold text-mint transition hover:bg-mint/15"
              >
                {action.label}
              </Link>
            ))}
          </div>
        )}
        {message.created_at && (
          <p className={`mt-2 text-[10px] ${isUser ? 'text-white/70' : 'text-muted'}`}>
            {shortDate(message.created_at)}
          </p>
        )}
      </div>
    </motion.div>
  )
}

export default function Support() {
  const {
    messages: messagesProp,
    openTickets: openTicketsProp,
    welcome,
    quickPrompts: quickPromptsProp,
    flash,
  } = usePage<SupportProps>().props
  const [draft, setDraft] = useState('')
  const [sending, setSending] = useState(false)
  const [escalateOpen, setEscalateOpen] = useState(false)
  const [ticketSubject, setTicketSubject] = useState('Need help from support')
  const [ticketNote, setTicketNote] = useState('')
  const [ticketRef, setTicketRef] = useState('')
  const bottomRef = useRef<HTMLDivElement>(null)

  const messages = Array.isArray(messagesProp) ? messagesProp : []
  const openTickets = Array.isArray(openTicketsProp) ? openTicketsProp : []
  const quickPrompts = Array.isArray(quickPromptsProp) ? quickPromptsProp : []

  const thread = useMemo(() => messages, [messages])

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [thread.length, flash?.support_ticket])

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    if (params.get('escalate') === '1') {
      setEscalateOpen(true)
    }
    const prompt = params.get('prompt')
    if (prompt === 'find') setDraft('Help me find a transaction')
    if (prompt === 'protection') setDraft('How does callback protection work?')
    if (prompt === 'recovery') setDraft('I sent money to the wrong person')
  }, [])

  const send = (text: string) => {
    const message = text.trim()
    if (!message || sending) return

    setSending(true)
    router.post(
      '/support/messages',
      { message },
      {
        preserveScroll: true,
        onFinish: () => {
          setSending(false)
          setDraft('')
        },
      },
    )
  }

  const onSubmit = (event: FormEvent) => {
    event.preventDefault()
    send(draft)
  }

  const escalate = (event: FormEvent) => {
    event.preventDefault()
    router.post(
      '/support/escalate',
      {
        subject: ticketSubject,
        note: ticketNote || null,
        transfer_reference: ticketRef || null,
      },
      {
        preserveScroll: true,
        onFinish: () => setEscalateOpen(false),
      },
    )
  }

  return (
    <Page narrow>
      <Head title="Support" />
      <PageHero
        icon={ChatIcon}
        title="Support"
        subtitle="Ask Reton or escalate to the team"
        tone="violet"
      />

      {flash?.support_ticket && (
        <Card className="mb-4 border-mint/30 bg-mint/5 p-4">
          <p className="text-sm text-text">
            Ticket <strong>{flash.support_ticket}</strong> opened - we will email you shortly.
          </p>
        </Card>
      )}

      {openTickets.length > 0 && (
        <Card className="mb-4 p-4">
          <p className="text-xs font-semibold uppercase tracking-wide text-muted">Open tickets</p>
          <div className="mt-2 space-y-2">
            {openTickets.map((ticket) => (
              <div key={ticket.id} className="flex items-center justify-between gap-3 text-sm">
                <span className="font-medium">{ticket.reference}</span>
                <Pill tone="amber">{ticket.status}</Pill>
              </div>
            ))}
          </div>
        </Card>
      )}

      <Card className="flex min-h-[28rem] flex-col overflow-hidden p-0">
        <div className="flex items-center gap-2 border-b border-line px-4 py-3">
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-violet-500/15 text-violet-600">
            <SparkleIcon size={16} />
          </span>
          <div>
            <p className="text-sm font-semibold">AI Assistant</p>
            <p className="text-xs text-muted">Powered by your live account data</p>
          </div>
        </div>

        <div className="flex-1 space-y-4 overflow-y-auto px-4 py-4">
          {thread.length === 0 && (
            <MessageBubble
              message={{
                id: 'welcome',
                role: 'assistant',
                body: welcome,
                actions: [
                  { label: 'Protection center', href: '/protection' },
                  { label: 'Activity', href: '/activity' },
                ],
                created_at: null,
              }}
            />
          )}

          {thread.map((message) => (
            <MessageBubble key={message.id} message={message} />
          ))}
          <div ref={bottomRef} />
        </div>

        <div className="border-t border-line bg-surface/50 px-4 py-3">
          <div className="mb-3 flex gap-2 overflow-x-auto pb-1">
            {quickPrompts.map((prompt) => (
              <button
                key={prompt.label}
                type="button"
                onClick={() => send(prompt.message)}
                disabled={sending}
                className="shrink-0 rounded-full border border-line bg-surface px-3 py-1.5 text-xs font-medium text-muted transition hover:border-mint/40 hover:text-mint disabled:opacity-50"
              >
                {prompt.label}
              </button>
            ))}
          </div>

          <form onSubmit={onSubmit} className="flex items-end gap-2">
            <textarea
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              rows={2}
              placeholder="Ask about a transaction, protection, or wrong transfers…"
              className="min-h-[2.75rem] flex-1 resize-none rounded-xl border border-line bg-surface px-3 py-2 text-sm outline-none ring-mint/30 focus:ring-2"
            />
            <Button type="submit" disabled={sending || !draft.trim()} className="shrink-0 gap-1.5">
              <SendIcon size={16} />
              Send
            </Button>
          </form>

          <button
            type="button"
            onClick={() => setEscalateOpen(true)}
            className="mt-3 text-xs font-semibold text-violet-600 hover:underline"
          >
            Talk to a human →
          </button>
        </div>
      </Card>

      {escalateOpen ? (
        <Modal onClose={() => setEscalateOpen(false)} title="Talk to a human">
          <form onSubmit={escalate} className="space-y-4">
            <p className="text-sm text-muted">
              Open a support ticket and our team will respond at your registered email.
            </p>
            <label className="block space-y-1.5">
              <span className="text-xs font-semibold uppercase tracking-wide text-muted">Subject</span>
              <input
                value={ticketSubject}
                onChange={(event) => setTicketSubject(event.target.value)}
                className="w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm"
                required
              />
            </label>
            <label className="block space-y-1.5">
              <span className="text-xs font-semibold uppercase tracking-wide text-muted">Details</span>
              <textarea
                value={ticketNote}
                onChange={(event) => setTicketNote(event.target.value)}
                rows={4}
                className="w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm"
                placeholder="Describe what happened…"
              />
            </label>
            <label className="block space-y-1.5">
              <span className="text-xs font-semibold uppercase tracking-wide text-muted">
                Transaction reference (optional)
              </span>
              <input
                value={ticketRef}
                onChange={(event) => setTicketRef(event.target.value)}
                className="w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm"
                placeholder="TRF-…"
              />
            </label>
            <div className="flex justify-end gap-2">
              <Button type="button" variant="ghost" onClick={() => setEscalateOpen(false)}>
                Cancel
              </Button>
              <Button type="submit">Open ticket</Button>
            </div>
          </form>
        </Modal>
      ) : null}
    </Page>
  )
}

Support.layout = (page: ReactNode) => <AppShell>{page}</AppShell>
