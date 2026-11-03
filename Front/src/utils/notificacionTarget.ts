import type { RouteLocationRaw } from 'vue-router'
import type { NotificacionItem } from '../composables/useNotificacionesRealtime'

export function notificationTarget(
  notification: Pick<NotificacionItem, 'referencia_tipo' | 'referencia_id'>
): RouteLocationRaw | null {
  const id = Number(notification.referencia_id)
  if (!Number.isFinite(id) || id <= 0) return null

  switch (notification.referencia_tipo) {
    case 'ticket':
      return { name: 'ticket-detail', params: { id } }
    case 'servicio':
      return { name: 'service-detail', params: { id } }
    case 'reporte':
      return { name: 'report-detail', params: { id } }
    default:
      return null
  }
}
