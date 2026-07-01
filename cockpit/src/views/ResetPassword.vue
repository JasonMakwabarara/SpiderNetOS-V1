<template>
  <div class="auth-page">
    <div class="auth-card">
      <h1>Set new password</h1>
      <form @submit.prevent="submit">
        <div class="input-group">
          <label>Email</label>
          <input v-model="email" type="email" required readonly />
        </div>
        <div class="input-group">
          <label>New password</label>
          <input v-model="password" type="password" minlength="8" required />
        </div>
        <div class="input-group">
          <label>Confirm password</label>
          <input v-model="passwordConfirmation" type="password" required />
        </div>
        <button type="submit" class="btn-primary" :disabled="loading">
          {{ loading ? 'Saving…' : 'Reset password' }}
        </button>
      </form>
      <p v-if="message" class="success">{{ message }}</p>
      <p v-if="error" class="error">{{ error }}</p>
    </div>
  </div>
</template>

<script>
import api from '../services/api'

export default {
  data() {
    return {
      token: this.$route.query.token || '',
      email: this.$route.query.email || '',
      password: '',
      passwordConfirmation: '',
      message: '',
      error: '',
      loading: false,
    }
  },
  methods: {
    async submit() {
      this.error = ''
      this.message = ''
      if (this.password !== this.passwordConfirmation) {
        this.error = 'Passwords do not match.'
        return
      }
      this.loading = true
      try {
        const res = await api.post('/password/reset', {
          token: this.token,
          email: this.email,
          password: this.password,
          password_confirmation: this.passwordConfirmation,
        })
        this.message = res.data.message
        setTimeout(() => this.$router.push('/login'), 2000)
      } catch (e) {
        this.error = e.response?.data?.error || 'Reset failed. Request a new link.'
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
h1 { font-size: 1.5rem; margin-bottom: 1rem; color: #1e293b; }
.input-group { margin-bottom: 1rem; }
.input-group label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.35rem; }
input { width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 12px; }
.btn-primary { width: 100%; background: #2563eb; color: white; border: none; padding: 0.75rem; border-radius: 12px; font-weight: 600; cursor: pointer; }
.success { color: #059669; margin-top: 1rem; font-size: 0.85rem; }
.error { color: #ef4444; margin-top: 1rem; font-size: 0.85rem; }
</style>
