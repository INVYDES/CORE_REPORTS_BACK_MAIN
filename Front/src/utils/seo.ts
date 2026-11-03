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

const MANAGED_TAGS: Array<['name' | 'property', string]> = [
  ['name', 'description'],
  ['name', 'robots'],
  ['property', 'og:type'],
  ['property', 'og:site_name'],
  ['property', 'og:locale'],
  ['property', 'og:title'],
  ['property', 'og:description'],
  ['property', 'og:url'],
  ['property', 'og:image'],
  ['name', 'twitter:card'],
  ['name', 'twitter:title'],
  ['name', 'twitter:description'],
  ['name', 'twitter:image'],
]

interface SeoSnapshot {
  title: string
  meta: Map<string, string | null>
  canonical: string | null
}

let pristine: SeoSnapshot | null = null
let activeInstances = 0

function capturePristine(): void {
  const meta = new Map<string, string | null>()
  for (const [attr, key] of MANAGED_TAGS) {
    const el = document.head.querySelector<HTMLMetaElement>(`meta[${attr}="${key}"]`)
    meta.set(`${attr}:${key}`, el ? el.getAttribute('content') : null)
  }
  const canonical = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]')
  pristine = {
    title: document.title,
    meta,
    canonical: canonical ? canonical.getAttribute('href') : null,
  }
}

function restorePristine(): void {
  if (!pristine) return
  document.title = pristine.title
  for (const [attr, key] of MANAGED_TAGS) {
    const el = document.head.querySelector<HTMLMetaElement>(`meta[${attr}="${key}"]`)
    const value = pristine.meta.get(`${attr}:${key}`)
    if (value === null || value === undefined) {
      el?.remove()
    } else if (el) {
      el.setAttribute('content', value)
    }
  }
  const canonical = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]')
  if (pristine.canonical === null) {
    canonical?.remove()
  } else if (canonical) {
    canonical.setAttribute('href', pristine.canonical)
  }
}

/**
 * Aplica el SEO y lo mantiene sincronizado con la ruta actual.
 * Al desmontar la última página pública, restaura las metas por defecto de
 * index.html para que las vistas internas (/app) no hereden el SEO del sitio.
 */
export function useSeo(getOptions: () => SeoOptions): void {
  if (!pristine) capturePristine()
  activeInstances += 1

  watchEffect(() => applySeo(getOptions()))

  onBeforeUnmount(() => {
    activeInstances -= 1
    if (activeInstances === 0) {
      document.getElementById('seo-jsonld')?.remove()
      restorePristine()
    }
  })
}
