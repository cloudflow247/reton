import { useId, type ReactNode, type SVGProps } from 'react'

type P = SVGProps<SVGSVGElement> & { size?: number }

function Svg({ size = 24, children, viewBox = '0 0 48 48', ...props }: P & { children: ReactNode }) {
  return (
    <svg width={size} height={size} viewBox={viewBox} fill="none" aria-hidden {...props}>
      {children}
    </svg>
  )
}

/** Official MTN mark — sunshine yellow + oval outline + MTN wordmark (2022 refresh). */
export function MtnMark({ size = 24, ...p }: P) {
  return (
    <Svg size={size} {...p}>
      <rect width="48" height="48" rx="14" fill="#FFCB05" />
      <ellipse cx="24" cy="24" rx="17.5" ry="10.8" stroke="#000" strokeWidth="2.4" fill="none" />
      <text
        x="24"
        y="28.5"
        textAnchor="middle"
        fill="#000"
        fontSize="10.8"
        fontWeight="900"
        fontFamily="'Arial Black', 'Helvetica Neue', system-ui, sans-serif"
        letterSpacing="0.6"
      >
        MTN
      </text>
    </Svg>
  )
}

/** Globacom Glo — official green tile with lowercase glo wordmark. */
export function GloMark({ size = 24, ...p }: P) {
  return (
    <Svg size={size} {...p}>
      <rect width="48" height="48" rx="14" fill="#00A859" />
      <text
        x="24"
        y="31"
        textAnchor="middle"
        fill="#fff"
        fontSize="18"
        fontWeight="800"
        fontFamily="'Arial Rounded MT Bold', 'Nunito', system-ui, sans-serif"
        letterSpacing="-0.5"
      >
        glo
      </text>
      {/* Plus inside the g — signature Glo mark detail */}
      <path d="M11.2 24h3.6M13 22.2v3.6" stroke="#00A859" strokeWidth="1.5" strokeLinecap="round" />
    </Svg>
  )
}

/** Airtel Africa — red tile with the official Wave symbol. */
const AIRTEL_WAVE =
  'M7.137 23.862c.79 0 1.708-.19 2.751-.554 1.55-.538 2.784-1.281 3.986-2.009l.316-.205a29.733 29.733 0 0 0 3.764-2.72 16.574 16.574 0 0 0 5.457-7.529c.395-1.138.949-3.384.268-5.487a7.117 7.117 0 0 0-2.862-3.749c-.158-.126-1.898-1.47-5.203-1.47-3.005 0-6.31 1.107-9.806 3.32l-.11.08-.317.205a20.133 20.133 0 0 0-2.309 1.693C1.585 6.813-.091 9.106.004 11.067c.031.79.427 1.534 1.075 2.008a3.472 3.472 0 0 0 2.214.68c1.803 0 3.765-.948 5.109-1.74l.253-.157.696-.443.237-.158c1.898-1.234 3.875-2.515 6.105-3.258a5.255 5.255 0 0 1 1.55-.285 3.163 3.163 0 0 1 .664.08 2.112 2.112 0 0 1 1.47 1.106c.523 1.012.396 2.61-.316 4.08a17.871 17.871 0 0 1-4.887 5.836 19.488 19.488 0 0 1-3.194 2.215l-.095.031a9.634 9.634 0 0 1-1.471.696l-.08.032-.41.158c-2.23.57-.87-1.329-.87-1.329.474-.537.98-1.028 1.518-1.502.316-.269.633-.554.933-.854l.064-.063c.395-.38.933-.902.901-1.645-.047-.98-1.075-1.582-2.056-1.613h-.063c-.95 0-1.819.522-2.404.98a7.27 7.27 0 0 0-1.598 1.74c-.6.901-1.85 3.226-.632 5.077.49.743 1.313 1.123 2.42 1.123z'

export function AirtelMark({ size = 24, ...p }: P) {
  return (
    <Svg size={size} {...p}>
      <rect width="48" height="48" rx="14" fill="#E40000" />
      <g transform="translate(6.5, 6.5) scale(1.55)">
        <path fill="#fff" d={AIRTEL_WAVE} />
      </g>
    </Svg>
  )
}

/** T2 (formerly 9mobile) — 2025 rebrand: vibrant orange + bold T2 wordmark. */
export function T2Mark({ size = 24, ...p }: P) {
  const gid = useId().replace(/:/g, '')
  return (
    <Svg size={size} {...p}>
      <defs>
        <linearGradient id={`t2-bg-${gid}`} x1="0" y1="0" x2="48" y2="48">
          <stop stopColor="#FF7A1A" />
          <stop offset="1" stopColor="#F04E00" />
        </linearGradient>
      </defs>
      <rect width="48" height="48" rx="14" fill={`url(#t2-bg-${gid})`} />
      <text
        x="24"
        y="31"
        textAnchor="middle"
        fill="#fff"
        fontSize="20"
        fontWeight="900"
        fontFamily="'Helvetica Neue', system-ui, sans-serif"
        letterSpacing="-0.8"
      >
        T2
      </text>
    </Svg>
  )
}

/** MultiChoice DStv — signature blue with white wordmark. */
export function DstvMark({ size = 24, ...p }: P) {
  return (
    <Svg size={size} {...p}>
      <rect width="48" height="48" rx="14" fill="#0095DA" />
      <path
        d="M8 30c6-8 14-12 22-12s14 4 18 10"
        stroke="#fff"
        strokeWidth="2.5"
        strokeLinecap="round"
        fill="none"
        opacity="0.35"
      />
      <text
        x="24"
        y="30"
        textAnchor="middle"
        fill="#fff"
        fontSize="13.5"
        fontWeight="800"
        fontFamily="'Helvetica Neue', system-ui, sans-serif"
        fontStyle="italic"
      >
        DStv
      </text>
    </Svg>
  )
}

