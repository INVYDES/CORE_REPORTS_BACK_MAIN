import client from './client'
export const historyApi = {
  list: (params:any={}) => client.get('/history', { params }).then(r=>r.data),
}
