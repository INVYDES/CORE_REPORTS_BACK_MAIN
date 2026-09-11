<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/authStore'
import { storeToRefs } from 'pinia'
import { dashboardApi } from '../../api/dashboard'
import client from '../../api/client'
import { AlertOctagon, CalendarClock, Clock, FileText, PlusCircle, CheckCircle2, Ticket, Activity } from 'lucide-vue-next'

// --- Normalize lowercase API enums into UI labels/colors ---
const statusLabel = (code?: string | null) => ({
    abierto: 'Abierto', en_proceso: 'En Proceso', resuelto: 'Resuelto', cerrado: 'Cerrado',
    programado: 'Programado', realizado: 'Realizado', vencido: 'Vencido',
    parcial: 'Parcial', finalizado: 'Finalizado', descartado: 'Descartado',
} as any)[code || ''] || code || ''
const reportStatusLabel = (code?: string | null) => ({
    abierto: 'Abierto', parcial: 'Parcial', finalizado: 'Finalizado', descartado: 'Descartado',
} as any)[code || ''] || code || ''
const priorityLabel = (code?: string | null) => ({ alta: 'Alta', media: 'Media', baja: 'Baja' } as any)[code || ''] || code || ''
const tipoLabel = (code?: string | null) => ({ ticket: 'Ticket', servicio: 'Servicio', libre: 'Libre' } as any)[code || ''] || code || ''
const fmtShort = (iso?: string | null) => (iso ? String(iso).replace('T', ' ').slice(0, 16) : '')

const router = useRouter()
const authStore = useAuthStore()
const { currentUser } = storeToRefs(authStore)

const unassignedTickets = ref<any[]>([])
const upcomingServices = ref<any[]>([])
const recentReports = ref<any[]>([])
const myPendingTasks = ref<any[]>([])
const myRecentReports = ref<any[]>([])

onMounted(async () => {
    try {
        const dash = await dashboardApi.get()
        unassignedTickets.value = (dash.tickets_por_asignar || []).slice(0,5).map((t:any)=> ({ id: t.id, folio: t.folio, asunto: t.asunto, prioridad: priorityLabel(t.prioridad), estatus: statusLabel(t.estatus) }))
        upcomingServices.value = (dash.servicios_proximos || []).slice(0,5).map((s:any)=> ({ id: s.id, folio: s.folio, asunto: s.asunto, estatus: statusLabel(s.estatus), prioridad: priorityLabel(s.prioridad), fechaVencimiento: fmtShort(s.fecha_vencimiento) }))
        recentReports.value = (dash.actividad_reciente || []).slice(0,5).map((r:any)=> ({ idReporte: r.id, folio: r.folio, tipoReporte: tipoLabel(r.tipo), estatusReporte: reportStatusLabel(r.estatus), fechaElaboracion: fmtShort(r.fecha_inicio) }))
        if ((currentUser.value as any)?.rol === 3) {
            const uid = (currentUser.value as any)?.id
            const [tRes, sRes, rRes] = await Promise.all([
                client.get('/tickets', { params: { per_page: 100 } }).then(r=>r.data).catch(()=>({data:[]})),
                client.get('/servicios', { params: { per_page: 100 } }).then(r=>r.data).catch(()=>({data:[]})),
                client.get('/reportes', { params: { per_page: 100 } }).then(r=>r.data).catch(()=>({data:[]})),
            ])
            const tList = tRes.data || tRes || []
            const sList = sRes.data || sRes || []
            const rList = rRes.data || rRes || []
            const myTix = (Array.isArray(tList)?tList:[]).filter((t:any)=> t.usuario_asignado_id===uid && !['resuelto','cerrado'].includes(t.estatus)).map((t:any)=> ({ id:t.id, type:'Ticket', folio:t.folio, asunto:t.asunto, estatus:statusLabel(t.estatus), prioridad:priorityLabel(t.prioridad), date:fmtShort(t.fecha_solicitud), route:'ticket-detail' }))
            const mySvc = (Array.isArray(sList)?sList:[]).filter((s:any)=> s.usuario_asignado_id===uid && !['realizado'].includes(s.estatus)).map((s:any)=> ({ id:s.id, type:'Servicio', folio:s.folio, asunto:s.asunto, estatus:statusLabel(s.estatus), prioridad:priorityLabel(s.prioridad), date:fmtShort(s.fecha_vencimiento), route:'service-detail' }))
            myPendingTasks.value = [...myTix, ...mySvc].sort((a:any,b:any)=> new Date(a.date.replace(' ', 'T') || b.date).getTime()-new Date(b.date.replace(' ', 'T') || a.date).getTime())
            myRecentReports.value = (Array.isArray(rList)?rList:[]).filter((r:any)=> r.creado_por===uid).slice(0,5).map((r:any)=> ({ idReporte:r.id, folio:r.folio, estatusReporte:reportStatusLabel(r.estatus), fechaElaboracion:fmtShort(r.fecha_inicio) }))
        }
    } catch {}
})

