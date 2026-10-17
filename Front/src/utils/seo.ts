import { onBeforeUnmount, watchEffect } from 'vue'

/**
 * SEO para una SPA: título, description, canonical, Open Graph, Twitter Card,
 * robots y datos estructurados JSON-LD por ruta.
 *
 * No hay prerender, así que los buscadores leen estas etiquetas después de
 * ejecutar el JS. La base index.html ya trae los valores por defecto.
 */

export const SITE_URL = 'https://creports.corion.mx'
export const SITE_NAME = 'coreReports'
const DEFAULT_IMAGE = `${SITE_URL}/logo_empresa.png`

export interface SeoOptions {
    /** Título completo de la página (se muestra tal cual). */
    title: string
    description: string
    /** Ruta canónica, ej. "/" o "/terminos". */
    path: string
    image?: string
    noindex?: boolean
    jsonLd?: Record<string, unknown> | Record<string, unknown>[]
}

function setMeta(attr: 'name' | 'property', key: string, content: string): void {
    let el = document.head.querySelector<HTMLMetaElement>(`meta[${attr}="${key}"]`)
    if (!el) {
        el = document.createElement('meta')
        el.setAttribute(attr, key)
        document.head.appendChild(el)
    }
    el.setAttribute('content', content)
}

function setLink(rel: string, href: string): void {
    let el = document.head.querySelector<HTMLLinkElement>(`link[rel="${rel}"]`)
    if (!el) {
        el = document.createElement('link')
        el.setAttribute('rel', rel)
        document.head.appendChild(el)
    }
    el.setAttribute('href', href)
}

function cleanPath(path: string): string {
    const sinHash = path.split('#')[0] ?? ''
    const sinQuery = sinHash.split('?')[0] ?? ''
    return sinQuery || '/'
}

export function applySeo(options: SeoOptions): void {
    const url = SITE_URL + cleanPath(options.path)
    const image = options.image ?? DEFAULT_IMAGE
    const robots = options.noindex ? 'noindex,nofollow' : 'index,follow'

    document.title = options.title
    setMeta('name', 'description', options.description)
    setMeta('name', 'robots', robots)
    setLink('canonical', url)

    setMeta('property', 'og:type', 'website')
    setMeta('property', 'og:site_name', SITE_NAME)
    setMeta('property', 'og:locale', 'es_MX')
    setMeta('property', 'og:title', options.title)
    setMeta('property', 'og:description', options.description)
    setMeta('property', 'og:url', url)
    setMeta('property', 'og:image', image)

    setMeta('name', 'twitter:card', 'summary_large_image')
    setMeta('name', 'twitter:title', options.title)
    setMeta('name', 'twitter:description', options.description)
    setMeta('name', 'twitter:image', image)

    const prev = document.getElementById('seo-jsonld')
    if (prev) prev.remove()

    if (options.jsonLd) {
        const script = document.createElement('script')
        script.type = 'application/ld+json'
        script.id = 'seo-jsonld'
        script.textContent = JSON.stringify(options.jsonLd)
        document.head.appendChild(script)
    }
}

/** Aplica el SEO y lo mantiene sincronizado con la ruta actual. */
export function useSeo(getOptions: () => SeoOptions): void {
    watchEffect(() => applySeo(getOptions()))

    onBeforeUnmount(() => {
        document.getElementById('seo-jsonld')?.remove()
    })
}
