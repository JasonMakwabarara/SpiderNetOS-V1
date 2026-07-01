<template>
  <div class="integrations-page">
    <div class="page-header">
      <div>
        <h1>Integration Hub</h1>
        <p>Connect Slack, email, WhatsApp, and CRMs to your workspace</p>
      </div>
      <button class="btn-create" @click="openCreate">+ Connect</button>
    </div>

    <p v-if="toast" :class="['toast', toast.type]">{{ toast.text }}</p>

    <div v-if="loading" class="loading">Loading integrations…</div>

    <div v-else class="grid">
      <div v-for="item in integrations" :key="item.id" class="card">
        <div class="card-head">
          <span class="icon">{{ typeIcon(item.type) }}</span>
          <span :class="['status', item.status]">{{ item.status }}</span>
        </div>
        <h3>{{ item.name }}</h3>
        <p class="type">{{ item.type }}</p>
        <p v-if="item.inbound_url" class="inbound-url">
          Inbound: <code>{{ item.inbound_url }}</code>
          <button class="link-btn" type="button" @click="copy(item.inbound_url)">Copy</button>
        </p>
        <p v-if="item.oauth_connected" class="oauth-badge">OAuth connected</p>
        <p v-if="item.last_error" class="error">{{ item.last_error }}</p>
        <div class="actions">
          <button @click="test(item)">Test</button>
          <button v-if="canSend(item.type)" @click="openSend(item)">Send test</button>
          <button v-if="isCrm(item.type)" @click="sync(item)">Sync</button>
          <button @click="openEdit(item)">Edit</button>
          <button class="danger" @click="remove(item)">Delete</button>
        </div>
      </div>
      <div v-if="!integrations.length" class="empty">No integrations yet. Connect your first channel.</div>
    </div>

    <div v-if="showModal" class="modal" @click.self="closeModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ editingId ? 'Edit integration' : 'Connect integration' }}</h2>
          <button class="modal-close" type="button" @click="closeModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="field-group">
            <label>Name</label>
            <input v-model="form.name" placeholder="e.g. Team Slack" />
          </div>
          <div v-if="!editingId" class="field-group">
            <label>Type</label>
            <select v-model="form.type">
              <option value="slack">Slack</option>
              <option value="email">Email (SMTP)</option>
              <option value="whatsapp">WhatsApp Cloud</option>
              <option value="hubspot">HubSpot CRM</option>
              <option value="salesforce">Salesforce</option>
            </select>
          </div>
          <template v-if="form.type === 'slack'">
            <div class="field-group"><label>Webhook URL</label><input v-model="form.credentials.webhook_url" type="password" placeholder="Leave blank to keep existing" /></div>
          </template>
          <template v-else-if="form.type === 'email'">
            <div class="field-group"><label>From address</label><input v-model="form.credentials.from" /></div>
            <div class="field-group"><label>SMTP host</label><input v-model="form.credentials.host" placeholder="smtp.example.com" /></div>
            <div class="field-group"><label>SMTP port</label><input v-model="form.credentials.port" type="number" placeholder="587" /></div>
            <div class="field-group"><label>SMTP username</label><input v-model="form.credentials.username" /></div>
            <div class="field-group"><label>SMTP password</label><input v-model="form.credentials.password" type="password" placeholder="Leave blank to keep existing" /></div>
            <p class="oauth-hint">Configure your provider to POST inbound mail to the webhook URL on the card.</p>
          </template>
          <template v-else-if="form.type === 'whatsapp'">
            <div class="field-group"><label>Phone number ID</label><input v-model="form.credentials.phone_number_id" /></div>
            <div class="field-group"><label>Access token</label><input v-model="form.credentials.access_token" type="password" placeholder="Leave blank to keep existing" /></div>
          </template>
          <template v-else-if="form.type === 'hubspot'">
            <p class="oauth-hint">Connect with HubSpot OAuth, or paste a legacy private app token.</p>
            <button v-if="!editingId" type="button" class="btn-oauth" @click="connectHubSpotOAuth">Connect with HubSpot</button>
            <div class="field-group"><label>API key (legacy)</label><input v-model="form.credentials.api_key" type="password" placeholder="Optional if using OAuth" /></div>
            <div class="field-group"><label>Import to object</label><input v-model="form.config.target_object" placeholder="people" /></div>
          </template>
          <template v-else-if="form.type === 'salesforce'">
            <div class="field-group"><label>Instance URL</label><input v-model="form.credentials.instance_url" /></div>
            <div class="field-group"><label>Access token</label><input v-model="form.credentials.access_token" type="password" placeholder="Leave blank to keep existing" /></div>
            <div class="field-group"><label>SOQL query</label><textarea v-model="form.config.soql" rows="2" placeholder="SELECT Id, Name, Email, Phone FROM Contact LIMIT 200"></textarea></div>
            <div class="field-group"><label>Import to object</label><input v-model="form.config.target_object" placeholder="people" /></div>
          </template>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" type="button" @click="closeModal">Cancel</button>
          <button class="btn-save" type="button" @click="save">{{ editingId ? 'Save' : 'Connect' }}</button>
        </div>
      </div>
    </div>

    <div v-if="sendModal" class="modal" @click.self="sendModal = null">
      <div class="modal-content">
        <h2>Send test — {{ sendModal.name }}</h2>
        <div class="field-group"><label>To / recipient</label><input v-model="sendForm.to" /></div>
        <div v-if="sendModal.type === 'email'" class="field-group"><label>Subject</label><input v-model="sendForm.subject" /></div>
        <div class="field-group"><label>Message</label><textarea v-model="sendForm.message" rows="3"></textarea></div>
        <div class="modal-footer">
          <button class="btn-cancel" type="button" @click="sendModal = null">Cancel</button>
          <button class="btn-save" type="button" @click="sendTest">Send</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '../services/api'

