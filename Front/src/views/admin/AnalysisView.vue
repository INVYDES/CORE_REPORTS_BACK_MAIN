<template>
  <div class="analysis-dashboard min-h-screen bg-gray-50/50 p-4 md:p-8 space-y-6" ref="dashboardContent">
    <!-- Header -->
    <header class="dashboard-header flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 group flex items-center gap-2">
            Análisis y Rendimiento
        </h1>
        <p class="text-sm text-gray-500 mt-1">Monitorea los indicadores clave de tu operación</p>
      </div>

      <div class="header-actions flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto" data-html2canvas-ignore="true">
        <select v-model="selectedTechnician" class="action-select bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-colors w-full sm:w-auto">
          <option :value="null">General (Todos los técnicos)</option>
          <option v-for="tech in availableTechnicians" :key="tech.id" :value="tech.id">{{ tech.nombre }}</option>
        </select>
        <select v-model="timeFilter" class="action-select bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none transition-colors w-full sm:w-auto">
          <option value="Este Mes">Este Mes</option>
          <option value="Hoy">Hoy</option>
          <option value="Esta Semana">Esta Semana</option>
          <option value="Este Año">Este Año</option>
          <option value="Histórico">Histórico (Todo)</option>
        </select>
        <button @click="exportPDF" class="btn-export bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-4 py-2.5 w-full sm:w-auto focus:outline-none transition-all shadow-sm">Exportar PDF</button>
      </div>
    </header>

    <!-- Analysis Heading -->
    <div class="mt-4 sm:mt-6 mb-2">
        <h2 class="text-xl font-bold text-gray-800 border-b border-gray-200 pb-2">
            {{ selectedTechnician === null ? 'Rendimiento General del Equipo' : 'Rendimiento Individual (Técnico)' }}
        </h2>
    </div>

    <!-- Tier 1: Hero Cards -->
    <section class="scorecards-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
      <div class="scorecard flex flex-col justify-between rounded-xl border border-gray-100 shadow-md p-6 bg-gradient-to-br from-white to-gray-50 transform hover:-translate-y-1 transition-transform duration-200">
        <h3 class="card-title text-sm font-semibold text-gray-500 mb-2 uppercase tracking-wide">Cumplimiento SLA</h3>
        <p class="card-value text-4xl font-extrabold mb-2" :class="slaPercentage === null ? 'text-gray-400 font-medium' : (slaPercentage >= 90 ? 'text-emerald-600' : 'text-red-600')">{{ slaPercentage ?? 0 }}%</p>
        <span class="trend-indicator text-xs font-medium px-2 py-1 rounded w-fit" :class="slaPercentage === null ? 'bg-gray-100 text-gray-500' : (slaPercentage >= 90 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700')">En base a prioridad</span>
      </div>

      <div class="scorecard flex flex-col justify-between rounded-xl border border-gray-100 shadow-md p-6 bg-gradient-to-br from-white to-gray-50 transform hover:-translate-y-1 transition-transform duration-200">
        <h3 class="card-title text-sm font-semibold text-gray-500 mb-2 uppercase tracking-wide">MTTR Sol. Fallas</h3>
        <p class="card-value text-4xl font-extrabold text-gray-900 mb-2">{{ mttrAvg.toFixed(1) }} <span class="text-lg font-medium text-gray-500">hrs</span></p>
        <span class="trend-indicator text-xs font-medium px-2 py-1 rounded w-fit bg-red-100 text-red-700">Equipos fallando</span>
      </div>

      <div class="scorecard flex flex-col justify-between rounded-xl border border-gray-100 shadow-md p-6 bg-gradient-to-br from-white to-gray-50 transform hover:-translate-y-1 transition-transform duration-200">
        <h3 class="card-title text-sm font-semibold text-gray-500 mb-2 uppercase tracking-wide">Horas Hombre Totales</h3>
        <p class="card-value text-4xl font-extrabold text-blue-600 mb-2">{{ totalHoras.toFixed(1) }} <span class="text-lg font-medium text-blue-400">hrs</span></p>
        <span class="trend-indicator text-xs font-medium px-2 py-1 rounded w-fit bg-blue-100 text-blue-700">{{ selectedTechnician === null ? 'Totales aplicadas' : 'Aplicadas por técnico' }}</span>
      </div>

      <div class="scorecard flex flex-col justify-between rounded-xl border border-gray-100 shadow-md p-6 bg-gradient-to-br from-white to-gray-50 transform hover:-translate-y-1 transition-transform duration-200" :class="percentRework === null ? 'border-l-4 border-gray-200' : (percentRework > 10 ? 'border-l-4 border-l-red-500' : 'border-l-4 border-l-emerald-500')">
        <h3 class="card-title text-sm font-semibold text-gray-500 mb-2 uppercase tracking-wide">% Retrabajos</h3>
        <p class="card-value text-4xl font-extrabold mb-2" :class="percentRework === null ? 'text-gray-400 font-medium' : (percentRework > 10 ? 'text-red-600' : 'text-emerald-600')">{{ percentRework ?? 0 }}%</p>
        <span class="trend-indicator text-xs font-medium px-2 py-1 rounded w-fit" :class="percentRework === null ? 'bg-gray-100 text-gray-500' : (percentRework > 10 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700')">Sobre solicitados</span>
      </div>
    </section>

    <!-- Distribución de Tipos de Mantenimiento (Stacked Bar) -->
    <section class="maintenance-distribution w-full bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-6">
      <div class="flex justify-between items-end mb-4">
        <div>
          <h3 class="text-base font-semibold text-gray-900">Distribución de Mantenimiento</h3>
          <p class="text-sm text-gray-500 mt-1">Desglose por categoría de reporte</p>
        </div>
        <div class="text-2xl font-bold text-gray-900">{{ totalReportes }} <span class="text-sm font-medium text-gray-500">Reportes</span></div>
      </div>
      
      <!-- Stacked Bar -->
      <div class="w-full h-8 flex rounded-lg overflow-hidden relative group">
        <div class="h-full bg-emerald-500 transition-all duration-500 group-hover:opacity-90 flex items-center justify-center text-xs font-bold text-white shadow-inner" :style="{ width: percentPreventive + '%' }">
          <span v-if="percentPreventive > 4">{{ percentPreventive }}%</span>
        </div>
        <div class="h-full bg-red-500 transition-all duration-500 group-hover:opacity-90 flex items-center justify-center text-xs font-bold text-white shadow-inner border-l border-white/20" :style="{ width: percentCorrective + '%' }">
          <span v-if="percentCorrective > 4">{{ percentCorrective }}%</span>
        </div>
        <div class="h-full bg-indigo-500 transition-all duration-500 group-hover:opacity-90 flex items-center justify-center text-xs font-bold text-white shadow-inner border-l border-white/20" :style="{ width: percentDiagnosisFallas + '%' }">
          <span v-if="percentDiagnosisFallas > 4">{{ percentDiagnosisFallas }}%</span>
        </div>
        <div class="h-full bg-amber-500 transition-all duration-500 group-hover:opacity-90 flex items-center justify-center text-xs font-bold text-white shadow-inner border-l border-white/20" :style="{ width: percentInstalacion + '%' }">
          <span v-if="percentInstalacion > 4">{{ percentInstalacion }}%</span>
        </div>
        <div class="h-full bg-cyan-500 transition-all duration-500 group-hover:opacity-90 flex items-center justify-center text-xs font-bold text-white shadow-inner border-l border-white/20" :style="{ width: percentMejora + '%' }">
          <span v-if="percentMejora > 4">{{ percentMejora }}%</span>
        </div>
      </div>

      <!-- Legend -->
      <div class="flex flex-wrap justify-between items-start mt-5 text-sm gap-4">
        <div class="flex flex-col items-center gap-1">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
            <span class="font-medium text-gray-700">Preventivo ({{ percentPreventive }}%)</span>
          </div>
          <span class="text-gray-500 text-xs font-medium bg-gray-50 px-2 py-0.5 rounded-md">{{ countPreventive }} Reportes</span>
        </div>
        
        <div class="flex flex-col items-center gap-1">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-red-500"></span>
            <span class="font-medium text-gray-700">Correctivo ({{ percentCorrective }}%)</span>
          </div>
          <span class="text-gray-500 text-xs font-medium bg-gray-50 px-2 py-0.5 rounded-md">{{ countCorrective }} Reportes</span>
        </div>
        
        <div class="flex flex-col items-center gap-1">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-indigo-500"></span>
            <span class="font-medium text-gray-700">Diagnóstico ({{ percentDiagnosisFallas }}%)</span>
          </div>
          <span class="text-gray-500 text-xs font-medium bg-gray-50 px-2 py-0.5 rounded-md">{{ countDiagnostico }} Reportes</span>
        </div>

        <div class="flex flex-col items-center gap-1">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-amber-500"></span>
            <span class="font-medium text-gray-700">Instalación ({{ percentInstalacion }}%)</span>
          </div>
          <span class="text-gray-500 text-xs font-medium bg-gray-50 px-2 py-0.5 rounded-md">{{ countInstalacion }} Reportes</span>
        </div>

        <div class="flex flex-col items-center gap-1">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-cyan-500"></span>
            <span class="font-medium text-gray-700">Mejora ({{ percentMejora }}%)</span>
          </div>
          <span class="text-gray-500 text-xs font-medium bg-gray-50 px-2 py-0.5 rounded-md">{{ countMejora }} Reportes</span>
        </div>
      </div>
    </section>

    <!-- Tier 2: Secondary Secondary Scorecards (Thematic Panels) -->
    <section class="scorecards-grid grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
      
      <!-- Panel 1: Tiempos Operativos -->
      <div class="panel flex flex-col bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-shadow">
        <h3 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
          <Clock class="w-5 h-5 text-gray-400" />
          Tiempos Operativos (Promedios)
        </h3>
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 w-full flex-grow">
          <div class="flex flex-col items-center w-full px-2">
            <h4 class="text-xs font-medium text-gray-500 mb-1 text-center">T. Atención</h4>
            <p class="text-xl font-bold text-gray-900">{{ attentionTimeAvg.toFixed(1) }} <span class="text-xs font-normal text-gray-500">hrs</span></p>
          </div>
          <div class="hidden sm:block text-gray-300">
            <ArrowRight class="w-5 h-5" />
          </div>
          <div class="flex flex-col items-center w-full px-2 sm:border-l sm:border-gray-100">
            <h4 class="text-xs font-medium text-gray-500 mb-1 text-center">T. Diagnóstico</h4>
            <p class="text-xl font-bold text-gray-900">{{ diagnosisTimeAvg.toFixed(1) }} <span class="text-xs font-normal text-gray-500">hrs</span></p>
          </div>
          <div class="hidden sm:block text-gray-300">
            <ArrowRight class="w-5 h-5" />
          </div>
          <div class="flex flex-col items-center w-full px-2 sm:border-l sm:border-gray-100 relative group cursor-help">
            <h4 class="text-xs font-medium text-gray-500 mb-1 text-center border-b border-dashed border-gray-400">T. Utilización</h4>
            <p class="text-xl font-bold text-gray-900">{{ techUtilizationAvg.toFixed(1) }} <span class="text-xs font-normal text-gray-500">hrs</span></p>
            <div class="absolute bottom-full mb-2 hidden w-64 rounded bg-gray-800 p-2 text-xs text-white shadow-lg group-hover:block z-50 text-center font-normal">
              Cálculo: Tiempo total de traslado (ida y vuelta) + llegada + inicio + fin
            </div>
          </div>
        </div>
      </div>

      <!-- Panel 2: Volumen de Operación -->
      <div class="panel flex flex-col bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-shadow">
        <h3 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
          <BarChart2 class="w-5 h-5 text-gray-400" />
          Volumen de Operación
        </h3>
        <div class="flex flex-col justify-center flex-grow">
          <div class="flex justify-between items-end mb-3">
            <p class="text-3xl font-extrabold text-gray-900">{{ totalTicketsReportes + totalServiciosReportes + totalLibresReportes }}</p>
            <span class="text-sm font-medium text-gray-500 mb-1">Total Reportes Ejecutados</span>
          </div>
          
          <!-- Stacked Bar -->
          <div class="w-full flex h-2 rounded-full overflow-hidden mb-4 bg-gray-100">
            <div class="bg-blue-500 transition-all duration-500" :style="{ width: ((totalTicketsReportes / (totalTicketsReportes + totalServiciosReportes + totalLibresReportes || 1)) * 100) + '%' }"></div>
            <div class="bg-purple-500 transition-all duration-500 border-l border-white/20" :style="{ width: ((totalServiciosReportes / (totalTicketsReportes + totalServiciosReportes + totalLibresReportes || 1)) * 100) + '%' }"></div>
            <div class="bg-gray-400 transition-all duration-500 border-l border-white/20" :style="{ width: ((totalLibresReportes / (totalTicketsReportes + totalServiciosReportes + totalLibresReportes || 1)) * 100) + '%' }"></div>
          </div>

          <!-- Legend -->
          <div class="flex flex-wrap justify-between text-xs font-medium text-gray-600 gap-2">
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-blue-500"></span> Tickets ({{ totalTicketsReportes }})
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-purple-500"></span> Programados ({{ totalServiciosReportes }})
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-gray-400"></span> Libres ({{ totalLibresReportes }})
            </div>
          </div>
        </div>
      </div>

      <!-- Panel 3: Eficacia y Calidad -->
      <div class="panel flex flex-col bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-shadow lg:col-span-2">
        <h3 class="text-base font-semibold text-gray-900 border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
          <Activity class="w-5 h-5 text-gray-400" />
          Eficacia y Calidad
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 w-full">
          <!-- FTFR -->
          <div class="flex flex-col justify-center p-4 bg-gray-50/70 rounded-lg transition-colors border-l-4" :class="ftfrPercentage === null ? 'border-gray-200' : (ftfrPercentage <= 30 ? 'border-red-500' : 'border-emerald-500')">
            <h4 class="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">% FTFR</h4>
            <p class="text-2xl" :class="ftfrPercentage === null ? 'text-gray-400 font-medium' : (ftfrPercentage <= 30 ? 'text-red-600 font-bold' : 'text-emerald-600 font-bold')">{{ ftfrPercentage ?? 0 }}%</p>
          </div>
          <!-- OT Conformidad -->
          <div class="flex flex-col justify-center p-4 bg-gray-50/70 rounded-lg transition-colors border-l-4" :class="percentConformity === null ? 'border-gray-200' : (percentConformity >= 80 ? 'border-emerald-500' : 'border-gray-300')">
            <h4 class="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">% Conformidad</h4>
            <p class="text-2xl" :class="percentConformity === null ? 'text-gray-400 font-medium' : (percentConformity >= 80 ? 'text-emerald-600 font-bold' : 'text-gray-900 font-bold')">{{ percentConformity ?? 0 }}%</p>
          </div>
          <!-- OT Descartadas -->
          <div class="flex flex-col justify-center p-4 bg-gray-50/70 rounded-lg transition-colors border-l-4" :class="percentDiscarded === null ? 'border-gray-200' : (percentDiscarded > 10 ? 'border-orange-500' : 'border-gray-300')">
            <h4 class="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">% Descartadas</h4>
            <p class="text-2xl" :class="percentDiscarded === null ? 'text-gray-400 font-medium' : (percentDiscarded > 10 ? 'text-orange-500 font-bold' : 'text-gray-500 font-bold')">{{ percentDiscarded ?? 0 }}%</p>
          </div>
          <!-- OT Abiertas -->
          <div class="flex flex-col justify-center p-4 bg-gray-50/70 rounded-lg transition-colors border-l-4" :class="percentOpen === null ? 'border-gray-200' : (percentOpen > 20 ? 'border-yellow-400' : 'border-gray-300')">
            <h4 class="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wider">% Abiertas</h4>
            <p class="text-2xl" :class="percentOpen === null ? 'text-gray-400 font-medium' : (percentOpen > 20 ? 'text-yellow-600 font-bold' : 'text-gray-500 font-bold')">{{ percentOpen ?? 0 }}%</p>
          </div>
        </div>
      </div>

    </section>

    <!-- Tier 2: Trend Chart (Full Width) -->
    <section class="w-full">
      <div class="chart-card w-full overflow-hidden bg-white rounded-xl border border-gray-100 shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
        <h3 class="chart-header text-base font-semibold text-gray-900 mb-6">Mantenimiento Proactivo vs Reactivo</h3>
        <div class="chart-container w-full h-full">
            <apexchart 
                :key="'chart1-' + timeFilter"
                type="area" 
                :height="chartHeight" 
                :options="chart1Options" 
                :series="chart1Series">
            </apexchart>
        </div>
      </div>
    </section>


    <!-- Tier 3: Leaderboard -->
    <section class="leaderboard-section w-full">
      <div class="chart-card w-full overflow-hidden bg-white rounded-xl border border-gray-100 shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
        <h3 class="chart-header text-base font-semibold text-gray-900 mb-6">Rendimiento del Personal (Horas Invertidas)</h3>
         <div class="chart-container w-full h-full">   
            <apexchart 
                :key="'chart3-' + selectedTechnician"
                type="bar" 
                height="350" 
                :options="chart3Options" 
                :series="chart3Series">
            </apexchart>
         </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, watchEffect, ref, onMounted, onUnmounted, watch } from 'vue'
