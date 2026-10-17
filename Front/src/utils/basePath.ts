/**
 * Ruta base pública de la app ("/" o "/core-reports/").
 *
 * Se deduce de la URL del propio bundle para que un mismo build funcione
 * tanto en la raíz de un dominio/subdominio como dentro de un subdirectorio.
 */
const bundlePath = new URL(import.meta.url).pathname
const assetsIndex = bundlePath.lastIndexOf('/assets/')

export const appBase = assetsIndex >= 0 ? bundlePath.slice(0, assetsIndex + 1) : '/'
