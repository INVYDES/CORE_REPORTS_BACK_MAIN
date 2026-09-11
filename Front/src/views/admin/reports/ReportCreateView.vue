<script setup lang="ts">
// @ts-nocheck
    import { reactive, computed, ref, onMounted } from 'vue';
    import { useRouter } from 'vue-router'
    const router = useRouter()

    import { useAuthStore } from '../../../stores/authStore';
    import { storeToRefs } from 'pinia';

    const authStore = useAuthStore();
    const { currentUser } = storeToRefs(authStore);

    if (!currentUser.value || (currentUser.value?.rol !== 1 && currentUser.value?.rol !== 3)) {
        router.push({ name: 'dashboard' })
    }

    import { getDependenciaName } from '../../../data/mockDependencias';
    import type { ReporteMaterial, EvidenciaFotografica } from '../../../data/mockReportes';
        import { ticketsApi } from '../../../api/tickets';
    import { serviciosApi } from '../../../api/servicios';
    import { areasApi } from '../../../api/areas';
    import client from '../../../api/client';
    import { useToast } from 'vue-toastification';
    import { Image as ImageIcon, Upload, X as XIcon, Camera } from 'lucide-vue-next'
    const toast = useToast();

    const props = defineProps<{ isModal?: boolean }>()
    const emit = defineEmits<{ (e: 'close'): void; (e: 'created'): void }>()
    import { mockSubdependencias as fallbackAreas } from '../../../data/mockDependencias';
    import { mockUsers as fallbackUsers } from '../../../data/mockUsers';
    
    const formData = reactive(
        {
        tipoReporte: 'Ticket',
        origenDatosId: null as number | null,
        categoria: 'Mantenimiento Correctivo' as 'Mantenimiento Preventivo' | 'Mantenimiento Correctivo' | 'Instalación' | 'Mejora' | 'Diagnóstico',
        fallaReportada: '',
        conclusionTrabajo: '',
        fechaElaboracion: new Date().toISOString().slice(0, 16),
        idSubdependencia: null as number | null,
        responsableTexto: '',
        cargoTexto: '',
        ejecutoresIds: [] as number[],
        horaSalidaBase: '',
        horaLlegadaSitio: '',
        fechaHoraInicio: '',
        fechaHoraFin: '',
        horaRegresoABase: '',
        desarrolloActividades: '',
        materiales: [] as ReporteMaterial[],
        evidenciaFotografica: [] as EvidenciaFotografica[],
        esRetrabajo: false,
        estatusReporte: 'Abierto / Trabajo Parcial'
        }
    )

    const dependencyName = computed(() =>
        (currentUser.value as any)?.dependencia?.nombre || getDependenciaName((currentUser.value as any).idDependencia ?? (currentUser.value as any).dependencia_id)
    )

    const realAreas = ref<any[]>([])
    const availableSubareas = computed(() => {
        if (realAreas.value.length) return realAreas.value
        return fallbackAreas.filter((s: any) => s.idDependencia === ((currentUser.value as any).idDependencia ?? (currentUser.value as any).dependencia_id) && s.activo)
    })
    onMounted(async ()=>{
        try {
            const res = await areasApi.list()
            const list = res.data || res
            realAreas.value = (Array.isArray(list) ? list : []).map((a:any)=> ({ idSubdependencia: a.id, idDependencia: a.dependencia_id, nombre: a.nombre, activo: a.activa }))
        } catch {}
        try {
            const t = await ticketsApi.list({ per_page: 100 })
            const list = t.data || t
            availableTicketsReal.value = Array.isArray(list) ? list : []
        } catch {}
        try {
            const s = await serviciosApi.list({ per_page: 100 })
            const list = s.data || s
            availableServicesReal.value = Array.isArray(list) ? list : []
        } catch {}
        try {
            const u = await client.get('/usuarios', { params:{ per_page:100 } }).then(r=>r.data)
            const list = u.data || u
            availableTechniciansReal.value = Array.isArray(list) ? list.filter((x:any)=> [1,3].includes(x.rol)) : []
        } catch {}
    })

    const availableTicketsReal = ref<any[]>([])
    const availableServicesReal = ref<any[]>([])
    const availableTechniciansReal = ref<any[]>([])

    const getAsuntoFallback = (tipo:string, id:number) => {
        if (tipo==='Ticket') {
            const r = availableTicketsReal.value.find((t:any)=> t.id===id)
            return r ? r.asunto : 'Ticket no encontrado'
        } else {
            const r = availableServicesReal.value.find((s:any)=> s.id===id)
            return r ? r.asunto : 'Servicio no encontrado'
        }
    }

    const asuntoOrigen = computed(() => {
        if(!formData.origenDatosId) return 'Seleccione un origen de datos...'
        return getAsuntoFallback(formData.tipoReporte, formData.origenDatosId)
    })

    const ROLE_TO_CARGO: Record<number, string> ={
        0: 'Default',
        1: 'Admin. de Técnicos',
        2: 'Administrador Comercial',
        3: 'Técnico'
    }

    const availableTechnicians = computed(() => {
        if (availableTechniciansReal.value.length) return availableTechniciansReal.value.map((u:any)=> ({ ...u, idUsuario: u.id, idDependencia: u.dependencia_id, nombre: u.nombre, apellidos: u.apellidos, rol: u.rol, numeroEmpleado: u.numero_empleado }))
        return fallbackUsers.filter((u: any) => u.idDependencia === ((currentUser.value as any).idDependencia ?? (currentUser.value as any).dependencia_id) && (u.rol === 3 || u.rol === 1))
    })

    const getEjecutorData = (id:number) => {
        const real = availableTechnicians.value.find((u: any) => (u.idUsuario||u.id) === id)
        if (real) return real
        return fallbackUsers.find((u: any) => u.idUsuario === id)
    }

    const totalHorasCalculadas = computed(() =>
    {
        if(!formData.fechaHoraInicio || !formData.fechaHoraFin) return '0.0 hrs'
        
        const start = new Date(formData.fechaHoraInicio).getTime()
        const end = new Date(formData.fechaHoraFin).getTime()

        const diffMs = end - start
        if(diffMs < 0) return '0.0 hrs' // Prevent negative time

        const hours = diffMs / (1000 * 60 * 60)
        return `${hours.toFixed(2)} hrs`
    }
    )

    const addEjecutor = () => {
        if (formData.ejecutoresIds.length < 4) {
            formData.ejecutoresIds.push(0)
        }
    }

    const removeEjecutor = (index: number) => {
        formData.ejecutoresIds.splice(index, 1)
    }

    const getEjecutorCargo = (id: number) => {
    const user = getEjecutorData(id)
    return user ? (ROLE_TO_CARGO[user.rol] || 'Sin Cargo') : '...'
    }

    const addMaterialRow = () => {
        formData.materiales.push({
            idItem: formData.materiales.length + 1,
            material: '',
            cantidad: 1,
            unidad: 'Pieza'
        })
    }

    const removeMaterialRow = (index: number) => {
        formData.materiales.splice(index, 1)
        formData.materiales.forEach((m, i) => {
            m.idItem = i + 1
        })
    }

    const availableTickets = computed(() => {
        if (availableTicketsReal.value.length) return availableTicketsReal.value.map((t:any)=> ({ id: t.id, folio: t.folio, asunto: t.asunto, idDependencia: t.dependencia_id }))
        return []
    })
    const availableServices = computed(() => {
        if (availableServicesReal.value.length) return availableServicesReal.value.map((s:any)=> ({ id: s.id, folio: s.folio, asunto: s.asunto, idDependencia: s.dependencia_id }))
        return []
    })

    const isSaving = ref(false)
    const pendingFiles = ref<File[]>([])

    const saveReport = async () => {
        if (isSaving.value) return
        isSaving.value = true
        try {
            const { mapReporteForBackend, buildReporteFormData } = await import('../../../api/mapper')
            const mapped = mapReporteForBackend(formData, pendingFiles.value)
            if (!formData.responsableTexto.trim()) {
                toast.error('Escribe un Responsable en la Sección 2')
                isSaving.value = false
                return
            }
            const fd = await buildReporteFormData(mapped, client)
            if (formData.conclusionTrabajo) fd.append('desarrollo', (formData.desarrolloActividades || '') + '\nConclusión: ' + formData.conclusionTrabajo)
            const res = await client.post('/reportes', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
            toast.success(`¡Éxito! El reporte ${res.data.folio} ha sido guardado. El back ya sincronizó Ticket/Servicio automáticamente.`)
            if (props.isModal) { emit('created'); emit('close') } else router.push({ name: 'dashboard' })
        } catch (e:any) {
            const msg = e?.message || e?.response?.data?.message || JSON.stringify(e?.response?.data?.errors) || 'Error al guardar reporte'
            toast.error(msg)
        } finally { isSaving.value = false }
    }

const evidenFileInput = ref<HTMLInputElement | null>(null)
const isDragOver = ref(false)
const triggerFileInput = () => evidenFileInput.value?.click()
const handleDragOver = () => { isDragOver.value = true }
const handleDragLeave = () => { isDragOver.value = false }
const handleDrop = (event: DragEvent) => {
    isDragOver.value = false
    const files = event.dataTransfer?.files
    if (!files) return
    const fakeEvent = { target: { files } } as unknown as Event
    onFileChange(fakeEvent)
}

const autoResize = (event: Event) => {
    const target = event.target as HTMLTextAreaElement;
    target.style.height = 'auto';
    target.style.height = `${target.scrollHeight}px`;
};

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement
    if (!input.files) return
    const remaining = 10 - formData.evidenciaFotografica.length
    if (remaining <= 0) {
        alert('Máximo 10 fotos permitidas.')
        input.value = ''
        return
    }
    const filesToAdd = Array.from(input.files).slice(0, remaining)
    for (const file of filesToAdd) {
        const blobUrl = URL.createObjectURL(file)
        formData.evidenciaFotografica.push({ url: blobUrl, comentario: '' })
        pendingFiles.value.push(file) // mapper.js enviará este File real
    }
    input.value = ''
}

