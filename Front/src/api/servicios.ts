import client from './client'
import { mapServicioForBackend } from './mapper'

export const serviciosApi = {
  list: (params: any = {}) => client.get('/servicios', { params }).then(r => r.data),
  get: (id: number | string) => client.get(`/servicios/${id}`).then(r => r.data),
  create: (payload: any) => {
    const body = mapServicioForBackend(payload)
    return client.post('/servicios', body).then(r => r.data)
  },
  update: (id: number, data: any) => client.put(`/servicios/${id}`, data).then(r => r.data),
}