import { Clock, ArrowRight, BarChart2, Activity } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/authStore'
import client from '../../api/client'
import { mockReportes, type Reporte } from '../../data/mockReportes'
import { mockTickets, type Ticket } from '../../data/mockTickets'
import { mockServices, type Service } from '../../data/mockServicios'
import { mockUsers, type Usuario } from '../../data/mockUsers'
import { mockEncuestas } from '../../data/mockEncuestas'
import html2pdf from 'html2pdf.js'

// Initialize router and auth
const router = useRouter()
const authStore = useAuthStore()
const currentUser = computed(() => authStore.currentUser)

// HTML2PDF Export Logic
const dashboardContent = ref<HTMLElement | null>(null)
const exportPDF = () => {
    if (!dashboardContent.value) return;
    const opt = {
        margin:       10,
        filename:     `dashboard-analisis-${new Date().toISOString().split('T')[0]}.pdf`,
        image:        { type: 'jpeg' as 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'mm', format: 'letter', orientation: 'landscape' as 'landscape' }
    };
    html2pdf().set(opt).from(dashboardContent.value).save();
}

// 1. Security Redirect - espera a que cargue currentUser
watchEffect(() => {
    if (!currentUser.value) return
    if (currentUser.value?.rol !== 1 && currentUser.value?.rol !== 2) {
        router.push({ name: 'dashboard' })
    }
})

