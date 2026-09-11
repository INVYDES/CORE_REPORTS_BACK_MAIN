import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from './authStore'

vi.mock('../api/client', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
    defaults: { headers: { common: {} } },
  },
}))

describe('authStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('inicia sin usuario', () => {
    const store = useAuthStore()
    expect(store.isAuthenticated).toBe(false)
    expect(store.currentUser).toBeNull()
  })

  it('setea token y usuario en login', async () => {
    const client = (await import('../api/client')).default as any
    client.post.mockResolvedValue({ data: { user: { id: 1, dependencia_id: 1, rol: 1, nombre: 'Admin', apellidos: 'Tec', email: 'a@b.com', estado: true }, token: 'abc123' } })
    const store = useAuthStore()
    const user = await store.login('a@b.com', 'password')
    expect(user.email).toBe('a@b.com')
    expect(store.isAuthenticated).toBe(true)
    expect(localStorage.getItem('token')).toBe('abc123')
  })
})
