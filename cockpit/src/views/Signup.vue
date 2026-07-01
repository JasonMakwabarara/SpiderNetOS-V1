<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="logo">
        <span class="logo-icon">🕷️</span>
        <h1>SpiderNetOS</h1>
      </div>
      <p class="subtitle">Create your workspace</p>
      <form @submit.prevent="submit">
        <div class="input-group">
          <label>Workspace name</label>
          <input v-model="workspaceName" placeholder="Acme Sales" required />
        </div>
        <div class="input-group">
          <label>Your name</label>
          <input v-model="name" autocomplete="name" required />
        </div>
        <div class="input-group">
          <label>Email</label>
          <input v-model="email" type="email" autocomplete="email" required />
        </div>
        <div class="input-group">
          <label>Password</label>
          <input v-model="password" type="password" autocomplete="new-password" minlength="8" required />
        </div>
        <div class="input-group">
          <label>Confirm password</label>
          <input v-model="passwordConfirmation" type="password" autocomplete="new-password" required />
        </div>
        <button type="submit" class="btn-primary" :disabled="loading">
          {{ loading ? 'Creating…' : 'Create workspace' }}
        </button>
      </form>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <p class="footer-link">
        Already have an account? <router-link to="/login">Sign in</router-link>
      </p>
    </div>
  </div>
</template>

<script>
import { register } from '../composables/useAuth'

export default {
  data() {
    return {
      workspaceName: '',
      name: '',
      email: '',
      password: '',
      passwordConfirmation: '',
      error: '',
      loading: false,
    }
  },
  methods: {
    async submit() {
      this.error = ''
      if (this.password !== this.passwordConfirmation) {
        this.error = 'Passwords do not match.'
        return
      }
      this.loading = true
      try {
        await register({
          workspace_name: this.workspaceName,
          name: this.name,
          email: this.email,
          password: this.password,
          password_confirmation: this.passwordConfirmation,
        })
        this.$router.push('/dashboard')
      } catch (e) {
        const msg = e.response?.data?.message
        const errors = e.response?.data?.errors
        this.error = msg || (errors ? Object.values(errors).flat().join(' ') : 'Signup failed.')
      } finally {
        this.loading = false
      }
    },
  },
}
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0f0c29, #1a1a3e, #24243e);
  padding: 1.5rem;
}
.auth-card {
  background: white;
  padding: 2.5rem;
  border-radius: 32px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}
.logo { display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 0.5rem; }
.logo-icon { font-size: 2.5rem; }
.logo h1 {
  font-size: 1.8rem;
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
.subtitle { text-align: center; color: #64748b; margin-bottom: 1.5rem; }
.input-group { margin-bottom: 1rem; }
.input-group label { display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem; }
input {
  width: 100%;
  padding: 0.8rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  font-size: 0.95rem;
}
.btn-primary {
  width: 100%;
  background: #2563eb;
  color: white;
  border: none;
  padding: 0.875rem;
  border-radius: 40px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 0.5rem;
}
.btn-primary:disabled { opacity: 0.7; }
.error { color: #ef4444; margin-top: 1rem; font-size: 0.85rem; text-align: center; }
.footer-link { margin-top: 1.5rem; text-align: center; font-size: 0.85rem; color: #64748b; }
.footer-link a { color: #3b82f6; font-weight: 600; text-decoration: none; }
</style>
