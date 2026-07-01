<template>
  <div class="insights-page">
    <div class="page-header">
      <div>
        <h1>📊 Insights</h1>
        <p>Predictive analytics &amp; pipeline reporting</p>
      </div>
      <router-link class="btn-outline" to="/dashboard">← Dashboard</router-link>
    </div>

    <div v-if="loading" class="loading">Crunching numbers…</div>

    <template v-else>
      <!-- KPI cards -->
      <div class="kpis">
        <div class="kpi">
          <span class="kpi-label">Total Records</span>
          <span class="kpi-value">{{ summary.totals?.records ?? 0 }}</span>
        </div>
        <div class="kpi">
          <span class="kpi-label">Expected Pipeline</span>
          <span class="kpi-value">{{ money(forecast.pipeline?.expected_total) }}</span>
          <span class="kpi-sub">won {{ money(forecast.pipeline?.won_value) }} + weighted {{ money(forecast.pipeline?.weighted_open) }}</span>
        </div>
        <div class="kpi">
          <span class="kpi-label">Win Rate</span>
          <span class="kpi-value">{{ pct(forecast.conversion?.win_rate) }}</span>
          <span class="kpi-sub">{{ forecast.conversion?.won ?? 0 }} won / {{ forecast.conversion?.lost ?? 0 }} lost</span>
        </div>
        <div class="kpi">
          <span class="kpi-label">Conversion</span>
          <span class="kpi-value">{{ pct(forecast.conversion?.conversion_rate) }}</span>
          <span class="kpi-sub">of {{ forecast.conversion?.total ?? 0 }} deals</span>
        </div>
        <div class="kpi">
          <span class="kpi-label">Flow Runs</span>
          <span class="kpi-value">{{ summary.totals?.flow_runs ?? 0 }}</span>
          <span class="kpi-sub">{{ summary.flow_runs_by_status?.success ?? 0 }} ok · {{ summary.flow_runs_by_status?.failed ?? 0 }} failed</span>
        </div>
      </div>

      <div class="grid">
        <!-- Revenue forecast line chart -->
        <div class="card wide">
          <div class="card-head">
            <h3>Revenue forecast</h3>
            <span :class="['trend', forecast.revenue?.trend]">
              {{ trendIcon(forecast.revenue?.trend) }} {{ money(forecast.revenue?.monthly_growth) }}/mo · R² {{ forecast.revenue?.confidence ?? 0 }}
            </span>
          </div>
          <svg v-if="line.points.length" class="chart" :viewBox="`0 0 ${line.w} ${line.h}`" preserveAspectRatio="none">
            <line v-for="(g, i) in line.gridY" :key="'g'+i" :x1="line.padX" :x2="line.w - 8" :y1="g.y" :y2="g.y" class="gridline" />
            <polyline :points="line.historyPath" class="line-hist" fill="none" />
            <polyline :points="line.projPath" class="line-proj" fill="none" />
            <circle v-for="(p, i) in line.points" :key="'p'+i" :cx="p.x" :cy="p.y" :r="p.projected ? 3.5 : 3" :class="['dot', { proj: p.projected }]" />
          </svg>
          <div v-else class="chart-empty">No revenue history yet.</div>
          <div class="x-labels">
            <span v-for="(p, i) in line.points" :key="'l'+i" :class="{ proj: p.projected }">{{ shortMonth(p.label) }}</span>
          </div>
        </div>

        <!-- Pipeline value by stage -->
        <div class="card">
          <div class="card-head"><h3>Pipeline value by stage</h3></div>
          <div class="bars">
            <div v-for="b in stageValueBars" :key="b.label" class="bar-row">
              <span class="bar-label">{{ b.label }}</span>
              <div class="bar-track"><div class="bar-fill" :style="{ width: b.pct + '%', background: b.color }"></div></div>
              <span class="bar-val">{{ money(b.value) }}</span>
            </div>
            <div v-if="!stageValueBars.length" class="chart-empty">No deals yet.</div>
          </div>
        </div>

        <!-- Deal count by stage -->
        <div class="card">
          <div class="card-head"><h3>Deals by stage</h3></div>
          <div class="bars">
            <div v-for="b in stageCountBars" :key="b.label" class="bar-row">
              <span class="bar-label">{{ b.label }}</span>
              <div class="bar-track"><div class="bar-fill" :style="{ width: b.pct + '%', background: b.color }"></div></div>
              <span class="bar-val">{{ b.value }}</span>
            </div>
            <div v-if="!stageCountBars.length" class="chart-empty">No deals yet.</div>
          </div>
        </div>

        <!-- Records by object -->
        <div class="card">
          <div class="card-head"><h3>Records by object</h3></div>
          <div class="bars">
            <div v-for="b in objectBars" :key="b.label" class="bar-row">
              <span class="bar-label">{{ b.label }}</span>
              <div class="bar-track"><div class="bar-fill" :style="{ width: b.pct + '%', background: b.color }"></div></div>
              <span class="bar-val">{{ b.value }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import api from '../services/api'

const PALETTE = ['#3b82f6', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#64748b']

export default {
  data() {
    return {
      loading: true,
      summary: {},
      forecast: {},
      stageValue: [],
      stageCount: [],
    }
  },
  computed: {
    objectBars() { return this.toBars((this.summary.objects || []).map(o => ({ label: o.name, value: o.records }))) },
    stageValueBars() { return this.toBars(this.stageValue.map(b => ({ label: b.label, value: b.value }))) },
    stageCountBars() { return this.toBars(this.stageCount.map(b => ({ label: b.label, value: b.count }))) },
    line() {
      const hist = this.forecast.revenue?.history || []
      const proj = this.forecast.revenue?.projection || []
      const all = [...hist.map(p => ({ ...p, projected: false })), ...proj.map(p => ({ ...p, projected: true }))]
      const w = 600, h = 220, padX = 40, padY = 20
      if (!all.length) return { points: [], w, h, padX, gridY: [], historyPath: '', projPath: '' }
      const max = Math.max(1, ...all.map(p => p.value))
      const stepX = (w - padX - 8) / Math.max(1, all.length - 1)
      const points = all.map((p, i) => ({
        ...p,
        x: padX + i * stepX,
        y: h - padY - (p.value / max) * (h - padY * 2)
      }))
      const histPts = points.filter(p => !p.projected)
      // Projection line starts at the last historical point for continuity.
      const lastHist = histPts[histPts.length - 1]
      const projPts = [lastHist, ...points.filter(p => p.projected)].filter(Boolean)
      const gridY = [0, 0.5, 1].map(f => ({ y: h - padY - f * (h - padY * 2) }))
      return {
        points, w, h, padX, gridY,
        historyPath: histPts.map(p => `${p.x},${p.y}`).join(' '),
        projPath: projPts.map(p => `${p.x},${p.y}`).join(' ')
      }
    }
  },
  async mounted() { await this.load() },
  methods: {
    async load() {
      this.loading = true
      try {
        const [summary, forecast] = await Promise.all([
          api.get('/reports/summary'),
          api.get('/reports/forecast', { params: { object: 'deals', periods: 3 } })
        ])
        this.summary = summary.data
        this.forecast = forecast.data || {}
        // Stage breakdowns (best-effort; deals object may not exist).
        try {
          const [val, cnt] = await Promise.all([
            api.get('/reports/objects/deals/aggregate', { params: { by: 'stage', metric: 'sum', field: 'value' } }),
            api.get('/reports/objects/deals/group', { params: { by: 'stage' } })
          ])
          this.stageValue = val.data.buckets || []
          this.stageCount = cnt.data.buckets || []
        } catch (e) { /* no deals object */ }
      } catch (e) { console.error(e) } finally { this.loading = false }
    },
    toBars(items) {
      const max = Math.max(1, ...items.map(i => i.value || 0))
      return items.map((i, idx) => ({ ...i, pct: Math.round(((i.value || 0) / max) * 100), color: PALETTE[idx % PALETTE.length] }))
    },
    money(v) {
      const n = Number(v || 0)
      return '$' + (Math.abs(n) >= 1000 ? (n / 1000).toFixed(1) + 'k' : n.toFixed(0))
    },
    pct(v) { return Math.round((Number(v) || 0) * 100) + '%' },
    trendIcon(t) { return t === 'up' ? '↗' : t === 'down' ? '↘' : '→' },
    shortMonth(m) { if (!m) return ''; const [, mo] = m.split('-'); return ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][parseInt(mo)] || m }
  }
}
</script>

<style scoped>
.insights-page { padding: 2rem; max-width: 1400px; margin: 0 auto; background: linear-gradient(135deg, #f5f7fa 0%, #eef2f6 100%); min-height: 100vh; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
.page-header h1 { font-size: 2rem; background: linear-gradient(135deg, #1e293b, #3b82f6); -webkit-background-clip: text; background-clip: text; color: transparent; }
.page-header p { color: #64748b; font-size: 0.9rem; }
.btn-outline { background: transparent; border: 1px solid #3b82f6; color: #3b82f6; padding: 0.5rem 1rem; border-radius: 12px; cursor: pointer; font-weight: 500; text-decoration: none; }
.loading { padding: 4rem; text-align: center; color: #64748b; }
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.kpi { background: white; border-radius: 18px; padding: 1.1rem 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; flex-direction: column; gap: 0.2rem; }
.kpi-label { font-size: 0.7rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; letter-spacing: 0.04em; }
.kpi-value { font-size: 1.6rem; font-weight: 700; color: #1e293b; }
.kpi-sub { font-size: 0.7rem; color: #94a3b8; }
.grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; }
.card { background: white; border-radius: 20px; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.card.wide { grid-column: 1 / -1; }
.card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.card-head h3 { font-size: 1rem; color: #1e293b; }
.trend { font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 20px; background: #f1f5f9; color: #475569; }
.trend.up { background: #d1fae5; color: #065f46; }
.trend.down { background: #fee2e2; color: #991b1b; }
.chart { width: 100%; height: 220px; }
.gridline { stroke: #f1f5f9; stroke-width: 1; }
.line-hist { stroke: #3b82f6; stroke-width: 2.5; }
.line-proj { stroke: #8b5cf6; stroke-width: 2.5; stroke-dasharray: 5 4; }
.dot { fill: #3b82f6; }
.dot.proj { fill: #8b5cf6; }
.x-labels { display: flex; justify-content: space-between; margin-top: 0.4rem; padding: 0 0.5rem; }
.x-labels span { font-size: 0.65rem; color: #94a3b8; }
.x-labels span.proj { color: #8b5cf6; font-weight: 600; }
.chart-empty { padding: 2rem; text-align: center; color: #94a3b8; font-size: 0.85rem; }
.bars { display: flex; flex-direction: column; gap: 0.6rem; }
.bar-row { display: grid; grid-template-columns: 90px 1fr 70px; align-items: center; gap: 0.6rem; }
.bar-label { font-size: 0.78rem; color: #475569; text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bar-track { background: #f1f5f9; border-radius: 8px; height: 16px; overflow: hidden; }
.bar-fill { height: 100%; border-radius: 8px; transition: width 0.4s ease; min-width: 2px; }
.bar-val { font-size: 0.78rem; color: #1e293b; font-weight: 600; }
@media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
</style>
