<template>
  <div class="audit-page">
    <div class="page-header">
      <div>
        <h1>🔒 Audit Log</h1>
        <p>Security and workspace activity trail</p>
      </div>
      <router-link class="btn-outline" to="/dashboard">← Dashboard</router-link>
    </div>

    <div class="toolbar">
      <input v-model="filterType" class="filter" placeholder="Filter by type (e.g. auth, record)" @keyup.enter="load" />
      <button class="btn-ghost" @click="load">Refresh</button>
    </div>

    <div v-if="loading" class="loading">Loading audit events…</div>

    <table v-else-if="events.length" class="audit-table">
      <thead>
        <tr>
          <th>Time</th>
          <th>Type</th>
          <th>Aggregate</th>
          <th>User</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="ev in events" :key="ev.id">
          <td class="time">{{ formatTime(ev.created_at) }}</td>
          <td><span class="type-badge">{{ ev.type }}</span></td>
          <td class="mono">{{ ev.aggregate_id || '—' }}</td>
          <td>{{ ev.user_id || '—' }}</td>
          <td class="details">{{ formatData(ev.data) }}</td>
        </tr>
      </tbody>
    </table>

    <div v-else class="empty">No audit events found.</div>
  </div>
</template>

<script>
import api from '../../services/api'

export default {
  data() {
    return { events: [], loading: true, filterType: '' }
  },
  async mounted() { await this.load() },
  methods: {
    async load() {
      this.loading = true
      try {
        const params = {}
        if (this.filterType) params.type = this.filterType
        const res = await api.get('/audit', { params })
        this.events = res.data || []
      } catch (e) {
        if (e.response?.status === 403) this.$router.push('/dashboard')
      } finally {
        this.loading = false
      }
    },
    formatTime(t) {
      if (!t) return ''
      return new Date(t).toLocaleString()
    },
    formatData(data) {
      if (!data) return ''
      if (typeof data === 'string') {
        try { data = JSON.parse(data) } catch (e) { return data }
      }
      return Object.entries(data).map(([k, v]) => `${k}: ${v}`).join(' · ')
    }
  }
}
</script>

<style scoped>
.audit-page { padding: 2rem; max-width: 1200px; margin: 0 auto; min-height: 100vh; background: linear-gradient(135deg, #f5f7fa 0%, #eef2f6 100%); }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
.page-header h1 { font-size: 1.8rem; color: #1e293b; }
.page-header p { color: #64748b; font-size: 0.85rem; }
.btn-outline { background: transparent; border: 1px solid #3b82f6; color: #3b82f6; padding: 0.5rem 1rem; border-radius: 12px; text-decoration: none; }
.toolbar { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.filter { flex: 1; padding: 0.55rem 1rem; border: 1px solid #e2e8f0; border-radius: 12px; }
.btn-ghost { background: #f1f5f9; border: none; padding: 0.55rem 1rem; border-radius: 12px; cursor: pointer; }
.loading, .empty { padding: 3rem; text-align: center; color: #94a3b8; }
.audit-table { width: 100%; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-collapse: collapse; }
.audit-table th, .audit-table td { padding: 0.65rem 0.9rem; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 0.8rem; }
.audit-table th { background: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.68rem; }
.type-badge { background: #e0e7ff; color: #3730a3; padding: 0.15rem 0.5rem; border-radius: 8px; font-size: 0.72rem; font-weight: 600; }
.mono { font-family: ui-monospace, monospace; font-size: 0.75rem; color: #475569; }
.details { color: #64748b; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.time { white-space: nowrap; color: #94a3b8; font-size: 0.75rem; }
</style>
