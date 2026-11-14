import { packReportDesarrollo } from '../utils/reportMeta'

type BackendPayload = Record<string, any>

const categoriaMap: Record<string, string> = {
  'Mantenimiento Preventivo': 'preventivo',
  'Mantenimiento Correctivo': 'correctivo',
  Instalación: 'instalacion',
  Instalacion: 'instalacion',
  Mejora: 'mejora',
  Diagnóstico: 'diagnostico',
  Diagnostico: 'diagnostico',
  preventivo: 'preventivo',
  correctivo: 'correctivo',
  instalacion: 'instalacion',
  mejora: 'mejora',
  diagnostico: 'diagnostico',
}

const tipoMap: Record<string, string> = {
  Ticket: 'ticket',
  'Servicio Programado': 'servicio',
  Libre: 'libre',
  ticket: 'ticket',
  servicio: 'servicio',
  libre: 'libre',
}

const prioridadMap: Record<string, string> = {
  Baja: 'baja',
  Media: 'media',
  Alta: 'alta',
  baja: 'baja',
  media: 'media',
  alta: 'alta',
}

const estatusReporteMap: Record<string, string> = {
  'Abierto / Trabajo Parcial': 'parcial',
  Abierto: 'abierto',
  'Cerrado / Trabajo Finalizado': 'finalizado',
  'Descartado / Cancelado': 'descartado',
  parcial: 'parcial',
  finalizado: 'finalizado',
}

function clean(obj: Record<string, any>): BackendPayload {
  const out: BackendPayload = {}
  for (const [k, v] of Object.entries(obj)) {
    if (v === null || v === undefined || v === '') continue
    if (typeof v === 'string') {
      const t = v.trim()
      if (t === '') continue
      out[k] = t
    } else out[k] = v
  }
  return out
}

/**
 * Formatea fecha/hora respetando la hora local capturada por el usuario
 * (sin desfasar +6 horas a UTC como hacía toISOString()).
 */
export function toMysqlDatetime(val: any): string | null {
  if (!val) return null
  if (typeof val === 'string') {
    const trimmed = val
      .trim()
      .replace(/\.\d+Z$/i, '')
      .replace(/Z$/i, '')
    // Si ya viene como YYYY-MM-DDTHH:mm o YYYY-MM-DD HH:mm de un datetime-local
    const match = trimmed.match(/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::(\d{2}))?/)
    if (match) {
      return `${match[1]} ${match[2]}:${match[3] || '00'}`
    }
    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
      return `${trimmed} 00:00:00`
    }
  }
  const d = new Date(val)
  if (isNaN(d.getTime())) return null
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

