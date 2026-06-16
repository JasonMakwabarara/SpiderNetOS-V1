<template>
  <div class="flows-page">
    <div class="page-header"><div><h1>⚡ Workflow Flows</h1><p>Automate tasks with triggers and actions</p></div><button class="btn-create" @click="openCreateModal">+ New Flow</button></div>

    <div class="stats-container"><div class="stat-card"><div class="stat-icon purple">📋</div><div class="stat-info"><span class="stat-value">{{ flows.length }}</span><span class="stat-label">Total Flows</span></div></div><div class="stat-card"><div class="stat-icon green">▶️</div><div class="stat-info"><span class="stat-value">{{ totalExecutions }}</span><span class="stat-label">Executions</span></div></div></div>

    <div class="flows-grid"><div v-for="flow in flows" :key="flow.id" class="flow-card"><div class="flow-header"><div class="flow-icon">{{ flow.icon || '⚡' }}</div><div><h3>{{ flow.name }}</h3><span class="flow-trigger">{{ flow.trigger || 'manual' }}</span></div></div><p>{{ flow.description || 'No description' }}</p><div class="flow-stats">✅ {{ flow.executions || 0 }} executions</div><div class="flow-actions"><button class="action-execute" @click="executeFlow(flow.id)">▶ Execute</button><button class="action-edit" @click="editFlow(flow)">✎ Edit</button><button class="action-actions" @click="configureActions(flow)">⚙️ Actions</button><button class="action-delete" @click="deleteFlow(flow.id)">🗑 Delete</button></div></div><div v-if="flows.length === 0" class="empty-state"><span>⚡</span><p>No flows yet</p><button class="btn-outline" @click="openCreateModal">Create your first flow</button></div></div>

    <!-- Professional Create/Edit Modal -->
    <div v-if="showModal" class="modal" @click.self="showModal = false">
      <div class="modal-content">
        <div class="modal-header"><h2>{{ editing ? '✎ Edit Flow' : '✨ Create New Flow' }}</h2><button class="modal-close" @click="showModal = false">✖</button></div>
        <div class="modal-body">
          <div class="field-group"><label>Flow Name</label><input v-model="form.name" placeholder="e.g., Order Processing, Data Sync" /></div>
          <div class="field-group"><label>Description (optional)</label><textarea v-model="form.description" placeholder="What does this flow do?" rows="2"></textarea></div>
          <div class="field-group"><label>Trigger</label><input v-model="form.trigger" placeholder="manual / webhook / schedule" /></div>
        </div>
        <div class="modal-footer"><button class="btn-cancel" @click="showModal = false">Cancel</button><button class="btn-save" @click="saveFlow">{{ editing ? 'Save Changes' : 'Create Flow' }}</button></div>
      </div>
    </div>

    <!-- Actions Modal -->
    <div v-if="showActionsModal" class="modal" @click.self="showActionsModal = false"><div class="modal-content actions-modal"><div class="modal-header"><h2>⚙️ Configure Actions</h2><button class="modal-close" @click="showActionsModal = false">✖</button></div><div class="modal-body"><div v-for="(action, idx) in tempActions" :key="idx" class="action-item"><div class="action-header"><select v-model="action.type"><option value="email">📧 Email</option><option value="slack">💬 Slack</option><option value="webhook">🌐 Webhook</option></select><button class="remove-action" @click="removeAction(idx)">✖</button></div><div v-if="action.type === 'email'"><input v-model="action.to" placeholder="Recipient email" /><input v-model="action.subject" placeholder="Subject" /><textarea v-model="action.body" placeholder="Email body" rows="2"></textarea></div><div v-if="action.type === 'slack'"><input v-model="action.webhook" placeholder="Slack webhook URL" /><textarea v-model="action.message" placeholder="Message" rows="2"></textarea></div><div v-if="action.type === 'webhook'"><input v-model="action.url" placeholder="Webhook URL" /><textarea v-model="action.data" placeholder='JSON data (optional)' rows="2"></textarea></div></div><button class="btn-add-action" @click="addAction">+ Add Action</button></div><div class="modal-footer"><button class="btn-cancel" @click="showActionsModal = false">Cancel</button><button class="btn-save" @click="saveActions">Save Actions</button></div></div></div>

    <!-- Execute Result Modal -->
    <div v-if="showExecuteModal" class="modal" @click.self="showExecuteModal = false"><div class="modal-content result-modal"><div class="modal-header"><h2>✅ Execution Result</h2><button class="modal-close" @click="showExecuteModal = false">✖</button></div><div class="modal-body"><div class="result-message">{{ executeResult }}</div></div><div class="modal-footer"><button class="btn-save" @click="showExecuteModal = false">Close</button></div></div></div>
  </div>
</template>

