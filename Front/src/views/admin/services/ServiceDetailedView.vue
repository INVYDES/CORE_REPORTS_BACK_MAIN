<script setup lang="ts">
  import { ref, reactive, onMounted, computed } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import {
    ArrowLeft,
    User,
    Briefcase,
    Calendar,
    Clock,
    CheckCircle2,
    AlertCircle,
  } from 'lucide-vue-next'
  import client from '../../../api/client'
  import {
    formatLocalDateOnly,
    formatLocalDateTime,
    stripUtcSuffix,
  } from '../../../utils/reportMeta'
  import { useToast } from 'vue-toastification'
  import { useAuthStore } from '../../../stores/authStore'
  import { storeToRefs } from 'pinia'

  const authStore = useAuthStore()
  const { currentUser } = storeToRefs(authStore)
  const toast = useToast()

  const route = useRoute()
  const router = useRouter()

  const goBack = () => {
    router.back()
  }

  const serviceId = Number(route.params.id)
  const service = ref<any>(null)
  const technicians = ref<any[]>([])
  const areas = ref<any[]>([])
  const saving = ref(false)
  const showEditModal = ref(false)
  const savingEdit = ref(false)

  const editForm = reactive({
    asunto: '',
    descripcion: '',
    solicitante: '',
    cargo: '',
    categoria: 'preventivo',
    prioridad: 'media',
    estatus: 'programado',
    area_id: null as number | null,
    fecha_asignacion: '',
    fecha_vencimiento: '',
  })

  const statusLabel = (code: string) =>
    (
      ({
        programado: 'Programado',
        en_proceso: 'En Proceso',
        realizado: 'Realizado',
        vencido: 'Vencido',
      }) as any
    )[code] ||
    code ||
    '—'
  const priorityLabel = (code: string | null) =>
    (({ alta: 'Alta', media: 'Media', baja: 'Baja' }) as any)[code || ''] || code || '—'
  const fmt = (iso?: string | null) => {
    if (!iso) return ''
    return formatLocalDateOnly(iso)
  }

  const applyService = (raw: any) => {
    const dep =
      (currentUser.value as any)?.dependencia_id ?? (currentUser.value as any)?.idDependencia
    if (raw.dependencia_id !== undefined && dep !== undefined && raw.dependencia_id !== dep) {
      service.value = null
      return
    }
    const rawAsig = raw.fecha_asignacion ? stripUtcSuffix(String(raw.fecha_asignacion)).slice(0, 10) : ''
    const rawVenc = raw.fecha_vencimiento ? stripUtcSuffix(String(raw.fecha_vencimiento)).slice(0, 10) : ''
    const rawReportes = Array.isArray(raw.reportes) ? raw.reportes : []
    const finalReport =
      rawReportes.slice().reverse().find((r: any) => String(r.estatus || '').toLowerCase() === 'finalizado') ||
      rawReportes[rawReportes.length - 1] ||
      null
    const hasFinalizadoReport = Boolean(finalReport && String(finalReport.estatus || '').toLowerCase() === 'finalizado')
    const effectiveRawStatus = hasFinalizadoReport ? 'realizado' : raw.estatus || 'programado'
    service.value = {
      id: raw.id,
      folio: raw.folio,
      asunto: raw.asunto,
      descripcion: raw.descripcion || '',
      solicitante: raw.solicitante || '—',
      cargo: raw.cargo || '',
      categoriaRaw: raw.categoria || 'preventivo',
      estatusRaw: effectiveRawStatus,
      prioridadRaw: raw.prioridad || 'media',
      areaId: raw.area_id ?? null,
      estatus: statusLabel(effectiveRawStatus),
      prioridad: priorityLabel(raw.prioridad),
      asignadoA: raw.asignado
        ? `${raw.asignado.nombre || ''} ${raw.asignado.apellidos || ''}`.trim()
        : '',
      usuarioAsignadoId: raw.usuario_asignado_id ?? null,
      rawFechaAsignacion: rawAsig,
      rawFechaVencimiento: rawVenc,
      fechaAsignacion: rawAsig ? fmt(rawAsig) : 'No definida',
      fechaVencimiento: rawVenc ? fmt(rawVenc) : 'Pendiente',
      reportes: rawReportes.map((r: any) => ({
        id: r.id,
        folio: r.folio,
        estatus: String(r.estatus || '').toLowerCase(),
        fecha: r.fecha_inicio || r.created_at || '',
        creadoPor: r.creado_por,
      })),
      reporteAsociadoId: finalReport?.id ?? null,
      reporteAsociadoFolio: finalReport?.folio ?? null,
      historialModificaciones: [],
    }
  }

  onMounted(async () => {
    try {
      const res = await client.get(`/servicios/${serviceId}`).then(r => r.data)
      applyService(res.data || res)
    } catch {
      service.value = null
    }
    try {
      const res = await client.get('/tecnicos').then(r => r.data)
      const list = res.data || res || []
      allUsers.value = Array.isArray(list) ? list : []
      const dep =
        (currentUser.value as any)?.dependencia_id ?? (currentUser.value as any)?.idDependencia
      technicians.value = (Array.isArray(list) ? list : [])
        .filter((u: any) => u.rol === 3 && (dep === undefined || u.dependencia_id === dep))
        .map((u: any) => ({ id: u.id, nombre: u.nombre, apellidos: u.apellidos }))
    } catch {}
    try {
      const res = await client.get('/areas', { params: { per_page: 100 } }).then(r => r.data)
      const list = res.data || res || []
      if (Array.isArray(list)) areas.value = list
    } catch {}
  })

  const openEditModal = () => {
    if (!service.value) return
    editForm.asunto = service.value.asunto || ''
    editForm.descripcion = service.value.descripcion || ''
    editForm.solicitante = service.value.solicitante === '—' ? '' : service.value.solicitante || ''
    editForm.cargo = service.value.cargo || ''
    editForm.categoria = service.value.categoriaRaw || 'preventivo'
    editForm.prioridad = service.value.prioridadRaw || 'media'
    editForm.estatus = service.value.estatusRaw || 'programado'
    editForm.area_id = service.value.areaId ?? null
    editForm.fecha_asignacion = service.value.rawFechaAsignacion || ''
    editForm.fecha_vencimiento = service.value.rawFechaVencimiento || ''
    showEditModal.value = true
  }

  const saveServiceEdit = async () => {
    if (!service.value || savingEdit.value) return
    if (!editForm.asunto.trim()) {
      toast.error('El asunto es obligatorio')
      return
    }
    savingEdit.value = true
    try {
      const res = await client
        .put(`/servicios/${serviceId}`, {
          asunto: editForm.asunto.trim(),
          descripcion: editForm.descripcion.trim(),
          solicitante: editForm.solicitante.trim() || null,
          cargo: editForm.cargo.trim() || null,
          categoria: editForm.categoria,
          prioridad: editForm.prioridad,
          estatus: editForm.estatus,
          area_id: editForm.area_id,
          fecha_asignacion: editForm.fecha_asignacion || null,
          fecha_vencimiento: editForm.fecha_vencimiento || null,
        })
        .then(r => r.data)
      applyService(res.data || res)
      toast.success('Servicio programado actualizado correctamente')
      showEditModal.value = false
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'No se pudo actualizar el servicio')
    } finally {
      savingEdit.value = false
    }
  }

  const selectedTechnician = ref<any>('')
  const assignService = async () => {
    const tech = technicians.value.find((t: any) => t.id === Number(selectedTechnician.value))
    if (!service.value || !tech || saving.value) return
    saving.value = true
    try {
      const wasProgramado = service.value.estatus === 'Programado'
      const res = await client
        .put(`/servicios/${serviceId}`, {
          usuario_asignado_id: tech.id,
          estatus: wasProgramado ? 'en_proceso' : undefined,
        })
        .then(r => r.data)
      const raw = res.data || res
      applyService(raw)
      toast.success(`Servicio asignado exitosamente a ${tech.nombre} ${tech.apellidos}`)
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'No se pudo asignar el servicio')
    } finally {
      saving.value = false
    }
  }

  const availableTechnicians = technicians

  const allUsers = ref<any[]>([])

  const getUserName = (id: number | null) => {
    if (!id) return 'Sin Asignar'
    const t = allUsers.value.find((u: any) => u.id === id) || technicians.value.find((u: any) => u.id === id)
    return t ? `${t.nombre} ${t.apellidos}`.trim() : 'Usuario Desconocido'
  }

  const formatDate = (dateString: string) => {
    return formatLocalDateTime(dateString)
  }

  const isServiceFinalized = computed(() => {
    if (!service.value) return false
    const st = String(service.value.estatusRaw || service.value.estatus || '').toLowerCase()
    return st === 'realizado' || st === 'finalizado' || st === 'completado' || Boolean(service.value.reporteAsociadoId)
  })

  const goToCreateReport = () => {
    if (!service.value || isServiceFinalized.value) return
    router.push({
      name: 'reports-new',
      query: {
        tipo: 'Servicio Programado',
        origenId: String(service.value.id),
      },
    })
  }