/** GOtv — lime green with dark GOtv wordmark. */
export function GotvMark({ size = 24, ...p }: P) {
  return (
    <Svg size={size} {...p}>
      <rect width="48" height="48" rx="14" fill="#76BC21" />
      <text
        x="24"
        y="30"
        textAnchor="middle"
        fill="#1A472A"
        fontSize="13"
        fontWeight="900"
        fontFamily="'Helvetica Neue', system-ui, sans-serif"
      >
        GOtv
      </text>
    </Svg>
  )
}

/** StarTimes — red tile with gold star. */
export function StartimesMark({ size = 24, ...p }: P) {
  return (
    <Svg size={size} {...p}>
      <rect width="48" height="48" rx="14" fill="#ED1C24" />
      <path
        d="M24 11l3.2 6.5 7.2 1-5.2 5.1 1.2 7.1L24 27l-6.4 3.7 1.2-7.1-5.2-5.1 7.2-1L24 11z"
        fill="#FFD200"
      />
    </Svg>
  )
}

/** Showmax — black tile with red play mark. */
export function ShowmaxMark({ size = 24, ...p }: P) {
  return (
    <Svg size={size} {...p}>
      <rect width="48" height="48" rx="14" fill="#0A0A0A" />
      <path d="M19 15l14 9-14 9V15z" fill="#E50914" />
    </Svg>
  )
}

function DiscoMark({ size = 24, bg, abbr, ...p }: P & { bg: string; abbr: string }) {
  return (
    <Svg size={size} {...p}>
      <rect width="48" height="48" rx="14" fill={bg} />
      <path
        d="M24 11l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L24 25.5l-5.6 2.9 1.1-6.2-4.5-4.4 6.2-.9L24 11z"
        fill="#fff"
        fillOpacity="0.95"
      />
      <text x="24" y="41" textAnchor="middle" fill="#fff" fontSize="8.5" fontWeight="700" fontFamily="system-ui,sans-serif" opacity="0.92">
        {abbr}
      </text>
    </Svg>
  )
}

export const IkedcMark = (p: P) => <DiscoMark bg="#E11B22" abbr="IKEJA" {...p} />
export const EkedcMark = (p: P) => <DiscoMark bg="#5B2D8E" abbr="EKO" {...p} />
export const IbedcMark = (p: P) => <DiscoMark bg="#0a7a3b" abbr="IBADAN" {...p} />
export const AedcMark = (p: P) => <DiscoMark bg="#0b5cab" abbr="ABUJA" {...p} />
export const PhedMark = (p: P) => <DiscoMark bg="#1b4f9c" abbr="PH" {...p} />
export const KedcoMark = (p: P) => <DiscoMark bg="#16923c" abbr="KANO" {...p} />

export function RemitaMark({ size = 24, ...p }: P) {
  return (
    <Svg size={size} {...p}>
      <rect width="48" height="48" rx="14" fill="#1e3a5f" />
      <path d="M14 18h20M14 24h14M14 30h18" stroke="#fff" strokeWidth="2.5" strokeLinecap="round" />
    </Svg>
  )
}

export function GenericBillerMark({ size = 24, label = '?', bg = '#64748b', ...p }: P & { label?: string; bg?: string }) {
  return (
    <Svg size={size} {...p}>
      <rect width="48" height="48" rx="14" fill={bg} />
      <text x="24" y="30" textAnchor="middle" fill="#fff" fontSize="14" fontWeight="700" fontFamily="system-ui,sans-serif">
        {label.slice(0, 3).toUpperCase()}
      </text>
    </Svg>
  )
}

export type BillerBrandId =
  | 'mtn'
  | 'glo'
  | 'airtel'
  | 't2'
  | '9mobile'
  | 'dstv'
  | 'gotv'
  | 'startimes'
  | 'showmax'
  | 'ikedc'
  | 'ekedc'
  | 'ibedc'
  | 'aedc'
  | 'phed'
  | 'kedco'
  | 'remita'
  | 'sportybet'
  | 'bet9ja'
  | 'betking'
  | 'nairabet'

const MARKS: Record<BillerBrandId, (p: P) => React.JSX.Element> = {
  mtn: MtnMark,
  glo: GloMark,
  airtel: AirtelMark,
  t2: T2Mark,
  '9mobile': T2Mark,
  dstv: DstvMark,
  gotv: GotvMark,
  startimes: StartimesMark,
  showmax: ShowmaxMark,
  ikedc: IkedcMark,
  ekedc: EkedcMark,
  ibedc: IbedcMark,
  aedc: AedcMark,
  phed: PhedMark,
  kedco: KedcoMark,
  remita: RemitaMark,
  sportybet: (p) => <GenericBillerMark label="SB" bg="#E90003" {...p} />,
  bet9ja: (p) => <GenericBillerMark label="B9" bg="#006837" {...p} />,
  betking: (p) => <GenericBillerMark label="BK" bg="#1E3A8A" {...p} />,
  nairabet: (p) => <GenericBillerMark label="NB" bg="#F59E0B" {...p} />,
}

export function BillerBrandIcon({ brand, size = 44, round }: { brand: string; size?: number; round?: boolean }) {
  const key = (brand === '9mobile' ? 't2' : brand) as BillerBrandId
  const Mark = MARKS[key] ?? ((props: P) => <GenericBillerMark label={brand} {...props} />)

  return (
    <span
      className={`inline-flex shrink-0 overflow-hidden shadow-md ring-1 ring-black/5 transition-transform ${
        round ? 'rounded-full' : 'rounded-xl'
      }`}
      style={{ width: size, height: size }}
    >
      <Mark size={size} width={size} height={size} />
    </span>
  )
}
