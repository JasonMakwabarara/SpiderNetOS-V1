<template>
  <div class="flows-page">
    <div class="page-header">
      <div><h1>⚡ Workflow Flows</h1><p>Automate tasks with triggers, actions, and visual DAGs</p></div>
      <button class="btn-create" @click="openCreateModal">+ New Flow</button>
    </div>

    <div class="stats-container">
      <div class="stat-card"><div class="stat-icon purple">📋</div><div class="stat-info"><span class="stat-value">{{ flows.length }}</span><span class="stat-label">Total Flows</span></div></div>
      <div class="stat-card"><div class="stat-icon green">▶️</div><div class="stat-info"><span class="stat-value">{{ totalExecutions }}</span><span class="stat-label">Executions</span></div></div>
    </div>

    <div class="flows-grid">
      <div v-for="flow in flows" :key="flow.id" class="flow-card">
        <div class="flow-header">
          <div class="flow-icon">{{ flow.icon || '⚡' }}</div>
          <div>
            <h3>{{ flow.name }}</h3>
            <span class="flow-trigger">{{ triggerLabel(flow) }}</span>
            <span v-if="flow.graph && flow.graph.nodes && flow.graph.nodes.length" class="flow-badge">DAG · {{ flow.graph.nodes.length }} nodes</span>
          </div>
        </div>
        <p>{{ flow.description || 'No description' }}</p>

        <div v-if="flow.webhook_url" class="webhook-box">
          <span class="webhook-label">Webhook URL</span>
          <div class="webhook-row">
            <code>{{ flow.webhook_url }}</code>
            <button class="copy-btn" @click="copy(flow.webhook_url)">Copy</button>
          </div>
        </div>

        <div class="flow-stats">✅ {{ flow.executions || 0 }} executions</div>
        <div class="flow-actions">
          <button class="action-execute" @click="executeFlow(flow.id)">▶ Run</button>
          <button class="action-builder" @click="openBuilder(flow)">🎨 Builder</button>
          <button class="action-runs" @click="openRuns(flow)">📜 Runs</button>
          <button class="action-edit" @click="editFlow(flow)">✎ Edit</button>
          <button class="action-delete" @click="deleteFlow(flow.id)">🗑</button>
        </div>
      </div>
      <div v-if="flows.length === 0" class="empty-state"><span>⚡</span><p>No flows yet</p><button class="btn-outline" @click="openCreateModal">Create your first flow</button></div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="modal" @click.self="showModal = false">
      <div class="modal-content">
        <div class="modal-header"><h2>{{ editing ? '✎ Edit Flow' : '✨ Create New Flow' }}</h2><button class="modal-close" @click="showModal = false">✖</button></div>
        <div class="modal-body">
          <div class="field-group"><label>Flow Name</label><input v-model="form.name" placeholder="e.g., New Lead Welcome" /></div>
          <div class="field-group"><label>Description (optional)</label><textarea v-model="form.description" placeholder="What does this flow do?" rows="2"></textarea></div>
          <div class="field-group">
            <label>Trigger</label>
            <select v-model="form.trigger">
              <option value="manual">Manual (Run button)</option>
              <option value="webhook">Webhook (external HTTP call)</option>
              <option value="record-event">Record event (create/update/delete)</option>
              <option value="schedule">Schedule (cron)</option>
            </select>
          </div>

          <div v-if="form.trigger === 'record-event'" class="trigger-config">
            <div class="field-group">
              <label>Object</label>
              <select v-model="form.trigger_config.object">
                <option value="">—</option>
                <option v-for="o in objects" :key="o.id" :value="o.slug">{{ o.name }}</option>
              </select>
            </div>
            <div class="field-group">
              <label>Events</label>
              <div class="checks">
                <label class="check"><input type="checkbox" value="created" v-model="form.trigger_config.events" /> created</label>
                <label class="check"><input type="checkbox" value="updated" v-model="form.trigger_config.events" /> updated</label>
                <label class="check"><input type="checkbox" value="deleted" v-model="form.trigger_config.events" /> deleted</label>
              </div>
            </div>
          </div>

          <div v-if="form.trigger === 'schedule'" class="trigger-config">
            <div class="field-group">
              <label>Cron expression</label>
              <input v-model="form.trigger_config.cron" placeholder="*/5 * * * *  (every 5 minutes)" />
              <p class="hint">Standard 5-field cron. Examples: <code>0 9 * * *</code> daily 9am · <code>*/15 * * * *</code> every 15 min</p>
            </div>
          </div>

          <div v-if="form.trigger === 'webhook'" class="trigger-config">
            <p class="hint">A unique webhook URL is generated on save. External services POST to it to trigger this flow; the JSON body becomes the run context.</p>
          </div>
        </div>
        <div class="modal-footer"><button class="btn-cancel" @click="showModal = false">Cancel</button><button class="btn-save" @click="saveFlow">{{ editing ? 'Save Changes' : 'Create Flow' }}</button></div>
      </div>
    </div>

    <!-- Runs Modal -->
    <div v-if="showRunsModal" class="modal" @click.self="showRunsModal = false">
      <div class="modal-content result-modal">
        <div class="modal-header"><h2>📜 {{ runsFlow.name }} · Runs</h2><button class="modal-close" @click="showRunsModal = false">✖</button></div>
        <div class="modal-body runs-body">
          <div v-if="runs.length === 0" class="empty-runs">No runs yet. Trigger the flow to see history.</div>
          <div v-for="run in runs" :key="run.id" class="run-item">
            <div class="run-head" @click="run._open = !run._open">
              <span :class="['run-status', run.status]">{{ run.status }}</span>
              <span class="run-trigger">{{ run.trigger }}</span>
              <span class="run-time">{{ formatTime(run.created_at) }}</span>
              <span class="run-toggle">{{ run._open ? '▲' : '▼' }}</span>
            </div>
            <div v-if="run._open" class="run-steps">
              <div v-for="(s, i) in (run.steps || [])" :key="i" :class="['step', s.status]">
                <span class="step-status">{{ s.status }}</span>
                <span class="step-type">{{ s.type }}</span>
                <span class="step-msg">{{ s.message }}</span>
              </div>
              <div v-if="run.error" class="step failed"><span class="step-msg">Error: {{ run.error }}</span></div>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn-save" @click="showRunsModal = false">Close</button></div>
      </div>
    </div>

    <!-- Execute Result Modal -->
    <div v-if="showExecuteModal" class="modal" @click.self="showExecuteModal = false">
      <div class="modal-content result-modal">
        <div class="modal-header"><h2>✅ Execution Result</h2><button class="modal-close" @click="showExecuteModal = false">✖</button></div>
        <div class="modal-body">
          <div class="result-message">{{ executeResult }}</div>
          <div v-if="executeSteps.length" class="run-steps">
            <div v-for="(s, i) in executeSteps" :key="i" :class="['step', s.status]">
              <span class="step-status">{{ s.status }}</span><span class="step-type">{{ s.type }}</span><span class="step-msg">{{ s.message }}</span>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn-save" @click="showExecuteModal = false">Close</button></div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '../services/api'