// Safe fallback for idDependencia during initial load/redirect
const myCompanyId = computed(() => (currentUser.value as any)?.dependencia_id ?? (currentUser.value as any)?.idDependencia ?? -1)

const timeFilter = ref('Este Mes')
const selectedTechnician = ref<number | null>(null)

const analyticsData = ref<any>(null)
const fetchAnalytics = async ()=>{
    try {
        const params:any = {}
        if (selectedTechnician.value) params.tecnico = selectedTechnician.value
        const res = await client.get('/analytics/all', { params }).then(r=>r.data)
        analyticsData.value = res
    } catch {}
}
onMounted(fetchAnalytics)
watch([timeFilter, selectedTechnician], fetchAnalytics)

// Responsive chart height
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200)
const onResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))

const chartHeight = computed(() => {
    if (windowWidth.value < 640) return 280
    if (windowWidth.value < 1024) return 320
    return 350
})

// Date helper
const checkDateFilter = (dateStr: string | undefined): boolean => {
    if (timeFilter.value === 'Histórico') return true;
    if (!dateStr) return false;
    
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return false;
    
    const now = new Date();
    
    // Normalize to local midnight to prevent time-of-day edge case drift
    const dDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const nDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    switch (timeFilter.value) {
        case 'Hoy':
            return dDate.getTime() === nDate.getTime();
        case 'Esta Semana':
            const firstDayOfWeek = new Date(nDate);
            firstDayOfWeek.setDate(nDate.getDate() - nDate.getDay());
            return dDate >= firstDayOfWeek;
        case 'Este Mes':
            return dDate.getMonth() === nDate.getMonth() && dDate.getFullYear() === nDate.getFullYear();
        case 'Este Año':
            return dDate.getFullYear() === nDate.getFullYear();
        default:
            return true;
    }
}

