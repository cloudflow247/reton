# Changelog

All notable changes to Reton are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- **Admin site settings** — email notifications (`support@retonpay.com` default), SEO / Open Graph / JSON-LD, security headers (HSTS, CSP, frame options), auth rate limits, and robots/sitemap — all editable from admin → Site; SMTP secrets encrypted at rest.
- **Admin platform settings** — all `RETON_*` business rules and integration credentials (Dojah, Remita, KYC tiers, fraud, callback/recovery windows, marketplace timing, FX/cards, Horizon access) editable from the admin dashboard; encrypted at rest with masked secrets, audit logs, and env fallbacks until saved.
- **Dojah KYC verification** — BVN and NIN identity checks via `KycVerificationGateway` (fake sandbox + HTTP production), name/DOB matching, consent requirement, rate limiting, and audit logs without storing raw identifiers.
- **AI Customer Support** — in-app chat at `/support` with rule-based assistant: transaction lookup by reference (TRF-, DEP-, CBK-, RCV-, BILL-, PO-), callback protection explanations, wrong-transfer recovery guidance, live trust score, and human escalation via support tickets (`TKT-…`). Open tickets surface on the admin dashboard.
- **Platform admin** — secret admin path, encrypted integration settings, audit logs, promote/revoke admins, and control-center dashboard (ALATPay, Interswitch, Giglogistics, Dojah health).
- **Virtual cards** — Bridgecard/Interswitch gateway abstraction, issue/fund/freeze flows, and Cards UI.
- **Physical marketplace** — shipments, hub verification, item codes, and Giglogistics webhook sync.
- **Withdraw & receive** — dedicated web flows for bank cash-out and inbound funding.
- **Dashboard UI refresh** — shadcn-style cards, responsive 8/4 desktop grid, mobile-first balance hero, KYC tier badge, compliance posture strip, and trust sidebar.
- **Explanatory desktop nav** — labeled primary actions (Home, Send, Withdraw, Bills, Cards), nested **More** menu (Activity, Shop, Protection), and top-right **profile avatar menu** with Profile, PIN, and Sign out.

### Configuration
- `DOJAH_DRIVER`, `DOJAH_BASE_URL`, `DOJAH_APP_ID`, `DOJAH_SECRET_KEY` — identity verification (see `.env.example`).
- Bridgecard, Interswitch, Giglogistics, and admin-path settings documented in `.env.example` / admin Integrations.

### Fixed
- Profile KYC forms no longer crash for Tier 1 users (Inertia `useForm` instead of react-hook-form `register`).
- Bill payment RRR tests now respect injected fake provider instances.
- Buttons no longer stack icon above label — shared `.btn` uses horizontal flex, consistent radius, and focus rings.
- Header nav no longer overlaps the Reton wordmark; Sign out no longer wraps onto two lines.
- Auto-refund no longer skips the physical-shipment guard (unreachable `PaidHeld` double-check removed).
- Bill `reconcile()` routes Remita RRR bills to the Remita gateway instead of the default Interswitch provider.
- Generic callbacks on shipped / awaiting-verification physical orders no longer always throw `disputeNotAllowed()`.
- Redundant `preg_replace(...) ?? ''` coalescing removed from KYC and Interswitch phone/reference helpers.

## [2026-06-30]

### Added
#### Digital marketplace
- **Protected digital sales** — sellers list digital items; buyers pay with Reton protection until delivery is confirmed or a dispute is resolved.
- **Order lifecycle** — `paid_held` → seller delivers → buyer confirms → `completed`, with structured dispute paths (`not_delivered`, `not_as_described`, `invalid_item`).
- **Escrow guidance** — step labels, dispute eligibility, seller trust score, and delivery / confirmation deadlines on each order card.
- **Auto-refund on missed delivery** — scheduler command `marketplace:expire-undelivered` refunds buyers when the seller passes the delivery deadline (default 72h) with no open callback.
- **Shareable listing links** — canonical URLs at `/l/{uuid}` for WhatsApp and social DMs; copy, native share, and QR on seller listing pages.
- **Mobile deep-link readiness** — `ListingLinks` helper, `RETON_PUBLIC_URL`, custom scheme `reton://l/{uuid}`, and `/.well-known` association files for future iOS Universal Links and Android App Links.
- **Guest listing pages** — buyers can preview a listing without signing in; login/register redirects back to the same listing to purchase.
- **Demo marketplace seed** — sample listings for demo accounts (Ada / Bola).

#### Protected transfers & wallet
- **Pending receiver balance** — protected transfers debit the sender immediately and credit the receiver as **pending** (`held_balance`) until release or refund.
- **Wallet hold/release** — `holdIncoming`, `releaseIncomingHold`, and `reverseProtected` on `WalletService` for protected settlement.
- **Dashboard balance UX** — hero shows **Available to spend**; pending incoming funds appear as an amber pill with **Total in wallet** when relevant.

#### Protection center & trust
- **Structured marketplace disputes** — category-specific rules, grace periods, and callback integration via `DigitalEscrowJudgementService`.
- **Live trust updates** — Reverb broadcasts trust/protection changes; Dashboard and Protection pages refresh without a manual reload.
- **Protection UI overhaul** — clearer held-transfer, callback, and recovery flows with escrow steppers on digital orders.

#### Platform & developer experience
- **Laravel Reverb** — WebSocket broadcasting for trust-protection events (`config/reverb.php`, `TrustProtectionListener`).
- **Laravel Horizon** — queue dashboard for Redis deployments (`HorizonServiceProvider`, Docker compose service).
- **Windows-native dev path** — `composer dev` runs app, database queue worker, Reverb, Pail, and Vite without Docker or Redis.
- **React Hook Form + Zod** — shared schemas for auth, send, and marketplace forms; shadcn-style UI primitives under `resources/js/components/ui/`.
- **Dashboard summary API** — aggregated trust metrics for the home screen.
- **Tests** — marketplace, share links, auto-refund scheduler, protected-transfer wallet behaviour, and broadcasting coverage (282 tests).

### Changed
- **Send flow** — protected transfer option explains pending receiver funds and recall until release (Reton-native copy, no third-party brand references).
- **Create listing flow** — after publish, sellers land on the share page with link and QR tools.
- **Login / register** — optional `?redirect=` query preserves return URL (e.g. back to a listing link from WhatsApp).
- **Auto-release scheduler** — respects receiver pending balance instead of legacy central escrow assumptions.
- **README** — Windows, Linux/macOS, Reverb, and Horizon setup notes.

### Configuration
New environment variables (see `.env.example`):
- `RETON_DIGITAL_CONFIRM_HOURS`, `RETON_DIGITAL_DELIVERY_DEADLINE_HOURS`, `RETON_DIGITAL_DISPUTE_GRACE_HOURS`
- `RETON_PUBLIC_URL`, `RETON_LISTING_PATH`, `RETON_APP_SCHEME`
- `RETON_IOS_BUNDLE_ID`, `RETON_APPLE_TEAM_ID`, `RETON_ANDROID_PACKAGE`, `RETON_ANDROID_SHA256` (mobile deep links)
- `BROADCAST_CONNECTION`, `REVERB_*`, `VITE_REVERB_*`

### Fixed
- Blank authenticated pages caused by a missing `ShieldIcon` import in `AppShell`.
- Marketplace API exceptions now extend `DomainException` correctly.
- Form request class declarations for digital order confirm/deliver endpoints.
