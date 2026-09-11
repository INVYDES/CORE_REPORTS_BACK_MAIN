<script setup lang="ts">
// @ts-nocheck
    import { ref, onMounted, computed } from 'vue'
    import { useRouter } from 'vue-router'

    // Import session
    import { useAuthStore } from '../../../stores/authStore';
    import { storeToRefs } from 'pinia';
    
    // Store
    const authStore = useAuthStore();
    const { currentUser } = storeToRefs(authStore);

import client from '../../../api/client'
    import { Star } from 'lucide-vue-next';

    const router = useRouter()

    // Role-based access: only tech roles (1: Admin Técnico, 3: Técnico) can view reports
    if (!currentUser.value || (currentUser.value?.rol !== 1 && currentUser.value?.rol !== 3)) {
        router.push({ name: 'dashboard' })
    }

    const searchQuery = ref('')
    const filterTipo = ref('Todos') // 'Todos', 'Ticket', 'Servicio Programado'
    const dateFilter = ref('')

    const reports = ref<any[]>([])
    const users = ref<any[]>([])
    const surveyIds = ref<Set<number>>(new Set())

    const tipoLabel = (code: string) => ({ ticket: 'Ticket', servicio: 'Servicio Programado', libre: 'Libre' } as any)[code] || code
    const estatusLabel = (code: string) => ({ finalizado: 'Cerrado / Trabajo Finalizado', abierto: 'Abierto / Trabajo Parcial', parcial: 'Abierto / Trabajo Parcial', descartado: 'Descartado / Cancelado' } as any)[code] || code
    const fmtShort = (iso?: string | null) => (iso ? String(iso).replace('T', ' ').slice(0, 16) : '')

    const getUserName = (id: number | null | undefined) => {
        if (!id) return 'Sin Asignar'
        const u = users.value.find((x: any) => x.id === id)
        return u ? `${u.nombre} ${u.apellidos}`.trim() : 'Usuario Desconocido'
    }
    const userName = getUserName

    // Get the Title/Folio of the parent Ticket or Service (resuelto en el fetch con datos reales)
    const getOrigenDetails = (reporte: any) => {
        if (reporte.tipoReporte === 'Libre' || !reporte.origenDatosId) {
            return { folio: 'N/A', asunto: ' Reporte Independiente / Libre ' }
        }
        if (reporte.tipoReporte === 'Ticket') {
            return reporte.originFolio
                ? { folio: `Ticket: ${reporte.originFolio}`, asunto: reporte.originAsunto }
                : { folio: 'N/A', asunto: 'Ticket no encontrado' }
        }
        if (reporte.tipoReporte === 'Servicio Programado') {
            return reporte.originFolio
                ? { folio: `Servicio: ${reporte.originFolio}`, asunto: reporte.originAsunto }
                : { folio: 'N/A', asunto: 'Servicio no encontrado' }
        }
        return { folio: '---', asunto: 'Tipo de origen desconocido' }
    }

    // Check if the report has a customer survey
    const hasSurvey = (reporteId: number) => {
        return surveyIds.value.has(reporteId)
    }

    // Form date for display
    const formatDate = (dateString: string) => {
        if (!dateString) return '---'
        const date = new Date(dateString)
        return date.toLocaleDateString('es-MX', { year: 'numeric', month: '2-digit', day: '2-digit' })
    }

    const loadReports = async () => {
        try {
            const res = await client.get('/reportes', { params: { per_page: 100 } }).then(r => r.data)
            const list = res.data || res || []
            reports.value = (Array.isArray(list) ? list : []).map((x: any) => {
                const origen = x.ticket || x.servicio || null
                return {
                    idReporte: x.id,
                    folio: x.folio,
                    idDependencia: x.dependencia_id,
                    creadoPor: x.creado_por,
                    tipoReporte: tipoLabel(x.tipo),
                    origenDatosId: x.ticket_id ?? x.servicio_id ?? null,
                    originFolio: origen?.folio || '',
                    originAsunto: origen?.asunto || '',
                    fechaElaboracion: fmtShort(x.fecha_inicio),
                    desarrolloActividades: x.desarrollo || '',
                    estatusReporte: estatusLabel(x.estatus),
                }
            })
        } catch {}
    }

    const loadUsers = async () => {
        try {
            const res = await client.get('/usuarios', { params: { per_page: 100 } }).then(r => r.data)
            const list = res.data || res || []
            if (Array.isArray(list)) users.value = list
        } catch {}
    }

    const loadSurveys = async () => {
        try {
            const res = await client.get('/encuestas', { params: { per_page: 100 } }).then(r => r.data)
            const list = res.data || res || []
            if (Array.isArray(list)) surveyIds.value = new Set(list.map((e: any) => e.reporte_id))
        } catch {}
    }

    onMounted(() => { loadReports(); loadUsers(); loadSurveys() })

    // Filter Engine
    const filteredReports = computed(() => {
        const depId = (currentUser.value as any)?.dependencia_id ?? (currentUser.value as any)?.idDependencia
        return reports.value
        // Multi-tenant security: only show reports for this enterprise
        .filter(r => r.idDependencia === undefined || depId === undefined || r.idDependencia === depId)
        // Filter by type dropdown
        .filter(r => filterTipo.value === 'Todos' || r.tipoReporte === filterTipo.value)
        // Search bar (Search by Report Folio or origin folio)
        .filter(r => {
            if (!searchQuery.value) return true
            const query = searchQuery.value.toLowerCase()
            const folioMatch = (r.folio || '').toLowerCase().includes(query)
            const originDetails = getOrigenDetails(r)
            const originMatch = (originDetails?.folio || '').toLowerCase().includes(query)
            return folioMatch || originMatch
        })
        // Filter by Date
        .filter(r => {
            if (!dateFilter.value) return true
            return (r.fechaElaboracion || '').startsWith(dateFilter.value)
        })
        .sort((a: any, b: any) => new Date(b.fechaElaboracion).getTime() - new Date(a.fechaElaboracion).getTime())
    })

