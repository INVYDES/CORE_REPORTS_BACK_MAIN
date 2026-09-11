<script setup lang="ts">
// @ts-nocheck
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../../stores/authStore'
import { storeToRefs } from 'pinia'
import { getDependenciaName } from '../../../data/mockDependencias'
import type { Subdependencia } from '../../../data/mockDependencias'
import { areasApi } from '../../../api/areas'
import { useToast } from 'vue-toastification'
import { 
    Network, 
    Trash2, 
    Save,
    Map
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const { currentUser } = storeToRefs(authStore)

if (!currentUser.value || (currentUser.value?.rol !== 1 && currentUser.value?.rol !== 2)) {
    router.push({ name: 'dashboard' })
}

const companyId = computed(() => (currentUser.value as any)?.idDependencia ?? (currentUser.value as any)?.dependencia_id ?? 0)
const companyName = computed(() => getDependenciaName(companyId.value))

const toast = useToast()
const localSubareas = ref<Subdependencia[]>([])
const loading = ref(false)

const fetchAreas = async () => {
    loading.value = true
    try {
        const res = await areasApi.list()
        const list = res.data || res
        const arr = Array.isArray(list) ? list : []
        localSubareas.value = arr.map((a:any)=> ({
            idSubdependencia: a.id,
            idDependencia: a.dependencia_id,
            codigo: a.codigo,
            nombre: a.nombre,
            descripcion: a.descripcion,
            activo: a.activa ?? true
        }))
    } catch (e:any) {
        toast.error(e?.response?.data?.message || 'No se pudieron cargar áreas desde el back')
    } finally { loading.value = false }
}

onMounted(fetchAreas)

const isModalOpen = ref(false)
const isEditing = ref(false)
const form = ref({
    idSubdependencia: 0,
    codigo: '',
    nombre: '',
    descripcion: ''
})

const openModalForAdd = () => {
    form.value = {
        idSubdependencia: 0,
        codigo: '',
        nombre: '',
        descripcion: ''
    }
    isEditing.value = false
    isModalOpen.value = true
}

const openModalForEdit = (subarea: any) => {
    form.value = {
        idSubdependencia: subarea.idSubdependencia,
        codigo: subarea.codigo || '',
        nombre: subarea.nombre,
        descripcion: subarea.descripcion || ''
    }
    isEditing.value = true
    isModalOpen.value = true
}

const deleteSubarea = async (idSubdependencia: number) => {
    if (!confirm('¿Estás seguro de que deseas eliminar esta subárea?')) return
    try {
        await areasApi.remove(idSubdependencia)
        localSubareas.value = localSubareas.value.filter(s => s.idSubdependencia !== idSubdependencia)
        toast.success('Área eliminada')
    } catch (e:any) {
        toast.error(e?.response?.data?.message || 'Error al eliminar')
    }
}

const handleDeleteFromModal = async () => {
    await deleteSubarea(form.value.idSubdependencia);
    isModalOpen.value = false;
}


const saveSubarea = async () => {
    if(!form.value.nombre.trim()) {
        toast.error("El nombre no puede estar vacío.")
        return
    }
    if(!form.value.codigo.trim()) {
        toast.error("El código no puede estar vacío.")
        return
    }
    try {
        const payload = {
            codigo: form.value.codigo.trim().toUpperCase().replace(/\s+/g,'_'),
            nombre: form.value.nombre.trim(),
            descripcion: form.value.descripcion.trim() || null
        }
        if (isEditing.value) {
            const updated = await areasApi.update(form.value.idSubdependencia, payload)
            const idx = localSubareas.value.findIndex(s => s.idSubdependencia === form.value.idSubdependencia)
            if (idx !== -1) localSubareas.value[idx] = { 
                ...localSubareas.value[idx], 
                codigo: updated.codigo || payload.codigo,
                nombre: updated.nombre || payload.nombre,
                descripcion: updated.descripcion || payload.descripcion
            }
            toast.success('Área actualizada')
        } else {
            const created = await areasApi.create(payload)
            localSubareas.value.push({
                idSubdependencia: created.id,
                idDependencia: created.dependencia_id,
                codigo: created.codigo,
                nombre: created.nombre,
                descripcion: created.descripcion,
                activo: true
            })
            toast.success('Área creada')
        }
        isModalOpen.value = false
    } catch (e:any) {
        const msg = e?.response?.data?.message || JSON.stringify(e?.response?.data?.errors) || 'Error al guardar'
        toast.error(msg)
    }
}

</script>

<template>
    <div class="page-container-company">
        <!-- Main Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div class="w-full sm:w-auto">
                <h2 class="text-2xl font-bold text-slate-900">Gestión de Subdependencias / Áreas</h2>
                <p class="text-slate-500 mt-1">Administración de áreas operativas para {{ companyName }}</p>
            </div>
            
            <button @click="openModalForAdd" class="btn-primary flex justify-center items-center gap-2 w-full sm:w-auto whitespace-nowrap">
                <Network :size="16"/>
                Añadir Nueva Área
            </button>
        </div>

        <div class="dashboard-grid">
            <!-- SECTION: List of Areas -->
            <section class="settings-section">
                <div class="section-header">
                    <Map class="text-indigo-600" :size="20"/>
                    <h3>Áreas Asignadas</h3>
                </div>
                
                <div class="section-body">
                    <div class="users-grid">
                        <div v-for="area in localSubareas" :key="area.idSubdependencia" @click="openModalForEdit(area)" class="user-card card relative group cursor-pointer hover:border-blue-300 hover:bg-blue-50/30 transition-all flex items-center">
                            <div class="user-avatar">
                                <Network :size="20" class="text-indigo-600"/>
                            </div>
                            <div class="user-info w-full">
                                <h4>{{ area.nombre }}</h4>
                                <div class="user-meta mt-1">
                                    <span class="user-badge">Área Activa</span>
                                    <span class="text-xs text-slate-500 block mt-1">ID: REF-{{ area.idSubdependencia }}</span>
                                </div>
                            </div>
                            <div class="ml-auto flex items-center text-slate-300 group-hover:text-blue-500 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                            </div>
                        </div>

                        <!-- Empty state if no areas -->
                        <div v-if="localSubareas.length === 0" class="col-span-full">
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-6 flex flex-col items-center gap-3 text-center w-full">
                                <Network class="text-amber-500" :size="32" />
                                <h4 class="text-amber-800 font-bold">No hay áreas configuradas</h4>
                                <p class="text-amber-700 text-sm">Aún no se han configurado subdependencias o áreas operativas para esta empresa. Haz clic en "Añadir Nueva Área" para comenzar.</p>
                            </div>
                        </div>

                    </div>
                </div>
            </section>
        </div>

        <!-- Add/Edit Modal -->
        <Teleport to="body">
            <div v-if="isModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800">{{ isEditing ? 'Detalles de Área' : 'Añadir Nueva Área' }}</h3>
                        <button @click="isModalOpen = false" class="text-slate-400 hover:text-slate-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                    
                    <div class="p-6 overflow-y-auto form-modal-content">
                        <div class="form-group mb-0">
                            <label>Nombre del Área o Subdependencia</label>
                            <input type="text" v-model="form.nombre" class="editable-input" placeholder="Ej. Línea de Producción 1" />
                            <label>Responsable Dependencia</label>
                            <input type="text" class="editable-input" placeholder = "Responsable Dependencia"/>
                            <label>Correo principal</label>
                            <input type="text" class="editable-input" placeholder = "Ej. correo_principal@empresa.com"/>
                            <label>Correo secundario</label>
                            <input type="text" class="editable-input" placeholder = "Ej. correo_secundario@empresa.com"/>
                        </div>
                    </div>
                    
                    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-between items-center gap-3">
                        <button v-if="isEditing" @click="handleDeleteFromModal" class="btn-outline text-red-600 border-red-200 hover:bg-red-50 hover:border-red-300 hover:text-red-700 flex items-center gap-2 px-3 py-2">
                            <Trash2 :size="16"/> <span class="hidden sm:inline">Eliminar</span>
                        </button>
                        <div v-else></div>
                        
                        <div class="flex gap-2 sm:gap-3">
                            <button @click="isModalOpen = false" class="btn-outline px-3 py-2">Cancelar</button>
                            <button @click="saveSubarea" class="btn-primary flex items-center gap-2 py-2">
                                <Save :size="16"/> Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.page-container-company {
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
    font-family: system-ui, -apple-system, sans-serif;
}



.dashboard-grid {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.settings-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #f1f5f9;
}
.section-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
}

.card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

/* Form Styles */
.form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.5rem;
}

.editable-input {
    width: 100%;
    padding: 0.65rem 1rem;
    border-radius: 6px;
    font-size: 0.95rem;
    outline: none;
    transition: all 0.2s;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #0f172a;
}

.editable-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Users Grid Styles (Reused for Areas) */
.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
}

.user-card {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.25rem;
    transition: transform 0.2s, box-shadow 0.2s;
}
.user-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
    border-color: #cbd5e1;
}

.user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.user-info h4 {
    margin: 0;
    font-size: 1rem;
    color: #0f172a;
    font-weight: 600;
}

.user-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    background: #f1f5f9;
    color: #475569;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Buttons */
.btn-primary {
    background: #2563eb;
    color: white;
    border: none;
    padding: 0.6rem 1.25rem;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-primary:hover {
    background: #1d4ed8;
}

.btn-outline {
    background: transparent;
    color: #475569;
    border: 1px solid #cbd5e1;
    padding: 0.6rem 1.25rem;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-outline:hover {
    background: #f1f5f9;
    color: #0f172a;
}
</style>