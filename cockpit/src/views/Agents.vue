<template>
  <div class="agents-page">
    <div class="page-header">
      <div>
        <h1>🤖 Task-Specific Agents</h1>
        <p>Create agents with defined roles and capabilities</p>
      </div>
      <button class="btn-create" @click="openCreateModal">+ New Agent</button>
    </div>

    <div class="stats-container">
      <div class="stat-card"><div class="stat-icon purple">🤖</div><div class="stat-info"><span class="stat-value">{{ agents.length }}</span><span class="stat-label">Total Agents</span></div></div>
      <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-info"><span class="stat-value">{{ activeCount }}</span><span class="stat-label">Active</span></div></div>
    </div>

    <div class="agents-grid">
      <div v-for="agent in agents" :key="agent.id" class="agent-card">
        <div class="agent-header">
          <div class="agent-icon">{{ getIconForRole(agent.role) }}</div>
          <div class="agent-badge" :class="agent.status">{{ agent.status }}</div>
        </div>
        <h3>{{ agent.name }}</h3>
        <div class="agent-role-badge">{{ agent.role || 'Custom' }}</div>
        <p class="agent-description">{{ agent.description || 'No description' }}</p>
        <div class="agent-capabilities">
          <span v-for="cap in getCapabilities(agent.role)" :key="cap" class="capability">{{ cap }}</span>
        </div>
        <div class="card-actions">
          <button class="action-chat" @click="openChatModal(agent)">💬 Chat</button>
          <button class="action-edit" @click="editAgent(agent)">✎ Edit</button>
          <button class="action-delete" @click="deleteAgent(agent.id)">🗑 Delete</button>
        </div>
      </div>
      <div v-if="agents.length === 0" class="empty-state"><span>🤖</span><p>No agents yet</p><button class="btn-outline" @click="openCreateModal">Create your first agent</button></div>
    </div>

    <!-- Professional Create/Edit Modal with Role Selection -->
    <div v-if="showModal" class="modal" @click.self="showModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ editing ? '✎ Edit Agent' : '✨ Create Task-Specific Agent' }}</h2>
          <button class="modal-close" @click="showModal = false">✖</button>
        </div>
        <div class="modal-body">
          <div class="field-group">
            <label>Agent Name</label>
            <input v-model="form.name" placeholder="e.g., Sales Assistant, Support Bot" />
          </div>
          <div class="field-group">
            <label>Role / Purpose</label>
            <select v-model="form.role">
              <option value="">Select a role...</option>
              <option value="Sales">💰 Sales Assistant</option>
              <option value="Support">🛟 Customer Support</option>
              <option value="Data Analyst">📊 Data Analyst</option>
              <option value="Developer">💻 Developer Helper</option>
              <option value="HR">👥 HR Assistant</option>
              <option value="Custom">⚙️ Custom Role</option>
            </select>
          </div>
          <div class="field-group">
            <label>Description</label>
            <textarea v-model="form.description" placeholder="What should this agent do?" rows="3"></textarea>
          </div>
          <div class="field-group" v-if="form.role === 'Custom'">
            <label>Custom Capabilities (comma separated)</label>
            <input v-model="form.customCapabilities" placeholder="e.g., answer questions, analyze data, send emails" />
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="showModal = false">Cancel</button>
          <button class="btn-save" @click="saveAgent">{{ editing ? 'Save Changes' : 'Create Agent' }}</button>
        </div>
      </div>
    </div>

    <!-- Chat Modal with Context Awareness -->
    <div v-if="showChatModal" class="modal" @click.self="showChatModal = false">
      <div class="modal-content chat-modal">
        <div class="modal-header">
          <h2>💬 Chat with {{ currentAgent?.name }}</h2>
          <div class="agent-role-badge small">{{ currentAgent?.role || 'Custom' }}</div>
          <button class="modal-close" @click="showChatModal = false">✖</button>
        </div>
        <div class="chat-context">
          <div class="context-badge">🎯 {{ getContextPrompt(currentAgent) }}</div>
        </div>
        <div class="chat-messages" ref="chatContainer">
          <div v-for="(msg, idx) in chatHistory" :key="idx" :class="['message', msg.role]">
            <div class="avatar">{{ msg.role === 'user' ? '👤' : getIconForRole(currentAgent?.role) }}</div>
            <div class="bubble">{{ msg.content }}</div>
          </div>
          <div v-if="chatLoading" class="message assistant"><div class="avatar">🤖</div><div class="bubble typing">...</div></div>
        </div>
        <div class="chat-input">
          <input v-model="chatMessage" @keyup.enter="sendChatMessage" :placeholder="`Ask ${currentAgent?.name} something...`" />
          <button @click="sendChatMessage">Send</button>
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
      agents: [],
      showModal: false,
      editing: false,
      form: { id: null, name: '', description: '', role: '', customCapabilities: '', icon: '🤖' },
      showChatModal: false,
      currentAgent: null,
      chatHistory: [],
      chatMessage: '',
      chatLoading: false
    }
  },
  computed: {
    activeCount() {
      return this.agents.filter(a => a.status === 'active').length
    }
  },
  async mounted() {
    await this.fetchAgents()
  },
  methods: {
    async fetchAgents() {
      try {
        const res = await api.get('/agents')
        this.agents = res.data || []
      } catch(e) { console.error(e) }
    },
    getIconForRole(role) {
      const icons = { 'Sales': '💰', 'Support': '🛟', 'Data Analyst': '📊', 'Developer': '💻', 'HR': '👥', 'Custom': '⚙️' }
      return icons[role] || '🤖'
    },
    getCapabilities(role) {
      const caps = {
        'Sales': ['Lead qualification', 'Product recommendations', 'Pricing info'],
        'Support': ['Ticket resolution', 'FAQ answers', 'Issue tracking'],
        'Data Analyst': ['Data visualization', 'Trend analysis', 'Report generation'],
        'Developer': ['Code review', 'Debugging help', 'Documentation'],
        'HR': ['Employee onboarding', 'Policy questions', 'Leave requests'],
        'Custom': ['Custom capabilities']
      }
      return caps[role] || ['General assistance']
    },
    getContextPrompt(agent) {
      const prompts = {
        'Sales': "You are a Sales Assistant. Help with product inquiries, pricing, and closing deals. Be professional and persuasive.",
        'Support': "You are a Customer Support Agent. Help resolve issues, answer FAQs, and provide troubleshooting steps. Be patient and helpful.",
        'Data Analyst': "You are a Data Analyst. Help interpret data, create visualizations, and provide insights. Be analytical and precise.",
        'Developer': "You are a Developer Assistant. Help with code, debugging, and best practices. Be technical and accurate.",
        'HR': "You are an HR Assistant. Help with employee questions, policies, and onboarding. Be friendly and informative."
      }
      return prompts[agent?.role] || `You are ${agent?.name}. ${agent?.description || 'Help with tasks.'}`
    },
    openCreateModal() {
      this.editing = false
      this.form = { id: null, name: '', description: '', role: '', customCapabilities: '', icon: '🤖' }
      this.showModal = true
    },
    editAgent(agent) {
      this.editing = true
      this.form = { ...agent }
      this.showModal = true
    },
    async saveAgent() {
      try {
        let capabilities = this.getCapabilities(this.form.role)
        if (this.form.role === 'Custom' && this.form.customCapabilities) {
          capabilities = this.form.customCapabilities.split(',').map(c => c.trim())
        }
        const agentData = {
          name: this.form.name,
          description: this.form.description || `A ${this.form.role || 'custom'} agent`,
          role: this.form.role || 'Custom',
          capabilities: capabilities,
          status: 'inactive',
          icon: this.getIconForRole(this.form.role)
        }
        if (this.editing) {
          await api.put(`/agents/${this.form.id}`, agentData)
        } else {
          await api.post('/agents', agentData)
        }
        this.showModal = false
        await this.fetchAgents()
      } catch(e) { alert('Error saving agent') }
    },
    async deleteAgent(id) {
      if (!confirm('Delete this agent?')) return
      await api.delete(`/agents/${id}`)
      await this.fetchAgents()
    },
    openChatModal(agent) {
      this.currentAgent = agent
      this.chatHistory = []
      // Add system context message
      this.chatHistory.push({ role: 'assistant', content: `👋 I'm ${agent.name}. ${agent.description || 'How can I help you?'}` })
      this.showChatModal = true
      this.$nextTick(() => this.scrollToBottom())
    },
    async sendChatMessage() {
      if (!this.chatMessage.trim()) return
      const userMsg = { role: 'user', content: this.chatMessage }
      this.chatHistory.push(userMsg)
      const message = this.chatMessage
      this.chatMessage = ''
      this.chatLoading = true
      this.scrollToBottom()
      try {
        // Send with context
        const contextPrompt = this.getContextPrompt(this.currentAgent)
        const fullMessage = `${contextPrompt}\n\nUser: ${message}\n\nRespond as ${this.currentAgent.name}. Be helpful and stay within your role.`
        const res = await api.post(`/agents/${this.currentAgent.id}/run`, { message: fullMessage })
        const aiResponse = res.data.result?.response || 'No response'
        this.chatHistory.push({ role: 'assistant', content: aiResponse })
      } catch(e) {
        this.chatHistory.push({ role: 'assistant', content: 'Error: ' + e.message })
      } finally {
        this.chatLoading = false
        this.scrollToBottom()
      }
    },
    scrollToBottom() {
      const container = this.$refs.chatContainer
      if (container) container.scrollTop = container.scrollHeight
    }
  }
}
</script>

