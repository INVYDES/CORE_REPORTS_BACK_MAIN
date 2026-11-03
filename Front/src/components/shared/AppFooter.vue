<script setup lang="ts">
  import { useI18n } from 'vue-i18n'
  import { ArrowUpRight, Mail, ShieldCheck } from 'lucide-vue-next'

  const { t } = useI18n()
  const year = new Date().getFullYear()
  const contactEmail = 'contacto@corion.mx'
</script>

<template>
  <footer class="app-footer">
    <div class="container">
      <div class="footer-top">
        <div class="footer-brand">
          <span class="brand-mark">coreReport</span>
          <p>{{ t('footer.tagline') }}</p>
          <span class="legal-badge"><ShieldCheck :size="14" /> {{ t('footer.badge') }}</span>
        </div>

        <nav class="footer-col" :aria-label="t('footer.product')">
          <h4>{{ t('footer.product') }}</h4>
          <router-link :to="{ name: 'landing' }">{{ t('footer.home') }}</router-link>
          <router-link :to="{ name: 'register' }">{{ t('footer.trial') }}</router-link>
          <router-link :to="{ name: 'login' }">{{ t('footer.login') }}</router-link>
          <router-link :to="{ name: 'landing', hash: '#precios' }">{{
            t('footer.pricing')
          }}</router-link>
        </nav>

        <nav class="footer-col" :aria-label="t('footer.legal')">
          <h4>{{ t('footer.legal') }}</h4>
          <router-link :to="{ name: 'privacy' }">{{ t('footer.privacy') }}</router-link>
          <router-link :to="{ name: 'terms' }">{{ t('footer.terms') }}</router-link>
          <router-link :to="{ name: 'legal-notice' }">{{ t('footer.notice') }}</router-link>
        </nav>

        <div class="footer-col">
          <h4>{{ t('footer.contact') }}</h4>
          <a :href="`mailto:${contactEmail}`" class="contact-link">
            <Mail :size="15" /> {{ contactEmail }}
          </a>
          <router-link :to="{ name: 'register' }" class="contact-link">
            {{ t('footer.demo') }} <ArrowUpRight :size="15" />
          </router-link>
        </div>
      </div>

      <div class="footer-bottom">
        <span>© {{ year }} coreReports. {{ t('footer.rights') }}</span>
        <span class="footer-legal-links">
          <router-link :to="{ name: 'privacy' }">{{ t('footer.privacy') }}</router-link>
          <span aria-hidden="true">·</span>
          <router-link :to="{ name: 'terms' }">{{ t('footer.terms') }}</router-link>
          <span aria-hidden="true">·</span>
          <router-link :to="{ name: 'legal-notice' }">{{ t('footer.notice') }}</router-link>
        </span>
      </div>

      <p class="legal-note">{{ t('footer.note') }}</p>
    </div>
  </footer>
</template>

<style scoped>
  .app-footer {
    background: #0f172a;
    color: #94a3b8;
    padding: 3.5rem 0 1.5rem;
  }
  .container {
    width: 100%;
    max-width: 1140px;
    margin: 0 auto;
    padding: 0 1.5rem;
  }
  .footer-top {
    display: grid;
    grid-template-columns: 1.6fr 1fr 1fr 1.2fr;
    gap: 2rem;
    padding-bottom: 2.25rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.2);
  }
  .footer-brand .brand-mark {
    display: block;
    font-weight: 900;
    font-size: 1.25rem;
    letter-spacing: -0.02em;
    color: #fff;
    margin-bottom: 0.6rem;
  }
  .footer-brand p {
    margin: 0 0 0.9rem;
    font-size: 0.9rem;
    line-height: 1.6;
    max-width: 24rem;
  }
  .legal-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #bfdbfe;
    background: rgba(59, 130, 246, 0.12);
    border: 1px solid rgba(59, 130, 246, 0.25);
    border-radius: 999px;
    padding: 0.25rem 0.7rem;
  }
  .footer-col {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
  }
  .footer-col h4 {
    margin: 0 0 0.35rem;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: #e2e8f0;
  }
  .footer-col a {
    color: #94a3b8;
    text-decoration: none;
    font-size: 0.88rem;
    width: fit-content;
    transition:
      color 0.2s,
      transform 0.2s;
  }
  .footer-col a:hover {
    color: #fff;
    transform: translateX(2px);
  }
  .contact-link {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
  }
  .footer-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding-top: 1.25rem;
    font-size: 0.82rem;
  }
  .footer-legal-links {
    display: inline-flex;
    gap: 0.5rem;
    align-items: center;
  }
  .footer-legal-links a {
    color: #cbd5e1;
    text-decoration: none;
  }
  .footer-legal-links a:hover {
    color: #fff;
  }
  .legal-note {
    margin: 1rem 0 0;
    font-size: 0.74rem;
    line-height: 1.6;
    color: #64748b;
    max-width: 70rem;
  }

  @media (max-width: 980px) {
    .footer-top {
      grid-template-columns: 1fr 1fr;
    }
  }
  @media (max-width: 620px) {
    .footer-top {
      grid-template-columns: 1fr;
      gap: 1.5rem;
    }
    .footer-bottom {
      flex-direction: column;
      align-items: flex-start;
    }
  }
</style>
