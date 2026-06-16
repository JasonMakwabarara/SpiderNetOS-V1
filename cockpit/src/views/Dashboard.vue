<template>
  <div class="dashboard">
    <!-- Welcome Section -->
    <div class="welcome-section">
      <div>
        <h1>Welcome back, <span class="highlight">{{ userName }}</span></h1>
        <p class="greeting">Your AI Operating System is ready. Here's your workspace overview.</p>
      </div>
      <div class="status-badge">
        <span class="dot"></span>
        System Online
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card" @click="goToAgents">
        <div class="stat-icon purple">
          <span>🤖</span>
        </div>
        <div class="stat-info">
          <span class="stat-value">{{ activeAgents }}</span>
          <span class="stat-label">Active Agents</span>
          <span class="stat-desc">Task-specific AI assistants</span>
        </div>
      </div>
      <div class="stat-card" @click="goToFlows">
        <div class="stat-icon blue">
          <span>⚡</span>
        </div>
        <div class="stat-info">
          <span class="stat-value">{{ totalFlows }}</span>
          <span class="stat-label">Workflows</span>
          <span class="stat-desc">Automated business processes</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">
          <span>📊</span>
        </div>
        <div class="stat-info">
          <span class="stat-value">{{ totalExecutions }}</span>
          <span class="stat-label">Executions (24h)</span>
          <span class="stat-desc">AI-powered automations run</span>
        </div>
      </div>
    </div>

    <!-- Feature Cards Section -->
    <div class="features-section">
      <h2>Platform Capabilities</h2>
      <div class="features-grid">
        <div class="feature-card" @click="goToAtlas">
          <div class="feature-icon atlas">🗣️</div>
          <h3>Atlas AI Assistant</h3>
          <p>Natural language interface to create agents, build workflows, and manage your AI ecosystem. Use commands like "create agent SalesBot" or "/agents".</p>
          <div class="feature-details">
            <span class="detail-tag">🤖 Create Agents</span>
            <span class="detail-tag">⚡ Build Flows</span>
            <span class="detail-tag">📋 List Resources</span>
            <span class="detail-tag">💬 Natural Language</span>
          </div>
        </div>

        <div class="feature-card" @click="goToAgents">
          <div class="feature-icon agents">🤖</div>
          <h3>Task-Specific Agents</h3>
          <p>Deploy AI agents with specialized roles: Sales, Support, Data Analyst, Developer, and HR. Each agent has role-based capabilities and memory.</p>
          <div class="feature-details">
            <span class="detail-tag">💰 Sales Assistant</span>
            <span class="detail-tag">🛟 Support Agent</span>
            <span class="detail-tag">📊 Data Analyst</span>
            <span class="detail-tag">💻 Developer Helper</span>
            <span class="detail-tag">👥 HR Assistant</span>
          </div>
        </div>

        <div class="feature-card" @click="goToFlows">
          <div class="feature-icon flows">⚡</div>
          <h3>Automated Workflows</h3>
          <p>Build multi-step automations with triggers and actions. Execute with one click and monitor execution history.</p>
          <div class="feature-details">
            <span class="detail-tag">📧 Email Actions</span>
            <span class="detail-tag">💬 Slack Webhooks</span>
            <span class="detail-tag">🌐 HTTP Calls</span>
            <span class="detail-tag">📈 Execution Logs</span>
          </div>
        </div>

        <div class="feature-card">
          <div class="feature-icon rag">📚</div>
          <h3>RAG Knowledge Base</h3>
          <p>Upload documents, build a knowledge base, and ask questions. AI answers using your private documents with source attribution.</p>
          <div class="feature-details">
            <span class="detail-tag">📄 Document Upload</span>
            <span class="detail-tag">🔍 Vector Search</span>
            <span class="detail-tag">💡 AI Q&A</span>
            <span class="detail-tag">📎 Source Attribution</span>
          </div>
        </div>

        <div class="feature-card">
          <div class="feature-icon security">🔒</div>
          <h3>Enterprise Security</h3>
          <p>Multi-tenant architecture with complete data isolation, API authentication, and CORS protection.</p>
          <div class="feature-details">
            <span class="detail-tag">🏢 Tenant Isolation</span>
            <span class="detail-tag">🔐 API Tokens</span>
            <span class="detail-tag">🛡️ CORS Protected</span>
            <span class="detail-tag">📝 Audit Logs</span>
          </div>
        </div>

        <div class="feature-card">
          <div class="feature-icon packs">📦</div>
          <h3>Feature Packs</h3>
          <p>Install pre-built vertical solutions. Each pack includes dynamic agents, flows, and configurations ready to use.</p>
          <div class="feature-details">
            <span class="detail-tag">✅ Pack Validation</span>
            <span class="detail-tag">🔄 Dynamic Agents</span>
            <span class="detail-tag">📋 Versioned</span>
            <span class="detail-tag">🚀 One-Click Install</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-section">
      <h3>Quick Actions</h3>
      <div class="action-grid">
        <div class="action-card" @click="goToAtlas">
          <div class="action-icon">🗣️</div>
          <div class="action-text">
            <strong>Open Atlas</strong>
            <span>Chat with AI assistant</span>
          </div>
          <div class="action-arrow">→</div>
        </div>
        <div class="action-card" @click="goToFlows">
          <div class="action-icon">➕</div>
          <div class="action-text">
            <strong>New Flow</strong>
            <span>Create automation workflow</span>
          </div>
          <div class="action-arrow">→</div>
        </div>
        <div class="action-card" @click="goToAgents">
          <div class="action-icon">🤖</div>
          <div class="action-text">
            <strong>New Agent</strong>
            <span>Deploy AI assistant</span>
          </div>
          <div class="action-arrow">→</div>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="recent-section">
      <div class="recent-card">
        <div class="card-header">
          <h3>📋 Recent Agents</h3>
          <button class="view-link" @click="goToAgents">View all →</button>
        </div>
        <div class="recent-list">
          <div v-for="agent in recentAgents" :key="agent.id" class="recent-item">
            <div class="item-icon">{{ agent.icon || '🤖' }}</div>
            <div class="item-info">
              <div class="item-name">{{ agent.name }}</div>
              <div class="item-desc">{{ agent.description || 'No description' }}</div>
            </div>
            <div class="item-status" :class="agent.status">{{ agent.status }}</div>
          </div>
          <div v-if="recentAgents.length === 0" class="empty-state">No agents yet. Create your first agent →</div>
        </div>
      </div>
      <div class="recent-card">
        <div class="card-header">
          <h3>⚡ Recent Flows</h3>
          <button class="view-link" @click="goToFlows">View all →</button>
        </div>
        <div class="recent-list">
          <div v-for="flow in recentFlows" :key="flow.id" class="recent-item">
            <div class="item-icon">{{ flow.icon || '⚡' }}</div>
            <div class="item-info">
              <div class="item-name">{{ flow.name }}</div>
              <div class="item-desc">{{ flow.description || 'No description' }}</div>
            </div>
            <div class="item-stats">{{ flow.executions || 0 }} runs</div>
          </div>
          <div v-if="recentFlows.length === 0" class="empty-state">No flows yet. Create your first flow →</div>
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
      flows: [],
      userName: 'Operator'
    }
  },
  computed: {
    activeAgents() {
      return this.agents.filter(a => a.status === 'active').length
    },
    totalFlows() {
      return this.flows.length
    },
    totalExecutions() {
      return this.flows.reduce((sum, f) => sum + (f.executions || 0), 0)
    },
    recentAgents() {
      return this.agents.slice(0, 3)
    },
    recentFlows() {
      return this.flows.slice(0, 3)
    }
  },
  async mounted() {
    await this.fetchData()
    const user = localStorage.getItem('user')
    if (user) {
      try {
        this.userName = JSON.parse(user).name || 'Operator'
      } catch(e) {}
    }
  },
  methods: {
    async fetchData() {
      try {
        const [agentsRes, flowsRes] = await Promise.all([
          api.get('/agents'),
          api.get('/flows')
        ])
        this.agents = agentsRes.data || []
        this.flows = flowsRes.data || []
      } catch (err) {
        console.error('Error fetching data:', err)
      }
    },
    goToAtlas() {
      this.$router.push('/atlas')
    },
    goToFlows() {
      this.$router.push('/flows')
    },
    goToAgents() {
      this.$router.push('/agents')
    }
  }
}
</script>

