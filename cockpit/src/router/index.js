import { createRouter, createWebHistory } from 'vue-router'
import { bootstrapAuth, isAuthenticated, hasMinimumRole } from '../composables/useAuth'

const Landing = () => import('../views/Landing.vue')
const Login = () => import('../views/Login.vue')
const Signup = () => import('../views/Signup.vue')
const ForgotPassword = () => import('../views/ForgotPassword.vue')
const ResetPassword = () => import('../views/ResetPassword.vue')
const Dashboard = () => import('../views/Dashboard.vue')
const Agents = () => import('../views/Agents.vue')
const Flows = () => import('../views/Flows.vue')
const Atlas = () => import('../views/Atlas.vue')
const Records = () => import('../views/Records.vue')
const FlowBuilder = () => import('../views/FlowBuilder.vue')
const Insights = () => import('../views/Insights.vue')
const Providers = () => import('../views/admin/Providers.vue')
const Audit = () => import('../views/admin/Audit.vue')
const Integrations = () => import('../views/Integrations.vue')
const Monitoring = () => import('../views/Monitoring.vue')
const Settings = () => import('../views/Settings.vue')

const routes = [
  { path: '/', name: 'Landing', component: Landing },
  { path: '/login', name: 'Login', component: Login, meta: { guestOnly: true } },
  { path: '/signup', name: 'Signup', component: Signup, meta: { guestOnly: true } },
  { path: '/forgot-password', name: 'ForgotPassword', component: ForgotPassword, meta: { guestOnly: true } },
  { path: '/reset-password', name: 'ResetPassword', component: ResetPassword, meta: { guestOnly: true } },
  { path: '/dashboard', name: 'Dashboard', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/agents', name: 'Agents', component: Agents, meta: { requiresAuth: true } },
  { path: '/flows', name: 'Flows', component: Flows, meta: { requiresAuth: true } },
  { path: '/flows/:id/builder', name: 'FlowBuilder', component: FlowBuilder, meta: { requiresAuth: true, fullBleed: true } },
  { path: '/records', name: 'Records', component: Records, meta: { requiresAuth: true } },
  { path: '/insights', name: 'Insights', component: Insights, meta: { requiresAuth: true } },
  { path: '/atlas', name: 'Atlas', component: Atlas, meta: { requiresAuth: true } },
  { path: '/monitoring', name: 'Monitoring', component: Monitoring, meta: { requiresAuth: true } },
  { path: '/integrations', name: 'Integrations', component: Integrations, meta: { requiresAuth: true, requiresRole: 'tenant_admin' } },
  { path: '/settings', name: 'Settings', component: Settings, meta: { requiresAuth: true } },
  { path: '/admin/providers', name: 'Providers', component: Providers, meta: { requiresAuth: true, requiresSuperAdmin: true } },
  { path: '/admin/audit', name: 'Audit', component: Audit, meta: { requiresAuth: true, requiresRole: 'tenant_admin' } },
  { path: '/:catchAll(.*)', redirect: '/' },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach(async (to) => {
  if (to.meta.requiresAuth) {
    if (!isAuthenticated()) {
      return { name: 'Login', query: { redirect: to.fullPath } }
    }
    const valid = await bootstrapAuth()
    if (!valid) {
      return { name: 'Login', query: { redirect: to.fullPath } }
    }
    if (to.meta.requiresSuperAdmin && !hasMinimumRole('super_admin')) {
      return { name: 'Dashboard', query: { denied: '1' } }
    }
    if (to.meta.requiresRole && !hasMinimumRole(to.meta.requiresRole)) {
      return { name: 'Dashboard', query: { denied: '1' } }
    }
  }

  if (to.meta.guestOnly && isAuthenticated()) {
    const ok = await bootstrapAuth()
    if (ok) return { name: 'Dashboard' }
  }

  return true
})

export default router
