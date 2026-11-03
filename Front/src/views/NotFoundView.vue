<script setup lang="ts">
  import { computed } from 'vue'
  import { useRoute } from 'vue-router'
  import { ArrowRight, Compass, Home, LifeBuoy, LogIn, LayoutDashboard } from 'lucide-vue-next'
  import { useAuthStore } from '../stores/authStore'
  import PublicHeader from '../components/shared/PublicHeader.vue'
  import AppFooter from '../components/shared/AppFooter.vue'
  import { useSeo } from '../utils/seo'

  const route = useRoute()
  const auth = useAuthStore()

  const ruta = computed(() => route.fullPath)
  const autenticado = computed(() => auth.isAuthenticated)
  const contacto = 'contacto@corion.mx'

  useSeo(() => ({
    title: 'Página no encontrada · coreReports',
    description:
      'La página que buscas no existe o cambió de lugar. Vuelve al inicio o entra a tu cuenta.',
    path: route.path,
    noindex: true,
  }))
</script>

<template>
  <div class="nf-page">
    <PublicHeader />

    <main class="nf-main">
      <span class="orb orb-a" aria-hidden="true"></span>
      <span class="orb orb-b" aria-hidden="true"></span>

      <div class="container nf-inner">
        <span class="code">404</span>

        <span class="pill"><Compass :size="14" /> Ruta no encontrada</span>
        <h1>Esta página se perdió en el taller</h1>
        <p class="lead">
          La dirección que abriste no existe o cambió de lugar. Puedes volver al inicio, entrar a tu
          cuenta o avisarnos para revisarlo.
        </p>

        <code class="path">{{ ruta }}</code>

        <div class="actions">
          <router-link :to="{ name: 'landing' }" class="btn btn-primary">
            <Home :size="17" /> Ir al inicio
          </router-link>
          <router-link v-if="autenticado" :to="{ name: 'dashboard' }" class="btn btn-outline">
            <LayoutDashboard :size="17" /> Ir a mi panel
          </router-link>
          <router-link v-else :to="{ name: 'login' }" class="btn btn-outline">
            <LogIn :size="17" /> Iniciar sesión
          </router-link>
        </div>

        <div class="help-grid">
          <router-link :to="{ name: 'register' }" class="help-card">
            <span class="help-icon"><ArrowRight :size="18" /></span>
            <div>
              <strong>Crear una cuenta</strong>
              <small>Prueba gratis por 7 días, sin tarjeta.</small>
            </div>
          </router-link>

          <a :href="`mailto:${contacto}`" class="help-card">
            <span class="help-icon"><LifeBuoy :size="18" /></span>
            <div>
              <strong>Reportar un enlace roto</strong>
              <small>Escríbenos a {{ contacto }}.</small>
            </div>
          </a>
        </div>
      </div>
    </main>

    <AppFooter />
  </div>
</template>

