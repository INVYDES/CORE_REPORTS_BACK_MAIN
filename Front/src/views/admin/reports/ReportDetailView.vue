<script setup lang="ts">
  import { ref, onMounted, reactive, computed } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { useToast } from 'vue-toastification'
  import {
    ArrowLeft,
    User,
    Calendar,
    Clock,
    CheckCircle2,
    FileText,
    Wrench,
    Building2,
    Eye,
    Mail,
    Send,
    X,
  } from 'lucide-vue-next'

  // Import Data
  import client from '../../../api/client'
  import { downloadBlob } from '../../../utils/download'
  import {
    packReportDesarrollo,
    unpackReportDesarrollo,
    formatLocalDateTime,
    formatLocalDateShort,
    parseLocalDate,
    toInputDatetimeLocal,
    calcNetHoursText,
    type ReportExtraMeta,
  } from '../../../utils/reportMeta'
  import { toMysqlDatetime } from '../../../api/mapper'
  import { Upload, Trash2, Plus } from 'lucide-vue-next'

  // Pinia store
  import { useAuthStore } from '../../../stores/authStore'
  import { storeToRefs } from 'pinia'

  const authStore = useAuthStore()
  const { currentUser } = storeToRefs(authStore)


  const route = useRoute()
  const router = useRouter()
  const toast = useToast()

  const goBack = () => {
    router.back()
  }

  const reportId = Number(route.params.id)
  const report = ref<any>(null)
  const reportExtraMeta = ref<ReportExtraMeta>({})
  const users = ref<any[]>([])
  const areas = ref<any[]>([])

  const fileNameFromUrl = (url: string) => {
    if (!url) return ''
    try {
      const path = new URL(url, window.location.origin).pathname
      const name = decodeURIComponent(path.split('/').pop() || '')
      return name
    } catch {
      return url.split('/').pop() || ''
    }
  }

  const isImageEvidence = (item: any) =>
    String(item.tipoArchivo || '').startsWith('image/') ||
    /\.(jpe?g|png|gif|webp|svg)(\?.*)?$/i.test(String(item.url || ''))

  const tipoLabel = (code: string) =>
    (({ ticket: 'Ticket', servicio: 'Servicio Programado', libre: 'Libre' }) as any)[code] || code
  const catLabel = (code: string) =>
    (
      ({
        preventivo: 'Mantenimiento Preventivo',
        correctivo: 'Mantenimiento Correctivo',
        instalacion: 'Instalación',
        mejora: 'Mejora',
        diagnostico: 'Diagnóstico',
      }) as any
    )[code] || code
  const estatusLabel = (code: string) =>
    (
      ({
        finalizado: 'Cerrado / Trabajo Finalizado',
        abierto: 'Abierto / Trabajo Parcial',
        parcial: 'Abierto / Trabajo Parcial',
        descartado: 'Descartado / Cancelado',
      }) as any
    )[code] || code

  const applyReport = (raw: any) => {
    if (!raw) {
      report.value = null
      return
    }
    const dep =
      (currentUser.value as any)?.dependencia_id ?? (currentUser.value as any)?.idDependencia
    if (raw.dependencia_id !== undefined && dep !== undefined && Number(raw.dependencia_id) !== Number(dep)) {
      console.warn('Advertencia de dependencia:', raw.dependencia_id, 'vs usuario:', dep)
    }
    const origen = raw.ticket || raw.servicio || null
    const unpacked = unpackReportDesarrollo(raw.desarrollo || '')
    reportExtraMeta.value = unpacked.meta || {}
    const dbMateriales = (raw.materiales || []).map((m: any) => ({
      idItem: m.id,
      material: m.material?.nombre || m.nombre || 'Material',
      cantidad: Number(m.cantidad) || 1,
      unidad: m.material?.unidad_base || m.unidad || 'pza',
    }))
    const metaMateriales = (unpacked.meta.materiales || []).map((m, idx) => ({
      idItem: idx + 1,
      material: m.material || 'Material',
      cantidad: Number(m.cantidad) || 1,
      unidad: m.unidad || 'pza',
    }))

    report.value = {
      id: raw.id,
      idReporte: raw.id,
      folio: raw.folio,
      idDependencia: raw.dependencia_id,
      idSubdependencia: raw.area_id ?? undefined,
      creadoPor: raw.creado_por,
      tipoReporte: tipoLabel(raw.tipo),
      origenDatosId: raw.ticket_id ?? raw.servicio_id ?? null,
      originFolio: origen?.folio || '',
      originAsunto: origen?.asunto || '',
      categoria: catLabel(raw.categoria),
      fechaElaboracion: raw.fecha_inicio,
      fechaHoraInicio: raw.fecha_inicio,
      fechaHoraFin: raw.fecha_fin,
      horaSalidaBase: raw.hora_salida,
      horaLlegadaSitio: raw.hora_llegada,
      horaRegresoABase: raw.hora_regreso,
      fallaReportada:
        unpacked.meta.falla ||
        unpacked.meta.fallaLibre ||
        (origen?.descripcion
          ? (String(origen.descripcion).match(/[📋]?\s*Descripción de la Falla:\s*([\s\S]*?)(?=\n\n[📷]?\s*Evidencias|\n\n--|$)/i)?.[1] || '').trim() ||
            origen?.asunto
          : origen?.asunto) ||
        undefined,
      areaTexto: unpacked.meta.areaTexto || undefined,
      desarrolloActividades: unpacked.text || '',
      conclusionTrabajo: unpacked.meta.conclusion || undefined,
      responsableTexto: unpacked.meta.responsable || '',
      cargoTexto: unpacked.meta.cargo || '',
      esRetrabajo: !!raw.es_retrabajo,
      responsableId: raw.responsable_id,
      ejecutoresIds: (raw.ejecutores || []).map((e: any) => e.usuario_id),
      materiales: metaMateriales.length > 0 ? metaMateriales : dbMateriales,
      evidenciaFotografica: (raw.evidencias || [])
        .map((ev: any, idx: number) => ({
          id: ev.id,
          url: ev.url || ev.ruta_archivo || ev.path || '',
          comentario:
            ev.comentario ||
            ev.descripcion ||
            unpacked.meta.evidenciaComentarios?.[idx] ||
            '',
          tipoArchivo: ev.tipo_archivo || ev.tipoArchivo || '',
          nombre: fileNameFromUrl(ev.url || ev.ruta_archivo || ev.path || ''),
        }))
        .filter((ev: any) => ev.url),
      historialModificaciones: [],
      estatusReporte: estatusLabel(raw.estatus),
    }
  }

  const loadReport = async () => {
    try {
      const res = await client.get(`/reportes/${reportId}`).then(r => r.data)
      applyReport(res.data || res)
    } catch (err: any) {
      console.error('Error cargando reporte:', err)
      const msg = err?.response?.data?.message || err?.message || 'Error del servidor al obtener reporte'
      toast.error(`Error al cargar reporte: ${msg}`)
      report.value = null
    }
    try {
      const res = await client.get('/tecnicos').then(r => r.data)
      const list = res.data || res || []
      if (Array.isArray(list)) users.value = list
    } catch (err: any) {
      console.error('Error cargando técnicos:', err)
    }
    try {
      const res = await client.get('/areas', { params: { per_page: 100 } }).then(r => r.data)
      const list = res.data || res || []
      if (Array.isArray(list)) areas.value = list
    } catch {
      /* no-op */
    }
  }
  onMounted(() => {
    loadReport()
    loadEmpresa()
  })

  const getUserName = (id: number | null) => {
    if (!id) return 'Sin Asignar'
    const user = users.value.find((u: any) => u.id === id)
    return user ? `${user.nombre} ${user.apellidos}`.trim() : 'Usuario Desconocido'
  }

  const getEjecutoresNames = (ids: number[]) => {
    if (!ids || ids.length === 0) return 'Ninguno'
    return ids.map(id => getUserName(id)).join(', ')
  }

  const formatDate = (dateString: string) => {
    return formatLocalDateTime(dateString)
  }

  const getOrigenDetails = () => {
    if (!report.value) return { folio: 'N/A', asunto: '' }
    if (report.value.tipoReporte === 'Libre' || !report.value.origenDatosId) {
      return { folio: 'Reporte Libre', asunto: report.value.fallaReportada || 'Independiente' }
    }
    const rawFolio = report.value.originFolio || `#${report.value.origenDatosId}`
    const cleanFolio = rawFolio.replace(/^(Ticket|Servicio):\s*/i, '')
    return {
      folio: cleanFolio,
      asunto: report.value.originAsunto || report.value.fallaReportada || '',
    }
  }

  const splitParagraphs = (raw?: string | null): string[] => {
    if (!raw) return []
    const lines = String(raw)
      .split(/\r?\n/)
      .map(l => l.trim())

    const paragraphs: string[] = []
    let current = ''

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i]
      if (!line) {
        if (current) {
          paragraphs.push(current)
          current = ''
        }
        continue
      }

      if (!current) {
        current = line
        continue
      }

      // Si el renglón anterior no termina en signo de cierre (. : ; ! ?)
      // y el siguiente empieza con minúscula (texto copiado de PDF partido a mitad de oración),
      // unirlo al mismo párrafo con un espacio.
      const prevEndsSentence = /[.!?:;]$/.test(current)
      const nextStartsLower = /^[a-záéíóúñü]/.test(line)
      const nextIsBullet = /^([-•*]|\d+[.)]|[A-Z]:\s)/.test(line)

      if (!prevEndsSentence && nextStartsLower && !nextIsBullet) {
        current += ' ' + line
      } else {
        paragraphs.push(current)
        current = line
      }
    }

    if (current) {
      paragraphs.push(current)
    }

    return paragraphs.length > 0 ? paragraphs : [String(raw).trim()]
  }

  // -- Full Edit Modal Logic (#13) --
  const showEditModal = ref(false)
  const isSavingEdit = ref(false)
  const editFileInput = ref<HTMLInputElement | null>(null)
  const editNewFiles = ref<File[]>([])

  const availableTechniciansForEdit = computed(() =>
    users.value.filter((x: any) => [1, 3].includes(Number(x.rol)))
  )

  const editForm = reactive({
    categoria: 'Mantenimiento Correctivo',
    esRetrabajo: false,
    idSubdependencia: null as number | null,
    responsableTexto: '',
    cargoTexto: '',
    ejecutoresIds: [] as number[],
    horaSalidaBase: '',
    horaLlegadaSitio: '',
    fechaHoraInicio: '',
    fechaHoraFin: '',
    horaRegresoABase: '',
    fallaReportada: '',
    desarrolloActividades: '',
    conclusionTrabajo: '',
    estatusReporte: 'Abierto / Trabajo Parcial',
    materiales: [] as Array<{ idItem: number; material: string; cantidad: number; unidad: string }>,
    evidenciaFotografica: [] as Array<{
      id?: number
      url: string
      comentario: string
      tipoArchivo: string
      nombre: string
      isNew?: boolean
      fileIndex?: number
    }>,
  })

  const editTotalHours = computed(() =>
    calcNetHoursText(editForm.fechaHoraInicio, editForm.fechaHoraFin)
  )

  const addEditEjecutor = () => {
    if (editForm.ejecutoresIds.length < 4) {
      editForm.ejecutoresIds.push(0)
    }
  }

  const removeEditEjecutor = (idx: number) => {
    editForm.ejecutoresIds.splice(idx, 1)
  }

  const addEditMaterialRow = () => {
    editForm.materiales.push({
      idItem: editForm.materiales.length + 1,
      material: '',
      cantidad: 1,
      unidad: 'Pieza',
    })
  }

  const removeEditMaterialRow = (idx: number) => {
    editForm.materiales.splice(idx, 1)
    editForm.materiales.forEach((m, i) => {
      m.idItem = i + 1
    })
  }

  const triggerEditFileInput = () => editFileInput.value?.click()

  const onEditFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement
    if (!input.files) return
    const remaining = 20 - editForm.evidenciaFotografica.length
    if (remaining <= 0) {
      toast.warning('Máximo 20 evidencias por reporte.')
      input.value = ''
      return
    }
    const filesToAdd = Array.from(input.files).slice(0, remaining)
    for (const file of filesToAdd) {
      const ext = (file.name.split('.').pop() || '').toLowerCase()
      const isImg =
        file.type.startsWith('image/') ||
        ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)
      const blobUrl = isImg ? URL.createObjectURL(file) : ''
      const fileIdx = editNewFiles.value.length
      editNewFiles.value.push(file)
      editForm.evidenciaFotografica.push({
        url: blobUrl,
        comentario: '',
        tipoArchivo: file.type || ext,
        nombre: file.name,
        isNew: true,
        fileIndex: fileIdx,
      })
    }
    input.value = ''
  }

  const removeEditEvidence = (idx: number) => {
    const item = editForm.evidenciaFotografica[idx]
    if (item && item.url && item.url.startsWith('blob:')) {
      URL.revokeObjectURL(item.url)
    }
    editForm.evidenciaFotografica.splice(idx, 1)
  }

  const openEditModal = () => {
    if (!report.value) return
    editNewFiles.value = []
    editForm.categoria = report.value.categoria || 'Mantenimiento Correctivo'
    editForm.esRetrabajo = !!report.value.esRetrabajo
    editForm.idSubdependencia = report.value.idSubdependencia ?? null
    editForm.responsableTexto =
      report.value.responsableTexto || displayResponsableNombre.value || ''
    editForm.cargoTexto =
      report.value.cargoTexto ||
      (displayResponsableCargo.value !== '---' ? displayResponsableCargo.value : '')
    editForm.ejecutoresIds = Array.isArray(report.value.ejecutoresIds)
      ? [...report.value.ejecutoresIds]
      : []
    editForm.horaSalidaBase = toInputDatetimeLocal(report.value.horaSalidaBase)
    editForm.horaLlegadaSitio = toInputDatetimeLocal(report.value.horaLlegadaSitio)
    editForm.fechaHoraInicio = toInputDatetimeLocal(report.value.fechaHoraInicio)
    editForm.fechaHoraFin = toInputDatetimeLocal(report.value.fechaHoraFin)
    editForm.horaRegresoABase = toInputDatetimeLocal(report.value.horaRegresoABase)
    editForm.fallaReportada = report.value.fallaReportada || ''
    editForm.desarrolloActividades = report.value.desarrolloActividades || ''
    editForm.conclusionTrabajo = report.value.conclusionTrabajo || ''
    editForm.estatusReporte = report.value.estatusReporte || 'Abierto / Trabajo Parcial'
    editForm.materiales = (report.value.materiales || []).map((m: any, idx: number) => ({
      idItem: idx + 1,
      material: m.material || '',
      cantidad: Number(m.cantidad) || 1,
      unidad: m.unidad || 'Pieza',
    }))
    editForm.evidenciaFotografica = (report.value.evidenciaFotografica || []).map((ev: any) => ({
      id: ev.id,
      url: ev.url,
      comentario: ev.comentario || '',
      tipoArchivo: ev.tipoArchivo || '',
      nombre: ev.nombre || '',
      isNew: false,
    }))
    showEditModal.value = true
  }

  const saveReportEdit = async () => {
    if (!report.value || !currentUser.value || isSavingEdit.value) return
    if (!editForm.fechaHoraInicio || !editForm.fechaHoraFin) {
      toast.error('Las horas de inicio y fin de trabajo son obligatorias.')
      return
    }
    const rawEstatus =
      (
        {
          'Cerrado / Trabajo Finalizado': 'finalizado',
          'Abierto / Trabajo Parcial': 'parcial',
          'Descartado / Cancelado': 'descartado',
        } as any
      )[editForm.estatusReporte] || 'parcial'

    if (rawEstatus === 'finalizado' && !editForm.horaRegresoABase) {
      toast.error('La hora de regreso a base es obligatoria al cerrar (finalizar) el reporte.')
      return
    }

    const rawCategoria =
      (
        {
          'Mantenimiento Preventivo': 'preventivo',
          'Mantenimiento Correctivo': 'correctivo',
          Instalación: 'instalacion',
          Mejora: 'mejora',
          Diagnóstico: 'diagnostico',
        } as any
      )[editForm.categoria] || 'correctivo'

    const materialesLimpios = editForm.materiales
      .filter(m => m && String(m.material || '').trim() !== '')
      .map((m, idx) => ({
        idItem: idx + 1,
        material: String(m.material).trim(),
        cantidad: Number(m.cantidad) || 1,
        unidad: String(m.unidad || 'Pieza'),
      }))

    const evidenciaComentarios = editForm.evidenciaFotografica.map(ev =>
      String(ev.comentario || '').trim()
    )

    const updatedMeta: ReportExtraMeta = {
      ...reportExtraMeta.value,
      conclusion: String(editForm.conclusionTrabajo || '').trim() || undefined,
      responsable: String(editForm.responsableTexto || '').trim() || undefined,
      cargo: String(editForm.cargoTexto || '').trim() || undefined,
      fallaLibre:
        report.value.tipoReporte === 'Libre'
          ? String(editForm.fallaReportada || '').trim() || undefined
          : reportExtraMeta.value.fallaLibre,
      materiales: materialesLimpios,
      evidenciaComentarios,
    }

    const packedDesarrollo = packReportDesarrollo(editForm.desarrolloActividades, updatedMeta)
    const cleanEjecutores = editForm.ejecutoresIds.filter(id => id && Number(id) > 0)
    const keepEvidenciaIds = editForm.evidenciaFotografica
      .filter(ev => !ev.isNew && ev.id)
      .map(ev => Number(ev.id))
    const newFilesToUpload = editForm.evidenciaFotografica
      .filter(ev => ev.isNew && ev.fileIndex !== undefined)
      .map(ev => editNewFiles.value[ev.fileIndex!])
      .filter((f): f is File => Boolean(f))

    isSavingEdit.value = true
    try {
      const fd = new FormData()
      fd.append('_method', 'PUT')
      fd.append('categoria', rawCategoria)
      fd.append('estatus', rawEstatus)
      fd.append('es_retrabajo', editForm.esRetrabajo ? '1' : '0')
      if (editForm.idSubdependencia) {
        fd.append('area_id', String(editForm.idSubdependencia))
      }
      const fInicio = toMysqlDatetime(editForm.fechaHoraInicio)
      const fFin = toMysqlDatetime(editForm.fechaHoraFin)
      const hSalida = toMysqlDatetime(editForm.horaSalidaBase)
      const hLlegada = toMysqlDatetime(editForm.horaLlegadaSitio)
      const hRegreso = toMysqlDatetime(editForm.horaRegresoABase)
      if (fInicio) fd.append('fecha_inicio', fInicio)
      if (fFin) fd.append('fecha_fin', fFin)
      if (hSalida) fd.append('hora_salida', hSalida)
      if (hLlegada) fd.append('hora_llegada', hLlegada)
      if (hRegreso) fd.append('hora_regreso', hRegreso)
      fd.append('desarrollo', packedDesarrollo)

      fd.append('sync_ejecutores', '1')
      cleanEjecutores.forEach(uid => fd.append('ejecutores[]', String(uid)))

      fd.append('sync_evidencias', '1')
      keepEvidenciaIds.forEach(eid => fd.append('keep_evidencias[]', String(eid)))
      newFilesToUpload.forEach(file => fd.append('evidencias[]', file))

      const res = await client.post(`/reportes/${report.value.idReporte}`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      const updatedRaw = res.data?.data || res.data
      if (updatedRaw && updatedRaw.id) {
        applyReport(updatedRaw)
      } else {
        await loadReport()
      }

      toast.success('Reporte actualizado correctamente')
      showEditModal.value = false
    } catch (e: any) {
      toast.error(
        e?.response?.data?.message || 'No se pudo guardar el reporte. Intenta de nuevo.'
      )
    } finally {
      isSavingEdit.value = false
    }
  }

  const openSurvey = () => {
    if (!report.value) return
    router.push({ name: 'report-survey', params: { id: report.value.idReporte } })
  }


  const previewUrl = ref<string | null>(null)
  const isDownloading = ref(false)
  const isExporting = ref(false)
  const showPdfPreviewModal = ref(false)
  const previewHtml = ref('')
  const previewScrollContainer = ref<HTMLElement | null>(null)

  const openPdfPreview = () => {
    if (!report.value) return
    previewHtml.value = buildPrintableReportHtml()
    showPdfPreviewModal.value = true
    setTimeout(() => {
      if (previewScrollContainer.value) {
        previewScrollContainer.value.scrollTop = 0
      }
    }, 60)
  }

  const printDirectly = () => {
    if (!report.value) return
    const printContent = buildPrintableReportHtml()
    const printWindow = window.open('', '_blank', 'width=850,height=1000')
    if (!printWindow) {
      exportPDF()
      return
    }
    printWindow.document.write(`<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>${report.value.folio || 'Reporte'}</title>
  <style>
    @page { size: portrait; margin: 8mm; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; background: #ffffff; color: #000000; font-family: Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  </style>
</head>
<body>
  ${printContent}
  <script>
    window.onload = function() {
      setTimeout(function() {
        window.print();
      }, 500);
    };
  <\/script>
</body>
</html>`)
    printWindow.document.close()
  }

  const canEditReport = computed(() => {
    if (!report.value || !currentUser.value) return false
    const uid = Number((currentUser.value as any).id ?? (currentUser.value as any).idUsuario)
    return Number(report.value.creadoPor) === uid
  })

  const buildPrintableReportHtml = () => {
    const r = report.value
    const esc = (v: any) =>
      String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')

    const formatParagraphsHtml = (text?: string | null, fallback = 'Sin información.') => {
      const list = splitParagraphs(text)
      if (list.length === 0) {
        return `<p class="pdf-item" style="font-family:Arial,sans-serif;font-size:9pt;line-height:1.38;color:#000000;margin:0 0 8px 0;text-align:justify;">${esc(fallback)}</p>`
      }
      return list
        .map(
          p =>
            `<p class="pdf-item" style="font-family:Arial,sans-serif;font-size:9pt;line-height:1.38;color:#000000;margin:0 0 9px 0;text-align:justify;text-justify:inter-word;">${esc(p)}</p>`
        )
        .join('')
    }
    const orig = getOrigenDetails()
    const conclusionText =
      r.conclusionTrabajo ||
      (r.estatusReporte === 'Cerrado / Trabajo Finalizado'
        ? 'Trabajo finalizado satisfactoriamente.'
        : 'Trabajo en proceso, pendiente de finalización.')

    const tecUser = users.value.find((u: any) => u.id === r.creadoPor) || null
    const tecNombre = tecUser
      ? `${tecUser.nombre} ${tecUser.apellidos}`.trim()
      : getUserName(r.creadoPor) || 'Técnico Especialista'
    const tecPuesto =
      tecUser?.puesto ||
      (tecUser ? getRoleText(tecUser.rol) : displayResponsableCargo.value || 'Especialista Técnico')
    const tecNum =
      tecUser?.numero_empleado ||
      tecUser?.num_empleado ||
      String(tecUser?.id || r.creadoPor || '001').padStart(3, '0')
    const tecFecha = formatDateShort(r.fechaElaboracion || r.fechaHoraInicio)
    const tecEmail = tecUser?.email || empresa.value?.email || '—'

    const origFolio = orig.folio || '0000'
    const origFecha = formatDateShort(r.fechaElaboracion || r.fechaHoraInicio)
    const origArea = areaName.value || 'Área General'
    const origRespArea = displayResponsableNombre.value || '—'

    const matsRows =
      r.materiales && r.materiales.length > 0
        ? r.materiales
            .map(
              (m: any, i: number) => `
              <tr>
                <td style="padding:4px 6px;border:1px solid #000000;text-align:center;">${i + 1}</td>
                <td style="padding:4px 8px;border:1px solid #000000;">${esc(m.material)}</td>
                <td style="padding:4px 6px;border:1px solid #000000;text-align:center;">${esc(m.cantidad)}</td>
                <td style="padding:4px 6px;border:1px solid #000000;text-align:center;">${esc(m.unidad)}</td>
              </tr>`
            )
            .join('')
        : ''

    const evidencePairs: any[][] = []
    if (r.evidenciaFotografica && r.evidenciaFotografica.length > 0) {
      for (let i = 0; i < r.evidenciaFotografica.length; i += 2) {
        evidencePairs.push([r.evidenciaFotografica[i], r.evidenciaFotografica[i + 1]])
      }
    }

    const evidsHtml =
      evidencePairs.length > 0
        ? `<table style="width:100%;border-collapse:collapse;margin-top:4px;margin-bottom:12px;">` +
          evidencePairs
            .map(
              pair => `
              <tr class="pdf-item evidence-row" style="page-break-inside:avoid;break-inside:avoid;">
                <td style="width:50%;padding:3px 8px 10px 0;vertical-align:top;">
                  ${
                    isImageEvidence(pair[0])
                      ? `<div style="border:1px solid #cbd5e1;border-radius:2px;padding:3px;background:#ffffff;text-align:center;">
                           <img src="${esc(pair[0].url)}" style="width:100%;max-height:165px;height:auto;object-fit:contain;display:block;margin:0 auto;" />
                         </div>`
                      : `<div style="padding:10px;background:#f1f5f9;border:1px solid #cbd5e1;font-size:9.5px;">Documento: ${esc(pair[0].nombre || pair[0].url)}</div>`
                  }
                  <div style="font-family:Arial,sans-serif;font-size:8pt;color:#000000;line-height:1.3;margin-top:4px;text-align:left;">${esc(pair[0].comentario || '')}</div>
                </td>
                <td style="width:50%;padding:3px 0 10px 8px;vertical-align:top;">
                  ${
                    pair[1]
                      ? (isImageEvidence(pair[1])
                          ? `<div style="border:1px solid #cbd5e1;border-radius:2px;padding:3px;background:#ffffff;text-align:center;">
                               <img src="${esc(pair[1].url)}" style="width:100%;max-height:165px;height:auto;object-fit:contain;display:block;margin:0 auto;" />
                             </div>`
                          : `<div style="padding:10px;background:#f1f5f9;border:1px solid #cbd5e1;font-size:9.5px;">Documento: ${esc(pair[1].nombre || pair[1].url)}</div>`) +
                        `<div style="font-family:Arial,sans-serif;font-size:8pt;color:#000000;line-height:1.3;margin-top:4px;text-align:left;">${esc(pair[1].comentario || '')}</div>`
                      : ''
                  }
                </td>
              </tr>`
            )
            .join('') +
          `</table>`
        : `<p class="pdf-item" style="font-family:Arial,sans-serif;font-size:8.5pt;color:#64748b;font-style:italic;margin:0 0 12px 0;">No se registraron evidencias fotográficas.</p>`

    return `
<div style="width:750px;margin:0 auto;padding:16px 20px;font-family:Arial,Helvetica,sans-serif;font-size:9pt;color:#000000;background:#ffffff;line-height:1.35;box-sizing:border-box;">
  <!-- ENCABEZADO INSTITUCIONAL -->
  <table class="pdf-item pdf-header-table" style="width:100%;border-collapse:collapse;border:2px solid #000000;margin-bottom:14px;table-layout:fixed;page-break-inside:avoid;break-inside:avoid;">
    <tr>
      <td style="width:28%;max-width:210px;height:88px;border-right:2px solid #000000;text-align:center;vertical-align:middle;padding:4px 6px;overflow:hidden;background:#ffffff;">
        ${
          companyLogo.value
            ? `<img src="${esc(companyLogo.value)}" style="max-height:80px;max-width:190px;width:auto;height:auto;object-fit:contain;display:block;margin:0 auto;" alt="Logo" />`
            : `<div style="font-size:22px;font-weight:bold;color:#000000;letter-spacing:1px;">${esc(companyName.value.substring(0, 4).toUpperCase())}</div>`
        }
      </td>
      <td style="width:72%;padding:0;vertical-align:middle;">
        <div style="font-family:Arial,sans-serif;font-size:13pt;font-weight:bold;text-align:center;padding:6px 6px;border-bottom:2px solid #000000;text-transform:uppercase;letter-spacing:0.5px;color:#000000;">
          ${esc(companyName.value)}
        </div>
        <div style="background-color:#9ca3af;color:#ffffff;font-family:Arial,sans-serif;font-size:10.5pt;font-weight:bold;text-align:center;padding:4px 6px;border-bottom:2px solid #000000;letter-spacing:1px;">
          ${esc(r.folio)}
        </div>
        <div style="font-family:Arial,sans-serif;font-size:9pt;font-weight:bold;text-align:center;padding:5px 6px;text-transform:uppercase;color:#000000;">
          REPORTE DE MANTENIMIENTO / ORDEN DE TRABAJO ${r.categoria ? `(${esc(r.categoria.toUpperCase())})` : ''}
        </div>
      </td>
    </tr>
  </table>

  <!-- DATOS GENERALES -->
  <div class="pdf-item pdf-section-title" style="font-family:Arial,sans-serif;font-weight:bold;font-size:9.5pt;margin-top:10px;margin-bottom:4px;color:#000000;text-transform:uppercase;letter-spacing:0.3px;page-break-after:avoid;break-after:avoid;">
    DATOS GENERALES
  </div>
  <table class="pdf-item" style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:8.5pt;line-height:1.45;margin-bottom:8px;page-break-inside:avoid;break-inside:avoid;">
    <tr>
      <td style="width:50%;padding:2px 8px 2px 0;vertical-align:top;color:#000000;">
        <strong style="font-weight:bold;">Responsable:</strong> ${esc(tecNombre)}
      </td>
      <td style="width:50%;padding:2px 0 2px 8px;vertical-align:top;color:#000000;">
        <strong style="font-weight:bold;">OT/OC/Ticket:</strong> ${esc(origFolio)}
      </td>
    </tr>
    <tr>
      <td style="padding:2px 8px 2px 0;vertical-align:top;color:#000000;">
        <strong style="font-weight:bold;">Puesto:</strong> ${esc(tecPuesto)}
      </td>
      <td style="padding:2px 0 2px 8px;vertical-align:top;color:#000000;">
        <strong style="font-weight:bold;">Área:</strong> ${esc(origArea)}
      </td>
    </tr>
    <tr>
      <td style="padding:2px 8px 2px 0;vertical-align:top;color:#000000;">
        <strong style="font-weight:bold;">No. Empleado:</strong> ${esc(tecNum)}
      </td>
      <td style="padding:2px 0 2px 8px;vertical-align:top;color:#000000;">
        <strong style="font-weight:bold;">Responsable de Área:</strong> ${esc(origRespArea)}
      </td>
    </tr>
    <tr>
      <td style="padding:2px 8px 2px 0;vertical-align:top;color:#000000;">
        <strong style="font-weight:bold;">Fecha:</strong> ${esc(tecFecha)}
      </td>
      <td style="padding:2px 0 2px 8px;vertical-align:top;"></td>
    </tr>
    <tr>
      <td style="padding:2px 8px 2px 0;vertical-align:top;color:#000000;">
        <strong style="font-weight:bold;">Correos:</strong> ${esc(tecEmail)}
      </td>
      <td style="padding:2px 0 2px 8px;vertical-align:top;"></td>
    </tr>
  </table>

  <!-- SEPARADOR HORIZONTAL -->
  <div style="border-top:2px solid #000000;margin:8px 0 10px 0;width:100%;clear:both;"></div>

  <!-- FALLA REPORTADA -->
  <div class="pdf-item pdf-section-title" style="font-family:Arial,sans-serif;font-weight:bold;font-size:9.5pt;margin-top:10px;margin-bottom:4px;color:#000000;text-transform:uppercase;page-break-after:avoid;break-after:avoid;">
    DESCRIPCIÓN DE LA FALLA REPORTADA
  </div>
  <div style="margin-bottom:8px;">
    ${formatParagraphsHtml(r.fallaReportada, 'Sin falla reportada.')}
  </div>

  <!-- DESCRIPCION DEL SERVICIO -->
  <div class="pdf-item pdf-section-title" style="font-family:Arial,sans-serif;font-weight:bold;font-size:9.5pt;margin-top:10px;margin-bottom:4px;color:#000000;text-transform:uppercase;page-break-after:avoid;break-after:avoid;">
    DESCRIPCIÓN DEL SERVICIO
  </div>
  <div style="margin-bottom:8px;">
    ${formatParagraphsHtml(r.desarrolloActividades, 'Sin descripción de actividades.')}
  </div>

  <!-- CONCLUSION DEL SERVICIO -->
  <div class="pdf-item pdf-section-title" style="font-family:Arial,sans-serif;font-weight:bold;font-size:9.5pt;margin-top:10px;margin-bottom:4px;color:#000000;text-transform:uppercase;page-break-after:avoid;break-after:avoid;">
    CONCLUSIÓN DEL SERVICIO
  </div>
  <div style="margin-bottom:8px;">
    ${formatParagraphsHtml(conclusionText, 'Trabajo en proceso, pendiente de finalización.')}
  </div>

  <!-- TIEMPOS -->
  <div class="pdf-item" style="font-family:Arial,sans-serif;font-size:9pt;margin-top:8px;margin-bottom:12px;color:#000000;page-break-inside:avoid;break-inside:avoid;">
    <strong style="font-weight:bold;">Tiempos:</strong> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    Inicio: ${esc(formatTimeOnly(r.fechaHoraInicio))} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    Fin: ${esc(formatTimeOnly(r.fechaHoraFin))} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    Total: ${esc(totalHours.value)}
  </div>

  <!-- MATERIALES -->
  ${
    matsRows
      ? `<div class="pdf-item pdf-section-title" style="font-family:Arial,sans-serif;font-weight:bold;font-size:9.5pt;margin-top:8px;margin-bottom:4px;color:#000000;text-transform:uppercase;page-break-after:avoid;break-after:avoid;">MATERIALES UTILIZADOS</div>
         <table class="pdf-item" style="width:100%;border-collapse:collapse;font-family:Arial,sans-serif;font-size:8.5pt;margin-bottom:12px;page-break-inside:avoid;break-inside:avoid;">
           <thead>
             <tr style="background:#f1f5f9;">
               <th style="border:1px solid #000000;padding:4px 6px;width:40px;text-align:center;">Item</th>
               <th style="border:1px solid #000000;padding:4px 8px;text-align:left;">Descripción</th>
               <th style="border:1px solid #000000;padding:4px 6px;width:75px;text-align:center;">Cantidad</th>
               <th style="border:1px solid #000000;padding:4px 6px;width:75px;text-align:center;">Unidad</th>
             </tr>
           </thead>
           <tbody>${matsRows}</tbody>
         </table>`
      : ''
  }

  <!-- EVIDENCIA FOTOGRAFICA -->
  <div class="pdf-item pdf-section-title" style="font-family:Arial,sans-serif;font-weight:bold;font-size:9.5pt;margin-top:10px;margin-bottom:6px;color:#000000;text-transform:uppercase;page-break-after:avoid;break-after:avoid;">
    EVIDENCIA FOTOGRÁFICA
  </div>
  ${evidsHtml}

  <!-- FIRMAS -->
  <div class="pdf-item signatures-block" style="margin-top:26px;page-break-inside:avoid;break-inside:avoid;">
    <table style="width:100%;border-collapse:collapse;">
      <tr>
        <td style="width:44%;vertical-align:bottom;">
          <div style="font-family:Arial,sans-serif;font-size:9pt;font-weight:bold;margin-bottom:40px;color:#000000;">Revisó:</div>
          <div style="border-top:1px solid #000000;width:100%;"></div>
          <div style="font-family:Arial,sans-serif;text-align:center;font-size:8pt;font-weight:bold;letter-spacing:0.5px;margin-top:4px;color:#000000;">NOMBRE Y FIRMA</div>
        </td>
        <td style="width:12%;"></td>
        <td style="width:44%;vertical-align:bottom;">
          <div style="font-family:Arial,sans-serif;font-size:9pt;font-weight:bold;margin-bottom:40px;color:#000000;">Elaboró:</div>
          <div style="border-top:1px solid #000000;width:100%;"></div>
          <div style="font-family:Arial,sans-serif;text-align:center;font-size:8pt;font-weight:bold;letter-spacing:0.5px;margin-top:4px;color:#000000;">NOMBRE Y FIRMA</div>
        </td>
      </tr>
    </table>
  </div>
</div>`
  }

  const exportPDF = async () => {
    if (!report.value || isDownloading.value) return
    isDownloading.value = true

    const folioName = `${report.value.folio || 'reporte'}.pdf`
    let tempContainer: HTMLElement | null = null
    const prevScrollX = window.scrollX || window.pageXOffset || 0
    const prevScrollY = window.scrollY || window.pageYOffset || 0

    try {
      window.scrollTo(0, 0)

      // 1. Crear un contenedor temporal limpio de ancho fijo exacto para la exportación (oculto detrás del viewport)
      tempContainer = document.createElement('div')
      tempContainer.id = 'temp-pdf-export-container'
      tempContainer.style.position = 'fixed'
      tempContainer.style.top = '0'
      tempContainer.style.left = '0'
      tempContainer.style.width = '750px'
      tempContainer.style.zIndex = '-9999'
      tempContainer.style.background = '#ffffff'
      tempContainer.style.color = '#000000'
      tempContainer.style.margin = '0'
      tempContainer.style.padding = '0'
      tempContainer.style.pointerEvents = 'none'
      tempContainer.innerHTML = buildPrintableReportHtml()
      document.body.appendChild(tempContainer)
      const targetEl = tempContainer

      // 2. Esperar que todas las imágenes terminen de cargar completamente
      const imgs = Array.from(targetEl.querySelectorAll('img'))
      await Promise.all(
        imgs.map(
          img =>
            new Promise<void>(resolve => {
              if (img.complete && img.naturalHeight !== 0) return resolve()
              img.onload = () => resolve()
              img.onerror = () => resolve()
              setTimeout(resolve, 2500)
            })
        )
      )
      await new Promise(resolve => setTimeout(resolve, 250))

      // 3. Importación dinámica de html2canvas y jsPDF
      const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
        import('html2canvas'),
        import('jspdf'),
      ])

      // 4. Mapeo de bloques atómicos indivisibles (.pdf-item) en coordenadas DOM
      const targetRect = targetEl.getBoundingClientRect()
      const itemEls = Array.from(targetEl.querySelectorAll('.pdf-item'))
      const blocks = itemEls
        .map(el => {
          const rect = el.getBoundingClientRect()
          return {
            top: rect.top - targetRect.top,
            bottom: rect.bottom - targetRect.top,
            height: rect.height,
            isTitle: el.classList.contains('pdf-section-title'),
          }
        })
        .filter(b => b.height > 0)

      blocks.sort((a, b) => a.top - b.top)

      // 5. Parámetros de hoja Carta (Letter: 215.9 x 279.4 mm) y márgenes formales
      const pdfWidthMm = 215.9
      const pdfHeightMm = 279.4
      const marginHorizMm = 12
      const marginTopMm = 12
      const marginBottomMm = 14
      const printableWidthMm = pdfWidthMm - 2 * marginHorizMm // 191.9 mm
      const printableHeightMm = pdfHeightMm - (marginTopMm + marginBottomMm) // 253.4 mm

      const targetWidthDom = targetEl.offsetWidth || 750
      const maxPageDomH = Math.floor(targetWidthDom * (printableHeightMm / printableWidthMm))
      const totalDomHeight = targetEl.scrollHeight || targetEl.offsetHeight

      // 6. Algoritmo de corte inteligente de páginas (Smart Page Breaks)
      const pageBreaks: { top: number; bottom: number }[] = []
      let currentDomY = 0

      while (currentDomY < totalDomHeight - 10) {
        const idealSplitDomY = currentDomY + maxPageDomH

        if (idealSplitDomY >= totalDomHeight) {
          pageBreaks.push({ top: currentDomY, bottom: totalDomHeight })
          break
        }

        let splitDomY = idealSplitDomY

        for (let i = 0; i < blocks.length; i++) {
          const block = blocks[i]

          // Si el bloque cruza el límite de corte de la hoja
          if (block.top < idealSplitDomY && block.bottom > idealSplitDomY) {
            if (block.height <= maxPageDomH && block.top > currentDomY + 60) {
              splitDomY = block.top

              // Si el elemento inmediatamente anterior es un título de sección, no dejarlo huérfano
              if (i > 0 && blocks[i - 1].isTitle && blocks[i - 1].top > currentDomY + 40) {
                splitDomY = blocks[i - 1].top
              }
            }
            break
          }

          // Si el corte cae inmediatamente sobre un título de sección sin espacio para su texto
          if (block.isTitle && block.top < idealSplitDomY && (idealSplitDomY - block.top) < 60) {
            if (block.top > currentDomY + 60) {
              splitDomY = block.top
              break
            }
          }
        }

        if (splitDomY <= currentDomY + 40) {
          splitDomY = idealSplitDomY
        }

        pageBreaks.push({ top: currentDomY, bottom: splitDomY })
        currentDomY = splitDomY
      }

      // 7. Renderizado en Canvas con alta resolución
      const canvas = await html2canvas(targetEl, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false,
        scrollX: 0,
        scrollY: 0,
      })

      const canvasScale = canvas.width / targetWidthDom
      const scaleMmPerPx = printableWidthMm / canvas.width

      const pdf = new jsPDF({
        unit: 'mm',
        format: 'letter',
        orientation: 'portrait',
      })

      const totalPages = pageBreaks.length

      for (let pageIdx = 0; pageIdx < totalPages; pageIdx++) {
        const { top: sliceTopDom, bottom: sliceBottomDom } = pageBreaks[pageIdx]
        const sliceTopCanvas = Math.round(sliceTopDom * canvasScale)
        const sliceBottomCanvas = Math.min(canvas.height, Math.round(sliceBottomDom * canvasScale))
        const sliceHCanvas = sliceBottomCanvas - sliceTopCanvas

        if (sliceHCanvas <= 0) continue

        const sliceCanvas = document.createElement('canvas')
        sliceCanvas.width = canvas.width
        sliceCanvas.height = sliceHCanvas

        const ctx = sliceCanvas.getContext('2d')
        if (ctx) {
          ctx.fillStyle = '#ffffff'
          ctx.fillRect(0, 0, sliceCanvas.width, sliceCanvas.height)
          ctx.drawImage(
            canvas,
            0,
            sliceTopCanvas,
            canvas.width,
            sliceHCanvas,
            0,
            0,
            canvas.width,
            sliceHCanvas
          )
        }

        if (pageIdx > 0) {
          pdf.addPage('letter', 'portrait')
        }

        const sliceImg = sliceCanvas.toDataURL('image/jpeg', 0.98)
        const sliceHeightMm = sliceHCanvas * scaleMmPerPx

        // Insertar imagen de contenido respetando margen formal
        pdf.addImage(
          sliceImg,
          'JPEG',
          marginHorizMm,
          marginTopMm,
          printableWidthMm,
          sliceHeightMm
        )

        // Pie de página institucional formal en cada página
        pdf.setFont('helvetica', 'normal')
        pdf.setFontSize(7.5)
        pdf.setTextColor(100, 116, 139)
        const footerLeft = `Generado con CoreReports para ${companyName.value} — Folio: ${report.value.folio}`
        const footerRight = `Página ${pageIdx + 1} de ${totalPages}`
        pdf.text(footerLeft, marginHorizMm, pdfHeightMm - 7)
        pdf.text(footerRight, pdfWidthMm - marginHorizMm - pdf.getTextWidth(footerRight), pdfHeightMm - 7)
      }

      pdf.save(folioName)
      toast.success('PDF descargado exactamente como la vista previa')
    } catch (clientErr) {
      console.error('Error generando PDF en navegador:', clientErr)

      // Fallback de emergencia al servidor solo si la captura en cliente falla
      try {
        const reportId = report.value.id || (report.value as any).idReporte || Number(route.params.id)
        const res = await client.get(`/reportes/${reportId}/pdf`, { responseType: 'blob' })
        if (res.data && res.data.size > 200) {
          const blob = new Blob([res.data], { type: 'application/pdf' })
          downloadBlob(blob, folioName)
          toast.success('PDF descargado correctamente')
          return
        }
      } catch (backupErr) {
        console.error('Fallback de servidor también falló:', backupErr)
      }

      toast.error('No se pudo generar el archivo automáticamente. Por favor abre "Vista Previa" y usa el botón "Imprimir".')
    } finally {
      if (tempContainer && tempContainer.parentNode) {
        tempContainer.parentNode.removeChild(tempContainer)
      }
      window.scrollTo(prevScrollX, prevScrollY)
      isDownloading.value = false
    }
  }

  const exportExcel = async () => {
    if (!report.value || isExporting.value) return
    isExporting.value = true
    try {
      const r = report.value
      const orig = getOrigenDetails()
      const escHtml = (v: any) =>
        String(v ?? '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')

      const matsHtml =
        r.materiales && r.materiales.length > 0
          ? r.materiales
              .map(
                (m: any, idx: number) =>
                  `<tr><td>${idx + 1}</td><td>${escHtml(m.material)}</td><td>${escHtml(m.cantidad)}</td><td>${escHtml(m.unidad)}</td></tr>`
              )
              .join('')
          : `<tr><td colspan="4">Sin materiales registrados</td></tr>`

      const html = `\uFEFF<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="UTF-8"></head>
<body>
  <table border="1" style="border-collapse:collapse;">
    <tr><th colspan="4" style="background:#1e3a8a;color:#ffffff;font-size:14pt;padding:8px;">REPORTE DE TRABAJO ${escHtml(r.folio)} - ${escHtml(companyName.value)}</th></tr>
    <tr><td><b>Folio</b></td><td>${escHtml(r.folio)}</td><td><b>Estatus</b></td><td>${escHtml(r.estatusReporte)}</td></tr>
    <tr><td><b>Tipo</b></td><td>${escHtml(r.tipoReporte)}</td><td><b>Origen</b></td><td>${escHtml(orig.folio)} - ${escHtml(orig.asunto)}</td></tr>
    <tr><td><b>Categoría</b></td><td>${escHtml(r.categoria)}</td><td><b>Área</b></td><td>${escHtml(areaName.value)}</td></tr>
    <tr><td><b>Elaborado por</b></td><td>${escHtml(getUserName(r.creadoPor))}</td><td><b>Ejecutores</b></td><td>${escHtml(getEjecutoresNames(r.ejecutoresIds))}</td></tr>
    <tr><td><b>Responsable</b></td><td>${escHtml(displayResponsableNombre.value)}</td><td><b>Cargo</b></td><td>${escHtml(displayResponsableCargo.value)}</td></tr>
    <tr><td><b>Salida Base</b></td><td>${escHtml(r.horaSalidaBase ? formatDate(r.horaSalidaBase) : '---')}</td><td><b>Llegada a Sitio</b></td><td>${escHtml(r.horaLlegadaSitio ? formatDate(r.horaLlegadaSitio) : '---')}</td></tr>
    <tr><td><b>Inicio Trabajo</b></td><td>${escHtml(formatDate(r.fechaHoraInicio))}</td><td><b>Fin Trabajo</b></td><td>${escHtml(r.fechaHoraFin ? formatDate(r.fechaHoraFin) : '---')}</td></tr>
    <tr><td><b>Regreso a Base</b></td><td>${escHtml(r.horaRegresoABase ? formatDate(r.horaRegresoABase) : '---')}</td><td><b>Horas Trabajo Neto</b></td><td><b>${escHtml(totalHours.value)}</b></td></tr>
    <tr><td colspan="4"></td></tr>
    <tr><th colspan="4" style="background:#e2e8f0;text-align:left;">Falla Reportada</th></tr>
    <tr><td colspan="4">${escHtml(r.fallaReportada || 'N/A')}</td></tr>
    <tr><th colspan="4" style="background:#e2e8f0;text-align:left;">Desarrollo de Actividades</th></tr>
    <tr><td colspan="4">${escHtml(r.desarrolloActividades || 'Sin descripción')}</td></tr>
    <tr><th colspan="4" style="background:#e2e8f0;text-align:left;">Conclusión del Trabajo</th></tr>
    <tr><td colspan="4">${escHtml(r.conclusionTrabajo || 'Sin conclusión adicional')}</td></tr>
    <tr><td colspan="4"></td></tr>
    <tr><th colspan="4" style="background:#e2e8f0;text-align:left;">Materiales Utilizados</th></tr>
    <tr><th>#</th><th>Material</th><th>Cantidad</th><th>Unidad</th></tr>
    ${matsHtml}
  </table>
</body>
</html>`
      const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' })
      downloadBlob(blob, `reporte-${r.folio}.xls`)
      toast.success('Excel descargado correctamente')
    } catch {
      toast.error('No se pudo generar el Excel')
    } finally {
      isExporting.value = false
    }
  }

  // === Modal de Envío de Reporte por Correo ===
  // Control para mostrar u ocultar la funcionalidad de envío por correo
  const ENABLE_EMAIL_FEATURE = false
  const showEmailModal = ref(false)
  const isSendingEmail = ref(false)
  const emailRecipients = ref<string[]>([])
  const newEmailInput = ref('')
  const emailComment = ref('')

  const openEmailModal = () => {
    emailRecipients.value = []
    const cMail =
      empresa.value?.correoReportes ||
      empresa.value?.correo_reportes ||
      empresa.value?.correoContacto ||
      empresa.value?.correo_contacto ||
      (currentUser.value as any)?.dependencia?.correo_reportes ||
      ''
    if (cMail && !emailRecipients.value.includes(cMail)) {
      emailRecipients.value.push(cMail)
    }
    emailComment.value = ''
    newEmailInput.value = ''
    showEmailModal.value = true
  }

  const addRecipientEmail = () => {
    const raw = newEmailInput.value.trim()
    if (!raw) return
    const emails = raw.split(/[\s,;]+/).filter(Boolean)
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    let count = 0
    for (const e of emails) {
      if (!emailRegex.test(e)) {
        toast.warning(`"${e}" no parece ser un correo válido`)
        continue
      }
      if (!emailRecipients.value.includes(e)) {
        emailRecipients.value.push(e)
        count++
      }
    }
    if (count > 0) {
      newEmailInput.value = ''
    }
  }

  const removeRecipientEmail = (idx: number) => {
    emailRecipients.value.splice(idx, 1)
  }

  const sendReportByEmail = async () => {
    if (!report.value) return
    const reportId = report.value.idReporte || report.value.id || route.params.id
    if (!reportId) {
      toast.error('No se pudo identificar el reporte para enviar')
      return
    }
    if (emailRecipients.value.length === 0) {
      toast.warning('Agrega al menos un destinatario de correo')
      return
    }
    isSendingEmail.value = true
    try {
      const res = await client.post(`/reportes/${reportId}/enviar-correo`, {
        destinatarios: emailRecipients.value,
        comentario: emailComment.value || null,
      })
      showEmailModal.value = false
      emailRecipients.value = []
      emailComment.value = ''
      newEmailInput.value = ''
      toast.success(res.data?.message || 'Reporte enviado por correo exitosamente')
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'No se pudo enviar el correo del reporte')
    } finally {
      isSendingEmail.value = false
    }
  }

  // === Print helpers ===
  // Datos de la empresa (logotipo y nombre) para el encabezado del PDF/impresión.
  const empresa = ref<any>(null)
  const loadEmpresa = async () => {
    try {
      const res = await client.get('/empresa').then(r => r.data)
      empresa.value = res.data || res
    } catch {
      empresa.value = null
    }
  }

  const companyName = computed(() => {
    return empresa.value?.nombre || (currentUser.value as any)?.dependencia?.nombre || 'Empresa'
  })

  const companyLogo = computed(() => empresa.value?.logo || '')

  const getResponsableUser = computed(() => {
    if (!report.value || !report.value.responsableId) return null
    return users.value.find((u: any) => u.id === report.value!.responsableId) || null
  })

  const displayResponsableNombre = computed(() => {
    if (!report.value) return 'Sin Asignar'
    if (report.value.responsableTexto) return report.value.responsableTexto
    return getUserName(report.value.responsableId ?? null)
  })

  const displayResponsableCargo = computed(() => {
    if (!report.value) return '---'
    if (report.value.cargoTexto) return report.value.cargoTexto
    if (getResponsableUser.value) return getRoleText(getResponsableUser.value.rol)
    return '---'
  })

  const getRoleText = (rol: number) => {
    switch (rol) {
      case 0:
        return 'Usuario Básico'
      case 1:
        return 'Administrador Técnico'
      case 2:
        return 'Administrador Comercial'
      case 3:
        return 'Técnico'
      default:
        return 'Desconocido'
    }
  }

  const tecnicoUser = computed(() => {
    if (!report.value || !report.value.creadoPor) return null
    return users.value.find((u: any) => u.id === report.value!.creadoPor) || null
  })

  const tecnicoNombre = computed(() => {
    if (tecnicoUser.value)
      return `${tecnicoUser.value.nombre} ${tecnicoUser.value.apellidos}`.trim()
    return getUserName(report.value?.creadoPor ?? null) || 'Técnico Especialista'
  })

  const tecnicoPuesto = computed(() => {
    if (tecnicoUser.value?.puesto) return tecnicoUser.value.puesto
    if (tecnicoUser.value) return getRoleText(tecnicoUser.value.rol)
    return displayResponsableCargo.value || 'Especialista Técnico'
  })

  const tecnicoNumero = computed(() => {
    return (
      tecnicoUser.value?.numero_empleado ||
      tecnicoUser.value?.num_empleado ||
      String(tecnicoUser.value?.id || report.value?.creadoPor || '001').padStart(3, '0')
    )
  })

  const tecnicoEmail = computed(() => {
    return tecnicoUser.value?.email || empresa.value?.email || '—'
  })

  const formatDateShort = (dateString: string) => {
    return formatLocalDateShort(dateString) || '---'
  }

  const formatTimeOnly = (dateString: string) => {
    const d = parseLocalDate(dateString)
    if (!d) return '--:--'
    return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
  }

  const totalHours = computed(() => {
    if (!report.value) return '0h 0m'
    return calcNetHoursText(report.value.fechaHoraInicio, report.value.fechaHoraFin)
  })

  const areaName = computed(() => {
    if (!report.value) return '---'
    if (report.value.idSubdependencia === undefined || report.value.idSubdependencia === null) {
      return (report.value as any).areaTexto || 'Área General'
    }
    const a = areas.value.find((x: any) => x.id === report.value!.idSubdependencia)
    return a ? a.nombre : ((report.value as any).areaTexto || `Área #${report.value.idSubdependencia}`)
  })
