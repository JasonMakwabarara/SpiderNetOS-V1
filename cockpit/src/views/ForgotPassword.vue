<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1>Reset password</h1>
      <p class="subtitle">Enter your email and we'll send a reset link if an account exists.</p>
      <form @submit.prevent="submit">
        <div class="input-group">
          <label>Email</label>
          <input v-model="email" type="email" autocomplete="email" required />
        </div>
        <button type="submit" class="btn-primary" :disabled="loading">
          {{ loading ? 'Sending…' : 'Send reset link' }}
        </button>
      </form>
      <p v-if="message" class="success">{{ message }}</p>
      <p v-if="error" class="error">{{ error }}</p>
      <p class="footer-link"><router-link to="/login">Back to sign in</router-link></p>
    </div>
  </div>
</template>

<script>
import api from '../services/api'

export default {
  data() {
    return { email: '', message: '', error: '', loading: false }
  },
  methods: {
    async submit() {
      this.message = ''
      this.error = ''
      this.loading = true
      try {
        const res = await api.post('/password/forgot', { email: this.email })
        this.message = res.data.message
      } catch {
        this.error = 'Could not process request. Try again later.'
      } finally {
        this.loading = false
      }
    },
  },
}
</script>

<style scoped>
.auth-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0f0c29, #1a1a3e); padding: 1.5rem; }
.auth-card { background: white; padding: 2rem; border-radius: 24px; max-width: 420px; width: 100%; }
h1 { font-size: 1.5rem; margin-bottom: 0.5rem; color: #1e293b; }
.subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem; }
.input-group { margin-bottom: 1rem; }
.input-group label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.35rem; }
input { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 12px; }
.btn-primary { width: 100%; background: #2563eb; color: white; border: none; padding: 0.75rem; border-radius: 12px; font-weight: 600; cursor: pointer; }
.success { color: #059669; margin-top: 1rem; font-size: 0.85rem; }
.error { color: #ef4444; margin-top: 1rem; font-size: 0.85rem; }
.footer-link { margin-top: 1.25rem; text-align: center; }
.footer-link a { color: #3b82f6; text-decoration: none; font-weight: 600; }
</style>
