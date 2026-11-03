<script setup lang="ts">
  import { useI18n } from 'vue-i18n'
  import { ArrowLeft, Moon, Sun } from 'lucide-vue-next'
  import { useTheme } from '../../utils/theme'

  const { t } = useI18n()
  const { oscuro, alternarTema } = useTheme()
</script>

<template>
  <header class="public-header">
    <div class="container">
      <router-link :to="{ name: 'landing' }" class="brand">coreReport</router-link>

      <nav class="links">
        <router-link :to="{ name: 'landing' }" class="link-back">
          <ArrowLeft :size="16" /> {{ t('footer.home') }}
        </router-link>
        <button
          class="icon-btn"
          type="button"
          :aria-label="oscuro ? t('common.themeLight') : t('common.themeDark')"
          :title="oscuro ? t('common.themeLight') : t('common.themeDark')"
          @click="alternarTema"
        >
          <Sun v-if="oscuro" :size="18" />
          <Moon v-else :size="18" />
        </button>
        <router-link :to="{ name: 'login' }" class="btn btn-ghost">{{
          t('footer.login')
        }}</router-link>
        <router-link :to="{ name: 'register' }" class="btn btn-primary">{{
          t('footer.trial')
        }}</router-link>
      </nav>
    </div>
  </header>
</template>

<style scoped>
  .public-header {
    position: sticky;
    top: 0;
    z-index: 40;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid #e2e8f0;
    transition:
      background-color 0.25s,
      border-color 0.25s;
  }
  .container {
    width: 100%;
    max-width: 1140px;
    margin: 0 auto;
    padding: 0 1.5rem;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
  }
  .brand {
    font-weight: 900;
    font-size: 1.2rem;
    letter-spacing: -0.02em;
    color: #1e3a8a;
    text-decoration: none;
  }
  .links {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .link-back {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #475569;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 500;
    margin-right: 0.5rem;
  }
  .link-back:hover {
    color: #1e3a8a;
  }
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0.95rem;
    border-radius: 8px;
    font-size: 0.86rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid transparent;
    transition:
      background-color 0.2s,
      color 0.2s,
      border-color 0.2s;
  }
  .btn-ghost {
    color: #0f172a;
  }
  .btn-ghost:hover {
    background: #f1f5f9;
  }
  .btn-primary {
    background: #1e3a8a;
    color: #fff;
  }
  .btn-primary:hover {
    background: #172554;
  }

  .icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    cursor: pointer;
    transition:
      background-color 0.2s,
      color 0.2s,
      border-color 0.2s;
  }
  .icon-btn:hover {
    color: #1e3a8a;
    border-color: #c7d2fe;
  }

  /* ---------- Modo oscuro ---------- */
  html.theme-dark .public-header {
    background: rgba(11, 18, 32, 0.94);
    border-bottom-color: #1e293b;
  }
  html.theme-dark .brand {
    color: #93c5fd;
  }
  html.theme-dark .link-back {
    color: #cbd5e1;
  }
  html.theme-dark .link-back:hover {
    color: #fff;
  }
  html.theme-dark .btn-ghost {
    color: #e2e8f0;
  }
  html.theme-dark .btn-ghost:hover {
    background: #1e293b;
  }
  html.theme-dark .icon-btn {
    background: #111a2e;
    border-color: #1e293b;
    color: #cbd5e1;
  }
  html.theme-dark .icon-btn:hover {
    color: #fff;
    border-color: #1d4ed8;
  }

  @media (max-width: 620px) {
    .link-back :deep(span) {
      display: none;
    }
    .btn-ghost {
      display: none;
    }
  }
</style>
