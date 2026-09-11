import client from './client'
export const dashboardApi = {
  get: () => client.get('/dashboard').then(r=>r.data),
  analyticsAll: () => client.get('/analytics/all').then(r=>r.data),
}