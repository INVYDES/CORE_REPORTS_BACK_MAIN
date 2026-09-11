// @ts-nocheck
import { describe, it, expect } from 'vitest'
import { mapTicketForBackend, mapServicioForBackend, mapReporteForBackend } from './mapper'

describe('mapper.js - filtra y mapea payloads para el back', () => {
  it('mapTicketForBackend normaliza prioridad y area_id', () => {
    const out = mapTicketForBackend({ asunto: 'Falla', prioridad: 'Alta', idSubdependencia: 3, descripcion: 'x' })
    expect(out.prioridad).toBe('alta')
    expect(out.area_id).toBe(3)
    expect(out.asunto).toBe('Falla')
  })

  it('filtra vacíos y recorta', () => {
    const out = mapTicketForBackend({ asunto: '  Hola  ', solicitante: '', cargo: null, prioridad: 'Media' })
    expect(out.asunto).toBe('Hola')
    expect(out.solicitante).toBeUndefined()
    expect(out.cargo).toBeUndefined()
  })

  it('mapServicioForBackend mapea categoria larga a enum', () => {
    const out = mapServicioForBackend({ asunto: 'Mant', categoria: 'Mantenimiento Preventivo', fecha_asignacion: '2026-09-05' })
    expect(out.categoria).toBe('preventivo')
  })

  it('mapReporteForBackend valida y mapea tipo/estatus', () => {
    const front = {
      tipoReporte: 'Ticket',
      origenDatosId: 5,
      categoria: 'Mantenimiento Correctivo',
      fechaHoraInicio: '2026-09-05T10:00',
      fechaHoraFin: '2026-09-05T12:00',
      horaSalidaBase: '2026-09-05T08:00',
      horaRegresoABase: '2026-09-05T14:00',
      desarrolloActividades: 'Cambio balata',
      estatusReporte: 'Cerrado / Trabajo Finalizado',
      idSubdependencia: 2,
      responsableTexto: 'Ing. Juan',
      ejecutoresIds: [3, 0, 4],
      materiales: [],
      esRetrabajo: false,
    }
    const out = mapReporteForBackend(front, [])
    expect(out.tipo).toBe('ticket')
    expect(out.ticket_id).toBe(5)
    expect(out.categoria).toBe('correctivo')
    expect(out.estatus).toBe('finalizado')
    expect(out.area_id).toBe(2)
    expect(out.ejecutores).toEqual([3, 4])
  })

  it('lanza error si falta origen en reporte no libre', () => {
    expect(() =>
      mapReporteForBackend({ tipoReporte: 'Ticket', origenDatosId: null, fechaHoraInicio: '2026-09-05T10:00', fechaHoraFin: '2026-09-05T12:00', estatusReporte: 'Abierto / Trabajo Parcial' }, [])
    ).toThrow()
  })
})
