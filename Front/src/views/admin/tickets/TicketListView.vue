<script setup lang="ts">
  import { ref, computed, onMounted, onUnmounted, defineAsyncComponent } from 'vue'
  import { useRouter } from 'vue-router'
  import { Search, Filter, ChevronRight, X } from 'lucide-vue-next'
  import { useAuthStore } from '../../../stores/authStore'
  import { storeToRefs } from 'pinia'

  const TicketCreateView = defineAsyncComponent(() => import('./TicketCreateView.vue'))

  // Store initialization
  const authStore = useAuthStore()
  const { currentUser } = storeToRefs(authStore)

  import client from '../../../api/client'
  import { useToast } from 'vue-toastification'

  import type { Ticket } from '../../../data/mockTickets'

  const router = useRouter()
  const toast = useToast()
  const tickets = ref<Ticket[]>([])
  const users = ref<any[]>([])
  const showTicketModal = ref(false)

  const statusLabel = (code: string) =>
    (
      ({
        abierto: 'Abierto',
        en_proceso: 'En Proceso',
        resuelto: 'Resuelto',
        cerrado: 'Resuelto',
      }) as any
    )[code] || code
  const priorityLabel = (code: string) =>
    (({ alta: 'Alta', media: 'Media', baja: 'Baja' }) as any)[code] || code
  const fmtShort = (iso?: string | null) => (iso ? String(iso).replace('T', ' ').slice(0, 16) : '')

  const loadUsers = async () => {
    try {
      const res = await client.get('/tecnicos').then(r => r.data)
      const list = res.data || res
      if (Array.isArray(list)) {
        users.value = list
      }
    } catch {
      // Silencioso: se complementa con los usuarios presentes en los tickets
    }
  }

  const availableUsers = computed(() => {
    const depId =
      (currentUser.value as any)?.dependencia_id ?? (currentUser.value as any)?.idDependencia
    const map = new Map<string, string>()

    users.value.forEach((u: any) => {
      const uDep = u.dependencia_id ?? u.idDependencia
      if (depId && uDep && Number(uDep) !== Number(depId)) return
      const id = String(u.id ?? u.idUsuario ?? '')
      const fullName = `${u.nombre || ''} ${u.apellidos || ''}`.trim()
      if (id && fullName) {
        map.set(id, fullName)
      }
    })

    tickets.value.forEach((t: any) => {
      if (depId && t.idDependencia !== undefined && Number(t.idDependencia) !== Number(depId))
        return
      if (t.usuarioAsignadoId && t.atendio) {
        const id = String(t.usuarioAsignadoId)
        if (!map.has(id)) {
          map.set(id, t.atendio)
        }
      }
    })

    return Array.from(map.entries()).map(([id, nombre]) => ({ id, nombre }))
  })

  const fetchTickets = async () => {
    try {
      const [res, repRes] = await Promise.all([
        client.get('/tickets', { params: { per_page: 100 } }).then(r => r.data),
        client
          .get('/reportes', { params: { per_page: 100 } })
          .then(r => r.data)
          .catch(() => ({ data: [] })),
      ])
      const list = res.data || res
      const repList = repRes.data || repRes || []
      const finalizedTicketIds = new Set(
        (Array.isArray(repList) ? repList : [])
          .filter((r: any) => r.ticket_id && String(r.estatus || '').toLowerCase() === 'finalizado')
          .map((r: any) => Number(r.ticket_id))
      )
      if (Array.isArray(list) && list.length) {
        tickets.value = list.map((x: any) => ({
          id: x.id,
          idDependencia: x.dependencia_id,
          usuarioAsignadoId: x.usuario_asignado_id ?? null,
          folio: x.folio,
          asunto: x.asunto,
          descripcion: x.descripcion || '',
          solicitante: x.solicitante,
          cargo: x.cargo,
          prioridad: priorityLabel(x.prioridad),
          estatus: finalizedTicketIds.has(Number(x.id)) ? 'Resuelto' : statusLabel(x.estatus),
          fechaSolicitud: fmtShort(x.fecha_solicitud),
          fechaAtencion: x.fecha_atencion ? fmtShort(x.fecha_atencion) : '',
          atendio: x.asignado
            ? `${x.asignado.nombre || ''} ${x.asignado.apellidos || ''}`.trim()
            : '',
        }))
      }
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'No se pudieron cargar los tickets')
    }
  }
  onMounted(() => {
    fetchTickets()
    loadUsers()
    window.addEventListener('core-reports:ticket-created', fetchTickets)
  })
  onUnmounted(() => {
    window.removeEventListener('core-reports:ticket-created', fetchTickets)
  })

  // Filter state
  const searchQuery = ref('')
  const statusFilter = ref('')
  const priorityFilter = ref('')
  const userFilter = ref('')
  const dateFilter = ref('')

  // Filter by enterprise and search criteria
  const filteredTickets = computed(() => {
    return tickets.value.filter(ticket => {
      const depId =
        (currentUser.value as any)?.dependencia_id ?? (currentUser.value as any)?.idDependencia
      if (
        ticket.idDependencia !== undefined &&
        depId !== undefined &&
        ticket.idDependencia !== depId
      ) {
        return false
      }

      if (currentUser.value?.rol === 3) {
        const uid = (currentUser.value as any)?.id ?? (currentUser.value as any)?.idUsuario
        if (Number((ticket as any).usuarioAsignadoId) !== Number(uid)) {
          return false
        }
      }

      const q = searchQuery.value.toLowerCase()
      const matchesSearch =
        searchQuery.value === '' ||
        (ticket.folio || '').toLowerCase().includes(q) ||
        (ticket.asunto || '').toLowerCase().includes(q) ||
        (ticket.descripcion || '').toLowerCase().includes(q)
      const matchesStatus = statusFilter.value === '' || ticket.estatus === statusFilter.value
      const matchesPriority =
        priorityFilter.value === '' || ticket.prioridad === priorityFilter.value
      const matchesUser =
        userFilter.value === '' ||
        (userFilter.value === 'sin_asignar'
          ? !(ticket as any).usuarioAsignadoId && !ticket.atendio
          : String((ticket as any).usuarioAsignadoId) === String(userFilter.value))
      const matchesDate =
        dateFilter.value === '' || (ticket.fechaSolicitud || '').slice(0, 10) === dateFilter.value

      return matchesSearch && matchesStatus && matchesPriority && matchesUser && matchesDate
    })
  })

  // ACtive filters
  const hasActiveFilters = computed(() => {
    return (
      searchQuery.value !== '' ||
      statusFilter.value !== '' ||
      priorityFilter.value !== '' ||
      userFilter.value !== '' ||
      dateFilter.value !== ''
    )
  })

  // Cleaning filters
  const clearFilters = () => {
    searchQuery.value = ''
    statusFilter.value = ''
    priorityFilter.value = ''
    userFilter.value = ''
    dateFilter.value = ''
  }

  // Navigation Details
  const viewTicketDetails = (id: number) => {
    router.push({ name: 'ticket-detail', params: { id: id } })
  }

  // Navigation Create a ticket (opens same modal as sidebar)
  const makeTicket = () => {
    showTicketModal.value = true
  }

  const onTicketCreated = () => {
    showTicketModal.value = false
    fetchTickets()
  }

  const formatTicketSummary = (desc: string) => {
    if (!desc) return { text: '—', hasPhoto: false }
    const hasPhoto = /(https?:\/\/[^\s"'<>]+\.(?:jpg|jpeg|png|webp|gif)|📷\s*Evidencias|Evidencias Fotográficas)/i.test(desc)
    let clean = desc
      .replace(/[📷]?\s*Evidencias Fotográficas Adjuntas:[\s\S]*$/i, '')
      .replace(/-?\s*Foto\s*\d+:?\s*https?:\/\/[^\s]+/gi, '')
      .replace(/(https?:\/\/[^\s"'<>]+\.(?:jpg|jpeg|png|webp|gif)(?:\?[^\s"'<>]*)?)/gi, '')
      .replace(/[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]/gu, '')
      .replace(/[🏢👤📞✉️📋📷📌📍📎🏷️🔍⚡⚠️✅❌]/gu, '')
      .replace(/^[ \t]+/gm, '')
      .trim()
    if (clean.length > 130) {
      clean = clean.slice(0, 130) + '…'
    }
    return {
      text: clean || 'Sin descripción adicional',
      hasPhoto,
    }
  }

  // Status coloring
  const getStatusInfo = (ticket: Ticket) => {
    if (!ticket.atendio || ticket.atendio === '') {
      return { text: 'Sin Asignar', pillClass: 'sin-asignar', borderClass: 'border-rojo' }
    }

    if (ticket.estatus === 'Abierto')
      return { text: 'Abierto', pillClass: 'abierto', borderClass: 'border-rojo' }
    if (ticket.estatus === 'En Proceso')
      return { text: 'En Proceso', pillClass: 'en-proceso', borderClass: 'border-verde' }
    if (ticket.estatus === 'Cerrado')
      return { text: 'Cerrado', pillClass: 'cerrado', borderClass: 'border-gris' }
    if (ticket.estatus === 'Resuelto')
      return { text: 'Resuelto', pillClass: 'resuelto', borderClass: 'border-azul' }

    return { text: ticket.estatus, pillClass: '', borderClass: 'border-gris' }
  }

  // NEW: Priority Badge Logic
  const getPriorityDisplay = (prioridad: string) => {
    if (prioridad === 'Alta') return '!!!'
    if (prioridad === 'Media') return '!!'
    if (prioridad === 'Baja') return '!'
    return ''
  }

  const getPriorityClass = (prioridad: string) => {
    if (prioridad === 'Alta') return 'p-alta'
    if (prioridad === 'Media') return 'p-media'
    if (prioridad === 'Baja') return 'p-baja'
    return ''
  }
</script>

<template>
  <div class="page-container-list-ticket">
    <div class="page-header">
      <h1>Gestión de Tickets</h1>
      <button class="btn-primary" @click="makeTicket">+ Nuevo ticket</button>
    </div>

    <div class="filter-bar">
      <div class="search-box">
        <Search :size="18" class="icon" />
        <input
          type="text"
          v-model="searchQuery"
          placeholder="Buscar ticket por asunto o descripción..."
        />
      </div>

      <div class="filters">
        <div class="filter-item">
          <Filter :size="16" class="icon" />
          <select v-model="statusFilter">
            <option value="">Todos los Estatus</option>
            <option value="Abierto">Abierto</option>
            <option value="En Proceso">En Proceso</option>
            <option value="Resuelto">Resuelto</option>
          </select>
        </div>

        <div class="filter-item">
          <Filter :size="16" class="icon" />
          <select v-model="priorityFilter">
            <option value="">Todas las Prioridades</option>
            <option value="Alta">Alta (!!!)</option>
            <option value="Media">Media (!!)</option>
            <option value="Baja">Baja (!)</option>
          </select>
        </div>

        <div class="filter-item" v-if="currentUser?.rol !== 3">
          <Filter :size="16" class="icon" />
          <select v-model="userFilter">
            <option value="">Todos los Usuarios</option>
            <option value="sin_asignar">Sin asignar</option>
            <option v-for="u in availableUsers" :key="u.id" :value="String(u.id)">
              {{ u.nombre }}
            </option>
          </select>
        </div>

        <div class="filter-item" style="min-width: 200px">
          <DateFilter v-model="dateFilter" />
        </div>

        <button
          v-if="hasActiveFilters"
          @click="clearFilters"
          class="btn-clear"
          title="Limpiar todos los filtros"
        >
          <X :size="16" />
          Limpiar
        </button>
      </div>
    </div>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Ticket</th>
            <th class="col-detalles">Detalles del reporte</th>
            <th>Atendió</th>
            <th>Estatus</th>
            <th></th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="ticket in filteredTickets"
            :key="ticket.id"
            class="ticket-row"
            :class="getStatusInfo(ticket).borderClass"
            @click="viewTicketDetails(ticket.id)"
          >
            <td class="col-ticket">
              <div class="folio">{{ ticket.folio }}</div>
              <div class="date-text">Sol: {{ ticket.fechaSolicitud }}</div>
              <div class="date-text" v-if="ticket.fechaAtencion">
                Atn: {{ ticket.fechaAtencion }}
              </div>
            </td>

            <td class="col-detalles">
              <div class="asunto-container">
                <span
                  class="priority-badge"
                  :class="getPriorityClass(ticket.prioridad)"
                  :title="`Prioridad ${ticket.prioridad}`"
                >
                  {{ getPriorityDisplay(ticket.prioridad) }}
                </span>
                <div class="asunto">{{ ticket.asunto }}</div>
              </div>
              <div class="descripcion">
                <span>{{ formatTicketSummary(ticket.descripcion).text }}</span>
                <span
                  v-if="formatTicketSummary(ticket.descripcion).hasPhoto"
                  style="display: inline-block; margin-left: 6px; padding: 1px 6px; background: #eff6ff; color: #2563eb; border-radius: 4px; font-size: 0.75rem; font-weight: 600;"
                  title="Tiene evidencias fotográficas adjuntas"
                >
                  Foto
                </span>
              </div>
            </td>

            <td class="align-middle">
              <div class="empleado">{{ ticket.atendio || 'Sin asignar' }}</div>
            </td>

            <td class="align-middle">
              <span class="status-indicator" :class="getStatusInfo(ticket).pillClass">
                {{ getStatusInfo(ticket).text }}
              </span>
            </td>

            <td class="align-middle text-right pr-6">
              <ChevronRight :size="20" class="chevron-icon" />
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="filteredTickets.length === 0" class="p-12 text-center text-slate-500 italic">
        No se encontraron tickets con esos filtros...
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="showTicketModal"
        style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;"
      >
        <div
          style="background: white; border-radius: 16px; width: 100%; max-width: 800px; max-height: 90vh; overflow: hidden; position: relative; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); display: flex; flex-direction: column;"
        >
          <div style="overflow-y: auto; flex: 1; padding: 1.5rem;">
            <TicketCreateView
              :isModal="true"
              @close="showTicketModal = false"
              @created="onTicketCreated"
            />
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped lang="scss">
  .border-rojo {
    border-left: 8px solid #ef4444;
  }
  .border-verde {
    border-left: 8px solid #22c55e;
  }
  .border-amarillo {
    border-left: 8px solid #eab308;
  }
  .border-azul {
    border-left: 8px solid #3b82f6;
  }
  .border-gris {
    border-left: 8px solid #94a3b8;
  }

  .status-indicator {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;

    &.sin-asignar {
      background: #fee2e2;
      color: #b91c1c;
    }
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

  .page-container-list-ticket {
    max-width: 1200px;
    margin: 0 auto;
  }

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;

    h1 {
      font-size: 1.8rem;
      font-weight: 700;
      color: #0f172a;
    }

    .btn-primary {
      background: #2563eb;
      color: white;
      padding: 0.6rem 1.2rem;
      border-radius: 8px;
      border: none;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: 0.5s;

      &:hover {
        background: #1d4ed8;
      }
    }
  }

  .table-container {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);

    table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }
  }

  th {
    background: #f8fafc;
    padding: 1rem 1.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: #64748b;
    border-bottom: 1px solid #e2e8f0;
  }

  td {
    padding: 1.2rem 1.5rem;
    vertical-align: top;
  }

  .col-detalles {
    width: 50%;
    min-width: 400px;
  }

  .col-ticket {
    min-width: 140px;
  }

  .ticket-row {
    transition: all 0.2s ease;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;

    &:hover {
      background-color: #f8fafc;

      .chevron-icon {
        color: #3b82f6;
        transform: translateX(4px);
      }
    }
  }

  .folio {
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 4px;
    font-size: 0.95rem;
  }

  .date-text {
    font-size: 0.75rem;
    color: #64748b;
    line-height: 1.4;
  }

  /* --- NEW: Asunto Layout with Badges --- */
  .asunto-container {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
  }

  .asunto {
    font-weight: 600;
    color: #1e293b;
    font-size: 1rem;
    margin-bottom: 0; /* Margin moved to container */
  }

  .priority-badge {
    font-weight: 900;
    font-size: 0.85rem;
    padding: 2px 6px;
    border-radius: 6px;
    letter-spacing: 1px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px; /* Keeps the badges aligned nicely */

    &.p-alta {
      color: #ef4444;
      background: #fee2e2;
    }
    &.p-media {
      color: #f97316;
      background: #ffedd5;
    }
    &.p-baja {
      color: #64748b;
      background: #f1f5f9;
    }
  }
  /* -------------------------------------- */

  .descripcion {
    font-size: 0.85rem;
    color: #475569;
    line-height: 1.5;
  }

  .empleado {
    font-size: 0.9rem;
    color: #334155;
    font-weight: 500;
  }

  .filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 1rem;
    flex-wrap: wrap;
  }

  .search-box {
    position: relative;
    flex: 1;
    min-width: 300px;

    .icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
    }

    input {
      width: 100%;
      box-sizing: border-box;
      padding: 0.6rem 1rem 0.6rem 2.5rem;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      background: white;
      color: #1e293b;
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.2s;

      &:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
      }
    }
  }

  .filters {
    display: flex;
    gap: 1rem;
  }

  .filter-item {
    position: relative;
    display: flex;
    align-items: center;

    .icon {
      position: absolute;
      left: 10px;
      color: #64748b;
      pointer-events: none;
    }

    select,
    input[type='date'] {
      box-sizing: border-box;
      padding: 0.6rem 1rem;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      background: white;
      font-size: 0.9rem;
      color: #334155;
      outline: none;
      cursor: pointer;
    }

    select {
      padding-left: 2.2rem;
    }
  }

  .chevron-icon {
    color: #cbd5e1;
    transition: all 0.2s ease;
  }

  .btn-clear {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0.6rem 1rem;
    background: #f1f5f9;
    color: #64748b;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;

    &:hover {
      background: #e2e8f0;
      color: #0f172a;
    }
  }

  @media (max-width: 768px) {
    .page-header {
      flex-direction: column;
      align-items: stretch;
      gap: 1rem;
    }
    .page-header button {
      width: 100%;
    }
    .filter-bar {
      display: flex;
      flex-direction: column;
      align-items: stretch;
      width: 100%;
      gap: 1rem;
    }
    .filters,
    .search-box {
      display: flex;
      flex-direction: column;
      align-items: stretch;
      width: 100%;
      gap: 1rem;
    }
    .filter-item,
    .filter-item select,
    .filter-item input,
    .search-box input,
    .btn-clear {
      width: 100%;
    }
    .table-container {
      background: transparent;
      border: none;
      box-shadow: none;
    }
    table,
    thead,
    tbody,
    th,
    td,
    tr {
      display: block;
    }
    thead {
      display: none;
    }
    .ticket-row {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 0.5rem;
      padding: 1rem;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      margin-bottom: 1rem;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      background: white;
    }
    td {
      padding: 0;
      width: 100%;
      display: flex;
      flex-direction: column;
    }
    .col-detalles {
      min-width: unset;
      width: 100%;
    }
    .chevron-icon {
      display: none;
    }
  }
</style>
