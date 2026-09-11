<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/authStore'
import { storeToRefs } from 'pinia'
import client from '../../api/client'
import { onMounted } from 'vue'
import {
    ChevronLeft,
    ChevronRight,
    FileText,
    CalendarClock,
    Ticket,
    CheckCircle2,
    CalendarDays
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const { currentUser } = storeToRefs(authStore)

if (!currentUser.value || (currentUser.value?.rol !== 1 && currentUser.value?.rol !== 3)) {
    router.replace({ name: 'dashboard' })
}

const idDep = (currentUser.value as any)?.idDependencia ?? (currentUser.value as any)?.dependencia_id
const rawTickets = ref<any[]>([])
const rawServices = ref<any[]>([])
const rawReportes = ref<any[]>([])
const rawUsers = ref<any[]>([])
onMounted(async ()=>{
    try {
        const [tRes, sRes, rRes, uRes] = await Promise.all([
            client.get('/tickets', { params:{ per_page:100 } }).then(r=>r.data).catch(()=>({data:[]})),
            client.get('/servicios', { params:{ per_page:100 } }).then(r=>r.data).catch(()=>({data:[]})),
            client.get('/reportes', { params:{ per_page:100 } }).then(r=>r.data).catch(()=>({data:[]})),
            client.get('/usuarios', { params:{ per_page:100 } }).then(r=>r.data).catch(()=>({data:[]})),
        ])
        rawTickets.value = tRes.data || tRes || []
        rawServices.value = sRes.data || sRes || []
        rawReportes.value = rRes.data || rRes || []
        rawUsers.value = uRes.data || uRes || []
    } catch {}
})

// State for calendar
const currentDate = ref(new Date()) // Currently displayed month
const today = new Date() // Today's actual date

// State for selection
const selectedDate = ref<Date>(today)
const activeCategory = ref<'Reportes' | 'Servicios' | 'Tickets'>('Reportes')

// Month navigation
const prevMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
}
const nextMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
}
const goToToday = () => {
    currentDate.value = new Date()
    selectedDate.value = new Date()
}

const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"]
const currentMonthName = computed(() => monthNames[currentDate.value.getMonth()])
const currentYear = computed(() => currentDate.value.getFullYear())

const selectDate = (date: Date) => {
    selectedDate.value = date
}

const isSameDay = (d1: Date, d2: Date) => {
    return d1.getFullYear() === d2.getFullYear() &&
           d1.getMonth() === d2.getMonth() &&
           d1.getDate() === d2.getDate()
}

// Generate calendar grid
const daysInMonth = computed(() => {
    const year = currentDate.value.getFullYear()
    const month = currentDate.value.getMonth()
    const firstDay = new Date(year, month, 1)
    const lastDay = new Date(year, month + 1, 0)
    
    const days = []
    
    // Add padding days from previous month to align first day to Monday
    let firstDayIndex = firstDay.getDay() - 1
    if (firstDayIndex === -1) firstDayIndex = 6 // Sunday is 6
    
    for (let i = 0; i < firstDayIndex; i++) {
        days.push({ date: new Date(year, month, -firstDayIndex + i + 1), isCurrentMonth: false })
    }
    
    // Add days of current month
    for (let i = 1; i <= lastDay.getDate(); i++) {
        days.push({ date: new Date(year, month, i), isCurrentMonth: true })
    }
    
    // Add padding days for next month to complete the grid (up to 42 days)
    const remainingDays = 42 - days.length
    for (let i = 1; i <= remainingDays; i++) {
        days.push({ date: new Date(year, month + 1, i), isCurrentMonth: false })
    }
    
    return days
})

// Define items format
interface HistoryItem {
    id: number
    folio: string
    worker: string
    description: string
    status: string
    type: 'Reporte' | 'Servicio' | 'Ticket'
    route: string
    dateStr: string // YYYY-MM-DD
}

// Filter and map all data globally for this user's enterprise
const allTickets = computed<HistoryItem[]>(() => {
    return rawTickets.value
        .filter((t:any) => (t.dependencia_id ?? t.idDependencia) === idDep)
        .map(t => ({
            id: t.id,
            folio: t.folio,
            worker: t.atendio || t.solicitante || t.usuario_asignado_id || '',
            description: t.asunto,
            status: t.estatus,
            type: 'Ticket',
            route: 'ticket-detail',
            dateStr: (t.fecha_solicitud || t.fechaSolicitud) as string
        }))
})

