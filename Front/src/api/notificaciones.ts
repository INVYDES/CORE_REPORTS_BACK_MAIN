import client from './client'
export const notificacionesApi = {
  list: (params:any={}) => client.get('/notificaciones', { params }).then(r=>r.data),
  create: (data:any) => client.post('/notificaciones', data).then(r=>r.data),
  markRead: (id:number) => client.patch(`/notificaciones/${id}/leida`).then(r=>r.data),
  markAllRead: () => client.post('/notificaciones/leidas-todas').then(r=>r.data),
  resend: (id:number) => client.post(`/notificaciones/${id}/reenviar`).then(r=>r.data),
}
