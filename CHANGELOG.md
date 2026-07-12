# Changelog

All notable changes to Reton are documented here, following [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

Product of **RETON PTE LTD** · Founder & CEO: **Gabriel Rotimi Mogaji**

## [Unreleased]

### Added
- **BVN verification for funding** — Tier 2 unlock via payment-rail OTP; alternate identity providers remain available when configured.
- **Instant toasts** — success and error feedback as soon as Inertia finishes a request.
- **Office address** — Contact page and footer list 7, Greenland Estate, Ikorodu, Lagos State, Nigeria.
- **Smooth onboarding** — paginated login/signup, email verification, HTML mail, and a short wizard (welcome → PIN → fund).
- **Admin site settings** — email, SEO, security headers, auth rate limits, robots/sitemap — editable under Admin → Site; SMTP secrets encrypted at rest.
- **Admin platform settings** — business rules and integration credentials from the dashboard; encrypted at rest, masked in UI, audited, with env fallbacks until saved.
- **Identity verification** — BVN/NIN checks via gateway abstraction (fake + HTTP), consent, rate limits, and audit logs without storing raw identifiers in plain text.
- **AI Customer Support** — in-app chat with transaction lookup, protection guidance, recovery help, and ticket escalation.
- **Platform admin** — configurable admin path, encrypted integrations, audit logs, and a control-center dashboard.
- **Virtual cards** — issuing abstraction with issue, fund, and freeze flows.
- **Physical marketplace** — shipments, hub verification, item codes, logistics webhook sync.
- **Withdraw & receive** — dedicated bank cash-out and inbound funding flows.
- **Public Get the App** — centered Coming Soon App Store / Google Play badges above the footer.
- **Dashboard refresh** — clearer balance hero, KYC badge, trust sidebar, and mobile-first layout.

### Changed
- **PIN** — transaction PIN is exactly four digits across onboarding, settings, and payment confirmation.
- **BVN for funding** — Add Money and Profile share the same OTP gate; static deposit account CTA waits until BVN is verified.
- **Payment HTTP client** — shorter timeouts, clearer provider errors, and credential checks before BVN provision.
- **Customer-facing copy** — provider brand names kept in admin; user surfaces stay product-first.
- **Withdraw** — gated Coming Soon by default until production payout is ready.

### Fixed
- Dashboard crash for new users when Getting Started passed the wrong icon prop.
- Production-wide “Something went wrong” from mounting the toast host outside Inertia’s page context.
- Fake BVN confirm failing across HTTP requests (OTP state now cache-backed).
- Null/partial list props on Protection, Marketplace, Bills, Add Money, Withdraw, and Support no longer blank the page.
- Admin App Settings platform link no longer called a hooks helper from JSX.
- Admin route conflict — user `/dashboard` registers ahead of the configurable admin prefix.
- Shared button and header layout polish.

### Configuration
- `KYC_BVN_PROVIDER` and integration settings — see `.env.example` and Admin → Integrations.
- Never commit live API keys, webhook secrets, or production `.env` files.

## [2026-06-30]

### Added
#### Digital marketplace
- **Protected digital sales** — sellers list digital items; buyers pay with Reton protection until delivery is confirmed or a dispute is resolved.
- **Order lifecycle** — paid and held → seller delivers → buyer confirms → completed, with structured dispute paths.
- **Escrow guidance** — step labels, dispute eligibility, seller trust score, and deadlines on each order card.
- **Auto-refund on missed delivery** — scheduler refunds buyers when the seller misses the delivery deadline with no open callback.
- **Shareable listing links** — canonical `/l/{uuid}` URLs for chat apps; copy, native share, and QR for sellers.
- **Mobile deep-link readiness** — public URL helpers and `/.well-known` association files for future store apps.
- **Guest listing pages** — preview without signing in; login/register returns to the same listing.

#### Protected transfers & wallet
- **Pending receiver balance** — protected transfers debit the sender immediately and credit the receiver as pending until release or refund.
- **Wallet hold/release** — hold, release, and reverse paths for protected settlement.
- **Dashboard balance UX** — Available to spend; pending incoming funds called out separately.

#### Protection center & trust
- **Structured marketplace disputes** — category-specific rules and callback integration.
- **Live trust updates** — Reverb broadcasts; Dashboard and Protection refresh without a manual reload.
- **Protection UI overhaul** — clearer held-transfer, callback, and recovery flows.

#### Platform & developer experience
- **Laravel Reverb** — WebSocket broadcasting for trust-protection events.
- **Laravel Horizon** — queue dashboard for Redis deployments.
- **Windows-native dev path** — `composer dev` without Docker or Redis.
- **React Hook Form + Zod** — shared schemas; UI primitives under `resources/js/components/ui/`.
- **Tests** — marketplace, share links, auto-refund, protected-transfer behaviour, and broadcasting coverage.

### Changed
- **Send flow** — protected transfer copy explains pending receiver funds and recall until release.
- **Create listing** — after publish, sellers land on the share page with link and QR tools.
- **Login / register** — optional `?redirect=` preserves return URL from shared listing links.
- **Auto-release scheduler** — respects receiver pending balance.

### Configuration
New environment variables (see `.env.example` — leave secrets empty in git):
- Marketplace timing and public listing URL settings
- Mobile deep-link identifiers
- Broadcasting / Reverb settings

### Fixed
- Blank authenticated pages from a missing icon import in the app shell.
- Marketplace API exceptions and form request declarations for digital order endpoints.

---

© 2026 RETON PTE LTD. All rights reserved.