const allServices = computed<HistoryItem[]>(() => {
    return rawServices.value
        .filter((s:any) => (s.dependencia_id ?? s.idDependencia) === idDep)
        .map(s => ({
            id: s.id,
            folio: s.folio,
            worker: s.asignadoA || s.usuario_asignado_id || 'Sin asignar',
            description: s.asunto,
            status: s.estatus,
            type: 'Servicio',
            route: 'service-detail',
            dateStr: (s.fecha_vencimiento || s.fechaVencimiento) as string
        }))
})

const allReports = computed<HistoryItem[]>(() => {
    return rawReportes.value
        .filter((r:any) => (r.dependencia_id ?? r.idDependencia) === idDep)
        .map(r => {
            const creator = rawUsers.value.find((u:any) => (u.id ?? u.idUsuario) === (r.creado_por ?? r.creadoPor))
            const workerName = creator ? `${creator.nombre} ${creator.apellidos}` : 'Usuario Desconocido'
            return {
                id: r.idReporte,
                folio: r.folio,
                worker: workerName,
                description: String(r.fallaReportada || r.desarrolloActividades || 'Sin descripción'),
                status: r.estatusReporte,
                type: 'Reporte',
                route: 'report-detail',
                dateStr: (r.fechaElaboracion.split('T')[0]) as string
            }
        })
})

const allItems = computed(() => [...allTickets.value, ...allServices.value, ...allReports.value])

// Build a map for fast lookup by YYYY-MM-DD
const itemsByDate = computed(() => {
    const map = new Map<string, { reports: number, services: number, tickets: number }>()
    allItems.value.forEach(item => {
        if (!map.has(item.dateStr)) {
            map.set(item.dateStr, { reports: 0, services: 0, tickets: 0 })
        }
        const counts = map.get(item.dateStr)!
        if (item.type === 'Reporte') counts.reports++
        if (item.type === 'Servicio') counts.services++
        if (item.type === 'Ticket') counts.tickets++
    })
    return map
})

const getCountsForDate = (date: Date) => {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const key = `${year}-${month}-${day}`
    
    return itemsByDate.value.get(key) || { reports: 0, services: 0, tickets: 0 }
}

const selectedDateCounts = computed(() => {
    if (!selectedDate.value) return { reports: 0, services: 0, tickets: 0 }
    return getCountsForDate(selectedDate.value)
})

const displayDateStr = computed(() => {
    if (!selectedDate.value) return ''
    const date = selectedDate.value
    return `${date.getDate()} de ${monthNames[date.getMonth()]} de ${date.getFullYear()}`
})

const filteredList = computed(() => {
    if (!selectedDate.value) return []
    const date = selectedDate.value
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const key = `${year}-${month}-${day}`

    let list: HistoryItem[] = []
    if (activeCategory.value === 'Reportes') {
        list = allReports.value.filter(i => i.dateStr === key)
    } else if (activeCategory.value === 'Servicios') {
        list = allServices.value.filter(i => i.dateStr === key)
    } else if (activeCategory.value === 'Tickets') {
        list = allTickets.value.filter(i => i.dateStr === key)
    }
    return list
})

const navigateTo = (routeName: string, id: number) => {
    router.push({ name: routeName, params: { id } })
}

const getStatusClass = (status: string) => {
    if (status === 'Abierto' || status === 'Programado' || status.includes('Parcial')) return 'bg-blue-100 text-blue-700'
    if (status === 'En Proceso' || status === 'En Progreso') return 'bg-purple-100 text-purple-700'
    if (status === 'Vencido') return 'bg-red-100 text-red-700'
    return 'bg-emerald-100 text-emerald-700'
}

const setCategory = (category: 'Reportes' | 'Servicios' | 'Tickets') => {
    activeCategory.value = category
}
</script>

