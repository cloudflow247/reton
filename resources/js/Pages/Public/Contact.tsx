import type { FormEvent, ReactNode } from 'react'
import { useState } from 'react'
import { Head } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { PublicLayout } from '@/components/PublicLayout'
import { Button, Field } from '@/components/ui'
import { ChatIcon, CheckIcon, MailIcon, PhoneIcon } from '@/components/icons'

const channels = [
  { Icon: MailIcon, label: 'Email us', value: 'support@reton.ng', href: 'mailto:support@reton.ng' },
  { Icon: PhoneIcon, label: 'Call us', value: '+234 700 000 0000', href: 'tel:+2347000000000' },
  { Icon: ChatIcon, label: 'In-app chat', value: 'Mon–Sat, 8am–8pm WAT', href: null },
]

export default function Contact() {
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [message, setMessage] = useState('')
  const [sent, setSent] = useState(false)

  function submit(e: FormEvent) {
    e.preventDefault()
    // No inbox backend yet — hand off to the user's mail client.
    const body = encodeURIComponent(`${message}\n\n— ${name} (${email})`)
    window.location.href = `mailto:support@reton.ng?subject=${encodeURIComponent('Reton enquiry from ' + name)}&body=${body}`
    setSent(true)
  }

  return (
    <div className="mx-auto max-w-5xl px-5 py-20">
      <Head title="Contact" />
      <span className="inline-flex items-center gap-2 rounded-full border border-line bg-mint/10 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-mint">
        Contact
      </span>
      <h1 className="mt-5 font-display text-4xl font-bold tracking-tight sm:text-5xl">We’re here to help.</h1>
      <p className="mt-5 max-w-2xl text-lg leading-relaxed text-muted">
        Questions about protection, recovery or your account? Reach us any way you like — a real person will get
        back to you.
      </p>

      <div className="mt-12 grid gap-8 lg:grid-cols-[1fr_1.1fr]">
        <div className="space-y-3">
          {channels.map(({ Icon, label, value, href }) => {
            const inner = (
              <div className="card elevate flex items-center gap-4 p-5">
                <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-mint/10 text-mint">
                  <Icon size={22} />
                </span>
                <div>
                  <div className="font-display text-sm font-semibold">{label}</div>
                  <div className="text-sm text-muted">{value}</div>
                </div>
              </div>
            )
            return href ? (
              <a key={label} href={href} className="block">
                {inner}
              </a>
            ) : (
              <div key={label}>{inner}</div>
            )
          })}
        </div>

        <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} className="card p-6 sm:p-7">
          {sent ? (
            <div className="flex flex-col items-center gap-3 py-8 text-center">
              <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-mint/10 text-mint">
                <CheckIcon size={26} />
              </span>
              <h2 className="font-display text-lg font-bold">Thanks — your mail client is open.</h2>
              <p className="max-w-xs text-sm text-muted">Send the message and we’ll reply within one business day.</p>
            </div>
          ) : (
            <form onSubmit={submit} className="space-y-4">
              <h2 className="font-display text-lg font-bold tracking-tight">Send us a message</h2>
              <Field label="Your name" value={name} onChange={(e) => setName(e.target.value)} required />
              <Field
                label="Email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
              <label className="block">
                <span className="mb-1.5 block text-xs font-medium uppercase tracking-wide text-muted">Message</span>
                <textarea
                  className="field w-full px-4 py-3 text-sm"
                  rows={4}
                  value={message}
                  onChange={(e) => setMessage(e.target.value)}
                  required
                />
              </label>
              <Button type="submit" className="w-full" disabled={!name || !email || !message}>
                Send message
              </Button>
            </form>
          )}
        </motion.div>
      </div>
    </div>
  )
}

Contact.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>
