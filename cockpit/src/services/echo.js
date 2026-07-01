import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

let echoInstance = null

/**
 * Lazily connect to Laravel Reverb for tenant-scoped private channels.
 * Returns null when Reverb is not configured (falls back to polling).
 */
export function getEcho() {
  const key = import.meta.env.VITE_REVERB_APP_KEY
  if (!key) return null

  if (echoInstance) return echoInstance

  window.Pusher = Pusher

  const apiBase = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'
  const authEndpoint = apiBase.replace(/\/api\/?$/, '') + '/api/broadcasting/auth'

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint,
    auth: {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('token') || ''}`,
        Accept: 'application/json',
      },
    },
  })

  return echoInstance
}

export function subscribeTenantChannel(tenantId, handlers = {}) {
  const echo = getEcho()
  if (!echo || !tenantId) return null

  const channel = echo.private(`tenant.${tenantId}`)

  if (handlers.onMonitoring) {
    channel.listen('.monitoring.updated', handlers.onMonitoring)
  }
  if (handlers.onFlowRun) {
    channel.listen('.flow_run.updated', handlers.onFlowRun)
  }
  if (handlers.onAgentRun) {
    channel.listen('.agent_run.updated', handlers.onAgentRun)
  }
  if (handlers.onIntegration) {
    channel.listen('.integration.updated', handlers.onIntegration)
  }

  return channel
}

export function disconnectEcho() {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
  }
}
