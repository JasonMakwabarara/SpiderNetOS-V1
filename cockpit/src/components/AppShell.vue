<script>
import { getAuthState, logout, can } from '../composables/useAuth'

export default {
  name: 'AppShell',
  setup() {
    return { auth: getAuthState(), logout, can }
  },
  computed: {
    fullBleed() {
      return this.$route.meta.fullBleed === true
    },
    navItems() {
      const items = [
        { to: '/dashboard', label: 'Dashboard', icon: '🏠' },
        { to: '/records', label: 'Records', icon: '🗂️' },
        { to: '/flows', label: 'Flows', icon: '⚡' },
        { to: '/agents', label: 'Agents', icon: '🤖' },
        { to: '/atlas', label: 'Atlas', icon: '🧠' },
        { to: '/insights', label: 'Insights', icon: '📊' },
        { to: '/monitoring', label: 'Monitoring', icon: '📡' },
      ]
      if (this.can('manage_workspace')) {
        items.push({ to: '/integrations', label: 'Integrations', icon: '🔌' })
      }
      if (this.can('view_audit')) {
        items.push({ to: '/admin/audit', label: 'Audit', icon: '📋' })
      }
      if (this.auth.user?.is_super_admin) {
        items.push({ to: '/admin/providers', label: 'AI Providers', icon: '⚙️' })
      }
      return items
    },
    initials() {
      const name = this.auth.user?.name || '?'
      return name.split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase()
    },
  },
  methods: {
    isActive(path) {
      return this.$route.path === path || this.$route.path.startsWith(path + '/')
    },
    async signOut() {
      await logout()
      this.$router.push('/login')
    },
  },
}
</script>

<template>
  <div class="shell" :class="{ 'shell-bleed': fullBleed }">
    <aside class="sidebar">
      <div class="brand">
        <span class="brand-icon">🕷️</span>
        <span class="brand-text">SpiderNetOS</span>
      </div>
      <nav class="nav">
        <router-link
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="nav-link"
          :class="{ active: isActive(item.to) }"
        >
          <span class="nav-icon">{{ item.icon }}</span>
          {{ item.label }}
        </router-link>
      </nav>
      <div class="sidebar-foot">
        <router-link to="/settings" class="nav-link" :class="{ active: isActive('/settings') }">
          <span class="nav-icon">⚙️</span> Settings
        </router-link>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <div class="topbar-left">
          <span class="workspace">{{ auth.user?.email }}</span>
          <span v-if="auth.user?.role" class="role-badge">{{ auth.user.role.replace('_', ' ') }}</span>
        </div>
        <div class="user-menu">
          <router-link to="/settings" class="user-chip">
            <span class="avatar">{{ initials }}</span>
            <span class="user-name">{{ auth.user?.name }}</span>
          </router-link>
          <button class="btn-signout" type="button" @click="signOut">Sign out</button>
        </div>
      </header>
      <main class="content" :class="{ 'content-bleed': fullBleed }">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.shell {
  display: grid;
  grid-template-columns: 220px 1fr;
  min-height: 100vh;
  background: #f8fafc;
}
.sidebar {
  background: #0f172a;
  color: #e2e8f0;
  display: flex;
  flex-direction: column;
  padding: 1rem 0.75rem;
  border-right: 1px solid #1e293b;
}
.brand {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem 1.25rem;
  font-weight: 700;
  font-size: 1rem;
}
.brand-icon { font-size: 1.4rem; }
.nav { flex: 1; display: flex; flex-direction: column; gap: 0.2rem; }
.nav-link {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.55rem 0.75rem;
  border-radius: 10px;
  color: #94a3b8;
  text-decoration: none;
  font-size: 0.88rem;
  transition: background 0.15s, color 0.15s;
}
.nav-link:hover { background: #1e293b; color: #f1f5f9; }
.nav-link.active { background: #1d4ed8; color: white; }
.nav-icon { width: 1.2rem; text-align: center; }
.sidebar-foot { border-top: 1px solid #1e293b; padding-top: 0.75rem; }
.main { display: flex; flex-direction: column; min-width: 0; }
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.65rem 1.25rem;
  background: white;
  border-bottom: 1px solid #e2e8f0;
  gap: 1rem;
}
.topbar-left { display: flex; align-items: center; gap: 0.5rem; min-width: 0; }
.workspace { font-size: 0.8rem; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.role-badge {
  font-size: 0.65rem;
  text-transform: uppercase;
  font-weight: 700;
  background: #eff6ff;
  color: #1d4ed8;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
}
.user-menu { display: flex; align-items: center; gap: 0.75rem; }
.user-chip {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
  color: #1e293b;
}
.avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
}
.user-name { font-size: 0.85rem; font-weight: 600; }
.btn-signout {
  background: transparent;
  border: 1px solid #e2e8f0;
  color: #475569;
  padding: 0.35rem 0.75rem;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.8rem;
}
.btn-signout:hover { background: #f8fafc; border-color: #cbd5e1; }
.content { flex: 1; overflow: auto; }
.content-bleed { padding: 0; overflow: hidden; }
.shell-bleed .content { height: calc(100vh - 53px); }

@media (max-width: 900px) {
  .shell { grid-template-columns: 1fr; }
  .sidebar { display: none; }
  .user-name { display: none; }
}
</style>
