import { cn } from '@/lib/utils'

function avatarInitials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length >= 2) {
    return `${parts[0]![0] ?? ''}${parts[parts.length - 1]![0] ?? ''}`.toUpperCase()
  }

  return (parts[0]?.[0] ?? 'R').toUpperCase()
}

function avatarHue(name: string): number {
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }

  return Math.abs(hash) % 360
}

type UserAvatarProps = {
  name: string
  size?: number
  className?: string
  ring?: boolean
}

export function UserAvatar({ name, size = 36, className, ring = true }: UserAvatarProps) {
  const hue = avatarHue(name)
  const initials = avatarInitials(name)

  return (
    <span
      className={cn(
        'inline-flex shrink-0 items-center justify-center rounded-full font-display font-bold text-white',
        ring && 'ring-2 ring-white/80',
        className,
      )}
      style={{
        width: size,
        height: size,
        fontSize: size * 0.38,
        background: `linear-gradient(145deg, hsl(${hue} 52% 42%) 0%, hsl(${hue} 58% 32%) 100%)`,
        boxShadow: '0 4px 14px -6px rgba(9, 79, 57, 0.45)',
      }}
      aria-hidden
    >
      {initials}
    </span>
  )
}
