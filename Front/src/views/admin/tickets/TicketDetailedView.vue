<script setup lang="ts">
  import { ref, onMounted, computed } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import {
    ArrowLeft,
    User,
    Briefcase,
    Calendar,
    Clock,
    CheckCircle2,
    AlertCircle,
    Camera,
    ExternalLink,
    X,
  } from 'lucide-vue-next'
  import client from '../../../api/client'
  import { formatLocalDateTime } from '../../../utils/reportMeta'
  import { useAuthStore } from '../../../stores/authStore'
  import { storeToRefs } from 'pinia'

  const authStore = useAuthStore()
  const { currentUser } = storeToRefs(authStore)

  const route = useRoute()
  const router = useRouter()

  const goBack = () => {
    router.back()
  }

  const ticketId = Number(route.params.id)
  const ticket = ref<any>(null)
  const technicians = ref<any[]>([])
  const saving = ref(false)

  const statusLabel = (code: string) =>
    (
      ({
        abierto: 'Abierto',
        en_proceso: 'En Proceso',
        resuelto: 'Resuelto',
        cerrado: 'Cerrado',
      }) as any
    )[code] ||
    code ||
    '—'
  const priorityLabel = (code: string | null) =>
    (({ alta: 'Alta', media: 'Media', baja: 'Baja' }) as any)[code || ''] || code || '—'
  const fmt = (iso?: string | null) => {
    if (!iso) return ''
    return formatLocalDateTime(iso)
  }

  // 3. Evidencias fotográficas adjuntas en la descripción
  const activeLightbox = ref<string | null>(null)

  const parsedDescription = computed(() => {
    const raw = ticket.value?.descripcion || ''
    if (!raw) return { cleanText: '', images: [] as string[] }

    // Detectar URLs directas de imágenes (jpg, jpeg, png, webp, gif)
    const urlRegex = /(https?:\/\/[^\s"'<>]+\.(?:jpg|jpeg|png|webp|gif)(?:\?[^\s"'<>]*)?)/gi
    const matches = raw.match(urlRegex) || []
    const images = Array.from(new Set(matches))

    // Limpiar el texto: quitar bloque de evidencias fotográficas para que no se vea como texto feo y remover emojis
    let cleanText = raw
      .replace(/[📷]?\s*Evidencias Fotográficas Adjuntas:[\s\S]*$/i, '')
      .replace(/-?\s*Foto\s*\d+:?\s*https?:\/\/[^\s]+/gi, '')
      .replace(/(https?:\/\/[^\s"'<>]+\.(?:jpg|jpeg|png|webp|gif)(?:\?[^\s"'<>]*)?)/gi, '')
      .replace(/[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]/gu, '')
      .replace(/[🏢👤📞✉️📋📷📌📍📎🏷️🔍⚡⚠️✅❌]/gu, '')
      .replace(/^[ \t]+/gm, '')
      .trim()

    return {
      cleanText: cleanText || raw,
      images,
    }
  })

  const applyTicket = (raw: any) => {
    const dep =
      (currentUser.value as any)?.dependencia_id ?? (currentUser.value as any)?.idDependencia
    if (raw.dependencia_id !== undefined && dep !== undefined && raw.dependencia_id !== dep) {
      ticket.value = null
      return
    }
    const rawReportes = Array.isArray(raw.reportes) ? raw.reportes : []
    const finalReport =
      rawReportes.slice().reverse().find((r: any) => String(r.estatus || '').toLowerCase() === 'finalizado') ||
      rawReportes[rawReportes.length - 1] ||
      null
    const hasFinalizadoReport = Boolean(finalReport && String(finalReport.estatus || '').toLowerCase() === 'finalizado')
    ticket.value = {
      id: raw.id,
      folio: raw.folio,
      asunto: raw.asunto,
      descripcion: raw.descripcion || '',
      solicitante: raw.solicitante || '—',
      cargo: (raw.cargo || '')
        .replace(/[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]/gu, '')
        .replace(/[🏢👤📞✉️📋📷📌📍📎🏷️🔍⚡⚠️✅❌]/gu, '')
        .trim(),
      estatus: hasFinalizadoReport ? 'Resuelto' : statusLabel(raw.estatus),
      prioridad: priorityLabel(raw.prioridad),
      atendio: raw.asignado
        ? `${raw.asignado.nombre || ''} ${raw.asignado.apellidos || ''}`.trim()
        : '',
      usuarioAsignadoId: raw.usuario_asignado_id ?? null,
      fechaSolicitud: fmt(raw.fecha_solicitud),
      fechaAtencion: fmt(raw.fecha_atencion),
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
      const res = await client.get(`/tickets/${ticketId}`).then(r => r.data)
      applyTicket(res.data || res)
    } catch {
      ticket.value = null
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
  })

  const selectedTechnician = ref<any>('')
  const assignTicket = async () => {
    const tech = technicians.value.find((t: any) => t.id === Number(selectedTechnician.value))
    if (!ticket.value || !tech || saving.value) return
    saving.value = true
    try {
      const res = await client
        .patch(`/tickets/${ticketId}/asignar`, { usuario_asignado_id: tech.id })
        .then(r => r.data)
      const raw = res.data || res
      const wasOpen = ticket.value.estatus === 'Abierto'
      applyTicket(raw)
      if (wasOpen && ticket.value) ticket.value.estatus = 'En Proceso'
      alert(`Ticket asignado exitosamente a ${tech.nombre} ${tech.apellidos}`)
    } catch (e: any) {
      alert(e?.response?.data?.message || 'No se pudo asignar el ticket')
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
    if (!dateString) return '---'
    const date = new Date(dateString)
    return date.toLocaleDateString('es-MX', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  const isTicketResolved = computed(() => {
    if (!ticket.value) return false
    const st = String(ticket.value.estatus || '').toLowerCase()
    return st === 'resuelto' || st === 'cerrado' || Boolean(ticket.value.reporteAsociadoId)
  })

  const goToCreateReport = () => {
    if (!ticket.value || isTicketResolved.value) return
    router.push({
      name: 'reports-new',
      query: {
        tipo: 'Ticket',
        origenId: String(ticket.value.id),
      },
    })
  }
</script>

<template>
  <div class="page-container-detail-ticket">
    <div v-if="ticket">
      <!-- Header -->
      <header class="ticket-header">
        <!-- Back button -->
        <button @click="goBack" class="btn-back">
          <ArrowLeft :size="18" />
          <span>Regresar a la lista</span>
        </button>

        <!-- Folio & Actions -->
        <div
          style="display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; flex-wrap: wrap;"
        >
          <div class="header-title">
            <span class="folio-badge">{{ ticket.folio }}</span>
            <h1>{{ ticket.asunto }}</h1>
          </div>
          <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <button
              v-if="!isTicketResolved && (currentUser?.rol === 1 || currentUser?.rol === 3)"
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
          <span class="status-pill" :class="ticket.estatus.toLowerCase().replace(' ', '-')">
            {{ ticket.estatus }}
          </span>
        </div>

        <div class="divider-vertical"></div>

        <!-- Priority-->
        <div class="meta-item">
          <span class="meta-label">Prioridad</span>
          <span class="priority-text" :class="ticket.prioridad.toLowerCase()">
            <AlertCircle :size="16" />
            {{ ticket.prioridad }}
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
                {{ ticket.solicitante }}
              </div>
              <!-- Role -->
              <div class="role"><Briefcase :size="12" class="inline-icon" />{{ ticket.cargo }}</div>
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
            <p class="description-text">{{ parsedDescription.cleanText }}</p>

            <!-- Galería de Evidencias Fotográficas Adjuntas -->
            <div v-if="parsedDescription.images.length > 0" class="evidence-gallery-section">
              <div class="evidence-header">
                <Camera :size="18" class="evidence-icon" />
                <h3>Evidencias Fotográficas Adjuntas ({{ parsedDescription.images.length }})</h3>
              </div>
              <div class="evidence-grid">
                <div
                  v-for="(imgUrl, idx) in parsedDescription.images"
                  :key="idx"
                  class="evidence-card"
                  @click="activeLightbox = imgUrl"
                >
                  <div class="evidence-thumb-container">
                    <img :src="imgUrl" :alt="'Evidencia ' + (idx + 1)" class="evidence-thumb" loading="lazy" />
                    <div class="evidence-overlay">
                      <span class="zoom-pill">Ampliar foto</span>
                    </div>
                  </div>
                  <div class="evidence-card-footer">
                    <span class="evidence-label">Foto {{ idx + 1 }}</span>
                    <a
                      :href="imgUrl"
                      target="_blank"
                      rel="noopener"
                      @click.stop
                      class="evidence-ext-link"
                      title="Abrir imagen en pestaña nueva"
                    >
                      <ExternalLink :size="14" />
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Linked Reports Section -->
          <div v-if="ticket.reportes && ticket.reportes.length > 0" class="card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
              <h2 style="font-size: 1.05rem; color: #0f172a; margin: 0; font-weight: 700;">
                Reportes de Trabajo Vinculados ({{ ticket.reportes.length }})
              </h2>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.6rem;">
              <div
                v-for="rep in ticket.reportes"
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
            v-if="ticket.historialModificaciones && ticket.historialModificaciones.length > 0"
          >
            <h2 class="timeline-heading">Historial de Modificaciones</h2>
            <div class="timeline">
              <div
                class="timeline-item"
                v-for="mod in ticket.historialModificaciones"
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
                <div class="name" :class="{ 'text-red': !ticket.atendio }">
                  {{ ticket.atendio || 'Sin asignar' }}
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
                <span><strong>Solicitud:</strong> {{ ticket.fechaSolicitud }}</span>
              </div>

              <!-- Attention date-->
              <div class="date-info mt-2">
                <Clock :size="16" class="icon" />
                <span><strong>Atención:</strong> {{ ticket.fechaAtencion || 'Pendiente' }}</span>
              </div>
            </div>
          </div>

          <!--Reasignar ticket/card-->
          <!-- Only admin_tecnico, no one else can have acces to this! 1:ADMIN-->
          <div v-if="currentUser?.rol === 1 || currentUser?.rol === 2" class="card assignment-card">
            <!-- Title-->
            <h3>{{ ticket.atendio ? 'Reasignar Ticket' : 'Asignar Técnico' }}</h3>

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
              @click="assignTicket"
              :disabled="!selectedTechnician || selectedTechnician === ticket.atendio"
            >
              Confirmar Asignación
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Error state-->
    <div v-else class="error-state">
      <h2>Ticket no encontrado u oculto</h2>
      <p>El ticket con este ID no existe o no te pertenece.</p>
      <button @click="goBack" class="btn-back mt-4">Regresar a la lista</button>
    </div>

    <!-- Lightbox Modal para ver la foto en alta resolución -->
    <Teleport to="body">
      <div
        v-if="activeLightbox"
        class="lightbox-backdrop"
        @click="activeLightbox = null"
      >
        <div class="lightbox-dialog" @click.stop>
          <button class="lightbox-close-btn" @click="activeLightbox = null" title="Cerrar">
            <X :size="22" />
          </button>
          <div class="lightbox-image-wrapper">
            <img :src="activeLightbox" alt="Evidencia en tamaño completo" class="lightbox-image" />
          </div>
          <div class="lightbox-footer">
            <a
              :href="activeLightbox"
              target="_blank"
              rel="noopener"
              class="btn-open-original"
            >
              <ExternalLink :size="15" /> Abrir imagen original en nueva pestaña
            </a>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped lang="scss">
  .page-container-detail-ticket {
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

    .evidence-gallery-section {
      margin-top: 1.5rem;
      padding-top: 1.25rem;
      border-top: 1px solid #f1f5f9;
    }

    .evidence-header {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.85rem;

      h3 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
      }

      .evidence-icon {
        color: #2563eb;
      }
    }

    .evidence-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 1rem;
    }

    .evidence-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      overflow: hidden;
      cursor: pointer;
      transition: all 0.2s ease;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

      &:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.12);
        border-color: #93c5fd;

        .evidence-thumb {
          transform: scale(1.04);
        }

        .evidence-overlay {
          opacity: 1;
        }
      }
    }

    .evidence-thumb-container {
      position: relative;
      width: 100%;
      height: 140px;
      background: #f1f5f9;
      overflow: hidden;
    }

    .evidence-thumb {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      transition: transform 0.25s ease;
    }

    .evidence-overlay {
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.2s ease;
    }

    .zoom-pill {
      background: rgba(255, 255, 255, 0.95);
      color: #0f172a;
      font-size: 0.75rem;
      font-weight: 600;
      padding: 5px 10px;
      border-radius: 20px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }

    .evidence-card-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 12px;
      background: #f8fafc;
      border-top: 1px solid #f1f5f9;
    }

    .evidence-label {
      font-size: 0.8rem;
      font-weight: 600;
      color: #334155;
    }

    .evidence-ext-link {
      color: #64748b;
      display: inline-flex;
      align-items: center;
      padding: 3px;
      border-radius: 4px;
      transition: color 0.15s, background-color 0.15s;

      &:hover {
        color: #2563eb;
        background: #eff6ff;
      }
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

    &.abierto {
      background: #fee2e2;
      color: #b91c1c;
    }
    &.en-proceso {
      background: #dcfce7;
      color: #15803d;
    }
    &.resuelto {
      background: #e0f2fe;
      color: #0369a1;
    }
    &.cerrado {
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

  /* Lightbox Styles */
  .lightbox-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.88);
    backdrop-filter: blur(4px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    animation: fadeIn 0.2s ease;
  }

  .lightbox-dialog {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    background: #0f172a;
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
    overflow: hidden;
  }

  .lightbox-close-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.15s;
    z-index: 10;

    &:hover {
      background: #ef4444;
    }
  }

  .lightbox-image-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    max-width: 85vw;
    max-height: 75vh;
    padding: 1rem;
  }

  .lightbox-image {
    max-width: 100%;
    max-height: 70vh;
    object-fit: contain;
    border-radius: 6px;
  }

  .lightbox-footer {
    width: 100%;
    padding: 10px 16px;
    background: #1e293b;
    display: flex;
    justify-content: center;
  }

  .btn-open-original {
    color: #93c5fd;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 6px;
    transition: background 0.15s;

    &:hover {
      background: rgba(59, 130, 246, 0.15);
      color: #ffffff;
    }
  }

  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
</style>