</script>
<!------------------------------------------------------------------------------------------------------------------>
<template>
    <div class="page-container-report-list">

        <!-- Header -->
        <div class="page-header">
            
            <!-- Header title-->
            <h2>
                Gestión de Reportes
            </h2>
        </div>

        <!-- Filter Rows-->
        <div class="filters-row">
            <!-- Search box -->
            <div class="search-box">
                
                <!-- Search Icon-->
                <span class="search-icon">🔍</span>

                <!-- Input search box -->
                <input 
                    type="text" 
                    v-model="searchQuery" 
                    placeholder="Buscar reporte por folio o asunto..." 
                />
                
            </div>
            
            <!-- Date Filter -->
            <DateFilter v-model="dateFilter" style="max-width: 200px;" />
        
        <!-- Dropdown List Filter Type-->
            <select v-model="filterTipo" class="filter-dropdown">
                <option value="Todos">Todos los Tipos</option>
                <option value="Ticket">Tickets</option>
                <option value="Servicio Programado">Servicios Programados</option>
                <option value="Libre">Libres</option>
            </select>
        </div>

        <!-- List card-->
        <div class="list-card">

            <!-- Table cols-->
            <div class="list-header">
                <div class="col-folio">REPORTE</div>
                <div class="col-details">DETALLES DEL REPORTE</div>
                <div class="col-user">ELABORÓ</div>
                <div class="col-status text-center">ESTATUS</div>
                <div class="col-action"></div>
            </div>

            <!-- Filtered Reports: Empty State -->
            <div v-if="filteredReports.length === 0" class="empty-state">
                <p>No se encontraron reportes con los filtros actuales.</p>
            </div>

            <!-- Table Filtered reports-->
            <div 
                v-for="report in filteredReports" 
                :key="report.idReporte" 
                class="list-item cursor-pointer"
                @click="router.push({ name: 'report-detail', params: { id: report.idReporte } })"
            >
                <!-- Col Folio -->
                <div class="col-folio">
                    <span class="font-semibold text-slate-900">{{ report.folio }}</span>
                    <span class="text-sm text-slate-500 mt-1 block">Elab: {{ formatDate(report.fechaElaboracion) }}</span>
                </div>

                <!-- Col details -->
                <div class="col-details">

                    <div class="details-title">
                        <span 
                            class="badge-origin" 
                            :class="{
                                'bg-ticket': report.tipoReporte === 'Ticket',
                                'bg-servicio': report.tipoReporte === 'Servicio Programado',
                                'bg-libre': report.tipoReporte === 'Libre'
                            }"
                        >
                            {{ report.tipoReporte === 'Libre' ? 'Libre' : getOrigenDetails(report).folio }}
                        </span>
                        <p></p>
                        <span class="font-semibold text-slate-900 ml-2">{{ getOrigenDetails(report).asunto }}</span>
                    </div>

                    <!-- Activities decription -->
                    <p class="text-sm text-slate-500 mt-1 block line-clamp-2 m-0">
                        {{ report.desarrolloActividades || 'Sin descripción de actividades.' }}
                    </p>

                </div>


                <!-- Creado por col-->
                <div class="col-user">
                    <span class="text-sm text-slate-900">{{ getUserName(report.creadoPor) }}</span>
                </div>

                <!-- Col Status-->
                <div class="col-status flex justify-center items-center gap-2">
                    <!-- Pill Status -->
                    <span 
                        class="status-pill text-center leading-tight"
                        :class="report.estatusReporte === 'Cerrado / Trabajo Finalizado' ? 'pill-success' : 'pill-warning'"
                    >
                        {{ report.estatusReporte === 'Cerrado / Trabajo Finalizado' ? 'FINALIZADO' : 'TRABAJO PARCIAL' }}
                    </span>
                    <!-- Survey Indicator -->
                    <Star 
                        :size="18" 
                        class="flex-shrink-0"
                        :class="hasSurvey(report.idReporte) ? 'text-amber-400' : 'text-slate-300'" 
                        :fill="hasSurvey(report.idReporte) ? 'currentColor' : 'none'"
                        :title="hasSurvey(report.idReporte) ? 'Evaluación Completada' : 'Evaluación Pendiente'"
                    />
                </div>

                <!-- Wtf is this? xd I don't remember it ahdashas-->
                <div class="col-action flex justify-center items-center">
                    <button @click="router.push({ name: 'report-detail', params: { id: report.idReporte } })" class="bg-transparent border-none text-2xl text-slate-400 cursor-pointer transition hover:text-blue-500 p-0" title="Ver detalles">›</button>
                </div>


            </div>
        </div>
    </div>
