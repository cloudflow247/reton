# Reton product & compliance roadmap

**Africa’s trust-first payment platform**  
Last updated: July 2026 · Status: ALATPay Buildathon MVP → production hardening

---

## Executive summary

Reton is not another generic wallet. The product thesis is **trust at the moment money moves**: Callback Protection, wrong-transfer recovery, fraud signals, merchant verification, and a visible timeline on every payment.

This roadmap sequences work from **buildathon demo** → **licensed soft launch** → **compliance-ready operations** → **scale**, aligned with Nigeria’s regulatory reality (CBN KYC tiers, NFIU AML expectations, licensed rails via ALATPay / Wema).

| Horizon | Goal | Primary audience |
|---------|------|------------------|
| **Now (Phase 0)** | Win the buildathon with a polished live demo | Judges, investors |
| **0–3 months (Phase 1)** | Real users on Tier 1, licensed settlement | Early adopters, ALATPay partner |
| **3–9 months (Phase 2)** | Compliance-ready operations | Regulators, bank partner, merchants |
| **9–18 months (Phase 3)** | Category leader in trust-layer payments | Consumers, SMEs, API partners |

---

## Strategic principles

1. **Licensed rails first** — Reton orchestrates trust; settlement and primary AML obligations sit with ALATPay/Wema until Reton holds its own licence.
2. **CBN-style KYC by default** — Tier 1 at ₦50k single / ₦200k daily inflow / ₦300k balance; higher limits only after BVN (ALATPay OTP) and NIN (Dojah) verification.
3. **Never claim full AML compliance** without STR workflow, sanctions screening, and an MLRO — position as an **AML-ready foundation**.
4. **Demo ≠ production** — fake drivers and demo accounts are fine for demos; production needs live KYB on every integration.
5. **Ship trust features people can see** — Callback Protection timeline, recovery cases, fraud alerts, and KYC badges beat feature breadth.

---

## Current state (MVP delivered)

### Core product ✅

| Area | Status | Notes |
|------|--------|-------|
| Authentication & PIN | ✅ | Sanctum, 4-digit transaction PIN, lockout |
| Double-entry wallet | ✅ | Fund, transfer, beneficiaries, statements |
| ALATPay integration | ✅ | Fake + HTTP gateway, webhooks, deposits, BVN OTP |
| **Callback Protection** | ✅ | Protected transfers, hold/release, timeline |
| Wrong-transfer recovery | ✅ | Eligibility rules, hold, admin escalation |
| Fraud engine | ✅ | Rule-based scoring, velocity, holds, alerts |
| Transaction timeline | ✅ | Every money movement logged |
| KYC tiers (1–3) | ✅ | Limits enforced; BVN via ALATPay; NIN via Dojah |
| Bills (Interswitch / Remita) | ✅ | Gateway abstraction, reconcile paths |
| Virtual cards (Bridgecard) | ✅ | Issue, fund, freeze; fake + HTTP |
| Digital marketplace | ✅ | Escrow, disputes, auto-refund scheduler |
| Physical marketplace | ✅ | Giglogistics integration, hub verification |
| AI support chat | ✅ | Rule-based assistant + ticket escalation |
| Admin control panel | ✅ | Encrypted settings, integrations, audit log |

### Admin & configuration ✅

- **Integrations** — ALATPay, Interswitch, Remita, Dojah, Bridgecard, Giglogistics, Termii
- **Platform** — KYC limits, fraud thresholds, callback/recovery windows, marketplace timing
- **Site** — email, SEO, security headers, rate limits
- **App** — Demo mode, admin URL, public URL, mobile deep-link identifiers

### Intentionally deferred ⏸

| Area | Reason |
|------|--------|
| Full AML program (STR, sanctions, MLRO) | Partner-bank model + post-launch |
| Native iOS/Android apps | Deep links scaffolded; apps Phase 3 |
| Biller payment code admin UI | Env/config for now; low demo impact |
| Granular admin roles | Single `is_admin` sufficient for MVP |

---

## Phase 0 — Buildathon demo (now)

**Objective:** Deliver a credible, live-feeling demo that showcases trust differentiation in under 10 minutes.

### Demo stack (recommended)

```env
RETON_DEMO_MODE=true
ALATPAY_DRIVER=fake
DOJAH_DRIVER=fake
BRIDGECARD_DRIVER=fake
INTERSWITCH_DRIVER=fake
GIGLOGISTICS_DRIVER=fake
```

### Demo script (judge flow)

1. **Sign in** as Ada (`ada@demo.retonpay.com` / `demo1234`, PIN `1234`)
2. **Dashboard** — balance, KYC Tier 1 badge, compliance strip, pending protected funds
3. **Send → Protected transfer** to Bola — receiver sees "Payment Protected"
4. **Callback Protection** — sender requests callback; show timeline
5. **Wrong transfer recovery** — report accidental send; case opened
6. **Fraud alert** — trigger velocity or large-amount rule in admin
7. **Support chat** — ask about a transaction reference; escalate ticket
8. **Admin panel** — integration health, recent audit, platform KYC limits (CBN ₦50k)