const removeEvidence = (index: number) => {
    const item = formData.evidenciaFotografica[index]
    if (item && item.url && item.url.startsWith('blob:')) URL.revokeObjectURL(item.url)
    formData.evidenciaFotografica.splice(index, 1)
    pendingFiles.value.splice(index, 1)
}

const previewUrl = ref<string | null>(null)
</script>


<template>
    <div class="page-container-create-report">
        <!-- Header -->
        <div class="header-actions">
            <h2>
                Crear Nuevo Reporte Técnico
            </h2>
            <button v-if="isModal" @click="emit('close')" class="btn-close-modal" title="Cerrar">×</button>
        </div>

        <form class="report-form" @submit.prevent>
            
            <!--Section 1: Type -->
            <div class="form-section">
                <!-- Section title-->
                <h3 class="section-title">
                    Sección 1 — Tipo
                </h3>
                
                <!-- Report type, report number, elaboration date-->
                <div class="grid-2-cols">
                    <!-- Tipo de Reporte -->
                    <div class="form-group">
                        <label>Tipo de reporte *</label>
                        <select v-model="formData.tipoReporte">
                            <option value="Ticket">Ticket — falla reportada por cliente</option>
                            <option value="Servicio Programado">Servicio Programado / Proyecto</option>
                            <option value="Libre">Libre — sin ticket ni servicio</option>
                        </select>
                        <span class="format-hint">Formato: Ticket/Servicio/Libre — define si el reporte cierra un Ticket/Servicio</span>
                    </div>

                    <!-- Fecha de elaboración -->
                    <div class="form-group">
                        <label>Fecha de elaboración *</label>
                        <input type="datetime-local" v-model="formData.fechaElaboracion" />
                        <span class="format-hint">Formato: AAAA-MM-DD HH:MM — ej. 2026-09-05 14:30</span>
                    </div>

                    <!-- Categoría del Trabajo -->
                    <div class="form-group">
                        <label>Categoría del Trabajo *</label>
                        <select v-model="formData.categoria" required>
                            <option value="" disabled>Seleccione una categoría...</option>
                            <option value="Mantenimiento Preventivo">Mantenimiento Preventivo</option>
                            <option value="Mantenimiento Correctivo">Mantenimiento Correctivo</option>
                            <option value="Instalación">Instalación</option>
                            <option value="Mejora">Mejora</option>
                            <option value="Diagnóstico">Diagnóstico</option>
                        </select>
                        <span class="format-hint">Ej: Correctivo = reparación de falla, Preventivo = mantenimiento programado</span>
                    </div>

                    <!-- Número de reporte -->
                    <div class="form-group">
                        <label>Número de reporte</label>
                        <input type="text" value="Generado al guardar..." disabled class="input-disabled" />
                        <span class="format-hint">Formato autogenerado: REP-2026-001</span>
                    </div>

                </div>

                <!-- Origen de datos dropdown list selection-->
                <div class="data-origin-box" :class="{ 'disabled-box': formData.tipoReporte === 'Libre' }">
                    <!-- Subtitle-->
                    <h4>
                        Origen de datos
                    </h4>
                    
                    <div class="form-group" v-if="formData.tipoReporte !== 'Libre'">
                        <!-- Text: Selecciona ID-->
                        <label>
                            Selecciona ID de {{ formData.tipoReporte }}
                        </label>
                        
                        <!-- Select data function / Data source-->
                        <select v-model="formData.origenDatosId">
                            
                            <!-- First Option: Null - Select an option-->
                            <option :value="null">
                                Selecciona una opción...
                            </option>
                            
                            <!-- Second Option: Ticket -->
                            <template v-if="formData.tipoReporte === 'Ticket'">
                                <!-- v-for: Show ticket folio and asunto-->
                                <option v-for="ticket in availableTickets" :key="ticket.id" :value="ticket.id">
                                    {{ ticket.folio }} - {{ ticket.asunto }}
                                </option>
                            </template>

                            <!--Third option: Service-->
                            <template v-if="formData.tipoReporte === 'Servicio Programado'">
                                <!-- v-for: Show service folio and asunto-->
                                <option v-for="servicio in availableServices" :key="servicio.id" :value="servicio.id">
                                    {{ servicio.folio }} - {{ servicio.asunto }}
                                </option>
                            </template>

                        </select>
                        <span class="format-hint">Formato: selecciona el folio origen (ej. TKT-2026-004) — al cerrar se actualiza a Resuelto/Realizado</span>
                    </div>
                    <p v-else class="text-muted">Reporte libre seleccionado. No requiere origen de datos. Formato libre.</p>
                </div>

                <!-- Es Retrabajo? (Bottom of section) -->
                <div class="checkbox-group-horizontal mt-4">
                    <input type="checkbox" v-model="formData.esRetrabajo" id="esRetrabajo" class="w-auto" />
                    <label for="esRetrabajo" class="cursor-pointer mb-0">¿Es un Retrabajo?</label>
                </div>

            </div>

            <!-- Section 2: Client-->
            <div class="form-section">

                <!-- Header Title -->
                <h3 class="section-title">
                    Sección 2 — Cliente
                </h3>

                <!-- Enterprise, responsable, cargo-->
                <div class="grid-2-cols">

                    <!-- Enterprise-->
                    <div class="form-group">
                        <label>Empresa</label>
                        <input type="text" :value="dependencyName" disabled class="input-disabled" />
                    </div>

                    <!-- Subarea dropdown -->
                    <div class="form-group">
                        <label>Dependencia / Área</label>
                        <select v-model="formData.idSubdependencia">
                            <option :value="null">Selecciona un área...</option>
                            <option v-for="area in availableSubareas" :key="area.idSubdependencia" :value="area.idSubdependencia">
                                {{ area.nombre }}
                            </option>
                        </select>
                    </div>

                    <!-- Responsable-->
                    <div class="form-group">
                        <label>Responsable *</label>
                        <input type="text" v-model="formData.responsableTexto" placeholder="Ej. Ing. Juan Pérez" />
                        <span class="format-hint">Formato: Nombre completo de quien recibe el trabajo — ej. Ing. Juan Pérez</span>
                    </div>

                    <!-- Cargo responsable -->
                    <div class="form-group">
                        <label>Cargo</label>
                        <input type="text" v-model="formData.cargoTexto" placeholder="Ej. Gerente de Planta" />
                        <span class="format-hint">Formato: Cargo/puesto — ej. Gerente de Producción, Supervisor de Turno</span>
                    </div>

                </div>

            </div>

            <!-- Section 3: Executors -->
            <div class="form-section">

                <!-- Header title-->
                <h3 class="section-title">
                    Sección 3 — Ejecutores
                </h3>

                <!-- Executors container -->
                <div class="ejecutores-container">  

                    <!-- Row for each executor -->               
                    <div v-for="(id, index) in formData.ejecutoresIds" :key="index" class="ejecutor-row">
                        
                        <!-- Dropdown list to select executor: OPENNN -->
                        <div class="form-group flex-grow">
                            <select v-model="formData.ejecutoresIds[index]" class="w-full">
                                <option :value="0" disabled>Selecciona un ejecutor...</option>
                                <option v-for="tech in availableTechnicians" :key="tech.idUsuario" :value="tech.idUsuario">
                                    {{ tech.nombre }} {{ tech.apellidos }}
                                </option>
                            </select>
                        </div>

                        <!-- Auto-filled employee number -->
                        <div class="form-group w-32">
                            <input type="text" 
                                   :value="getEjecutorData(id)?.numeroEmpleado || '---'" 
                                   disabled class="input-disabled text-center" />
                        </div>

                        <!-- Auto-filled employee number -->
                        <div class="form-group w-48">
                            <input type="text" 
                                   :value="getEjecutorCargo(id)" 
                                   disabled class="input-disabled text-center" />
                        </div>

                        <!-- Remove button -->
                        <button class="btn-remove" @click="removeEjecutor(index)" title="Eliminar ejecutor">
                            X
                        </button>

                    </div>

                    <!-- Executors actions -->
                    <div class="ejecutores-actions">
                        <!-- Button to add executor -->
                        <button class="btn-outline" @click="addEjecutor" :disabled="formData.ejecutoresIds.length >= 4">
                            Agregar ejecutor
                        </button>
                        <!-- Text: Up to 4 executors-->
                        <span class="text-muted small">
                            Hasta 4 ejecutores en total. (Actual: {{ formData.ejecutoresIds.length }}) — Formato: Nombre + No. Empleado (ej. EMP-004) + Cargo
                        </span>
                    </div>

                </div>
            </div>

            <!-- Section 4: Generals (Date and name of the activitie)-->
            <div class="form-section">
                <!-- Header title -->
                <h3 class="section-title">
                    Sección 4 — Generales
                </h3>
                
                <!-- Date and time of the activity, name of the activity (autofilled)-->
                <div class="grid-3-cols mb-1-5">
                    <div class="form-group">
                        <label>Hora de salida base</label>
                        <input type="datetime-local" v-model="formData.horaSalidaBase" />
                        <span class="format-hint">Formato: AAAA-MM-DD HH:MM — ej. 2026-09-05 08:00 (salida del taller)</span>
                    </div>

                    <div class="form-group">
                        <label>Hora de llegada a sitio</label>
                        <input type="datetime-local" v-model="formData.horaLlegadaSitio" />
                        <span class="format-hint">Formato: AAAA-MM-DD HH:MM — ej. 2026-09-05 09:15 (llegada cliente)</span>
                    </div>

                    <div class="form-group">
                        <label>Hora de inicio trabajo *</label>
                        <input type="datetime-local" v-model="formData.fechaHoraInicio" />
                        <span class="format-hint">Obligatorio — Formato: AAAA-MM-DD HH:MM — ej. 2026-09-05 09:30</span>
                    </div>
                </div>

                <div class="grid-3-cols">
                    <div class="form-group">
                        <label>Hora de fin trabajo *</label>
                        <input type="datetime-local" v-model="formData.fechaHoraFin" />
                        <span class="format-hint">Obligatorio — debe ser posterior a inicio — ej. 2026-09-05 13:30</span>
                    </div>

                    <div class="form-group">
                        <label>Hora de regreso a base *</label>
                        <input type="datetime-local" v-model="formData.horaRegresoABase" />
                        <span class="format-hint">Obligatorio al cerrar (finalizado) — Formato: AAAA-MM-DD HH:MM — ej. 2026-09-05 15:00</span>
                    </div>

                    <!-- Total hours calculated -->
                    <div class="form-group">
                        <label>Horas de trabajo neto</label>
                        <input type="text" :value="totalHorasCalculadas" disabled class="input-disabled highlight-box" />
                    </div>
                </div>

                <!-- F/S/Activitie label - Autofilled based on data origin or user input -->
                <div class="form-group mt-1-5">
                    <label>Descripción de la falla reportada</label>
                    <input v-if="formData.tipoReporte !== 'Libre'" type="text" :value="asuntoOrigen" disabled class="input-disabled" />
                    <input v-else type="text" v-model="formData.fallaReportada" placeholder="Escribe la descripción de la falla..." />
                </div>
            </div>            

            <!-- Section 5: Materials and resources list -->
            <div class="form-section">

                <!-- Header title -->
                <h3 class = "section-title">
                    Sección 5 — Desarrollo
                </h3>

                <!-- Activities resume -->
                <div class="form-group">

                    <!-- Label -->
                    <label>
                        Descripción de actividades
                    </label>
                    
                    <!-- Text area -->
                    <textarea
                        v-model="formData.desarrolloActividades"
                        rows="4"
                        @input="autoResize"
                        class="overflow-hidden"
                        placeholder="Describe las actividades realizadas con detalle...">
                    </textarea>
                    <span class="format-hint">Formato: texto libre detallado — ej. "Cambio de balata delantera, purga de sistema hidráulico, prueba de ruta 5km"</span>

                </div>

                <!-- Materials container -->
                <div class="materials-container mt-1-5">

                    <!-- Header title and button -->
                    <div class="section-header-flex">

                        <!-- Label -->
                        <label class="font-bold">
                            Lista de materiales
                        </label>

                        <!-- Button -->
                        <button type="button" class="btn-outline btn-sm" @click="addMaterialRow">
                            Agregar material
                        </button>

                    </div>
                    <span class="format-hint">Formato: Material del catálogo (ej. Cable AWG 12) + Cantidad (ej. 2.5) + Unidad (Pza/Metros/Litros) — descuenta stock automáticamente</span>
                    
                    <!-- Materials table -->
                    <div class="materials-table">

                        <!-- Headers table -->
                        <div class="materials-header">
                            <div class="col-item text-center">Item</div>
                            <div class="col-mat">Material</div>
                            <div class="col-cant text-center">Cantidad</div>
                            <div class="col-uni">Unidad</div>
                            <div class="col-acc text-center">Acción</div>
                        </div>
                        
                        <!-- Material rows -->
                        <div v-for="(mat, index) in formData.materiales" :key="index" class="material-row">
                            
                            <!-- ID -->
                            <div class="col-item text-center">
                                {{ mat.idItem }}
                            </div>
                            
                            <!-- Material name -->
                            <div class="col-mat">
                                <input type="text" v-model="mat.material" class="w-full" placeholder="Ej. Cable AWG 12" />
                            </div>

                            <!-- Material Quantity-->
                            <div class="col-cant">
                                <input type="number" v-model="mat.cantidad" class="w-full text-center" min="1" />
                            </div>

                            <!-- Unit -->
                            <div class="col-uni">
                                <select v-model="mat.unidad" class="w-full">
                                    <option value="Pza">Pza</option>
                                    <option value="Metros">Metros</option>
                                    <option value="Litros">Litros</option>
                                    <option value="Kgs">Kgs</option>
                                    <option value="Lote">Lote</option>
                                </select>
                            </div>
                            
                            <!-- Remove material button -->
                            <div class="col-acc flex-center">
                                <button type="button" class="btn-remove btn-sm-remove" @click="removeMaterialRow(index)">X</button>
                            </div>
                        </div>

                        <!-- If empty -->
                        <div v-if="formData.materiales.length === 0" class="empty-state">
                            No se han agregado materiales. Haz clic en "Agregar material" si utilizaste refacciones.
                        </div>

                    </div>
                </div>

                <!-- Photos container -->
                <div class="form-group mt-1-5">

                    <label>Fotos (máx 10) — Evidencias fotográficas</label>

                    <input ref="evidenFileInput" type="file" multiple accept="image/*" @change="onFileChange" class="hidden-file-input" />

                    <div class="file-drop-zone" :class="{ 'drag-over': isDragOver, 'has-files': formData.evidenciaFotografica.length > 0 }"
                         @click="triggerFileInput" @dragover.prevent="handleDragOver" @dragleave="handleDragLeave" @drop.prevent="handleDrop">
                        <div class="drop-icon-wrap">
                            <div class="drop-icon">
                                <ImageIcon :size="28" />
                            </div>
                            <div class="drop-icon-badge"><Camera :size="12" /></div>
                        </div>
                        <div class="drop-text">
                            <span class="drop-title">Arrastra imágenes aquí o <span class="drop-link">haz clic para seleccionar</span></span>
                            <span class="drop-subtitle">JPG · PNG · WebP — máx 10 fotos · ≤5MB c/u · {{ formData.evidenciaFotografica.length }}/10 — Formato back: <code>evidencias[]</code></span>
                        </div>
                        <div class="drop-action">
                            <span class="btn-drop"><Upload :size="14" /> Seleccionar</span>
                        </div>
                    </div>

                    <!-- Preview grid -->
                    <div class="evidence-preview-grid" v-if="formData.evidenciaFotografica.length > 0">
                        <div class="evidence-item" v-for="(item, idx) in formData.evidenciaFotografica" :key="idx">
                            <div class="evidence-thumb" @click="previewUrl = item.url">
                                <img :src="item.url" alt="Evidencia" title="Haz clic para previsualizar" />
                                <button type="button" class="btn-remove-thumb" @click.stop="removeEvidence(idx)">&times;</button>
                            </div>
                            <textarea
                                v-model="item.comentario"
                                placeholder="Escribe un comentario o descripción de la foto..."
                                class="evidence-comment-input form-control"
                                rows="2"
                                @input="autoResize"
                            ></textarea>
                        </div>
                    </div>

                </div>

                <!-- tipoReporte-->
                <div class="form-group mt-1-5" v-if="formData.tipoReporte !== 'Libre'">
                    
                    <!-- Subtitle -->
                    <label>Estatus del reporte (solo Ticket/Servicio)</label>

                    <!-- DRopdown list -->
                    <select v-model="formData.estatusReporte">
                        <option value="Abierto / Trabajo Parcial">Abierto (no se finaliza)</option>
                        <option value="Cerrado / Trabajo Finalizado">Cerrado (finalizado)</option>
                    </select>

                    <!-- Text -->
                    <span class="text-muted small mt-1">
                        Si eliges "Cerrado (finalizado)", el sistema cerrará automáticamente el Ticket/Servicio origen.
                    </span>

                </div>

                <!-- Conclusion -->
                <div class="form-group mt-1-5">
                    <label>Conclusión del trabajo</label>
                    <textarea
                        v-model="formData.conclusionTrabajo"
                        rows="3"
                        @input="autoResize"
                        class="overflow-hidden"
                        placeholder="Comentarios sobre la conclusión del trabajo...">
                    </textarea>
                </div>
            </div>

            <div class="form-actions">
                <button v-if="isModal" type="button" @click="emit('close')" class="btn-cancelar">Cancelar</button>
                <button class="btn-primary" @click="saveReport" :disabled="isSaving">
                    {{ isSaving ? 'Guardando...' : 'Guardar Reporte' }}
                </button>
            </div>

        </form>

        <!-- Image Preview Modal -->
        <div class="modal-overlay" v-if="previewUrl" @click="previewUrl = null">
            <div class="modal-content-img" @click.stop>
                <button class="btn-close-modal" @click="previewUrl = null">&times;</button>
                <img :src="previewUrl" class="img-preview-full" alt="Vista previa detallada" />
            </div>
        </div>

    </div>
