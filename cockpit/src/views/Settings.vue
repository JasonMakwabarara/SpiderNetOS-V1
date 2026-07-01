<template>
  <div class="settings-page">
    <h1>Account settings</h1>
    <p class="subtitle">Manage your password and active sessions.</p>

    <section class="card">
      <h2>Change password</h2>
      <form @submit.prevent="changePassword">
        <div class="field">
          <label>Current password</label>
          <input v-model="currentPassword" type="password" autocomplete="current-password" required />
        </div>
        <div class="field">
          <label>New password</label>
          <input v-model="newPassword" type="password" autocomplete="new-password" minlength="8" required />
        </div>
        <div class="field">
          <label>Confirm new password</label>
          <input v-model="newPasswordConfirmation" type="password" required />
        </div>
        <button type="submit" class="btn-primary" :disabled="pwLoading">Update password</button>
        <p v-if="pwMessage" class="msg ok">{{ pwMessage }}</p>
        <p v-if="pwError" class="msg err">{{ pwError }}</p>
      </form>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Active sessions</h2>
        <button class="btn-outline" type="button" @click="revokeOthers" :disabled="sessionsLoading">
          Sign out other devices
        </button>
      </div>
      <div v-if="sessionsLoading" class="muted">Loading sessions…</div>
      <ul v-else class="session-list">
        <li v-for="s in sessions" :key="s.id" class="session-item">
          <div>
            <strong>{{ s.name }}</strong>
            <span v-if="s.current" class="badge">This device</span>
            <p class="muted">Last used {{ formatDate(s.last_used_at || s.created_at) }}</p>
          </div>
          <button v-if="!s.current" class="btn-danger" type="button" @click="revoke(s.id)">Revoke</button>
        </li>
      </ul>
    </section>
  </div>
</template>

<script>
import api from '../services/api'
import { getAuthState } from '../composables/useAuth'

export default {
  data() {
    return {
      auth: getAuthState(),
      currentPassword: '',
      newPassword: '',
      newPasswordConfirmation: '',
      pwMessage: '',
      pwError: '',
      pwLoading: false,
      sessions: [],
      sessionsLoading: true,
    }
  },
  async mounted() {
    await this.loadSessions()
  },
  methods: {
    async changePassword() {
      this.pwMessage = ''
      this.pwError = ''
      if (this.newPassword !== this.newPasswordConfirmation) {
        this.pwError = 'New passwords do not match.'
        return
      }
      this.pwLoading = true
      try {
        const res = await api.put('/password', {
          current_password: this.currentPassword,
          password: this.newPassword,
          password_confirmation: this.newPasswordConfirmation,
        })
        this.pwMessage = res.data.message
        this.currentPassword = ''
        this.newPassword = ''
        this.newPasswordConfirmation = ''
      } catch (e) {
        this.pwError = e.response?.data?.error || 'Could not update password.'
      } finally {
        this.pwLoading = false
      }
    },
    async loadSessions() {
      this.sessionsLoading = true
      try {
        const res = await api.get('/sessions')
        this.sessions = res.data || []
      } finally {
        this.sessionsLoading = false
      }
    },
    async revoke(id) {
      await api.delete(`/sessions/${id}`)
      await this.loadSessions()
    },
    async revokeOthers() {
      await api.delete('/sessions')
      await this.loadSessions()
    },
    formatDate(v) {
      if (!v) return 'never'
      return new Date(v).toLocaleString()
    },
  },
}
</script>

<style scoped>
.settings-page { padding: 2rem; max-width: 720px; }
h1 { font-size: 1.6rem; color: #1e293b; }
.subtitle { color: #64748b; margin-bottom: 1.5rem; }
.card {
  background: white;
  border-radius: 16px;
  padding: 1.25rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.card h2 { font-size: 1rem; margin-bottom: 1rem; }
.card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.field { margin-bottom: 0.9rem; }
.field label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.3rem; color: #475569; }
.field input { width: 100%; padding: 0.65rem 0.8rem; border: 1px solid #e2e8f0; border-radius: 10px; }
.btn-primary { background: #2563eb; color: white; border: none; padding: 0.55rem 1rem; border-radius: 10px; font-weight: 600; cursor: pointer; }
.btn-outline { background: white; border: 1px solid #cbd5e1; padding: 0.4rem 0.75rem; border-radius: 8px; cursor: pointer; font-size: 0.8rem; }
.btn-danger { background: #fee2e2; color: #b91c1c; border: none; padding: 0.35rem 0.65rem; border-radius: 8px; cursor: pointer; font-size: 0.75rem; }
.session-list { list-style: none; }
.session-item { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9; }
.badge { font-size: 0.65rem; background: #dbeafe; color: #1d4ed8; padding: 0.1rem 0.4rem; border-radius: 999px; margin-left: 0.5rem; }
.muted { color: #94a3b8; font-size: 0.8rem; }
.msg { margin-top: 0.75rem; font-size: 0.85rem; }
.msg.ok { color: #059669; }
.msg.err { color: #dc2626; }
</style>
