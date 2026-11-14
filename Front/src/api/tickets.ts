import client from './client'
import { mapTicketForBackend } from './mapper'

export const ticketsApi = {
  list: (params: any = {}) => client.get('/tickets', { params }).then(r => r.data),
  get: (id: number | string) => client.get(`/tickets/${id}`).then(r => r.data),
  create: (payload: any) => {
    const body = mapTicketForBackend(payload)
    return client.post('/tickets', body).then(r => r.data)
  },
  update: (id: number, data: any) => client.put(`/tickets/${id}`, data).then(r => r.data),
  asignar: (id: number, usuario_asignado_id: number) =>
    client.patch(`/tickets/${id}/asignar`, { usuario_asignado_id }).then(r => r.data),
}
