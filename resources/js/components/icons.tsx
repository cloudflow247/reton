import type { SVGProps } from 'react'

type IconProps = SVGProps<SVGSVGElement> & { size?: number }

function Svg({ size = 20, children, ...props }: IconProps & { children: React.ReactNode }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.7}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden
      {...props}
    >
      {children}
    </svg>
  )
}

export const HomeIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M3.5 10.5 12 3.5l8.5 7" />
    <path d="M5.5 9.5V20h13V9.5" />
    <path d="M9.75 20v-4.5h4.5V20" />
  </Svg>
)

export const SendIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M8 16 16 8" />
    <path d="M9 8h7v7" />
  </Svg>
)

export const ReceiveIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M16 8 8 16" />
    <path d="M15 16H8V9" />
  </Svg>
)

export const PlusIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M12 5.5v13M5.5 12h13" />
  </Svg>
)

export const ActivityIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M3.5 12.5h4l2.5-6 4 12 2.5-6h4" />
  </Svg>
)

export const UserIcon = (p: IconProps) => (
  <Svg {...p}>
    <circle cx="12" cy="8.5" r="3.5" />
    <path d="M5.5 19.5a6.5 6.5 0 0 1 13 0" />
  </Svg>
)

export const ShieldIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M12 3.5 5 6v5.5c0 4.4 3 7.4 7 9 4-1.6 7-4.6 7-9V6l-7-2.5z" />
    <path d="M9.2 12.2 11 14l3.6-4" />
  </Svg>
)

export const CopyIcon = (p: IconProps) => (
  <Svg {...p}>
    <rect x="9" y="9" width="11" height="11" rx="2.5" />
    <path d="M5.5 15H5a1.5 1.5 0 0 1-1.5-1.5V5A1.5 1.5 0 0 1 5 3.5h8.5A1.5 1.5 0 0 1 15 5v.5" />
  </Svg>
)

export const CheckIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M4.5 12.5 9 17l10.5-10.5" />
  </Svg>
)

export const ChevronRightIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M9 5.5 15.5 12 9 18.5" />
  </Svg>
)

export const ArrowRightIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M4.5 12h15M13 5.5 19.5 12 13 18.5" />
  </Svg>
)

export const LockIcon = (p: IconProps) => (
  <Svg {...p}>
    <rect x="5" y="10.5" width="14" height="9.5" rx="2.5" />
    <path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" />
  </Svg>
)

export const UndoIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M4 12a8 8 0 1 0 .9-3.7" />
    <path d="M4 4v4.6h4.6" />
  </Svg>
)

export const BillIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M6 3.5h12v17l-2-1.3-2 1.3-2-1.3-2 1.3-2-1.3-2 1.3z" />
    <path d="M9 8.5h6M9 12h6" />
  </Svg>
)

export const ScanIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M4 8V5.6A1.6 1.6 0 0 1 5.6 4H8" />
    <path d="M16 4h2.4A1.6 1.6 0 0 1 20 5.6V8" />
    <path d="M20 16v2.4a1.6 1.6 0 0 1-1.6 1.6H16" />
    <path d="M8 20H5.6A1.6 1.6 0 0 1 4 18.4V16" />
    <path d="M4 12h16" />
  </Svg>
)