<style scoped>
  .nf-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    color: #0f172a;
    font-family: 'Inter', sans-serif;
  }
  .nf-main {
    position: relative;
    flex: 1;
    display: flex;
    align-items: center;
    overflow: hidden;
    background:
      radial-gradient(700px 320px at 15% -10%, #e0e7ff 0%, rgba(224, 231, 255, 0) 70%),
      radial-gradient(600px 300px at 90% 10%, #eef2ff 0%, rgba(238, 242, 255, 0) 65%);
  }
  .container {
    width: 100%;
    max-width: 1140px;
    margin: 0 auto;
    padding: 0 1.5rem;
  }
  .orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(70px);
    opacity: 0.5;
    pointer-events: none;
    animation: float 14s ease-in-out infinite;
  }
  .orb-a {
    width: 300px;
    height: 300px;
    background: #bfdbfe;
    top: -60px;
    right: 5%;
  }
  .orb-b {
    width: 240px;
    height: 240px;
    background: #bbf7d0;
    bottom: -100px;
    left: 0;
    animation-delay: -7s;
  }
  @keyframes float {
    0%,
    100% {
      transform: translate3d(0, 0, 0) scale(1);
    }
    50% {
      transform: translate3d(0, -20px, 0) scale(1.05);
    }
  }
  .nf-inner {
    position: relative;
    z-index: 1;
    text-align: center;
    padding-top: 4rem;
    padding-bottom: 4rem;
    max-width: 760px;
  }
  .code {
    display: block;
    font-size: clamp(5rem, 16vw, 9rem);
    font-weight: 900;
    line-height: 0.9;
    letter-spacing: -0.06em;
    background: linear-gradient(120deg, #1e3a8a 0%, #2563eb 45%, #0ea5e9 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin-bottom: 0.5rem;
  }
  .pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    color: #1e3a8a;
    border-radius: 999px;
    padding: 0.3rem 0.8rem;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 1rem;
  }
  h1 {
    margin: 0;
    font-size: clamp(1.6rem, 3.2vw, 2.3rem);
    letter-spacing: -0.02em;
  }
  .lead {
    margin: 1rem auto 0;
    max-width: 34rem;
    color: #475569;
    line-height: 1.7;
  }
  .path {
    display: inline-block;
    margin-top: 1.2rem;
    background: #f1f5f9;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    padding: 0.4rem 0.8rem;
    font-size: 0.82rem;
    color: #475569;
    max-width: 100%;
    overflow-wrap: anywhere;
  }
  .actions {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.8rem;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.3rem;
    border-radius: 8px;
    font-size: 0.92rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid transparent;
    transition:
      transform 0.15s,
      background-color 0.2s,
      border-color 0.2s,
      box-shadow 0.2s;
  }
  .btn:hover {
    transform: translateY(-1px);
  }
  .btn-primary {
    background: #1e3a8a;
    color: #fff;
    box-shadow: 0 10px 24px -14px rgba(30, 58, 138, 0.9);
  }
  .btn-primary:hover {
    background: #172554;
  }
  .btn-outline {
    background: #fff;
    color: #1e3a8a;
    border-color: #c7d2fe;
  }
  .btn-outline:hover {
    border-color: #1e3a8a;
  }
  .help-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.9rem;
    margin-top: 2.5rem;
    text-align: left;
  }
  .help-card {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    text-decoration: none;
    color: inherit;
    transition:
      border-color 0.2s,
      transform 0.2s,
      box-shadow 0.2s;
  }
  .help-card:hover {
    border-color: #c7d2fe;
    transform: translateY(-2px);
    box-shadow: 0 18px 36px -28px rgba(30, 58, 138, 0.55);
  }
  .help-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #eef2ff;
    color: #1e3a8a;
    flex-shrink: 0;
  }
  .help-card strong {
    display: block;
    font-size: 0.9rem;
    color: #0f172a;
  }
  .help-card small {
    display: block;
    font-size: 0.78rem;
    color: #64748b;
    margin-top: 0.15rem;
    line-height: 1.4;
  }

  /* ---------- Modo oscuro ---------- */
  html.theme-dark .nf-page {
    background: #0b1220;
    color: #e2e8f0;
  }
  html.theme-dark .nf-main {
    background:
      radial-gradient(700px 320px at 15% -10%, #172554 0%, rgba(23, 37, 84, 0) 70%),
      radial-gradient(600px 300px at 90% 10%, #0f172a 0%, rgba(15, 23, 42, 0) 65%);
  }
  html.theme-dark h1 {
    color: #f1f5f9;
  }
  html.theme-dark .lead {
    color: #94a3b8;
  }
  html.theme-dark .pill {
    background: #172554;
    border-color: #1d4ed8;
    color: #bfdbfe;
  }
  html.theme-dark .path {
    background: #111a2e;
    border-color: #1e293b;
    color: #cbd5e1;
  }
  html.theme-dark .btn-outline {
    background: #111a2e;
    color: #bfdbfe;
    border-color: #1e293b;
  }
  html.theme-dark .help-card {
    background: #111a2e;
    border-color: #1e293b;
  }
  html.theme-dark .help-card:hover {
    border-color: #1d4ed8;
  }
  html.theme-dark .help-card strong {
    color: #f1f5f9;
  }
  html.theme-dark .help-card small {
    color: #94a3b8;
  }
  html.theme-dark .help-icon {
    background: #172554;
    color: #93c5fd;
  }

  @media (max-width: 620px) {
    .help-grid {
      grid-template-columns: 1fr;
    }
    .nf-inner {
      padding-top: 2.5rem;
      padding-bottom: 2.5rem;
    }
  }
</style>
