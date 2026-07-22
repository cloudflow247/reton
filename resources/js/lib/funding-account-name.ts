/**
 * Mirror of App\Support\Banking\FundingAccountName for client display safety.
 * Prefer the Reton profile name when the provider returns a merchant-prefixed VA name.
 */
export function shortFundingAccountName(
  providerAccountName: string | null | undefined,
  profileName: string | null | undefined,
): string {
  const profile = (profileName ?? '').trim()
  const preferred = profile.toUpperCase()
  const provider = (providerAccountName ?? '').trim()

  if (!preferred) {
    return provider || '-'
  }

  if (!provider) {
    return preferred
  }

  const profileTokens = tokens(profile)
  const providerTokens = tokens(provider)

  if (profileTokens.length > 0) {
    const overlap = profileTokens.filter((t) => providerTokens.includes(t)).length
    if (overlap >= Math.min(2, profileTokens.length)) {
      return preferred
    }
  }

  for (const sep of [' - ', ' - ', ' - ', ' / ', ' | ']) {
    if (!provider.includes(sep)) continue
    const parts = provider.split(sep).map((p) => p.trim()).filter(Boolean)
    const tail = parts[parts.length - 1] ?? ''
    const tailTokens = tokens(tail)
    const overlap = profileTokens.filter((t) => tailTokens.includes(t)).length
    if (overlap >= Math.min(2, profileTokens.length)) {
      return preferred
    }
  }

  return provider
}

function tokens(name: string): string[] {
  return name
    .toLowerCase()
    .replace(/[^a-z\s]/g, ' ')
    .split(/\s+/)
    .filter((p) => p.length >= 2)
}
