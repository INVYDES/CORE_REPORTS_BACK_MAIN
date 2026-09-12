import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VueWebManifestPlugin } from './scripts/pwa-manifest'

// https://vite.dev/config/
export default defineConfig({
  base: process.env.VITE_BASE || '/',
  plugins: [
    vue(),
    tailwindcss(),
    VueWebManifestPlugin(),
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
