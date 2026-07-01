import { configureEcho, echoIsConfigured } from '@laravel/echo-react'

/** True when the Vite build included Reverb credentials (optional on Laravel Cloud). */
export function reverbEnabled(): boolean {
  return Boolean(import.meta.env.VITE_REVERB_APP_KEY)
}

/**
 * Configure Echo once at boot. Skipped when Reverb is not provisioned so pages
 * like Dashboard still render on Laravel Cloud without WebSockets.
 */
export function setupEcho(): void {
  if (echoIsConfigured() || !reverbEnabled()) {
    return
  }

  configureEcho({ broadcaster: 'reverb' })
}

export { echoIsConfigured }
