<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { useRoute } from 'vue-router'
import {
  Building2,
  User,
  Phone,
  Mail,
  AlertCircle,
  FileText,
  Camera,
  X,
  CheckCircle2,
  Copy,
  Check,
  Wrench,
  Loader2,
} from 'lucide-vue-next'
import client from '../../api/client'

const route = useRoute()
const slug = String(route.params.slug || '').trim()

// Estado de la empresa
const isLoadingEmpresa = ref(true)
const empresaNotFound = ref(false)
const empresa = ref<{
  id: number
  nombre: string
  slug: string
  logo: string | null
  telefono?: string
  correoContacto?: string
} | null>(null)

// Estado del formulario
const form = reactive({
  lugar: '',
  solicitante: '',
  telefono: '',
  email: '',
  asunto: '',
  descripcion: '',
  prioridad: 'media',
  evidencias: [] as string[],
})

const isSubmitting = ref(false)
const submitError = ref<string | null>(null)

// Estado de éxito
const isSubmitted = ref(false)
const resultTicket = ref<{
  folio: string
  empresa: string
  lugar: string
  solicitante: string
  fecha: string
} | null>(null)

const copiedFolio = ref(false)

const loadEmpresa = async () => {
  if (!slug) {
    empresaNotFound.value = true
    isLoadingEmpresa.value = false
    return
  }

  isLoadingEmpresa.value = true
  empresaNotFound.value = false

  try {
    const res = await client.get(`/public/empresa/${encodeURIComponent(slug)}`)
    empresa.value = res.data?.data || null
    if (!empresa.value) {
      empresaNotFound.value = true
    }
  } catch (err: any) {
    empresaNotFound.value = true
  } finally {
    isLoadingEmpresa.value = false
  }
}

const handleFileSelect = (e: Event) => {
  const input = e.target as HTMLInputElement
  if (!input.files || input.files.length === 0) return

  const remainingSlots = 3 - form.evidencias.length
  if (remainingSlots <= 0) {
    alert('Puedes adjuntar un máximo de 3 fotos.')
    input.value = ''
    return
  }

  const files = Array.from(input.files).slice(0, remainingSlots)

  files.forEach(file => {
    if (!file.type.startsWith('image/')) {
      alert(`El archivo ${file.name} no es una imagen válida.`)
      return
    }

    if (file.size > 5 * 1024 * 1024) {
      alert(`La imagen ${file.name} excede el límite de 5 MB.`)
      return
    }

    const reader = new FileReader()
    reader.onload = evt => {
      const b64 = evt.target?.result as string
      if (b64 && form.evidencias.length < 3) {
        form.evidencias.push(b64)
      }
    }
    reader.readAsDataURL(file)
  })

  input.value = ''
}

const removeEvidence = (index: number) => {
  form.evidencias.splice(index, 1)
}

