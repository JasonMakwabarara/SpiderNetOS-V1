<template>
  <div class="providers-page">
    <button class="btn-back" @click="$router.push('/dashboard')">← Dashboard</button>
    <div class="page-header">
      <div>
        <h1>AI Providers</h1>
        <p>Platform AI providers. Keys are encrypted at rest and never returned.</p>
      </div>
      <button class="btn-create" @click="openCreate">+ New Provider</button>
    </div>

    <div v-if="forbidden" class="empty-state">
      <p>Super-admin access required to manage providers.</p>
    </div>

    <table v-else class="providers-table">
      <thead>
        <tr>
          <th>Name</th><th>Type</th><th>Scope</th><th>Chat model</th>
          <th>Embedding model</th><th>Priority</th><th>Enabled</th><th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in providers" :key="p.id">
          <td>{{ p.name }}</td>
          <td>{{ p.type }}</td>
          <td>{{ p.scope }}</td>
          <td>{{ p.chat_model || '—' }}</td>
          <td>{{ p.embedding_model || '—' }}</td>
          <td>{{ p.priority }}</td>
          <td><span :class="['badge', p.enabled ? 'on' : 'off']">{{ p.enabled ? 'enabled' : 'disabled' }}</span></td>
          <td class="row-actions">
            <button class="action-edit" @click="openEdit(p)">Edit</button>
            <button class="action-delete" @click="remove(p)">Delete</button>
          </td>
        </tr>
        <tr v-if="providers.length === 0">
          <td colspan="8" class="empty-row">No providers configured yet.</td>
        </tr>
      </tbody>
    </table>

    <div v-if="showModal" class="modal" @click.self="showModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ editing ? 'Edit Provider' : 'New Provider' }}</h2>
          <button class="modal-close" @click="showModal = false">✖</button>
        </div>
        <div class="modal-body">
          <div class="field-group">
            <label>Name</label>
            <input v-model="form.name" placeholder="e.g. OpenAI (platform)" />
          </div>
          <div class="field-group">
            <label>Type</label>
            <select v-model="form.type" @change="applyTypeDefaults">
              <option value="openai">OpenAI</option>
              <option value="deepseek">DeepSeek</option>
              <option value="ollama">Ollama (local)</option>
              <option value="azure_openai">Azure OpenAI</option>
              <option value="anthropic">Anthropic</option>
              <option value="custom_openai_compatible">Custom (OpenAI-compatible)</option>
            </select>
          </div>
          <div class="field-group">
            <label>Base URL <span class="hint">{{ baseUrlHint }}</span></label>
            <input v-model="form.base_url" :placeholder="baseUrlPlaceholder" />
            <p v-if="form.type === 'deepseek'" class="field-note">
              Leave blank for DeepSeek's official API. For the ByteDance international API
              (BytePlus / Volcengine ModelArk), set the full base, e.g.
              <code>https://ark.ap-southeast.bytepluses.com/api/v3</code>.
            </p>
          </div>
          <div class="field-group">
            <label>API Key <span class="hint">(write-only; leave blank to keep)</span></label>
            <input v-model="form.api_key" type="password" placeholder="••••••••" autocomplete="off" />
          </div>
          <div class="field-group">
            <label>Chat model</label>
            <input v-model="form.chat_model" :placeholder="chatModelPlaceholder" />
          </div>
          <div class="field-group">
            <label>Embedding model</label>
            <input v-model="form.embedding_model" placeholder="e.g. text-embedding-3-small" />
          </div>
          <div class="field-row">
            <div class="field-group">
              <label>Priority <span class="hint">(lower runs first)</span></label>
              <input v-model.number="form.priority" type="number" min="0" />
            </div>
            <div class="field-group">
              <label>Enabled</label>
              <select v-model="form.enabled">
                <option :value="true">Yes</option>
                <option :value="false">No</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="showModal = false">Cancel</button>
          <button class="btn-save" @click="save">{{ editing ? 'Save Changes' : 'Create' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '../../services/api'

const emptyForm = () => ({
  id: null,
  name: '',
  type: 'openai',
  base_url: '',
  api_key: '',
  chat_model: '',
  embedding_model: '',
  priority: 100,
  enabled: true,
  scope: 'platform',
})

const TYPE_DEFAULTS = {
  openai: { base: 'https://api.openai.com', chat: 'gpt-4o-mini', embedding: 'text-embedding-3-small' },
  deepseek: { base: 'https://api.deepseek.com', chat: 'deepseek-chat', embedding: '' },
  ollama: { base: 'http://localhost:11434', chat: 'llama3.1', embedding: 'nomic-embed-text' },
  azure_openai: { base: '', chat: '', embedding: '' },
  anthropic: { base: 'https://api.anthropic.com', chat: 'claude-3-5-sonnet-latest', embedding: '' },
  custom_openai_compatible: { base: '', chat: '', embedding: '' },
}

export default {
  data() {
    return {
      providers: [],
      showModal: false,
      editing: false,
      forbidden: false,
      form: emptyForm(),
    }
  },
  computed: {
    typeDefaults() {
      return TYPE_DEFAULTS[this.form.type] || TYPE_DEFAULTS.openai
    },
    baseUrlPlaceholder() {
      return this.typeDefaults.base || 'https://your-endpoint.example.com'
    },
    baseUrlHint() {
      return this.form.type === 'ollama' ? '(local server)' : '(optional; defaults per type)'
    },
    chatModelPlaceholder() {
      return this.typeDefaults.chat ? `e.g. ${this.typeDefaults.chat}` : 'model name'
    },
  },
  async mounted() {
    await this.fetchProviders()
  },
  methods: {
    applyTypeDefaults() {
      // Pre-fill blank model fields with sensible defaults when switching type.
      const d = this.typeDefaults
      if (!this.form.chat_model) this.form.chat_model = d.chat
      if (!this.form.embedding_model) this.form.embedding_model = d.embedding
    },
    async fetchProviders() {
      try {
        const res = await api.get('/admin/ai-providers')
        this.providers = res.data || []
      } catch (e) {
        if (e.response?.status === 403) this.forbidden = true
        else console.error(e)
      }
    },
    openCreate() {
      this.editing = false
      this.form = emptyForm()
      this.showModal = true
    },
    openEdit(p) {
      this.editing = true
      // api_key is never returned; leave blank to keep existing.
      this.form = { ...emptyForm(), ...p, api_key: '' }
      this.showModal = true
    },
    async save() {
      const payload = { ...this.form }
      if (!payload.api_key) delete payload.api_key
      try {
        if (this.editing) {
          await api.put(`/admin/ai-providers/${this.form.id}`, payload)
        } else {
          await api.post('/admin/ai-providers', payload)
        }
        this.showModal = false
        await this.fetchProviders()
      } catch (e) {
        alert(e.response?.data?.message || 'Error saving provider')
      }
    },
    async remove(p) {
      if (!confirm(`Delete provider "${p.name}"?`)) return
      await api.delete(`/admin/ai-providers/${p.id}`)
      await this.fetchProviders()
    },
  },
}
</script>

<style scoped>
.providers-page { padding: 2rem; max-width: 1200px; margin: 0 auto; min-height: 100vh; }
.btn-back { background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem; padding: 0; }
.btn-back:hover { text-decoration: underline; }
.field-note { font-size: 0.72rem; color: #64748b; margin-top: 0.4rem; line-height: 1.4; }
.field-note code { background: #f1f5f9; padding: 0.1rem 0.35rem; border-radius: 6px; font-size: 0.7rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem; }
.page-header h1 { font-size: 2rem; }
.page-header p { color: #64748b; font-size: 0.85rem; }
.btn-create { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 12px; cursor: pointer; font-weight: 600; }
.providers-table { width: 100%; border-collapse: collapse; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.providers-table th, .providers-table td { text-align: left; padding: 0.75rem 1rem; font-size: 0.85rem; border-bottom: 1px solid #f1f5f9; }
.providers-table th { background: #f8fafc; color: #475569; font-weight: 600; }
.badge { padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
.badge.on { background: #d1fae5; color: #065f46; }
.badge.off { background: #fee2e2; color: #991b1b; }
.row-actions { display: flex; gap: 0.5rem; }
.row-actions button { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.7rem; cursor: pointer; border: none; }
.action-edit { background: #f1f5f9; color: #334155; }
.action-delete { background: #fee2e2; color: #dc2626; }
.empty-row { text-align: center; color: #94a3b8; padding: 2rem; }
.empty-state { text-align: center; padding: 3rem; background: white; border-radius: 16px; color: #64748b; }
.modal { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
.modal-content { background: white; border-radius: 24px; width: 90%; max-width: 540px; overflow: hidden; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; }
.modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #94a3b8; }
.modal-body { padding: 0 1.5rem 1rem; max-height: 60vh; overflow-y: auto; }
.field-group { margin-bottom: 1rem; }
.field-row { display: flex; gap: 1rem; }
.field-row .field-group { flex: 1; }
.field-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.8rem; color: #334155; }
.field-group .hint { color: #94a3b8; font-weight: 400; }
.field-group input, .field-group select { width: 100%; padding: 0.6rem 0.9rem; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.85rem; }
.modal-footer { display: flex; gap: 1rem; justify-content: flex-end; padding: 1rem 1.5rem 1.5rem; }
.btn-cancel { background: #f1f5f9; border: none; padding: 0.6rem 1.2rem; border-radius: 40px; cursor: pointer; }
.btn-save { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 40px; cursor: pointer; font-weight: 600; }
</style>
