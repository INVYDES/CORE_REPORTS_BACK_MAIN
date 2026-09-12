<script setup lang="ts">
import { useRouter } from 'vue-router'
const router = useRouter()
</script>
<template>
  <div style="min-height:80vh; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:2rem;">
    <div style="width:80px; height:80px; background:#fef3c7; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:40px; margin-bottom:1rem;">…</div>
    <h1 style="color:#d97706; font-size:1.8rem; font-weight:700;">Pago pendiente</h1>
    <p style="color:#475569; margin-top:0.5rem;">Tu pago está en proceso. Te notificaremos cuando se acredite.</p>
    <button @click="router.push({ name: 'dashboard' })" style="margin-top:1.5rem; background:#d97706; color:white; border:none; padding:0.75rem 1.5rem; border-radius:8px; cursor:pointer; font-weight:600;">Ir al dashboard</button>
  </div>
</template>
