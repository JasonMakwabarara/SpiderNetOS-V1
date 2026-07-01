<template>
  <div class="monitoring-page">
    <div class="page-header">
      <div>
        <h1>📡 Monitoring</h1>
        <p>System health, agent performance, and workspace activity</p>
      </div>
      <div class="header-actions">
        <span class="poll-hint" :class="{ live: liveConnected }">
          {{ liveConnected ? 'Live via WebSocket' : `Auto-refresh ${pollSeconds}s` }}
        </span>
        <router-link class="btn-outline" to="/dashboard">← Dashboard</router-link>
      </div>
    </div>

    <div v-if="loading && !metrics" class="loading">Loading metrics…</div>

    <template v-else-if="metrics">
      <div class="status-banner" :class="metrics.system?.status">
        System {{ metrics.system?.status || 'unknown' }} · DB {{ metrics.system?.database }} · Queue {{ metrics.system?.queue_driver }}
        <span v-if="metrics.system?.queue_depth != null"> (depth {{ metrics.system.queue_depth }})</span>
      </div>

      <div class="kpis">
        <div class="kpi"><span class="label">Agent runs</span><span class="val">{{ metrics.agents?.total ?? 0 }}</span><span class="sub">{{ metrics.agents?.recent_failures ?? 0 }} failed (24h)</span></div>
        <div class="kpi"><span class="label">Flow runs</span><span class="val">{{ metrics.flows?.total ?? 0 }}</span><span class="sub">{{ metrics.flows?.recent_failures ?? 0 }} failed (24h)</span></div>
        <div class="kpi"><span class="label">Integrations</span><span class="val">{{ metrics.integrations?.connected ?? 0 }}/{{ metrics.integrations?.total ?? 0 }}</span><span class="sub">connected</span></div>
        <div class="kpi"><span class="label">AI spend today</span><span class="val">${{ metrics.ai?.estimated_cost_today_usd ?? 0 }}</span><span class="sub">cap ${{ metrics.ai?.daily_cap_usd ?? 0 }}</span></div>
        <div class="kpi"><span class="label">Audit (24h)</span><span class="val">{{ metrics.activity?.audit_events_24h ?? 0 }}</span><span class="sub">{{ metrics.activity?.timeline_entries_24h ?? 0 }} timeline entries</span></div>
      </div>

      <div class="panels">
        <div class="panel">
          <h3>Agent runs by status</h3>
          <div v-for="(count, status) in metrics.agents?.by_status || {}" :key="'a'+status" class="bar-row">
            <span>{{ status }}</span>
            <div class="bar-track"><div class="bar-fill" :style="{ width: barPct(count, metrics.agents?.total) }"></div></div>
            <span>{{ count }}</span>
          </div>
        </div>
        <div class="panel">
          <h3>Flow runs by status</h3>
          <div v-for="(count, status) in metrics.flows?.by_status || {}" :key="'f'+status" class="bar-row">
            <span>{{ status }}</span>
            <div class="bar-track"><div class="bar-fill flow" :style="{ width: barPct(count, metrics.flows?.total) }"></div></div>
            <span>{{ count }}</span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import api from '../services/api'
import { subscribeTenantChannel, disconnectEcho } from '../services/echo'

export default {
  data() {
    return {
      metrics: null,
      loading: true,
      pollSeconds: 60,
      timer: null,
      liveConnected: false,
      tenantId: null,
    }
  },
  async mounted() {
    await this.load()
    await this.setupLive()
    this.timer = setInterval(this.load, this.pollSeconds * 1000)
  },
  beforeUnmount() {
    if (this.timer) clearInterval(this.timer)
    disconnectEcho()
  },
  methods: {
    async load() {
      try {
        const res = await api.get('/monitoring')
        this.metrics = res.data
      } catch (e) { console.error(e) }
      finally { this.loading = false }
    },
    async setupLive() {
      try {
        const me = await api.get('/me')
        this.tenantId = me.data?.tenant_id
        if (!this.tenantId) return

        const channel = subscribeTenantChannel(this.tenantId, {
          onMonitoring: (event) => {
            if (event?.metrics) {
              this.metrics = event.metrics
              this.liveConnected = true
            }
          },
        })
        if (channel) this.liveConnected = true
      } catch (e) {
        console.warn('Live monitoring unavailable, using polling', e)
      }
    },
    barPct(count, total) {
      const t = Math.max(1, total || 0)
      return Math.round((count / t) * 100) + '%'
    }
  }
}
</script>

<style scoped>
.monitoring-page { padding: 2rem; max-width: 1100px; margin: 0 auto; min-height: 100vh; background: linear-gradient(135deg, #0f172a, #1e293b); color: #e2e8f0; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
.page-header h1 { font-size: 1.8rem; color: #f8fafc; }
.page-header p { color: #94a3b8; font-size: 0.85rem; }
.header-actions { display: flex; align-items: center; gap: 0.75rem; }
.poll-hint { font-size: 0.75rem; color: #64748b; }
.poll-hint.live { color: #34d399; }
.btn-outline { background: transparent; border: 1px solid #64748b; color: #cbd5e1; padding: 0.45rem 0.9rem; border-radius: 10px; text-decoration: none; font-size: 0.85rem; }
.status-banner { padding: 0.75rem 1rem; border-radius: 12px; margin-bottom: 1.25rem; font-size: 0.85rem; font-weight: 600; }
.status-banner.healthy { background: #064e3b; color: #6ee7b7; }
.status-banner.degraded { background: #7f1d1d; color: #fca5a5; }
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.kpi { background: rgba(255,255,255,0.05); border-radius: 14px; padding: 1rem; border: 1px solid rgba(255,255,255,0.08); }
.kpi .label { font-size: 0.68rem; text-transform: uppercase; color: #94a3b8; display: block; }
.kpi .val { font-size: 1.5rem; font-weight: 700; color: #f8fafc; display: block; margin: 0.2rem 0; }
.kpi .sub { font-size: 0.7rem; color: #64748b; }
.panels { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
.panel { background: rgba(255,255,255,0.04); border-radius: 14px; padding: 1rem; border: 1px solid rgba(255,255,255,0.06); }
.panel h3 { font-size: 0.9rem; margin-bottom: 0.75rem; color: #cbd5e1; }
.bar-row { display: grid; grid-template-columns: 70px 1fr 36px; gap: 0.5rem; align-items: center; margin-bottom: 0.45rem; font-size: 0.75rem; }
.bar-track { background: rgba(0,0,0,0.3); border-radius: 6px; height: 10px; overflow: hidden; }
.bar-fill { height: 100%; background: #3b82f6; border-radius: 6px; }
.bar-fill.flow { background: #10b981; }
.loading { padding: 4rem; text-align: center; color: #64748b; }
</style>