// 2. Original Filtered Data (by company and time filter)
const myOriginalReports = computed(() => mockReportes.filter((r: Reporte) => r.idDependencia === myCompanyId.value && checkDateFilter(r.fechaElaboracion)))
// Note: Tickets y Servicios also filtered by date. Some dashboards keep base tickets un-time-filtered, but here we sync them. 
const myOriginalTickets = computed(() => mockTickets.filter((t: Ticket) => t.idDependencia === myCompanyId.value && checkDateFilter(t.fechaSolicitud)))
const myOriginalServices = computed(() => mockServices.filter((s: Service) => s.idDependencia === myCompanyId.value && checkDateFilter(s.fechaAsignacion)))

const realTechs = ref<any[]>([])
onMounted(async () => {
    try {
        const res = await client.get('/usuarios', { params: { per_page: 100 } }).then(r=>r.data)
        const list = res.data || res
        realTechs.value = Array.isArray(list) ? list.filter((u:any)=> [1,3].includes(u.rol)).map((u:any)=> ({ id: u.id, nombre: `${u.nombre} ${u.apellidos}` })) : []
    } catch {}
})

const availableTechnicians = computed(() => {
    if (realTechs.value.length) return realTechs.value
    const techIds = new Set<number>()
    myOriginalReports.value.forEach(r => {
        r.ejecutoresIds.forEach(id => techIds.add(id))
    })
    return mockUsers.filter(u => techIds.has(u.idUsuario)).map(u => ({ id: u.idUsuario, nombre: `${u.nombre} ${u.apellidos}` }))
})

