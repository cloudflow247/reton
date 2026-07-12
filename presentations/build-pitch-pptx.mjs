/**
 * Build Reton Buildathon PPTX from screenshots + narrative slides.
 * Usage: node presentations/build-pitch-pptx.mjs
 */
import PptxGenJS from 'pptxgenjs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { existsSync } from 'node:fs'

const __dirname = dirname(fileURLToPath(import.meta.url))
const shot = (name) => join(__dirname, 'screenshots', name)
const out = join(__dirname, 'Reton-ALATPay-Buildathon-Pitch.pptx')

const BG = '071612'
const MINT = '1EC98A'
const MUTED = '8AA89D'
const TEXT = 'E8F3EE'
const CARD = '0F211C'

const pptx = new PptxGenJS()
pptx.defineLayout({ name: 'WIDE', width: 13.333, height: 7.5 })
pptx.layout = 'WIDE'
pptx.author = 'RETON PTE LTD'
pptx.title = 'Reton — ALATPay Buildathon'
pptx.subject = 'Payments you can take back'

function darkSlide() {
  const s = pptx.addSlide()
  s.background = { color: BG }
  return s
}

function eyebrow(s, text, y = 0.45) {
  s.addText(text.toUpperCase(), {
    x: 0.7, y, w: 12, h: 0.3,
    fontSize: 11, fontFace: 'Arial', color: MINT, bold: true, charSpacing: 3,
  })
}

function title(s, text, y = 0.85, opts = {}) {
  s.addText(text, {
    x: 0.7, y, w: opts.w ?? 11.5, h: opts.h ?? 1.4,
    fontSize: opts.fontSize ?? 36, fontFace: 'Arial', color: TEXT, bold: true,
    ...opts,
  })
}

function body(s, text, y, opts = {}) {
  s.addText(text, {
    x: 0.7, y, w: opts.w ?? 11, h: opts.h ?? 1.2,
    fontSize: opts.fontSize ?? 18, fontFace: 'Arial', color: MUTED,
    ...opts,
  })
}

function bullets(s, items, y = 2.4) {
  s.addText(items.map((t) => ({ text: t, options: { bullet: true, breakLine: true } })), {
    x: 0.7, y, w: 11.5, h: 4,
    fontSize: 20, fontFace: 'Arial', color: TEXT, paraSpacing: 10,
  })
}

function addShot(s, file, x, y, w, h) {
  if (!existsSync(file)) return false
  s.addImage({ path: file, x, y, w, h })
  return true
}

// 1 Title
{
  const s = darkSlide()
  s.addText('RETON', { x: 0.7, y: 1.6, w: 6, h: 0.4, fontSize: 14, color: MINT, bold: true, charSpacing: 4 })
  s.addText('Payments you can take back.', {
    x: 0.7, y: 2.1, w: 11, h: 1.6, fontSize: 42, fontFace: 'Arial', color: TEXT, bold: true,
  })
  body(s, 'Africa’s trust-first digital banking platform — built so a mistake or a scam doesn’t have to be final.', 3.9)
  body(s, 'RETON PTE LTD  ·  retonpay.com  ·  Licensed rails via ALAT by Wema', 5.3, { fontSize: 14 })
}

// 2 Problem
{
  const s = darkSlide()
  eyebrow(s, 'The problem')
  title(s, 'Sending money is easy.\nTaking it back is not.')
  bullets(s, [
    'One wrong digit and the money is gone.',
    'Scams move faster than bank disputes.',
    'Most wallets optimise for “send and forget.”',
    'People don’t need another clone — they need a second chance.',
  ])
}

