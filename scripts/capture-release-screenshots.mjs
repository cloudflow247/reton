/**
 * Capture release screenshots for team briefs.
 * Usage: node scripts/capture-release-screenshots.mjs
 * Requires: npx playwright (chromium), app running at APP_URL
 */
import { mkdirSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { execFileSync } from 'node:child_process'
import { chromium } from 'playwright'

const BASE = process.env.APP_URL ?? 'http://127.0.0.1:8000'
const OUT = join(process.cwd(), 'docs/release-2026-06-30/screenshots')
const PASSWORD = process.env.RETON_DEMO_PASSWORD ?? 'demo1234'

mkdirSync(OUT, { recursive: true })

const prepared = JSON.parse(
  execFileSync('php', ['scripts/prepare-screenshot-data.php'], { encoding: 'utf8' }),
)
const LISTING_ID = prepared.share_listing_id
writeFileSync(
  join(process.cwd(), 'docs/release-2026-06-30/screenshot-meta.json'),
  JSON.stringify({ ...prepared, captured_at: new Date().toISOString() }, null, 2),
)

async function login(page, email) {
  await page.context().clearCookies()
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' })
  await page.locator('input[type="email"]').fill(email)
  await page.locator('input[type="password"]').fill(PASSWORD)
  await page.locator('button[type="submit"]').click()
  await page.waitForURL(/\/(dashboard|marketplace|send)/, { timeout: 20000 })
  await page.waitForTimeout(1200)
}

async function shot(page, name) {
  await page.waitForTimeout(600)
  await page.screenshot({ path: join(OUT, `${name}.png`), fullPage: true })
  console.log(`saved ${name}.png`)
}

const browser = await chromium.launch()
const context = await browser.newContext({
  viewport: { width: 1280, height: 900 },
  deviceScaleFactor: 2,
})
const page = await context.newPage()

try {
  await page.goto(`${BASE}/l/${LISTING_ID}`, { waitUntil: 'networkidle' })
  await shot(page, '01-listing-share-guest')

  await login(page, 'bola@demo.reton.ng')
  await page.goto(`${BASE}/l/${LISTING_ID}`, { waitUntil: 'networkidle' })
  await shot(page, '02-listing-share-seller-qr')

  await login(page, 'ada@demo.reton.ng')
  await page.goto(`${BASE}/marketplace`, { waitUntil: 'networkidle' })
  await shot(page, '03-marketplace-seller')

  const sellBtn = page.getByRole('button', { name: /sell an item/i })
  if (await sellBtn.isVisible()) {
    await sellBtn.click()
    await page.waitForTimeout(800)
    await shot(page, '04-create-listing-modal')
    await page.keyboard.press('Escape')
  }

  await page.goto(`${BASE}/dashboard`, { waitUntil: 'networkidle' })
  await shot(page, '05-dashboard-balance')

  await page.goto(`${BASE}/send`, { waitUntil: 'networkidle' })
  await shot(page, '06-send-protected')

  await page.goto(`${BASE}/protection`, { waitUntil: 'networkidle' })
  await shot(page, '07-protection-center')

  await context.clearCookies()
  await login(page, 'ada@demo.reton.ng')
  await page.goto(`${BASE}/marketplace`, { waitUntil: 'networkidle' })
  await shot(page, '08-marketplace-buyer-view')

  await page.goto(`${BASE}/l/${LISTING_ID}`, { waitUntil: 'networkidle' })
  await shot(page, '09-listing-share-buyer-signed-in')
} finally {
  await browser.close()
}

console.log(`Screenshots written to ${OUT}`)
