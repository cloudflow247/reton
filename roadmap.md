# Reton product & compliance roadmap

**Trust at the moment money moves.**  
Last updated: July 2026 · Company: **RETON PTE LTD** · Founder & CEO: **Gabriel Rotimi Mogaji** · Co-Founder: **Aina Christana Olajumoke**

Status: ALATPay Buildathon MVP → production hardening

---

## Executive summary

Reton is not another generic wallet. The product thesis is **trust when money moves**: Callback Protection, wrong-transfer recovery, fraud signals, merchant verification, and a visible timeline on every payment.

This roadmap sequences work from **buildathon demo** → **licensed soft launch** → **compliance-ready operations** → **scale**, aligned with Nigeria’s regulatory reality (CBN KYC tiers, NFIU AML expectations, licensed rails via ALAT / Wema).

| Horizon | Goal | Primary audience |
|---------|------|------------------|
| **Now (Phase 0)** | Win the buildathon with a polished live demo | Judges, investors |
| **0–3 months (Phase 1)** | Real users on Tier 1, licensed settlement | Early adopters, bank partner |
| **3–9 months (Phase 2)** | Compliance-ready operations | Regulators, bank partner, merchants |
| **9–18 months (Phase 3)** | Category leader in trust-layer payments | Consumers, SMEs, API partners |

---

## Strategic principles

1. **Licensed rails first** — Reton orchestrates trust; settlement and primary AML obligations sit with the licensed partner until Reton holds its own licence.
2. **CBN-style KYC by default** — Tier 1 at ₦50k single / ₦200k daily inflow / ₦300k balance; higher limits only after verified identity.
3. **Never claim full AML compliance** without STR workflow, sanctions screening, and an MLRO — position as an **AML-ready foundation**.
4. **Demo ≠ production** — fake drivers and sandbox accounts are fine for demos; production needs live KYB on every integration.
5. **Ship trust features people can see** — Callback Protection timeline, recovery cases, fraud alerts, and KYC badges beat feature breadth.

---

## Current state (MVP delivered)

### Core product

| Area | Status | Notes |
|------|--------|-------|
| Authentication & PIN | Done | Sanctum, 4-digit transaction PIN, lockout |
| Double-entry wallet | Done | Fund, transfer, beneficiaries, statements |
| Payment rail integration | Done | Fake + HTTP gateway, webhooks, deposits, BVN OTP |
| **Callback Protection** | Done | Protected transfers, hold/release, timeline |
| Wrong-transfer recovery | Done | Eligibility rules, hold, admin escalation |
| Fraud engine | Done | Rule-based scoring, velocity, holds, alerts |
| Transaction timeline | Done | Every money movement logged |
| KYC tiers (1–3) | Done | Limits enforced; BVN / NIN providers via admin |
| Bills | Done | Gateway abstraction, reconcile paths |
| Virtual cards | Done | Issue, fund, freeze; fake + HTTP |
| Digital marketplace | Done | Escrow, disputes, auto-refund scheduler |
| Physical marketplace | Done | Logistics integration, hub verification |
| AI support chat | Done | Assistant + ticket escalation |
| Admin control panel | Done | Encrypted settings, integrations, audit log |

### Intentionally deferred

| Area | Reason |
|------|--------|
| Full AML program (STR, sanctions, MLRO) | Partner-bank model + post-launch |
| Native iOS / Android apps | Deep links scaffolded; apps Phase 3 |
| Granular admin roles | Single admin flag is enough for MVP |

---

## Phase 0 — Buildathon demo (now)

**Objective:** A credible live demo that shows trust differentiation in under 10 minutes.

### Suggested judge flow

1. Sign in with a seeded sandbox account (local demo mode only)
2. Dashboard — balance, KYC badge, pending protected funds
3. Send a **protected transfer** — receiver sees “Payment Protected”
4. Callback Protection — request callback; show the timeline
5. Wrong-transfer recovery — open a recovery case
6. Fraud alert — show admin visibility
7. Support chat — look up a transaction; escalate a ticket
8. Admin — integration health and platform KYC limits

### Phase 0 checklist

- [ ] Record a short demo video backup
- [ ] Use a non-default admin path in any shared environment
- [ ] Keep demo mode off on public production
- [ ] Prepare a one-slide compliance narrative (below)
- [ ] Queue production KYB for payment and identity providers

**Exit criteria:** Demo runs end-to-end without manual database edits; flagship flows look production-ready.

---

## Phase 1 — Soft launch on licensed rails (0–3 months)

**Objective:** Real NGN flows for early users via production payment rails, Tier 1 signup without friction, Tier 2+ via live identity verification.

### Payments & wallet

| Task | Priority |
|------|----------|
| Production KYB + live credentials in Admin → Integrations | P0 |
| Webhook signature validation and idempotency verified in prod | P0 |
| Static wallet provisioning per KYC tier | P0 |
| Settlement reconciliation + admin deposit dashboard | P1 |
| Withdraw to bank (name enquiry + payout) | P1 |

### Identity (KYC)

