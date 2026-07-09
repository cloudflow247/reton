/** Parse a pasted listing link or item code into a /l/{ref} path segment. */
export function parseListingRef(input: string): string | null {
  const trimmed = input.trim()
  if (!trimmed) {
    return null
  }

  const fromUrl = trimmed.match(/\/l\/([^/?#]+)/i)
  if (fromUrl) {
    return decodeURIComponent(fromUrl[1])
  }

  const fromApp = trimmed.match(/^reton:\/\/l\/([^/?#]+)/i)
  if (fromApp) {
    return decodeURIComponent(fromApp[1])
  }

  const compact = trimmed.toUpperCase().replace(/\s+/g, '')
  const codeMatch = compact.match(/^(?:RTN-?)?([23456789ABCDEFGHJKMNPQRSTUVWXYZ]{6})$/)
  if (codeMatch) {
    return `RTN-${codeMatch[1]}`
  }

  if (/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(trimmed)) {
    return trimmed
  }

  if (/^RTN-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{6}$/i.test(compact)) {
    return compact
  }

  return trimmed
}
