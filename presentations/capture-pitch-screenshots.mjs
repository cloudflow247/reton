/**
 * Capture Reton product screenshots for the buildathon pitch deck.
 * Usage: node presentations/capture-pitch-screenshots.mjs
 */
import { mkdirSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { chromium } from 'playwright'

const __dirname = dirname(fileURLToPath(import.meta.url))
const OUT = join(__dirname, 'screenshots')
const BASE = process.env.APP_URL ?? 'https://retonpay.com'

mkdirSync(OUT, { recursive: true })

const pages = [
  { path: '/', name: '01-home', label: 'Home' },
  { path: '/how-it-works', name: '02-how-it-works', label: 'How it works' },
  { path: '/security', name: '03-security', label: 'Security' },
  { path: '/business', name: '04-business', label: 'Business' },
  { path: '/contact', name: '05-contact', label: 'Contact' },
  { path: '/login', name: '06-login', label: 'Sign in' },
]

const browser = await chromium.launch()
const context = await browser.newContext({
  viewport: { width: 1440, height: 900 },
  deviceScaleFactor: 2,
})
const page = await context.newPage()

for (const item of pages) {
  const url = `${BASE}${item.path}`
  console.log(`capturing ${url}`)
  await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 })
  await page.waitForTimeout(900)
  // Prefer the main product frame; full page for marketing length
  await page.screenshot({
    path: join(OUT, `${item.name}.png`),
    fullPage: false,
  })
  console.log(`saved ${item.name}.png`)
}

// Hero / mid-page crops for storytelling
await page.goto(`${BASE}/`, { waitUntil: 'networkidle', timeout: 60000 })
await page.waitForTimeout(800)
await page.evaluate(() => window.scrollTo(0, Math.min(900, document.body.scrollHeight * 0.35)))
await page.waitForTimeout(500)
await page.screenshot({ path: join(OUT, '07-home-features.png'), fullPage: false })
console.log('saved 07-home-features.png')

await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
await page.waitForTimeout(600)
await page.screenshot({ path: join(OUT, '08-home-footer-app.png'), fullPage: false })
console.log('saved 08-home-footer-app.png')

await browser.close()
console.log('done →', OUT)
