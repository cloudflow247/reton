# Changelog

All notable changes to Reton are documented here, following [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- **ALATPay BVN verification** — Tier 2 funding unlock via ALATPay static-wallet OTP (SMS from ALATPay). Dojah remains available for NIN / alternate BVN when configured.
- **Instant toasts** — global success and error feedback that appears as soon as Inertia finishes a request, without waiting to scroll to an inline banner.
- **Office address** — public Contact page and footer list 7, Greenland Estate, Ikorodu, Lagos State, Nigeria.
- **Smooth onboarding** — paginated login/signup with Reton branding, email verification, HTML mail templates, and a short wizard (welcome → PIN → fund).
- **Admin site settings** — email notifications, SEO / Open Graph / JSON-LD, security headers, auth rate limits, robots/sitemap — editable under Admin → Site; SMTP secrets encrypted at rest.
- **Admin platform settings** — business rules and integration credentials editable from the admin dashboard; encrypted at rest with masked secrets, audit logs, and env fallbacks until saved.
- **Dojah KYC** — BVN/NIN checks via gateway abstraction (fake + HTTP), consent, rate limits, and audit logs without storing raw identifiers in plain text.
- **AI Customer Support** — in-app chat at `/support` with transaction lookup, protection guidance, recovery help, trust score, and ticket escalation (`TKT-…`).
- **Platform admin** — secret admin path, encrypted integrations, audit logs, and a control-center dashboard.
- **Virtual cards** — Bridgecard/Interswitch abstraction with issue, fund, and freeze flows.
- **Physical marketplace** — shipments, hub verification, item codes, Giglogistics webhook sync.
- **Withdraw & receive** — dedicated bank cash-out and inbound funding flows.
- **Dashboard refresh** — clearer balance hero, KYC badge, trust sidebar, and mobile-first layout.

### Changed
- **PIN** — transaction PIN is exactly four digits across onboarding, settings, and payment confirmation.
- **BVN for funding** — Add Money and Profile share the same OTP gate; static deposit account CTA waits until BVN is verified.
- **ALATPay HTTP client** — shorter timeouts, clearer provider error messages, and credential checks before BVN provision.

### Fixed
- Dashboard crash for new users when Getting Started passed the wrong icon prop to `StepRow`.
- Production-wide “Something went wrong” caused by mounting the toast host outside Inertia’s page context.
- Fake ALATPay BVN confirm failing across HTTP requests (wallet OTP state now cache-backed).
- Null/partial list props on Protection, Marketplace, Bills, Add Money, Withdraw, and Support no longer blank the page.
- Admin App Settings “Platform” link no longer called a hooks helper from JSX.
- Admin route conflict — user `/dashboard` registers ahead of the configurable admin prefix.
- Bill payment RRR tests respect injected fake providers; Remita reconcile routes correctly.
- Shared button and header layout polish (icons, wordmark, Sign out wrapping).

### Configuration
- `KYC_BVN_PROVIDER` — `alatpay` (default) or `dojah`.
- `DOJAH_*`, Bridgecard, Interswitch, Giglogistics, Termii, and admin-path settings — see `.env.example` and Admin → Integrations.

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