</template>
<!------------------------------------------------------------------------------------------------------------------>
<style scoped>
/* Main Container & Header */
.page-container-report-list { padding: 2rem; max-width: 1200px; margin: 0 auto; font-family: system-ui, -apple-system, sans-serif; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
.page-header h2 { margin: 0; color: #0f172a; font-size: 1.75rem; }

/* Filters */
.filters-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
.search-box { flex-grow: 1; position: relative; display: flex; align-items: center; }
.search-icon { position: absolute; left: 1rem; color: #94a3b8; }
.search-box input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; border: 1px solid #e2e8f0; border-radius: 6px; background: white; color: #1e293b; outline: none; transition: border-color 0.2s; }
.search-box input:focus { border-color: #3b82f6; }
.filter-dropdown { padding: 0.6rem 2rem 0.6rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px; background: white; outline: none; cursor: pointer; color: #475569; }

/* Main Card Container */
.list-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }

/* Grid Layout for Rows */
.list-header, .list-item { 
    display: grid; 
    grid-template-columns: 150px 1fr 180px 150px 40px; 
    gap: 1rem; 
    padding: 1rem 1.5rem; 
}
.list-header { background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 0.75rem; font-weight: 700; color: #64748b; letter-spacing: 0.05em; }
.list-item { border-bottom: 1px solid #e2e8f0; align-items: center; position: relative; }
.list-item:last-child { border-bottom: none; }
.list-item:hover { background: #f8fafc; }

/* Left Red Accent Line (Like your design) */
.list-item::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: transparent; transition: 0.2s; }
.list-item:hover::before { background: #ef4444; } /* Red accent on hover */



/* Badges and Pills */
.badge-origin { padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.bg-ticket { background: #fee2e2; color: #641515; } /* Soft red */
.bg-servicio { background: #e0e7ff; color: #4f46e5; } /* Soft indigo */
.bg-libre { background: #f1f5f9; color: #475569; } /* Soft gray */

.status-pill { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.05em; }
.pill-warning { background: #fee2e2; color: #ef4444; } /* Reddish for pending/parcial */
.pill-success { background: #dcfce3; color: #166534; } /* Green for finalized */

.empty-state { padding: 3rem; text-align: center; color: #64748b; }

@media (max-width: 768px) {
    .list-header {
        display: none;
    }
    .list-item {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        margin-bottom: 1rem;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }
    .list-card {
        background: transparent;
        border: none;
        box-shadow: none;
    }
    .col-folio, .col-details, .col-user, .col-status {
        width: 100%;
        text-align: left;
    }
    .col-status {
        justify-content: flex-start;
    }
    .col-action {
        display: none;
    }
    .page-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    .page-header button {
        width: 100%;
    }
    .filters-row {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        gap: 1rem;
    }
    .search-box, .search-box input, .filter-dropdown {
        width: 100%;
    }
}
</style>