const navigateTo = (routeName: string, params?: any) => { router.push({ name: routeName, params }) }
const getPriorityClass = (priority: string) => {
    switch (priority) {
        case 'Crítica': return 'bg-red-100 text-red-700'
        case 'Alta': return 'bg-orange-100 text-orange-700'
        case 'Media': return 'bg-yellow-100 text-yellow-700'
        case 'Baja': return 'bg-green-100 text-green-700'
        default: return 'bg-slate-100 text-slate-700'
    }
}
const getStatusClass = (status: string) => {
    if (status === 'Abierto' || status === 'Programado') return 'bg-blue-100 text-blue-700'
    if (status === 'En Proceso' || status === 'En Progreso') return 'bg-purple-100 text-purple-700'
    if (status === 'Vencido') return 'bg-red-100 text-red-700'
    return 'bg-emerald-100 text-emerald-700'
}
</script>

<template>
    <div class="dashboard-root px-4 mx-auto w-full max-w-7xl" v-if="currentUser">
        
        <!-- ========================================== -->
        <!-- ADMIN VIEW (Roles 1 & 2)                  -->
        <!-- ========================================== -->
        <div v-if="currentUser.rol === 1 || currentUser.rol === 2" class="admin-dashboard">
            <div class="header-section">
                <h2>Centro de Control</h2>
                <p class="text-slate-500 mt-1">Visión general de la operación de {{ currentUser.nombre }}</p>
            </div>

            <div class="grid-3-col">
                <!-- COL 1: Tickets por Asignar -->
                <div class="dash-card">
                    <div class="card-header border-b-red">
                        <AlertOctagon class="text-red-500" :size="20"/>
                        <h3>Tickets por Asignar</h3>
                        <span class="badge">{{ unassignedTickets.length }}</span>
                    </div>
                    <div class="card-body">
                        <div v-if="unassignedTickets.length === 0" class="empty-state">
                            <CheckCircle2 :size="32" class="text-emerald-400 mb-2"/>
                            <p>Todos los tickets están asignados.</p>
                        </div>
                        <div v-else class="list-container">
                            <div 
                                v-for="ticket in unassignedTickets" 
                                :key="ticket.id" 
                                class="list-item"
                                @click="navigateTo('ticket-detail', { id: ticket.id })"
                            >
                                <div class="item-main">
                                    <span class="folio">{{ ticket.folio }}</span>
                                    <span class="subject">{{ ticket.asunto }}</span>
                                </div>
                                <div class="item-meta">
                                    <span class="tag" :class="getPriorityClass(ticket.prioridad)">{{ ticket.prioridad }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button v-if="currentUser?.rol === 1 || (currentUser?.rol as number) === 3" class="text-blue-600 font-medium text-sm hover:underline" @click="navigateTo('tickets')">Ver todos los tickets &rarr;</button>
                    </div>
                </div>

                <!-- COL 2: Servicios Próximos -->
                <div class="dash-card">
                    <div class="card-header border-b-blue">
                        <CalendarClock class="text-blue-500" :size="20"/>
                        <h3>Servicios Próximos</h3>
                    </div>
                    <div class="card-body">
                        <div v-if="upcomingServices.length === 0" class="empty-state">
                            <CalendarClock :size="32" class="text-slate-300 mb-2"/>
                            <p>No hay servicios próximos.</p>
                        </div>
                        <div v-else class="list-container">
                            <div 
                                v-for="service in upcomingServices" 
                                :key="service.id" 
                                class="list-item"
                                @click="navigateTo('service-detail', { id: service.id })"
                            >
                                <div class="item-main">
                                    <span class="folio">{{ service.folio }}</span>
                                    <span class="subject">{{ service.asunto }}</span>
                                </div>
                                <div class="item-meta flex-col items-end gap-1">
                                    <span class="text-xs font-semibold text-slate-500">{{ service.fechaVencimiento }}</span>
                                    <span class="tag" :class="getStatusClass(service.estatus)">{{ service.estatus }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button v-if="currentUser?.rol === 1 || (currentUser?.rol as number) === 3" class="text-blue-600 font-medium text-sm hover:underline" @click="navigateTo('schedule-list')">Ver cronograma &rarr;</button>
                    </div>
                </div>

                <!-- COL 3: Actividad Reciente -->
                <div class="dash-card">
                    <div class="card-header border-b-emerald">
                        <Activity class="text-emerald-500" :size="20"/>
                        <h3>Actividad Reciente (Reportes)</h3>
                    </div>
                    <div class="card-body">
                        <div v-if="recentReports.length === 0" class="empty-state">
                            <FileText :size="32" class="text-slate-300 mb-2"/>
                            <p>No hay reportes recientes.</p>
                        </div>
                        <div v-else class="timeline-container">
                            <div 
                                v-for="report in recentReports" 
                                :key="report.idReporte" 
                                class="timeline-item"
                                @click="navigateTo('report-detail', { id: report.idReporte })"
                            >
                                <div class="timeline-dot"></div>
                                <div class="timeline-content">
                                    <div class="flex justify-between w-full">
                                        <span class="font-semibold text-sm text-slate-800">{{ report.folio }}</span>
                                        <span class="text-xs text-slate-400">{{ report.fechaElaboracion }}</span>
                                    </div>
                                    <span class="text-xs text-slate-500 mt-1 block">Tipo: {{ report.tipoReporte }}</span>
                                    <span class="text-xs text-slate-500 block">Estatus: {{ report.estatusReporte }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button v-if="currentUser?.rol === 1 || (currentUser?.rol as number) === 3" class="text-blue-600 font-medium text-sm hover:underline" @click="navigateTo('reports')">Ir a reportes &rarr;</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TECHNICIAN VIEW (Role 3)                  -->
        <!-- ========================================== -->
        <div v-else-if="currentUser.rol === 3" class="tech-dashboard">
            <div class="tech-header flex justify-between items-center mb-6">
                <div>
                    <h2>Mi Día de Trabajo</h2>
                    <p class="text-slate-500 mt-1">Bienvenido, {{ currentUser.nombre }}</p>
                </div>
                <!-- Quick Action -->
                <button class="btn-primary-large" @click="navigateTo('reports-new')">
                    <PlusCircle :size="20"/>
                    Nuevo Reporte
                </button>
            </div>

            <div class="grid-2-col">
                <!-- COL 1: Pendientes -->
                <div class="dash-card">
                    <div class="card-header border-b-indigo">
                        <Clock class="text-indigo-500" :size="20"/>
                        <h3>Mis Tareas Pendientes</h3>
                        <span class="badge bg-indigo-100 text-indigo-700">{{ myPendingTasks.length }}</span>
                    </div>
                    <div class="card-body">
                        <div v-if="myPendingTasks.length === 0" class="empty-state">
                            <CheckCircle2 :size="40" class="text-indigo-300 mb-3"/>
                            <p class="text-lg font-medium text-slate-600">¡Al día!</p>
                            <p class="text-sm">No tienes tareas pendientes asignadas.</p>
                        </div>
                        <div v-else class="list-container">
                            <div 
                                v-for="task in myPendingTasks" 
                                :key="task.id + task.type" 
                                class="list-item large-item"
                                @click="navigateTo(task.route, { id: task.id })"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="icon-box" :class="task.type === 'Ticket' ? 'bg-orange-100 text-orange-600' : 'bg-blue-100 text-blue-600'">
                                        <Ticket v-if="task.type === 'Ticket'" :size="18"/>
                                        <CalendarClock v-else :size="18"/>
                                    </div>
                                    <div class="item-main">
                                        <span class="folio">{{ task.folio }}</span>
                                        <span class="subject clamp-1">{{ task.asunto }}</span>
                                        <span class="text-xs text-slate-400 mt-1 flex items-center gap-1">Vence: {{ task.date }}</span>
                                    </div>
                                </div>
                                <div class="item-meta flex-col items-end gap-2">
                                    <span class="tag" :class="getStatusClass(task.estatus)">{{ task.estatus }}</span>
                                    <span class="tag" :class="getPriorityClass(task.prioridad)">{{ task.prioridad }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COL 2: Últimos Reportes -->
                <div class="dash-card">
                    <div class="card-header border-b-emerald">
                        <FileText class="text-emerald-500" :size="20"/>
                        <h3>Mis Últimos Reportes Generados</h3>
                    </div>
                    <div class="card-body">
                         <div v-if="myRecentReports.length === 0" class="empty-state">
                            <FileText :size="40" class="text-slate-300 mb-3"/>
                            <p>Aún no has generado reportes.</p>
                        </div>
                        <div v-else class="list-container">
                            <div 
                                v-for="report in myRecentReports" 
                                :key="report.idReporte" 
                                class="list-item"
                                @click="navigateTo('report-detail', { id: report.idReporte })"
                            >
                                <div class="item-main">
                                    <span class="folio text-emerald-700">{{ report.folio }}</span>
                                    <span class="subject mt-1 flex items-center gap-2">
                                        <Activity :size="14" class="text-slate-400"/>
                                        {{ report.estatusReporte }}
                                    </span>
                                </div>
                                <div class="item-meta">
                                    <span class="text-sm font-medium text-slate-500">{{ report.fechaElaboracion }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-slate-50">
                        <button class="text-emerald-600 font-medium text-sm hover:underline w-full text-center" @click="navigateTo('reports')">Ver mi historial completo</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.dashboard-root {
    font-family: system-ui, -apple-system, sans-serif;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.header-section {
    margin-bottom: 2rem;
}
h2 {
    color: #0f172a;
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
}

/* Grids */
.grid-3-col {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.grid-2-col {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .grid-2-col {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .grid-3-col {
        grid-template-columns: 1fr;
    }
}

/* Base Card */
.dash-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    height: 500px; /* Fixed height for consistency */
}

.card-header {
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.card-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    flex-grow: 1;
}
.border-b-red { border-bottom: 2px solid #ef4444; }
.border-b-blue { border-bottom: 2px solid #3b82f6; }
.border-b-emerald { border-bottom: 2px solid #10b981; }
.border-b-indigo { border-bottom: 2px solid #6366f1; }

.badge {
    background: #fee2e2;
    color: #b91c1c;
    padding: 0.15rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

.card-body {
    flex-grow: 1;
    overflow-y: auto;
    padding: 0;
}

.card-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e2e8f0;
    background: #ffffff;
}

/* Empty State */
.empty-state {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    text-align: center;
    padding: 2rem;
}

/* List Items */
.list-container {
    display: flex;
    flex-direction: column;
}

.list-item {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: background 0.2s;
}
.list-item:hover {
    background: #f8fafc;
}
.list-item:last-child {
    border-bottom: none;
}

.item-main {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.folio {
    font-weight: 700;
    font-size: 0.9rem;
    color: #475569;
}

.subject {
    font-size: 0.95rem;
    font-weight: 500;
    color: #0f172a;
    line-height: 1.2;
}
.clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.item-meta {
    display: flex;
    align-items: center;
}

.tag {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 4px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

/* Timeline specific */
.timeline-container {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.timeline-item {
    display: flex;
    gap: 1rem;
    cursor: pointer;
    position: relative;
    padding-left: 0.5rem;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: 17px;
    top: 24px;
    bottom: -24px;
    width: 2px;
    background: #e2e8f0;
}
.timeline-item:last-child::before {
    display: none;
}
.timeline-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #10b981;
    margin-top: 4px;
    z-index: 1;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #10b981;
}
.timeline-content {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 1rem;
    border-radius: 8px;
    flex-grow: 1;
    transition: transform 0.2s, box-shadow 0.2s;
}
.timeline-item:hover .timeline-content {
    transform: translateX(4px);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    background: #ffffff;
    border-color: #cbd5e1;
}

/* Technician specific */
.btn-primary-large {
    background: #2563eb;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.btn-primary-large:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
}

.icon-box {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.large-item {
    padding: 1.5rem;
}
</style>