// 3. KPI Filtered Scopes (General vs Individual)
const myReports = computed(() => {
    if (selectedTechnician.value === null) return myOriginalReports.value
    return myOriginalReports.value.filter(r => r.ejecutoresIds.includes(selectedTechnician.value as number))
})

const myTickets = computed(() => {
    if (selectedTechnician.value === null) return myOriginalTickets.value
    return myOriginalTickets.value.filter(t => {
        return myOriginalReports.value.some(r => r.tipoReporte === 'Ticket' && r.origenDatosId === t.id && r.ejecutoresIds.includes(selectedTechnician.value as number))
    })
})

const myServices = computed(() => {
    if (selectedTechnician.value === null) return myOriginalServices.value
    return myOriginalServices.value.filter(s => {
        return myOriginalReports.value.some(r => r.tipoReporte === 'Servicio Programado' && r.origenDatosId === s.id && r.ejecutoresIds.includes(selectedTechnician.value as number))
    })
})


const totalReportes = computed(() => myReports.value.length)

// 4. KPI Calculations (Volumes)
const totalTicketsReportes = computed(() => myReports.value.filter(r => r.tipoReporte === 'Ticket').length)
const totalServiciosReportes = computed(() => myReports.value.filter(r => r.tipoReporte === 'Servicio Programado').length)
const totalLibresReportes = computed(() => myReports.value.filter(r => r.tipoReporte === 'Libre').length)

// totalHoras: Sum diff between fechaHoraInicio and fechaHoraFin across 'Cerrado / Trabajo Finalizado' reports
const totalHoras = computed(() => {
    if (analyticsData.value?.horas_hombre?.horas) return parseFloat(analyticsData.value.horas_hombre.horas) || 0
    let hl = 0;
    myReports.value.forEach((report: Reporte) => {
        if (report.estatusReporte === 'Cerrado / Trabajo Finalizado' && report.fechaHoraInicio && report.fechaHoraFin) {
            const start = new Date(report.fechaHoraInicio).getTime()
            const end = new Date(report.fechaHoraFin).getTime()
            if (!isNaN(start) && !isNaN(end) && end > start) {
                const duration = (end - start) / (1000 * 60 * 60)
                hl += duration * (report.ejecutoresIds?.length || 1)
            }
        }
    })
    return hl
})

// Times
const mttrAvg = computed(() => {
    if (analyticsData.value?.mttr?.mttr_horas) return parseFloat(analyticsData.value.mttr.mttr_horas) || 0
    let totalHrs = 0;
    let count = 0;
    myReports.value.forEach(r => {
        if (r.categoria === 'Mantenimiento Correctivo' && r.fechaHoraInicio && r.fechaHoraFin) {
            const start = new Date(r.fechaHoraInicio).getTime()
            const end = new Date(r.fechaHoraFin).getTime()
            if (!isNaN(start) && !isNaN(end) && end > start) {
                totalHrs += (end - start) / (1000 * 60 * 60)
                count++
            }
        }
    })
    return count > 0 ? totalHrs / count : 0
})

const attentionTimeAvg = computed(() => {
    let totalHrs = 0;
    let count = 0;
    myReports.value.forEach(r => {
        if ((r.tipoReporte === 'Ticket' || r.tipoReporte === 'Servicio Programado') && r.fechaHoraInicio && r.fechaHoraFin) {
            const start = new Date(r.fechaHoraInicio).getTime()
            const end = new Date(r.fechaHoraFin).getTime()
            if (!isNaN(start) && !isNaN(end) && end > start) {
                totalHrs += (end - start) / (1000 * 60 * 60)
                count++
            }
        }
    })
    return count > 0 ? totalHrs / count : 0
})

