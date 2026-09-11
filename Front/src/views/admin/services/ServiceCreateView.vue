<script setup lang = "ts">
    import { ref, computed, onMounted } from 'vue'
    import { useRouter } from 'vue-router'
    import { Save } from 'lucide-vue-next'

    import { serviciosApi } from '../../../api/servicios';
    import { areasApi } from '../../../api/areas';
    import client from '../../../api/client';
    import { useToast } from 'vue-toastification';
    
    const router = useRouter()
    const toast = useToast()

    const realAreas = ref<any[]>([])
    const realTechs = ref<any[]>([])
    const availableSubareas = computed(() => {
        if (realAreas.value.length) return realAreas.value
        return []
    })
    const availableTechnicians = computed(() => realTechs.value)

    onMounted(async ()=>{
        try {
            const res = await areasApi.list()
            const list = res.data || res
            const arr = Array.isArray(list) ? list : []
            realAreas.value = arr.map((a:any)=> ({ idSubdependencia: a.id, idDependencia: a.dependencia_id, nombre: a.nombre }))
        } catch {}
        try {
            const u = await client.get('/usuarios', { params:{ per_page:100 } }).then(r=>r.data)
            const list = u.data || u
            realTechs.value = Array.isArray(list) ? list.filter((x:any)=> [1,3].includes(x.rol)).map((x:any)=> ({ idUsuario: x.id, nombre: x.nombre, apellidos: x.apellidos })) : []
        } catch {}
    })

    const isSubmitting = ref(false)
    const formData = ref({
        idSubdependencia: null as number | null,
        solicitante: '',
        cargo: '',
        asunto: '',
        prioridad: 'Media',
        prioridadApi: 'media',
        asignadoA: '' as string | number, // ahora guarda id
        categoria: 'preventivo' as string,
        fechaAsignacion: '',
        fechaVencimiento: '',
        descripcion: ''
    })

    const submitService = async () => {
        if (isSubmitting.value) return
        isSubmitting.value = true
        try {
            const payload:any = {
                area_id: formData.value.idSubdependencia,
                solicitante: formData.value.solicitante,
                cargo: formData.value.cargo,
                asunto: formData.value.asunto,
                prioridad: formData.value.prioridad,
                categoria: formData.value.categoria,
                fecha_asignacion: formData.value.fechaAsignacion,
                fecha_vencimiento: formData.value.fechaVencimiento,
                descripcion: formData.value.descripcion,
            }
            if (formData.value.asignadoA) payload.usuario_asignado_id = Number(formData.value.asignadoA)
            const res = await serviciosApi.create(payload)
            toast.success(`Servicio ${res.folio} programado`)
            router.push({ name: 'schedule-list' })
        } catch (e:any) {
            toast.error(e?.response?.data?.message || JSON.stringify(e?.response?.data?.errors) || 'Error al programar servicio')
        } finally { isSubmitting.value = false }
    }
    const autoResize = (event: Event) => {
        const target = event.target as HTMLTextAreaElement;
        target.style.height = 'auto'; // Reset height
        target.style.height = `${target.scrollHeight}px`; // Set to scroll height
    };

