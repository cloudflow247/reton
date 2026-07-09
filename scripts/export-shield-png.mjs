import { readFileSync, writeFileSync, mkdirSync } from 'node:fs'
import { join } from 'node:path'
import { Resvg } from '@resvg/resvg-js'

const root = join(import.meta.dirname, '..')
const svg = readFileSync(join(root, 'public/shield.svg'))
const outDir = join(root, 'public/branding')
mkdirSync(outDir, { recursive: true })

for (const size of [192, 512, 640]) {
  const resvg = new Resvg(svg, {
    fitTo: { mode: 'width', value: size },
    background: 'transparent',
  })
  const file = join(outDir, `reton-shield-${size}.png`)
  writeFileSync(file, resvg.render().asPng())
  console.log(`wrote ${file}`)
}