// Distribution Counts
const countPreventive = computed(() => {
    if (analyticsData.value?.categoria) {
        const c = analyticsData.value.categoria.find((x:any)=> x.categoria==='preventivo')
        return c ? parseInt(c.total) : 0
    }
    return myReports.value.filter(r => r.categoria === 'Mantenimiento Preventivo').length
});
const countCorrective = computed(() => {
    if (analyticsData.value?.categoria) {
        const c = analyticsData.value.categoria.find((x:any)=> x.categoria==='correctivo')
        return c ? parseInt(c.total) : 0
    }
    return myReports.value.filter(r => r.categoria === 'Mantenimiento Correctivo').length
});
const countDiagnostico = computed(() => {
    if (analyticsData.value?.categoria) {
        const c = analyticsData.value.categoria.find((x:any)=> x.categoria==='diagnostico')
        return c ? parseInt(c.total) : 0
    }
    return myReports.value.filter(r => r.categoria === 'Diagnóstico').length
});
const countInstalacion = computed(() => {
    if (analyticsData.value?.categoria) {
        const c = analyticsData.value.categoria.find((x:any)=> x.categoria==='instalacion')
        return c ? parseInt(c.total) : 0
    }
    return myReports.value.filter(r => r.categoria === 'Instalación').length
});
const countMejora = computed(() => {
    if (analyticsData.value?.categoria) {
        const c = analyticsData.value.categoria.find((x:any)=> x.categoria==='mejora')
        return c ? parseInt(c.total) : 0
    }
    return myReports.value.filter(r => r.categoria === 'Mejora').length
});

// Percentages
const percentPreventive = computed(() => {
    if (myReports.value.length === 0) return 0;
    return Math.round((countPreventive.value / myReports.value.length) * 100);
});
const percentCorrective = computed(() => {
    if (myReports.value.length === 0) return 0;
    return Math.round((countCorrective.value / myReports.value.length) * 100);
});
const percentInstalacion = computed(() => {
    if (myReports.value.length === 0) return 0;
    return Math.round((countInstalacion.value / myReports.value.length) * 100);
});
const percentMejora = computed(() => {
    if (myReports.value.length === 0) return 0;
    return Math.round((countMejora.value / myReports.value.length) * 100);
});
const percentOpen = computed(() => {
    if (myReports.value.length === 0) return null;
    const count = myReports.value.filter(r => r.estatusReporte === 'Abierto / Trabajo Parcial').length;
    return Math.round((count / myReports.value.length) * 100);
});

const slaComplianceData = computed(() => {
    let insideSLA = 0;
    let totalResolved = 0;

    const SLA_WINDOWS = {
        'Alta': 24,
        'Media': 48,
        'Baja': 72
    };

    myTickets.value.forEach(ticket => {
        if (ticket.estatus === 'Resuelto' || ticket.estatus === 'Cerrado') {
            totalResolved++;
            
            // For resolution time, we use the last report's finish time or ticket.fechaAtencion
            const reports = myReports.value.filter(r => r.tipoReporte === 'Ticket' && r.origenDatosId === ticket.id);
            let resolutionTimestamp = 0;

            if (reports.length > 0) {
                const sortedReports = [...reports].sort((a, b) => new Date(b.fechaHoraFin).getTime() - new Date(a.fechaHoraFin).getTime());
                const lastReport = sortedReports[0];
                if (lastReport) {
                    resolutionTimestamp = new Date(lastReport.fechaHoraFin).getTime();
                }
            } else if (ticket.fechaAtencion) {
                resolutionTimestamp = new Date(ticket.fechaAtencion).getTime();
            }

            const requestTimestamp = new Date(ticket.fechaSolicitud).getTime();
            
            if (resolutionTimestamp && requestTimestamp) {
                const diffHours = (resolutionTimestamp - requestTimestamp) / (1000 * 60 * 60);
                const maxWindow = SLA_WINDOWS[ticket.prioridad] || 48;
                
                if (diffHours <= maxWindow) {
                    insideSLA++;
                }
            }
        }
    });

    return {
        percentage: totalResolved > 0 ? Math.round((insideSLA / totalResolved) * 100) : null,
        totalResolved,
        insideSLA
    };
});

const slaPercentage = computed(() => {
    if (analyticsData.value?.sla && Array.isArray(analyticsData.value.sla) && analyticsData.value.sla.length) {
        const avg = analyticsData.value.sla.reduce((acc:any, cur:any)=> acc + parseFloat(cur.cumplimiento||0), 0) / analyticsData.value.sla.length
        return Math.round(avg * 100)
    }
    return slaComplianceData.value.percentage
});

const ftfrPercentage = computed(() => {
    let firstTimeFixes = 0;
    let totalResolved = 0;

    myTickets.value.forEach(ticket => {
        if (ticket.estatus === 'Resuelto' || ticket.estatus === 'Cerrado') {
            totalResolved++;
            const reports = myReports.value.filter(r => r.tipoReporte === 'Ticket' && r.origenDatosId === ticket.id);
            if (reports.length === 1) {
                firstTimeFixes++;
            }
        }
    });

    return totalResolved > 0 ? Math.round((firstTimeFixes / totalResolved) * 100) : null;
});

// Phase 7 KPI Calculations

// 1. Tiempo Promedio de Utilización de Técnico (horaSalidaBase until horaRegresoABase)
const techUtilizationAvg = computed(() => {
    let totalHrs = 0;
    let count = 0;
    myReports.value.forEach(r => {
        if (r.horaSalidaBase && r.horaRegresoABase) {
            const start = new Date(r.horaSalidaBase).getTime();
            const end = new Date(r.horaRegresoABase).getTime();
            if (!isNaN(start) && !isNaN(end) && end > start) {
                totalHrs += (end - start) / (1000 * 60 * 60);
                count++;
            }
        }
    });
    return count > 0 ? totalHrs / count : 0;
});