// 2b Real Nigeria story
{
  const s = darkSlide()
  eyebrow(s, 'Why this is real')
  title(s, 'When money leaves…\ngetting it back is a war.', 0.85, { fontSize: 30, h: 1.3 })
  body(s, 'In 2024, about ₦95 million was pulled from a company’s account without permission. Banks rushed “block and recall.” Freezes often last only 72 hours. A court later ordered the money returned — and reversing it still became a legal fight.', 2.3, { h: 1.6, fontSize: 16 })
  s.addShape(pptx.shapes.ROUNDED_RECTANGLE, { x: 0.7, y: 4.1, w: 5.7, h: 2.3, fill: { color: CARD }, rectRadius: 0.2 })
  s.addText('How it works today', { x: 1.0, y: 4.35, w: 5.1, h: 0.4, bold: true, color: TEXT, fontSize: 15 })
  s.addText('Money flies out → hope a freeze works → race the clock → maybe a court → maybe nothing comes back.', {
    x: 1.0, y: 4.85, w: 5.1, h: 1.2, color: MUTED, fontSize: 14,
  })
  s.addShape(pptx.shapes.ROUNDED_RECTANGLE, { x: 6.7, y: 4.1, w: 5.7, h: 2.3, fill: { color: CARD }, rectRadius: 0.2 })
  s.addText('What a baby can understand', { x: 7.0, y: 4.35, w: 5.1, h: 0.4, bold: true, color: TEXT, fontSize: 15 })
  s.addText('If you give away a toy and it leaves the room, chasing it is hard. Reton keeps a string on the toy until you say “okay.”', {
    x: 7.0, y: 4.85, w: 5.1, h: 1.2, color: MUTED, fontSize: 14,
  })
}

// 2c Reton answer bridge
{
  const s = darkSlide()
  eyebrow(s, 'Reton’s answer')
  title(s, 'Don’t wait for a chase.\nBuild the undo first.')
  bullets(s, [
    'Callback Protection — hold the payment before it becomes final.',
    'Wrong-transfer recovery — freeze eligible funds and open a case.',
    'Fraud signals — watch risky moves while money is still in motion.',
    'A clear timeline — so everyone can see what happened, step by step.',
  ])
  body(s, 'Courts and bank recalls still matter. Reton adds something they can’t: protection inside the payment itself.', 5.5, { fontSize: 16, color: MINT })
}

// 3 Insight
{
  const s = darkSlide()
  eyebrow(s, 'The insight')
  title(s, 'Trust isn’t a badge on the login screen.\nIt’s what happens after the money moves.', 1.8, { fontSize: 30, h: 2.2 })
  body(s, 'Reton is built for the moment something goes wrong — not just the moment you hit send.', 4.4)
}

// 4 Home screenshot
{
  const s = darkSlide()
  eyebrow(s, 'Product · Live')
  title(s, 'The first wallet with an undo button for money.', 0.75, { fontSize: 26, h: 0.7 })
  addShot(s, shot('01-home.png'), 0.7, 1.55, 11.9, 5.2)
}

// 5 Callback Protection
{
  const s = darkSlide()
  eyebrow(s, 'Flagship')
  title(s, 'Callback Protection', 0.85, { fontSize: 34, h: 0.7 })
  body(s, 'Hold a payment until you’re ready. Release it when it feels right — or pull it back if something’s off.', 1.6)
  s.addShape(pptx.shapes.ROUNDED_RECTANGLE, { x: 0.7, y: 2.9, w: 2.2, h: 0.55, fill: { color: CARD }, rectRadius: 0.25 })
  s.addText('Send', { x: 0.7, y: 2.95, w: 2.2, h: 0.45, align: 'center', color: TEXT, fontSize: 14, bold: true })
  s.addText('→', { x: 3.0, y: 2.95, w: 0.4, h: 0.45, color: MUTED, fontSize: 18 })
  s.addShape(pptx.shapes.ROUNDED_RECTANGLE, { x: 3.4, y: 2.9, w: 3.2, h: 0.55, fill: { color: CARD }, rectRadius: 0.25 })
  s.addText('Protected / Pending', { x: 3.4, y: 2.95, w: 3.2, h: 0.45, align: 'center', color: 'E8A54B', fontSize: 14, bold: true })
  s.addText('→', { x: 6.7, y: 2.95, w: 0.4, h: 0.45, color: MUTED, fontSize: 18 })
  s.addShape(pptx.shapes.ROUNDED_RECTANGLE, { x: 7.1, y: 2.9, w: 2.2, h: 0.55, fill: { color: CARD }, rectRadius: 0.25 })
  s.addText('Release', { x: 7.1, y: 2.95, w: 2.2, h: 0.45, align: 'center', color: MINT, fontSize: 14, bold: true })
  s.addText('or Callback', { x: 9.5, y: 2.95, w: 2.5, h: 0.45, color: TEXT, fontSize: 14, bold: true })
  bullets(s, [
    'Receiver sees “Payment Protected.”',
    'Every step is logged on a visible timeline.',
    'Sender, receiver, and admin can act with clarity.',
  ], 3.8)
}

