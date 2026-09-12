/**
 * Plugin Vite minimalista que genera /manifest.webmanifest en el build
 * (y en dev server) sin dependencias externas. El manifest permite que
 * la app sea instalable como PWA; el service worker opcional puede
 * añadirse después (workbox) si se requiere modo offline completo.
 */


interface OpcionesManifest {
  nombre?: string
  nombreCorto?: string
  descripcion?: string
  colorTema?: string
  colorFondo?: string
}

export function VueWebManifestPlugin(opciones: OpcionesManifest = {}) {
  const {
    nombre = 'CoreReports',
    nombreCorto = 'coreReport',
    descripcion = 'Gestión de mantenimiento, tickets y reportes técnicos',
    colorTema = '#1e3a8a',
    colorFondo = '#ffffff',
  } = opciones

  const manifest = {
    name: nombre,
    short_name: nombreCorto,
    description: descripcion,
    start_url: '/',
    display: 'standalone',
    background_color: colorFondo,
    theme_color: colorTema,
    lang: 'es',
    icons: [
      { src: '/icon-core.svg', sizes: 'any', type: 'image/svg+xml', purpose: 'any' },
    ],
  }

  const MANIFEST_PATH = 'manifest.webmanifest'

  return {
    name: 'vue-web-manifest',
    configureServer(server: any) {
      server.middlewares.use((req: any, res: any, next: () => void) => {
        if (req.url === '/' + MANIFEST_PATH) {
          res.setHeader('Content-Type', 'application/manifest+json')
          res.end(JSON.stringify(manifest, null, 2))
          return
        }
        next()
      })
    },
    generateBundle(this: any) {
      this.emitFile({
        type: 'asset',
        fileName: MANIFEST_PATH,
        source: JSON.stringify(manifest, null, 2),
      })
    },
  }
}