// 2. Tiempo Promedio de Diagnóstico de Falla
const diagnosisTimeAvg = computed(() => {
    let totalHrs = 0;
    let count = 0;
    myReports.value.forEach(r => {
        if (r.categoria === 'Diagnóstico' && r.fechaHoraInicio && r.fechaHoraFin) {
            const start = new Date(r.fechaHoraInicio).getTime();
            const end = new Date(r.fechaHoraFin).getTime();
            if (!isNaN(start) && !isNaN(end) && end > start) {
                totalHrs += (end - start) / (1000 * 60 * 60);
                count++;
            }
        }
    });
    return count > 0 ? totalHrs / count : 0;
});

// 3. % de Diagnóstico de Fallas (Solo a no programados = Tickets + Libres)
const percentDiagnosisFallas = computed(() => {
    const nonProgrammedReports = myReports.value.filter(r => r.tipoReporte !== 'Servicio Programado');
    if (nonProgrammedReports.length === 0) return 0;
    const diagnosisCount = nonProgrammedReports.filter(r => r.categoria === 'Diagnóstico').length;
    return Math.round((diagnosisCount / nonProgrammedReports.length) * 100);
});

// 4. % de Retrabajos (Only on Tickets / Servicios Programados)
const percentRework = computed(() => {
    if (analyticsData.value?.retrabajos?.pct_retrabajo !== null && analyticsData.value?.retrabajos?.pct_retrabajo !== undefined) {
        return Math.round(parseFloat(analyticsData.value.retrabajos.pct_retrabajo) || 0)
    }
    const requestedWork = myReports.value.filter(r => r.tipoReporte === 'Ticket' || r.tipoReporte === 'Servicio Programado');
    if (requestedWork.length === 0) return null;
    const originCounts = new Map<string, number>();
    requestedWork.forEach(r => {
        const key = `${r.tipoReporte}-${r.origenDatosId}`;
        originCounts.set(key, (originCounts.get(key) || 0) + 1);
    });
    const reworkCount = requestedWork.filter(r => {
        const key = `${r.tipoReporte}-${r.origenDatosId}`;
        return (originCounts.get(key) || 0) >= 2;
    }).length;
    return Math.round((reworkCount / requestedWork.length) * 100);
});

// 5. % de OT Descartadas
const percentDiscarded = computed(() => {
    if (myReports.value.length === 0) return null;
    const discardedCount = myReports.value.filter(r => r.estatusReporte === 'Descartado / Cancelado').length;
    return Math.round((discardedCount / myReports.value.length) * 100);
});

// 6. % de OT Cerradas de Conformidad (encuesta calificacion >= 8 / closed reports WITH survey)
const percentConformity = computed(() => {
    const closedReportsWithSurvey = mockEncuestas.filter(e => 
        myReports.value.some(r => r.idReporte === e.idReporte && r.estatusReporte === 'Cerrado / Trabajo Finalizado')
    );
    if (closedReportsWithSurvey.length === 0) return null;
    
    const satisfied = closedReportsWithSurvey.filter(e => e.calificacion >= 8).length;
    return Math.round((satisfied / closedReportsWithSurvey.length) * 100);
});

// Technician Hours Map
const technicianHoursMap = computed(() => {
    const map = new Map<number, number>()
    
    myReports.value.forEach((report: Reporte) => {
        if (report.estatusReporte === 'Cerrado / Trabajo Finalizado' && report.fechaHoraInicio && report.fechaHoraFin) {
            const start = new Date(report.fechaHoraInicio).getTime()
            const end = new Date(report.fechaHoraFin).getTime()
            if (!isNaN(start) && !isNaN(end) && end > start) {
                const diffHours = (end - start) / (1000 * 60 * 60)
                
                // Add hours to each executor proportionally or total (we do total per executor here)
                report.ejecutoresIds.forEach((id: number) => {
                    if (selectedTechnician.value === null || id === selectedTechnician.value) {
                        map.set(id, (map.get(id) || 0) + diffHours)
                    }
                })
            }
        }
    })
    return map
})

// Mapped Arrays for Chart 3 (Sorted by Performance)
const sortedTechnicianData = computed(() => {
    const data: { name: string, hours: number }[] = []
    technicianHoursMap.value.forEach((val: number, id: number) => {
        const user = mockUsers.find((u: Usuario) => u.idUsuario === id)
        const name = user ? `${user.nombre} ${user.apellidos}` : `Usuario ${id}`
        data.push({ name, hours: Number(val.toFixed(1)) })
    })
    return data.sort((a, b) => b.hours - a.hours) // Sort descending
})

const technicianNames = computed(() => sortedTechnicianData.value.map(d => d.name))
const technicianHoursArray = computed(() => sortedTechnicianData.value.map(d => d.hours))

