<script setup lang="ts">
// @ts-nocheck
    import { ref, onMounted, computed } from 'vue'
    import { useRouter } from 'vue-router'
    import { Search, Filter, ChevronRight, X} from 'lucide-vue-next'


    // Import global data
    import { mockServices } from '../../../data/mockServicios'
import client from '../../../api/client'

    import type { Service } from '../../../data/mockServicios'

    const router = useRouter()
    const services = ref<Service[]>(mockServices)

    const statusLabel = (code: string) => ({ programado: 'Programado', en_proceso: 'En Proceso', realizado: 'Realizado', vencido: 'Vencido' } as any)[code] || code
    const priorityLabel = (code: string | null) => ({ alta: 'Alta', media: 'Media', baja: 'Baja' } as any)[code || ''] || code || ''
    const fmtShort = (iso?: string | null) => (iso ? String(iso).replace('T', ' ').slice(0, 16) : '')

    const fetchServices = async () => {
        try {
            const res = await client.get('/servicios', { params: { per_page: 100 } }).then(r => r.data)
            const list = res.data || res
            if (Array.isArray(list) && list.length) {
                services.value = list.map((x: any) => ({
                    id: x.id,
                    idDependencia: x.dependencia_id,
                    folio: x.folio,
                    asunto: x.asunto,
                    descripcion: x.descripcion || '',
                    categoria: x.categoria,
                    estatus: statusLabel(x.estatus),
                    prioridad: priorityLabel(x.prioridad),
                    fechaAsignacion: fmtShort(x.fecha_asignacion),
                    fechaVencimiento: fmtShort(x.fecha_vencimiento),
                    asignadoA: x.asignado ? `${x.asignado.nombre || ''} ${x.asignado.apellidos || ''}`.trim() : ''
                }))
            }
        } catch {}
    }
    onMounted(fetchServices)

    // Filter state
    const searchQuery = ref('')
    const statusFilter = ref('')
    const dateFilter = ref('')

    // Filtered services
    const filteredServices = computed(() => {
        return services.value.filter(service => {
            const q = searchQuery.value.toLowerCase()
            const matchesSearch = searchQuery.value === '' ||
                (service.asunto || '').toLowerCase().includes(q) ||
                (service.descripcion || '').toLowerCase().includes(q)

            const matchesStatus = statusFilter.value === '' || service.estatus === statusFilter.value

            const matchesDate = dateFilter.value === '' || (service.fechaVencimiento || '').slice(0, 10) === dateFilter.value

            return matchesSearch && matchesStatus && matchesDate
        })
    })


    // Active filters OKKK
    const hasActiveFilters = computed(() => {
        return searchQuery.value !== '' || 
               statusFilter.value !== '' || 
               dateFilter.value !== ''
    })

    // Cleaning filters OKK
    const clearFilters = () => {
        searchQuery.value = ''
        statusFilter.value = ''
        dateFilter.value = ''
    }

    // Navigation Details
    const viewServiceDetails = (id: number) => {
        router.push({ name: 'service-detail', params: { id: id } })
    }

    // Status coloring
    const getStatusInfo = (service: Service) => {
        if (!service.asignadoA || service.asignadoA === '') {
            return { text: 'Sin Asignar', pillClass: 'sin-asignar', borderClass: 'border-rojo' };
        }
        
        if (service.estatus === 'Programado') return { text: 'Programado', pillClass: 'programado', borderClass: 'border-verde' };
        if (service.estatus === 'En Proceso') return { text: 'En Proceso', pillClass: 'en-proceso', borderClass: 'border-azul' };
        if (service.estatus === 'Realizado') return { text: 'Realizado', pillClass: 'realizado', borderClass: 'border-gris' };
        if (service.estatus === 'Vencido') return { text: 'Vencido', pillClass: 'vencido', borderClass: 'border-rojo' };
        
        return { text: service.estatus, pillClass: '', borderClass: 'border-gris' };
    }

    // Priority Class
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

    const getDueDateWarning = (fechaVencimiento: string) => {
    // Current date for comparison
    const today = new Date()
    const due = new Date(fechaVencimiento)
    const diffTime = due.getTime() - today.getTime()
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

    // Red: Expired!
    if (diffDays < 0) return { text: '¡Vencido!', class: 'text-rojo' }
    // Orange: Due in 3 days or less!
    if (diffDays <= 3) return { text: `Vence en ${diffDays} días`, class: 'text-naranja' }
    // Gray: Safe
    return { text: `Vence el ${fechaVencimiento}`, class: 'text-gris' }
    }

</script>

