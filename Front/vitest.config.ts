import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: 'jsdom',
    // Las cookies de sesión se emiten con Secure/SameSite=Strict (igual que en
    // producción sobre HTTPS); jsdom solo las persiste si el origen es https.
    environmentOptions: {
      jsdom: { url: 'https://localhost/' },
    },
    include: ['src/**/*.{test,spec}.{js,ts,vue}'],
    coverage: {
      provider: 'v8',
      reportsDirectory: 'coverage',
      reporter: ['text', 'lcov', 'html'],
      include: ['src/**/*.{vue,ts,js}'],
      exclude: ['src/data/mock*', 'node_modules', 'dist'],
      thresholds: {
        lines: 70,
        functions: 70,
        branches: 60,
        statements: 70,
      },
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, './src'),
    },
  },
})