<script>
import api from '../services/api'
export default {
  data() {
    return {
      flows: [], showModal: false, editing: false, form: { id: null, name: '', description: '', trigger: 'manual', icon: '⚡' },
      showActionsModal: false, currentFlow: {}, tempActions: [], showExecuteModal: false, executeResult: ''
    }
  },
  computed: { totalExecutions() { return this.flows.reduce((s, f) => s + (f.executions || 0), 0) } },
  async mounted() { await this.fetchFlows() },
  methods: {
    async fetchFlows() { try { const res = await api.get('/flows'); this.flows = res.data } catch(e) { console.error(e) } },
    openCreateModal() { this.editing = false; this.form = { id: null, name: '', description: '', trigger: 'manual', icon: '⚡' }; this.showModal = true },
    editFlow(flow) { this.editing = true; this.form = { ...flow }; this.showModal = true },
    async saveFlow() { try { if (this.editing) { await api.put(`/flows/${this.form.id}`, this.form) } else { await api.post('/flows', this.form) } this.showModal = false; await this.fetchFlows() } catch(e) { alert('Error saving flow') } },
    async deleteFlow(id) { if (!confirm('Delete this flow?')) return; await api.delete(`/flows/${id}`); await this.fetchFlows() },
    async executeFlow(id) { try { const res = await api.post(`/flows/${id}/execute`); this.executeResult = res.data.message || 'Flow executed'; this.showExecuteModal = true; await this.fetchFlows() } catch(e) { alert('Error executing flow') } },
    configureActions(flow) { this.currentFlow = flow; this.tempActions = (flow.config && flow.config.actions) ? [...flow.config.actions] : []; this.showActionsModal = true },
    addAction() { this.tempActions.push({ type: 'email', to: '', subject: '', body: '' }) },
    removeAction(idx) { this.tempActions.splice(idx, 1) },
    async saveActions() { try { const updatedFlow = { ...this.currentFlow, config: { actions: this.tempActions } }; await api.put(`/flows/${this.currentFlow.id}`, updatedFlow); this.showActionsModal = false; await this.fetchFlows(); alert('Actions saved!') } catch(e) { alert('Error saving actions') } }
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
.flows-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.5rem; }
.flow-card { background: white; border-radius: 24px; padding: 1.5rem; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.flow-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.1); }
.flow-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.flow-icon { font-size: 2rem; }
.flow-trigger { font-size: 0.65rem; background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 20px; }
.flow-stats { background: #f8fafc; padding: 0.4rem; border-radius: 12px; text-align: center; margin: 0.5rem 0; font-size: 0.7rem; }
.flow-actions { display: flex; gap: 0.4rem; justify-content: flex-end; flex-wrap: wrap; }
.flow-actions button { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.7rem; cursor: pointer; border: none; font-weight: 500; }
.action-execute { background: #3b82f6; color: white; }
.action-edit { background: #f1f5f9; color: #334155; }
.action-actions { background: #8b5cf6; color: white; }
.action-delete { background: #fee2e2; color: #dc2626; }
.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
.modal-content { background: white; border-radius: 32px; width: 90%; max-width: 540px; overflow: hidden; animation: fadeIn 0.2s ease; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 1.5rem 0 1.5rem; }
.modal-header h2 { font-size: 1.3rem; font-weight: 600; }
.modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #94a3b8; transition: color 0.2s; }
.modal-close:hover { color: #ef4444; }
.modal-body { padding: 1.5rem; }
.field-group { margin-bottom: 1.25rem; }
.field-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.85rem; color: #334155; }
.field-group input, .field-group textarea { width: 100%; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 0.9rem; }
.field-group input:focus, .field-group textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.modal-footer { display: flex; gap: 1rem; justify-content: flex-end; padding: 0 1.5rem 1.5rem 1.5rem; }
.btn-cancel { background: #f1f5f9; border: none; padding: 0.6rem 1.2rem; border-radius: 40px; cursor: pointer; font-weight: 500; }
.btn-save { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 40px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
.btn-save:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
.action-item { background: #f8fafc; border-radius: 20px; padding: 1rem; margin-bottom: 1rem; }
.action-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; }
.action-header select { padding: 0.4rem; border-radius: 12px; border: 1px solid #e2e8f0; }
.remove-action { background: #fee2e2; color: #dc2626; border: none; border-radius: 20px; padding: 0.2rem 0.6rem; cursor: pointer; }
.btn-add-action { background: #e2e8f0; border: none; padding: 0.5rem 1rem; border-radius: 30px; cursor: pointer; width: 100%; margin-top: 0.5rem; font-weight: 500; }
.result-message { background: #f1f5f9; padding: 1.5rem; border-radius: 20px; text-align: center; font-weight: 500; }
.empty-state { text-align: center; padding: 3rem; background: white; border-radius: 28px; }
.empty-state span { font-size: 4rem; display: block; margin-bottom: 1rem; }
.btn-outline { background: transparent; border: 1px solid #3b82f6; color: #3b82f6; padding: 0.5rem 1rem; border-radius: 30px; cursor: pointer; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
@media (min-width: 2560px) { .flows-page { padding: 3rem; max-width: 1800px; } }
</style>