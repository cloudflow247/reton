# Reton — Google Slides paste pack (ALATPay Buildathon)

Open [Google Slides](https://slides.google.com) → Blank presentation → paste one slide at a time.

**Theme tip:** Dark background `#071612`, accent `#1EC98A`, text `#E8F3EE`, secondary `#8AA89D`. Title font: something geometric (Montserrat / Poppins). Body: clean sans.

Present live from `presentations/reton-buildathon-pitch.html` (open in Chrome → F for fullscreen) if you need zero setup.

---

## Slide 1 — Title
**Reton**  
Payments you can take back.

Africa’s trust-first digital banking platform — built so a mistake or a scam doesn’t have to be final.

RETON PTE LTD · ALATPay Buildathon · Licensed rails via ALAT by Wema

---

## Slide 2 — The problem
**Sending money is easy. Taking it back is not.**

- One wrong digit and the money is gone
- Scams move faster than bank disputes
- Most wallets optimise for “send and forget”
- People don’t need another clone — they need a second chance

---

## Slide 3 — The insight
**Trust isn’t a badge on the login screen.**  
**It’s what happens after the money moves.**

Reton is built for the moment something goes wrong — not just the moment you hit send.

---

## Slide 4 — Flagship
**Callback Protection**

Hold a payment until you’re ready. Release it when it feels right — or pull it back if something’s off.

Flow: Send → Protected / Pending → Release **or** Request callback

- Receiver sees “Payment Protected”
- Every step is logged on a visible timeline
- Sender, receiver, and admin can act with clarity

---

## Slide 5 — What we built
**A full trust stack — not a slideware wallet.**

| Feature | One line |
|---------|----------|
| Wrong-transfer recovery | Report a mistake; hold eligible funds; track the case |
| Fraud signals | Rule-based scoring with admin visibility |
| Double-entry wallet | Every naira ledgered — no silent tweaks |
| KYC tiers | CBN-aligned limits; BVN unlock for funding |
| Protected marketplace | Escrow-style protection until delivery confirmed |
| Money-aware support | Find a tx, explain protection, escalate |

---

## Slide 6 — ALATPay
**Real rails. Not a mock bank.**

**What we use:** Collections & payment links · Static virtual accounts · BVN OTP · Signed webhooks · Transaction verification

**Where money enters:** Users fund Reton wallets on ALATPay. We credit our ledger only after verified settlement.

**Why it matters:** ALAT by Wema is a licensed Nigerian bank rail — so Callback Protection sits on infrastructure people and regulators can trust.

---

## Slide 7 — Why Reton
**We’re not another wallet.**

- Trust layer — protection & recovery are the product
- Visible timeline — every money event is explainable
- Licensed settlement — ALATPay / Wema
- Production mindset — signed webhooks, PINs, encrypted secrets, audited admin

---

## Slide 8 — Built to ship
**Serious stack. Serious money.**

Laravel 12 · Inertia + React · Postgres · Redis · Horizon

Live at **retonpay.com** · Public repo for judges · MVP → production path documented

---

## Slide 9 — Demo (90 seconds)
**Show, don’t tell.**

1. Open a wallet — balance & KYC tier  
2. Send a protected transfer  
3. Receiver sees Payment Protected  
4. Request callback — timeline updates  
5. Optional: recovery case or admin fraud view  

Feeling for judges: *money moved — and I still had control.*

---

## Slide 10 — Team
**RETON PTE LTD**

- **Gabriel Rotimi Mogaji** — Founder & CEO  
- **Aina Christana Olajumoke** — Co-Founder  

Building Africa’s trust-first payment platform — one protected transfer at a time.

---

## Slide 11 — Close
**Money you can take back.**

Thank you. We’re ready to demo, walk the code, and answer anything.

retonpay.com · support@retonpay.com  
7, Greenland Estate, Ikorodu, Lagos  
© 2026 RETON PTE LTD

---

## Suggested talk time
| Segment | Time |
|---------|------|
| Problem → insight | 1:00 |
| Callback Protection | 1:30 |
| Product + ALATPay | 1:30 |
| Live demo | 1:30–2:00 |
| Close + Q&A | remaining |
