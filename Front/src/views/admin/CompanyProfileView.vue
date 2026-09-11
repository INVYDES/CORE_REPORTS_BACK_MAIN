<script setup lang="ts">
// @ts-nocheck
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/authStore'
import { storeToRefs } from 'pinia'
import client from '../../api/client'
import { useToast } from 'vue-toastification'
import { 
    Building2, 
    User, 
    Mail, 
    UploadCloud, 
    Users, 
    Settings,
    Save,
    CreditCard
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const { currentUser } = storeToRefs(authStore)
const toast = useToast()

// 1. Security & Access Control
if (!currentUser.value || currentUser.value?.rol !== 2) {
    router.push({ name: 'dashboard' })
}

const depId = computed(() => (currentUser.value as any)?.dependencia_id ?? (currentUser.value as any)?.idDependencia)

const planLabel = (code: string) => ({ trial: 'Trial', mensual: 'Mensual', anual: 'Anual' } as any)[code] || code || '—'
const tierLabel = (limit: number) => {
    if (!limit) return 'Básico'
    if (limit <= 5) return 'Básico'
    if (limit <= 20) return 'Intermedio'
    return 'Empresa'
}
const fmtDate = (d: string) => (d ? String(d).slice(0, 10) : '—')

// 2. Company data loaded from the API (camelCase view model)
const company = ref<any>(null)
const savingCompany = ref(false)

const mapCompany = (raw: any) => {
    if (!raw) return null
    return {
        idDependencia: raw.id,
        nombre: raw.nombre || '',
        correoContacto: raw.correo_contacto || '',
        correoReportes: raw.correo_reportes || '',
        telefono: raw.telefono || '',
        tipoLicencia: planLabel(raw.tipo_licencia),
        fechaExpiracionLicencia: fmtDate(raw.fecha_expiracion),
        limiteUsuarios: raw.limite_usuarios || 0,
        nivelUsuarios: tierLabel(raw.limite_usuarios)
    }
}

const loadCompany = async () => {
    if (!depId.value) return
    try {
        const res = await client.get(`/dependencias/${depId.value}`).then(r => r.data)
        company.value = mapCompany(res.data || res)
    } catch {
        company.value = null
    }
}

const saveCompany = async () => {
    if (!company.value) return
    savingCompany.value = true
    try {
        const res = await client.put(`/dependencias/${company.value.idDependencia}`, {
            correo_contacto: company.value.correoContacto,
            correo_reportes: company.value.correoReportes,
            telefono: company.value.telefono
        }).then(r => r.data)
        company.value = mapCompany(res.data || res)
        toast.success('Datos de la empresa actualizados')
    } catch (e: any) {
        toast.error(e?.response?.data?.message || 'No se pudieron guardar los datos')
    } finally {
        savingCompany.value = false
    }
}

// 3. Team members: real users of this dependencia
const localUsers = ref<any[]>([])

const mapUser = (u: any) => ({
    idUsuario: u.id,
    idDependencia: u.dependencia_id,
    numeroEmpleado: u.numero_empleado || `EMP-${String(u.id).padStart(3, '0')}`,
    rol: Number(u.rol),
    nombre: u.nombre || '',
    apellidos: u.apellidos || '',
    correo: u.email || '',
    estado: u.estado ? 1 : 0,
    fechaCreacion: u.created_at,
    ultimoAcceso: u.ultimo_acceso
})

const loadUsers = async () => {
    try {
        const res = await client.get('/usuarios', { params: { per_page: 100 } }).then(r => r.data)
        const list = res.data || res || []
        localUsers.value = Array.isArray(list) ? list.map(mapUser) : []
    } catch {
        localUsers.value = []
    }
}

const teamMembers = computed(() => localUsers.value)

const usedSeats = computed(() => {
    return localUsers.value.filter(u => u.estado === 1 && u.rol === 3).length
})

const seatsPercentage = computed(() => {
    if (!company.value || !company.value.limiteUsuarios) return 0
    return (usedSeats.value / company.value.limiteUsuarios) * 100
})

// Current user details to populate the admin field
const adminName = computed(() => {
    const name = currentUser.value ? `${(currentUser.value as any)?.nombre ?? ''} ${(currentUser.value as any)?.apellidos ?? ''}`.trim() : ''
    return name || 'N/A'
})

// Helper to get role text
const getRoleText = (rol: number) => {
    switch (Number(rol)) {
        case 0: return 'Usuario Básico'
        case 1: return 'Administrador Técnico'
        case 2: return 'Administrador Comercial'
        case 3: return 'Técnico'
        default: return 'Desconocido'
    }
}

// User Modal State
const isUserModalOpen = ref(false)
const isEditing = ref(false)
const userForm = ref({
    idUsuario: 0,
    nombre: '',
    apellidos: '',
    correo: '',
    numeroEmpleado: '',
    rol: 0,
    password: ''
})

const openModalForAdd = () => {
    userForm.value = {
        idUsuario: 0,
        nombre: '',
        apellidos: '',
        correo: '',
        numeroEmpleado: `EMP-${Math.floor(Math.random() * 1000).toString().padStart(3, '0')}`,
        rol: 0,
        password: ''
    }
    isEditing.value = false
    isUserModalOpen.value = true
}

const openModalForEdit = (user: any) => {
    userForm.value = {
        idUsuario: user.idUsuario,
        nombre: user.nombre,
        apellidos: user.apellidos,
        correo: user.correo,
        numeroEmpleado: user.numeroEmpleado,
        rol: user.rol,
        password: '' // Don't populate password
    }
    isEditing.value = true
    isUserModalOpen.value = true
}

const saveUser = async () => {
    if (!userForm.value.nombre || !userForm.value.apellidos || !userForm.value.correo) {
        toast.warning('Completa nombre, apellidos y correo')
        return
    }
    try {
        if (isEditing.value) {
            await client.put(`/usuarios/${userForm.value.idUsuario}`, {
                nombre: userForm.value.nombre,
                apellidos: userForm.value.apellidos,
                email: userForm.value.correo,
                rol: userForm.value.rol,
                numero_empleado: userForm.value.numeroEmpleado || null
            })
            toast.success('Usuario actualizado')
        } else {
            if (!userForm.value.password || userForm.value.password.length < 6) {
                toast.warning('La contraseña debe tener al menos 6 caracteres')
                return
            }
            await client.post('/usuarios', {
                nombre: userForm.value.nombre,
                apellidos: userForm.value.apellidos,
                email: userForm.value.correo,
                password: userForm.value.password,
                rol: userForm.value.rol,
                numero_empleado: userForm.value.numeroEmpleado || null
            })
            toast.success('Usuario creado correctamente')
        }
        isUserModalOpen.value = false
        await loadUsers()
    } catch (e: any) {
        toast.error(e?.response?.data?.message || 'No se pudo guardar el usuario')
    }
}

const removeUser = async (idUsuario: number) => {
    if (!confirm('¿Eliminar este usuario? Esta acción no se puede deshacer.')) return
    try {
        await client.delete(`/usuarios/${idUsuario}`)
        toast.success('Usuario eliminado')
        isUserModalOpen.value = false
        await loadUsers()
    } catch (e: any) {
        toast.error(e?.response?.data?.message || 'No se pudo eliminar el usuario')
    }
}

const showPlanInfo = () => {
    toast.info('El cambio de plan se gestiona con soporte. Contáctanos para ampliar tu licencia.')
}

onMounted(() => { loadCompany(); loadUsers() })

</script>

<template>
    <div class="page-container-company">
        <!-- Main Header -->
        <div class="page-header">
            <div>
                <h2>Perfil de Empresa</h2>
                <p class="text-slate-500 mt-1">Configuración comercial y administración de usuarios</p>
            </div>
        </div>

        <div v-if="company" class="dashboard-grid">
            <!-- SECTION A: Datos de empresa -->
            <section class="settings-section">
                <div class="section-header">
                    <Building2 class="text-blue-600" :size="20"/>
                    <h3>Datos de la Empresa</h3>
                </div>
                
                <div class="section-body card">
                    <div class="form-grid-company">
                        <!-- Left Side: Inputs -->
                        <div class="inputs-area">
                            <div class="form-group">
                                <label>Empresa</label>
                                <div class="input-with-icon">
                                    <Building2 :size="16" class="icon-input" />
                                    <input type="text" :value="company.nombre" readonly class="readonly-input" />
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Administrador Comercial</label>
                                <div class="input-with-icon">
                                    <User :size="16" class="icon-input" />
                                    <input type="text" :value="adminName" readonly class="readonly-input" />
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Correo de Contacto</label>
                                <div class="input-with-icon">
                                    <Mail :size="16" class="icon-input" />
                                    <input type="email" :value="company.correoContacto" readonly class="readonly-input" />
                                </div>
                            </div>
                        </div>

                        <!-- Right Side: Logo Upload -->
                        <div class="logo-area">
                            <label class="block mb-2 font-medium text-slate-700 text-sm">Logotipo de la empresa</label>
                            <div class="upload-box border-dashed">
                                <UploadCloud :size="32" class="text-slate-400 mb-2"/>
                                <span class="text-sm text-blue-600 font-semibold cursor-pointer py-1">Haz clic para subir</span>
                                <span class="text-xs text-slate-500 mt-1">o arrastra y suelta tu imagen aquí</span>
                                <span class="text-xs text-slate-400 mt-2">PNG o JPG (Max 2MB)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION B: Suscripción y Facturación -->
            <section class="settings-section">
                <div class="section-header">
                    <CreditCard class="text-purple-600" :size="20"/>
                    <h3>Suscripción y Facturación</h3>
                </div>
                
                <div class="section-body card flex flex-col gap-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-500 mb-1">Plan Actual</p>
                            <div class="flex items-center gap-2">
                                <span class="text-lg font-bold text-slate-800">{{ company.tipoLicencia }}</span>
                                <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">Expira: {{ company.fechaExpiracionLicencia }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-end mb-2">
                            <p class="text-sm font-semibold text-slate-700">
                                Uso de Licencias (Seats): <span class="font-normal text-slate-500">Usuarios Asignados: {{ usedSeats }} de {{ company.limiteUsuarios }} (Plan {{ company.nivelUsuarios }})</span>
                            </p>
                            <span class="text-xs font-bold" :class="seatsPercentage >= 100 ? 'text-red-500' : 'text-blue-600'">{{ Math.round(seatsPercentage) }}%</span>
                        </div>
                        
                        <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                            <div class="h-2.5 rounded-full transition-all duration-500"
                                :class="seatsPercentage >= 100 ? 'bg-red-500' : 'bg-blue-600'"
                                :style="{ width: `${Math.min(seatsPercentage, 100)}%` }">
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button v-if="usedSeats < company.limiteUsuarios" @click="openModalForAdd" class="btn-primary flex items-center gap-2">
                            <User :size="16"/>
                            + Añadir Nuevo Usuario
                        </button>
                        <div v-else class="flex flex-col gap-3">
                            <button class="btn-primary flex items-center gap-2 opacity-50 cursor-not-allowed w-max" disabled>
                                <User :size="16"/>
                                + Añadir Nuevo Usuario
                            </button>
                            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-center gap-3">
                                <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0 text-amber-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                </div>
                                <button @click="showPlanInfo" class="text-amber-800 font-bold text-sm text-left hover:underline">
                                    Límite alcanzado. Contacta a soporte para ampliar tu plan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION C: Usuarios (Team Grid) -->
            <section class="settings-section">
                <div class="section-header">
                    <Users class="text-indigo-600" :size="20"/>
                    <h3>Usuarios Asignados</h3>
                </div>
                
                <div class="section-body">
                    <div class="users-grid">
                        <div v-for="user in teamMembers" :key="user.idUsuario" class="user-card card relative group">
                            <button v-if="currentUser?.rol === 2" @click="openModalForEdit(user)" class="absolute top-3 right-3 text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity hover:text-blue-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                            </button>
                            <div class="user-avatar">
                                {{ user.nombre.charAt(0) }}{{ user.apellidos.charAt(0) }}
                            </div>
                            <div class="user-info">
                                <h4>{{ user.nombre }} {{ user.apellidos }}</h4>
                                <div class="user-meta mt-1">
                                    <span class="user-badge">{{ getRoleText(user.rol) }}</span>
                                    <span class="text-xs text-slate-500 block mt-1">ID: {{ user.numeroEmpleado }}</span>
                                    <div class="text-sm text-slate-600 mt-2 flex items-center gap-1">
                                        <Mail :size="12" class="text-slate-400"/>
                                        <span class="truncate">{{ user.correo }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION D: Configuración de correo -->
            <section class="settings-section">
                <div class="section-header">
                    <Settings class="text-emerald-600" :size="20"/>
                    <h3>Configuración de Correo (Envío de reportes)</h3>
                </div>
                
                <div class="section-body card">
                    <p class="text-sm text-slate-500 mb-5">
                        El servidor SMTP se configura a nivel del backend (variables <code class="text-slate-700 bg-slate-100 px-1 rounded">MAIL_*</code>).
                        Aquí defines los correos de tu empresa que se usan como contacto y como destino de los reportes.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="form-group">
                            <label>Correo de Contacto</label>
                            <input type="email" v-model="company.correoContacto" class="editable-input w-full" placeholder="contacto@tuempresa.com"/>
                        </div>

                        <div class="form-group">
                            <label>Correo para Reportes</label>
                            <input type="email" v-model="company.correoReportes" class="editable-input w-full" placeholder="reportes@tuempresa.com"/>
                        </div>

                        <div class="form-group">
                            <label>Teléfono</label>
                            <input type="text" v-model="company.telefono" class="editable-input w-full" placeholder="(000) 000-0000"/>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="smtp-actions mt-8 pt-5 border-t border-slate-100 flex flex-wrap gap-3 items-center justify-end">
                        <button class="btn-primary flex items-center gap-2" @click="saveCompany" :disabled="savingCompany">
                            <Save :size="16"/>
                            {{ savingCompany ? 'Guardando...' : 'Guardar Cambios' }}
                        </button>
                    </div>
                </div>
            </section>
        </div>
        
        <div v-else class="empty-state">
            <p>No se encontraron datos de la empresa. Por favor, contacte a soporte.</p>
        </div>

        <!-- User Form Modal -->
        <Teleport to="body">
            <div v-if="isUserModalOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800">{{ isEditing ? 'Editar Usuario' : 'Añadir Nuevo Usuario' }}</h3>
                        <button @click="isUserModalOpen = false" class="text-slate-400 hover:text-slate-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                    
                    <div class="p-6 overflow-y-auto form-modal-content">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="form-group mb-0">
                                <label>Nombre</label>
                                <input type="text" v-model="userForm.nombre" class="editable-input" placeholder="Nombre" />
                            </div>
                            <div class="form-group mb-0">
                                <label>Apellidos</label>
                                <input type="text" v-model="userForm.apellidos" class="editable-input" placeholder="Apellidos" />
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Correo Electrónico</label>
                            <input type="email" v-model="userForm.correo" class="editable-input" placeholder="ejemplo@empresa.com" />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="form-group mb-0">
                                <label>Número de Empleado</label>
                                <input type="text" v-model="userForm.numeroEmpleado" class="editable-input" placeholder="EMP-000" />
                            </div>
                            <div class="form-group mb-0">
                                <label>Rol</label>
                                <select v-model="userForm.rol" class="editable-input">
                                    <option :value="0">Usuario Básico</option>
                                    <option :value="1">Admin Técnico</option>
                                    <option :value="2">Admin Comercial</option>
                                    <option :value="3">Técnico</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Contraseña</label>
                            <input type="password" v-model="userForm.password" class="editable-input" placeholder="••••••••" />
                            <p v-if="isEditing" class="text-xs text-slate-500 mt-1">Dejar en blanco para mantener la contraseña actual.</p>
                        </div>
                    </div>
                    
                    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3">
                        <button v-if="isEditing && userForm.idUsuario !== (currentUser as any)?.id" @click="removeUser(userForm.idUsuario)" class="btn-outline text-red-600 border-red-200 hover:bg-red-50">Eliminar Usuario</button>
                        <div class="flex-1"></div>
                        <button @click="isUserModalOpen = false" class="btn-outline">Cancelar</button>
                        <button @click="saveUser" class="btn-primary">Guardar Usuario</button>
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

.page-header {
    margin-bottom: 2rem;
}
.page-header h2 {
    color: #0f172a;
    font-size: 1.75rem;
    font-weight: 700;
    margin: 0;
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

.input-with-icon {
    position: relative;
    display: flex;
    align-items: center;
}
.icon-input {
    position: absolute;
    left: 1rem;
    color: #94a3b8;
}

.readonly-input, .editable-input {
    width: 100%;
    padding: 0.65rem 1rem;
    border-radius: 6px;
    font-size: 0.95rem;
    outline: none;
    transition: all 0.2s;
}

.readonly-input {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    padding-left: 2.5rem;
    cursor: default;
}

.editable-input {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #0f172a;
}
.input-with-icon .editable-input {
    padding-left: 2.5rem;
}

.editable-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* SECTION A: Company Layout */
.form-grid-company {
    display: grid;
    grid-template-columns: 3fr 2fr;
    gap: 2.5rem;
}
.inputs-area {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.upload-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 180px;
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    background: #f8fafc;
    transition: all 0.2s;
}
.upload-box:hover {
    border-color: #3b82f6;
    background: #f0f9ff;
}

/* SECTION B: Users Grid */
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
    font-weight: 700;
    letter-spacing: 0.5px;
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

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #64748b;
    background: white;
    border-radius: 12px;
    border: 1px dashed #cbd5e1;
}

@media (max-width: 768px) {
    .form-grid-company {
        grid-template-columns: 1fr;
    }
}
</style>
