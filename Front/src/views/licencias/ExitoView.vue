<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
const route = useRoute()
const router = useRouter()
const toast = useToast()
onMounted(() => {
  const paymentId = route.query.payment_id || route.query.collection_id
  if (paymentId) {
    toast.success('¡Pago aprobado! Verificando...')
  }
  setTimeout(() => router.push({ name: 'dashboard' }), 3000)
})
</script>
<template>
  <div style="min-height:80vh; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:2rem;">
    <div style="width:80px; height:80px; background:#dcfce7; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:40px; margin-bottom:1rem;">✓</div>
    <h1 style="color:#16a34a; font-size:1.8rem; font-weight:700;">¡Pago aprobado!</h1>
    <p style="color:#475569; margin-top:0.5rem;">Tu licencia ha sido activada. Serás redirigido al dashboard...</p>
    <button @click="$router.push({ name: 'dashboard' })" style="margin-top:1.5rem; background:#16a34a; color:white; border:none; padding:0.75rem 1.5rem; border-radius:8px; cursor:pointer; font-weight:600;">Ir al dashboard</button>
  </div>
</template>
