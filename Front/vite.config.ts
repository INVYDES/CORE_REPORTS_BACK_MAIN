import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  base: '/core-reports/',
  plugins: [
    vue(),
    tailwindcss(),
  ],
  define: {
    __BUILD_TIME__: JSON.stringify(new Date().toLocaleString()),
  },
  server: {
    proxy: {
      '/api': { target: 'http://localhost:8000', changeOrigin: true }
    }
  }
})