<template>
    <div class="history-root px-4 mx-auto w-full max-w-7xl">
        <div class="header-section flex justify-between items-center mb-6">
            <div>
                <h2>Historial de Actividades</h2>
                <p class="text-slate-500 mt-1">Navega por el calendario para ver los registros del día</p>
            </div>
            <button class="btn-today" @click="goToToday">Hoy</button>
        </div>

        <div class="grid-history">
            <!-- CALENDAR SECTION -->
            <div class="calendar-card">
                <div class="calendar-header">
                    <button class="nav-btn" @click="prevMonth">
                        <ChevronLeft :size="20"/>
                    </button>
                    <h3 class="month-title">{{ currentMonthName }} {{ currentYear }}</h3>
                    <button class="nav-btn" @click="nextMonth">
                        <ChevronRight :size="20"/>
                    </button>
                </div>
                
                <div class="calendar-grid-header">
                    <span>Lun</span>
                    <span>Mar</span>
                    <span>Mié</span>
                    <span>Jue</span>
                    <span>Vie</span>
                    <span>Sáb</span>
                    <span>Dom</span>
                </div>

                <div class="calendar-grid">
                    <div 
                        v-for="(day, index) in daysInMonth" 
                        :key="index"
                        class="calendar-day"
                        :class="[
                            { 'other-month': !day.isCurrentMonth },
                            { 'is-today': isSameDay(day.date, today) },
                            { 'is-selected': isSameDay(day.date, selectedDate) }
                        ]"
                        @click="selectDate(day.date)"
                    >
                        <span class="day-number">{{ day.date.getDate() }}</span>
                        
                        <!-- Indicators -->
                        <div class="indicators">
                            <div class="indicator rep" v-if="getCountsForDate(day.date).reports > 0">
                                <FileText :size="10"/>
                                <span>{{ getCountsForDate(day.date).reports }}</span>
                            </div>
                            <div class="indicator svc" v-if="getCountsForDate(day.date).services > 0">
                                <CalendarClock :size="10"/>
                                <span>{{ getCountsForDate(day.date).services }}</span>
                            </div>
                            <div class="indicator tkt" v-if="getCountsForDate(day.date).tickets > 0">
                                <Ticket :size="10"/>
                                <span>{{ getCountsForDate(day.date).tickets }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DETAILS SECTION -->
            <div class="details-sidebar">
                
                <!-- UPPER CARD: Summary -->
                <div class="summary-card">
                    <div class="summary-header">
                        <CalendarDays :size="20" class="text-slate-500"/>
                        <h3>Resumen del Día</h3>
                        <span class="selected-date-badge">{{ displayDateStr }}</span>
                    </div>
                    <div class="summary-body">
                        <button 
                            class="summary-btn" 
                            :class="{ 'active': activeCategory === 'Reportes' }"
                            @click="setCategory('Reportes')"
                        >
                            <div class="btn-icon rep-icon"><FileText :size="18"/></div>
                            <div class="btn-text">
                                <span class="btn-label">Reportes</span>
                                <span class="btn-count">{{ selectedDateCounts.reports }}</span>
                            </div>
                        </button>
                        
                        <button 
                            class="summary-btn"
                            :class="{ 'active': activeCategory === 'Servicios' }"
                            @click="setCategory('Servicios')"
                        >
                            <div class="btn-icon svc-icon"><CalendarClock :size="18"/></div>
                            <div class="btn-text">
                                <span class="btn-label">Serv. Programados</span>
                                <span class="btn-count">{{ selectedDateCounts.services }}</span>
                            </div>
                        </button>

                        <button 
                            class="summary-btn"
                            :class="{ 'active': activeCategory === 'Tickets' }"
                            @click="setCategory('Tickets')"
                        >
                            <div class="btn-icon tkt-icon"><Ticket :size="18"/></div>
                            <div class="btn-text">
                                <span class="btn-label">Tickets</span>
                                <span class="btn-count">{{ selectedDateCounts.tickets }}</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- LOWER CARD: List -->
                <div class="list-card">
                    <div class="list-header" :class="`border-${activeCategory.toLowerCase()}`">
                        <h3>Detalle de {{ activeCategory }}</h3>
                        <span class="badge">{{ filteredList.length }}</span>
                    </div>
                    
                    <div class="list-body">
                        <div v-if="filteredList.length === 0" class="empty-state">
                            <CheckCircle2 :size="32" class="text-slate-300 mb-2"/>
                            <p>No hay {{ activeCategory.toLowerCase() }} en esta fecha.</p>
                        </div>
                        <div v-else class="list-container">
                            <div 
                                v-for="item in filteredList" 
                                :key="item.id" 
                                class="list-item"
                                @click="navigateTo(item.route, item.id)"
                            >
                                <div class="item-main">
                                    <div class="flex justify-between items-start">
                                        <span class="folio" :class="`text-${activeCategory.toLowerCase()}`">{{ item.folio }}</span>
                                        <span class="tag" :class="getStatusClass(item.status)">{{ item.status }}</span>
                                    </div>
                                    <span class="worker">{{ item.worker }}</span>
                                    <span class="desc clamp-2">{{ item.description }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
.history-root {
    font-family: system-ui, -apple-system, sans-serif;
    animation: fadeIn 0.3s ease;
    padding-bottom: 2rem;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

h2 {
    color: #0f172a;
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
}

.btn-today {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    background: #e2e8f0;
    color: #334155;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background 0.2s;
    border: none;
}
.btn-today:hover {
    background: #cbd5e1;
}

.grid-history {
    display: grid;
    grid-template-columns: 2fr 1.2fr;
    gap: 1.5rem;
    height: 600px;
}

@media (max-width: 1024px) {
    .grid-history {
        grid-template-columns: 1fr;
        height: auto;
    }
}

/* ================= CALENDAR ================= */
.calendar-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #f1f5f9;
}

.month-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    text-transform: capitalize;
}

.nav-btn {
    background: transparent;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #64748b;
    transition: all 0.2s;
}
.nav-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}