export default {
  data() {
    return {
      flows: [], objects: [],
      showModal: false, editing: false,
      form: this.emptyForm(),
      showRunsModal: false, runsFlow: {}, runs: [],
      showExecuteModal: false, executeResult: '', executeSteps: []
    }
  },
  computed: { totalExecutions() { return this.flows.reduce((s, f) => s + (f.executions || 0), 0) } },
  async mounted() { await this.fetchFlows(); await this.fetchObjects() },
  methods: {
    emptyForm() { return { id: null, name: '', description: '', trigger: 'manual', icon: '⚡', trigger_config: { object: '', events: ['created'], cron: '' } } },
    triggerLabel(flow) {
      const map = { manual: 'manual', webhook: 'webhook', 'record-event': 'on record event', schedule: 'scheduled' }
      return map[flow.trigger] || flow.trigger || 'manual'
    },
    async fetchFlows() { try { const res = await api.get('/flows'); this.flows = res.data } catch(e) { console.error(e) } },
    async fetchObjects() { try { const res = await api.get('/objects'); this.objects = res.data || [] } catch(e) { /* ignore */ } },
    openCreateModal() { this.editing = false; this.form = this.emptyForm(); this.showModal = true },
    editFlow(flow) {
      this.editing = true
      this.form = {
        id: flow.id, name: flow.name, description: flow.description, trigger: flow.trigger || 'manual', icon: flow.icon || '⚡',
        trigger_config: {
          object: flow.trigger_config?.object || '',
          events: flow.trigger_config?.events || ['created'],
          cron: flow.trigger_config?.cron || ''
        }
      }
      this.showModal = true
    },
    buildTriggerConfig() {
      if (this.form.trigger === 'record-event') return { object: this.form.trigger_config.object, events: this.form.trigger_config.events }
      if (this.form.trigger === 'schedule') return { cron: this.form.trigger_config.cron }
      return {}
    },
    async saveFlow() {
      const payload = { name: this.form.name, description: this.form.description, trigger: this.form.trigger, trigger_config: this.buildTriggerConfig() }
      try {
        if (this.editing) { await api.put(`/flows/${this.form.id}`, payload) }
        else { await api.post('/flows', payload) }
        this.showModal = false
        await this.fetchFlows()
      } catch(e) { alert(e.response?.data?.message || 'Error saving flow') }
    },
    async deleteFlow(id) { if (!confirm('Delete this flow?')) return; await api.delete(`/flows/${id}`); await this.fetchFlows() },
    async executeFlow(id) {
      try {
        const res = await api.post(`/flows/${id}/execute`)
        this.executeResult = res.data.message || 'Flow executed'
        this.executeSteps = res.data.run?.steps || []
        this.showExecuteModal = true
        await this.fetchFlows()
      } catch(e) { alert('Error executing flow') }
    },
    openBuilder(flow) { this.$router.push(`/flows/${flow.id}/builder`) },
    async openRuns(flow) {
      this.runsFlow = flow
      this.runs = []
      this.showRunsModal = true
      try { const res = await api.get(`/flows/${flow.id}/runs`); this.runs = (res.data || []).map(r => ({ ...r, _open: false })) }
      catch(e) { console.error(e) }
    },
    formatTime(t) { return t ? new Date(t).toLocaleString() : '' },
    async copy(text) { try { await navigator.clipboard.writeText(text); } catch(e) {} }
  }
}
</script>