</script>

<template>
  <div class="page-container-detailed-service">
    <div v-if="service">
      <!-- Header -->
      <header class="ticket-header">
        <!-- Back button -->
        <button @click="goBack" class="btn-back">
          <ArrowLeft :size="18" />
          <span>Regresar a la lista</span>
        </button>

        <!-- Folio & Actions -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
          <div class="header-title">
            <span class="folio-badge">{{ service.folio }}</span>
            <h1>{{ service.asunto }}</h1>
          </div>
          <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <button
              v-if="currentUser?.rol === 1 || currentUser?.rol === 2"
              @click="openEditModal"
              style="background: #ffffff; border: 1px solid #cbd5e1; color: #334155; padding: 10px 16px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer;"
            >
              ✏️ Editar Servicio
            </button>
            <button
              v-if="!isServiceFinalized && (currentUser?.rol === 1 || currentUser?.rol === 3)"
              @click="goToCreateReport"
              style="background: #2563eb; border: none; color: #ffffff; padding: 10px 18px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);"
            >
              📝 Crear Reporte
            </button>
          </div>
        </div>
      </header>

      <!-- Upper banner "Meta-information"-->
      <div class="card quick-meta-bar">
        <!-- Status -->
        <div class="meta-item">
          <span class="meta-label">Estatus</span>
          <span class="status-pill" :class="service.estatus.toLowerCase().replace(' ', '-')">
            {{ service.estatus }}
          </span>
        </div>

        <div class="divider-vertical"></div>

        <!-- Priority-->
        <div class="meta-item">
          <span class="meta-label">Prioridad</span>
          <span class="priority-text" :class="service.prioridad.toLowerCase()">
            <AlertCircle :size="16" />
            {{ service.prioridad }}
          </span>
        </div>

        <div class="divider-vertical"></div>

        <!-- Applicant -->
        <div class="meta-item">
          <span class="meta-label">Solicitante</span>
          <div class="person-info">
            <User :size="18" class="icon" />
            <!-- Information-->
            <div>
              <!-- Name-->
              <div class="name">
                {{ service.solicitante }}
              </div>
              <!-- Role -->
              <div class="role">
                <Briefcase :size="12" class="inline-icon" />{{ service.cargo }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Ticket grid -->
      <div class="ticket-grid">
        <!-- Main column / Description -->
        <div class="main-column">
          <div class="card description-card">
            <h2>Descripción del Reporte</h2>
            <p class="description-text">{{ service.descripcion }}</p>
          </div>

          <!-- Linked Reports Section -->
          <div v-if="service.reportes && service.reportes.length > 0" class="card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
              <h2 style="font-size: 1.05rem; color: #0f172a; margin: 0; font-weight: 700;">
                📋 Reportes de Trabajo Vinculados ({{ service.reportes.length }})
              </h2>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.6rem;">
              <div
                v-for="rep in service.reportes"
                :key="rep.id"
                style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; gap: 1rem; flex-wrap: wrap;"
              >
                <div style="display: flex; flex-direction: column; gap: 3px;">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">{{ rep.folio }}</span>
                    <span
                      class="status-pill"
                      style="font-size: 0.7rem; padding: 2px 8px; border-radius: 12px; font-weight: 700;"
                      :class="rep.estatus === 'finalizado' ? 'pill-success' : 'pill-warning'"
                    >
                      {{ rep.estatus === 'finalizado' ? 'FINALIZADO' : 'TRABAJO PARCIAL' }}
                    </span>
                  </div>
                  <span style="font-size: 0.8rem; color: #64748b;">
                    Elaboró: {{ getUserName(rep.creadoPor) }} • {{ formatDate(rep.fecha) }}
                  </span>
                </div>
                <button
                  @click="router.push({ name: 'report-detail', params: { id: rep.id } })"
                  style="background: #ffffff; border: 1px solid #cbd5e1; color: #1e40af; padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;"
                >
                  👁️ Ver Reporte
                </button>
              </div>
            </div>
          </div>

          <!-- History Timeline -->
          <div
            class="timeline-section"
            v-if="service.historialModificaciones && service.historialModificaciones.length > 0"
          >
            <h2 class="timeline-heading">Historial de Modificaciones</h2>
            <div class="timeline">
              <div
                class="timeline-item"
                v-for="mod in service.historialModificaciones"
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

        <!--Side column-->
        <div class="side-column">
          <!-- Information / Who attends and data-->
          <div class="card meta-card">
            <!-- Atendió -->
            <div class="meta-section">
              <h3>Atendió</h3>
              <div class="person-info">
                <!-- Icon -->
                <CheckCircle2 :size="18" class="icon" />
                <!-- Text: Name who attend-->
                <div class="name" :class="{ 'text-red': !service.asignadoA }">
                  {{ service.asignadoA || 'Sin asignar' }}
                </div>
              </div>
            </div>

            <div class="divider"></div>

            <!-- Dates -->
            <div class="meta-section">
              <h3>Fechas</h3>
              <!-- Request date -->
              <div class="date-info">
                <Calendar :size="16" class="icon" />
                <span><strong>Asignado:</strong> {{ service.fechaAsignacion }}</span>
              </div>

              <!-- Due date-->
              <div class="date-info mt-2">
                <Clock :size="16" class="icon" />
                <span
                  ><strong>Vencimiento:</strong> {{ service.fechaVencimiento || 'Pendiente' }}</span
                >
              </div>
            </div>
          </div>

          <!--Reasignar ticket/card-->
          <!-- Only admin_tecnico, no one else can have acces to this!-->
          <div v-if="currentUser?.rol === 1" class="card assignment-card">
            <!-- Title-->
            <h3>{{ service.asignadoA ? 'Reasignar Servicio' : 'Asignar Técnico' }}</h3>

            <!--Select technician-->
            <select v-model="selectedTechnician" class="custom-select">
              <option value="" disabled>Selecciona un técnico...</option>
              <option v-for="tech in availableTechnicians" :key="tech.id" :value="tech.id">
                {{ tech.nombre }} {{ tech.apellidos }}
              </option>
            </select>

            <!-- Button "Confirmar Asignación"-->
            <button
              class="btn-action mt-2"
              @click="assignService"
              :disabled="!selectedTechnician || selectedTechnician === service.asignadoA"
            >
              Confirmar Asignación
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Error state-->
    <div v-else class="error-state">
      <h2>Servicio no encontrado u oculto</h2>
      <p>El servicio con este ID no existe o no te pertenece.</p>
      <button @click="goBack" class="btn-back mt-4">Regresar a la lista</button>
    </div>

    <!-- Modal Editar Servicio Programado (#21) -->
    <div
      v-if="showEditModal"
      style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); display: flex; justify-content: center; align-items: center; z-index: 60; padding: 1rem;"
    >
      <div
        style="background: #ffffff; border-radius: 12px; width: 100%; max-width: 680px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);"
      >
        <div
          style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0;"
        >
          <h2 style="margin: 0; font-size: 1.15rem; color: #0f172a;">
            ✏️ Editar Servicio Programado ({{ service?.folio }})
          </h2>
          <button
            @click="showEditModal = false"
            style="background: none; border: none; font-size: 1.5rem; color: #64748b; cursor: pointer;"
          >
            &times;
          </button>
        </div>

        <div style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
              <label style="display:block;font-size:0.85rem;font-weight:600;color:#475569;margin-bottom:4px;">Asunto *</label>
              <input
                type="text"
                v-model="editForm.asunto"
                style="width:100%;padding:0.6rem;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;"
              />
            </div>
            <div>
              <label style="display:block;font-size:0.85rem;font-weight:600;color:#475569;margin-bottom:4px;">Área / Subdependencia</label>
              <select
                v-model="editForm.area_id"
                style="width:100%;padding:0.6rem;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;"
              >
                <option :value="null">Sin área específica</option>
                <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.nombre }}</option>
              </select>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div>
              <label style="display:block;font-size:0.85rem;font-weight:600;color:#475569;margin-bottom:4px;">Categoría</label>
              <select
                v-model="editForm.categoria"
                style="width:100%;padding:0.6rem;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;"
              >
                <option value="preventivo">Mantenimiento Preventivo</option>
                <option value="correctivo">Mantenimiento Correctivo</option>
                <option value="instalacion">Instalación</option>
                <option value="mejora">Mejora</option>
                <option value="diagnostico">Diagnóstico</option>
              </select>
            </div>
            <div>
              <label style="display:block;font-size:0.85rem;font-weight:600;color:#475569;margin-bottom:4px;">Prioridad</label>
              <select
                v-model="editForm.prioridad"
                style="width:100%;padding:0.6rem;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;"
              >
                <option value="baja">Baja</option>
                <option value="media">Media</option>
                <option value="alta">Alta</option>
              </select>
            </div>
            <div>
              <label style="display:block;font-size:0.85rem;font-weight:600;color:#475569;margin-bottom:4px;">Estatus</label>
              <select
                v-model="editForm.estatus"
                style="width:100%;padding:0.6rem;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;"
              >
                <option value="programado">Programado</option>
                <option value="en_proceso">En Proceso</option>
                <option value="realizado">Realizado</option>
                <option value="vencido">Vencido</option>
              </select>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
              <label style="display:block;font-size:0.85rem;font-weight:600;color:#475569;margin-bottom:4px;">Fecha de Asignación</label>
              <input
                type="date"
                v-model="editForm.fecha_asignacion"
                style="width:100%;padding:0.6rem;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;"
              />
            </div>
            <div>
              <label style="display:block;font-size:0.85rem;font-weight:600;color:#475569;margin-bottom:4px;">Fecha de Vencimiento (Plazo)</label>
              <input
                type="date"
                v-model="editForm.fecha_vencimiento"
                style="width:100%;padding:0.6rem;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;"
              />
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
              <label style="display:block;font-size:0.85rem;font-weight:600;color:#475569;margin-bottom:4px;">Solicitante</label>
              <input
                type="text"
                v-model="editForm.solicitante"
                style="width:100%;padding:0.6rem;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;"
              />
            </div>
            <div>
              <label style="display:block;font-size:0.85rem;font-weight:600;color:#475569;margin-bottom:4px;">Cargo</label>
              <input
                type="text"
                v-model="editForm.cargo"
                style="width:100%;padding:0.6rem;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;"
              />
            </div>
          </div>

          <div>
            <label style="display:block;font-size:0.85rem;font-weight:600;color:#475569;margin-bottom:4px;">Descripción detallada</label>
            <textarea
              v-model="editForm.descripcion"
              rows="4"
              style="width:100%;padding:0.6rem;border:1px solid #cbd5e1;border-radius:6px;box-sizing:border-box;"
            ></textarea>
          </div>
        </div>

        <div
          style="display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 12px 12px;"
        >
          <button
            @click="showEditModal = false"
            :disabled="savingEdit"
            style="background: #ffffff; border: 1px solid #cbd5e1; color: #475569; padding: 0.55rem 1.1rem; border-radius: 6px; font-weight: 600; cursor: pointer;"
          >
            Cancelar
          </button>
          <button
            @click="saveServiceEdit"
            :disabled="savingEdit"
            style="background: #2563eb; border: none; color: #ffffff; padding: 0.55rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer;"
          >
            {{ savingEdit ? 'Guardando...' : 'Guardar Cambios' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
  .page-container-detailed-service {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem 0;
  }

  .ticket-header {
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

  /* --- NEW: TOP METADATA BAR --- */
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

  .ticket-grid {
    display: grid;
    grid-template-columns: 2fr 1fr; /* Left column is twice as wide as right */
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

  .description-card {
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

  .person-info {
    display: flex;
    align-items: center;
    gap: 12px;

    .icon {
      color: #94a3b8;
    }
    .name {
      font-weight: 600;
      color: #0f172a;
      font-size: 0.95rem;
    }
    .role {
      font-size: 0.8rem;
      color: #64748b;
      display: flex;
      align-items: center;
      gap: 4px;
      margin-top: 2px;
    }
    .inline-icon {
      color: #cbd5e1;
    }
    .text-red {
      color: #ef4444;
    }
  }

  .date-info {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #334155;
    font-size: 0.9rem;

    .icon {
      color: #94a3b8;
    }
    strong {
      color: #0f172a;
      font-weight: 600;
    }
  }

  .mt-2 {
    margin-top: 0.5rem;
  }

  .status-pill {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-block;

    &.abierto,
    &.programado {
      background: #fee2e2;
      color: #b91c1c;
    }
    &.en-proceso,
    &.realizado {
      background: #dcfce7;
      color: #15803d;
    }
    &.resuelto {
      background: #e0f2fe;
      color: #0369a1;
    }
    &.cerrado,
    &.vencido {
      background: #f1f5f9;
      color: #475569;
    }
  }

  .priority-text {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
    font-size: 0.85rem;

    &.alta {
      color: #ef4444;
    }
    &.media {
      color: #f97316;
    }
    &.baja {
      color: #64748b;
    }
  }

  /* --- ASSIGNMENT STYLES --- */
  .assignment-card {
    margin-top: 1rem;
    background: #f8fafc;

    h3 {
      font-size: 0.85rem;
      text-transform: uppercase;
      color: #64748b;
      font-weight: 700;
      margin-bottom: 1rem;
      letter-spacing: 0.5px;
    }
  }

  .custom-select {
    width: 100%;
    padding: 0.8rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background-color: white;
    font-size: 0.95rem;
    color: #334155;
    outline: none;
    cursor: pointer;
    margin-bottom: 1rem;
    transition: border-color 0.2s;

    &:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
  }

  .btn-action {
    width: 100%;
    background: #2563eb;
    color: white;
    border: none;
    padding: 0.8rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition:
      background 0.2s,
      opacity 0.2s;

    &:hover:not(:disabled) {
      background: #1d4ed8;
    }

    &:disabled {
      background: #94a3b8;
      cursor: not-allowed;
      opacity: 0.7;
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

  @media (max-width: 768px) {
    .ticket-grid {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }
    .main-column,
    .side-column {
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
    .header-title {
      word-break: break-word;
    }
  }
</style>
