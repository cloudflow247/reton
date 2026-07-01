import { createInertiaApp } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createRoot } from 'react-dom/client'
import { setupEcho } from '@/lib/broadcasting'
import { AppProviders } from '@/providers/AppProviders'
import '../css/app.css'

setupEcho()

const appName = import.meta.env.VITE_APP_NAME ?? 'Reton'

createInertiaApp({
  title: (title) => (title ? `${title} · ${appName}` : `${appName} — payments you can take back`),
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob('./Pages/**/*.tsx'),
    ),
  setup({ el, App, props }) {
    createRoot(el).render(
      <AppProviders>
        <App {...props} />
      </AppProviders>,
    )
  },
  progress: {
    color: '#0b7a57',
  },
})
