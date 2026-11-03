import { ref } from 'vue'

/**
 * Modo oscuro para las páginas públicas.
 * Se guarda en localStorage y, si no hay preferencia, respeta el sistema.
 */
const LS_KEY = 'theme'
const oscuro = ref(false)

function aplicar(valor: boolean): void {
  if (typeof document === 'undefined') return
  document.documentElement.classList.toggle('theme-dark', valor)
  document.documentElement.style.colorScheme = valor ? 'dark' : 'light'
}

try {
  const guardado = localStorage.getItem(LS_KEY)
  oscuro.value =
    guardado === 'dark'
      ? true
      : guardado === 'light'
        ? false
        : !!window.matchMedia?.('(prefers-color-scheme: dark)').matches
} catch {
  /* no-op */
}

aplicar(oscuro.value)

export function useTheme() {
  const alternarTema = (): void => {
    oscuro.value = !oscuro.value
    aplicar(oscuro.value)
    try {
      localStorage.setItem(LS_KEY, oscuro.value ? 'dark' : 'light')
    } catch {
      /* no-op */
    }
  }

  return { oscuro, alternarTema }
}
