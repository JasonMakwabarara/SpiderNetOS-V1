import axios from 'axios'
import router from '../router'
import { clearSession } from '../composables/useAuth'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
  headers: { 'Content-Type': 'application/json' },
  timeout: 30000,
})

api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  response => response,
  async error => {
    const url = error.config?.url || ''
    if (error.response?.status === 401 && !url.includes('/login') && !url.includes('/register')) {
      clearSession()
      if (router.currentRoute.value?.name !== 'Login') {
        const redirect = router.currentRoute.value?.fullPath
        await router.push({
          name: 'Login',
          query: redirect && redirect !== '/login' ? { redirect } : {},
        })
      }
    }
    return Promise.reject(error)
  }
)

export default api