<style scoped>
.flows-page { padding: 2rem; max-width: 1400px; margin: 0 auto; background: linear-gradient(135deg, #f5f7fa 0%, #eef2f6 100%); min-height: 100vh; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
.page-header h1 { font-size: 2rem; background: linear-gradient(135deg, #1e293b, #3b82f6); -webkit-background-clip: text; background-clip: text; color: transparent; }
.btn-create { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 12px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
.btn-create:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
.stats-container { display: flex; gap: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; }
.stat-card { background: white; border-radius: 20px; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); flex: 1; }
.stat-icon { width: 48px; height: 48px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
.stat-value { font-size: 1.5rem; font-weight: 700; }
.flows-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.5rem; }
.flow-card { background: white; border-radius: 24px; padding: 1.5rem; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.flow-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.1); }
.flow-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.flow-icon { font-size: 2rem; }
.flow-trigger { font-size: 0.65rem; background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 20px; margin-right: 0.3rem; }
.flow-badge { font-size: 0.65rem; background: #ede9fe; color: #6d28d9; padding: 0.2rem 0.5rem; border-radius: 20px; }
.webhook-box { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 0.6rem; margin: 0.5rem 0; }
.webhook-label { font-size: 0.65rem; color: #64748b; text-transform: uppercase; font-weight: 600; }
.webhook-row { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem; }
.webhook-row code { flex: 1; font-size: 0.65rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #334155; }
.copy-btn { background: #e2e8f0; border: none; border-radius: 8px; padding: 0.2rem 0.6rem; cursor: pointer; font-size: 0.7rem; }
.flow-stats { background: #f8fafc; padding: 0.4rem; border-radius: 12px; text-align: center; margin: 0.5rem 0; font-size: 0.7rem; }
.flow-actions { display: flex; gap: 0.4rem; justify-content: flex-end; flex-wrap: wrap; }
.flow-actions button { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.7rem; cursor: pointer; border: none; font-weight: 500; }
.action-execute { background: #3b82f6; color: white; }
.action-builder { background: #ede9fe; color: #6d28d9; }
.action-runs { background: #f1f5f9; color: #334155; }
.action-edit { background: #f1f5f9; color: #334155; }
.action-delete { background: #fee2e2; color: #dc2626; }
.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
.modal-content { background: white; border-radius: 32px; width: 90%; max-width: 560px; max-height: 88vh; overflow-y: auto; animation: fadeIn 0.2s ease; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 1.5rem 0 1.5rem; }
.modal-header h2 { font-size: 1.3rem; font-weight: 600; }
.modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #94a3b8; }
.modal-body { padding: 1.5rem; }
.field-group { margin-bottom: 1.25rem; }
.field-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.85rem; color: #334155; }
.field-group input, .field-group textarea, .field-group select { width: 100%; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 0.9rem; }
.trigger-config { background: #f8fafc; border-radius: 16px; padding: 1rem; margin-top: -0.5rem; }
.trigger-config .field-group:last-child { margin-bottom: 0; }
.checks { display: flex; gap: 1rem; }
.check { font-weight: 400; font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem; }
.hint { font-size: 0.72rem; color: #94a3b8; line-height: 1.5; }
.hint code { background: #eef2f7; padding: 0.05rem 0.3rem; border-radius: 6px; }
.modal-footer { display: flex; gap: 1rem; justify-content: flex-end; padding: 0 1.5rem 1.5rem 1.5rem; }
.btn-cancel { background: #f1f5f9; border: none; padding: 0.6rem 1.2rem; border-radius: 40px; cursor: pointer; font-weight: 500; }
.btn-save { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 40px; cursor: pointer; font-weight: 600; }
.result-message { background: #f1f5f9; padding: 1.5rem; border-radius: 20px; text-align: center; font-weight: 500; }
.runs-body { max-height: 60vh; }
.empty-runs { text-align: center; color: #94a3b8; padding: 2rem; }
.run-item { border: 1px solid #f1f5f9; border-radius: 14px; margin-bottom: 0.6rem; overflow: hidden; }
.run-head { display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.9rem; cursor: pointer; background: #f8fafc; }
.run-status { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; padding: 0.15rem 0.5rem; border-radius: 20px; }
.run-status.success { background: #d1fae5; color: #065f46; }
.run-status.failed { background: #fee2e2; color: #991b1b; }
.run-status.running { background: #fef3c7; color: #92400e; }
.run-trigger { font-size: 0.75rem; color: #475569; }
.run-time { font-size: 0.7rem; color: #94a3b8; margin-left: auto; }
.run-toggle { font-size: 0.7rem; color: #94a3b8; }
.run-steps { padding: 0.5rem 0.9rem; }
.step { display: flex; gap: 0.6rem; align-items: baseline; padding: 0.25rem 0; font-size: 0.78rem; border-bottom: 1px solid #f8fafc; }
.step-status { font-size: 0.6rem; text-transform: uppercase; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 8px; background: #e2e8f0; color: #475569; }
.step.ok .step-status { background: #d1fae5; color: #065f46; }
.step.skipped .step-status { background: #f1f5f9; color: #94a3b8; }
.step.failed .step-status { background: #fee2e2; color: #991b1b; }
.step-type { font-weight: 600; color: #334155; }
.step-msg { color: #64748b; }
.empty-state { text-align: center; padding: 3rem; background: white; border-radius: 28px; }
.empty-state span { font-size: 4rem; display: block; margin-bottom: 1rem; }
.btn-outline { background: transparent; border: 1px solid #3b82f6; color: #3b82f6; padding: 0.5rem 1rem; border-radius: 30px; cursor: pointer; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
@media (min-width: 2560px) { .flows-page { padding: 3rem; max-width: 1800px; } }
</style>
