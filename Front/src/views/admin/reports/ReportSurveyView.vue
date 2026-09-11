<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import client from '../../../api/client'
import { useToast } from 'vue-toastification'
import { ArrowLeft, Star, Send } from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const reporte = ref<any>(null)
const encuestaExistente = ref<any>(null)
const isSubmitted = ref(false)

const form = reactive({ calificacion: 0, comentarios: '', nombreFirma: '' })

const isReadOnly = computed(()=> !!encuestaExistente.value)
const empresaName = computed(()=> reporte.value?.dependencia?.nombre || 'Empresa')
const areaName = computed(()=> reporte.value?.area?.nombre || 'Área')
const folioReporte = computed(()=> reporte.value?.folio || '')

const setRating = (n:number)=> { if (!isReadOnly.value) form.calificacion = n }

onMounted(async ()=>{
    try {
        const res = await client.get(`/reportes/${route.params.id}`).then(r=>r.data)
        reporte.value = res
        try {
            const enc = await client.get('/encuestas', { params:{ reporte_id: route.params.id } }).then(r=>r.data)
            const list = enc.data || enc || []
            if (Array.isArray(list) && list.length) {
                encuestaExistente.value = list[0]
                form.calificacion = encuestaExistente.value.calificacion * 2
                form.comentarios = encuestaExistente.value.comentario || ''
                form.nombreFirma = encuestaExistente.value.respondido_por || ''
            } else if (enc && enc.reporte_id) {
                encuestaExistente.value = enc
            }
        } catch {}
    } catch {}
})

const submitSurvey = async ()=>{
    if (!form.calificacion) { toast.error('Selecciona una calificación'); return }
    if (!form.nombreFirma.trim()) { toast.error('Ingresa tu nombre'); return }
    if (!reporte.value || reporte.value.estatus !== 'finalizado') { toast.error('Solo se puede encuestar un reporte finalizado'); return }
    try {
        const cal5 = Math.max(1, Math.min(5, Math.ceil(form.calificacion / 2)))
        await client.post('/encuestas', { reporte_id: reporte.value.id, calificacion: cal5, comentario: form.comentarios, respondido_por: form.nombreFirma })
        isSubmitted.value = true
        toast.success('Encuesta enviada')
        setTimeout(()=> router.push({ name:'report-detail', params:{ id: reporte.value.id } }), 1200)
    } catch(e:any){ toast.error(e?.response?.data?.message || JSON.stringify(e?.response?.data?.errors) || 'Error') }
}
</script>

<template>
    <div class="page-container max-w-2xl mx-auto py-8 px-4">
        <!-- Back Button -->
        <button @click="router.back()" class="flex items-center gap-2 text-slate-500 hover:text-indigo-600 transition-colors mb-6 group">
            <ArrowLeft :size="18" class="group-hover:-translate-x-1 transition-transform" />
            <span class="font-medium">Volver al Reporte</span>
        </button>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <!-- Header -->
            <div class="bg-indigo-600 text-white p-6 md:p-8 text-center flex flex-col items-center">
                <Star :size="48" class="text-amber-400 mb-4" :fill="'currentColor'" />
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Evaluación de Servicio</h1>
                <p class="text-indigo-100 opacity-90 max-w-md mx-auto">Tu opinión es muy importante para ayudarnos a mantener la calidad de nuestros servicios.</p>
            </div>

            <!-- Context Info -->
            <div class="bg-slate-50 border-b border-slate-100 p-4 flex flex-wrap gap-4 justify-between items-center text-sm">
                <div>
                    <span class="block text-slate-500 font-semibold mb-0.5">Empresa</span>
                    <span class="text-slate-800 font-medium">{{ empresaName }}</span>
                </div>
                <div>
                    <span class="block text-slate-500 font-semibold mb-0.5">Área / Subdependencia</span>
                    <span class="text-slate-800 font-medium">{{ areaName }}</span>
                </div>
                <div>
                    <span class="block text-slate-500 font-semibold mb-0.5">Folio Reporte</span>
                    <span class="bg-white px-2 py-0.5 border border-slate-200 rounded font-mono text-xs text-slate-700">{{ folioReporte }}</span>
                </div>
            </div>

            <!-- Pre-existing notification -->
            <div v-if="isReadOnly && !isSubmitted" class="m-6 p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg text-sm flex gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 text-blue-600"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                Este reporte ya cuenta con una evaluación de servicio registrada. La información mostrada a continuación es de solo lectura.
            </div>

            <!-- Success notification -->
            <div v-if="isSubmitted" class="m-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg flex items-center gap-3 animate-fade-in">
                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center flex-shrink-0 text-emerald-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div>
                    <h4 class="font-bold">¡Encuesta enviada con éxito!</h4>
                    <p class="text-sm opacity-90">Redirigiendo al reporte...</p>
                </div>
            </div>

            <!-- Form Body -->
            <div class="p-6 md:p-8 space-y-8">
                
                <!-- Rating Score -->
                <div class="form-section">
                    <label class="block text-slate-800 font-bold mb-1 text-lg text-center">Calificación del Servicio</label>
                    <p class="text-slate-500 text-sm text-center mb-6">En una escala del 1 al 10, ¿cómo calificarías la atención recibida?</p>
                    
                    <div class="flex flex-wrap justify-center gap-2">
                        <button 
                            v-for="i in 10" 
                            :key="i"
                            type="button"
                            @click="setRating(i)"
                            :class="[
                                'w-12 h-12 rounded-xl font-bold text-lg transition-all',
                                form.calificacion === i ? 'bg-indigo-600 text-white shadow-md scale-110' : 'bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600',
                                isReadOnly ? 'cursor-default' : 'cursor-pointer'
                            ]"
                            :disabled="isReadOnly"
                        >
                            {{ i }}
                        </button>
                    </div>
                </div>

                <!-- Comments -->
                <div class="form-section">
                    <label class="block text-slate-800 font-bold mb-2">Comentarios / Observaciones</label>
                    <textarea 
                        v-model="form.comentarios"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-700 min-h-[120px] focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-colors"
                        :class="{'opacity-80 cursor-not-allowed': isReadOnly}"
                        placeholder="Escribe tus comentarios aquí... (Opcional)"
                        :readonly="isReadOnly"
                    ></textarea>
                </div>

                <!-- Signature / Name -->
                <div class="form-section">
                    <label class="block text-slate-800 font-bold mb-2">Nombre</label>
                    <p class="text-slate-500 text-sm mb-3">Nombre de la persona que recibe y avala los trabajos realizados.</p>
                    <input 
                        type="text" 
                        v-model="form.nombreFirma"
                        class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-colors"
                        :class="{'opacity-80 cursor-not-allowed': isReadOnly}"
                        placeholder="Ej. Juan Pérez"
                        :readonly="isReadOnly"
                    />
                </div>

                <!-- Submit Action -->
                <div v-if="!isReadOnly" class="pt-4 border-t border-slate-100 flex justify-end">
                    <button @click="submitSurvey" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg flex items-center gap-2 transition-colors shadow-sm">
                        <Send :size="18" />
                        Enviar Evaluación
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.page-container {
    font-family: system-ui, -apple-system, sans-serif;
}
.animate-fade-in {
    animation: fadeIn 0.4s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>