export default {
  data() {
    return {
      integrations: [],
      loading: true,
      showModal: false,
      editingId: null,
      sendModal: null,
      sendForm: { to: '', subject: 'SpiderNetOS test', message: 'Hello from SpiderNetOS integration test.' },
      toast: null,
      form: { name: '', type: 'slack', credentials: {}, config: { target_object: 'people' } },
    }
  },
  async mounted() {
    await this.load()
    this.handleOAuthReturn()
  },
  methods: {
    notify(text, type = 'ok') {
      this.toast = { text, type }
      setTimeout(() => { this.toast = null }, 5000)
    },
    handleOAuthReturn() {
      const params = new URLSearchParams(window.location.search)
      if (params.get('oauth') !== 'hubspot') return
      const status = params.get('status')
      const message = params.get('message')
      if (status === 'success') this.notify('HubSpot connected successfully.')
      else if (status === 'error') this.notify(message || 'HubSpot OAuth failed.', 'err')
      window.history.replaceState({}, '', window.location.pathname)
      this.load()
    },
    async connectHubSpotOAuth() {
      if (!this.form.name?.trim()) {
        this.notify('Enter a name for this integration first.', 'err')
        return
      }
      try {
        const res = await api.post('/integrations/oauth/hubspot/start', {
          name: this.form.name,
          config: { target_object: this.form.config.target_object || 'people' },
        })
        if (res.data.url) window.location.href = res.data.url
      } catch (e) {
        this.notify(e.response?.data?.error || 'Could not start HubSpot OAuth.', 'err')
      }
    },
    async load() {
      this.loading = true
      try {
        const res = await api.get('/integrations')
        this.integrations = res.data || []
      } catch (e) {
        this.notify(e.response?.data?.message || 'Could not load integrations.', 'err')
      } finally {
        this.loading = false
      }
    },
    openCreate() {
      this.editingId = null
      this.form = { name: '', type: 'slack', credentials: {}, config: { target_object: 'people', soql: 'SELECT Id, Name, Email, Phone FROM Contact LIMIT 200' } }
      this.showModal = true
    },
    openEdit(item) {
      this.editingId = item.id
      this.form = {
        name: item.name,
        type: item.type,
        credentials: {},
        config: { ...(item.config || {}), target_object: item.config?.target_object || 'people' },
      }
      this.showModal = true
    },
    closeModal() {
      this.showModal = false
      this.editingId = null
    },
    async save() {
      const payload = { name: this.form.name, credentials: this.form.credentials, config: this.form.config }
      Object.keys(payload.credentials).forEach(k => {
        if (payload.credentials[k] === '') delete payload.credentials[k]
      })
      try {
        if (this.editingId) {
          await api.put(`/integrations/${this.editingId}`, payload)
          this.notify('Integration updated.')
        } else {
          await api.post('/integrations', { ...payload, type: this.form.type })
          this.notify('Integration connected.')
        }
        this.closeModal()
        await this.load()
      } catch (e) {
        this.notify(e.response?.data?.message || 'Save failed.', 'err')
      }
    },
    async test(item) {
      try {
        const res = await api.post(`/integrations/${item.id}/test`)
        this.notify(res.data.message || (res.data.ok ? 'Test passed.' : 'Test failed.'), res.data.ok ? 'ok' : 'err')
        await this.load()
      } catch (e) {
        this.notify('Test request failed.', 'err')
      }
    },
    async sync(item) {
      try {
        const res = await api.post(`/integrations/${item.id}/sync`)
        this.notify(res.data.message || 'Sync complete.', res.data.ok ? 'ok' : 'err')
        await this.load()
      } catch (e) {
        this.notify('Sync failed.', 'err')
      }
    },
    async remove(item) {
      if (!confirm(`Delete ${item.name}?`)) return
      await api.delete(`/integrations/${item.id}`)
      this.notify('Integration deleted.')
      await this.load()
    },
    canSend(type) {
      return ['slack', 'email', 'whatsapp', 'hubspot', 'salesforce'].includes(type)
    },
    openSend(item) {
      this.sendModal = item
      this.sendForm = {
        to: item.type === 'slack' ? '' : (item.config?.default_to || ''),
        subject: 'SpiderNetOS test',
        message: 'Hello from SpiderNetOS integration test.',
      }
    },
    async sendTest() {
      try {
        const res = await api.post(`/integrations/${this.sendModal.id}/send`, {
          to: this.sendForm.to,
          subject: this.sendForm.subject,
          message: this.sendForm.message,
          email: this.sendForm.to,
        })
        this.notify(res.data.message || 'Sent.', res.data.ok ? 'ok' : 'err')
        this.sendModal = null
      } catch (e) {
        this.notify(e.response?.data?.message || 'Send failed.', 'err')
      }
    },
    isCrm(type) { return ['hubspot', 'salesforce'].includes(type) },
    typeIcon(type) {
      return { slack: '💬', email: '📧', whatsapp: '📱', hubspot: '🟠', salesforce: '☁️' }[type] || '🔌'
    },
    async copy(text) {
      try {
        await navigator.clipboard.writeText(text)
        this.notify('Copied to clipboard.')
      } catch {
        this.notify('Could not copy.', 'err')
      }
    },
  },
}
</script>

