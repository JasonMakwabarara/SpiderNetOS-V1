import { createRouter, createWebHistory } from 'vue-router'
const Landing = () => import('../views/Landing.vue')
const Login = () => import('../views/Login.vue')
const Dashboard = () => import('../views/Dashboard.vue')
const Agents = () => import('../views/Agents.vue')
const Flows = () => import('../views/Flows.vue')
const Atlas = () => import('../views/Atlas.vue')

const routes = [
  { path: '/', name: 'Landing', component: Landing },
  { path: '/login', name: 'Login', component: Login },
  { path: '/dashboard', name: 'Dashboard', component: Dashboard },
  { path: '/agents', name: 'Agents', component: Agents },
  { path: '/flows', name: 'Flows', component: Flows },
  { path: '/atlas', name: 'Atlas', component: Atlas },
  { path: '/:catchAll(.*)', redirect: '/' }
]
const router = createRouter({ history: createWebHistory(), routes })
export default router
