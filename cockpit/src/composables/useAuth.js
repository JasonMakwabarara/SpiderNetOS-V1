import { reactive, readonly } from 'vue'
import api from '../services/api'

const ROLE_RANK = { member: 1, tenant_admin: 2, super_admin: 3 }

const state = reactive({
  user: null,
  token: null,
  bootstrapped: false,
})

let onUnauthorized = null

export function setUnauthorizedHandler(handler) {
  onUnauthorized = handler
}

function persist() {
  if (state.token) {
    localStorage.setItem('token', state.token)
  } else {
    localStorage.removeItem('token')
  }
  if (state.user) {
    localStorage.setItem('user', JSON.stringify(state.user))
  } else {
    localStorage.removeItem('user')
  }
}

function loadFromStorage() {
  state.token = localStorage.getItem('token')
  try {
    state.user = JSON.parse(localStorage.getItem('user') || 'null')
  } catch {
    state.user = null
  }
}

loadFromStorage()

export function getAuthState() {
  return readonly(state)
}

export function isAuthenticated() {
  return !!state.token
}

export function hasMinimumRole(role) {
  if (!state.user) return false
  const current = ROLE_RANK[state.user.role] || 0
  const required = ROLE_RANK[role] || 0
  return current >= required
}

export function can(permission) {
  return !!state.user?.permissions?.[permission]
}

export async function fetchMe() {
  if (!state.token) return null
  const res = await api.get('/me')
  state.user = res.data
  persist()
  return state.user
}

export async function bootstrapAuth() {
  if (state.bootstrapped) return isAuthenticated()
  state.bootstrapped = true
  if (!state.token) return false
  try {
    await fetchMe()
    return true
  } catch {
    clearSession()
    return false
  }
}

export function setSession(token, user) {
  state.token = token
  state.user = user
  persist()
}

export function clearSession() {
  state.token = null
  state.user = null
  persist()
  if (onUnauthorized) onUnauthorized()
}

export async function login(email, password) {
  const res = await api.post('/login', { email, password })
  setSession(res.data.token, res.data.user)
  return res.data
}

export async function register(payload) {
  const res = await api.post('/register', payload)
  setSession(res.data.token, res.data.user)
  return res.data
}

export async function logout() {
  try {
    if (state.token) await api.post('/logout')
  } catch {
    // Always clear local session even if the token already expired.
  }
  clearSession()
}