<style scoped>
.dashboard {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
  background: linear-gradient(135deg, #f0f4f8 0%, #e8edf2 100%);
  min-height: 100vh;
}

.welcome-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.welcome-section h1 {
  font-size: 1.8rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.25rem;
}

.welcome-section .highlight {
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.greeting {
  color: #64748b;
  font-size: 0.9rem;
}

.status-badge {
  background: #d1fae5;
  color: #065f46;
  padding: 0.4rem 1rem;
  border-radius: 40px;
  font-size: 0.8rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.status-badge .dot {
  width: 8px;
  height: 8px;
  background: #10b981;
  border-radius: 50%;
  display: inline-block;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(1.2); }
  100% { opacity: 1; transform: scale(1); }
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border-radius: 20px;
  padding: 1.25rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  transition: all 0.2s;
  cursor: pointer;
}

.stat-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
}

.stat-icon.purple {
  background: linear-gradient(135deg, #8b5cf6, #7c3aed);
  box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
}

.stat-icon.blue {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.stat-icon.green {
  background: linear-gradient(135deg, #10b981, #059669);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.stat-info {
  flex: 1;
}

.stat-value {
  display: block;
  font-size: 2rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.2;
}

.stat-label {
  font-size: 0.8rem;
  color: #64748b;
  font-weight: 500;
}

.stat-desc {
  font-size: 0.7rem;
  color: #94a3b8;
  display: block;
}

.features-section {
  margin-bottom: 2rem;
}

.features-section h2 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 1.25rem;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.25rem;
}

.feature-card {
  background: white;
  border-radius: 20px;
  padding: 1.5rem;
  transition: all 0.2s;
  cursor: pointer;
  border: 1px solid #e2e8f0;
}

.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.1);
  border-color: #3b82f6;
}

.feature-icon {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  margin-bottom: 1rem;
}

.feature-icon.atlas { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.feature-icon.agents { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.feature-icon.flows { background: linear-gradient(135deg, #10b981, #059669); }
.feature-icon.rag { background: linear-gradient(135deg, #f59e0b, #d97706); }
.feature-icon.security { background: linear-gradient(135deg, #ef4444, #dc2626); }
.feature-icon.packs { background: linear-gradient(135deg, #06b6d4, #0891b2); }

.feature-card h3 {
  font-size: 1.1rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.feature-card p {
  font-size: 0.85rem;
  color: #64748b;
  line-height: 1.5;
  margin-bottom: 1rem;
}

.feature-details {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.detail-tag {
  background: #f1f5f9;
  padding: 0.2rem 0.6rem;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 500;
  color: #475569;
}

.quick-section {
  margin-bottom: 2rem;
}

.quick-section h3 {
  font-size: 1.2rem;
  margin-bottom: 1rem;
  color: #1e293b;
}

.action-grid {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.action-card {
  background: white;
  border-radius: 20px;
  padding: 1rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  flex: 1;
  min-width: 200px;
}

.action-card:hover {
  transform: translateY(-3px);
  background: linear-gradient(135deg, white, #f0f4ff);
  box-shadow: 0 8px 20px rgba(59, 130, 246, 0.15);
}

.action-icon {
  font-size: 1.8rem;
}

.action-text {
  flex: 1;
}

.action-text strong {
  display: block;
  font-size: 1rem;
  color: #1e293b;
}

.action-text span {
  font-size: 0.7rem;
  color: #64748b;
}

.action-arrow {
  color: #3b82f6;
  font-weight: 600;
  opacity: 0;
  transition: opacity 0.2s;
}

.action-card:hover .action-arrow {
  opacity: 1;
}

.recent-section {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 1.5rem;
}

.recent-card {
  background: white;
  border-radius: 20px;
  padding: 1.25rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.card-header h3 {
  font-size: 1rem;
  color: #1e293b;
}

.view-link {
  background: none;
  border: none;
  color: #3b82f6;
  font-size: 0.75rem;
  cursor: pointer;
  font-weight: 500;
}

.view-link:hover {
  text-decoration: underline;
}

.recent-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.recent-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem;
  border-radius: 16px;
  background: #f8fafc;
  transition: background 0.2s;
}

.recent-item:hover {
  background: #f1f5f9;
}

.item-icon {
  font-size: 1.4rem;
}

.item-info {
  flex: 1;
}

.item-name {
  font-weight: 600;
  font-size: 0.85rem;
  color: #1e293b;
}

.item-desc {
  font-size: 0.7rem;
  color: #64748b;
}

.item-status {
  font-size: 0.65rem;
  padding: 0.2rem 0.5rem;
  border-radius: 20px;
  font-weight: 600;
  text-transform: uppercase;
}

.item-status.active {
  background: #d1fae5;
  color: #065f46;
}

.item-stats {
  font-size: 0.7rem;
  color: #64748b;
  background: white;
  padding: 0.2rem 0.5rem;
  border-radius: 20px;
}

.empty-state {
  text-align: center;
  padding: 1.5rem;
  color: #94a3b8;
  font-size: 0.8rem;
}

@media (min-width: 2560px) {
  .dashboard {
    max-width: 1800px;
    padding: 3rem;
  }
  .stat-value {
    font-size: 2.2rem;
  }
  .action-card {
    padding: 1.2rem 1.5rem;
  }
  .action-icon {
    font-size: 2rem;
  }
}
</style>