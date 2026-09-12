/**
 * Almacenamiento de sesión centralizado.
 *
 * Usa sessionStorage en lugar de localStorage: el token y los datos de
 * usuario viven solo mientras dura la pestaña, reduciendo la ventana de
 * robo de token vía XSS (se pierde al cerrar la pestaña).
 */
const TOKEN_KEY = 'token'
const USER_KEY = 'user'

function safeStorage(): Storage | null {
  try {
    if (typeof window === 'undefined') return null
    return window.sessionStorage
  } catch {
    return null
  }
}

/** Lee el token de la sesión; hace fallback a legacy localStorage y migra. */
export function getToken(): string | null {
  const s = safeStorage()
  const token = s?.getItem(TOKEN_KEY) ?? null
  if (token) return token

  // Migración desde la implementación anterior (localStorage)
  try {
    const legacy = window.localStorage.getItem(TOKEN_KEY) ?? window.localStorage.getItem('jwt')
    if (legacy) {
      s?.setItem(TOKEN_KEY, legacy)
      window.localStorage.removeItem(TOKEN_KEY)
      window.localStorage.removeItem('jwt')
      return legacy
    }
  } catch {
    /* no-op */
  }
  return null
}

/** Lee el usuario de la sesión; hace fallback a legacy localStorage y migra. */
export function getUser(): UsuarioAlmacenado | null {
  const s = safeStorage()
  const raw = s?.getItem(USER_KEY) ?? null
  if (raw) {
    try {
      return JSON.parse(raw) as UsuarioAlmacenado
    } catch {
      s?.removeItem(USER_KEY)
    }
  }

  try {
    const legacy = window.localStorage.getItem(USER_KEY)
    if (legacy) {
      s?.setItem(USER_KEY, legacy)
      window.localStorage.removeItem(USER_KEY)
      try {
        return JSON.parse(legacy) as UsuarioAlmacenado
      } catch {
        return null
      }
    }
  } catch {
    /* no-op */
  }
  return null
}

export function setSession(token: string, user: UsuarioAlmacenado): void {
  const s = safeStorage()
  s?.setItem(TOKEN_KEY, token)
  s?.setItem(USER_KEY, JSON.stringify(user))
}

export function setToken(token: string): void {
  safeStorage()?.setItem(TOKEN_KEY, token)
}

export function clearSession(): void {
  const s = safeStorage()
  s?.removeItem(TOKEN_KEY)
  s?.removeItem(USER_KEY)
  // Limpieza legacy
  try {
    window.localStorage.removeItem('token')
    window.localStorage.removeItem('jwt')
    window.localStorage.removeItem('user')
  } catch {
    /* no-op */
  }
}

export interface UsuarioAlmacenado {
  id: number
  dependencia_id: number
  numero_empleado: string | null
  rol: number
  nombre: string
  apellidos: string
  email: string
  estado: boolean
  dependencia?: unknown
}