export function toMysqlDateOnly(val: any): string | null {
  if (!val) return null
  if (typeof val === 'string') {
    const trimmed = val.trim()
    const match = trimmed.match(/^(\d{4}-\d{2}-\d{2})/)
    if (match && match[1]) return match[1]
  }
  const d = new Date(val)
  if (isNaN(d.getTime())) return null
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

export function mapTicketForBackend(front: any): BackendPayload {
  const raw = {
    folio: front.folio || undefined, // backend genera TKT-AAAAMMDD-0001 si falta
    asunto: front.asunto,
    descripcion: front.descripcion,
    solicitante: front.solicitante,
    cargo: front.cargo,
    prioridad: prioridadMap[front.prioridad] || 'media',
    area_id: front.area_id ?? front.idSubdependencia ?? null,
    equipo_id: front.equipo_id ?? null,
    fecha_limite: front.fecha_limite ? toMysqlDatetime(front.fecha_limite) : null,
  }
  return clean(raw)
}

export function mapServicioForBackend(front: any): BackendPayload {
  const fAsig = front.fecha_asignacion ?? front.fechaAsignacion
  const fVenc = front.fecha_vencimiento ?? front.fechaVencimiento
  const raw = {
    folio: front.folio || undefined,
    asunto: front.asunto,
    descripcion: front.descripcion,
    categoria: categoriaMap[front.categoria] || null,
    fecha_asignacion: fAsig ? toMysqlDateOnly(fAsig) : null,
    fecha_vencimiento: fVenc ? toMysqlDateOnly(fVenc) : null,
    area_id: front.area_id ?? front.idSubdependencia ?? null,
    equipo_id: front.equipo_id ?? null,
    solicitante: front.solicitante || null,
    cargo: front.cargo || null,
    usuario_asignado_id: front.usuario_asignado_id ?? front.idTecnicoAsignado ?? null,
    prioridad: front.prioridad ? prioridadMap[front.prioridad] || 'media' : 'media',
  }
  return clean(raw)
}

export function mapReporteForBackend(front: any, fileList: File[] = []): BackendPayload {
  const tipo = tipoMap[front.tipoReporte] || 'libre'
  const categoria = categoriaMap[front.categoria] || 'correctivo'
  const estatus = estatusReporteMap[front.estatusReporte] || 'parcial'

  if (tipo !== 'libre' && !front.origenDatosId)
    throw new Error('Selecciona un Ticket/Servicio origen')
  if (!front.fechaHoraInicio || !front.fechaHoraFin) throw new Error('Fecha inicio/fin requeridas')
  if (estatus === 'finalizado' && !front.horaRegresoABase)
    throw new Error('Hora regreso a base obligatoria al cerrar')

  const materialesLimpios = (front.materiales || [])
    .filter((m: any) => m && String(m.material || '').trim() !== '')
    .map((m: any, idx: number) => ({
      idItem: m.idItem || idx + 1,
      material: String(m.material).trim(),
      cantidad: Number(m.cantidad) || 1,
      unidad: String(m.unidad || 'Pza'),
    }))

  const evidenciaComentarios = (front.evidenciaFotografica || []).map((e: any) =>
    String(e?.comentario || '').trim()
  )

  const baseDesarrollo = String(front.desarrolloActividades || '').trim()
  const desarrolloConMeta = packReportDesarrollo(baseDesarrollo, {
    conclusion: String(front.conclusionTrabajo || '').trim() || undefined,
    responsable: String(front.responsableTexto || '').trim() || undefined,
    cargo: String(front.cargoTexto || '').trim() || undefined,
    falla: String(front.fallaReportada || '').trim() || undefined,
    fallaLibre: String(front.fallaReportada || '').trim() || undefined,
    areaTexto: String(front.areaTexto || '').trim() || undefined,
    materiales: materialesLimpios.length > 0 ? materialesLimpios : undefined,
    evidenciaComentarios: evidenciaComentarios.some(Boolean) ? evidenciaComentarios : undefined,
  })

  const payload = {
    // No enviamos folio para que el backend genere REP-AAAAMM-00001 secuencial
    tipo,
    ticket_id: tipo === 'ticket' ? front.origenDatosId : null,
    servicio_id: tipo === 'servicio' ? front.origenDatosId : null,
    categoria,
    fecha_inicio: toMysqlDatetime(front.fechaHoraInicio),
    fecha_fin: toMysqlDatetime(front.fechaHoraFin),
    hora_salida: toMysqlDatetime(front.horaSalidaBase),
    hora_llegada: toMysqlDatetime(front.horaLlegadaSitio),
    hora_regreso: toMysqlDatetime(front.horaRegresoABase),
    desarrollo: desarrolloConMeta,
    estatus,
    area_id: front.idSubdependencia || null,
    es_retrabajo: !!front.esRetrabajo,
    ejecutores: (front.ejecutoresIds || []).filter((id: any) => id && id !== 0),
    enviar_correo: !!front.enviarCorreo,
    destinatarios_correo: Array.isArray(front.destinatariosCorreo) ? front.destinatariosCorreo : [],
    comentario_correo: front.comentarioCorreo || '',
    _materialesRaw: materialesLimpios,
    _files: fileList,
  }
  return clean(payload)
}

export async function buildReporteFormData(mapped: BackendPayload, client: any): Promise<FormData> {
  const fd = new FormData()
  // Si viene folio explícito se respeta; si no, Reporte::generarFolio() crea REP-AAAAMM-00001
  if (mapped.folio) {
    fd.append('folio', String(mapped.folio))
  }
  fd.append('tipo', mapped.tipo)
  if (mapped.ticket_id) fd.append('ticket_id', String(mapped.ticket_id))
  if (mapped.servicio_id) fd.append('servicio_id', String(mapped.servicio_id))
  fd.append('categoria', mapped.categoria)
  fd.append('fecha_inicio', mapped.fecha_inicio)
  fd.append('fecha_fin', mapped.fecha_fin)
  if (mapped.hora_salida) fd.append('hora_salida', mapped.hora_salida)
  if (mapped.hora_llegada) fd.append('hora_llegada', mapped.hora_llegada)
  if (mapped.hora_regreso) fd.append('hora_regreso', mapped.hora_regreso)
  fd.append('desarrollo', mapped.desarrollo || '')
  fd.append('estatus', mapped.estatus)
  if (mapped.area_id) fd.append('area_id', String(mapped.area_id))
  if (mapped.es_retrabajo) fd.append('es_retrabajo', '1')

  if (mapped.enviar_correo) {
    fd.append('enviar_correo', '1')
    ;(mapped.destinatarios_correo || []).forEach((email: string) => {
      if (email && email.trim()) {
        fd.append('destinatarios_correo[]', String(email).trim())
      }
    })
    if (mapped.comentario_correo) {
      fd.append('comentario_correo', String(mapped.comentario_correo))
    }
  }

  ;(mapped.ejecutores || []).forEach((id: any) => fd.append('ejecutores[]', String(id)))

  if (mapped._materialesRaw && mapped._materialesRaw.length) {
    try {
      const catRes = await client.get('/materiales', { params: { per_page: 100 } })
      const cat = catRes.data?.data || catRes.data || []
      let idx = 0
      mapped._materialesRaw.forEach((m: any) => {
        const found = Array.isArray(cat)
          ? cat.find(
              (c: any) =>
                String(c.nombre || '')
                  .toLowerCase()
                  .trim() ===
                String(m.material || '')
                  .toLowerCase()
                  .trim()
            )
          : null
        const cantidad = Number(m.cantidad) || 1
        // Solo enviamos al descuento de inventario si existe exactamente en catálogo
        // y cuenta con stock suficiente; nunca sustituimos por cat[0].
        if (found && found.id && Number(found.stock_actual ?? 0) >= cantidad) {
          fd.append(`materiales[${idx}][material_id]`, String(found.id))
          fd.append(`materiales[${idx}][cantidad]`, String(cantidad))
          idx++
        }
      })
    } catch {
      /* no-op */
    }
  }

  ;(mapped._files || []).forEach((f: File) => fd.append('evidencias[]', f))

  return fd
}

export default {
  mapTicketForBackend,
  mapServicioForBackend,
  mapReporteForBackend,
  buildReporteFormData,
}
