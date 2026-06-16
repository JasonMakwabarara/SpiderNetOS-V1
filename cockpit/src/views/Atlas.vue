<template>
  <div class="atlas-container">
    <div class="atlas-header">
      <h1>🗣️ Atlas – General AI Assistant</h1>
      <p>I'm your AI Operating System assistant. I can help you create agents, manage flows, and answer questions about the platform.</p>
    </div>
    <div class="chat-wrapper">
      <div class="chat-messages" ref="messagesContainer">
        <div v-for="msg in messages" :key="msg.id" :class="['message', msg.role]">
          <div class="avatar">{{ msg.role === 'user' ? '👤' : '🗣️' }}</div>
          <div class="bubble">{{ msg.content }}</div>
        </div>
        <div v-if="loading" class="message assistant"><div class="avatar">🗣️</div><div class="bubble typing">...</div></div>
      </div>
      <div class="chat-input-area">
        <div class="suggestions">
          <button v-for="s in suggestions" :key="s" @click="sendSuggestion(s)">{{ s }}</button>
        </div>
        <div class="input-group">
          <input v-model="inputMessage" @keyup.enter="sendMessage" placeholder='Try "create agent SalesBot" or "create flow DataPipeline"' />
          <button @click="sendMessage" class="send-btn">Send</button>
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
      messages: [{ id: 1, role: 'assistant', content: "👋 **Hello! I'm Atlas, your AI Operating System assistant.**\n\nI can help you:\n• Create task-specific agents (Sales, Support, Data Analyst, etc.)\n• Build automated workflows\n• Monitor your AI workspace\n• Answer questions about SpiderNetOS\n\n**Try these commands:**\n• `create agent SalesBot`\n• `create flow DataPipeline`\n• `create email flow Newsletter to email@example.com`\n• `create slack flow Alerts webhook https://hooks.slack.com/...`\n• `/agents`\n• `/flows`\n• `/status`\n• `/help`" }],
      inputMessage: '',
      loading: false,
      suggestions: ['create agent SalesBot', 'create flow DataPipeline', 'create email flow Test to email@example.com', '/agents', '/help']
    }
  },
  methods: {
    async sendMessage() {
      if (!this.inputMessage.trim()) return
      const userMsg = { id: Date.now(), role: 'user', content: this.inputMessage }
      this.messages.push(userMsg)
      const command = this.inputMessage
      this.inputMessage = ''
      this.loading = true
      this.scrollToBottom()
      try {
        const response = await this.processCommand(command)
        this.messages.push({ id: Date.now(), role: 'assistant', content: response })
      } catch(err) {
        this.messages.push({ id: Date.now(), role: 'assistant', content: `❌ Error: ${err.message}` })
      } finally {
        this.loading = false
        this.scrollToBottom()
      }
    },
    async processCommand(text) {
      const lower = text.toLowerCase()
      
      // CREATE EMAIL FLOW
      if (lower.includes('create email flow')) {
        const match = text.match(/create email flow (.+?) to (.+)/i)
        if (!match) return '❌ Please use: create email flow [name] to [email@example.com]'
        const name = match[1].trim()
        const email = match[2].trim()
        try {
          const flowData = {
            name: name,
            description: `Email flow created via Atlas: ${name}`,
            trigger: 'manual',
            icon: '📧',
            config: { actions: [{ type: 'email', to: email, subject: `Flow ${name} executed`, body: `The flow "${name}" was triggered successfully!` }] }
          }
          const res = await api.post('/flows', flowData)
          return `✅ Email flow "${res.data.name}" created!\n\n📧 Will send to: ${email}\n▶️ Go to Flows page and click Execute to test it.`
        } catch(e) { return '❌ Failed to create email flow. Make sure backend is running.' }
      }
      
      // CREATE SLACK FLOW
      if (lower.includes('create slack flow')) {
        const match = text.match(/create slack flow (.+?) webhook (.+)/i)
        if (!match) return '❌ Please use: create slack flow [name] webhook [https://hooks.slack.com/...]'
        const name = match[1].trim()
        let webhook = match[2].trim()
        try {
          const flowData = {
            name: name,
            description: `Slack flow created via Atlas: ${name}`,
            trigger: 'manual',
            icon: '💬',
            config: { actions: [{ type: 'slack', webhook: webhook, message: `The flow "${name}" was executed in SpiderNetOS!` }] }
          }
          const res = await api.post('/flows', flowData)
          return `✅ Slack flow "${res.data.name}" created!\n\n💬 Will send to your Slack channel.\n▶️ Go to Flows page and click Execute to test it.`
        } catch(e) { return '❌ Failed to create Slack flow.' }
      }
      
      // CREATE AGENT
      if (lower.includes('create agent')) {
        const name = text.replace(/create agent/i, '').trim()
        if (!name) return '❌ Please specify an agent name.'
        try {
          const res = await api.post('/agents', { name, description: `Created via Atlas: ${name}`, role: 'Custom', status: 'inactive', icon: '🤖' })
          return `✅ Agent "${res.data.name}" created successfully!\n\nYou can now:\n• Edit its role and capabilities on the Agents page\n• Chat with it to perform specific tasks`
        } catch(e) { return '❌ Failed to create agent.' }
      }
      
      // CREATE FLOW
      if (lower.includes('create flow') && !lower.includes('email') && !lower.includes('slack')) {
        const name = text.replace(/create flow/i, '').trim()
        if (!name) return '❌ Please specify a flow name.'
        try {
          const res = await api.post('/flows', { name, description: `Created via Atlas: ${name}`, trigger: 'manual', icon: '⚡' })
          return `✅ Flow "${res.data.name}" created successfully!\n\nYou can now:\n• Add actions (Slack, Email, Webhook) from the Flows page\n• Click Execute to run it`
        } catch(e) { return '❌ Failed to create flow.' }
      }
      
      // /AGENTS COMMAND
      if (lower === '/agents' || lower === 'list agents') {
        const res = await api.get('/agents')
        if (res.data.length === 0) return '📋 No agents yet. Create one with "create agent [name]"'
        return "**📋 Your Agents:**\n" + res.data.map(a => `• ${a.name} (${a.role || 'Custom'}) - ${a.status}`).join('\n')
      }
      
      // /FLOWS COMMAND
      if (lower === '/flows' || lower === 'list flows') {
        const res = await api.get('/flows')
        if (res.data.length === 0) return '📋 No flows yet. Create one with "create flow [name]"'
        return "**📋 Your Flows:**\n" + res.data.map(f => `• ${f.name} (${f.executions || 0} runs)`).join('\n')
      }
      
      // /STATUS COMMAND
      if (lower === '/status') {
        const [agents, flows] = await Promise.all([api.get('/agents'), api.get('/flows')])
        return `📊 **SpiderNetOS Status**\n• Agents: ${agents.data.length}\n• Flows: ${flows.data.length}\n• System: Operational\n• AI: Ready`
      }
      
      // /HELP COMMAND
      if (lower === '/help') {
        return "**📚 Available Commands:**\n\n• `create agent [name]` - Create a new AI agent\n• `create flow [name]` - Create a new workflow\n• `create email flow [name] to [email]` - Create email notification flow\n• `create slack flow [name] webhook [url]` - Create Slack notification flow\n• `/agents` - List all agents\n• `/flows` - List all flows\n• `/status` - System status\n• `/help` - Show this help"
      }
      
      // FALLBACK - General AI response
      try {
        const res = await api.post('/atlas/chat', { message: text })
        return res.data.response || "I'm here to help you manage your AI Operating System. Try `/help` for commands."
      } catch(e) {
        return "I'm here to help you manage your AI Operating System. Try `/help` for commands."
      }
    },
    sendSuggestion(suggestion) {
      this.inputMessage = suggestion
      this.sendMessage()
    },
    scrollToBottom() {
      const container = this.$refs.messagesContainer
      if (container) container.scrollTop = container.scrollHeight
    }
  }
}
</script>

