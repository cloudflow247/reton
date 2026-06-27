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

export const WalletIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H17a2 2 0 0 1 2 2v.5" />
    <rect x="4" y="7.5" width="16" height="12" rx="2.5" />
    <path d="M15.5 13.5h2.8" />
    <circle cx="15.5" cy="13.5" r="0.4" fill="currentColor" stroke="none" />
  </Svg>
)

export const BankIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M3.5 9.5 12 4l8.5 5.5" />
    <path d="M5 9.5h14" />
    <path d="M6 12v6M10 12v6M14 12v6M18 12v6" />
    <path d="M4 20.5h16" />
  </Svg>
)

export const BoltIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M13 3 5 13h6l-1 8 8-10h-6l1-8z" />
  </Svg>
)

export const ClockIcon = (p: IconProps) => (
  <Svg {...p}>
    <circle cx="12" cy="12" r="8.5" />
    <path d="M12 7.5V12l3 2" />
  </Svg>
)

export const EyeIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" />
    <circle cx="12" cy="12" r="3" />
  </Svg>
)

export const EyeOffIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M9.6 5.8A9.5 9.5 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16 16 0 0 1-3 3.6" />
    <path d="M6.3 7.7A16 16 0 0 0 2.5 12S6 18.5 12 18.5a9.4 9.4 0 0 0 4-.9" />
    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
    <path d="M4 4l16 16" />
  </Svg>
)

export const MailIcon = (p: IconProps) => (
  <Svg {...p}>
    <rect x="3.5" y="5.5" width="17" height="13" rx="2.5" />
    <path d="M4 7l8 5.5L20 7" />
  </Svg>
)

export const PhoneIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M6.5 4.5h3l1.5 4-2 1.5a11 11 0 0 0 5 5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A15.5 15.5 0 0 1 4.5 6.7 2 2 0 0 1 6.5 4.5z" />
  </Svg>
)

export const ChatIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v6A2.5 2.5 0 0 1 17.5 15H9l-4 4v-4H6.5" />
  </Svg>
)

export const QrIcon = (p: IconProps) => (
  <Svg {...p}>
    <rect x="4" y="4" width="6" height="6" rx="1.2" />
    <rect x="14" y="4" width="6" height="6" rx="1.2" />
    <rect x="4" y="14" width="6" height="6" rx="1.2" />
    <path d="M14 14h2.5v2.5M20 14v.01M14 20h2M19.5 17.5V20h.01" />
  </Svg>
)

export const ShareIcon = (p: IconProps) => (
  <Svg {...p}>
    <circle cx="6.5" cy="12" r="2.5" />
    <circle cx="17.5" cy="6" r="2.5" />
    <circle cx="17.5" cy="18" r="2.5" />
    <path d="M8.8 10.8 15.2 7.2M8.8 13.2l6.4 3.6" />
  </Svg>
)

export const SparkleIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M12 4l1.6 4.8L18.5 10l-4.9 1.6L12 16.5l-1.6-4.9L5.5 10l4.9-1.2L12 4z" />
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

export const BellIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M6 9.5a6 6 0 0 1 12 0c0 4 1.2 5.5 1.8 6.2.4.5 0 1.3-.7 1.3H4.9c-.7 0-1.1-.8-.7-1.3C4.8 15 6 13.5 6 9.5z" />
    <path d="M10 20a2 2 0 0 0 4 0" />
  </Svg>
)

export const SignalIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M5 19.5v-3M9.7 19.5v-6M14.3 19.5v-9M19 19.5v-12" />
  </Svg>
)

export const TvIcon = (p: IconProps) => (
  <Svg {...p}>
    <rect x="3.5" y="6" width="17" height="11" rx="2.5" />
    <path d="M8.5 20.5h7M9 6 12 3.5 15 6" />
  </Svg>
)

export const GiftIcon = (p: IconProps) => (
  <Svg {...p}>
    <rect x="4" y="9.5" width="16" height="11" rx="1.6" />
    <path d="M3 9.5h18M12 9.5v11" />
    <path d="M12 9.5S10.5 4.5 8 5.2 9.5 9.5 12 9.5zM12 9.5s1.5-5 4-4.3-.5 4.3-4 4.3z" />
  </Svg>
)

export const TrendIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M4 15.5 9 10l3.5 3.5L20 6" />
    <path d="M15 6h5v5" />
  </Svg>
)

export const GridIcon = (p: IconProps) => (
  <Svg {...p}>
    <rect x="4" y="4" width="6.5" height="6.5" rx="1.6" />
    <rect x="13.5" y="4" width="6.5" height="6.5" rx="1.6" />
    <rect x="4" y="13.5" width="6.5" height="6.5" rx="1.6" />
    <rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.6" />
  </Svg>
)

export const PiggyIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M4 12.5c0-3 2.7-5 6.5-5 4 0 7 2.2 7 5.3 0 1.5-.7 2.8-1.9 3.7v2.5h-2.3l-.5-1.3a9 9 0 0 1-4.6 0L7.6 19H5.4v-2.6A5 5 0 0 1 4 13.5z" />
    <path d="M4.5 11.5C3 11.4 3 9 4.7 9.3M10 7.6 11 5.5M14.5 11h.01" />
  </Svg>
)

export const CardIcon = (p: IconProps) => (
  <Svg {...p}>
    <rect x="3" y="5.5" width="18" height="13" rx="2.5" />
    <path d="M3 9.5h18M6.5 14.5h4" />
  </Svg>
)

export const ContactlessIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M9 7a8 8 0 0 1 0 10M12.5 5a11 11 0 0 1 0 14M5.5 9.5a4 4 0 0 1 0 5" />
  </Svg>
)

export const SnowIcon = (p: IconProps) => (
  <Svg {...p}>
    <path d="M12 2.5v19M4 7l16 10M20 7 4 17" />
    <path d="M9 3.5 12 6l3-2.5M9 20.5 12 18l3 2.5M3.5 10 5 7.2 2.3 6.5M20.5 14 19 16.8l2.7.7M3.5 14 2.3 17.5 5 16.8M20.5 10l1.2-3.5L19 7.2" />
  </Svg>
)
