import axios from 'axios'

const rawBase = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'
const baseURL = rawBase.endsWith('/v1') ? rawBase : rawBase.replace(/\/$/, '') + '/v1'
const client = axios.create({
  baseURL,
  withCredentials: false,
  headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
})

client.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

client.interceptors.response.use(
  (r) => r,
  (error) => {
    const status = error.response?.status
    if (status === 419) {
      error.response.data = { message: 'Sesión expirada. Recarga la página e intenta de nuevo.' }
    }
    if (status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      delete client.defaults.headers.common['Authorization']
      if (!window.location.pathname.includes('/core-reports/')) {
      }
    }
    if (status === 422 && error.response?.data?.errors) {
    }
    return Promise.reject(error)
  }
)

export default client