<template>
    <div class="page-container-list-services">

        <!-- Header Services OKKK-->
        <div class="page-header">
            <h1>Gestión de Servicios Programados</h1>
        </div>

        <div class="filter-bar">

            <div class="search-box">
                <Search :size="18" class="icon" />
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Buscar servicio por asunto o descripción..."                    
                />
            </div>
            
            <div class="filters">

                <!-- Status Filter-->
                <div class="filter-item">
                    <Filter :size="16" class="icon" />
                    <select v-model="statusFilter">
                        <option value="">Todos los Estatus</option>
                        <option value="Programado">Programado</option>
                        <option value="En Proceso">En Proceso</option>
                        <option value="Realizado">Realizado</option>
                        <option value="Vencido">Vencido</option>
                    </select>
                </div>

                <!-- Date filter-->
                <div class="filter-item" style="min-width: 200px;">
                    <DateFilter v-model="dateFilter"/>
                </div>

                <!-- Active filter => Clean filters -->
                <button
                    v-if = "hasActiveFilters"
                    @click="clearFilters"
                    class="btn-clear"
                    title="Limpiar todos los filtros"
                >
                    <X :size="16"/>
                    Limpiar
                </button>
            </div>
        </div>

        <div class="table-container">
            <table>
                <!-- Head -->
                <thead>
                    <tr>
                        <th>Servicios</th>
                        <th class="col-detalles">Detalles del servicio</th>
                        <th>Asignado A:</th>
                        <th>Estatus</th>
                        <th></th>
                    </tr>
                </thead>

                <!-- Body -->
                <tbody>
                    <tr
                        v-for="service in filteredServices"
                        :key="service.id"
                        class="ticket-row"
                        :class="getStatusInfo(service).borderClass"
                        @click="viewServiceDetails(service.id)"
                    >
                        <!-- Dates -->
                        <td class="col-service">
                            <div class="folio">{{ service.folio }}</div>
                            <div class="date-text">Asig: {{ service.fechaAsignacion }}</div>
                            <div class="date-text" :class= "getDueDateWarning(service.fechaVencimiento).class" style="font-weight: 700; margin-top: 4px;">
                                {{ getDueDateWarning(service.fechaVencimiento).text }}
                            </div>
                        </td>

                        <!-- Details -->
                        <td class="col-detalles">

                            <!-- Asunto / Prioridad -->
                            <div class="asunto-container">
                                <span 
                                    class="priority-badge" 
                                    :class="getPriorityClass(service.prioridad)"
                                    :title="`Prioridad ${service.prioridad}`"
                                >
                                    {{ getPriorityDisplay(service.prioridad) }}
                                </span>
                                <div class="asunto">{{ service.asunto }}</div>
                            </div>

                            <!-- Description -->
                            <div class="descripcion">
                                {{ service.descripcion }}
                            </div>

                        </td>
                        
                        <!-- Assigned to -->
                        <td class="align-middle">
                            <div class="empleado">{{ service.asignadoA || 'Sin asignar' }}</div>
                        </td>
                        
                        <!-- Status Indicator-->
                        <td class="align-middle">
                            <span class="status-indicator" :class="getStatusInfo(service).pillClass">
                                {{ getStatusInfo(service).text }}
                            </span>
                        </td>

                        <!-- Align text (middle) -->
                        <td class="align-middle text-right">
                            <ChevronRight :size="20" class="chevron-icon" />
                        </td>

                    </tr>
                </tbody>
            </table>

            <div v-if="filteredServices.length === 0" class="empty-state">
                No se encontraron servicios con esos filtros...
            </div>

        </div>
    </div>
</template>

<style scoped lang="scss">

    .text-rojo { color: #ef4444; }
    .text-naranja { color: #f97316; }
    .text-gris { color: #64748b; }

    .border-rojo { border-left: 8px solid #ef4444; }
    .border-verde { border-left: 8px solid #22c55e; }
    .border-amarillo { border-left: 8px solid #eab308; }
    .border-azul { border-left: 8px solid #3b82f6; }
    .border-gris { border-left: 8px solid #94a3b8; }

    .status-indicator {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;

        &.sin-asignar { background: #fee2e2; color: #b91c1c; } 
        &.realizado { background: #c7d7ed; color: #4c5665; } /* Yellow */
        &.programado { background: #dcfce7; color: #15803d; } /* Green */   
        &.en-proceso { background: #e0f2fe; color: #0369a1; }  /* Blue */   
        &.vencido { background: #fee2e2; color: #b91c1c; }    /* Red */  
    }

    .align-middle {
        vertical-align: middle !important;
    }

    .page-container-list-services {
        max-width: 1200px;
        margin: 0 auto;
    }

    .page-header{
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;

        h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f172a;
        }
    }

    .table-container {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);

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

        &.p-alta { color: #ef4444; background: #fee2e2; }
        &.p-media { color: #f97316; background: #ffedd5; }
        &.p-baja { color: #64748b; background: #f1f5f9; }
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

    .filter-bar{
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

    .filters{
        display: flex;
        gap: 1rem;
    }

    .filter-item{
        position: relative;
        display: flex;
        align-items: center;

        .icon {
            position: absolute;
            left: 10px;
            color: #64748b;
            pointer-events: none;
        }

        select, input[type="date"] {
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

    .empty-state {
        padding: 3rem;
        text-align: center;
        color: #64748b;
        font-style: italic;
    }

    .text-right {
        text-align: right;
        padding-right: 1.5rem;
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
        .filters, .search-box {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            width: 100%;
            gap: 1rem;
        }
        .filter-item, .filter-item select, .filter-item input, .search-box input, .btn-clear {
            width: 100%;
        }
        .table-container {
            background: transparent;
            border: none;
            box-shadow: none;
        }
        table, thead, tbody, th, td, tr {
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