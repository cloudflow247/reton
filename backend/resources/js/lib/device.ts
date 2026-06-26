/**
 * A stable per-browser device fingerprint, sent as headers on money-movement
 * and auth requests so the server's fraud engine and device registrar keep
 * working under Inertia (they used to read these from the axios SPA client).
 */
function fingerprint(): string {
  const key = 'reton-device'
  let id = localStorage.getItem(key)
  if (!id) {
    id = crypto.randomUUID()
    localStorage.setItem(key, id)
  }
  return id
}

export function deviceHeaders(): Record<string, string> {
  return {
    'X-Device-Fingerprint': fingerprint(),
    'X-Device-Name': navigator.userAgent.slice(0, 80),
    'X-Device-Platform': 'web',
  }
}
