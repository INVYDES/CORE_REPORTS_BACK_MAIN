<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Component } from 'vue'
import type { ApexOptions } from 'apexcharts'
import {
    Wrench, Ticket, ChartColumn, Users, ShieldCheck, Clock, Check, ArrowRight,
    Building2, CalendarClock, FileText, BellRing, Menu, X, Gauge, TrendingUp,
    AlertOctagon, Activity, CheckCircle2, ArrowUpRight, Zap, Sun, Moon, Globe
} from 'lucide-vue-next'
import StatCard from '../components/ui/StatCard.vue'
import AppFooter from '../components/shared/AppFooter.vue'
import { useSeo, SITE_URL } from '../utils/seo'
import { useTheme } from '../utils/theme'
import { cambiarIdioma } from '../i18n'

const { t, locale } = useI18n()
const { oscuro, alternarTema } = useTheme()

type EstadoCodigo = 'open' | 'progress' | 'scheduled' | 'done' | 'partial'
type PrioridadCodigo = 'high' | 'medium' | 'low'

const statusLabel = (code: EstadoCodigo): string => ({
    open: t('landing.product.demo.statusOpen'),
    progress: t('landing.product.demo.statusProgress'),
    scheduled: t('landing.product.demo.statusScheduled'),
    done: t('landing.product.demo.statusFinished'),
    partial: t('landing.product.demo.statusPartial'),
}[code])

const priorityLabel = (code: PrioridadCodigo): string => ({
    high: t('landing.product.demo.priorityHigh'),
    medium: t('landing.product.demo.priorityMedium'),
    low: t('landing.product.demo.priorityLow'),
}[code])

const getPriorityClass = (p: PrioridadCodigo): string => ({
    high: 'bg-orange-100 text-orange-700',
    medium: 'bg-yellow-100 text-yellow-700',
    low: 'bg-green-100 text-green-700',
}[p])

const getStatusClass = (s: EstadoCodigo): string => ({
    open: 'bg-blue-100 text-blue-700',
    progress: 'bg-purple-100 text-purple-700',
    scheduled: 'bg-blue-100 text-blue-700',
    done: 'bg-emerald-100 text-emerald-700',
    partial: 'bg-amber-100 text-amber-700',
}[s])

useSeo(() => ({
    title: locale.value === 'en'
        ? 'coreReports — Maintenance, tickets and technical reports'
        : 'coreReports — Gestión de mantenimiento, tickets y reportes técnicos',
    description: t('landing.hero.lead'),
    path: '/',
    jsonLd: [
        {
            '@context': 'https://schema.org',
            '@type': 'Organization',
            name: 'coreReports',
            url: SITE_URL,
            logo: `${SITE_URL}/logo_empresa.png`,
            email: 'contacto@corion.mx',
        },
        {
            '@context': 'https://schema.org',
            '@type': 'SoftwareApplication',
            name: 'coreReports',
            applicationCategory: 'BusinessApplication',
            operatingSystem: 'Web',
            description: t('landing.hero.lead'),
            url: SITE_URL,
            inLanguage: locale.value,
            offers: [
                { '@type': 'Offer', name: t('landing.pricing.plans.trial.name'), price: '0', priceCurrency: 'MXN' },
                { '@type': 'Offer', name: t('landing.pricing.plans.monthly.name'), price: '399', priceCurrency: 'MXN' },
                { '@type': 'Offer', name: t('landing.pricing.plans.annual.name'), price: '3830.40', priceCurrency: 'MXN' },
            ],
        },
    ],
}))

const cambiarA = (idioma: 'es' | 'en') => cambiarIdioma(idioma)

const menuOpen = ref(false)
const headerScrolled = ref(false)
const scrollProgress = ref(0)

const onScroll = () => {
    headerScrolled.value = window.scrollY > 12
    const max = document.documentElement.scrollHeight - window.innerHeight
    scrollProgress.value = max > 0 ? Math.min(100, (window.scrollY / max) * 100) : 0
}

