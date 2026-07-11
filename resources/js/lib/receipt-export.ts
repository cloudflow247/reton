/** Branded Reton receipt → PNG (no extra dependencies). */

export async function paintReceiptPng(input: {
  app: string
  title: string
  amountLabel: string
  isCredit: boolean
  reference: string
  status: string
  type: string
  dateLabel: string
  customer: string
  retonId?: string | null
  transferRef?: string | null
}): Promise<Blob> {
  const width = 720
  const height = 980
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  const ctx = canvas.getContext('2d')
  if (!ctx) {
    throw new Error('Canvas unavailable.')
  }

  ctx.fillStyle = '#f2f6f3'
  ctx.fillRect(0, 0, width, height)

  const pad = 36
  roundRect(ctx, pad, pad, width - pad * 2, height - pad * 2, 28)
  ctx.fillStyle = '#ffffff'
  ctx.fill()

  const headerH = 210
  const grad = ctx.createLinearGradient(pad, pad, width - pad, pad + headerH)
  grad.addColorStop(0, '#0a6a4d')
  grad.addColorStop(0.55, '#0e7e5c')
  grad.addColorStop(1, '#094f39')
  ctx.save()
  roundRect(ctx, pad, pad, width - pad * 2, headerH + 40, 28)
  ctx.clip()
  ctx.fillStyle = grad
  ctx.fillRect(pad, pad, width - pad * 2, headerH + 40)
  drawWaves(ctx, pad, pad + headerH - 30, width - pad * 2, 90)
  ctx.restore()

  ctx.fillStyle = 'rgba(255,255,255,0.7)'
  ctx.font = '600 13px Inter, system-ui, sans-serif'
  ctx.fillText(input.app.toUpperCase(), pad + 36, pad + 48)

  ctx.fillStyle = '#ffffff'
  ctx.font = '700 28px "Space Grotesk", Inter, sans-serif'
  ctx.fillText('Payment receipt', pad + 36, pad + 92)

  ctx.fillStyle = 'rgba(255,255,255,0.85)'
  ctx.font = '500 15px Inter, system-ui, sans-serif'
  wrapText(ctx, input.title, pad + 36, pad + 128, width - pad * 2 - 72, 22)

  ctx.fillStyle = input.isCredit ? '#0b7a57' : '#122a22'
  ctx.font = '700 48px "Space Grotesk", Inter, sans-serif'
  ctx.fillText(input.amountLabel, pad + 36, pad + headerH + 88)

  ctx.fillStyle = '#5d726b'
  ctx.font = '600 12px Inter, system-ui, sans-serif'
  ctx.fillText(input.isCredit ? 'CREDIT' : 'DEBIT', pad + 36, pad + headerH + 118)

  const rows: Array<[string, string]> = [
    ['Reference', input.reference],
    ['Status', input.status],
    ['Type', input.type],
    ['Date', input.dateLabel],
    ['Customer', input.customer],
  ]
  if (input.retonId) {
    rows.push(['Reton ID', input.retonId])
  }
  if (input.transferRef) {
    rows.push(['Transfer', input.transferRef])
  }

  let y = pad + headerH + 170
  for (const [label, value] of rows) {
    ctx.fillStyle = '#5d726b'
    ctx.font = '600 11px Inter, system-ui, sans-serif'
    ctx.fillText(label.toUpperCase(), pad + 36, y)
    ctx.fillStyle = '#122a22'
    ctx.font = '600 16px "Space Grotesk", Inter, sans-serif'
    wrapText(ctx, value, pad + 36, y + 24, width - pad * 2 - 72, 22)
    y += 70
    ctx.strokeStyle = '#e1eae5'
    ctx.beginPath()
    ctx.moveTo(pad + 36, y - 18)
    ctx.lineTo(width - pad - 36, y - 18)
    ctx.stroke()
  }

  ctx.save()
  ctx.beginPath()
  ctx.rect(pad, height - pad - 70, width - pad * 2, 70)
  ctx.clip()
  drawWaves(ctx, pad, height - pad - 70, width - pad * 2, 70, true)
  ctx.restore()

  ctx.fillStyle = '#5d726b'
  ctx.font = '500 12px Inter, system-ui, sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText('Trust-first payments · Reton', width / 2, height - pad - 28)
  ctx.textAlign = 'left'

  return await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob(
      (blob) => (blob ? resolve(blob) : reject(new Error('PNG encode failed.'))),
      'image/png',
      1,
    )
  })
}

export async function shareOrDownloadPng(
  blob: Blob,
  filename: string,
  title: string,
): Promise<'shared' | 'downloaded'> {
  const file = new File([blob], filename, { type: 'image/png' })

  if (typeof navigator.canShare === 'function' && navigator.canShare({ files: [file] })) {
    await navigator.share({ files: [file], title })
    return 'shared'
  }

  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
  return 'downloaded'
}

function roundRect(
  ctx: CanvasRenderingContext2D,
  x: number,
  y: number,
  w: number,
  h: number,
  r: number,
): void {
  const radius = Math.min(r, w / 2, h / 2)
  ctx.beginPath()
  ctx.moveTo(x + radius, y)
  ctx.arcTo(x + w, y, x + w, y + h, radius)
  ctx.arcTo(x + w, y + h, x, y + h, radius)
  ctx.arcTo(x, y + h, x, y, radius)
  ctx.arcTo(x, y, x + w, y, radius)
  ctx.closePath()
}

function drawWaves(
  ctx: CanvasRenderingContext2D,
  x: number,
  y: number,
  w: number,
  h: number,
  muted = false,
): void {
  const layers = [
    { color: muted ? 'rgba(11,122,87,0.10)' : 'rgba(255,255,255,0.18)', amp: 18, period: 180, offset: 0 },
    { color: muted ? 'rgba(11,122,87,0.16)' : 'rgba(255,255,255,0.12)', amp: 12, period: 120, offset: 40 },
    { color: muted ? 'rgba(9,95,68,0.20)' : 'rgba(255,255,255,0.08)', amp: 8, period: 90, offset: 80 },
  ]

  for (const layer of layers) {
    ctx.beginPath()
    ctx.moveTo(x, y + h)
    for (let i = 0; i <= w; i += 4) {
      const yy = y + h / 2 + Math.sin(((i + layer.offset) / layer.period) * Math.PI * 2) * layer.amp
      ctx.lineTo(x + i, yy)
    }
    ctx.lineTo(x + w, y + h)
    ctx.closePath()
    ctx.fillStyle = layer.color
    ctx.fill()
  }
}

function wrapText(
  ctx: CanvasRenderingContext2D,
  text: string,
  x: number,
  y: number,
  maxWidth: number,
  lineHeight: number,
): void {
  const words = text.split(/\s+/)
  let line = ''
  let cursorY = y
  for (const word of words) {
    const test = line ? `${line} ${word}` : word
    if (ctx.measureText(test).width > maxWidth && line) {
      ctx.fillText(line, x, cursorY)
      line = word
      cursorY += lineHeight
    } else {
      line = test
    }
  }
  if (line) {
    ctx.fillText(line, x, cursorY)
  }
}