// Chart 1: Proactive vs Reactive (dynamic timeline based on timeFilter)
const chart1TimelineData = computed(() => {
    const labels: string[] = []
    const proactiveData: number[] = []
    const reactiveData: number[] = []
    const now = new Date()

    switch (timeFilter.value) {
        case 'Hoy': {
            for (let h = 0; h <= 23; h++) {
                labels.push(`${h.toString().padStart(2, '0')}:00`)
                const proCount = myServices.value.filter(s => {
                    if (!s.fechaAsignacion) return false
                    const d = new Date(s.fechaAsignacion)
                    return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate() && d.getHours() === h
                }).length
                const reactCount = myTickets.value.filter(t => {
                    if (!t.fechaSolicitud) return false
                    const d = new Date(t.fechaSolicitud)
                    return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate() && d.getHours() === h
                }).length
                proactiveData.push(proCount)
                reactiveData.push(reactCount)
            }
            break
        }
        case 'Esta Semana': {
            const startOfWeek = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay())
            const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']
            for (let i = 0; i <= 6; i++) {
                const d = new Date(startOfWeek)
                d.setDate(startOfWeek.getDate() + i)
                const dateString = `${d.getFullYear()}-${(d.getMonth() + 1).toString().padStart(2, '0')}-${d.getDate().toString().padStart(2, '0')}`
                labels.push(`${dayNames[d.getDay()]!} ${d.getDate()}`)
                const proCount = myServices.value.filter(s => s.fechaAsignacion && s.fechaAsignacion.startsWith(dateString)).length
                const reactCount = myTickets.value.filter(t => t.fechaSolicitud && t.fechaSolicitud.startsWith(dateString)).length
                proactiveData.push(proCount)
                reactiveData.push(reactCount)
            }
            break
        }
        case 'Este Mes': {
            const year = now.getFullYear()
            const month = now.getMonth()
            const daysInMonth = new Date(year, month + 1, 0).getDate()
            for (let day = 1; day <= daysInMonth; day++) {
                const dateString = `${year}-${(month + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`
                labels.push(day.toString())
                const proCount = myServices.value.filter(s => s.fechaAsignacion && s.fechaAsignacion.startsWith(dateString)).length
                const reactCount = myTickets.value.filter(t => t.fechaSolicitud && t.fechaSolicitud.startsWith(dateString)).length
                proactiveData.push(proCount)
                reactiveData.push(reactCount)
            }
            break
        }
        case 'Este Año': {
            const year = now.getFullYear()
            const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']
            for (let m = 0; m < 12; m++) {
                const monthStr = `${year}-${(m + 1).toString().padStart(2, '0')}`
                labels.push(monthNames[m]!)
                const proCount = myServices.value.filter(s => s.fechaAsignacion && s.fechaAsignacion.startsWith(monthStr)).length
                const reactCount = myTickets.value.filter(t => t.fechaSolicitud && t.fechaSolicitud.startsWith(monthStr)).length
                proactiveData.push(proCount)
                reactiveData.push(reactCount)
            }
            break
        }
        case 'Histórico': {
            const allDates = [
                ...myServices.value.map(s => s.fechaAsignacion).filter(Boolean),
                ...myTickets.value.map(t => t.fechaSolicitud).filter(Boolean)
            ] as string[]
            if (allDates.length === 0) {
                labels.push(now.getFullYear().toString())
                proactiveData.push(0)
                reactiveData.push(0)
            } else {
                const years = allDates.map(d => new Date(d).getFullYear())
                const minYear = Math.min(...years)
                const maxYear = Math.max(...years, now.getFullYear())
                for (let y = minYear; y <= maxYear; y++) {
                    const yearStr = y.toString()
                    labels.push(yearStr)
                    const proCount = myServices.value.filter(s => s.fechaAsignacion && s.fechaAsignacion.startsWith(yearStr)).length
                    const reactCount = myTickets.value.filter(t => t.fechaSolicitud && t.fechaSolicitud.startsWith(yearStr)).length
                    proactiveData.push(proCount)
                    reactiveData.push(reactCount)
                }
            }
            break
        }
    }

    return { labels, proactiveData, reactiveData }
})

// 4. ApexCharts Configurations
const chart1Series = computed(() => [
    { name: 'Programados/Proactivos', data: chart1TimelineData.value.proactiveData },
    { name: 'Tickets/Reactivos', data: chart1TimelineData.value.reactiveData }
])

const chart1Options = computed(() => ({
    chart: { type: 'area', toolbar: { show: false }, zoom: { enabled: false } },
    colors: ['#10B981', '#F43F5E'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    xaxis: {
        categories: chart1TimelineData.value.labels,
        type: 'category' as const,
        tickAmount: windowWidth.value < 640 ? 8 : undefined,
        labels: {
            rotate: -45,
            rotateAlways: false,
            hideOverlappingLabels: true,
            style: { 
                fontSize: windowWidth.value < 640 ? '9px' : '11px',
                fontFamily: 'Inter, sans-serif'
            }
        }
    },
    yaxis: {
        title: { text: 'Volumen' },
        min: 0,
        forceNiceScale: true
    },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
    legend: { position: 'top' },
    grid: { borderColor: '#f3f4f6', strokeDashArray: 4 }
}))

// chart2 (SLA radialBar) removed — replaced by CSS linear gauge in template

const chart3Series = computed(() => [{ name: 'Horas Invertidas', data: technicianHoursArray.value }])
const chart3Options = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false } },
    plotOptions: {
        bar: {
            horizontal: true,
            borderRadius: 4,
            columnWidth: '50%',
            distributed: true
        }
    },
    colors: ['#3B82F6', '#60A5FA', '#93C5FD', '#BFDBFE', '#DBEAFE'], // Gradient Blues
    dataLabels: { enabled: true },
    xaxis: {
        categories: technicianNames.value,
        title: { text: "Horas Trabajadas" }
    },
    legend: { show: false },
    grid: { borderColor: '#f3f4f6' }
}))

</script>