</template>

<style scoped>
/* Basic layout */
.page-container-create-report { padding: 1rem; max-width: 1200px; margin: 0 auto; }
@media (min-width: 768px) {
    .page-container-create-report { padding: 2rem; }
}

.header-actions { display: flex; flex-direction: column; gap: 1rem; align-items: stretch; margin-bottom: 2rem; }
@media (min-width: 768px) {
    .header-actions { flex-direction: row; justify-content: space-between; align-items: center; }
}

.form-actions { display: flex; justify-content: flex-end; margin-top: 2rem; }

.btn-primary { background: #3b82f6; color: white; padding: 0.75rem 1rem; border-radius: 6px; border: none; cursor: pointer; width: 100%; transition: background 0.2s; font-size: 1rem; font-weight: 600; }
.btn-primary:hover { background: #2563eb; }
@media (min-width: 768px) {
    .btn-primary { width: auto; padding: 0.5rem 1rem; }
}

.form-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; }
@media (min-width: 768px) {
    .form-section { padding: 1.5rem; margin-bottom: 2rem; }
}

.section-title { margin-top: 0; margin-bottom: 1.5rem; color: #0f172a; font-size: 1.25rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; }
.section-header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.5rem; padding-bottom: 0.5rem; }

.grid-2-cols { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.2rem; }
@media (min-width: 768px) {
    .grid-2-cols { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.5rem; }
}

.grid-3-cols { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.2rem; }
@media (min-width: 768px) {
    .grid-3-cols { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.5rem; }
}

.form-group { display: flex; flex-direction: column; gap: 0.5rem; min-width: 0; }
label { font-size: 0.875rem; font-weight: 600; color: #475569; }

input, select { padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; background: white; width: 100%; max-width: 100%; box-sizing: border-box; min-width: 0; }
.input-disabled { background: #e2e8f0; color: #64748b; cursor: not-allowed; }

.data-origin-box { margin-top: 1.5rem; padding: 1.5rem; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; }
.disabled-box { opacity: 0.6; pointer-events: none; background: #f1f5f9; }
.text-muted { color: #64748b; }
.small { font-size: 0.875rem; }
.format-hint { font-size: 0.72rem; color: #64748b; font-style: italic; margin-top: 0.25rem; display: block; line-height: 1.4; background: #f8fafc; border-left: 2px solid #cbd5e1; padding: 0.25rem 0.5rem; border-radius: 2px; }
.hidden-file-input { display: none; }
.file-drop-zone {
    display: flex; align-items: center; gap: 1rem; padding: 1.25rem 1.5rem;
    border: 2px dashed #cbd5e1; border-radius: 12px; background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    cursor: pointer; transition: all 0.25s ease; position: relative; overflow: hidden;
}
.file-drop-zone:hover { border-color: #3b82f6; background: #eff6ff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.15); }
.file-drop-zone.drag-over { border-color: #3b82f6; background: #dbeafe; border-style: solid; transform: scale(1.01); box-shadow: 0 8px 20px rgba(59,130,246,0.2); }
.file-drop-zone.has-files { border-color: #10b981; background: #f0fdf4; }
.drop-icon-wrap { position: relative; flex-shrink: 0; }
.drop-icon { width: 56px; height: 56px; border-radius: 12px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
.file-drop-zone.has-files .drop-icon { background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
.drop-icon-badge { position: absolute; bottom: -4px; right: -4px; width: 22px; height: 22px; border-radius: 50%; background: #f59e0b; color: white; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
.drop-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.25rem; }
.drop-title { font-weight: 600; color: #0f172a; font-size: 0.95rem; }
.drop-link { color: #3b82f6; text-decoration: underline; text-underline-offset: 2px; }
.drop-subtitle { font-size: 0.78rem; color: #64748b; }
.drop-subtitle code { background: #e2e8f0; padding: 0.1rem 0.3rem; border-radius: 4px; font-size: 0.7rem; }
.drop-action { flex-shrink: 0; }
.btn-drop { display: inline-flex; align-items: center; gap: 0.4rem; background: #0f172a; color: white; padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.85rem; transition: background 0.2s; }
.file-drop-zone:hover .btn-drop { background: #1e293b; }
@media (max-width: 768px) { .file-drop-zone { flex-direction: column; text-align: center; } .drop-action { width: 100%; } .btn-drop { width: 100%; justify-content: center; } }

/* Layout Utilities */
.w-full { width: 100%; max-width: 100%; min-width: 0;}
.w-32 { width: 100%; box-sizing: border-box; min-width: 0;}
.w-48 { width: 100%; box-sizing: border-box; min-width: 0;}
.flex-grow { flex-grow: 1; min-width: 0; }
.text-center { text-align: center; }
.mt-1-5 { margin-top: 1.2rem; }
@media (min-width: 768px) {
    .mt-1-5 { margin-top: 1.5rem; }
}
.mt-1 { margin-top: 0.5rem; display: block; }

@media (min-width: 768px) {
    .w-32 { width: 8rem; }
    .w-48 { width: 12rem; }
}

/* Horizontal Checkbox Layout */
.checkbox-group-horizontal { display: flex; flex-direction: row; align-items: center; gap: 0.75rem; }
.checkbox-group-horizontal input[type="checkbox"] { width: 1.25rem; height: 1.25rem; margin: 0; }
.checkbox-group-horizontal label { margin: 0; white-space: nowrap; }

/* Ejecutores Rows */
.ejecutores-container { display: flex; flex-direction: column; gap: 1rem; padding: 1rem; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; }
.ejecutor-row { display: flex; flex-direction: column; gap: 1rem; align-items: stretch; background: #f8fafc; padding: 1rem; border-radius: 6px; border: 1px solid #e2e8f0; }

@media (min-width: 768px) {
    .ejecutor-row { flex-direction: row; align-items: center; padding: 0.75rem; }
}

/* Buttons */
.btn-remove { background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; border-radius: 6px; width: 100%; height: 44px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
.btn-remove:hover { background: #fecaca; }

@media (min-width: 768px) {
    .btn-remove { width: 36px; height: 36px; flex-shrink: 0; }
}

.ejecutores-actions { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem; }
.btn-outline { background: transparent; border: 1px solid #3b82f6; color: #3b82f6; padding: 0.75rem 1rem; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; width: 100%; }
.btn-outline:hover:not(:disabled) { background: #eff6ff; }
.btn-outline:disabled { border-color: #cbd5e1; color: #94a3b8; cursor: not-allowed; }

@media (min-width: 768px) {
    .ejecutores-actions { flex-direction: row; align-items: center; gap: 1rem; }
    .btn-outline { width: auto; padding: 0.5rem 1rem; }
}

/* Highlight Box for the Math */
.highlight-box { font-weight: bold; color: #0f172a; background: #e0f2fe; border-color: #7dd3fc; }

/* Section 5 Specific Styles */
textarea { width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; resize: vertical; box-sizing: border-box; overflow: hidden; background: #ffffff; }
.font-bold { font-weight: 600; color: #475569; }
.btn-sm { padding: 0.5rem 0.75rem; font-size: 0.875rem; border-width: 1px; }

@media (min-width: 768px) {
    .btn-sm { padding: 0.25rem 0.75rem; }
}

.btn-sm-remove { width: 36px; height: 36px; font-size: 1rem; }
@media (min-width: 768px) {
    .btn-sm-remove { width: 28px; height: 28px; font-size: 0.75rem; }
}

.flex-center { display: flex; justify-content: center; align-items: center; }

/* 1. The Parent Rows (These must be flex containers to sit horizontally) */
.materials-table { border: 1px solid #cbd5e1; border-radius: 8px; overflow-x: auto; margin-top: 0.5rem; background: #fff; }
.materials-header { display: flex; background: #0f172a; color: white; padding: 0.75rem 0; font-weight: bold; font-size: 0.875rem; min-width: max-content; }
.material-row { display: flex; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0; align-items: center; min-width: max-content; }
.material-row:last-child { border-bottom: none; }
.empty-state { padding: 1.5rem; text-align: center; color: #64748b; font-size: 0.875rem; font-style: italic; min-width: 100%; }

/* 2. The Children Cells (This adds the internal padding to every column) */
.materials-header > div,
.material-row > div { 
    padding: 0 1rem; 
}

/* Table Columns */
.col-item { width: 5rem; flex-shrink: 0; box-sizing: border-box; }
.col-mat { flex-grow: 1; flex-shrink: 0; min-width: 15rem; box-sizing: border-box; }
.col-cant { width: 8rem; flex-shrink: 0; box-sizing: border-box; }
.col-uni { width: 8rem; flex-shrink: 0; box-sizing: border-box; }
.col-acc { width: 6rem; flex-shrink: 0; box-sizing: border-box; }

/* Evidence preview grid */
.evidence-preview-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    margin-top: 1rem;
}
.evidence-item {
    display: flex;
    flex-direction: column;
    width: 200px;
    gap: 0.5rem;
}
.evidence-thumb {
    position: relative;
    width: 100%;
    height: 120px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #cbd5e1;
    cursor: pointer;
    transition: box-shadow 0.2s;
}
.evidence-thumb:hover {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.evidence-thumb img {
    width: 100%;
    height: 100%;
    object-fit: contain; /* Changed from cover so it isn't cropped */
    background: #f8fafc;
}
.btn-remove-thumb {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    border: none;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    padding: 0;
    transition: background 0.2s;
    z-index: 10;
}
.btn-remove-thumb:hover {
    background: rgba(220, 38, 38, 1);
}
.evidence-comment-input {
    font-size: 12px;
    padding: 0.5rem;
    resize: none;
    overflow: hidden;
    min-height: 50px;
}

/* Image Preview Modal */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
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
</style>