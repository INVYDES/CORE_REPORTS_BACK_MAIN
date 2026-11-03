import { onBeforeUnmount, onMounted, ref } from 'vue'
import { notificacionesApi } from '../api/notificaciones'

export interface NotificacionArea {
  id: number
  nombre: string
  codigo?: string | null
}

export interface NotificacionReferencia {
  id: number
  folio?: string | null
  asunto?: string | null
  estatus?: string | null
  area_id?: number | null
}

export interface NotificacionItem {
  id: number
  tipo: string
  titulo?: string | null
  referencia_tipo: 'ticket' | 'servicio' | 'reporte'
  referencia_id: number
  asunto: string
  cuerpo?: string | null
  leida_at?: string | null
  created_at: string
  area_id?: number | null
  area?: NotificacionArea | null
  referencia?: NotificacionReferencia | null
}

interface UseNotificacionesOptions {
  areaId?: () => number | null | undefined
  intervalMs?: number
}

const DEFAULT_INTERVAL_MS = 15000

export function useNotificacionesRealtime(options: UseNotificacionesOptions = {}) {
  const notifications = ref<NotificacionItem[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)
  const isLive = ref(false)
  const hasNew = ref(false)

  let timer: ReturnType<typeof setInterval> | null = null
  let inFlight = false
  let newestSeenId: number | null = null

  const buildParams = () => {
    const params: Record<string, unknown> = { per_page: 20 }
    const areaId = options.areaId?.()
    if (areaId) params.area_id = areaId
    return params
  }

  const fetchNotifications = async ({ silent = false } = {}) => {
    if (inFlight) return

    inFlight = true
    if (!silent) loading.value = true

    try {
      const res = await notificacionesApi.list(buildParams())
      const list = res?.data ?? res
      const items: NotificacionItem[] = Array.isArray(list) ? list : []

      const newestId = items.reduce((max, item) => Math.max(max, Number(item.id) || 0), 0)
      if (newestSeenId !== null && newestId > newestSeenId) {
        hasNew.value = true
      }
      newestSeenId = newestId

      notifications.value = items
      unreadCount.value = Number(
        res?.meta?.no_leidas ??
          res?.no_leidas ??
          items.filter(item => !item.leida_at).length
      )
      isLive.value = true
    } catch {
      isLive.value = false
    } finally {
      inFlight = false
      if (!silent) loading.value = false
    }
  }

  const markRead = async (id: number) => {
    const notification = notifications.value.find(item => item.id === id)
    if (!notification || notification.leida_at) return

    try {
      await notificacionesApi.markRead(id)
      notification.leida_at = new Date().toISOString()
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch {}
  }

  const markAllRead = async () => {
    try {
      await notificacionesApi.markAllRead()
      const now = new Date().toISOString()
      notifications.value.forEach(item => {
        item.leida_at = item.leida_at || now
      })
      unreadCount.value = 0
      hasNew.value = false
    } catch {}
  }

  const clearNew = () => {
    hasNew.value = false
  }

  const start = () => {
    if (timer) return

    void fetchNotifications()
    timer = setInterval(() => {
      if (document.visibilityState !== 'hidden') {
        void fetchNotifications({ silent: true })
      }
    }, options.intervalMs ?? DEFAULT_INTERVAL_MS)
  }

  const stop = () => {
    if (!timer) return
    clearInterval(timer)
    timer = null
  }

  const handleVisibility = () => {
    if (document.visibilityState === 'visible') {
      void fetchNotifications({ silent: true })
    }
  }

  const handleOnline = () => {
    void fetchNotifications({ silent: true })
  }

  onMounted(() => {
    start()
    document.addEventListener('visibilitychange', handleVisibility)
    window.addEventListener('online', handleOnline)
    window.addEventListener('focus', handleOnline)
  })

  onBeforeUnmount(() => {
    stop()
    document.removeEventListener('visibilitychange', handleVisibility)
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('focus', handleOnline)
  })

  return {
    notifications,
    unreadCount,
    loading,
    isLive,
    hasNew,
    fetchNotifications,
    markRead,
    markAllRead,
    clearNew,
    start,
    stop,
  }
}
