// @ts-nocheck
const categoriaMap = {
  'Mantenimiento Preventivo': 'preventivo',
  'Mantenimiento Correctivo': 'correctivo',
  'Instalación': 'instalacion',
  'Instalacion': 'instalacion',
  'Mejora': 'mejora',
  'Diagnóstico': 'diagnostico',
  'Diagnostico': 'diagnostico',
  'preventivo': 'preventivo',
  'correctivo': 'correctivo',
  'instalacion': 'instalacion',
  'mejora': 'mejora',
  'diagnostico': 'diagnostico',
}

const tipoMap = {
  'Ticket': 'ticket',
  'Servicio Programado': 'servicio',
  'Libre': 'libre',
  'ticket': 'ticket',
  'servicio': 'servicio',
  'libre': 'libre',
}

const prioridadMap = {
  'Baja': 'baja', 'Media': 'media', 'Alta': 'alta',
  'baja': 'baja', 'media': 'media', 'alta': 'alta',
}

const estatusReporteMap = {
  'Abierto / Trabajo Parcial': 'parcial',
  'Abierto': 'abierto',
  'Cerrado / Trabajo Finalizado': 'finalizado',
  'Descartado / Cancelado': 'descartado',
  'parcial': 'parcial', 'finalizado': 'finalizado',
}

function clean(obj) {
  const out = {}
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

function toMysqlDatetime(val) {
  if (!val) return null
  const d = new Date(val)
  if (isNaN(d.getTime())) return null
  return d.toISOString().slice(0, 19).replace('T', ' ')
}

export function mapTicketForBackend(front) {
  const raw = {
    folio: front.folio || undefined, // backend genera si falta
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

export function mapServicioForBackend(front) {
  const raw = {
    folio: front.folio || undefined,
    asunto: front.asunto,
    descripcion: front.descripcion,
    categoria: categoriaMap[front.categoria] || null,
    fecha_asignacion: front.fecha_asignacion ? toMysqlDatetime(front.fecha_asignacion) : null,
    fecha_vencimiento: front.fecha_vencimiento ? toMysqlDatetime(front.fecha_vencimiento) : null,
    area_id: front.area_id ?? front.idSubdependencia ?? null,
    equipo_id: front.equipo_id ?? null,
    solicitante: front.solicitante || null,
    cargo: front.cargo || null,
    prioridad: front.prioridad || null,
  }
  return clean(raw)
}

export function mapReporteForBackend(front, fileList = []) {
  const tipo = tipoMap[front.tipoReporte] || 'libre'
  const categoria = categoriaMap[front.categoria] || 'correctivo'
  const estatus = estatusReporteMap[front.estatusReporte] || 'parcial'

  if (tipo !== 'libre' && !front.origenDatosId) throw new Error('Selecciona un Ticket/Servicio origen')
  if (!front.fechaHoraInicio || !front.fechaHoraFin) throw new Error('Fecha inicio/fin requeridas')
  if (estatus === 'finalizado' && !front.horaRegresoABase) throw new Error('Hora regreso a base obligatoria al cerrar')

  const payload = {
    folio: undefined, // backend lo genera si no viene, aquí lo dejamos que lo genere ReporteController
    tipo,
    ticket_id: tipo === 'ticket' ? front.origenDatosId : null,
    servicio_id: tipo === 'servicio' ? front.origenDatosId : null,
    categoria,
    fecha_inicio: toMysqlDatetime(front.fechaHoraInicio),
    fecha_fin: toMysqlDatetime(front.fechaHoraFin),
    hora_salida: toMysqlDatetime(front.horaSalidaBase),
    hora_llegada: toMysqlDatetime(front.horaLlegadaSitio),
    hora_regreso: toMysqlDatetime(front.horaRegresoABase),
    desarrollo: (front.desarrolloActividades || front.fallaReportada || '').trim(),
    estatus,
    area_id: front.idSubdependencia || null,
    es_retrabajo: !!front.esRetrabajo,
    ejecutores: (front.ejecutoresIds || []).filter(id => id && id !== 0),
    _materialesRaw: front.materiales || [],
    _files: fileList,
  }
  return clean(payload)
}

export async function buildReporteFormData(mapped, client) {
  const fd = new FormData()
  const folio = `REP-${Date.now().toString().slice(-6)}`
  fd.append('folio', folio)
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

  mapped.ejecutores.forEach(id => fd.append('ejecutores[]', String(id)))

  if (mapped._materialesRaw.length) {
    try {
      const catRes = await client.get('/materiales', { params: { per_page: 100 } })
      const cat = catRes.data?.data || catRes.data || []
      mapped._materialesRaw.forEach((m, idx) => {
        let matId = m.material_id
        if (!matId && m.material) {
          const found = cat.find(c => c.nombre.toLowerCase() === m.material.toLowerCase().trim())
          matId = found?.id || cat[0]?.id
        }
        if (matId) {
          fd.append(`materiales[${idx}][material_id]`, String(matId))
          fd.append(`materiales[${idx}][cantidad]`, String(m.cantidad || 1))
        }
      })
    } catch {}
  }

  ;(mapped._files || []).forEach(f => fd.append('evidencias[]', f))

  return fd
}

export default { mapTicketForBackend, mapServicioForBackend, mapReporteForBackend, buildReporteFormData }