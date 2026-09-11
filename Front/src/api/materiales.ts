import client from './client'
export const materialesApi = {
  list: (params:any={}) => client.get('/materiales', { params }).then(r=>r.data),
  create: (data:any) => client.post('/materiales', data).then(r=>r.data),
}