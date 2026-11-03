import { describe, expect, it } from 'vitest'
import { notificationTarget } from './notificacionTarget'

describe('notificationTarget', () => {
  it('navega al ticket real referenciado', () => {
    expect(notificationTarget({ referencia_tipo: 'ticket', referencia_id: 42 })).toEqual({
      name: 'ticket-detail',
      params: { id: 42 },
    })
  })

  it('navega al servicio real referenciado', () => {
    expect(notificationTarget({ referencia_tipo: 'servicio', referencia_id: 7 })).toEqual({
      name: 'service-detail',
      params: { id: 7 },
    })
  })

  it('navega al reporte real referenciado', () => {
    expect(notificationTarget({ referencia_tipo: 'reporte', referencia_id: 9 })).toEqual({
      name: 'report-detail',
      params: { id: 9 },
    })
  })

  it('no navega si la referencia no es válida', () => {
    expect(notificationTarget({ referencia_tipo: 'ticket', referencia_id: 0 })).toBeNull()
  })
})
