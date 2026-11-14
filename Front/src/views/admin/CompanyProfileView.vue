<script setup lang="ts">
  import { ref, computed, onMounted } from 'vue'
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
    Save,
    CreditCard,
    Eye,
    EyeOff,
    Share2,
    Copy,
    Check,
    ExternalLink,
  } from 'lucide-vue-next'

  const authStore = useAuthStore()
  const { currentUser } = storeToRefs(authStore)
  const toast = useToast()

  const planLabel = (code: string) =>
    (({ trial: 'Trial', mensual: 'Mensual', anual: 'Anual' }) as any)[code] || code || '—'
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
    const limite = raw.limiteUsuarios ?? raw.limite_usuarios ?? 0
    return {
      idDependencia: raw.idDependencia ?? raw.id,
      nombre: raw.nombre || '',
      rfc: raw.rfc || '',
      correoContacto: raw.correoContacto || raw.correo_contacto || '',
      correoReportes: raw.correoReportes || raw.correo_reportes || '',
      telefono: raw.telefono || '',
      tipoLicencia: planLabel(raw.tipoLicencia ?? raw.tipo_licencia),
      fechaExpiracionLicencia: fmtDate(raw.fechaExpiracionLicencia ?? raw.fecha_expiracion),
      limiteUsuarios: limite,
      nivelUsuarios: tierLabel(limite),
      logo: raw.logo || '',
      slug: raw.slug || (raw.nombre ? raw.nombre.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') : ''),
    }
  }

  const copiedPublicLink = ref(false)
  const publicLink = computed(() => {
    if (!company.value?.slug) return ''
    const origin = window.location.origin
    return `${origin}/solicitar/${company.value.slug}`
  })

  const copyPublicLink = async () => {
    if (!publicLink.value) return
    try {
      await navigator.clipboard.writeText(publicLink.value)
      copiedPublicLink.value = true
      toast.success('¡Enlace de clientes copiado al portapapeles!')
      setTimeout(() => {
        copiedPublicLink.value = false
      }, 3000)
    } catch {
      toast.error('No se pudo copiar el enlace')
    }
  }

  const loadCompany = async () => {
    try {
      // El endpoint /empresa resuelve la dependencia del usuario en sesión
      const res = await client.get('/empresa').then(r => r.data)
      company.value = mapCompany(res.data || res)
    } catch (err) {
      console.warn('Error al cargar /empresa, usando datos de la sesión como respaldo:', err)
      const sessionDep = (currentUser.value as any)?.dependencia
      if (sessionDep) {
        company.value = mapCompany(sessionDep)
      } else {
        company.value = null
      }
    }
  }

  const saveCompany = async () => {
    if (!company.value) return
    savingCompany.value = true
    try {
      const payload: Record<string, any> = {
        correoContacto: company.value.correoContacto || '',
        correoReportes: company.value.correoReportes || '',
        telefono: company.value.telefono || '',
      }
      if (company.value.nombre) {
        payload.nombre = company.value.nombre
      }
      if (company.value.rfc) {
        payload.rfc = company.value.rfc
      }

      const res = await client.put('/empresa', payload).then(r => r.data)
      const data = res.data || res
      company.value = mapCompany(data)
      toast.success('Datos de la empresa actualizados')
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'No se pudieron guardar los datos')
    } finally {
      savingCompany.value = false
    }
  }

  // 2.b Logotipo de la empresa (multipart hacia /empresa/logo)
  const logoInput = ref<HTMLInputElement | null>(null)
  const uploadingLogo = ref(false)
  const isDraggingLogo = ref(false)
  const MAX_LOGO_BYTES = 4 * 1024 * 1024

  const pickLogo = () => logoInput.value?.click()

  const uploadLogo = async (file: File) => {
    if (!file.type.startsWith('image/')) {
      toast.error('El archivo debe ser una imagen (PNG, JPG o WEBP)')
      return
    }
    if (file.size > MAX_LOGO_BYTES) {
      toast.error('La imagen no debe superar los 4 MB')
      return
    }

    uploadingLogo.value = true
    try {
      const formData = new FormData()
      formData.append('logo', file)

      const res = await client
        .post('/empresa/logo', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
        .then(r => r.data)

      const data = res.data || res
      if (company.value) company.value.logo = data.logo || company.value.logo
      toast.success('Logotipo actualizado')
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'No se pudo subir el logotipo')
    } finally {
      uploadingLogo.value = false
    }
  }

  const onLogoSelected = (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (file) uploadLogo(file)
    input.value = ''
  }

  const onLogoDropped = (event: DragEvent) => {
    isDraggingLogo.value = false
    const file = event.dataTransfer?.files?.[0]
    if (file) uploadLogo(file)
  }

  // 3. Team members: real users of this dependencia
  const localUsers = ref<any[]>([])

  const mapUser = (u: any) => {
    const depId = u.dependencia_id || (currentUser.value as any)?.dependencia_id || 1
    return {
      idUsuario: u.id,
      idDependencia: u.dependencia_id,
      numeroEmpleado: u.numero_empleado || `EMP-${depId}${String(u.id).padStart(3, '0')}`,
      rol: Number(u.rol),
      nombre: u.nombre || '',
      apellidos: u.apellidos || '',
      correo: u.email || '',
      estado: u.estado ? 1 : 0,
      fechaCreacion: u.created_at,
      ultimoAcceso: u.ultimo_acceso,
    }
  }

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

  // Cuenta todos los usuarios activos de la empresa (alineado con UsuarioController en backend)
  const usedSeats = computed(() => {
    return localUsers.value.filter(u => u.estado === 1).length
  })

  const seatsPercentage = computed(() => {
    if (!company.value || !company.value.limiteUsuarios) return 0
    return (usedSeats.value / company.value.limiteUsuarios) * 100
  })

  // Current user details to populate the admin field
  const adminName = computed(() => {
    const name = currentUser.value
      ? `${(currentUser.value as any)?.nombre ?? ''} ${(currentUser.value as any)?.apellidos ?? ''}`.trim()
      : ''
    return name || 'N/A'
  })

  const adminRoleLabel = computed(() => {
    return currentUser.value?.rol === 1 ? 'Administrador Técnico' : 'Administrador Comercial'
  })

  // Helper to get role text
  const getRoleText = (rol: number) => {
    switch (Number(rol)) {
      case 0:
        return 'Usuario Básico'
      case 1:
        return 'Administrador Técnico'
      case 2:
        return 'Administrador Comercial'
      case 3:
        return 'Técnico'
      default:
        return 'Desconocido'
    }
  }

  // Genera el siguiente ID de empleado consecutivo: EMP-{id_dependencia}{consecutivo} (ej. EMP-1001, EMP-1002...)
  const getNextEmployeeNumber = () => {
    const depId =
      (currentUser.value as any)?.dependencia_id ??
      (currentUser.value as any)?.idDependencia ??
      1
    const prefix = `EMP-${depId}`
    let maxNum = 0
    for (const u of localUsers.value) {
      const numEmp = String(u.numeroEmpleado || u.numero_empleado || '').toUpperCase().trim()
      if (numEmp.startsWith(prefix)) {
        const suffix = numEmp.slice(prefix.length)
        const n = parseInt(suffix, 10)
        if (!isNaN(n) && n > maxNum) maxNum = n
      }
    }
    return `${prefix}${String(maxNum + 1).padStart(3, '0')}`
  }

  // User Modal State
  const isUserModalOpen = ref(false)
  const isEditing = ref(false)
  const showPassword = ref(false)
  const userForm = ref({
    idUsuario: 0,
    nombre: '',
    apellidos: '',
    correo: '',
    numeroEmpleado: '',
    rol: 3,
    password: '',
  })

  const openModalForAdd = () => {
    userForm.value = {
      idUsuario: 0,
      nombre: '',
      apellidos: '',
      correo: '',
      numeroEmpleado: getNextEmployeeNumber(),
      rol: 3,
      password: '',
    }
    isEditing.value = false
    showPassword.value = false
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
      password: '', // Don't populate password
    }
    isEditing.value = true
    showPassword.value = false
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
          numero_empleado: userForm.value.numeroEmpleado || null,
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
          numero_empleado: userForm.value.numeroEmpleado || null,
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

  // Modal de Cambio de Plan
  const isPlanModalOpen = ref(false)
  const availablePlans = ref<any[]>([])
  const buyingPlanId = ref<number | null>(null)

  const loadPlans = async () => {
    try {
      const res = await client.get('/licencias/disponibles').then(r => r.data)
      const list = res.data || res || []
      if (Array.isArray(list) && list.length > 0) {
        availablePlans.value = list
        return
      }
    } catch {
      /* fallback */
    }
    availablePlans.value = [
      {
        id: 1,
        nombre: 'Plan Básico',
        tipo: 'mensual',
        precio: 499,
        limite_usuarios: 5,
        limite_reportes_mensuales: 100,
      },
      {
        id: 2,
        nombre: 'Plan Intermedio',
        tipo: 'mensual',
        precio: 999,
        limite_usuarios: 20,
        limite_reportes_mensuales: 500,
      },
      {
        id: 3,
        nombre: 'Plan Empresa',
        tipo: 'anual',
        precio: 9990,
        limite_usuarios: 100,
        limite_reportes_mensuales: 5000,
      },
    ]
  }

  const showPlanInfo = async () => {
    if (availablePlans.value.length === 0) await loadPlans()
    isPlanModalOpen.value = true
  }

  const selectPlan = async (plan: any) => {
    if (buyingPlanId.value !== null) return
    buyingPlanId.value = plan.id
    try {
      const res = await client
        .post(`/licencias/${plan.id}/comprar-mercadopago`, {
          ciclo: plan.tipo || 'mensual',
        })
        .then(r => r.data)
      const url = res?.init_point || res?.data?.init_point || res?.sandbox_init_point
      if (url) {
        window.location.href = url
        return
      }
      toast.info('Solicitud registrada. Contacta a soporte para activar tu nuevo plan.')
      isPlanModalOpen.value = false
    } catch (e: any) {
      toast.info(
        e?.response?.data?.message ||
          'Para activar este plan de forma inmediata, contacta a soporte comercial.'
      )
    } finally {
      buyingPlanId.value = null
    }
  }

  onMounted(() => {
    loadCompany()
    loadUsers()
  })
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
          <Building2 class="text-blue-600" :size="20" />
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
                <label>{{ adminRoleLabel }}</label>
                <div class="input-with-icon">
                  <User :size="16" class="icon-input" />
                  <input type="text" :value="adminName" readonly class="readonly-input" />
                </div>
              </div>

              <div class="form-group">
                <label>Correo de Contacto de la Empresa</label>
                <div class="input-with-icon">
                  <Mail :size="16" class="icon-input" />
                  <input
                    type="email"
                    v-model="company.correoContacto"
                    class="editable-input"
                    placeholder="contacto@tuempresa.com"
                  />
                </div>
              </div>

              <div class="form-group">
                <label>Correo para Recepción de Reportes</label>
                <div class="input-with-icon">
                  <Mail :size="16" class="icon-input" />
                  <input
                    type="email"
                    v-model="company.correoReportes"
                    class="editable-input"
                    placeholder="reportes@tuempresa.com"
                  />
                </div>
                <span class="text-xs text-slate-400 mt-1 block">
                  A este correo se enviarán copias y notificaciones de los reportes generados.
                </span>
              </div>

              <div class="form-group">
                <label>Teléfono de la Empresa</label>
                <input
                  type="text"
                  v-model="company.telefono"
                  class="editable-input w-full"
                  placeholder="(000) 000-0000"
                />
              </div>

              <div class="pt-2">
                <button
                  class="btn-primary flex items-center gap-2"
                  @click="saveCompany"
                  :disabled="savingCompany"
                >
                  <Save :size="16" />
                  {{ savingCompany ? 'Guardando...' : 'Guardar Datos de Empresa' }}
                </button>
              </div>
            </div>

            <!-- Right Side: Logo Upload -->
            <div class="logo-area">
              <label class="block mb-2 font-medium text-slate-700 text-sm"
                >Logotipo de la empresa</label
              >
              <div
                class="upload-box border-dashed"
                :class="{ 'is-dragging': isDraggingLogo, 'is-locked': uploadingLogo }"
                role="button"
                tabindex="0"
                @click="pickLogo"
                @keydown.enter.prevent="pickLogo"
                @keydown.space.prevent="pickLogo"
                @dragover.prevent="isDraggingLogo = true"
                @dragleave.prevent="isDraggingLogo = false"
                @drop.prevent="onLogoDropped"
              >
                <img
                  v-if="company.logo"
                  :src="company.logo"
                  alt="Logotipo de la empresa"
                  class="logo-preview"
                />
                <template v-if="!company.logo">
                  <UploadCloud :size="32" class="text-slate-400 mb-2" />
                  <span class="text-xs text-slate-400 mt-2">PNG, JPG o WEBP (Max 4 MB)</span>
                </template>
                <span class="text-sm text-blue-600 font-semibold cursor-pointer py-1">
                  {{
                    uploadingLogo
                      ? 'Subiendo...'
                      : company.logo
                        ? 'Cambiar logotipo'
                        : 'Haz clic para subir'
                  }}
                </span>
                <span v-if="!company.logo" class="text-xs text-slate-500 mt-1"
                  >o arrastra y suelta tu imagen aquí</span
                >
                <input
                  ref="logoInput"
                  type="file"
                  accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml"
                  class="hidden"
                  @change="onLogoSelected"
                />
              </div>
              <p class="mt-2.5 text-xs text-slate-400 leading-relaxed text-center px-1">
                <strong class="text-slate-500 font-medium">Recomendado:</strong> 2560 × 1015 px (proporción horizontal 2.5:1) en PNG o JPG sin bordes vacíos para ajuste óptimo en PDF.
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- SECTION: Enlace para Clientes (Portal de Solicitud de Tickets) -->
      <section class="settings-section">
        <div class="section-header">
          <Share2 class="text-blue-600" :size="20" />
          <h3>Enlace Público para tus Clientes</h3>
        </div>

        <div class="section-body card flex flex-col gap-4">
          <p class="text-sm text-slate-600 leading-relaxed">
            Comparte esta liga con tus clientes, sucursales o personal en sitio para que puedan levantar tickets de servicio directamente <strong>sin necesidad de iniciar sesión</strong>.
          </p>

          <div class="flex items-center gap-2 flex-wrap">
            <div class="flex-1 min-w-[280px]">
              <input
                type="text"
                readonly
                :value="publicLink"
                class="w-full text-xs font-mono bg-slate-50 border border-slate-300 rounded px-3 py-2 text-slate-700 select-all focus:outline-none"
              />
            </div>
            <button
              type="button"
              @click="copyPublicLink"
              class="btn-primary flex items-center gap-1.5 text-xs px-4 py-2 cursor-pointer"
            >
              <Check v-if="copiedPublicLink" :size="15" />
              <Copy v-else :size="15" />
              <span>{{ copiedPublicLink ? '¡Enlace Copiado!' : 'Copiar Enlace' }}</span>
            </button>
            <a
              :href="publicLink"
              target="_blank"
              rel="noopener noreferrer"
              class="btn-outline flex items-center gap-1.5 text-xs px-3 py-2"
            >
              <ExternalLink :size="15" />
              <span>Ver Formulario</span>
            </a>
          </div>

          <div class="bg-blue-50/70 border border-blue-200/60 rounded-lg p-3 text-xs text-blue-800 flex items-start gap-2">
            <span class="text-base leading-none">💡</span>
            <span>
              <strong>Recomendación:</strong> Puedes enviar este enlace por WhatsApp a los gerentes o encargados de tus clientes. Cuando manden un reporte, entrará directamente a tu panel de tickets con el nombre del lugar y sus datos de contacto.
            </span>
          </div>
        </div>
      </section>

      <!-- SECTION B: Suscripción y Facturación -->
      <section class="settings-section">
        <div class="section-header">
          <CreditCard class="text-purple-600" :size="20" />
          <h3>Suscripción y Facturación</h3>
        </div>

        <div class="section-body card flex flex-col gap-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4 flex-wrap gap-3">
            <div>
              <p class="text-sm font-semibold text-slate-500 mb-1">Plan Actual</p>
              <div class="flex items-center gap-2">
                <span class="text-lg font-bold text-slate-800">{{ company.tipoLicencia }}</span>
                <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full"
                  >Expira: {{ company.fechaExpiracionLicencia }}</span
                >
              </div>
            </div>
            <button @click="showPlanInfo" class="btn-outline flex items-center gap-2 text-sm">
              <CreditCard :size="16" />
              Cambiar / Mejorar Plan
            </button>
          </div>

          <div>
            <div class="flex justify-between items-end mb-2">
              <p class="text-sm font-semibold text-slate-700">
                Uso de Licencias (Seats):
                <span class="font-normal text-slate-500"
                  >Usuarios Asignados: {{ usedSeats }} de {{ company.limiteUsuarios }} (Plan
                  {{ company.nivelUsuarios }})</span
                >
              </p>
              <span
                class="text-xs font-bold"
                :class="seatsPercentage >= 100 ? 'text-red-500' : 'text-blue-600'"
                >{{ Math.round(seatsPercentage) }}%</span
              >
            </div>

            <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
              <div
                class="h-2.5 rounded-full transition-all duration-500"
                :class="seatsPercentage >= 100 ? 'bg-red-500' : 'bg-blue-600'"
                :style="{ width: `${Math.min(seatsPercentage, 100)}%` }"
              ></div>
            </div>
          </div>

          <div class="pt-2">
            <button
              v-if="usedSeats < company.limiteUsuarios"
              @click="openModalForAdd"
              class="btn-primary flex items-center gap-2"
            >
              <User :size="16" />
              + Añadir Nuevo Usuario
            </button>
            <div v-else class="flex flex-col gap-3">
              <button
                class="btn-primary flex items-center gap-2 opacity-50 cursor-not-allowed w-max"
                disabled
              >
                <User :size="16" />
                + Añadir Nuevo Usuario
              </button>
              <div
                class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-center gap-3"
              >
                <div
                  class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center flex-shrink-0 text-amber-600"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  >
                    <path
                      d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"
                    />
                    <path d="M12 9v4" />
                    <path d="M12 17h.01" />
                  </svg>
                </div>
                <button
                  @click="showPlanInfo"
                  class="text-amber-800 font-bold text-sm text-left hover:underline"
                >
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
          <Users class="text-indigo-600" :size="20" />
          <h3>Usuarios Asignados</h3>
        </div>

        <div class="section-body">
          <div class="users-grid">
            <div
              v-for="user in teamMembers"
              :key="user.idUsuario"
              class="user-card card relative group"
            >
              <button
                v-if="currentUser?.rol === 2 || currentUser?.rol === 1"
                @click="openModalForEdit(user)"
                class="absolute top-3 right-3 text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity hover:text-blue-600"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                >
                  <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                  <path d="m15 5 4 4" />
                </svg>
              </button>
              <div class="user-avatar">
                {{ user.nombre.charAt(0) }}{{ user.apellidos.charAt(0) }}
              </div>
              <div class="user-info">
                <h4>{{ user.nombre }} {{ user.apellidos }}</h4>
                <div class="user-meta mt-1">
                  <span class="user-badge">{{ getRoleText(user.rol) }}</span>
                  <span class="text-xs text-slate-500 block mt-1"
                    >ID: {{ user.numeroEmpleado }}</span
                  >
                  <div class="text-sm text-slate-600 mt-2 flex items-center gap-1">
                    <Mail :size="12" class="text-slate-400" />
                    <span class="truncate">{{ user.correo }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div v-else class="empty-state">
      <p>No se encontraron datos de la empresa. Por favor, contacte a soporte.</p>
    </div>

    <!-- Plan Upgrade Modal -->
    <Teleport to="body">
      <div
        v-if="isPlanModalOpen"
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div
          class="bg-white rounded-xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]"
        >
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 class="text-lg font-bold text-slate-800">Cambiar o Mejorar Plan</h3>
              <p class="text-xs text-slate-500 mt-0.5">
                Selecciona el plan que mejor se adapte al tamaño de tu equipo
              </p>
            </div>
            <button @click="isPlanModalOpen = false" class="text-slate-400 hover:text-slate-600">
              ✕
            </button>
          </div>
          <div class="p-6 overflow-y-auto grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
              v-for="plan in availablePlans"
              :key="plan.id"
              class="border border-slate-200 rounded-xl p-4 flex flex-col justify-between hover:border-blue-500 transition-all"
            >
              <div>
                <h4 class="font-bold text-slate-900 text-base">
                  {{ plan.nombre || `Plan ${plan.tipo || ''}` }}
                </h4>
                <p class="text-2xl font-extrabold text-blue-600 mt-2">
                  ${{ Number(plan.precio || 0).toLocaleString('es-MX') }}
                  <span class="text-xs font-normal text-slate-500">/{{ plan.tipo || 'mes' }}</span>
                </p>
                <ul class="text-xs text-slate-600 mt-4 space-y-2 list-none p-0">
                  <li>✓ Hasta {{ plan.limite_usuarios ?? plan.limiteUsuarios ?? 10 }} usuarios</li>
                  <li v-if="plan.limite_reportes_mensuales">
                    ✓ {{ plan.limite_reportes_mensuales }} reportes/mes
                  </li>
                  <li>✓ Soporte y respaldos incluidos</li>
                </ul>
              </div>
              <button
                class="btn-primary w-full mt-5 text-sm"
                :disabled="buyingPlanId === plan.id"
                @click="selectPlan(plan)"
              >
                {{ buyingPlanId === plan.id ? 'Procesando...' : 'Elegir Plan' }}
              </button>
            </div>
          </div>
          <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex justify-end">
            <button type="button" @click="isPlanModalOpen = false" class="btn-outline">
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- User Form Modal -->
    <Teleport to="body">
      <div
        v-if="isUserModalOpen"
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
      >
        <div
          class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]"
        >
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800">
              {{ isEditing ? 'Editar Usuario' : 'Añadir Nuevo Usuario' }}
            </h3>
            <button @click="isUserModalOpen = false" class="text-slate-400 hover:text-slate-600">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              >
                <path d="M18 6 6 18" />
                <path d="m6 6 12 12" />
              </svg>
            </button>
          </div>

          <div class="p-6 overflow-y-auto form-modal-content">
            <div class="grid grid-cols-2 gap-4 mb-4">
              <div class="form-group mb-0">
                <label>Nombre</label>
                <input
                  type="text"
                  v-model="userForm.nombre"
                  class="editable-input"
                  placeholder="Nombre"
                />
              </div>
              <div class="form-group mb-0">
                <label>Apellidos</label>
                <input
                  type="text"
                  v-model="userForm.apellidos"
                  class="editable-input"
                  placeholder="Apellidos"
                />
              </div>
            </div>

            <div class="form-group">
              <label>Correo Electrónico</label>
              <input
                type="email"
                v-model="userForm.correo"
                class="editable-input"
                placeholder="ejemplo@empresa.com"
              />
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
              <div class="form-group mb-0">
                <label>Número de Empleado</label>
                <input
                  type="text"
                  v-model="userForm.numeroEmpleado"
                  class="editable-input"
                  placeholder="EMP-1001"
                />
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
              <div class="password-wrap">
                <input
                  v-model="userForm.password"
                  :type="showPassword ? 'text' : 'password'"
                  class="editable-input"
                  placeholder="••••••••"
                />
                <button
                  type="button"
                  class="password-toggle"
                  :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                  @click="showPassword = !showPassword"
                >
                  <EyeOff v-if="showPassword" :size="18" />
                  <Eye v-else :size="18" />
                </button>
              </div>
              <p v-if="isEditing" class="text-xs text-slate-500 mt-1">
                Dejar en blanco para mantener la contraseña actual.
              </p>
            </div>
          </div>

          <div
            class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-end gap-3"
          >
            <button
              v-if="isEditing && userForm.idUsuario !== (currentUser as any)?.id"
              @click="removeUser(userForm.idUsuario)"
              class="btn-outline text-red-600 border-red-200 hover:bg-red-50"
            >
              Eliminar Usuario
            </button>
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
    font-family:
      system-ui,
      -apple-system,
      sans-serif;
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

  .readonly-input,
  .editable-input {
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

  /* Mostrar/ocultar contraseña */
  .password-wrap {
    position: relative;
  }

  .password-wrap input {
    padding-right: 2.6rem;
  }

  .password-toggle {
    position: absolute;
    right: 0.6rem;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    background: none;
    border: none;
    cursor: pointer;
    color: #64748b;
  }

  .password-toggle:hover {
    color: #3b82f6;
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
  .upload-box.is-dragging {
    border-color: #2563eb;
    background: #eff6ff;
  }
  .upload-box.is-locked {
    opacity: 0.7;
    pointer-events: none;
  }
  .upload-box input[type='file'] {
    display: none;
  }
  .logo-preview {
    max-height: 90px;
    max-width: 100%;
    object-fit: contain;
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
    transition:
      transform 0.2s,
      box-shadow 0.2s;
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
