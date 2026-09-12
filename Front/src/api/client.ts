import axios from 'axios'
import { getToken, clearSession } from './session'

const rawBase = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'
const baseURL = rawBase.endsWith('/v1') ? rawBase : rawBase.replace(/\/$/, '') + '/v1'
const client = axios.create({
  baseURL,
  withCredentials: false,
  headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
})

client.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

/**
 * Manejo de 401:
 * - Si hay sesión local, revalida una sola vez contra /me (por si el backend
 *   reinició y el token sigue siendo válido).
 * - Si falla o no hay sesión, limpia y avisa a la app (evento auth:expirado)
 *   para que el router/store decidan el redirect al login.
 */
let revalidando: Promise<void> | null = null

function forzarLogout(): void {
  clearSession()
  delete client.defaults.headers.common['Authorization']
  window.dispatchEvent(new CustomEvent('auth:expirado'))
}

client.interceptors.response.use(
  (r) => r,
  async (error) => {
    const status = error.response?.status
    if (status === 419) {
      error.response.data = { message: 'Sesión expirada. Recarga la página e intenta de nuevo.' }
    }
    if (status === 401) {
      const esLogin = String(error.config?.url ?? '').includes('/login')
      const yaReintentado = (error.config as any)?._revalidado === true

      if (!esLogin && !yaReintentado && getToken()) {
        error.config._revalidado = true
        try {
          revalidando = revalidando ?? client.get('/me').then(() => undefined)
          await revalidando
          return client.request(error.config)
        } catch {
          forzarLogout()
        } finally {
          revalidando = null
        }
      } else if (!esLogin) {
        forzarLogout()
      }
    }
    return Promise.reject(error)
  }
)

export default client