const submitTicket = async () => {
  submitError.value = null

  if (!form.lugar.trim()) {
    submitError.value = 'Por favor escribe el nombre del lugar, sucursal o cliente.'
    return
  }
  if (!form.solicitante.trim()) {
    submitError.value = 'Por favor escribe tu nombre.'
    return
  }
  if (!form.telefono.trim()) {
    submitError.value = 'Por favor escribe un teléfono o celular de contacto.'
    return
  }
  if (!form.asunto.trim()) {
    submitError.value = 'Por favor indica el asunto o título del problema.'
    return
  }
  if (!form.descripcion.trim()) {
    submitError.value = 'Por favor describe la falla o servicio solicitado.'
    return
  }

  isSubmitting.value = true

  try {
    const res = await client.post(`/public/empresa/${encodeURIComponent(slug)}/tickets`, {
      lugar: form.lugar.trim(),
      solicitante: form.solicitante.trim(),
      telefono: form.telefono.trim(),
      email: form.email.trim() || null,
      asunto: form.asunto.trim(),
      descripcion: form.descripcion.trim(),
      prioridad: form.prioridad,
      evidencias: form.evidencias,
    })

    resultTicket.value = res.data?.data || {
      folio: res.data?.folio || 'TCK-0000',
      empresa: empresa.value?.nombre || 'CoreReports',
      lugar: form.lugar,
      solicitante: form.solicitante,
      fecha: new Date().toLocaleString(),
    }

    isSubmitted.value = true
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (err: any) {
    submitError.value = err?.response?.data?.message || 'Ocurrió un error al registrar la solicitud. Por favor intenta de nuevo.'
  } finally {
    isSubmitting.value = false
  }
}

const resetForm = () => {
  form.lugar = ''
  form.solicitante = ''
  form.telefono = ''
  form.email = ''
  form.asunto = ''
  form.descripcion = ''
  form.prioridad = 'media'
  form.evidencias = []
  isSubmitted.value = false
  resultTicket.value = null
  submitError.value = null
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const copyFolio = async () => {
  if (!resultTicket.value?.folio) return
  try {
    await navigator.clipboard.writeText(resultTicket.value.folio)
    copiedFolio.value = true
    setTimeout(() => {
      copiedFolio.value = false
    }, 2500)
  } catch {
    // Fallback silencioso
  }
}

onMounted(() => {
  loadEmpresa()
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 py-8 px-4 sm:px-6 lg:px-8 font-sans">
    <div class="max-w-2xl mx-auto">

      <!-- Estado: Cargando Empresa -->
      <div v-if="isLoadingEmpresa" class="text-center py-20 bg-white rounded-xl shadow-sm border border-slate-200">
        <Loader2 class="animate-spin text-blue-600 mx-auto mb-3" :size="36" />
        <p class="text-slate-600 font-medium">Cargando portal de atención...</p>
      </div>

      <!-- Estado: Empresa No Encontrada -->
      <div v-else-if="empresaNotFound" class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center py-16">
        <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
          <AlertCircle :size="32" />
        </div>
        <h2 class="text-xl font-bold text-slate-800 mb-2">Empresa no encontrada</h2>
        <p class="text-slate-600 text-sm max-w-md mx-auto mb-6">
          El enlace que abriste (<code class="bg-slate-100 px-2 py-0.5 rounded text-slate-700 font-mono">{{ slug }}</code>) no corresponde a ninguna empresa activa en el sistema.
        </p>
        <p class="text-xs text-slate-400">Verifica que la dirección web esté escrita correctamente o solicita el enlace a tu proveedor de servicios.</p>
      </div>

      <!-- Estado: Éxito (Ticket Enviado) -->
      <div v-else-if="isSubmitted && resultTicket" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 sm:p-8">
        <div class="text-center pb-6 border-b border-slate-100">
          <div class="w-20 h-20 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner">
            <CheckCircle2 :size="48" />
          </div>
          <span class="inline-block bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1 rounded-full mb-2">
            SOLICITUD REGISTRADA
          </span>
          <h2 class="text-2xl font-black text-slate-800 mb-1">¡Solicitud recibida con éxito!</h2>
          <p class="text-sm text-slate-600 max-w-md mx-auto">
            El equipo de <strong class="text-slate-800">{{ empresa?.nombre }}</strong> ha recibido tu reporte y se pondrá en contacto para coordinar la atención.
          </p>
        </div>

        <!-- Tarjeta de Folio -->
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-5 my-6">
          <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
              <p class="text-xs uppercase tracking-wider font-bold text-blue-700">Tu Folio de Atención</p>
              <p class="text-2xl font-black text-blue-900 tracking-tight font-mono">{{ resultTicket.folio }}</p>
            </div>
            <button
              type="button"
              @click="copyFolio"
              class="inline-flex items-center gap-1.5 bg-white hover:bg-blue-100 text-blue-700 border border-blue-300 px-3 py-1.5 rounded-lg text-xs font-semibold shadow-sm transition-all cursor-pointer"
            >
              <Check v-if="copiedFolio" :size="14" class="text-emerald-600" />
              <Copy v-else :size="14" />
              <span>{{ copiedFolio ? '¡Copiado!' : 'Copiar Folio' }}</span>
            </button>
          </div>
          <p class="text-xs text-blue-600/80 mt-2">
            Guarda este folio como comprobante para dar seguimiento con el personal técnico.
          </p>
        </div>

        <!-- Resumen de datos enviados -->
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 text-xs space-y-2 mb-6">
          <div class="flex justify-between">
            <span class="text-slate-500 font-medium">Lugar / Sucursal:</span>
            <span class="text-slate-800 font-bold text-right">{{ resultTicket.lugar }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500 font-medium">Solicitante:</span>
            <span class="text-slate-800 font-bold text-right">{{ resultTicket.solicitante }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500 font-medium">Fecha de registro:</span>
            <span class="text-slate-800 text-right">{{ resultTicket.fecha }}</span>
          </div>
        </div>

        <div class="text-center">
          <button
            type="button"
            @click="resetForm"
            class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm px-6 py-2.5 rounded-lg shadow-sm transition-all cursor-pointer"
          >
            <Wrench :size="16" />
            <span>Levantar otro reporte</span>
          </button>
        </div>
      </div>

      <!-- Estado: Formulario Activo -->
      <div v-else class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">

        <!-- Encabezado corporativo de la empresa -->
        <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-blue-950 text-white p-6 sm:p-8">
          <div class="flex items-center gap-4 flex-wrap">
            <div
              v-if="empresa?.logo"
              class="w-16 h-16 bg-white rounded-lg p-1.5 flex items-center justify-center shadow-md overflow-hidden flex-shrink-0"
            >
              <img :src="empresa.logo" :alt="empresa.nombre" class="max-w-full max-h-full object-contain" />
            </div>
            <div
              v-else
              class="w-14 h-14 bg-blue-600/30 border border-blue-400/40 rounded-lg flex items-center justify-center text-blue-300 font-bold text-xl flex-shrink-0"
            >
              <Wrench :size="28" />
            </div>
            <div>
              <span class="inline-block bg-blue-500/20 text-blue-300 border border-blue-400/30 text-[11px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full mb-1">
                Portal de Atención a Clientes
              </span>
              <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-white m-0">
                {{ empresa?.nombre }}
              </h1>
            </div>
          </div>
          <p class="text-xs sm:text-sm text-slate-300 mt-3 max-w-xl leading-relaxed">
            Completa los siguientes datos para reportar una falla técnica o solicitar mantenimiento en tus instalaciones.
          </p>
        </div>

        <!-- Formulario -->
        <form @submit.prevent="submitTicket" class="p-6 sm:p-8 space-y-5">

          <!-- Mensaje de error general si falla la validación o envío -->
          <div v-if="submitError" class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg flex items-start gap-2">
            <AlertCircle :size="16" class="flex-shrink-0 mt-0.5" />
            <span>{{ submitError }}</span>
          </div>

          <!-- 1. Lugar / Sucursal -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              1. Lugar o Sucursal <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <Building2 :size="16" />
              </div>
              <input
                type="text"
                v-model="form.lugar"
                placeholder="Ej. Restaurante Los Arcos, Sucursal Norte, Bodega 4..."
                class="w-full pl-9 pr-3 py-2.5 text-sm rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition-colors"
                required
              />
            </div>
            <p class="text-[11px] text-slate-500 mt-1">Escribe el nombre del negocio, sucursal o ubicación exacta donde se requiere el servicio.</p>
          </div>

          <!-- 2. Solicitante y Teléfono (en dos columnas en pantallas medianas) -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                2. Tu Nombre y Apellido <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                  <User :size="16" />
                </div>
                <input
                  type="text"
                  v-model="form.solicitante"
                  placeholder="Ej. Juan Pérez"
                  class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition-colors"
                  required
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                3. Teléfono / WhatsApp <span class="text-red-500">*</span>
              </label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                  <Phone :size="16" />
                </div>
                <input
                  type="tel"
                  v-model="form.telefono"
                  placeholder="Ej. 442 123 4567"
                  class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition-colors"
                  required
                />
              </div>
            </div>
          </div>

          <!-- 4. Correo electrónico (opcional) -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              4. Correo Electrónico <span class="text-slate-400 text-[10px] font-normal lowercase">(opcional)</span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <Mail :size="16" />
              </div>
              <input
                type="email"
                v-model="form.email"
                placeholder="Ej. contacto@sucursal.com"
                class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition-colors"
              />
            </div>
          </div>

          <!-- 5. Asunto o Título de la Falla -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              5. Asunto o Falla Principal <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <AlertCircle :size="16" />
              </div>
              <input
                type="text"
                v-model="form.asunto"
                placeholder="Ej. Fuga de agua en tarja, Aire acondicionado no enciende..."
                class="w-full pl-9 pr-3 py-2.5 text-sm rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition-colors"
                required
              />
            </div>
          </div>

          <!-- 6. Descripción Detallada -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
              6. Detalles de la Falla <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <textarea
                v-model="form.descripcion"
                rows="4"
                placeholder="Describe qué ocurrió, en qué zona exacta se encuentra el equipo y cualquier detalle relevante para el técnico..."
                class="w-full p-3 text-sm rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:outline-none focus:border-blue-600 focus:ring-1 focus:ring-blue-600 transition-colors"
                required
              ></textarea>
            </div>
          </div>

          <!-- 7. Evidencias Fotográficas (Máximo 3 fotos) -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                7. Fotos de la Falla <span class="text-slate-400 text-[10px] font-normal lowercase">(opcional, máx 3)</span>
              </label>
              <span class="text-[11px] text-slate-500 font-medium">{{ form.evidencias.length }} de 3 fotos</span>
            </div>

            <!-- Botón para adjuntar fotos -->
            <div v-if="form.evidencias.length < 3" class="mb-3">
              <label class="flex flex-col items-center justify-center border-2 border-dashed border-slate-300 hover:border-blue-500 hover:bg-blue-50/50 rounded-lg py-4 px-4 cursor-pointer transition-colors text-center">
                <Camera :size="24" class="text-slate-400 mb-1" />
                <span class="text-xs font-semibold text-blue-700">Tomar foto o adjuntar archivo</span>
                <span class="text-[10px] text-slate-400 mt-0.5">JPG, PNG o WebP (máx. 5 MB por foto)</span>
                <input
                  type="file"
                  accept="image/*"
                  capture="environment"
                  multiple
                  @change="handleFileSelect"
                  class="hidden"
                />
              </label>
            </div>

            <!-- Previsualización de imágenes adjuntas -->
            <div v-if="form.evidencias.length > 0" class="grid grid-cols-3 gap-3">
              <div
                v-for="(imgSrc, idx) in form.evidencias"
                :key="idx"
                class="relative aspect-square rounded-lg border border-slate-200 overflow-hidden bg-slate-100 group shadow-sm"
              >
                <img :src="imgSrc" class="w-full h-full object-cover" alt="Evidencia" />
                <button
                  type="button"
                  @click="removeEvidence(idx)"
                  class="absolute top-1 right-1 bg-red-600 hover:bg-red-700 text-white p-1 rounded-full shadow transition-all cursor-pointer"
                  title="Quitar foto"
                >
                  <X :size="12" />
                </button>
              </div>
            </div>
          </div>

          <!-- Botón de Envío -->
          <div class="pt-4 border-t border-slate-100">
            <button
              type="submit"
              :disabled="isSubmitting"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm py-3 px-6 rounded-lg shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Loader2 v-if="isSubmitting" class="animate-spin" :size="18" />
              <span>{{ isSubmitting ? 'Enviando solicitud...' : 'Enviar Solicitud de Servicio' }}</span>
            </button>
            <p class="text-[11px] text-center text-slate-400 mt-2">
              Al enviar tu solicitud, el personal de {{ empresa?.nombre }} recibirá una notificación de inmediato.
            </p>
          </div>

        </form>

      </div>

      <!-- Pie de página discreto -->
      <div class="text-center mt-6 text-xs text-slate-400">
        <span>Powered by CoreReports</span>
      </div>

    </div>
  </div>
</template>

