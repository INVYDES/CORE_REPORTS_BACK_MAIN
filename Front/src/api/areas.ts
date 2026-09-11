import client from './client'
export const areasApi = {
  list: (params:any={}) => client.get('/areas', { params }).then(r=>r.data),
  create: (data:any) => client.post('/areas', data).then(r=>r.data),
  update: (id:number, data:any) => client.put(`/areas/${id}`, data).then(r=>r.data),
  remove: (id:number) => client.delete(`/areas/${id}`).then(r=>r.data),
}