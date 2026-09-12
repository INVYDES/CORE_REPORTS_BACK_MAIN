import { describe, it, expect, beforeEach } from 'vitest'
import {
  getToken, getUser, setSession, setToken, clearSession,
  type UsuarioAlmacenado,
} from './session'

const usuario: UsuarioAlmacenado = {
  id: 1, dependencia_id: 1, numero_empleado: 'EMP001', rol: 1,
  nombre: 'Admin', apellidos: 'Tec', email: 'a@b.com', estado: true,
}

describe('session', () => {
  beforeEach(() => {
    sessionStorage.clear()
    localStorage.clear()
  })

  it('guarda y lee token + user', () => {
    setSession('tok123', usuario)
    expect(getToken()).toBe('tok123')
    expect(getUser()?.email).toBe('a@b.com')
  })

  it('setToken actualiza solo el token', () => {
    setSession('tok123', usuario)
    setToken('tok456')
    expect(getToken()).toBe('tok456')
    expect(getUser()?.id).toBe(1)
  })

  it('clearSession elimina todo', () => {
    setSession('tok123', usuario)
    clearSession()
    expect(getToken()).toBeNull()
    expect(getUser()).toBeNull()
  })

  it('migra token jwt legacy de localStorage', () => {
    localStorage.setItem('jwt', 'legacy-jwt')
    expect(getToken()).toBe('legacy-jwt')
    expect(sessionStorage.getItem('token')).toBe('legacy-jwt')
    expect(localStorage.getItem('jwt')).toBeNull()
  })

  it('migra user legacy de localStorage', () => {
    localStorage.setItem('user', JSON.stringify(usuario))
    const u = getUser()
    expect(u?.email).toBe('a@b.com')
    expect(localStorage.getItem('user')).toBeNull()
  })

  it('devuelve null con JSON corrupto', () => {
    sessionStorage.setItem('user', '{corrupto')
    expect(getUser()).toBeNull()
  })

  it('clearSession limpia también claves legacy', () => {
    localStorage.setItem('jwt', 'x')
    localStorage.setItem('token', 'y')
    localStorage.setItem('user', 'z')
    clearSession()
    expect(localStorage.getItem('jwt')).toBeNull()
    expect(localStorage.getItem('token')).toBeNull()
    expect(localStorage.getItem('user')).toBeNull()
  })
})
