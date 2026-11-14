export interface ReportExtraMeta {
  conclusion?: string
  responsable?: string
  cargo?: string
  falla?: string
  fallaLibre?: string
  areaTexto?: string
  materiales?: Array<{
    idItem?: number
    material: string
    cantidad: number
    unidad: string
  }>
  evidenciaComentarios?: string[]
}

const META_REGEX = /\n*<!--CORE_META:([\s\S]*?)-->\s*$/

export function packReportDesarrollo(text: string, meta: ReportExtraMeta): string {
  const cleanText = (text || '').replace(META_REGEX, '').trim()
  const hasData =
    !!meta.conclusion ||
    !!meta.responsable ||
    !!meta.cargo ||
    !!meta.falla ||
    !!meta.fallaLibre ||
    !!meta.areaTexto ||
    (Array.isArray(meta.materiales) && meta.materiales.length > 0) ||
    (Array.isArray(meta.evidenciaComentarios) && meta.evidenciaComentarios.some(c => !!c))

  if (!hasData) return cleanText
  return `${cleanText}\n\n<!--CORE_META:${JSON.stringify(meta)}-->`
}

export function unpackReportDesarrollo(raw?: string | null): {
  text: string
  meta: ReportExtraMeta
} {
  if (!raw) return { text: '', meta: {} }
  const str = String(raw)
  const match = str.match(META_REGEX)
  if (!match) {
    return { text: str, meta: {} }
  }
  const text = str.replace(META_REGEX, '').trim()
  try {
    const meta = JSON.parse(match[1] || '{}') as ReportExtraMeta
    return { text, meta: meta && typeof meta === 'object' ? meta : {} }
  } catch {
    return { text, meta: {} }
  }
}

/**
 * Strips trailing UTC markers (.000000Z / Z) from ISO strings returned by Laravel
 * so that wall-clock timestamps stored in MySQL are interpreted in the browser's local time
 * without subtracting 6 hours (Mexico UTC-6).
 */
export function stripUtcSuffix(val?: string | null): string {
  if (!val) return ''
  return String(val)
    .trim()
    .replace(/\.\d+Z$/i, '')
    .replace(/Z$/i, '')
}

export function parseLocalDate(val?: string | null): Date | null {
  const clean = stripUtcSuffix(val)
  if (!clean) return null
  if (/^\d{4}-\d{2}-\d{2}$/.test(clean)) {
    const [y = 2026, m = 1, d = 1] = clean.split('-').map(Number)
    return new Date(y, m - 1, d, 12, 0, 0)
  }
  const normalized = clean.includes('T') ? clean : clean.replace(' ', 'T')
  const dt = new Date(normalized)
  return isNaN(dt.getTime()) ? null : dt
}

export function formatLocalDateTime(val?: string | null): string {
  const d = parseLocalDate(val)
  if (!d) return '---'
  return d.toLocaleDateString('es-MX', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function formatLocalDateOnly(val?: string | null): string {
  const d = parseLocalDate(val)
  if (!d) return '---'
  return d.toLocaleDateString('es-MX', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  })
}

export function formatLocalDateShort(val?: string | null): string {
  const clean = stripUtcSuffix(val)
  if (!clean) return ''
  return clean.replace('T', ' ').slice(0, 16)
}

export function toInputDatetimeLocal(val?: string | null): string {
  const clean = stripUtcSuffix(val)
  if (!clean) return ''
  return clean.replace(' ', 'T').slice(0, 16)
}

export function calcNetHoursText(start?: string | null, end?: string | null): string {
  const s = parseLocalDate(start)
  const e = parseLocalDate(end)
  if (!s || !e) return '0h 0m'
  const diffMs = e.getTime() - s.getTime()
  if (isNaN(diffMs) || diffMs <= 0) return '0h 0m'
  const totalMinutes = Math.round(diffMs / (1000 * 60))
  const h = Math.floor(totalMinutes / 60)
  const m = totalMinutes % 60
  return `${h}h ${m}m`
}


