<script setup lang="ts">
// @ts-nocheck
    import { ref, onMounted, reactive, computed } from 'vue'
    import { useRoute, useRouter } from 'vue-router'
    import { ArrowLeft, User, Calendar, Clock, CheckCircle2, FileText, Wrench, Building2 } from 'lucide-vue-next'
    
    // Import Data
import client from '../../../api/client'
    
    // Pinia store
    import { useAuthStore } from '../../../stores/authStore'
    import { storeToRefs } from 'pinia'
    
    const authStore = useAuthStore()
    const { currentUser } = storeToRefs(authStore)

    const route = useRoute()
    const router = useRouter()

    // Role-based access: only tech roles (1: Admin Técnico, 3: Técnico) can view reports
    if (!currentUser.value || (currentUser.value?.rol !== 1 && currentUser.value?.rol !== 3)) {
        router.push({ name: 'dashboard' })
    }

    const goBack = () => {
        router.back()
    }

    const reportId = Number(route.params.id)
    const report = ref<any>(null)
    const users = ref<any[]>([])
    const areas = ref<any[]>([])

    const tipoLabel = (code: string) => ({ ticket: 'Ticket', servicio: 'Servicio Programado', libre: 'Libre' } as any)[code] || code
    const catLabel = (code: string) => ({ preventivo: 'Mantenimiento Preventivo', correctivo: 'Mantenimiento Correctivo', instalacion: 'Instalación', mejora: 'Mejora', diagnostico: 'Diagnóstico' } as any)[code] || code
    const estatusLabel = (code: string) => ({ finalizado: 'Cerrado / Trabajo Finalizado', abierto: 'Abierto / Trabajo Parcial', parcial: 'Abierto / Trabajo Parcial', descartado: 'Descartado / Cancelado' } as any)[code] || code

    const applyReport = (raw: any) => {
        const dep = (currentUser.value as any)?.dependencia_id ?? (currentUser.value as any)?.idDependencia
        if (raw.dependencia_id !== undefined && dep !== undefined && raw.dependencia_id !== dep) {
            report.value = null
            return
        }
        const origen = raw.ticket || raw.servicio || null
        report.value = {
            idReporte: raw.id,
            folio: raw.folio,
            idDependencia: raw.dependencia_id,
            idSubdependencia: raw.area_id ?? undefined,
            creadoPor: raw.creado_por,
            tipoReporte: tipoLabel(raw.tipo),
            origenDatosId: raw.ticket_id ?? raw.servicio_id ?? null,
            categoria: catLabel(raw.categoria),
            fechaElaboracion: raw.fecha_inicio,
            fechaHoraInicio: raw.fecha_inicio,
            fechaHoraFin: raw.fecha_fin,
            horaSalidaBase: raw.hora_salida,
            horaLlegadaSitio: raw.hora_llegada,
            horaRegresoABase: raw.hora_regreso,
            fallaReportada: origen?.descripcion || undefined,
            desarrolloActividades: raw.desarrollo || '',
            conclusionTrabajo: undefined,
            esRetrabajo: !!raw.es_retrabajo,
            responsableId: raw.responsable_id,
            ejecutoresIds: (raw.ejecutores || []).map((e: any) => e.usuario_id),
            materiales: (raw.materiales || []).map((m: any) => ({
                idItem: m.id,
                material: m.material?.nombre || 'Material',
                cantidad: Number(m.cantidad) || 1,
                unidad: m.material?.unidad_base || 'pza',
            })),
            evidenciaFotografica: (raw.evidencias || []).map((ev: any) => ({ url: ev.url || ev.ruta_archivo || ev.path || '', comentario: ev.comentario || ev.descripcion || '' })).filter((ev: any) => ev.url),
            historialModificaciones: [],
            estatusReporte: estatusLabel(raw.estatus),
        }
    }

    const loadReport = async () => {
        try {
            const res = await client.get(`/reportes/${reportId}`).then(r => r.data)
            applyReport(res.data || res)
        } catch {
            report.value = null
        }
        try {
            const res = await client.get('/usuarios', { params: { per_page: 100 } }).then(r => r.data)
            const list = res.data || res || []
            if (Array.isArray(list)) users.value = list
        } catch {}
        try {
            const res = await client.get('/areas', { params: { per_page: 100 } }).then(r => r.data)
            const list = res.data || res || []
            if (Array.isArray(list)) areas.value = list
        } catch {}
    }
    onMounted(loadReport)

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
        if (!dateString) return '---'
        const date = new Date(dateString)
        return date.toLocaleDateString('es-MX', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
    }

    const getOrigenDetails = () => {
        if (!report.value) return { folio: 'N/A', asunto: '' }
        if (report.value.tipoReporte === 'Libre' || !report.value.origenDatosId) {
            return { folio: 'Reporte Libre', asunto: report.value.fallaReportada || 'Independiente' }
        }
        const prefix = report.value.tipoReporte === 'Ticket' ? 'Ticket' : 'Servicio'
        return { folio: `${prefix}: ${report.value.folio}`, asunto: report.value.fallaReportada || '' }
    }

    // -- Edit Logic --
    const showEditModal = ref(false)
    const editForm = reactive({
        estatusReporte: '',
        desarrolloActividades: '',
        fechaHoraFin: '',
        horaSalidaBase: '',
        horaRegresoABase: ''
    })

    const openEditModal = () => {
        if (!report.value) return;
        editForm.estatusReporte = report.value.estatusReporte;
        editForm.desarrolloActividades = report.value.desarrolloActividades;
        // input datetime-local requires YYYY-MM-DDThh:mm format
        editForm.fechaHoraFin = report.value.fechaHoraFin ? report.value.fechaHoraFin.slice(0, 16) : ''; 
        editForm.horaSalidaBase = report.value.horaSalidaBase ? report.value.horaSalidaBase.slice(0, 16) : ''; 
        editForm.horaRegresoABase = report.value.horaRegresoABase ? report.value.horaRegresoABase.slice(0, 16) : ''; 
        showEditModal.value = true;
    }

    const saveReportEdit = async () => {
        if (!report.value || !currentUser.value) return;

        // Determine what changed for the changelog
        let changesDesc = [];
        if (report.value.estatusReporte !== editForm.estatusReporte) {
            changesDesc.push(`Cambió el estatus a ${editForm.estatusReporte}`);
        }
        if (report.value.desarrolloActividades !== editForm.desarrolloActividades) {
            changesDesc.push(`Actualizó la descripción de actividades`);
        }
        if (report.value.fechaHoraFin !== editForm.fechaHoraFin) {
            changesDesc.push(`Actualizó la fecha/hora de fin`);
        }
        if (report.value.horaSalidaBase !== editForm.horaSalidaBase) {
            changesDesc.push(`Actualizó la hora de salida`);
        }
        if (report.value.horaRegresoABase !== editForm.horaRegresoABase) {
            changesDesc.push(`Actualizó la hora de regreso a base`);
        }

        if (changesDesc.length === 0) {
            showEditModal.value = false;
            return; // No changes
        }

        // Persist changes via the API
        const rawEstatus = ({ 'Cerrado / Trabajo Finalizado': 'finalizado', 'Abierto / Trabajo Parcial': 'parcial', 'Descartado / Cancelado': 'descartado' } as any)[editForm.estatusReporte] || 'parcial'
        try {
            await client.put(`/reportes/${report.value.idReporte}`, {
                estatus: rawEstatus,
                desarrollo: editForm.desarrolloActividades,
                fecha_fin: editForm.fechaHoraFin || null,
                hora_salida: editForm.horaSalidaBase || null,
                hora_regreso: editForm.horaRegresoABase || null
            })
        } catch {
            toast.error('No se pudo guardar el reporte. Intenta de nuevo.')
            return
        }

        // Apply changes locally
        report.value.estatusReporte = editForm.estatusReporte as 'Abierto / Trabajo Parcial' | 'Cerrado / Trabajo Finalizado' | 'Descartado / Cancelado';
        report.value.desarrolloActividades = editForm.desarrolloActividades;
        report.value.fechaHoraFin = editForm.fechaHoraFin;
        report.value.horaSalidaBase = editForm.horaSalidaBase;
        report.value.horaRegresoABase = editForm.horaRegresoABase;
        
        // Safety check array
        if (!report.value.historialModificaciones) {
            report.value.historialModificaciones = [];
        }
        
        // Push activity log (session view of this change)
        report.value.historialModificaciones.push({
            idModificacion: Date.now(),
            fechaModificacion: new Date().toISOString(),
            idUsuario: (currentUser.value as any).id ?? (currentUser.value as any).idUsuario,
            descripcion: changesDesc.join(' y ')
        });

        toast.success('Reporte actualizado correctamente')
        showEditModal.value = false;
    }

    const openSurvey = () => {
        if (!report.value) return;
        router.push({ name: 'report-survey', params: { id: report.value.idReporte } })
    }

    import { useToast } from 'vue-toastification'
    const toast = useToast()

    const previewUrl = ref<string | null>(null)
    const isDownloading = ref(false)
    
    const exportPDF = () => {
        isDownloading.value = true
        toast.info("Preparando PDF...", { timeout: 1000 })
        
        setTimeout(() => {
            isDownloading.value = false
            window.print()
            toast.success("Mostrando diálogo de impresión/PDF")
        }, 1000)
    }

    // === Print helpers ===
    const companyName = computed(() => {
        if (!report.value) return ''
        return (currentUser.value as any)?.dependencia?.nombre || 'Empresa'
    })

    const companyLogo = computed(() => {
        return '' // No hay logo por API
    })

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
        switch(rol) {
            case 0: return 'Usuario Básico'
            case 1: return 'Administrador Técnico'
            case 2: return 'Administrador Comercial'
            case 3: return 'Técnico'
            default: return 'Desconocido'
        }
    }

    const formatDateShort = (dateString: string) => {
        if (!dateString) return '---'
        return dateString.replace('T', ' ').slice(0, 16)
    }

    const formatTimeOnly = (dateString: string) => {
        if (!dateString) return '--:--'
        const d = new Date(dateString)
        return d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
    }

    const totalHours = computed(() => {
        if (!report.value) return '0h 0m'
        const start = new Date(report.value.fechaHoraInicio)
        const end = new Date(report.value.fechaHoraFin)
        const diffMs = end.getTime() - start.getTime()
        if (diffMs <= 0) return '0h 0m'
        const hours = Math.floor(diffMs / (1000 * 60 * 60))
        const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60))
        return `${hours}h ${minutes}m`
    })

    const areaName = computed(() => {
        if (!report.value) return '---'
        if (report.value.idSubdependencia === undefined || report.value.idSubdependencia === null) return 'Sin área'
        const a = areas.value.find((x: any) => x.id === report.value!.idSubdependencia)
        return a ? a.nombre : `Área #${report.value.idSubdependencia}`
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
                        <button class="btn-primary" @click="exportPDF" :disabled="isDownloading">
                            <span v-if="isDownloading">⏳ Generando...</span>
                            <span v-else>⬇️ Descargar PDF</span>
                        </button>
                        <button v-if="currentUser?.rol === 1" class="btn-secondary" @click="openEditModal">✏️ Editar Reporte</button>
                        <button class="btn-survey" @click="openSurvey">
                            ⭐ Encuesta al Cliente
                        </button>
                    </div>
                </div>
            </header>

            <!-- Upper banner -->
            <div class="card quick-meta-bar">
                <div class="meta-item">
                    <span class="meta-label">Estatus</span>
                    <span class="status-pill" :class="report.estatusReporte === 'Cerrado / Trabajo Finalizado' ? 'pill-success' : 'pill-warning'">
                        {{ report.estatusReporte === 'Cerrado / Trabajo Finalizado' ? 'FINALIZADO' : 'TRABAJO PARCIAL' }}
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
                        <p class="description-text">{{ report.fallaReportada }}</p>
                    </div>

                    <div class="card description-card">
                        <h2>Desarrollo de Actividades</h2>
                        <p class="description-text">{{ report.desarrolloActividades || 'Sin descripción.' }}</p>
                    </div>

                    <div class="card description-card">
                        <h2>Conclusión del Trabajo</h2>
                        <p class="description-text" v-if="report.conclusionTrabajo">{{ report.conclusionTrabajo }}</p>
                        <p class="description-text" v-else>{{ report.estatusReporte === 'Cerrado / Trabajo Finalizado' ? 'Trabajo finalizado satisfactoriamente.' : 'Trabajo en proceso, pendiente de finalización.' }}</p>
                    </div>

                    <div class="card materials-card" v-if="report.materiales && report.materiales.length > 0">
                        <h2>Materiales Utilizados</h2>
                        <ul class="materials-list">
                            <li v-for="mat in report.materiales" :key="mat.idItem">
                                <span class="mat-name"><Wrench :size="14" class="inline-icon"/> {{ mat.material }}</span>
                                <span class="mat-qty">{{ mat.cantidad }} {{ mat.unidad }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="card description-card">
                        <h2>Evidencia Fotográfica</h2>
                        <div class="evidence-grid" v-if="report.evidenciaFotografica && report.evidenciaFotografica.length > 0">
                            <div class="evidence-item" v-for="(item, idx) in report.evidenciaFotografica" :key="idx">
                                <img :src="item.url" class="evidence-img cursor-pointer" alt="Evidencia" @click="previewUrl = item.url" title="Haz clic para ampliar" />
                                <p class="evidence-comment-text text-sm text-gray-600 mt-2">{{ item.comentario || 'Sin comentario' }}</p>
                            </div>
                        </div>
                        <p class="description-text" v-else style="color: #94a3b8; font-style: italic;">No se han agregado fotos.</p>
                    </div>

                    <!-- History Timeline -->
                    <div class="timeline-section" v-if="report.historialModificaciones && report.historialModificaciones.length > 0" data-html2canvas-ignore="true">
                        <h2 class="timeline-heading">Historial de Modificaciones</h2>
                        <div class="timeline">
                            <div class="timeline-item" v-for="mod in report.historialModificaciones" :key="mod.idModificacion">
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
                                    <div class="text-sm text-slate-500 mt-1" v-if="getOrigenDetails().asunto">{{ getOrigenDetails().asunto }}</div>
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
                                <span><strong>Elaboración:</strong> <br/>{{ formatDate(report.fechaElaboracion) }}</span>
                            </div>
                            <div class="date-info mt-2" v-if="report.horaSalidaBase">
                                <Clock :size="16" class="icon" />
                                <span><strong>Salida Base:</strong> <br/>{{ formatDate(report.horaSalidaBase) }}</span>
                            </div>
                            <div class="date-info mt-2" v-if="report.horaLlegadaSitio">
                                <Clock :size="16" class="icon" />
                                <span><strong>Llegada a Sitio:</strong> <br/>{{ formatDate(report.horaLlegadaSitio) }}</span>
                            </div>
                            <div class="date-info mt-2">
                                <Clock :size="16" class="icon" />
                                <span><strong>Inicio Trabajo:</strong> <br/>{{ formatDate(report.fechaHoraInicio) }}</span>
                            </div>
                            <div class="date-info mt-2" v-if="report.fechaHoraFin">
                                <Clock :size="16" class="icon" />
                                <span><strong>Fin Trabajo:</strong> <br/>{{ formatDate(report.fechaHoraFin) }}</span>
                            </div>
                            <div class="date-info mt-2" v-if="report.horaRegresoABase">
                                <Clock :size="16" class="icon" />
                                <span><strong>Regreso a Base:</strong> <br/>{{ formatDate(report.horaRegresoABase) }}</span>
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

        <!-- Edit Modal Overlay -->
        <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
            <div class="modal-content">
                <header class="modal-header">
                    <h2>✏️ Editar Reporte</h2>
                    <button class="btn-close" @click="showEditModal = false">&times;</button>
                </header>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Estatus del Reporte</label>
                        <select v-model="editForm.estatusReporte" class="form-control">
                            <option value="Abierto / Trabajo Parcial">Abierto / Trabajo Parcial</option>
                            <option value="Cerrado / Trabajo Finalizado">Cerrado / Trabajo Finalizado</option>
                            <option value="Descartado / Cancelado">Descartado / Cancelado</option>
                        </select>
                    </div>
                    <div class="grid-2-cols mt-4">
                        <div class="form-group">
                            <label>Hora de Salida Base</label>
                            <input type="datetime-local" v-model="editForm.horaSalidaBase" class="form-control" />
                        </div>
                        <div class="form-group">
                            <label>Fecha y Hora de Fin</label>
                            <input type="datetime-local" v-model="editForm.fechaHoraFin" class="form-control" />
                        </div>
                        <div class="form-group">
                            <label>Hora de Regreso a Base</label>
                            <input type="datetime-local" v-model="editForm.horaRegresoABase" class="form-control" />
                        </div>
                    </div>
                    <div class="form-group mt-4">
                        <label>Desarrollo de Actividades</label>
                        <textarea v-model="editForm.desarrolloActividades" rows="4" class="form-control"></textarea>
                    </div>
                </div>
                <footer class="modal-footer mt-4">
                    <button class="btn-cancel" @click="showEditModal = false">Cancelar</button>
                    <button class="btn-save" @click="saveReportEdit">Guardar Cambios</button>
                </footer>
            </div>
        </div>

        <!-- Image Preview Modal (On-Screen View Only) -->
        <div class="modal-overlay" v-if="previewUrl" @click="previewUrl = null">
            <div class="modal-content-img" @click.stop>
                <button class="btn-close-modal" @click="previewUrl = null">&times;</button>
                <img :src="previewUrl" class="img-preview-full" alt="Vista previa detallada" />
            </div>
        </div>

    </div>

    <!-- ===== PRINT-ONLY FORMAL DOCUMENT ===== -->
    <div v-if="report" class="print-report-document">
        <!-- HEADER -->
        <div class="print-header">
            <div class="print-header-logo">
                <img v-if="companyLogo" :src="companyLogo" alt="Logo empresa" class="print-logo-img" />
            </div>
            <div class="print-header-content">
                <div class="print-company-name">{{ companyName }}</div>
                <div class="print-folio-bar">{{ report.folio }}</div>
                <div class="print-subtitle">REPORTE DE MANTENIMIENTO / ORDEN DE TRABAJO</div>
            </div>
        </div>

        <!-- DATOS GENERALES -->
        <div class="print-section">
            <h2 class="print-section-title">DATOS GENERALES</h2>
            <div class="print-datos-grid">
                <div class="print-datos-col">
                    <p>Fecha de elaboración: <strong>{{ formatDateShort(report.fechaElaboracion) }}</strong></p>
                    <p>Reporte elaborado por: {{ getUserName(report.creadoPor) }}</p>
                    <p>Dependencia: {{ areaName }}</p>
                    <p>Responsable dependencia: {{ displayResponsableNombre }}</p>
                    <p>Cargo: {{ displayResponsableCargo }}</p>
                </div>
                <div class="print-datos-col">
                    <p>Tipo de reporte: {{ report.tipoReporte }}</p>
                    <p>Categoría del trabajo: {{ report.categoria }}</p>
                    <p>OT/SP/Ticket: {{ getOrigenDetails().folio }}</p>
                    <p>Ejecutores: {{ getEjecutoresNames(report.ejecutoresIds) }}</p>
                    <p>Estatus: <strong>{{ report.estatusReporte === 'Cerrado / Trabajo Finalizado' ? 'FINALIZADO' : 'TRABAJO PARCIAL' }}</strong></p>
                    <p>Retrabajo: <strong>{{ report.esRetrabajo ? 'Sí' : 'No' }}</strong></p>
                </div>
            </div>
        </div>

        <hr class="print-hr" />

        <!-- FALLA REPORTADA -->
        <div class="print-section" v-if="report.fallaReportada">
            <h2 class="print-section-title">FALLA REPORTADA</h2>
            <p class="print-text">{{ report.fallaReportada }}</p>
        </div>

        <!-- DESCRIPCIÓN DEL SERVICIO -->
        <div class="print-section">
            <h2 class="print-section-title">DESCRIPCIÓN DEL SERVICIO</h2>
            <p class="print-text">{{ report.desarrolloActividades || 'Sin descripción.' }}</p>
        </div>

        <!-- CONCLUSIÓN DEL SERVICIO -->
        <div class="print-section">
            <h2 class="print-section-title">CONCLUSIÓN DEL SERVICIO</h2>
            <p class="print-text" v-if="report.conclusionTrabajo">{{ report.conclusionTrabajo }}</p>
            <p class="print-text" v-else>{{ report.estatusReporte === 'Cerrado / Trabajo Finalizado' ? 'Trabajo finalizado satisfactoriamente.' : 'Trabajo en proceso, pendiente de finalizar.' }}</p>
        </div>

        <!-- MATERIALES -->
        <div class="print-section">
            <h2 class="print-section-title">MATERIALES</h2>
            <table class="print-table" v-if="report.materiales && report.materiales.length > 0">
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
                        <td>{{ idx + 1 }}</td>
                        <td>{{ mat.material }}</td>
                        <td>{{ mat.cantidad }}</td>
                        <td>{{ mat.unidad }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="print-text print-empty-state" v-else>
                No se usaron materiales en este reporte.
            </p>
        </div>

        <!-- EVIDENCIA FOTOGRÁFICA -->
        <div class="print-section">
            <h2 class="print-section-title">EVIDENCIA FOTOGRÁFICA</h2>
            <div class="print-evidence-grid" v-if="report.evidenciaFotografica && report.evidenciaFotografica.length > 0">
                <div class="print-evidence-item" v-for="(item, idx) in report.evidenciaFotografica" :key="idx">
                    <img :src="item.url" alt="Evidencia" class="print-evidence-img" />
                    <p class="print-evidence-comment">{{ item.comentario }}</p>
                </div>
            </div>
            <p class="print-text print-empty-state" v-else>
                No se tomaron evidencias fotográficas para este reporte.
            </p>
        </div>

        <!-- TIEMPOS -->
        <div class="print-section">
            <h2 class="print-section-title">TIEMPOS</h2>
            <p class="print-text">
                <strong>Inicio:</strong> {{ formatTimeOnly(report.fechaHoraInicio) }}
                &nbsp;&nbsp;
                <strong>Fin:</strong> {{ formatTimeOnly(report.fechaHoraFin) }}
                &nbsp;&nbsp;
                <strong>Total:</strong> {{ totalHours }}
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

        <p class="print-footer-note">Generado con coreReports®</p>
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

    &:hover { color: #0f172a; }
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
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    transition: background-color 0.2s;

    &:hover { background-color: #2563eb; }
}

.btn-secondary {
    background-color: white;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    transition: background-color 0.2s;

    &:hover { background-color: #f8fafc; }
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
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    transition: background-color 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;

    &:hover { background-color: #fde68a; }
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
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.description-card, .materials-card {
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

.materials-list {
    list-style: none;
    padding: 0;
    margin: 0;

    li {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f1f5f9;

        &:last-child { border-bottom: none; }

        .mat-name { color: #0f172a; font-weight: 500; display: flex; align-items: center; gap: 6px;}
        .mat-qty { color: #64748b; font-size: 0.9rem; font-weight: 600; }
    }
}

.inline-icon { color: #cbd5e1; }

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

.person-info, .origin-info {
    display: flex;
    align-items: flex-start;
    gap: 12px;

    .icon { color: #94a3b8; margin-top: 2px;}
    .name, strong { font-weight: 600; color: #0f172a; font-size: 0.95rem; }
    .group-names { line-height: 1.4; }
}

.date-info {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    color: #334155;
    font-size: 0.9rem;

    .icon { color: #94a3b8; margin-top: 2px; }
    strong { color: #0f172a; font-weight: 600; display: inline-block; margin-bottom: 2px;}
}

.status-pill {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-block;

    &.pill-warning { background: #fffbeb; color: #d97706; }
    &.pill-success { background: #dcfce7; color: #15803d; }
}

.error-state {
    padding: 4rem 2rem;
    text-align: center;
    
    h2 { color: #0f172a; margin-bottom: 0.5rem; }
    p { color: #64748b; }
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

    &:last-child { margin-bottom: 0; }
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
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);

    .timeline-user { color: #0f172a; font-size: 0.95rem; }
    .timeline-date { color: #64748b; font-size: 0.8rem; }
    .timeline-desc { color: #475569; font-size: 0.95rem; margin-top: 0.5rem; line-height: 1.4; }
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
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;

    h2 { font-size: 1.1rem; color: #0f172a; font-weight: 600; margin:0;}
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #94a3b8;
    cursor: pointer;
    line-height: 1;
    transition: color 0.1s;
    &:hover { color: #ef4444; }
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
    transition: border-color 0.2s, box-shadow 0.2s;
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

    &:hover { background: #f1f5f9; }
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

    &:hover { background: #2563eb; }
}

@media (max-width: 768px) {
    .report-grid {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    .main-column, .side-column {
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
    .btn-primary, .btn-secondary {
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
.evidence-comment-text {
    line-height: 1.3;
    white-space: pre-wrap;
    word-wrap: break-word;
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

    *, *::before, *::after {
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
    .print-header {
        display: flex;
        align-items: stretch;
        margin-bottom: 2rem;
        border: 2px solid #000;
        padding: 0;
    }
    .print-header-logo {
        width: 30%;
        border-right: 2px solid #000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem;
    }
    .print-logo-img {
        max-width: 90%;
        max-height: 80px;
        object-fit: contain;
    }
    .print-header-content {
        flex: 1;
        text-align: center;
        display: flex;
        flex-direction: column;
    }
    .print-company-name {
        font-size: 14pt;
        font-weight: 700;
        padding: 0.5rem;
        letter-spacing: 0.5px;
    }
    .print-folio-bar {
        background: #bbb;
        color: #000;
        font-size: 10pt;
        font-weight: 700;
        padding: 0.25rem 1rem;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .print-subtitle {
        font-size: 10pt;
        font-weight: 600;
        padding: 0.5rem;
        letter-spacing: 0.5px;
    }

    /* Sections */
    .print-section {
        margin-bottom: 1.25rem;
    }
    .print-section-title {
        font-size: 10pt;
        font-weight: 700;
        text-transform: uppercase;
        margin: 0 0 0.5rem 0;
        padding-bottom: 0.25rem;
        border-bottom: 1px solid #000;
        letter-spacing: 0.5px;
    }

    /* Datos generales 2-col */
    .print-datos-grid {
        display: flex;
        gap: 2rem;
    }
    .print-datos-col {
        flex: 1;
    }
    .print-datos-col p {
        margin: 0.2rem 0;
        font-size: 10pt;
    }

    .print-hr {
        border: none;
        border-top: 1px solid #000;
        margin: 1.5rem 0;
    }

    .print-text {
        font-size: 10pt;
        white-space: pre-wrap;
        margin: 0;
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