</script>
<!-------------------------------------------------------------------------------------------------------->
<template>
    <div class="page-container-create-service">
        
        <!-- Header -->
        <header class="form-header">
            <h1>Programar Nuevo Servicio</h1>
            <p class="subtitle">Complete los detalles para agendar la actividad de mantenimiento.</p>
        </header>

        <!-- Body card -->
        <div class="card form-card">
            <form @submit.prevent="submitService" class="ticket-form">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Dependencia / Área *</label>
                        <select v-model="formData.idSubdependencia" required class="fat-input select-arrow">
                            <option :value="null">Selecciona un área...</option>
                            <option v-for="area in availableSubareas" :key="area.idSubdependencia" :value="area.idSubdependencia">
                                {{ area.nombre }}
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Solicitante *</label>
                        <input type="text" v-model="formData.solicitante" required placeholder="Nombre completo" class="fat-input" />
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Cargo</label>
                        <input type="text" v-model="formData.cargo" placeholder="Ej. Gerente de Producción" class="fat-input" />
                    </div>
                    
                    <div class="form-group">
                        <label>Asunto *</label>
                        <input type="text" v-model="formData.asunto" required placeholder="Resumen corto del servicio" class="fat-input" />
                    </div>
                </div>

                <div class="form-group">
                    <label>Categoría *</label>
                    <select v-model="formData.categoria" class="fat-input select-arrow" required>
                        <option value="preventivo">Mantenimiento Preventivo</option>
                        <option value="correctivo">Mantenimiento Correctivo</option>
                        <option value="instalacion">Instalación</option>
                        <option value="mejora">Mejora</option>
                        <option value="diagnostico">Diagnóstico</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Asignar a Técnico</label>
                    <select v-model="formData.asignadoA" class="fat-input select-arrow">
                        <option value="">Sin asignar por ahora</option>
                        <option v-for="tech in availableTechnicians" :key="tech.idUsuario" :value="tech.idUsuario">
                            {{ tech.nombre }} {{ tech.apellidos }}
                        </option>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Fecha de Asignación *</label>
                        <input type="date" v-model="formData.fechaAsignacion" required class="fat-input" />
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha de Vencimiento (Límite) *</label>
                        <input type="date" v-model="formData.fechaVencimiento" required class="fat-input" />
                    </div>
                </div>

                <div class="form-group">
                    <label>Prioridad *</label>
                    <div class="priority-selector">
                        <label class="priority-chip" :class="{ 'active-baja': formData.prioridad === 'Baja' }">
                            <input type="radio" v-model="formData.prioridad" value="Baja" name="prioridad">
                            <span>! Baja</span>
                        </label>
                        
                        <label class="priority-chip" :class="{ 'active-media': formData.prioridad === 'Media' }">
                            <input type="radio" v-model="formData.prioridad" value="Media" name="prioridad">
                            <span>!! Media</span>
                        </label>
                        
                        <label class="priority-chip" :class="{ 'active-alta': formData.prioridad === 'Alta' }">
                            <input type="radio" v-model="formData.prioridad" value="Alta" name="prioridad">
                            <span>!!! Alta</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Descripción detallada *</label>
                    <textarea 
                        v-model="formData.descripcion" 
                        required 
                        rows="4" 
                        @input="autoResize"
                        placeholder="Describa la actividad a realizar, equipos involucrados, refacciones necesarias..."
                        class="fat-input textarea overflow-hidden"
                    ></textarea>
                </div>

                <div class="divider"></div>

                <div class="form-actions">
                    <button type="button" @click="router.back()" class="btn-cancelar">Cancelar</button>
                    <button type="submit" class="btn-enviar" :disabled="isSubmitting">
                        <Save :size="18" style="margin-right: 8px;"/>
                        {{ isSubmitting ? 'Programando...' : 'Programar Servicio' }}
                    </button>
                </div>

            </form>
        </div>
    </div>
</template>

<style scoped lang="scss">
.page-container-create-service {
    max-width: 800px;
    margin: 0 auto;
    padding: 1rem;
}

/* --- HEADER --- */
.form-header {
    margin-bottom: 1.5rem;

    h1 {
        font-size: 1.8rem;
        color: #0f172a;
        margin: 0.5rem 0 0.2rem 0;
    }

    .subtitle {
        color: #64748b;
        font-size: 0.95rem;
        margin: 0;
    }
}

/* --- CARDS & GRID --- */
.card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr; /* MOBILE FIRST */
    gap: 1.2rem;
    margin-bottom: 1.2rem;
}

@media (min-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr 1fr; /* DESKTOP */
    }
}

/* --- INPUTS --- */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-bottom: 1.2rem;

    label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #475569;
    }
}

.fat-input {
    width: 100%;
    box-sizing: border-box;
    min-height: 48px; 
    padding: 0.8rem 1rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 1rem; 
    color: #334155;
    background: white;
    transition: border-color 0.2s;

    &:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }
    
    &::placeholder {
        color: #94a3b8;
    }
}

.select-arrow {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1em;
}

.textarea {
    resize: vertical;
    min-height: 100px;
}

/* --- PRIORITY CHIPS --- */
.priority-selector {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap; 
}

.priority-chip {
    flex: 1;
    text-align: center;
    min-height: 48px; 
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: white;
    color: #64748b;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s;

    input { display: none; }

    &.active-baja { background: #f1f5f9; border-color: #94a3b8; color: #475569; }
    &.active-media { background: #ffedd5; border-color: #f97316; color: #c2410c; }
    &.active-alta { background: #fee2e2; border-color: #ef4444; color: #b91c1c; }
}

/* --- ACTIONS --- */
.divider {
    height: 1px;
    background: #e2e8f0;
    width: 100%;
    margin: 1.5rem 0;
}

.form-actions {
    display: flex;
    gap: 1rem;
    flex-direction: column-reverse; 
}

@media (min-width: 768px) {
    .form-actions {
        flex-direction: row;
        justify-content: flex-end;
    }
}

.btn-enviar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #2563eb;
    color: white;
    border: none;
    min-height: 48px;
    padding: 0 2rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.2s;
    width: 100%; 

    &:hover { background: #1d4ed8; }
}

@media (min-width: 768px) {
    .btn-enviar { width: auto; } 
}

.btn-cancelar {
    background: white;
    color: #64748b;
    border: 1px solid #cbd5e1;
    min-height: 48px;
    padding: 0 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    width: 100%;

    &:hover { 
        background: #f8fafc; 
        color: #0f172a;
    }
}

@media (min-width: 768px) {
    .btn-cancelar { width: auto; }
}
</style>