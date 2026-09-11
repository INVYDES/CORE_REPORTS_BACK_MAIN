import LogRocket from 'logrocket'
if (import.meta.env.VITE_LOGROCKET_ID) {
  LogRocket.init(import.meta.env.VITE_LOGROCKET_ID)
}
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import VueApexCharts from 'vue3-apexcharts'
import '@vuepic/vue-datepicker/dist/main.css'
import './assets/styles/main.css'
import DateFilter from './components/shared/DateFilter.vue'
import Toast from "vue-toastification"
import type { PluginOptions } from "vue-toastification"
import "vue-toastification/dist/index.css"

const app = createApp(App)

app.component('DateFilter', DateFilter)

const options: PluginOptions = {
};

app.use(Toast, options)
app.use(createPinia()) // Using Pinia
app.use(router)
app.use(VueApexCharts)
app.mount('#app')