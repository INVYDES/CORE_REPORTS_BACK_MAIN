import { describe, it, expect, beforeAll, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import LandingView from './LandingView.vue'
import i18n, { cambiarIdioma } from '../i18n'
import { useTheme } from '../utils/theme'

// jsdom no implementa IntersectionObserver ni matchMedia.
beforeAll(() => {
    class IO {
        observe() {}
        unobserve() {}
        disconnect() {}
        takeRecords() { return [] }
        root = null
        rootMargin = ''
        thresholds = []
    }
    ;(globalThis as any).IntersectionObserver = IO
    ;(window as any).IntersectionObserver = IO
    if (!window.matchMedia) {
        ;(window as any).matchMedia = (query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addEventListener() {},
            removeEventListener() {},
            addListener() {},
            removeListener() {},
            dispatchEvent() { return false },
        })
    }
})

const globalConfig = {
    plugins: [i18n],
    stubs: {
        RouterLink: { template: '<a><slot /></a>' },
        'router-link': { template: '<a><slot /></a>' },
        apexchart: { template: '<div class="chart-stub" />' },
    },
}

// jsdom reporta navigator.language = en-US, así que fijamos el idioma de partida.
beforeEach(() => {
    cambiarIdioma('es')
})

afterEach(() => {
    cambiarIdioma('es')
})

describe('LandingView', () => {
    it('renderiza el hero en español por defecto', () => {
        const wrapper = mount(LandingView, { global: globalConfig })

        expect(wrapper.text()).toContain('Mantenimiento, tickets y reportes')
        expect(wrapper.text()).toContain('Comenzar prueba gratis')
        expect(wrapper.find('.lang-switch button.active').text()).toBe('ES')
    })

    it('cambia a inglés y actualiza <html lang>', async () => {
        const wrapper = mount(LandingView, { global: globalConfig })

        const botones = wrapper.findAll('.lang-switch button')
        const botonEn = botones[1]
        expect(botonEn).toBeDefined()
        await botonEn!.trigger('click')
        await nextTick()

        expect(wrapper.text()).toContain('Maintenance, tickets and reports')
        expect(wrapper.find('.lang-switch button.active').text()).toBe('EN')
        expect(document.documentElement.lang).toBe('en')
    })

    it('aplica y quita el modo oscuro en <html>', async () => {
        const { oscuro, alternarTema } = useTheme()
        const inicial = oscuro.value

        alternarTema()
        expect(document.documentElement.classList.contains('theme-dark')).toBe(!inicial)

        alternarTema()
        expect(document.documentElement.classList.contains('theme-dark')).toBe(inicial)
    })

    it('publica los metas de SEO de la ruta', () => {
        mount(LandingView, { global: globalConfig })

        const description = document.head.querySelector('meta[name="description"]')?.getAttribute('content') ?? ''
        expect(description.length).toBeGreaterThan(40)
        expect(document.head.querySelector('link[rel="canonical"]')?.getAttribute('href')).toBe('https://creports.corion.mx/')
        expect(document.head.querySelector('meta[property="og:title"]')).not.toBeNull()
        expect(document.getElementById('seo-jsonld')?.textContent ?? '').toContain('SoftwareApplication')
    })
})