</script>

<template>
  <div class="report-view-root">
    <div class="page-container-detail-report" ref="reportContent">
      <div v-if="report">
        <!-- Header -->
        <header class="report-header">
          <button @click="goBack" class="btn-back" data-html2canvas-ignore="true">
            <ArrowLeft :size="18" />
            <span>Regresar a la lista</span>
          </button>

          <div class="header-title-container">
            <div class="header-title">
              <span class="folio-badge">{{ report.folio }}</span>
              <h1>Reporte de Trabajo</h1>
            </div>
            <div class="header-actions" data-html2canvas-ignore="true">
              <button class="btn-secondary" @click="openPdfPreview" title="Ver formato de reporte antes de descargar">
                <Eye :size="16" />
                <span>Vista Previa</span>
              </button>
              <button class="btn-primary" @click="exportPDF" :disabled="isDownloading">
                <span v-if="isDownloading">⏳ Generando...</span>
                <span v-else>⬇️ Descargar PDF</span>
              </button>
              <button v-if="ENABLE_EMAIL_FEATURE" class="btn-secondary" @click="openEmailModal" title="Enviar reporte en PDF por correo electrónico">
                <Mail :size="16" />
                <span>Enviar por Correo</span>
              </button>
              <button v-if="canEditReport" class="btn-secondary" @click="openEditModal">
                ✏️ Editar Reporte
              </button>
              <button v-if="currentUser?.rol !== 3" class="btn-survey" @click="openSurvey">
                ⭐ Encuesta al Cliente
              </button>
            </div>
          </div>
        </header>

        <!-- Upper banner -->
        <div class="card quick-meta-bar">
          <div class="meta-item">
            <span class="meta-label">Estatus</span>
            <span
              class="status-pill"
              :class="
                report.estatusReporte === 'Cerrado / Trabajo Finalizado'
                  ? 'pill-success'
                  : 'pill-warning'
              "
            >
              {{
                report.estatusReporte === 'Cerrado / Trabajo Finalizado'
                  ? 'FINALIZADO'
                  : 'TRABAJO PARCIAL'
              }}
            </span>
          </div>

          <div class="divider-vertical"></div>

          <div class="meta-item">
            <span class="meta-label">Elaborado por</span>
            <div class="person-info">
              <User :size="18" class="icon" />
              <div class="name">{{ getUserName(report.creadoPor) }}</div>
            </div>
          </div>

          <div class="divider-vertical"></div>

          <div class="meta-item">
            <span class="meta-label">Responsable</span>
            <div class="person-info">
              <User :size="18" class="icon" />
              <div class="name">{{ displayResponsableNombre }}</div>
            </div>
          </div>

          <div class="divider-vertical"></div>

          <div class="meta-item">
            <span class="meta-label">Dependencia / Área</span>
            <div class="person-info">
              <Building2 :size="18" class="icon" />
              <div class="name">{{ areaName }}</div>
            </div>
          </div>
        </div>

        <!-- Report grid -->
        <div class="report-grid">
          <!-- Main col -->
          <div class="main-column">
            <div class="card description-card" v-if="report.fallaReportada">
              <h2>Falla Reportada</h2>
              <div class="description-text-scroll">
                <p class="description-text">{{ report.fallaReportada }}</p>
              </div>
              <button
                v-if="report.fallaReportada.length > 200"
                class="view-full-preview-btn"
                @click="openPdfPreview"
                type="button"
              >
                <Eye :size="13" /> Ver completo en Vista Previa
              </button>
            </div>

            <div class="card description-card">
              <h2>Desarrollo de Actividades</h2>
              <div class="description-text-scroll">
                <p class="description-text">
                  {{ report.desarrolloActividades || 'Sin descripción.' }}
                </p>
              </div>
              <button
                v-if="(report.desarrolloActividades || '').length > 200"
                class="view-full-preview-btn"
                @click="openPdfPreview"
                type="button"
              >
                <Eye :size="13" /> Ver completo en Vista Previa
              </button>
            </div>

            <div class="card description-card">
              <h2>Conclusión del Trabajo</h2>
              <div class="description-text-scroll">
                <p class="description-text" v-if="report.conclusionTrabajo">
                  {{ report.conclusionTrabajo }}
                </p>
                <p class="description-text" v-else>
                  {{
                    report.estatusReporte === 'Cerrado / Trabajo Finalizado'
                      ? 'Trabajo finalizado satisfactoriamente.'
                      : 'Trabajo en proceso, pendiente de finalización.'
                  }}
                </p>
              </div>
              <button
                v-if="(report.conclusionTrabajo || '').length > 200"
                class="view-full-preview-btn"
                @click="openPdfPreview"
                type="button"
              >
                <Eye :size="13" /> Ver completo en Vista Previa
              </button>
            </div>

            <div
              class="card materials-card"
              v-if="report.materiales && report.materiales.length > 0"
            >
              <h2>Materiales Utilizados</h2>
              <div class="description-text-scroll" style="max-height: 190px;">
                <ul class="materials-list">
                  <li v-for="mat in report.materiales" :key="mat.idItem">
                    <span class="mat-name"
                      ><Wrench :size="14" class="inline-icon" /> {{ mat.material }}</span
                    >
                    <span class="mat-qty">{{ mat.cantidad }} {{ mat.unidad }}</span>
                  </li>
                </ul>
              </div>
            </div>

            <div class="card description-card">
              <h2>Evidencias</h2>
              <div
                class="evidence-grid evidence-grid-scroll"
                v-if="report.evidenciaFotografica && report.evidenciaFotografica.length > 0"
              >
                <div
                  class="evidence-item"
                  v-for="(item, idx) in report.evidenciaFotografica"
                  :key="idx"
                >
                  <img
                    v-if="isImageEvidence(item)"
                    :src="item.url"
                    class="evidence-img cursor-pointer"
                    alt="Evidencia"
                    @click="previewUrl = item.url"
                    title="Haz clic para ampliar"
                  />
                  <a
                    v-else
                    :href="item.url"
                    target="_blank"
                    rel="noopener"
                    class="evidence-doc-link"
                    :title="item.nombre"
                  >
                    <FileText :size="18" />
                    <span>{{ item.nombre || 'Documento adjunto' }}</span>
                  </a>
                  <p class="evidence-comment-text text-sm text-gray-600 mt-2">
                    {{ item.comentario || 'Sin comentario' }}
                  </p>
                </div>
              </div>
              <button
                v-if="report.evidenciaFotografica && report.evidenciaFotografica.length > 4"
                class="view-full-preview-btn mt-2"
                @click="openPdfPreview"
                type="button"
              >
                <Eye :size="13" /> Ver todas las fotos en Vista Previa
              </button>
              <p class="description-text" v-else-if="!report.evidenciaFotografica || report.evidenciaFotografica.length === 0" style="color: #94a3b8; font-style: italic">
                No se han agregado fotos.
              </p>
            </div>

            <!-- History Timeline -->
            <div
              class="timeline-section"
              v-if="report.historialModificaciones && report.historialModificaciones.length > 0"
              data-html2canvas-ignore="true"
            >
              <h2 class="timeline-heading">Historial de Modificaciones</h2>
              <div class="timeline">
                <div
                  class="timeline-item"
                  v-for="mod in report.historialModificaciones"
                  :key="mod.idModificacion"
                >
                  <div class="timeline-dot"></div>
                  <div class="timeline-content card">
                    <div class="flex justify-between items-start">
                      <strong class="timeline-user">{{ getUserName(mod.idUsuario) }}</strong>
                      <span class="timeline-date">{{ formatDate(mod.fechaModificacion) }}</span>
                    </div>
                    <p class="timeline-desc">{{ mod.descripcion }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Side col -->
          <div class="side-column">
            <div class="card meta-card">
              <div class="meta-section">
                <h3>Origen</h3>
                <div class="origin-info">
                  <FileText :size="16" class="icon" />
                  <div>
                    <strong>{{ getOrigenDetails().folio }}</strong>
                    <div class="text-sm text-slate-500 mt-1" v-if="getOrigenDetails().asunto">
                      {{ getOrigenDetails().asunto }}
                    </div>
                  </div>
                </div>
              </div>

              <div class="divider"></div>

              <div class="meta-section">
                <h3>Ejecutores</h3>
                <div class="person-info mt-2">
                  <CheckCircle2 :size="16" class="icon" />
                  <div class="name group-names">{{ getEjecutoresNames(report.ejecutoresIds) }}</div>
                </div>
              </div>

              <div class="divider"></div>

              <div class="meta-section">
                <h3>Tiempos</h3>
                <div class="date-info">
                  <Calendar :size="16" class="icon" />
                  <span
                    ><strong>Elaboración:</strong> <br />{{
                      formatDate(report.fechaElaboracion)
                    }}</span
                  >
                </div>
                <div class="date-info mt-2" v-if="report.horaSalidaBase">
                  <Clock :size="16" class="icon" />
                  <span
                    ><strong>Salida Base:</strong> <br />{{
                      formatDate(report.horaSalidaBase)
                    }}</span
                  >
                </div>
                <div class="date-info mt-2" v-if="report.horaLlegadaSitio">
                  <Clock :size="16" class="icon" />
                  <span
                    ><strong>Llegada a Sitio:</strong> <br />{{
                      formatDate(report.horaLlegadaSitio)
                    }}</span
                  >
                </div>
                <div class="date-info mt-2">
                  <Clock :size="16" class="icon" />
                  <span
                    ><strong>Inicio Trabajo:</strong> <br />{{
                      formatDate(report.fechaHoraInicio)
                    }}</span
                  >
                </div>
                <div class="date-info mt-2" v-if="report.fechaHoraFin">
                  <Clock :size="16" class="icon" />
                  <span
                    ><strong>Fin Trabajo:</strong> <br />{{ formatDate(report.fechaHoraFin) }}</span
                  >
                </div>
                <div class="date-info mt-2" v-if="report.horaRegresoABase">
                  <Clock :size="16" class="icon" />
                  <span
                    ><strong>Regreso a Base:</strong> <br />{{
                      formatDate(report.horaRegresoABase)
                    }}</span
                  >
                </div>
                <div class="date-info mt-2">
                  <Clock :size="16" class="icon" />
                  <span
                    ><strong>Horas de trabajo neto:</strong> <br />{{ totalHours }}</span
                  >
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="error-state">
        <h2>Reporte no encontrado u Oculto</h2>
        <p>El reporte con este ID no existe o no pertenece a tu dependencia.</p>
        <button @click="goBack" class="btn-back mt-4">Regresar a la lista</button>
      </div>

      <!-- Full Edit Modal Overlay (#13) -->
      <div v-if="showEditModal" class="modal-overlay">
        <div class="modal-content modal-content-wide">
          <header class="modal-header">
            <h2>✏️ Editar Reporte Técnico ({{ report?.folio }})</h2>
            <button class="btn-close" @click="showEditModal = false">&times;</button>
          </header>
          <div class="modal-body modal-body-scroll">
            <!-- Sección 1: Categoría y Retrabajo -->
            <div class="edit-section">
              <h3 class="edit-section-title">Sección 1 — Tipo y Categoría</h3>
              <div class="grid-3-cols">
                <div class="form-group">
                  <label>Tipo de reporte</label>
                  <input
                    type="text"
                    :value="report?.tipoReporte"
                    disabled
                    class="form-control bg-disabled"
                  />
                </div>
                <div class="form-group">
                  <label>Categoría del trabajo *</label>
                  <select v-model="editForm.categoria" class="form-control">
                    <option value="Mantenimiento Preventivo">Mantenimiento Preventivo</option>
                    <option value="Mantenimiento Correctivo">Mantenimiento Correctivo</option>
                    <option value="Instalación">Instalación</option>
                    <option value="Mejora">Mejora</option>
                    <option value="Diagnóstico">Diagnóstico</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>¿Es retrabajo?</label>
                  <select v-model="editForm.esRetrabajo" class="form-control">
                    <option :value="false">No</option>
                    <option :value="true">Sí</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Sección 2: Ubicación y Responsable -->
            <div class="edit-section">
              <h3 class="edit-section-title">Sección 2 — Ubicación y Responsable</h3>
              <div class="grid-3-cols">
                <div class="form-group">
                  <label>Área / Subdependencia</label>
                  <select v-model="editForm.idSubdependencia" class="form-control">
                    <option :value="null">Sin área específica</option>
                    <option v-for="a in areas" :key="a.id" :value="a.id">
                      {{ a.nombre }}
                    </option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Responsable *</label>
                  <input
                    type="text"
                    v-model="editForm.responsableTexto"
                    placeholder="Nombre del responsable..."
                    class="form-control"
                  />
                </div>
                <div class="form-group">
                  <label>Cargo del responsable</label>
                  <input
                    type="text"
                    v-model="editForm.cargoTexto"
                    placeholder="Ej. Jefe de Mantenimiento"
                    class="form-control"
                  />
                </div>
              </div>
            </div>

            <!-- Sección 3: Personal Ejecutor -->
            <div class="edit-section">
              <div class="edit-section-header-row">
                <h3 class="edit-section-title">Sección 3 — Personal Ejecutor (máx 4)</h3>
                <button
                  type="button"
                  class="btn-add-small"
                  @click="addEditEjecutor"
                  v-if="editForm.ejecutoresIds.length < 4"
                >
                  <Plus :size="14" /> Agregar ejecutor
                </button>
              </div>
              <div v-if="editForm.ejecutoresIds.length === 0" class="empty-hint">
                No hay ejecutores adicionales seleccionados.
              </div>
              <div
                v-for="(_, index) in editForm.ejecutoresIds"
                :key="index"
                class="dynamic-row"
              >
                <select v-model="editForm.ejecutoresIds[index]" class="form-control">
                  <option :value="0" disabled>Selecciona un técnico...</option>
                  <option
                    v-for="tech in availableTechniciansForEdit"
                    :key="tech.id"
                    :value="tech.id"
                  >
                    {{ tech.nombre }} {{ tech.apellidos }} ({{ tech.numero_empleado || 'S/N' }})
                  </option>
                </select>
                <button
                  type="button"
                  class="btn-remove-small"
                  @click="removeEditEjecutor(index)"
                  title="Quitar ejecutor"
                >
                  <Trash2 :size="15" />
                </button>
              </div>
            </div>

            <!-- Sección 4: Generales y Tiempos -->
            <div class="edit-section">
              <h3 class="edit-section-title">Sección 4 — Generales y Tiempos</h3>
              <div class="grid-3-cols">
                <div class="form-group">
                  <label>Hora de salida base</label>
                  <input
                    type="datetime-local"
                    v-model="editForm.horaSalidaBase"
                    class="form-control"
                  />
                </div>
                <div class="form-group">
                  <label>Hora de llegada a sitio</label>
                  <input
                    type="datetime-local"
                    v-model="editForm.horaLlegadaSitio"
                    class="form-control"
                  />
                </div>
                <div class="form-group">
                  <label>Hora de inicio trabajo *</label>
                  <input
                    type="datetime-local"
                    v-model="editForm.fechaHoraInicio"
                    class="form-control"
                  />
                </div>
                <div class="form-group">
                  <label>Hora de fin trabajo *</label>
                  <input
                    type="datetime-local"
                    v-model="editForm.fechaHoraFin"
                    class="form-control"
                  />
                </div>
                <div class="form-group">
                  <label>Hora de regreso a base *</label>
                  <input
                    type="datetime-local"
                    v-model="editForm.horaRegresoABase"
                    class="form-control"
                  />
                </div>
                <div class="form-group">
                  <label>Horas de trabajo neto</label>
                  <div class="net-hours-badge">{{ editTotalHours }}</div>
                </div>
              </div>

              <div class="form-group mt-3" v-if="report?.tipoReporte === 'Libre'">
                <label>Descripción de la falla reportada</label>
                <textarea
                  v-model="editForm.fallaReportada"
                  rows="2"
                  class="form-control"
                  placeholder="Describe la falla reportada..."
                ></textarea>
              </div>

              <div class="form-group mt-3">
                <label>Desarrollo de las actividades *</label>
                <textarea
                  v-model="editForm.desarrolloActividades"
                  rows="4"
                  class="form-control"
                  placeholder="Describe detalladamente las actividades realizadas..."
                ></textarea>
              </div>
            </div>

            <!-- Sección 5: Materiales, Evidencias y Conclusión -->
            <div class="edit-section">
              <div class="edit-section-header-row">
                <h3 class="edit-section-title">Sección 5 — Materiales Utilizados</h3>
                <button type="button" class="btn-add-small" @click="addEditMaterialRow">
                  <Plus :size="14" /> Agregar material
                </button>
              </div>
              <div v-if="editForm.materiales.length === 0" class="empty-hint">
                No se han agregado materiales. Haz clic en "Agregar material" si utilizaste refacciones.
              </div>
              <div
                v-for="(item, index) in editForm.materiales"
                :key="index"
                class="material-edit-row"
              >
                <span class="mat-idx">#{{ index + 1 }}</span>
                <input
                  type="text"
                  v-model="item.material"
                  placeholder="Descripción del material..."
                  class="form-control"
                />
                <input
                  type="number"
                  v-model.number="item.cantidad"
                  min="1"
                  class="form-control mat-qty-input"
                />
                <select v-model="item.unidad" class="form-control mat-unit-select">
                  <option value="Pieza">Pieza</option>
                  <option value="Metro">Metro</option>
                  <option value="Litro">Litro</option>
                  <option value="Kilo">Kilo</option>
                  <option value="Caja">Caja</option>
                  <option value="Rollo">Rollo</option>
                  <option value="Par">Par</option>
                  <option value="Juego">Juego</option>
                </select>
                <button
                  type="button"
                  class="btn-remove-small"
                  @click="removeEditMaterialRow(index)"
                  title="Quitar material"
                >
                  <Trash2 :size="15" />
                </button>
              </div>

              <!-- Evidencias Fotográficas -->
              <div class="edit-section-header-row mt-4">
                <h3 class="edit-section-title">
                  Evidencias Fotográficas ({{ editForm.evidenciaFotografica.length }}/20)
                </h3>
                <button type="button" class="btn-add-small" @click="triggerEditFileInput">
                  <Upload :size="14" /> Subir fotos / archivos
                </button>
                <input
                  ref="editFileInput"
                  type="file"
                  multiple
                  accept="image/*,.pdf,.csv,.xls,.xlsx"
                  style="display: none"
                  @change="onEditFileChange"
                />
              </div>

              <div v-if="editForm.evidenciaFotografica.length === 0" class="empty-hint">
                No hay evidencias adjuntas. Puedes subir fotografías o documentos nuevos.
              </div>
              <div v-else class="edit-evidence-grid">
                <div
                  v-for="(ev, idx) in editForm.evidenciaFotografica"
                  :key="idx"
                  class="edit-evidence-card"
                >
                  <div class="edit-evidence-preview">
                    <img
                      v-if="isImageEvidence(ev)"
                      :src="ev.url"
                      alt="Evidencia"
                      class="edit-evidence-img"
                    />
                    <div v-else class="edit-evidence-doc">
                      <FileText :size="20" />
                      <span>{{ ev.nombre || 'Documento' }}</span>
                    </div>
                    <button
                      type="button"
                      class="btn-delete-ev"
                      @click="removeEditEvidence(idx)"
                      title="Eliminar evidencia"
                    >
                      &times;
                    </button>
                  </div>
                  <input
                    type="text"
                    v-model="ev.comentario"
                    placeholder="Descripción de la foto..."
                    class="form-control mt-2"
                  />
                </div>
              </div>

              <!-- Estatus y Conclusión -->
              <div class="grid-2-cols mt-4">
                <div class="form-group">
                  <label>Estatus del Reporte</label>
                  <select v-model="editForm.estatusReporte" class="form-control">
                    <option value="Abierto / Trabajo Parcial">
                      Abierto (no se finaliza)
                    </option>
                    <option value="Cerrado / Trabajo Finalizado">
                      Cerrado (finalizado)
                    </option>
                    <option value="Descartado / Cancelado">Descartado / Cancelado</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Conclusión del trabajo</label>
                  <textarea
                    v-model="editForm.conclusionTrabajo"
                    rows="2"
                    class="form-control"
                    placeholder="Comentarios sobre la conclusión del trabajo..."
                  ></textarea>
                </div>
              </div>
            </div>
          </div>
          <footer class="modal-footer">
            <button
              class="btn-cancel"
              @click="showEditModal = false"
              :disabled="isSavingEdit"
            >
              Cancelar
            </button>
            <button class="btn-save" @click="saveReportEdit" :disabled="isSavingEdit">
              {{ isSavingEdit ? 'Guardando...' : 'Guardar Cambios' }}
            </button>
          </footer>
        </div>
      </div>

      <!-- Modal Vista Previa del PDF -->
      <div v-if="showPdfPreviewModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 860px; width: 95%; max-height: 94vh; display: flex; flex-direction: column; padding: 0; overflow: hidden; background: #ffffff; border-radius: 8px; box-shadow: 0 20px 40px rgba(0,0,0,0.35);">
          <header class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-bottom: 1px solid #e2e8f0; background: #ffffff; flex-shrink: 0;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <Eye :size="18" style="color: #2563eb;" />
              <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0; color: #0f172a;">Vista Previa del Reporte ({{ report?.folio }})</h2>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
              <button class="btn-secondary" style="padding: 6px 12px; font-size: 0.85rem;" @click="printDirectly" title="Imprimir o guardar directamente como PDF">
                🖨️ Imprimir
              </button>
              <button class="btn-primary" style="padding: 6px 14px; font-size: 0.85rem;" @click="exportPDF" :disabled="isDownloading">
                {{ isDownloading ? '⏳ Generando...' : '⬇️ Descargar PDF' }}
              </button>
              <button class="btn-close" @click="showPdfPreviewModal = false" style="background: none; border: none; font-size: 26px; cursor: pointer; color: #64748b; line-height: 1;">
                &times;
              </button>
            </div>
          </header>

          <div
            ref="previewScrollContainer"
            class="pdf-preview-viewport"
            style="padding: 24px 16px; overflow-y: auto; background: #525659; flex: 1; display: block; -webkit-overflow-scrolling: touch;"
          >
            <div
              id="pdf-preview-sheet"
              style="background: #ffffff; box-shadow: 0 4px 25px rgba(0,0,0,0.4); border-radius: 2px; width: 750px; max-width: 100%; margin: 0 auto; display: block;"
              v-html="previewHtml"
            ></div>
          </div>

          <footer class="modal-footer" style="padding: 12px 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; background: #ffffff; flex-shrink: 0;">
            <button class="btn-cancel" @click="showPdfPreviewModal = false">
              Cerrar
            </button>
            <button class="btn-secondary" @click="printDirectly">
              🖨️ Imprimir
            </button>
            <button class="btn-save" @click="exportPDF" :disabled="isDownloading">
              {{ isDownloading ? '⏳ Generando...' : '⬇️ Descargar PDF' }}
            </button>
          </footer>
        </div>
      </div>

      <!-- Image Preview Modal (On-Screen View Only) -->
      <div class="modal-overlay" v-if="previewUrl">
        <div class="modal-content-img" @click.stop>
          <button class="btn-close-modal" @click="previewUrl = null">&times;</button>
          <img :src="previewUrl" class="img-preview-full" alt="Vista previa detallada" />
        </div>
      </div>

      <!-- Modal Enviar Reporte por Correo (Oculto temporalmente) -->
      <div v-if="ENABLE_EMAIL_FEATURE && showEmailModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 520px; width: 92%; background: white; border-radius: 8px; padding: 20px;">
          <div class="modal-header flex items-center justify-between pb-3 border-b border-slate-200">
            <div class="flex items-center gap-2">
              <Mail class="text-blue-600" :size="20" />
              <h3 class="m-0 text-base font-bold text-slate-800">Enviar Reporte por Correo ({{ report.folio }})</h3>
            </div>
            <button
              type="button"
              class="text-slate-400 hover:text-slate-600 text-2xl leading-none cursor-pointer"
              @click="showEmailModal = false"
            >&times;</button>
          </div>

          <div class="modal-body py-4">
            <p class="text-xs text-slate-600 mb-3">
              El sistema generará el archivo oficial en PDF y lo enviará adjunto a las siguientes direcciones de correo:
            </p>

            <!-- Lista de correos agregados -->
            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Destinatarios:</label>
            <div class="flex flex-wrap gap-1.5 mb-3 p-2 bg-slate-50 border border-slate-200 rounded min-h-[42px] items-center">
              <span
                v-for="(email, idx) in emailRecipients"
                :key="idx"
                class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-1 rounded-full text-xs font-semibold"
              >
                <Mail :size="12" class="text-blue-500" />
                {{ email }}
                <button
                  type="button"
                  @click="removeRecipientEmail(idx)"
                  class="text-blue-400 hover:text-red-600 rounded-full p-0.5 transition-colors cursor-pointer"
                  title="Eliminar"
                >
                  <X :size="12" />
                </button>
              </span>
              <span v-if="emailRecipients.length === 0" class="text-xs text-slate-400 italic">
                Sin destinatarios. Agrega al menos uno abajo.
              </span>
            </div>

            <!-- Input para añadir correos -->
            <div class="flex gap-2 mb-4">
              <input
                type="email"
                v-model="newEmailInput"
                @keydown.enter.prevent="addRecipientEmail"
                placeholder="correo@ejemplo.com"
                class="flex-1 text-xs rounded border border-slate-300 px-3 py-2 focus:outline-none focus:border-blue-500 bg-white"
              />
              <button
                type="button"
                @click="addRecipientEmail"
                class="btn-outline flex items-center gap-1 px-3 py-2 text-xs font-semibold cursor-pointer"
              >
                <Plus :size="14" />
                Añadir
              </button>
            </div>

            <!-- Comentario opcional -->
            <div class="mb-2">
              <label class="block text-xs font-semibold text-slate-700 mb-1">Nota o mensaje adicional en el correo (opcional):</label>
              <textarea
                v-model="emailComment"
                rows="2"
                placeholder="Mensaje o indicaciones adicionales..."
                class="w-full text-xs rounded border border-slate-300 p-2 focus:outline-none focus:border-blue-500 bg-white"
              ></textarea>
            </div>
          </div>

          <div class="modal-footer flex items-center justify-end gap-2 pt-3 border-t border-slate-200">
            <button
              type="button"
              class="btn-secondary text-xs px-3 py-1.5 cursor-pointer"
              @click="showEmailModal = false"
              :disabled="isSendingEmail"
            >
              Cancelar
            </button>
            <button
              type="button"
              class="btn-primary flex items-center gap-1.5 text-xs px-4 py-1.5 cursor-pointer"
              @click="sendReportByEmail"
              :disabled="isSendingEmail"
            >
              <Send :size="14" />
              {{ isSendingEmail ? 'Enviando...' : 'Enviar Reporte en PDF' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- OVERLAY DE CARGA ELEGANTE MIENTRAS SE GENERA EL PDF -->
    <div
      v-if="isDownloading"
      style="position: fixed; inset: 0; z-index: 999999; background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center;"
    >
      <div style="background: #ffffff; padding: 26px 36px; border-radius: 12px; box-shadow: 0 20px 45px rgba(0,0,0,0.25); text-align: center; max-width: 320px;">
        <div style="font-size: 30px; margin-bottom: 8px;">⏳</div>
        <div style="font-family: inherit; font-size: 15px; font-weight: 600; color: #0f172a; margin-bottom: 4px;">Generando PDF...</div>
        <div style="font-family: inherit; font-size: 12px; color: #64748b;">Preparando documento en alta resolución</div>
      </div>
    </div>

    <!-- ===== PRINT-ONLY FORMAL DOCUMENT ===== -->
    <div v-if="report" class="print-report-document">
      <!-- HEADER -->
      <table class="print-header-box">
        <tr>
          <td class="print-header-logo-td">
            <img v-if="companyLogo" :src="companyLogo" alt="Logo empresa" class="print-logo-img" />
            <div v-else class="print-logo-fallback">{{ companyName.substring(0, 4).toUpperCase() }}</div>
          </td>
          <td class="print-header-info-td">
            <div class="print-company-name">{{ companyName }}</div>
            <div class="print-folio-bar">{{ report.folio }}</div>
            <div class="print-subtitle">
              REPORTE DE MANTENIMIENTO / ORDEN DE TRABAJO {{ report.categoria ? `(${report.categoria.toUpperCase()})` : '' }}
            </div>
          </td>
        </tr>
      </table>

      <!-- DATOS GENERALES -->
      <div class="print-section">
        <h2 class="print-section-title">DATOS GENERALES</h2>
        <table class="print-datos-table">
          <tr>
            <td style="width: 50%;"><strong>Responsable:</strong> {{ tecnicoNombre }}</td>
            <td style="width: 50%;"><strong>OT/OC/Ticket:</strong> {{ getOrigenDetails().folio }}</td>
          </tr>
          <tr>
            <td><strong>Puesto:</strong> {{ tecnicoPuesto }}</td>
            <td><strong>Área:</strong> {{ areaName }}</td>
          </tr>
          <tr>
            <td><strong>No. Empleado:</strong> {{ tecnicoNumero }}</td>
            <td><strong>Responsable de Área:</strong> {{ displayResponsableNombre }}</td>
          </tr>
          <tr>
            <td><strong>Fecha:</strong> {{ formatDateShort(report.fechaElaboracion) }}</td>
            <td></td>
          </tr>
          <tr>
            <td><strong>Correos:</strong> {{ tecnicoEmail }}</td>
            <td></td>
          </tr>
        </table>
      </div>

      <hr class="print-hr" />

      <!-- FALLA REPORTADA -->
      <div class="print-section" v-if="report.fallaReportada">
        <h2 class="print-section-title">DESCRIPCIÓN DE LA FALLA REPORTADA</h2>
        <div class="print-text-paragraphs">
          <p
            v-for="(p, i) in splitParagraphs(report.fallaReportada)"
            :key="'falla-' + i"
            class="print-paragraph"
          >
            {{ p }}
          </p>
        </div>
      </div>

      <!-- DESCRIPCIÓN DEL SERVICIO -->
      <div class="print-section">
        <h2 class="print-section-title">DESCRIPCIÓN DEL SERVICIO</h2>
        <div class="print-text-paragraphs">
          <p
            v-for="(p, i) in splitParagraphs(report.desarrolloActividades || 'Sin descripción de actividades.')"
            :key="'des-' + i"
            class="print-paragraph"
          >
            {{ p }}
          </p>
        </div>
      </div>

      <!-- CONCLUSIÓN DEL SERVICIO -->
      <div class="print-section">
        <h2 class="print-section-title">CONCLUSIÓN DEL SERVICIO</h2>
        <div class="print-text-paragraphs">
          <p
            v-for="(p, i) in splitParagraphs(
              report.conclusionTrabajo ||
                (report.estatusReporte === 'Cerrado / Trabajo Finalizado'
                  ? 'Trabajo finalizado satisfactoriamente.'
                  : 'Trabajo en proceso, pendiente de finalizar.')
            )"
            :key="'con-' + i"
            class="print-paragraph"
          >
            {{ p }}
          </p>
        </div>
      </div>

      <!-- TIEMPOS -->
      <div class="print-section">
        <p class="print-text" style="margin-top: 0.5rem; margin-bottom: 0.8rem;">
          <strong>Tiempos:</strong> &nbsp;&nbsp;&nbsp;&nbsp;
          <strong>Inicio:</strong> {{ formatTimeOnly(report.fechaHoraInicio) }}
          &nbsp;&nbsp;&nbsp;&nbsp;
          <strong>Fin:</strong> {{ formatTimeOnly(report.fechaHoraFin) }}
          &nbsp;&nbsp;&nbsp;&nbsp;
          <strong>Total:</strong> {{ totalHours }}
        </p>
      </div>

      <!-- MATERIALES -->
      <div class="print-section" v-if="report.materiales && report.materiales.length > 0">
        <h2 class="print-section-title">MATERIALES UTILIZADOS</h2>
        <table class="print-table">
          <thead>
            <tr>
              <th>Item</th>
              <th>Descripción</th>
              <th>Cantidad</th>
              <th>Unidad</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(mat, idx) in report.materiales" :key="mat.idItem">
              <td>{{ Number(idx) + 1 }}</td>
              <td>{{ mat.material }}</td>
              <td>{{ mat.cantidad }}</td>
              <td>{{ mat.unidad }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- EVIDENCIA FOTOGRÁFICA -->
      <div class="print-section">
        <h2 class="print-section-title">EVIDENCIA FOTOGRÁFICA</h2>
        <div
          class="print-evidence-grid"
          v-if="report.evidenciaFotografica && report.evidenciaFotografica.length > 0"
        >
          <div
            class="print-evidence-item"
            v-for="(item, idx) in report.evidenciaFotografica"
            :key="idx"
          >
            <img
              v-if="isImageEvidence(item)"
              :src="item.url"
              alt="Evidencia"
              class="print-evidence-img"
            />
            <p v-else class="print-evidence-comment">
              <strong>Documento:</strong> {{ item.nombre || item.url }}
            </p>
            <p class="print-evidence-comment">{{ item.comentario }}</p>
          </div>
        </div>
        <p class="print-text print-empty-state" v-else>
          No se tomaron evidencias fotográficas para este reporte.
        </p>
      </div>

      <!-- FOOTER / SIGNATURES -->
      <div class="print-signatures">
        <div class="print-sig-block">
          <p class="print-sig-label">Revisó:</p>
          <div class="print-sig-line"></div>
          <p class="print-sig-sublabel">NOMBRE Y FIRMA</p>
        </div>
        <div class="print-sig-block">
          <p class="print-sig-label">Elaboró:</p>
          <div class="print-sig-line"></div>
          <p class="print-sig-sublabel">NOMBRE Y FIRMA</p>
        </div>
      </div>

      <p class="print-footer-note">Generado con coreReports® para {{ companyName }}</p>
    </div>
  </div>
</template>

<style scoped lang="scss">
  .page-container-detail-report {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem 0;
  }

  .report-header {
    margin-bottom: 1.25rem;
  }

  .btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: none;
    border: none;
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
    margin-bottom: 0.75rem;
    transition: color 0.2s;

    &:hover {
      color: #0f172a;
    }
  }

  .header-title-container {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
  }

  .header-title {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;

    .folio-badge {
      align-self: flex-start;
      background: #e2e8f0;
      color: #475569;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.5px;
    }

    h1 {
      font-size: 2rem;
      color: #0f172a;
      margin: 0;
      line-height: 1.2;
    }
  }

  .header-actions {
    display: flex;
    gap: 12px;
  }

  .btn-primary {
    background-color: #3b82f6;
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    transition: background-color 0.2s;

    &:hover {
      background-color: #2563eb;
    }
  }

  .btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background-color: white;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    transition: background-color 0.2s;

    &:hover {
      background-color: #f8fafc;
    }
  }

  .btn-survey {
    background-color: #fef3c7;
    border: 1px solid #fde68a;
    color: #92400e;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    transition: background-color 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;

    &:hover {
      background-color: #fde68a;
    }
  }

  .quick-meta-bar {
    display: flex;
    align-items: center;
    gap: 2.5rem;
    margin-bottom: 1.5rem;
    padding: 1rem 1.5rem;
  }

  .meta-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .divider-vertical {
    width: 1px;
    height: 40px;
    background: #e2e8f0;
  }

  .report-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    align-items: start;
  }

  .card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  }

  .description-card,
  .materials-card {
    margin-bottom: 1.5rem;

    h2 {
      font-size: 1.1rem;
      color: #0f172a;
      margin-bottom: 1rem;
      border-bottom: 1px solid #f1f5f9;
      padding-bottom: 0.5rem;
    }

    .description-text {
      color: #334155;
      line-height: 1.6;
      font-size: 1rem;
      white-space: pre-wrap;
    }

    .description-text-scroll {
      max-height: 160px;
      overflow-y: auto;
      padding-right: 8px;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 transparent;

      &::-webkit-scrollbar {
        width: 6px;
      }
      &::-webkit-scrollbar-track {
        background: transparent;
      }
      &::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 4px;
      }
    }

    .view-full-preview-btn {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      margin-top: 10px;
      padding: 4px 10px;
      font-size: 0.8rem;
      font-weight: 600;
      color: #2563eb;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s;

      &:hover {
        background: #dbeafe;
        color: #1d4ed8;
      }
    }
  }

  .materials-list {
    list-style: none;
    padding: 0;
    margin: 0;

    li {
      display: flex;
      justify-content: space-between;
      padding: 0.75rem 0;
      border-bottom: 1px solid #f1f5f9;

      &:last-child {
        border-bottom: none;
      }

      .mat-name {
        color: #0f172a;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
      }
      .mat-qty {
        color: #64748b;
        font-size: 0.9rem;
        font-weight: 600;
      }
    }
  }

  .inline-icon {
    color: #cbd5e1;
  }

  .meta-card {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;

    h3 {
      font-size: 0.85rem;
      text-transform: uppercase;
      color: #94a3b8;
      font-weight: 700;
      margin-bottom: 0.75rem;
      letter-spacing: 0.5px;
    }
  }

  .divider {
    height: 1px;
    background: #e2e8f0;
    width: 100%;
  }

  .meta-label {
    color: #64748b;
    font-weight: 500;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .person-info,
  .origin-info {
    display: flex;
    align-items: flex-start;
    gap: 12px;

    .icon {
      color: #94a3b8;
      margin-top: 2px;
    }
    .name,
    strong {
      font-weight: 600;
      color: #0f172a;
      font-size: 0.95rem;
    }
    .group-names {
      line-height: 1.4;
    }
  }

  .date-info {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    color: #334155;
    font-size: 0.9rem;

    .icon {
      color: #94a3b8;
      margin-top: 2px;
    }
    strong {
      color: #0f172a;
      font-weight: 600;
      display: inline-block;
      margin-bottom: 2px;
    }
  }

  .status-pill {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-block;

    &.pill-warning {
      background: #fffbeb;
      color: #d97706;
    }
    &.pill-success {
      background: #dcfce7;
      color: #15803d;
    }
  }

  .error-state {
    padding: 4rem 2rem;
    text-align: center;

    h2 {
      color: #0f172a;
      margin-bottom: 0.5rem;
    }
    p {
      color: #64748b;
    }
  }

  /* Timeline specific styling */
  .timeline-section {
    margin-top: 2rem;

    .timeline-heading {
      font-size: 1.1rem;
      color: #0f172a;
      margin-bottom: 1rem;
      border-bottom: 1px solid #f1f5f9;
      padding-bottom: 0.5rem;
      font-weight: 600;
    }
  }

  .timeline {
    position: relative;
    padding-left: 1.5rem;

    &::before {
      content: '';
      position: absolute;
      top: 0;
      bottom: 0;
      left: 0.4rem;
      width: 2px;
      background: #e2e8f0;
    }
  }

  .timeline-item {
    position: relative;
    margin-bottom: 1.5rem;

    &:last-child {
      margin-bottom: 0;
    }
  }

  .timeline-dot {
    position: absolute;
    top: 1.25rem;
    left: -1.7rem;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #3b82f6;
    border: 3px solid white;
    box-shadow: 0 0 0 1px #cbd5e1;
    z-index: 1;
  }

  .timeline-content {
    background: white;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    padding: 1rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);

    .timeline-user {
      color: #0f172a;
      font-size: 0.95rem;
    }
    .timeline-date {
      color: #64748b;
      font-size: 0.8rem;
    }
    .timeline-desc {
      color: #475569;
      font-size: 0.95rem;
      margin-top: 0.5rem;
      line-height: 1.4;
    }
  }

  /* Modal Styling */
  .modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(15, 23, 42, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 50;
    backdrop-filter: blur(2px);
  }

  .modal-content {
    background: white;
    border-radius: 12px;
    width: 100%;
    max-width: 500px;
    box-shadow:
      0 20px 25px -5px rgba(0, 0, 0, 0.1),
      0 10px 10px -5px rgba(0, 0, 0, 0.04);
  }

  .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;

    h2 {
      font-size: 1.1rem;
      color: #0f172a;
      font-weight: 600;
      margin: 0;
    }
  }

  .btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #94a3b8;
    cursor: pointer;
    line-height: 1;
    transition: color 0.1s;
    &:hover {
      color: #ef4444;
    }
  }

  .modal-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;

    label {
      font-size: 0.85rem;
      font-weight: 600;
      color: #475569;
    }
  }

  .form-control {
    padding: 0.6rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.95rem;
    outline: none;
    transition:
      border-color 0.2s,
      box-shadow 0.2s;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;

    &:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
  }

  .modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    background-color: #f8fafc;
    border-top: 1px solid #f1f5f9;
    border-radius: 0 0 12px 12px;
  }

  .btn-cancel {
    background: white;
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;

    &:hover {
      background: #f1f5f9;
    }
  }

  .btn-save {
    background: #3b82f6;
    border: 1px solid transparent;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;

    &:hover {
      background: #2563eb;
    }
  }

  .modal-content-wide {
    max-width: 920px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
  }

  .modal-body-scroll {
    overflow-y: auto;
    max-height: calc(90vh - 135px);
  }

  .edit-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 1rem 1.25rem;
  }

  .edit-section-title {
    margin: 0 0 0.75rem 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 0.45rem;
  }

  .edit-section-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;

    .edit-section-title {
      margin: 0;
      border: none;
      padding: 0;
    }
  }

  .grid-2-cols {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
  }

  .grid-3-cols {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
  }

  .bg-disabled {
    background: #e2e8f0;
    color: #64748b;
    cursor: not-allowed;
  }

  .net-hours-badge {
    padding: 0.6rem 0.85rem;
    background: #e0f2fe;
    border: 1px solid #bae6fd;
    color: #0369a1;
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.95rem;
  }

  .btn-add-small {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;

    &:hover {
      background: #dbeafe;
    }
  }

  .btn-remove-small {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
    width: 34px;
    height: 34px;
    border-radius: 6px;
    cursor: pointer;
    flex-shrink: 0;

    &:hover {
      background: #fee2e2;
    }
  }

  .dynamic-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
  }

  .material-edit-row {
    display: grid;
    grid-template-columns: 32px 1fr 90px 130px 36px;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.5rem;
  }

  .mat-idx {
    font-size: 0.8rem;
    font-weight: 700;
    color: #64748b;
    text-align: center;
  }

  .empty-hint {
    font-size: 0.85rem;
    color: #64748b;
    font-style: italic;
    padding: 0.4rem 0;
  }

  .edit-evidence-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 0.85rem;
  }

  .edit-evidence-card {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 0.6rem;
  }

  .edit-evidence-preview {
    position: relative;
    height: 115px;
    background: #f1f5f9;
    border-radius: 6px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .edit-evidence-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .edit-evidence-doc {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    color: #475569;
    padding: 0.5rem;
    text-align: center;
  }

  .btn-delete-ev {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: rgba(220, 38, 38, 0.9);
    color: white;
    border: none;
    font-size: 1rem;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  @media (max-width: 768px) {
    .report-grid {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }
    .main-column,
    .side-column {
      width: 100%;
    }
    .header-title-container {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    .header-actions {
      flex-wrap: wrap;
      width: 100%;
      gap: 0.5rem;
    }
    .btn-primary,
    .btn-secondary {
      width: 100%;
    }
    .quick-meta-bar {
      flex-direction: column;
      align-items: flex-start;
      gap: 1rem;
    }
    .divider-vertical {
      width: 100%;
      height: 1px;
    }
    .timeline {
      padding-left: 1rem;
      margin-left: 1.5rem;
    }
    .timeline::before {
      left: 0;
    }
    .timeline-dot {
      left: -2.15rem;
    }
    .modal-content {
      width: 90%;
      max-width: 400px;
      margin: 0 auto;
    }
  }

  /* Evidence grid (on-screen) */
  .evidence-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    margin-top: 0.75rem;

    &.evidence-grid-scroll {
      max-height: 290px;
      overflow-y: auto;
      padding-right: 6px;
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 transparent;

      &::-webkit-scrollbar {
        width: 6px;
      }
      &::-webkit-scrollbar-track {
        background: transparent;
      }
      &::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 4px;
      }
    }
  }
  .evidence-item {
    display: flex;
    flex-direction: column;
    width: 200px;
  }
  .evidence-img {
    width: 100%;
    height: 140px;
    object-fit: contain;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    transition: box-shadow 0.2s;
  }
  .evidence-img:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  }
  .evidence-doc-link {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 72px;
    padding: 12px;
    border: 1px dashed #94a3b8;
    border-radius: 6px;
    background: #f8fafc;
    color: #334155;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    word-break: break-all;
  }
  .evidence-doc-link:hover {
    border-color: #3b82f6;
    color: #1d4ed8;
    background: #eff6ff;
  }
  .evidence-comment-text {
    line-height: 1.3;
    white-space: pre-wrap;
    word-wrap: break-word;
  }

  /* Image Preview Modal */
  .modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
  }
  .modal-content-img {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
  }
  .img-preview-full {
    max-width: 100%;
    max-height: 90vh;
    object-fit: contain;
    border-radius: 4px;
  }
  .btn-close-modal {
    position: absolute;
    top: -40px;
    right: 0;
    background: none;
    border: none;
    color: white;
    font-size: 32px;
    font-weight: bold;
    cursor: pointer;
  }

  /* Print document is hidden on screen */
  .print-report-document {
    display: none;
  }

  @media print {
    @page {
      margin: 2.5cm;
    }

    body {
      padding: 0;
      margin: 0;
    }

    *,
    *::before,
    *::after {
      box-sizing: border-box;
    }

    /* Hide the entire on-screen layout */
    .page-container-detail-report {
      display: none !important;
    }

    /* Show the formal print document */
    .print-report-document {
      display: block !important;
      font-family: Arial, Helvetica, sans-serif;
      color: #000;
      font-size: 11pt;
      line-height: 1.4;
      padding: 0;
      margin: 0;
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
    }

    /* Header */
    .print-header-box {
      width: 100%;
      border-collapse: collapse;
      border: 2px solid #000;
      margin-bottom: 1.5rem;
    }
    .print-header-logo-td {
      width: 28%;
      border-right: 2px solid #000;
      text-align: center;
      vertical-align: middle;
      padding: 0.5rem;
    }
    .print-logo-img {
      max-width: 95%;
      max-height: 80px;
      object-fit: contain;
      display: block;
      margin: 0 auto;
    }
    .print-logo-fallback {
      font-size: 16pt;
      font-weight: 700;
      color: #000;
    }
    .print-header-info-td {
      width: 72%;
      padding: 0;
      vertical-align: middle;
      text-align: center;
    }
    .print-company-name {
      font-size: 13pt;
      font-weight: 700;
      padding: 0.5rem;
      border-bottom: 2px solid #000;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .print-folio-bar {
      background: #9ca3af;
      color: #fff;
      font-size: 11pt;
      font-weight: 700;
      padding: 0.25rem 1rem;
      border-bottom: 2px solid #000;
      letter-spacing: 0.5px;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .print-subtitle {
      font-size: 9.5pt;
      font-weight: 700;
      padding: 0.35rem;
      text-transform: uppercase;
    }

    /* Sections */
    .print-section {
      margin-bottom: 1.25rem;
    }
    .print-section-title {
      font-size: 10pt;
      font-weight: 700;
      text-transform: uppercase;
      margin: 0 0 0.4rem 0;
      color: #000;
      letter-spacing: 0.5px;
    }

    /* Datos generales table */
    .print-datos-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 9.5pt;
      margin-bottom: 0.5rem;
    }
    .print-datos-table td {
      padding: 0.15rem 0.3rem;
      vertical-align: top;
      color: #000;
    }

    .print-hr {
      border: none;
      border-top: 2px solid #000;
      margin: 1.2rem 0;
    }

    .print-text {
      font-size: 10pt;
      white-space: pre-wrap;
      margin: 0;
    }
    .print-text-paragraphs {
      margin-top: 0.35rem;
      margin-bottom: 1.1rem;
    }
    .print-paragraph {
      font-size: 9.5pt;
      line-height: 1.55;
      color: #1e293b;
      text-align: justify;
      text-justify: inter-word;
      margin: 0 0 11px 0;
    }
    .print-paragraph:last-child {
      margin-bottom: 0;
    }
    .print-empty-state {
      color: #555;
      font-style: italic;
      padding-top: 0.5rem;
    }

    /* Materials table */
    .print-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 10pt;
      margin-top: 0.5rem;
    }
    .print-table th,
    .print-table td {
      border: 1px solid #333;
      padding: 0.4rem 0.6rem;
      text-align: left;
    }
    .print-table th {
      background: #f0f0f0;
      font-weight: 700;
    }

    /* Signatures */
    .print-signatures {
      display: flex;
      justify-content: space-between;
      margin-top: 6rem;
      gap: 4rem;
    }
    .print-sig-block {
      flex: 1;
    }
    .print-sig-label {
      font-size: 10pt;
      font-weight: 700;
      margin: 0 0 2rem 0;
    }
    .print-sig-line {
      border-bottom: 1px solid #000;
      width: 100%;
      margin-bottom: 0.25rem;
    }
    .print-sig-sublabel {
      font-size: 8pt;
      text-align: center;
      font-weight: 600;
      margin: 0;
      letter-spacing: 1px;
    }

    .print-footer-note {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      text-align: center;
      font-size: 8pt;
      color: #777;
      margin: 0;
      padding-top: 10px;
      background: white; /* To mask content underneath slightly */
      font-style: italic;
    }

    /* Evidence grid for print */
    .print-evidence-grid {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      margin-top: 0.5rem;
    }
    .print-evidence-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      page-break-inside: avoid;
      margin-bottom: 1rem;
    }
    .print-evidence-img {
      max-width: 100%;
      max-height: 9cm; /* Large enough, but preventing overflow */
      height: auto;
      object-fit: contain; /* Prevents cropping */
      border: 1px solid #000;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .print-evidence-comment {
      margin-top: 0.5rem;
      font-size: 10pt;
      text-align: center;
      width: 100%;
      max-width: 15cm;
      white-space: pre-wrap;
      word-wrap: break-word;
    }
  }
</style>