const goTo = (id: string) => {
    menuOpen.value = false
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const navLinks = computed(() => [
    { id: 'producto', label: t('landing.nav.product') },
    { id: 'caracteristicas', label: t('landing.nav.features') },
    { id: 'como-funciona', label: t('landing.nav.how') },
    { id: 'precios', label: t('landing.nav.pricing') },
])

/* ------------------------- Datos de ejemplo ----------------------- */

const demoTickets = computed(() => [
    { id: 1, folio: 'TCK-1042', subject: t('landing.product.demo.t1'), priority: 'high' as PrioridadCodigo, status: 'open' as EstadoCodigo },
    { id: 2, folio: 'TCK-1043', subject: t('landing.product.demo.t2'), priority: 'medium' as PrioridadCodigo, status: 'progress' as EstadoCodigo },
    { id: 3, folio: 'TCK-1044', subject: t('landing.product.demo.t3'), priority: 'low' as PrioridadCodigo, status: 'open' as EstadoCodigo },
])

const demoServicios = computed(() => [
    { id: 1, folio: 'SRV-091', subject: t('landing.product.demo.s1'), date: '19/10 · 09:00', status: 'scheduled' as EstadoCodigo },
    { id: 2, folio: 'SRV-092', subject: t('landing.product.demo.s2'), date: '21/10 · 11:00', status: 'scheduled' as EstadoCodigo },
    { id: 3, folio: 'SRV-093', subject: t('landing.product.demo.s3'), date: '24/10 · 16:00', status: 'scheduled' as EstadoCodigo },
])

const demoActividad = computed(() => [
    { id: 238, folio: 'REP-238', tipo: t('landing.product.demo.a1'), status: 'done' as EstadoCodigo, fecha: `${t('landing.product.demo.today')}, 10:24` },
    { id: 237, folio: 'REP-237', tipo: t('landing.product.demo.a2'), status: 'partial' as EstadoCodigo, fecha: `${t('landing.product.demo.today')}, 08:51` },
    { id: 236, folio: 'REP-236', tipo: t('landing.product.demo.a3'), status: 'done' as EstadoCodigo, fecha: `${t('landing.product.demo.yesterday')}, 17:40` },
])

const feed = computed(() => [
    { icon: Ticket, text: t('landing.hero.panel.feed1'), tag: 'open' as EstadoCodigo },
    { icon: BellRing, text: t('landing.hero.panel.feed2'), tag: 'scheduled' as EstadoCodigo },
    { icon: CheckCircle2, text: t('landing.hero.panel.feed3'), tag: 'done' as EstadoCodigo },
])
const activeFeed = ref(0)
let feedTimer: ReturnType<typeof setInterval> | undefined

/* --------------------------- Gráfica ------------------------------ */

const chartSeries = [
    { name: 'Tickets', data: [28, 34, 30, 42, 48, 44, 56, 62, 58, 71] },
    { name: 'Reportes', data: [16, 22, 26, 24, 33, 38, 41, 47, 52, 60] },
]

const chartOptions: ApexOptions = {
    chart: {
        type: 'area',
        sparkline: { enabled: true },
        animations: { enabled: true, speed: 900, dynamicAnimation: { speed: 600 } },
        toolbar: { show: false },
        zoom: { enabled: false },
    },
    colors: ['#3b82f6', '#22c55e'],
    stroke: { curve: 'smooth', width: 2.5 },
    fill: {
        type: 'gradient',
        gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.02, stops: [0, 95] },
    },
    dataLabels: { enabled: false },
    grid: { show: false },
    xaxis: { labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { labels: { show: false } },
    legend: { show: false },
    tooltip: { theme: 'light', x: { show: false } },
}

/* ------------------------- Contadores ----------------------------- */

type Counter = { value: number; display: number; prefix?: string; suffix?: string; key: string; started: boolean }
const counters = ref<Counter[]>([
    { value: 96, display: 0, suffix: '%', key: 'sla', started: false },
    { value: 42, display: 0, prefix: '-', suffix: '%', key: 'response', started: false },
    { value: 100, display: 0, suffix: '%', key: 'evidence', started: false },
])

const runCounter = (c: Counter) => {
    if (c.started) return
    c.started = true
    const start = performance.now()
    const duration = 1400
    const step = (now: number) => {
        const t2 = Math.min(1, (now - start) / duration)
        const eased = 1 - Math.pow(1 - t2, 3)
        c.display = Math.round(c.value * eased)
        if (t2 < 1) requestAnimationFrame(step)
    }
    requestAnimationFrame(step)
}

/* ------------------------- Animaciones ---------------------------- */

let revealObserver: IntersectionObserver | undefined
let counterObserver: IntersectionObserver | undefined

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true })
    onScroll()

    revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) {
                e.target.classList.add('is-visible')
                revealObserver?.unobserve(e.target)
            }
        })
    }, { threshold: 0.15 })
    document.querySelectorAll('[data-reveal]').forEach((el) => revealObserver?.observe(el))

    counterObserver = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) {
                counters.value.forEach(runCounter)
                counterObserver?.disconnect()
            }
        })
    }, { threshold: 0.4 })
    const statsEl = document.getElementById('stats-band')
    if (statsEl) counterObserver.observe(statsEl)

    feedTimer = setInterval(() => {
        activeFeed.value = (activeFeed.value + 1) % feed.value.length
    }, 3200)
})

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll)
    revealObserver?.disconnect()
    counterObserver?.disconnect()
    if (feedTimer) clearInterval(feedTimer)
})

/* --------------------------- Contenido ---------------------------- */

const features = computed<{ icon: Component; title: string; text: string }[]>(() => [
    { icon: Ticket, title: t('landing.features.items.tickets.title'), text: t('landing.features.items.tickets.text') },
    { icon: CalendarClock, title: t('landing.features.items.services.title'), text: t('landing.features.items.services.text') },
    { icon: FileText, title: t('landing.features.items.reports.title'), text: t('landing.features.items.reports.text') },
    { icon: ChartColumn, title: t('landing.features.items.analytics.title'), text: t('landing.features.items.analytics.text') },
    { icon: Users, title: t('landing.features.items.roles.title'), text: t('landing.features.items.roles.text') },
    { icon: ShieldCheck, title: t('landing.features.items.security.title'), text: t('landing.features.items.security.text') },
])

const bullets = computed(() => [
    t('landing.how.bullets.b1'),
    t('landing.how.bullets.b2'),
    t('landing.how.bullets.b3'),
    t('landing.how.bullets.b4'),
])

const steps = computed<{ icon: Component; number: string; title: string; text: string }[]>(() => [
    { icon: Building2, number: '1', title: t('landing.how.steps.one.title'), text: t('landing.how.steps.one.text') },
    { icon: Wrench, number: '2', title: t('landing.how.steps.two.title'), text: t('landing.how.steps.two.text') },
    { icon: TrendingUp, number: '3', title: t('landing.how.steps.three.title'), text: t('landing.how.steps.three.text') },
])

const plans = computed(() => [
    {
        key: 'trial',
        name: t('landing.pricing.plans.trial.name'),
        price: t('landing.pricing.plans.trial.price'),
        period: t('landing.pricing.plans.trial.period'),
        description: t('landing.pricing.plans.trial.description'),
        features: [
            t('landing.pricing.plans.trial.f1'),
            t('landing.pricing.plans.trial.f2'),
            t('landing.pricing.plans.trial.f3'),
            t('landing.pricing.plans.trial.f4'),
        ],
        cta: t('landing.pricing.plans.trial.cta'),
        featured: false,
    },
    {
        key: 'monthly',
        name: t('landing.pricing.plans.monthly.name'),
        price: t('landing.pricing.plans.monthly.price'),
        period: t('landing.pricing.plans.monthly.period'),
        description: t('landing.pricing.plans.monthly.description'),
        features: [
            t('landing.pricing.plans.monthly.f1'),
            t('landing.pricing.plans.monthly.f2'),
            t('landing.pricing.plans.monthly.f3'),
            t('landing.pricing.plans.monthly.f4'),
        ],
        cta: t('landing.pricing.plans.monthly.cta'),
        featured: true,
    },
    {
        key: 'annual',
        name: t('landing.pricing.plans.annual.name'),
        price: t('landing.pricing.plans.annual.price'),
        period: t('landing.pricing.plans.annual.period'),
        description: t('landing.pricing.plans.annual.description'),
        features: [
            t('landing.pricing.plans.annual.f1'),
            t('landing.pricing.plans.annual.f2'),
            t('landing.pricing.plans.annual.f3'),
            t('landing.pricing.plans.annual.f4'),
        ],
        cta: t('landing.pricing.plans.annual.cta'),
        featured: false,
    },
])
</script>