<style scoped>
.integrations-page { padding: 2rem; max-width: 1200px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
.page-header h1 { font-size: 1.8rem; color: #1e293b; }
.btn-create { background: #2563eb; color: white; border: none; padding: 0.5rem 1rem; border-radius: 12px; cursor: pointer; font-weight: 600; }
.toast { padding: 0.65rem 1rem; border-radius: 10px; margin-bottom: 1rem; font-size: 0.85rem; }
.toast.ok { background: #d1fae5; color: #065f46; }
.toast.err { background: #fee2e2; color: #991b1b; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
.card { background: white; border-radius: 18px; padding: 1.1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
.icon { font-size: 1.6rem; }
.status { font-size: 0.65rem; text-transform: uppercase; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 20px; }
.status.connected { background: #d1fae5; color: #065f46; }
.status.error { background: #fee2e2; color: #991b1b; }
.status.disconnected { background: #f1f5f9; color: #64748b; }
.type { font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.5rem; }
.inbound-url { font-size: 0.65rem; color: #64748b; word-break: break-all; margin-bottom: 0.5rem; }
.inbound-url code { font-size: 0.62rem; background: #f1f5f9; padding: 0.1rem 0.3rem; border-radius: 4px; }
.link-btn { background: none; border: none; color: #2563eb; cursor: pointer; font-size: 0.65rem; margin-left: 0.25rem; }
.oauth-badge { font-size: 0.65rem; color: #059669; font-weight: 600; margin-bottom: 0.5rem; }
.oauth-hint { font-size: 0.72rem; color: #64748b; margin-bottom: 0.75rem; line-height: 1.4; }
.btn-oauth { width: 100%; background: #ff7a59; color: white; border: none; padding: 0.55rem 1rem; border-radius: 10px; cursor: pointer; font-weight: 600; margin-bottom: 0.75rem; }
.error { font-size: 0.7rem; color: #dc2626; margin-bottom: 0.5rem; }
.actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
.actions button { font-size: 0.72rem; padding: 0.3rem 0.6rem; border-radius: 8px; border: none; background: #f1f5f9; cursor: pointer; }
.actions button.danger { background: #fee2e2; color: #dc2626; }
.loading, .empty { padding: 3rem; text-align: center; color: #94a3b8; grid-column: 1 / -1; }
.modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal-content { background: white; border-radius: 20px; width: 90%; max-width: 520px; padding: 1rem 1.25rem 1.25rem; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.field-group { margin-bottom: 0.9rem; }
.field-group label { display: block; font-size: 0.8rem; margin-bottom: 0.3rem; color: #475569; }
.field-group input, .field-group select, .field-group textarea { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid #e2e8f0; border-radius: 10px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; }
.btn-cancel { background: #f1f5f9; border: none; padding: 0.5rem 1rem; border-radius: 10px; cursor: pointer; }
.btn-save { background: #2563eb; color: white; border: none; padding: 0.5rem 1rem; border-radius: 10px; cursor: pointer; }
.modal-close { background: none; border: none; font-size: 1.1rem; cursor: pointer; color: #64748b; }
</style>
