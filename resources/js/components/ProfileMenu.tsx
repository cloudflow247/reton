import { useEffect, useRef, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import { UserAvatar } from '@/components/UserAvatar'
import { ChevronDownIcon, LockIcon, UndoIcon, UserIcon } from '@/components/icons'
import type { User } from '@/lib/types'
import { cn } from '@/lib/utils'

type ProfileMenuProps = {
  user: User
  needsPin: boolean
  profileActive: boolean
  onNavigate?: () => void
}

export function ProfileMenu({ user, needsPin, profileActive, onNavigate }: ProfileMenuProps) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const firstName = user.name.split(' ')[0] ?? user.name

  useEffect(() => {
    if (!open) return
    const onPointer = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false)
    }
    document.addEventListener('mousedown', onPointer)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onPointer)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  const close = () => setOpen(false)

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-haspopup="menu"
        aria-label="Account menu"
        className={cn(
          'inline-flex h-9 max-w-[11rem] items-center gap-2 rounded-full border py-1 pl-1 pr-2.5 text-left transition',
          open || profileActive
            ? 'border-mint/25 bg-mint/[0.08] text-mint'
            : 'border-line bg-surface text-text hover:border-mint/25 hover:bg-surface-2',
        )}
      >
        <UserAvatar name={user.name} size={30} ring={false} />
        <span className="hidden min-w-0 truncate text-xs font-semibold sm:inline">{firstName}</span>
        <ChevronDownIcon size={14} className={cn('shrink-0 transition-transform', open && 'rotate-180')} />
      </button>

      {open && (
        <div
          role="menu"
          className="absolute right-0 top-[calc(100%+0.5rem)] z-50 w-72 overflow-hidden rounded-2xl border border-line bg-surface shadow-[0_18px_44px_-22px_rgba(16,40,33,0.45)]"
        >
          <div className="flex items-center gap-3 border-b border-line px-4 py-3.5">
            <UserAvatar name={user.name} size={44} />
            <div className="min-w-0">
              <p className="truncate font-semibold text-text">{user.name}</p>
              <p className="truncate text-xs text-muted">{user.email}</p>
            </div>
          </div>

          <div className="p-1.5">
            <MenuLink
              href="/profile"
              Icon={UserIcon}
              label="Profile & KYC"
              hint="Identity, limits & account"
              active={profileActive}
              onClick={() => {
                close()
                onNavigate?.()
              }}
            />
            <MenuLink
              href="/pin"
              Icon={LockIcon}
              label="Transaction PIN"
              hint={needsPin ? 'Required — set your PIN' : 'Manage your 4-digit PIN'}
              warn={needsPin}
              onClick={() => {
                close()
                onNavigate?.()
              }}
            />
          </div>

          <div className="border-t border-line p-1.5">
            <button
              type="button"
              role="menuitem"
              onClick={() => {
                close()
                router.post('/logout')
              }}
              className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold text-danger transition hover:bg-danger/5"
            >
              <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-danger/10 text-danger">
                <UndoIcon size={16} />
              </span>
              <span>
                <span className="block">Sign out</span>
                <span className="block text-xs font-normal text-muted">End this session</span>
              </span>
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

function MenuLink({
  href,
  Icon,
  label,
  hint,
  active = false,
  warn = false,
  onClick,
}: {
  href: string
  Icon: (p: { size?: number; className?: string }) => JSX.Element
  label: string
  hint: string
  active?: boolean
  warn?: boolean
  onClick: () => void
}) {
  return (
    <Link
      href={href}
      role="menuitem"
      onClick={onClick}
      className={cn(
        'flex items-start gap-3 rounded-xl px-3 py-2.5 transition',
        active ? 'bg-mint/[0.1] text-mint' : 'text-text hover:bg-surface-2',
      )}
    >
      <span
        className={cn(
          'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
          active ? 'bg-mint/15 text-mint' : warn ? 'bg-amber/15 text-amber' : 'bg-surface-2 text-muted',
        )}
      >
        <Icon size={16} />
      </span>
      <span className="min-w-0">
        <span className="block text-sm font-semibold">{label}</span>
        <span className={cn('block text-xs', warn && !active ? 'text-amber' : 'text-muted')}>{hint}</span>
      </span>
    </Link>
  )
}