<style scoped>
.atlas-container { height: calc(100vh - 80px); display: flex; flex-direction: column; background: linear-gradient(135deg, #f8fafc, #f1f5f9); }
.atlas-header { text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #0f0c29, #1a1a3e); color: white; }
.atlas-header h1 { font-size: 1.8rem; margin-bottom: 0.25rem; }
.atlas-header p { font-size: 0.85rem; opacity: 0.8; }
.chat-wrapper { max-width: 900px; margin: 1.5rem auto; width: 100%; flex: 1; display: flex; flex-direction: column; background: white; border-radius: 32px; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1); overflow: hidden; }
.chat-messages { flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.message { display: flex; gap: 0.75rem; align-items: flex-start; }
.message.user { flex-direction: row-reverse; }
.message.user .bubble { background: #3b82f6; color: white; border-radius: 20px 20px 4px 20px; }
.message.assistant .bubble { background: #f1f5f9; color: #1e293b; border-radius: 20px 20px 20px 4px; }
.avatar { width: 36px; height: 36px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.bubble { max-width: 70%; padding: 0.75rem 1rem; line-height: 1.4; white-space: pre-wrap; }
.typing { opacity: 0.6; font-style: italic; }
.chat-input-area { padding: 1rem; border-top: 1px solid #e2e8f0; background: white; }
.suggestions { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
.suggestions button { background: white; border: 1px solid #cbd5e1; border-radius: 30px; padding: 0.25rem 0.75rem; font-size: 0.75rem; cursor: pointer; transition: all 0.2s; }
.suggestions button:hover { background: #3b82f6; color: white; border-color: #3b82f6; }
.input-group { display: flex; gap: 0.5rem; }
.input-group input { flex: 1; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; border-radius: 40px; font-size: 0.9rem; outline: none; }
.input-group input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.send-btn { background: #3b82f6; color: white; border: none; padding: 0.5rem 1.5rem; border-radius: 40px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
.send-btn:hover { background: #2563eb; }
@media (min-width: 2560px) { .chat-wrapper { max-width: 1200px; } .atlas-header h1 { font-size: 2.2rem; } .bubble { font-size: 1rem; } }
</style>