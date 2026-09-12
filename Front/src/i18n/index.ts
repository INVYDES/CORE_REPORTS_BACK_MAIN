import { createI18n } from 'vue-i18n'
import es from './es'
import en from './en'

export type MensajesIdioma = 'es' | 'en'

const LS_KEY = 'lang'

function idiomaInicial(): MensajesIdioma {
  try {
    const guardado = localStorage.getItem(LS_KEY) as MensajesIdioma | null
    if (guardado === 'es' || guardado === 'en') return guardado
    const navegador = navigator.language?.toLowerCase() ?? 'es'
    return navegador.startsWith('en') ? 'en' : 'es'
  } catch {
    return 'es'
  }
}

const i18n = createI18n({
  legacy: false, // modo Composition API
  locale: idiomaInicial(),
  fallbackLocale: 'es',
  messages: { es, en },
})

/** Cambia el idioma y persiste la preferencia. */
export function cambiarIdioma(idioma: MensajesIdioma): void {
  i18n.global.locale.value = idioma
  try {
    localStorage.setItem(LS_KEY, idioma)
  } catch {
    /* no-op */
  }
}

export default i18n