<style scoped>
.agents-page { padding: 2rem; max-width: 1400px; margin: 0 auto; background: linear-gradient(135deg, #f5f7fa 0%, #eef2f6 100%); min-height: 100vh; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
.page-header h1 { font-size: 2rem; background: linear-gradient(135deg, #1e293b, #3b82f6); -webkit-background-clip: text; background-clip: text; color: transparent; }
.btn-create { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 12px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
.stats-container { display: flex; gap: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; }
.stat-card { background: white; border-radius: 20px; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); flex: 1; }
.stat-icon { width: 48px; height: 48px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
.stat-value { font-size: 1.5rem; font-weight: 700; }
.agents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
.agent-card { background: white; border-radius: 24px; padding: 1.5rem; transition: all 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.agent-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.1); }
.agent-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
.agent-icon { font-size: 2.5rem; }
.agent-badge { font-size: 0.65rem; padding: 0.2rem 0.6rem; border-radius: 20px; font-weight: 600; text-transform: uppercase; }
.agent-badge.active { background: #d1fae5; color: #065f46; }
.agent-badge.inactive { background: #fee2e2; color: #991b1b; }
.agent-card h3 { font-size: 1.2rem; margin-bottom: 0.25rem; }
.agent-role-badge { display: inline-block; background: #e2e8f0; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; margin-bottom: 0.5rem; }
.agent-description { font-size: 0.8rem; color: #64748b; margin-bottom: 0.75rem; }
.agent-capabilities { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-bottom: 1rem; }
.capability { background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 20px; font-size: 0.65rem; color: #475569; }
.card-actions { display: flex; gap: 0.5rem; justify-content: flex-end; }
.card-actions button { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.7rem; cursor: pointer; border: none; font-weight: 500; }
.action-chat { background: #3b82f6; color: white; }
.action-edit { background: #f1f5f9; color: #334155; }
.action-delete { background: #fee2e2; color: #dc2626; }
.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
.modal-content { background: white; border-radius: 32px; width: 90%; max-width: 540px; overflow: hidden; animation: fadeIn 0.2s ease; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 1.5rem 0 1.5rem; flex-wrap: wrap; gap: 0.5rem; }
.modal-header h2 { font-size: 1.3rem; font-weight: 600; }
.modal-close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #94a3b8; }
.modal-body { padding: 1.5rem; }
.field-group { margin-bottom: 1.25rem; }
.field-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.85rem; color: #334155; }
.field-group input, .field-group textarea, .field-group select { width: 100%; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 0.9rem; }
.modal-footer { display: flex; gap: 1rem; justify-content: flex-end; padding: 0 1.5rem 1.5rem 1.5rem; }
.btn-cancel { background: #f1f5f9; border: none; padding: 0.6rem 1.2rem; border-radius: 40px; cursor: pointer; }
.btn-save { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 40px; cursor: pointer; font-weight: 600; }
.chat-modal { max-width: 600px; height: 70vh; display: flex; flex-direction: column; }
.chat-context { padding: 0.75rem 1rem; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; }
.context-badge { font-size: 0.7rem; color: #475569; }
.agent-role-badge.small { font-size: 0.65rem; }
.chat-messages { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem; background: #f8fafc; }
.message { display: flex; gap: 0.5rem; align-items: flex-start; }
.message.user { flex-direction: row-reverse; }
.message.user .bubble { background: #3b82f6; color: white; border-radius: 18px 18px 4px 18px; }
.message.assistant .bubble { background: white; color: #1e293b; border-radius: 18px 18px 18px 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.avatar { width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.bubble { max-width: 70%; padding: 0.6rem 1rem; line-height: 1.4; white-space: pre-wrap; }
.chat-input { display: flex; gap: 0.5rem; padding: 1rem; background: white; border-top: 1px solid #e2e8f0; }
.chat-input input { flex: 1; padding: 0.6rem 1rem; border: 1px solid #e2e8f0; border-radius: 40px; outline: none; }
.chat-input button { background: #3b82f6; color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 40px; cursor: pointer; }
.empty-state { text-align: center; padding: 3rem; background: white; border-radius: 28px; }
.btn-outline { background: transparent; border: 1px solid #3b82f6; color: #3b82f6; padding: 0.5rem 1rem; border-radius: 30px; cursor: pointer; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
</style>