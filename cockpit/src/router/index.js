import { createRouter, createWebHistory } from 'vue-router'
const Landing = () => import('../views/Landing.vue')
const Login = () => import('../views/Login.vue')
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

const routes = [
  { path: '/', name: 'Landing', component: Landing },
  { path: '/login', name: 'Login', component: Login },
  { path: '/dashboard', name: 'Dashboard', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/agents', name: 'Agents', component: Agents, meta: { requiresAuth: true } },
  { path: '/flows', name: 'Flows', component: Flows, meta: { requiresAuth: true } },
  { path: '/flows/:id/builder', name: 'FlowBuilder', component: FlowBuilder, meta: { requiresAuth: true } },
  { path: '/records', name: 'Records', component: Records, meta: { requiresAuth: true } },
  { path: '/insights', name: 'Insights', component: Insights, meta: { requiresAuth: true } },
  { path: '/atlas', name: 'Atlas', component: Atlas, meta: { requiresAuth: true } },
  { path: '/monitoring', name: 'Monitoring', component: Monitoring, meta: { requiresAuth: true } },
  { path: '/integrations', name: 'Integrations', component: Integrations, meta: { requiresAuth: true } },
  { path: '/admin/providers', name: 'Providers', component: Providers, meta: { requiresAuth: true } },
  { path: '/admin/audit', name: 'Audit', component: Audit, meta: { requiresAuth: true } },
  { path: '/:catchAll(.*)', redirect: '/' }
]

const router = createRouter({ history: createWebHistory(), routes })

// Navigation guard: redirect unauthenticated users to /login before load,
// not only reactively on a 401 response.
router.beforeEach((to) => {
  const isAuthenticated = !!localStorage.getItem('token')
  if (to.meta.requiresAuth && !isAuthenticated) {
    return { name: 'Login', query: { redirect: to.fullPath } }
  }
  if (to.name === 'Login' && isAuthenticated) {
    return { name: 'Dashboard' }
  }
  return true
})

export default router