| Task | Priority |
|------|----------|
| Live BVN OTP path for funding unlock | P0 |
| Tier 1 live on signup with CBN-aligned limits | P0 |
| Tier 2 / Tier 3 production-tested | P1 |
| KYC consent copy reviewed for NDPR | P1 |

### Operations

| Task | Priority |
|------|----------|
| Demo mode off in production | P0 |
| Horizon + Redis on Laravel Cloud | P0 |
| Error monitoring | P1 |
| On-call runbook for failed webhooks / stuck callbacks | P1 |

**Exit criteria:** Real users settling through the licensed rail; no unresolved stuck escrow older than 7 days; Tier 2 upgrades working in production.

---

## Phase 2 — Compliance & AML foundation (3–9 months)

**Objective:** Operate credibly with bank partner and regulators — with software and processes that satisfy partner due diligence.

### Responsibilities model

```
Licensed partner (bank rail)
  Primary settlement · STR filing (as agreed) · Sanctions (as agreed)
                │
                ▼
Reton platform
  CDD / KYC tiers · Transaction monitoring · Case management
  Audit logs · User freeze · Compliance export for partner MLRO
```

**Action:** Sign a responsibilities matrix with the bank partner (who files STRs, who holds KYC records, who runs sanctions).

### Software to build

| Capability | Phase 2 deliverable |
|------------|---------------------|
| Sanctions / PEP screening | At Tier 2 upgrade |
| Structuring / smurfing detection | Configurable fraud rules |
| Unified AML + fraud case queue | Assign, resolve, audit |
| Admin wallet freeze | With user notification |
| STR export pack | User, KYC, and history for partner MLRO |
| Record retention | 5–7 year policy + archival |

### Operations (non-software)

| Item | Deliverable |
|------|-------------|
| AML/CFT policy | Written policy aligned to CBN + NFIU guidance |
| MLRO | Named officer (can be fractional early on) |
| Staff training | Annual training log |
| NDPR + vendor DPAs | Privacy policy and processor agreements |

**Exit criteria:** Partner compliance sign-off; admin can export a compliance case in under five minutes.

---

## Phase 3 — Scale & differentiation (9–18 months)

| Initiative | Description |
|------------|-------------|
| Callback Protection API | Merchants embed protected checkout |
| Merchant verification | Blue badge, trust score, business KYB |
| Reton Pay links | Protected payment requests via chat apps |
| Native mobile apps | iOS / Android (store badges already on site) |
| Role-based admin | Compliance, support, ops |

**Recommendation:** Stay on partner rails through Phase 2; reassess an own licence only after product-market fit and volume justify it.

---

## Compliance narrative (investor-safe)

Use language that is accurate and defensible:

> Reton implements the customer-facing trust layer: CBN-aligned KYC tiers, identity verification, real-time transaction monitoring, fraud holds, full audit trails, and case export for our licensed banking partner. Settlement and regulatory reporting operate on partner rails. We do not claim standalone AML licensure; we build AML-ready infrastructure that satisfies partner due diligence.

**Do not say:** “We are fully AML compliant” or “We are licensed by the CBN” unless that is literally true.

**Do say:** “KYC-tiered limits, identity verification, transaction monitoring, and partner-bank settlement.”

---

## KYC & limits (defaults)

| Tier | Requirements | Limits (default) |
|------|--------------|------------------|
| **1** | Phone + email | ₦50k / ₦200k daily / ₦300k balance |
| **2** | BVN verified | Higher limits (admin-configurable) |
| **3** | NIN + address | Highest consumer / merchant tiers |

Sandbox identity shortcuts belong in local test config only — never in production docs or public credentials.

---

## Success metrics

| Phase | Targets |
|-------|---------|
| Phase 0 | Demo completes live; judge questions answered without “we’ll build that later” |
| Phase 1 | Protected transfer adoption > 30% of P2P; webhook success > 99.9% |
| Phase 2 | Partner DDQ passed; open AML/fraud cases > 7 days = 0 |
| Phase 3 | Meaningful GMV; merchant API partners live |

---

## Risk register

| Risk | Mitigation |
|------|------------|
| Production KYB delayed | Fake drivers for demo; queue KYB early |
| Over-claiming compliance | Partner-bank narrative; Phase 2 AML work |
| Encryption key rotation | Document re-entry of admin secrets |
| Fraud on Tier 1 | Keep ₦50k limit; velocity rules; Callback Protection |
| Scope creep | Prioritise trust features only |

---

## Immediate next actions

1. Finalise buildathon demo (script + video backup)
2. Submit production KYB for payment and identity providers
3. Production checklist — demo mode off, Redis, Horizon, monitoring
4. Partner responsibilities call — STR, sanctions, KYC record ownership
5. Scope Phase 2 sanctions / case-management work

---

## Document maintenance

| Event | Action |
|-------|--------|
| Major feature shipped | Update [CHANGELOG.md](./CHANGELOG.md) |
| Phase exit criteria met | Update status in this file |
| Regulatory guidance changes | Review KYC/AML sections with counsel |

---

© 2026 RETON PTE LTD. All rights reserved.  
*Reton — trust at the moment money moves.*