<!------------------------------------------------------------------------------------------------------------------>

<template>
    <div class="landing">

        <!-- ============ HEADER ============ -->
        <header class="site-header" :class="{ 'is-scrolled': headerScrolled }">
            <span class="scroll-progress" :style="{ width: scrollProgress + '%' }"></span>

            <div class="container header-inner">
                <a class="brand" href="/" @click.prevent="goTo('top')">
                    <span class="brand-mark">coreReport</span>
                </a>

                <nav class="nav-desktop">
                    <a v-for="link in navLinks" :key="link.id" :href="`#${link.id}`" @click.prevent="goTo(link.id)">
                        {{ link.label }}
                    </a>
                </nav>

                <div class="header-actions">
                    <div class="lang-switch" role="group" :aria-label="t('common.language')">
                        <Globe :size="15" class="globe" />
                        <button type="button" :class="{ active: locale === 'es' }" @click="cambiarA('es')">ES</button>
                        <button type="button" :class="{ active: locale === 'en' }" @click="cambiarA('en')">EN</button>
                    </div>

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

                    <router-link class="btn btn-ghost hide-sm" :to="{ name: 'login' }">{{ t('landing.nav.login') }}</router-link>
                    <router-link class="btn btn-primary hide-sm" :to="{ name: 'register' }">
                        {{ t('landing.nav.start') }} <ArrowRight :size="16" />
                    </router-link>
                </div>

                <button class="menu-toggle" type="button" :aria-label="t('landing.nav.menu')" @click="menuOpen = !menuOpen">
                    <X v-if="menuOpen" :size="24" />
                    <Menu v-else :size="24" />
                </button>
            </div>

            <div v-if="menuOpen" class="nav-mobile">
                <a v-for="link in navLinks" :key="link.id" :href="`#${link.id}`" @click.prevent="goTo(link.id)">
                    {{ link.label }}
                </a>
                <router-link class="btn btn-ghost" :to="{ name: 'login' }" @click="menuOpen = false">{{ t('landing.nav.login') }}</router-link>
                <router-link class="btn btn-primary" :to="{ name: 'register' }" @click="menuOpen = false">{{ t('landing.nav.start') }}</router-link>
            </div>
        </header>

        <main id="top">

            <!-- ============ HERO ============ -->
            <section class="hero">
                <span class="orb orb-a" aria-hidden="true"></span>
                <span class="orb orb-b" aria-hidden="true"></span>

                <div class="container hero-inner">
                    <div class="hero-copy">
                        <span class="badge-live">
                            <span class="pulse-dot"></span>
                            {{ t('landing.hero.badge') }}
                        </span>

                        <h1>
                            {{ t('landing.hero.title') }}
                            <span class="grad">{{ t('landing.hero.titleAccent') }}</span>
                        </h1>

                        <p class="lead">{{ t('landing.hero.lead') }}</p>

                        <div class="hero-cta">
                            <router-link class="btn btn-primary btn-lg" :to="{ name: 'register' }">
                                {{ t('landing.hero.ctaPrimary') }} <ArrowRight :size="18" />
                            </router-link>
                            <button class="btn btn-outline btn-lg" type="button" @click="goTo('producto')">
                                {{ t('landing.hero.ctaSecondary') }} <ArrowUpRight :size="18" />
                            </button>
                        </div>

                        <p class="hero-note">
                            <Check :size="15" /> {{ t('landing.hero.note1') }}
                            <Check :size="15" /> {{ t('landing.hero.note2') }}
                            <Check :size="15" /> {{ t('landing.hero.note3') }}
                        </p>

                        <div id="stats-band" class="hero-stats">
                            <div v-for="c in counters" :key="c.key" class="hero-stat">
                                <strong>{{ c.prefix }}{{ c.display }}{{ c.suffix }}</strong>
                                <span>{{ t(`landing.hero.stats.${c.key}`) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- ===== Panel tipo dashboard ===== -->
                    <div class="live-panel" data-reveal>
                        <div class="panel-bar">
                            <span class="dot dot-red"></span>
                            <span class="dot dot-amber"></span>
                            <span class="dot dot-green"></span>
                            <span class="panel-title">{{ t('landing.hero.panel.title') }}</span>
                            <span class="live-pill"><span class="pulse-dot"></span> {{ t('landing.hero.panel.live') }}</span>
                        </div>

                        <div class="panel-kpis">
                            <StatCard
                                :title="t('landing.hero.panel.slaCard')"
                                value="96%"
                                :icon="Gauge"
                                :trend="t('landing.hero.panel.slaTrend')"
                                trend-color="green"
                                color="#eef2ff"
                            />
                            <StatCard
                                :title="t('landing.hero.panel.mttrCard')"
                                value="3.2 h"
                                :icon="Clock"
                                :trend="t('landing.hero.panel.mttrTrend')"
                                trend-color="green"
                                color="#ecfdf5"
                            />
                        </div>

                        <div class="panel-chart">
                            <div class="panel-chart-head">
                                <span>{{ t('landing.hero.panel.chartTitle') }}</span>
                                <span class="chart-legend">
                                    <i class="l-blue"></i>{{ t('landing.hero.panel.tickets') }}
                                    <i class="l-green"></i>{{ t('landing.hero.panel.reports') }}
                                </span>
                            </div>
                            <apexchart type="area" height="132" :options="chartOptions" :series="chartSeries" />
                        </div>

                        <div class="panel-feed">
                            <div
                                v-for="(f, i) in feed"
                                :key="f.text"
                                class="feed-row"
                                :class="{ active: i === activeFeed }"
                            >
                                <span class="feed-icon"><component :is="f.icon" :size="16" /></span>
                                <span class="feed-text">{{ f.text }}</span>
                                <span class="tag" :class="getStatusClass(f.tag)">{{ statusLabel(f.tag) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ PRODUCTO ============ -->
            <section id="producto" class="section section-soft">
                <div class="container">
                    <header class="section-head" data-reveal>
                        <span class="eyebrow">{{ t('landing.product.eyebrow') }}</span>
                        <h2>{{ t('landing.product.title') }}</h2>
                        <p>{{ t('landing.product.lead') }}</p>
                    </header>

                    <div class="demo-grid">
                        <div class="dash-card" data-reveal>
                            <div class="card-header border-b-red">
                                <AlertOctagon class="text-red-500" :size="20" />
                                <h3>{{ t('landing.product.ticketsTitle') }}</h3>
                                <span class="badge">{{ demoTickets.length }}</span>
                            </div>
                            <div class="card-body">
                                <div class="list-container">
                                    <div v-for="item in demoTickets" :key="item.id" class="list-item">
                                        <div class="item-main">
                                            <span class="folio">{{ item.folio }}</span>
                                            <span class="subject">{{ item.subject }}</span>
                                        </div>
                                        <div class="item-meta">
                                            <span class="tag" :class="getPriorityClass(item.priority)">{{ priorityLabel(item.priority) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <span class="card-link">{{ t('landing.product.ticketsFooter') }} <ArrowRight :size="14" /></span>
                            </div>
                        </div>

                        <div class="dash-card" data-reveal>
                            <div class="card-header border-b-blue">
                                <CalendarClock class="text-blue-500" :size="20" />
                                <h3>{{ t('landing.product.servicesTitle') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="list-container">
                                    <div v-for="item in demoServicios" :key="item.id" class="list-item">
                                        <div class="item-main">
                                            <span class="folio">{{ item.folio }}</span>
                                            <span class="subject clamp-1">{{ item.subject }}</span>
                                        </div>
                                        <div class="item-meta meta-col">
                                            <span class="date">{{ item.date }}</span>
                                            <span class="tag" :class="getStatusClass(item.status)">{{ statusLabel(item.status) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <span class="card-link">{{ t('landing.product.servicesFooter') }} <BellRing :size="14" /></span>
                            </div>
                        </div>

                        <div class="dash-card" data-reveal>
                            <div class="card-header border-b-emerald">
                                <Activity class="text-emerald-500" :size="20" />
                                <h3>{{ t('landing.product.activityTitle') }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="timeline-container">
                                    <div v-for="item in demoActividad" :key="item.id" class="timeline-item">
                                        <div class="timeline-dot"></div>
                                        <div class="timeline-content">
                                            <div class="timeline-top">
                                                <span class="folio">{{ item.folio }}</span>
                                                <span class="date">{{ item.fecha }}</span>
                                            </div>
                                            <span class="timeline-meta">{{ t('landing.product.demo.typeLabel') }}: {{ item.tipo }}</span>
                                            <span class="timeline-meta">{{ t('landing.product.demo.statusLabel') }}: {{ statusLabel(item.status) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <span class="card-link">{{ t('landing.product.activityFooter') }} <ShieldCheck :size="14" /></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ CARACTERÍSTICAS ============ -->
            <section id="caracteristicas" class="section">
                <div class="container">
                    <header class="section-head" data-reveal>
                        <span class="eyebrow">{{ t('landing.features.eyebrow') }}</span>
                        <h2>{{ t('landing.features.title') }}</h2>
                        <p>{{ t('landing.features.lead') }}</p>
                    </header>

                    <div class="feature-grid">
                        <article v-for="f in features" :key="f.title" class="feature-card" data-reveal>
                            <span class="feature-icon"><component :is="f.icon" :size="22" /></span>
                            <h3>{{ f.title }}</h3>
                            <p>{{ f.text }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <!-- ============ CÓMO FUNCIONA ============ -->
            <section id="como-funciona" class="section section-dark">
                <div class="container">
                    <header class="section-head section-head-light" data-reveal>
                        <span class="eyebrow eyebrow-light">{{ t('landing.how.eyebrow') }}</span>
                        <h2>{{ t('landing.how.title') }}</h2>
                        <p>{{ t('landing.how.lead') }}</p>
                    </header>

                    <div class="steps">
                        <article v-for="s in steps" :key="s.number" class="step-card" data-reveal>
                            <span class="step-number">{{ s.number }}</span>
                            <span class="step-icon"><component :is="s.icon" :size="20" /></span>
                            <h3>{{ s.title }}</h3>
                            <p>{{ s.text }}</p>
                        </article>
                    </div>

                    <ul class="checklist" data-reveal>
                        <li v-for="item in bullets" :key="item">
                            <Check :size="18" /> <span>{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- ============ PRECIOS ============ -->
            <section id="precios" class="section section-soft">
                <div class="container">
                    <header class="section-head" data-reveal>
                        <span class="eyebrow">{{ t('landing.pricing.eyebrow') }}</span>
                        <h2>{{ t('landing.pricing.title') }}</h2>
                        <p>{{ t('landing.pricing.lead') }}</p>
                    </header>

                    <div class="pricing-grid">
                        <article
                            v-for="plan in plans"
                            :key="plan.key"
                            class="price-card"
                            :class="{ featured: plan.featured }"
                            data-reveal
                        >
                            <span v-if="plan.featured" class="ribbon">{{ t('landing.pricing.popular') }}</span>
                            <h3>{{ plan.name }}</h3>
                            <p class="price">{{ plan.price }}</p>
                            <p class="period">{{ plan.period }}</p>
                            <p class="plan-desc">{{ plan.description }}</p>
                            <ul>
                                <li v-for="item in plan.features" :key="item">
                                    <Check :size="16" /> <span>{{ item }}</span>
                                </li>
                            </ul>
                            <router-link
                                class="btn btn-block"
                                :class="plan.featured ? 'btn-primary' : 'btn-outline'"
                                :to="{ name: 'register' }"
                            >
                                {{ plan.cta }}
                            </router-link>
                        </article>
                    </div>

                    <p class="pricing-note">{{ t('landing.pricing.note') }}</p>
                </div>
            </section>

            <!-- ============ CTA FINAL ============ -->
            <section class="cta-final">
                <div class="container cta-inner">
                    <div data-reveal>
                        <span class="cta-icon"><Zap :size="22" /></span>
                        <h2>{{ t('landing.cta.title') }}</h2>
                        <p>{{ t('landing.cta.text') }}</p>
                    </div>
                    <router-link class="btn btn-light btn-lg" :to="{ name: 'register' }" data-reveal>
                        {{ t('landing.cta.button') }} <ArrowRight :size="18" />
                    </router-link>
                </div>
            </section>
        </main>

        <!-- ============ FOOTER ============ -->
        <AppFooter />
    </div>
</template>

<!------------------------------------------------------------------------------------------------------------------>

<style scoped>
.landing {
    --primary: #1e3a8a;
    --primary-dark: #172554;
    --blue: #3b82f6;
    --ink: #0f172a;
    --muted: #64748b;
    --line: #e2e8f0;
    --soft: #f8fafc;
    font-family: 'Inter', sans-serif;
    color: var(--ink);
    background: #fff;
    min-height: 100vh;
    overflow-x: hidden;
    transition: background-color .25s, color .25s;
}

.container {
    width: 100%;
    max-width: 1140px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

h1, h2, h3 { color: var(--ink); margin: 0; line-height: 1.15; }
p { margin: 0; }

/* ---------- Animaciones de entrada ---------- */
[data-reveal] {
    opacity: 0;
    transform: translateY(20px);
    transition: opacity .6s ease, transform .6s ease;
}
[data-reveal].is-visible { opacity: 1; transform: none; }

/* ---------- Botones ---------- */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    padding: .65rem 1.15rem;
    border-radius: 8px;
    font-size: .92rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid transparent;
    cursor: pointer;
    transition: background-color .2s, color .2s, border-color .2s, transform .15s, box-shadow .2s;
}
.btn:hover { transform: translateY(-1px); }
.btn-primary { background: var(--primary); color: #fff; box-shadow: 0 8px 20px -10px rgba(30, 58, 138, .8); }
.btn-primary:hover { background: var(--primary-dark); }
.btn-outline { background: #fff; color: var(--primary); border-color: #c7d2fe; }
.btn-outline:hover { border-color: var(--primary); box-shadow: 0 8px 20px -14px rgba(30, 58, 138, .6); }
.btn-ghost { background: transparent; color: var(--ink); }
.btn-ghost:hover { background: #f1f5f9; }
.btn-light { background: #fff; color: var(--primary); }
.btn-lg { padding: .85rem 1.5rem; font-size: 1rem; }
.btn-block { width: 100%; }

/* ---------- Header ---------- */
.site-header {
    position: sticky;
    top: 0;
    z-index: 50;
    background: rgba(255, 255, 255, .92);
    backdrop-filter: blur(8px);
    border-bottom: 1px solid transparent;
    transition: border-color .2s, box-shadow .2s, background-color .25s;
}
.site-header.is-scrolled {
    border-bottom-color: var(--line);
    box-shadow: 0 6px 18px -12px rgba(15, 23, 42, .35);
}
.scroll-progress {
    position: absolute;
    top: 0;
    left: 0;
    height: 2px;
    background: linear-gradient(90deg, #1e3a8a, #3b82f6, #22c55e);
    transition: width .1s linear;
}
.header-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    height: 68px;
}
.brand { text-decoration: none; }
.brand-mark {
    font-weight: 900;
    font-size: 1.25rem;
    letter-spacing: -0.02em;
    color: var(--primary);
}
.nav-desktop { display: flex; gap: 1.4rem; }
.nav-desktop a {
    color: #334155;
    text-decoration: none;
    font-size: .9rem;
    font-weight: 500;
    transition: color .2s;
}
.nav-desktop a:hover { color: var(--primary); }
.header-actions { display: flex; align-items: center; gap: .5rem; }

/* Selector de idioma y tema */
.lang-switch {
    display: inline-flex;
    align-items: center;
    gap: .1rem;
    border: 1px solid var(--line);
    border-radius: 999px;
    padding: .15rem .3rem;
    background: #fff;
}
.lang-switch .globe { color: #94a3b8; margin: 0 .2rem; }
.lang-switch button {
    border: none;
    background: transparent;
    color: #64748b;
    font-size: .74rem;
    font-weight: 700;
    padding: .25rem .45rem;
    border-radius: 999px;
    cursor: pointer;
    transition: background-color .2s, color .2s;
}
.lang-switch button:hover { color: var(--primary); }
.lang-switch button.active { background: #eef2ff; color: var(--primary); }
.icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 1px solid var(--line);
    background: #fff;
    color: #475569;
    cursor: pointer;
    transition: background-color .2s, color .2s, border-color .2s, transform .15s;
}
.icon-btn:hover { color: var(--primary); border-color: #c7d2fe; transform: translateY(-1px); }

.menu-toggle {
    display: none;
    background: transparent;
    border: none;
    color: var(--ink);
    cursor: pointer;
    padding: .25rem;
}
.nav-mobile {
    display: flex;
    flex-direction: column;
    gap: .5rem;
    padding: 1rem 1.5rem 1.5rem;
    border-top: 1px solid var(--line);
    background: #fff;
}
.nav-mobile a { color: #334155; text-decoration: none; font-weight: 500; padding: .4rem 0; }

/* ---------- Hero ---------- */
.hero {
    position: relative;
    padding: 4.5rem 0 3rem;
    background:
        radial-gradient(900px 420px at 12% -10%, #e0e7ff 0%, rgba(224, 231, 255, 0) 70%),
        radial-gradient(700px 380px at 92% 0%, #eef2ff 0%, rgba(238, 242, 255, 0) 65%);
    overflow: hidden;
}
.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    opacity: .45;
    pointer-events: none;
    animation: float 12s ease-in-out infinite;
}
.orb-a { width: 320px; height: 320px; background: #bfdbfe; top: -80px; right: 8%; }
.orb-b { width: 260px; height: 260px; background: #bbf7d0; bottom: -120px; left: -60px; animation-delay: -6s; }
@keyframes float {
    0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
    50% { transform: translate3d(0, -22px, 0) scale(1.06); }
}

.hero-inner {
    position: relative;
    display: grid;
    grid-template-columns: 1.02fr .98fr;
    gap: 3rem;
    align-items: center;
    z-index: 1;
}
.badge-live {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: #eef2ff;
    color: var(--primary);
    border: 1px solid #c7d2fe;
    border-radius: 999px;
    padding: .35rem .85rem;
    font-size: .78rem;
    font-weight: 600;
}
.pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    box-shadow: 0 0 0 0 rgba(34, 197, 94, .55);
    animation: pulse 1.8s infinite;
    display: inline-block;
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, .55); }
    70% { box-shadow: 0 0 0 9px rgba(34, 197, 94, 0); }
    100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}

.hero h1 {
    margin-top: 1.25rem;
    font-size: clamp(2rem, 4vw, 3rem);
    letter-spacing: -0.03em;
}
.grad {
    display: block;
    background: linear-gradient(100deg, #1e3a8a 0%, #2563eb 45%, #0ea5e9 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.lead {
    margin-top: 1.1rem;
    color: #475569;
    font-size: 1.05rem;
    line-height: 1.65;
    max-width: 34rem;
}
.hero-cta { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.9rem; }
.hero-note {
    margin-top: 1rem;
    color: var(--muted);
    font-size: .85rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .35rem .9rem;
}
.hero-note :deep(svg) { color: #16a34a; vertical-align: -2px; }

.hero-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-top: 2.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--line);
}
.hero-stat strong {
    display: block;
    font-size: 1.7rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--primary);
    font-variant-numeric: tabular-nums;
}
.hero-stat span { color: var(--muted); font-size: .82rem; line-height: 1.4; }

/* ---------- Panel tipo dashboard ---------- */
.live-panel {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 18px;
    box-shadow: 0 40px 80px -40px rgba(15, 23, 42, .45);
    overflow: hidden;
    transition: transform .35s ease, box-shadow .35s ease, background-color .25s;
}
.live-panel:hover {
    transform: translateY(-6px) rotate(-.3deg);
    box-shadow: 0 50px 90px -40px rgba(30, 58, 138, .5);
}
.panel-bar {
    display: flex;
    align-items: center;
    gap: .4rem;
    padding: .7rem .95rem;
    border-bottom: 1px solid var(--line);
    background: #f8fafc;
}
.dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.dot-red { background: #fca5a5; }
.dot-amber { background: #fcd34d; }
.dot-green { background: #86efac; }
.panel-title { margin-left: .6rem; font-size: .78rem; font-weight: 600; color: var(--muted); }
.live-pill {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-size: .68rem;
    font-weight: 700;
    color: #15803d;
    background: #dcfce7;
    border-radius: 999px;
    padding: .18rem .55rem;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.panel-kpis {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .75rem;
    padding: 1rem 1rem .25rem;
}
.panel-kpis :deep(.stat-card) { padding: 1rem; border-radius: 10px; }
.panel-kpis :deep(.card-value) { font-size: 1.45rem; }

.panel-chart { padding: .5rem 1rem .25rem; }
.panel-chart-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .72rem;
    font-weight: 600;
    color: var(--muted);
    margin-bottom: .25rem;
}
.chart-legend { display: inline-flex; align-items: center; gap: .3rem; font-weight: 500; }
.chart-legend i { width: 7px; height: 7px; border-radius: 50%; display: inline-block; margin-left: .35rem; }
.l-blue { background: #3b82f6; }
.l-green { background: #22c55e; }

.panel-feed { padding: .35rem .75rem .9rem; display: flex; flex-direction: column; gap: .4rem; }
.feed-row {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .55rem .6rem;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid transparent;
    font-size: .8rem;
    color: #334155;
    transition: background .3s, border-color .3s, transform .3s;
}
.feed-row.active {
    background: #eef2ff;
    border-color: #c7d2fe;
    transform: translateX(2px);
}
.feed-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 7px;
    background: #fff;
    color: var(--primary);
    border: 1px solid var(--line);
    flex-shrink: 0;
}
.feed-text { flex: 1; line-height: 1.25; }
.tag {
    font-size: .64rem;
    font-weight: 700;
    padding: .18rem .5rem;
    border-radius: 4px;
    letter-spacing: .04em;
    text-transform: uppercase;
    white-space: nowrap;
}

/* ---------- Secciones ---------- */
.section { padding: 5rem 0; }
.section-soft { background: var(--soft); }
.section-dark { background: #0f172a; }
.section-dark h2, .section-dark p { color: #e2e8f0; }
.section-head { max-width: 42rem; margin: 0 auto 3rem; text-align: center; }
.section-head-light h2 { color: #fff; }
.section-head-light p { color: #cbd5e1; }
.eyebrow {
    display: inline-block;
    color: var(--primary);
    font-weight: 700;
    font-size: .78rem;
    letter-spacing: .09em;
    text-transform: uppercase;
    margin-bottom: .7rem;
}
.eyebrow-light { color: #93c5fd; }
.section-head h2, .cta-final h2 { font-size: clamp(1.6rem, 3vw, 2.25rem); letter-spacing: -0.02em; }
.section-head p, .cta-final p { margin-top: .9rem; color: var(--muted); font-size: 1rem; line-height: 1.65; }

/* ---------- Tarjetas del dashboard ---------- */
.demo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
.dash-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, .05);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: transform .3s ease, box-shadow .3s ease, background-color .25s;
}
.dash-card:hover { transform: translateY(-4px); box-shadow: 0 24px 48px -28px rgba(15, 23, 42, .45); }
.card-header {
    padding: 1.1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: .65rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.card-header h3 { margin: 0; font-size: 1rem; font-weight: 600; color: #1e293b; flex-grow: 1; }
.border-b-red { border-bottom: 2px solid #ef4444; }
.border-b-blue { border-bottom: 2px solid #3b82f6; }
.border-b-emerald { border-bottom: 2px solid #10b981; }
.badge {
    background: #fee2e2;
    color: #b91c1c;
    padding: .15rem .6rem;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 700;
}
.card-body { flex-grow: 1; }
.card-footer { padding: .8rem 1.25rem; border-top: 1px solid #e2e8f0; background: #fff; }
.card-link { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; font-weight: 600; color: #2563eb; }
.list-container { display: flex; flex-direction: column; }
.list-item {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    transition: background .2s;
}
.list-item:hover { background: #f8fafc; }
.list-item:last-child { border-bottom: none; }
.item-main { display: flex; flex-direction: column; gap: .25rem; min-width: 0; }
.folio { font-weight: 700; font-size: .82rem; color: #475569; }
.subject { font-size: .9rem; font-weight: 500; color: #0f172a; line-height: 1.25; }
.clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
.item-meta { display: flex; align-items: center; }
.meta-col { flex-direction: column; align-items: flex-end; gap: .3rem; }
.date { font-size: .72rem; font-weight: 600; color: #64748b; white-space: nowrap; }
.timeline-container { padding: 1.1rem 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.timeline-item { display: flex; gap: .8rem; position: relative; }
.timeline-item::before {
    content: '';
    position: absolute;
    left: 5px;
    top: 18px;
    bottom: -18px;
    width: 2px;
    background: #e2e8f0;
}
.timeline-item:last-child::before { display: none; }
.timeline-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #10b981;
    margin-top: 5px;
    z-index: 1;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #10b981;
    flex-shrink: 0;
}
.timeline-content {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: .7rem .8rem;
    border-radius: 8px;
    flex-grow: 1;
}
.timeline-top { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
.timeline-meta { display: block; font-size: .74rem; color: #64748b; margin-top: .15rem; }

/* ---------- Características ---------- */
.feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
.feature-card {
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 1.5rem;
    background: #fff;
    transition: border-color .25s, transform .25s, box-shadow .25s, background-color .25s;
}
.feature-card:hover { border-color: #c7d2fe; transform: translateY(-3px); box-shadow: 0 20px 40px -28px rgba(30, 58, 138, .5); }
.feature-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #eef2ff;
    color: var(--primary);
    margin-bottom: 1rem;
}
.feature-card h3 { font-size: 1.05rem; margin-bottom: .5rem; }
.feature-card p { color: var(--muted); font-size: .92rem; line-height: 1.6; }

/* ---------- Cómo funciona ---------- */
.steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
.step-card {
    position: relative;
    border: 1px solid rgba(148, 163, 184, .25);
    border-radius: 12px;
    padding: 2rem 1.5rem 1.5rem;
    background: rgba(255, 255, 255, .04);
    transition: transform .25s, background .25s;
}
.step-card:hover { transform: translateY(-3px); background: rgba(255, 255, 255, .07); }
.step-number {
    position: absolute;
    top: -16px;
    left: 1.5rem;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--blue);
    color: #fff;
    font-weight: 700;
    font-size: .9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 16px -8px rgba(59, 130, 246, .9);
}
.step-icon { color: #93c5fd; display: inline-flex; margin-bottom: .75rem; }
.step-card h3 { font-size: 1.05rem; margin-bottom: .5rem; color: #fff; }
.step-card p { color: #94a3b8; font-size: .92rem; line-height: 1.6; }

.checklist { list-style: none; margin: 2.5rem 0 0; padding: 0; display: grid; grid-template-columns: repeat(2, 1fr); gap: .85rem; }
.checklist li {
    display: flex;
    align-items: flex-start;
    gap: .7rem;
    color: #e2e8f0;
    font-size: .92rem;
    line-height: 1.5;
    background: rgba(255, 255, 255, .04);
    border: 1px solid rgba(148, 163, 184, .25);
    border-radius: 10px;
    padding: .8rem 1rem;
}
.checklist li :deep(svg) { color: #4ade80; flex-shrink: 0; margin-top: 2px; }

/* ---------- Precios ---------- */
.pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; align-items: stretch; }
.price-card {
    position: relative;
    display: flex;
    flex-direction: column;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: #fff;
    padding: 1.75rem;
    transition: transform .25s, box-shadow .25s, background-color .25s;
}
.price-card:hover { transform: translateY(-4px); box-shadow: 0 30px 60px -40px rgba(15, 23, 42, .5); }
.price-card.featured { border-color: var(--primary); box-shadow: 0 30px 60px -34px rgba(30, 58, 138, .65); }
.ribbon {
    position: absolute;
    top: -12px;
    left: 1.75rem;
    background: var(--primary);
    color: #fff;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding: .25rem .6rem;
    border-radius: 999px;
}
.price-card h3 { font-size: 1.05rem; }
.price { font-size: 2rem; font-weight: 800; color: var(--primary); margin-top: .6rem; letter-spacing: -0.03em; }
.period { color: var(--muted); font-size: .82rem; margin-top: .2rem; }
.plan-desc { color: #475569; font-size: .9rem; margin-top: .9rem; padding-bottom: 1rem; border-bottom: 1px solid var(--line); }
.price-card ul { list-style: none; margin: 1rem 0 1.5rem; padding: 0; display: grid; gap: .6rem; flex: 1; }
.price-card li { display: flex; align-items: flex-start; gap: .55rem; font-size: .9rem; color: #334155; }
.price-card li :deep(svg) { color: #16a34a; flex-shrink: 0; margin-top: 2px; }
.pricing-note { text-align: center; color: var(--muted); font-size: .85rem; margin-top: 2rem; }

/* ---------- CTA final ---------- */
.cta-final {
    background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
    padding: 4rem 0;
    position: relative;
    overflow: hidden;
}
.cta-final::after {
    content: '';
    position: absolute;
    width: 420px;
    height: 420px;
    right: -120px;
    top: -160px;
    background: radial-gradient(circle, rgba(59, 130, 246, .35), transparent 70%);
    pointer-events: none;
}
.cta-inner {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
    flex-wrap: wrap;
    z-index: 1;
}
.cta-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255, 255, 255, .12);
    color: #bfdbfe;
    margin-bottom: .9rem;
}
.cta-final h2, .cta-final p { color: #fff; }
.cta-final p { color: #cbd5e1; }

/* ---------- Responsive ---------- */
@media (max-width: 980px) {
    .hero-inner { grid-template-columns: 1fr; }
    .live-panel { order: 2; }
    .demo-grid, .pricing-grid { grid-template-columns: repeat(2, 1fr); }
    .feature-grid, .steps { grid-template-columns: repeat(2, 1fr); }
    .nav-desktop { display: none; }
}
@media (max-width: 768px) {
    .hide-sm { display: none; }
    .menu-toggle { display: inline-flex; }
    .hero { padding: 3rem 0 2rem; }
    .hero-stats { grid-template-columns: 1fr; gap: .9rem; }
    .section { padding: 3.5rem 0; }
    .cta-inner { flex-direction: column; align-items: flex-start; }
    .checklist { grid-template-columns: 1fr; }
}
@media (max-width: 620px) {
    .demo-grid, .pricing-grid, .feature-grid, .steps { grid-template-columns: 1fr; }
    .panel-kpis { grid-template-columns: 1fr; }
    .lang-switch .globe { display: none; }
}

/* ---------- Modo oscuro ---------- */
html.theme-dark .landing { background: #0b1220; color: #e2e8f0; }
html.theme-dark .site-header { background: rgba(11, 18, 32, .94); border-bottom-color: #1e293b; }
html.theme-dark .brand-mark { color: #93c5fd; }
html.theme-dark .nav-desktop a { color: #cbd5e1; }
html.theme-dark .nav-desktop a:hover { color: #fff; }
html.theme-dark .nav-mobile { background: #0b1220; border-color: #1e293b; }
html.theme-dark .nav-mobile a { color: #cbd5e1; }
html.theme-dark .lang-switch { background: #111a2e; border-color: #1e293b; }
html.theme-dark .lang-switch button { color: #94a3b8; }
html.theme-dark .lang-switch button.active { background: #172554; color: #bfdbfe; }
html.theme-dark .icon-btn { background: #111a2e; border-color: #1e293b; color: #cbd5e1; }
html.theme-dark .icon-btn:hover { color: #fff; border-color: #1d4ed8; }
html.theme-dark .menu-toggle { color: #e2e8f0; }
html.theme-dark .hero {
    background:
        radial-gradient(900px 420px at 12% -10%, #172554 0%, rgba(23, 37, 84, 0) 70%),
        radial-gradient(700px 380px at 92% 0%, #0f172a 0%, rgba(15, 23, 42, 0) 65%);
}
html.theme-dark .orb-a { background: #1d4ed8; opacity: .3; }
html.theme-dark .orb-b { background: #065f46; opacity: .3; }
html.theme-dark .badge-live { background: #172554; border-color: #1d4ed8; color: #bfdbfe; }
html.theme-dark h1, html.theme-dark h2, html.theme-dark h3 { color: #f1f5f9; }
html.theme-dark .lead, html.theme-dark .hero-note { color: #94a3b8; }
html.theme-dark .hero-stats { border-color: #1e293b; }
html.theme-dark .hero-stat strong { color: #93c5fd; }
html.theme-dark .hero-stat span { color: #94a3b8; }
html.theme-dark .btn-outline { background: #111a2e; color: #bfdbfe; border-color: #1e293b; }
html.theme-dark .btn-outline:hover { border-color: #1d4ed8; }
html.theme-dark .btn-ghost { color: #e2e8f0; }
html.theme-dark .btn-ghost:hover { background: #1e293b; }
html.theme-dark .live-panel { background: #111a2e; border-color: #1e293b; }
html.theme-dark .panel-bar { background: #0f172a; border-color: #1e293b; }
html.theme-dark .panel-title { color: #94a3b8; }
html.theme-dark .feed-row { background: #0f172a; color: #cbd5e1; }
html.theme-dark .feed-row.active { background: #172554; border-color: #1d4ed8; }
html.theme-dark .feed-icon { background: #111a2e; border-color: #1e293b; color: #93c5fd; }
html.theme-dark .panel-kpis :deep(.stat-card) { background: #0f172a; border-color: #1e293b; }
html.theme-dark .panel-kpis :deep(.card-title) { color: #94a3b8; }
html.theme-dark .panel-kpis :deep(.card-value) { color: #f1f5f9; }
html.theme-dark .section-soft { background: #0f172a; }
html.theme-dark .section-head p { color: #94a3b8; }
html.theme-dark .dash-card { background: #111a2e; border-color: #1e293b; }
html.theme-dark .card-header { background: #0f172a; border-color: #1e293b; }
html.theme-dark .card-header h3 { color: #e2e8f0; }
html.theme-dark .list-item { border-color: #1e293b; }
html.theme-dark .list-item:hover { background: #16233c; }
html.theme-dark .subject { color: #e2e8f0; }
html.theme-dark .folio { color: #94a3b8; }
html.theme-dark .date { color: #94a3b8; }
html.theme-dark .card-footer { background: #111a2e; border-color: #1e293b; }
html.theme-dark .card-link { color: #93c5fd; }
html.theme-dark .timeline-content { background: #0f172a; border-color: #1e293b; }
html.theme-dark .timeline-meta { color: #94a3b8; }
html.theme-dark .timeline-item::before { background: #1e293b; }
html.theme-dark .timeline-dot { border-color: #111a2e; }
html.theme-dark .feature-card, html.theme-dark .price-card { background: #111a2e; border-color: #1e293b; }
html.theme-dark .feature-card p { color: #94a3b8; }
html.theme-dark .feature-icon { background: #172554; color: #93c5fd; }
html.theme-dark .price-card.featured { border-color: #3b82f6; }
html.theme-dark .plan-desc { color: #94a3b8; border-color: #1e293b; }
html.theme-dark .price-card li { color: #cbd5e1; }
html.theme-dark .pricing-note { color: #94a3b8; }
</style>
