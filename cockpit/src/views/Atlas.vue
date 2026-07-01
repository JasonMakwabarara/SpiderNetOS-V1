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
      messages: [{ id: 1, role: 'assistant', content: "👋 **Hello! I'm Atlas — Ask SpiderNet.**\n\nTalk to me naturally and I'll operate your workspace for you. I can:\n• Search and create records (people, companies, deals…)\n• Create and run AI agents\n• Build automation workflows\n• Search your knowledge base and remember your preferences\n\n**Try asking:**\n• \"Add Ada Lovelace as a person with email ada@example.com\"\n• \"Which companies do we have?\"\n• \"Create a Support agent called HelpBot\"\n• `/status` · `/objects` · `/agents` · `/help`" }],
      inputMessage: '',
      loading: false,
      suggestions: ['Add a person named Ada Lovelace', 'Which companies do we have?', 'Create a Support agent called HelpBot', '/objects', '/help']
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
    // Atlas is now LLM-powered with tool-calling + long-term memory on the
    // backend. The UI forwards everything to /atlas/chat, which decides whether
    // to read/create records, run agents, build flows, or answer directly.
    async processCommand(text) {
      try {
        const res = await api.post('/atlas/chat', { message: text })
        return res.data.response || "I'm here to help you manage your AI Operating System. Try `/help` for commands."
      } catch(e) {
        return "⚠️ I couldn't reach the assistant. Make sure you're signed in and the backend is running."
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