// 6 How it works shot
{
  const s = darkSlide()
  eyebrow(s, 'How it works')
  title(s, 'Clear flows. Safer money.', 0.75, { fontSize: 26, h: 0.6 })
  addShot(s, shot('02-how-it-works.png'), 0.7, 1.45, 11.9, 5.4)
}

// 7 Trust stack
{
  const s = darkSlide()
  eyebrow(s, 'What we built')
  title(s, 'A full trust stack — not slideware.', 0.8, { fontSize: 28, h: 0.7 })
  const cards = [
    ['Wrong-transfer recovery', 'Report a mistake. Hold eligible funds. Track the case.'],
    ['Fraud signals', 'Rule-based scoring with admin visibility.'],
    ['Double-entry wallet', 'Every naira ledgered — no silent tweaks.'],
    ['KYC tiers', 'CBN-aligned limits. BVN unlock for funding.'],
    ['Protected marketplace', 'Escrow-style protection until delivery confirmed.'],
    ['Money-aware support', 'Find a tx, explain protection, escalate.'],
  ]
  cards.forEach(([h, p], idx) => {
    const col = idx % 3
    const row = Math.floor(idx / 3)
    const x = 0.7 + col * 4.1
    const y = 1.8 + row * 2.3
    s.addShape(pptx.shapes.ROUNDED_RECTANGLE, { x, y, w: 3.85, h: 2.0, fill: { color: CARD }, rectRadius: 0.2 })
    s.addText(h, { x: x + 0.25, y: y + 0.35, w: 3.35, h: 0.45, fontSize: 16, bold: true, color: TEXT })
    s.addText(p, { x: x + 0.25, y: y + 0.9, w: 3.35, h: 0.8, fontSize: 13, color: MUTED })
  })
}

// 7b Shop & listings
{
  const s = darkSlide()
  eyebrow(s, 'Shop & listings')
  title(s, 'Sell something.\nGet paid — safely.')
  body(s, 'List an item. Share the link on WhatsApp. The buyer’s money stays protected until you deliver and they confirm.', 2.3, { h: 1.1 })
  bullets(s, [
    'Create a listing & share your link',
    'Buyer pays with Callback Protection',
    'You deliver · they confirm · money releases',
    'Same undo button for money — now for real sales. No public mall browse.',
  ], 3.5)
}

// 8 Security + Business shots
{
  const s = darkSlide()
  eyebrow(s, 'Product · Live')
  title(s, 'Security posture. Business on the way.', 0.7, { fontSize: 24, h: 0.55 })
  addShot(s, shot('03-security.png'), 0.55, 1.4, 6.0, 5.3)
  addShot(s, shot('04-business.png'), 6.75, 1.4, 6.0, 5.3)
}

// 9 ALATPay
{
  const s = darkSlide()
  eyebrow(s, 'ALATPay integration')
  title(s, 'Real rails. Not a mock bank.', 0.85, { fontSize: 30, h: 0.7 })
  s.addShape(pptx.shapes.ROUNDED_RECTANGLE, { x: 0.7, y: 1.9, w: 5.7, h: 2.6, fill: { color: CARD }, rectRadius: 0.2 })
  s.addText('What we use', { x: 1.0, y: 2.15, w: 5.1, h: 0.4, bold: true, color: TEXT, fontSize: 16 })
  s.addText('Collections & payment links · Static virtual accounts · BVN OTP · Signed webhooks · Transaction verification', {
    x: 1.0, y: 2.7, w: 5.1, h: 1.4, color: MUTED, fontSize: 15,
  })
  s.addShape(pptx.shapes.ROUNDED_RECTANGLE, { x: 6.7, y: 1.9, w: 5.7, h: 2.6, fill: { color: CARD }, rectRadius: 0.2 })
  s.addText('Where money enters', { x: 7.0, y: 2.15, w: 5.1, h: 0.4, bold: true, color: TEXT, fontSize: 16 })
  s.addText('Users fund Reton wallets on ALATPay. We credit our ledger only after verified settlement.', {
    x: 7.0, y: 2.7, w: 5.1, h: 1.4, color: MUTED, fontSize: 15,
  })
  body(s, 'ALAT by Wema gives us a licensed Nigerian bank rail — so Callback Protection sits on infrastructure people can trust.', 5.0)
}

