import client from './client'
import { mapReporteForBackend, buildReporteFormData } from './mapper'

export const reportesApi = {
  list: (params:any={}) => client.get('/reportes', { params }).then(r=>r.data),
  get: (id:number|string) => client.get(`/reportes/${id}`).then(r=>r.data),
  create: async (formData: any, files?: File[]) => {
    const mapped = mapReporteForBackend(formData, files || formData._files || [])
    const fd = await buildReporteFormData(mapped, client)
    return client.post('/reportes', fd, { headers: { 'Content-Type':'multipart/form-data' } }).then(r=>r.data)
  },
  update: (id:number, data:any) => client.put(`/reportes/${id}`, data).then(r=>r.data),
  conformidad: (id:number, payload:any) => client.patch(`/reportes/${id}/conformidad`, payload).then(r=>r.data)
}