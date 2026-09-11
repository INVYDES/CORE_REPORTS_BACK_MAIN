// @ts-nocheck
import client from './client'
import { mapServicioForBackend } from './mapper'

export const serviciosApi = {
  list: (params:any={}) => client.get('/servicios', { params }).then(r=>r.data),
  get: (id:number|string) => client.get(`/servicios/${id}`).then(r=>r.data),
  create: (payload:any) => {
    const body = mapServicioForBackend(payload)
    if (!body.folio) body.folio = `SRV-${Date.now().toString().slice(-6)}`
    return client.post('/servicios', body).then(r=>r.data)
  },
  update: (id:number, data:any) => client.put(`/servicios/${id}`, data).then(r=>r.data),
}