// 10 Contact + FAQ
{
  const s = darkSlide()
  eyebrow(s, 'Product · Live')
  title(s, 'Reachable. Self-explanatory.', 0.7, { fontSize: 24, h: 0.55 })
  addShot(s, shot('05-contact.png'), 0.55, 1.4, 6.0, 5.3)
  addShot(s, shot('06-faq.png'), 6.75, 1.4, 6.0, 5.3)
}

// 11 Why Reton
{
  const s = darkSlide()
  eyebrow(s, 'Why Reton')
  title(s, 'We’re not another wallet.')
  bullets(s, [
    'Trust layer — protection and recovery are the product.',
    'Visible timeline — every money event is explainable.',
    'Licensed settlement — ALATPay / Wema.',
    'Production mindset — signed webhooks, PINs, encrypted secrets, audited admin.',
  ])
}

// 12 Demo
{
  const s = darkSlide()
  eyebrow(s, 'Demo in 90 seconds')
  title(s, 'Show, don’t tell.', 0.85, { fontSize: 32, h: 0.7 })
  bullets(s, [
    'Open a wallet — balance & KYC tier',
    'Send a protected transfer',
    'Receiver sees Payment Protected',
    'Request callback — timeline updates live',
    'Optional: recovery case or admin fraud view',
  ], 1.9)
  body(s, 'The story: money moved — and I still had control.', 5.5, { color: MINT, fontSize: 18 })
}

// 13 Team
{
  const s = darkSlide()
  eyebrow(s, 'Team')
  title(s, 'RETON PTE LTD', 0.9, { fontSize: 32, h: 0.7 })
  s.addShape(pptx.shapes.ROUNDED_RECTANGLE, { x: 0.7, y: 2.1, w: 5.5, h: 1.8, fill: { color: CARD }, rectRadius: 0.2 })
  s.addText('Gabriel Rotimi Mogaji', { x: 1.0, y: 2.45, w: 5, h: 0.45, bold: true, color: TEXT, fontSize: 18 })
  s.addText('Founder & CEO', { x: 1.0, y: 2.95, w: 5, h: 0.4, color: MUTED, fontSize: 14 })
  s.addShape(pptx.shapes.ROUNDED_RECTANGLE, { x: 6.6, y: 2.1, w: 5.5, h: 1.8, fill: { color: CARD }, rectRadius: 0.2 })
  s.addText('Aina Christana Olajumoke', { x: 6.9, y: 2.45, w: 5, h: 0.45, bold: true, color: TEXT, fontSize: 18 })
  s.addText('Co-Founder', { x: 6.9, y: 2.95, w: 5, h: 0.4, color: MUTED, fontSize: 14 })
  body(s, 'Building Africa’s trust-first payment platform — one protected transfer at a time.', 4.5)
}

// 14 Close
{
  const s = darkSlide()
  s.addText('RETON', { x: 0.7, y: 1.8, w: 6, h: 0.35, fontSize: 14, color: MINT, bold: true, charSpacing: 4 })
  title(s, 'Money you can take back.', 2.3, { fontSize: 40, h: 1.2 })
  body(s, 'Thank you. We’re ready to demo, walk the product, and answer anything.', 3.7)
  body(s, 'retonpay.com  ·  support@retonpay.com\n7, Greenland Estate, Ikorodu, Lagos  ·  © 2026 RETON PTE LTD', 5.1, { fontSize: 14 })
}

await pptx.writeFile({ fileName: out })
console.log('Wrote', out)
