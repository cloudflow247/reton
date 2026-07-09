import { cva, type VariantProps } from 'class-variance-authority'
import type { ButtonHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

const buttonVariants = cva(
  'btn inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint/40 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0',
  {
    variants: {
      variant: {
        default:
          'bg-mint text-white shadow-[0_10px_24px_-14px_rgba(9,79,57,0.65)] hover:bg-mint-strong',
        secondary: 'border border-line bg-surface text-text hover:border-mint/35 hover:text-mint',
        ghost: 'border border-transparent text-muted hover:bg-surface-2 hover:text-text',
        danger: 'bg-danger/10 text-danger border border-danger/25 hover:bg-danger/15',
      },
      size: {
        default: 'px-4 py-2.5 text-sm',
        sm: 'px-3 py-1.5 text-xs',
        lg: 'px-5 py-3 text-base',
        icon: 'h-9 w-9 gap-0 p-0',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
)

export type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> &
  VariantProps<typeof buttonVariants>

export function Button({ className, variant, size, ...props }: ButtonProps) {
  return <button className={cn(buttonVariants({ variant, size }), className)} {...props} />
}

export { buttonVariants }