.calendar-grid-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    text-align: center;
    padding: 1rem 0;
    font-weight: 600;
    font-size: 0.85rem;
    color: #64748b;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-auto-rows: 1fr;
    flex-grow: 1;
    background: #f1f5f9;
    gap: 1px;
}

.calendar-day {
    background: #ffffff;
    min-height: 80px;
    padding: 0.5rem;
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    flex-direction: column;
}
.calendar-day:hover {
    background: #f8fafc;
}
.other-month {
    background: #fafaf9;
    color: #94a3b8;
}

.is-today .day-number {
    background: #3b82f6;
    color: white;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
.is-selected {
    background: #eff6ff;
    box-shadow: inset 0 0 0 2px #3b82f6;
}

.day-number {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    align-self: flex-start;
}

/* Indicators inside day */
.indicators {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-top: auto;
}
.indicator {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 2px 4px;
    border-radius: 4px;
}
.indicator.rep { background: #d1fae5; color: #059669; }
.indicator.svc { background: #dbeafe; color: #2563eb; }
.indicator.tkt { background: #ffedd5; color: #ea580c; }


/* ================= DETAILS SECTION ================= */
.details-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    height: 100%;
}

.summary-card, .list-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
}

.summary-card {
    flex-shrink: 0;
}

.list-card {
    flex-grow: 1;
    overflow: hidden;
    height: 100%;
}

/* Base card headers */
.summary-header, .list-header {
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.summary-header h3, .list-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    flex-grow: 1;
}
.selected-date-badge {
    background: #e2e8f0;
    color: #475569;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.list-header.border-reportes { border-bottom: 2px solid #10b981; }
.list-header.border-servicios { border-bottom: 2px solid #3b82f6; }
.list-header.border-tickets { border-bottom: 2px solid #f97316; }

.badge {
    background: #e2e8f0;
    color: #475569;
    padding: 0.15rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

/* Summary Buttons */
.summary-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.summary-btn {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    text-align: left;
    transition: all 0.2s;
}
.summary-btn:hover {
    background: #f1f5f9;
}
.summary-btn.active {
    background: #ffffff;
    border-color: #cbd5e1;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); /* lifted effect */
    transform: translateX(-4px); /* pop out */
}

/* Colors for specific active states */
.summary-btn.active:has(.rep-icon) { border-left: 4px solid #10b981; }
.summary-btn.active:has(.svc-icon) { border-left: 4px solid #3b82f6; }
.summary-btn.active:has(.tkt-icon) { border-left: 4px solid #f97316; }

.btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.rep-icon { background: #d1fae5; color: #10b981; }
.svc-icon { background: #dbeafe; color: #3b82f6; }
.tkt-icon { background: #ffedd5; color: #ea580c; }

.btn-text {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}
.btn-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #64748b;
}
.summary-btn.active .btn-label {
    color: #0f172a;
}
.btn-count {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
}


/* List content */
.list-body {
    flex-grow: 1;
    overflow-y: auto;
    padding: 0;
}
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
.list-container {
    display: flex;
    flex-direction: column;
}
.list-item {
    padding: 1rem 1.25rem;
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
    width: 100%;
}
.folio {
    font-weight: 700;
    font-size: 0.85rem;
}
.text-reportes { color: #059669; }
.text-servicios { color: #2563eb; }
.text-tickets { color: #ea580c; }

.worker {
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
}
.desc {
    font-size: 0.8rem;
    color: #475569;
    line-height: 1.4;
    margin-top: 2px;
}
.clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tag {
    font-size: 0.65rem;
    font-weight: 600;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

</style>