### Phase 0 checklist

- [ ] Record 3-minute demo video backup
- [ ] Confirm admin path is non-default (`/admin` → custom segment)
- [ ] Turn off demo mode flag messaging if pitching "production-ready"
- [ ] Prepare one-slide compliance narrative (see [Compliance narrative](#compliance-narrative-for-investors))
- [ ] Live Dojah KYB queued (sandbox works today; production ~24–72h + wallet top-up)

**Exit criteria:** Demo runs end-to-end without manual DB edits; all flagship flows have visible UI polish.

---

## Phase 1 — Soft launch on licensed rails (0–3 months)

**Objective:** Real NGN flows for early users via ALATPay production, Tier 1 signup without friction, Tier 2+ via live Dojah.

### 1.1 Payments & wallet

| Task | Priority | Owner |
|------|----------|-------|
| ALATPay production KYB + live API keys in admin Integrations | P0 | Ops + Eng |
| Webhook signature validation hardened; replay/idempotency verified in prod | P0 | Eng |
| Static wallet provisioning per KYC tier (collection vs individual) | P0 | Eng |
| Settlement reconciliation job + admin deposit dashboard | P1 | Eng |
| Withdraw to bank (name enquiry + payout API) | P1 | Eng |

### 1.2 Identity (KYC)

| Task | Priority | Notes |
|------|----------|-------|
| ALATPay production keys for BVN OTP (`ALATPAY_DRIVER=http`) | P0 | Default BVN path for funding |
| Dojah production KYB for NIN / optional BVN | P1 | Tier 3 and alternate provider |
| Tier 1 live on signup (phone/email) — **keep CBN ₦50k limits** | P0 | Already configured |
| Tier 2 BVN flow in Profile / Add Money — production tested | P0 | ALATPay OTP |
| Tier 3 NIN + address — production tested | P1 | Dojah |
| KYC consent copy reviewed by legal | P1 | NDPR alignment |

### 1.3 Integrations (production)

| Integration | Demo | Production gate |
|-------------|------|-----------------|
| ALATPay | fake | Business ID, API key, webhook secret |
| Dojah | fake | App ID, secret key, prepaid wallet |
| Bridgecard | fake | Issuing KYB, sandbox → live |
| Interswitch | fake | Terminal ID, OAuth credentials |
| Giglogistics | fake | API key for physical marketplace |

### 1.4 Operations

| Task | Priority |
|------|----------|
| `RETON_DEMO_MODE=false` in production | P0 |
| Horizon + Redis on Laravel Cloud | P0 |
| Error monitoring (Sentry or equivalent) | P1 |
| On-call runbook for failed webhooks / stuck callbacks | P1 |
| Promote 2+ platform admins; rotate admin URL | P1 |

**Exit criteria:** 100+ real users, ₦10M+ processed via ALATPay, zero unresolved stuck escrow > 7 days, Dojah Tier 2 upgrades working in production.

---

## Phase 2 — Compliance & AML foundation (3–9 months)

**Objective:** Operate credibly with bank partner and regulators — not necessarily as the licensed entity, but with software and processes that satisfy partner due diligence.

### 2.1 AML — what Reton must own vs partner

```
┌─────────────────────────────────────────────────────────────┐
│                    Licensed partner (ALATPay/Wema)           │
│  Primary settlement · STR filing (likely) · Sanctions (?)   │
└───────────────────────────┬─────────────────────────────────┘
                            │ data export · freeze · KYC records
┌───────────────────────────▼─────────────────────────────────┐
│                         Reton platform                       │
│  CDD/KYC tiers · Transaction monitoring · Case management   │
│  Audit logs · User freeze · Compliance export API           │
└─────────────────────────────────────────────────────────────┘
```

**Action:** Sign a responsibilities matrix with ALATPay/Wema (who files STRs, who holds KYC records, who runs sanctions).

### 2.2 AML Phase 1 (software — build)

| Capability | Status today | Phase 2 deliverable |
|------------|--------------|---------------------|
| Customer identification (CDD) | KYC tiers + Dojah | ✅ maintain |
| Transaction limits by tier | ✅ | ✅ maintain |
| Real-time fraud scoring | ✅ rule-based | Extend with AML patterns |
| Sanctions / PEP screening | ❌ | Integrate at Tier 2 upgrade (Dojah or dedicated API) |
| Structuring / smurfing detection | ❌ | Rules: rapid in/out, just-below-limit txs |
| Compliance case queue (admin) | Partial (fraud alerts) | Unified AML + fraud cases, assign, resolve |
| Account/wallet freeze | Partial (fraud hold) | Admin freeze with audit + user notification |
| STR export pack | ❌ | CSV/PDF bundle: user, KYC, tx history for partner MLRO |
| Record retention policy | Partial | 5–7 year retention config + archival |

### 2.3 AML Phase 2 (operations — non-software)

| Item | Deliverable |
|------|-------------|
| AML/CFT policy document | Written policy aligned to CBN + NFIU guidance |
| MLRO designation | Named officer (can be fractional at early stage) |
| Staff AML training | Annual training log |
| Independent review | Partner bank DDQ or external consultant |
| NDPR privacy policy + DPA with vendors | Dojah, Bridgecard, ALATPay |

### 2.4 Extended fraud → AML monitoring rules

Add to `app/Domain/Fraud/` (configurable via admin Platform → Fraud):

- **Structuring** — multiple transfers just under Tier single limit within 24h
- **Rapid cycling** — fund in → transfer out within N minutes (mule pattern)
- **Dormant activation** — account idle 90d then high velocity
- **Geographic/device anomaly** — new country IP + large transfer (when IP available)
- **Merchant concentration** — repeated payments to same unverified merchant

**Exit criteria:** Partner bank compliance sign-off; admin can export a compliance case in < 5 minutes; sanctions check on every Tier 2 upgrade.

---

## Phase 3 — Scale & differentiation (9–18 months)

**Objective:** Reton becomes the trust layer other apps and merchants embed — not just a consumer wallet.

### 3.1 Product

| Initiative | Description |
|------------|-------------|
| **Callback Protection API** | Merchants embed protected checkout; webhook on release/refund |
| **Merchant verification program** | Blue badge, trust score, business profile (KYB) |
| **Reton Pay links** | Protected payment requests via WhatsApp (extend `/l/*`) |
| **Native mobile apps** | iOS/Android using existing deep-link scaffold |
| **Referral & growth** | Trust-score-gated referral rewards |

### 3.2 Platform & admin

| Initiative | Description |
|------------|-------------|
| Role-based admin (compliance, support, ops) | Beyond single `is_admin` |
| Fraud/AML case SLA dashboard | Mean time to resolve, open case aging |
| Analytics & unit economics | CAC, activation, protected-transfer adoption |
| Biller payment code management | Admin UI for Interswitch Quickteller codes |
| Multi-currency expansion | Beyond NGN/USD cards (if licensed) |

### 3.3 Licensing path (optional)

If Reton pursues its own **PSP / MMO / PTSP** license:

- Budget 12–24 months and significant capital
- Full AML program becomes non-delegable
- Direct NFIU STR reporting infrastructure required

**Recommendation:** Stay on partner rails through Phase 2; reassess license only after product-market fit and volume justify it.

---

## Workstream reference

### A. Trust & payments (flagship)

| Milestone | Phase | Status |
|-----------|-------|--------|
| Instant + protected wallet transfers | 0 | ✅ |
| Callback timeline (every event logged) | 0 | ✅ |
| Wrong-transfer recovery | 0 | ✅ |
| Protected digital marketplace escrow | 0 | ✅ |
| Merchant protected checkout API | 3 | Planned |
| Chargeback/dispute SLAs in admin | 2 | Planned |

### B. Integrations

| Provider | Purpose | Admin path | Phase |
|----------|---------|------------|-------|
| ALATPay | Wallet funding, static accounts, webhooks | Integrations → ALATPay | 1 live |
| Dojah | BVN/NIN KYC | Integrations → Dojah | 1 live |
| Bridgecard | Virtual NGN/USD cards | Integrations → Bridgecard | 1 live |
| Interswitch | Bill payments (VAS) | Integrations → Interswitch | 1 live |
| Remita | RRR bill payments | Integrations → Remita | 1 optional |
| Giglogistics | Physical delivery | Integrations → Giglogistics | 1 live |

### C. Compliance & limits

| Setting | Default (CBN-style) | Admin path |
|---------|---------------------|------------|
| Tier 1 single tx | ₦50,000 | Platform → KYC |
| Tier 1 daily inflow | ₦200,000 | Platform → KYC |
| Tier 1 wallet max | ₦300,000 | Platform → KYC |
| Tier 2/3 | Post-BVN/NIN | Platform → KYC + Dojah |

**Decision (locked):** Do not raise Tier 1 to ₦100k without BVN — stay CBN-aligned for launch narrative.

### D. Security (ongoing)

| Control | Status | Next |
|---------|--------|------|
| Encrypted platform settings (APP_KEY) | ✅ | Key rotation runbook |
| BVN/NIN encrypted at rest | ✅ | — |
| Webhook signature validation | ✅ | Pen-test before Phase 1 |
| Idempotency on payment endpoints | ✅ | — |
| Rate limiting (auth + money) | Partial | Extend to all transfer routes |
| 2FA for login | Architecture-ready | TOTP Phase 2 |
| Device tracking | Partial | Enrich fraud context |
| CSRF / Sanctum / policies | ✅ | — |

### E. Admin & observability

| Capability | Status | Phase |
|------------|--------|-------|
| Secret admin URL | ✅ | — |
| Encrypted integration credentials | ✅ | — |
| Platform rules (KYC, fraud, timing) | ✅ | — |
| Audit log (settings changes) | ✅ | Extend to user freezes |
| Support ticket triage UI | Partial (dashboard count) | Phase 2 full queue |
| Horizon queue monitoring | ✅ | Production emails in Platform → Operations |

---

## Compliance narrative for investors

Use this language in pitches — accurate and defensible:

> **Reton implements the customer-facing trust layer:** CBN-aligned KYC tiers, identity verification via Dojah, real-time transaction monitoring, fraud holds, full audit trails, and case export for our licensed banking partner. Settlement and regulatory reporting operate on ALATPay/Wema rails. We do not claim standalone AML licensure; we build AML-ready infrastructure that satisfies partner due diligence and scales toward full program maturity.

**Do not say:** "We are fully AML compliant" or "We are licensed by CBN" unless literally true.

**Do say:** "KYC-tiered limits, BVN verification, transaction monitoring, and partner-bank settlement."

---

## KYC & user limits roadmap

| Tier | Requirements | Limits (default) | When |
|------|--------------|------------------|------|
| **1** | Phone + email | ₦50k / ₦200k daily / ₦300k balance | Signup (Phase 1) |
| **2** | BVN + Dojah match | ₦500k / ₦2M / ₦5M | User initiates in Profile |
| **3** | NIN + address | ₦5M / ₦20M / ₦50M | High-trust users, merchants |

**Demo shortcut:** `DOJAH_DRIVER=fake` + test BVN `22334455667`, DOB `1990-05-15` → instant Tier 2 in sandbox.

---

## Configuration management

| Layer | What | Where |
|-------|------|-------|
| Infrastructure | `APP_KEY`, DB, Redis, Reverb, mail | `.env` / Laravel Cloud only |
| Integrations | API keys, drivers, webhooks | Admin → Integrations (encrypted) |
| Business rules | KYC, fraud, callback, recovery | Admin → Platform |
| App identity | Demo mode, URLs, deep links | Admin → App |
| Fallback | All `RETON_*` in `.env.example` | Used until admin saves a group |

---

## Success metrics

### Phase 0 (demo)

- Demo completion rate in live pitch: 100%
- Judge questions answered without "we'll build that": > 90%

### Phase 1 (soft launch)

- Weekly active wallets: 500+
- Protected transfer adoption: > 30% of P2P volume
- Tier 2 conversion: > 15% of active users
- Webhook processing success: > 99.9%
- Mean callback resolution time: < 48h

### Phase 2 (compliance)

- Partner bank DDQ: passed
- Open AML/fraud cases > 7 days: 0
- STR-ready export time: < 5 min per case

### Phase 3 (scale)

- GMV monthly: ₦500M+
- Merchant API integrations: 10+
- NPS: > 50

---

## Risk register

| Risk | Impact | Mitigation |
|------|--------|------------|
| ALATPay production KYB delayed | No real money demo | Fake drivers + narrative; queue KYB early |
| Dojah production not live | No real Tier 2 | Tier 1 only at launch; sandbox for demos |
| Claiming false compliance | Legal/reputational | Use partner-bank narrative; roadmap Phase 2 AML |
| APP_KEY rotation breaks encrypted settings | Admin integrations lost | Document re-entry procedure; backup env |
| Fraud losses on Tier 1 | Financial | Keep ₦50k limit; velocity rules; callback protection |
| Scope creep (clone Opay) | Lose differentiation | Roadmap prioritizes trust features only |

---

## Immediate next actions (priority order)

1. **Finalize buildathon demo** — script, video backup, custom admin URL
2. **Submit ALATPay + Dojah production KYB** — parallel tracks
3. **Commit admin platform settings** — merge unreleased branch
4. **Production deploy checklist** — `RETON_DEMO_MODE=false`, Redis, Horizon, monitoring
5. **Partner responsibilities call** — STR, sanctions, KYC record ownership with Wema/ALATPay
6. **Phase 2 scoping** — sanctions API pick (Dojah vs ComplyAdvantage vs partner)

---

## Document maintenance

| Event | Action |
|-------|--------|
| Major feature shipped | Update [CHANGELOG.md](./CHANGELOG.md) |
| Phase exit criteria met | Bump phase status in this file |
| Regulatory guidance changes | Review KYC/AML sections with counsel |
| New integration added | Update Workstream B table |

---

*Reton — trust at the moment money moves.*
