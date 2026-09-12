import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'

const lrId = import.meta.env.VITE_LOGROCKET_ID as string | undefined
if (lrId && lrId.trim() !== '' && lrId !== 'your-company/your-app' && lrId.includes('/')) {
  window.addEventListener('error', (e) => {
    if (String(e.message || '').includes('LogRocket') || String((e as any).filename || '').includes('logr-ingest.com')) {
      e.stopImmediatePropagation()
      e.preventDefault()
    }
  }, true)
  import('logrocket').then(({ default: LogRocket }) => {
    try { LogRocket.init(lrId) } catch {}
  }).catch(() => {})
}
import VueApexCharts from 'vue3-apexcharts'
import '@vuepic/vue-datepicker/dist/main.css'
import './assets/styles/main.css'
import DateFilter from './components/shared/DateFilter.vue'
import Toast from "vue-toastification"
import type { PluginOptions } from "vue-toastification"
import "vue-toastification/dist/index.css"
import i18n from './i18n'

const app = createApp(App)

app.component('DateFilter', DateFilter)

const options: PluginOptions = {
};

app.use(Toast, options)
app.use(createPinia()) // Using Pinia
app.use(i18n)
app.use(router)
app.use(VueApexCharts)

// Sesión expirada (401 tras revalidación): redirigir al login conservando el destino
window.addEventListener('auth:expirado', () => {
  if (router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath, expirada: '1' } })
  }
})

app.mount('#app')