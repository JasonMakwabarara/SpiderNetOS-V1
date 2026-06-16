<template>
  <div class="login-container">
    <div class="login-card">
      <div class="logo">
        <span class="logo-icon">🕷️</span>
        <h1>SpiderNetOS</h1>
      </div>
      <p class="subtitle">Sign in to your workspace</p>
      <form @submit.prevent="login">
        <div class="input-group">
          <input type="email" v-model="email" placeholder="Email" required />
        </div>
        <div class="input-group">
          <input type="password" v-model="password" placeholder="Password" required />
        </div>
        <button type="submit" class="btn-login">Sign In</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
      <div class="demo-creds">
        <p>Demo Credentials:</p>
        <code>admin@spidernetos.com / Zukaarimoto01!</code>
      </div>
    </div>
  </div>
</template>

<script>
import api from '../services/api'

export default {
  data() {
    return {
      email: '',
      password: '',
      error: ''
    }
  },
  methods: {
    async login() {
      this.error = ''
      try {
        const res = await api.post('/login', { 
          email: this.email, 
          password: this.password 
        })
        localStorage.setItem('token', res.data.token)
        localStorage.setItem('user', JSON.stringify(res.data.user))
        this.$router.push('/dashboard')
      } catch (err) {
        this.error = 'Invalid email or password'
        console.error('Login error:', err)
      }
    }
  }
}
</script>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0f0c29, #1a1a3e, #24243e);
}

.login-card {
  background: white;
  padding: 2.5rem;
  border-radius: 32px;
  width: 100%;
  max-width: 450px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  text-align: center;
}

.logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.logo-icon {
  font-size: 2.5rem;
}

.logo h1 {
  font-size: 2rem;
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.subtitle {
  color: #64748b;
  margin-bottom: 2rem;
}

.input-group {
  margin-bottom: 1.25rem;
}

input {
  width: 100%;
  padding: 0.875rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  font-size: 1rem;
  transition: all 0.2s;
}

input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-login {
  width: 100%;
  background: #3b82f6;
  color: white;
  border: none;
  padding: 0.875rem;
  border-radius: 40px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-login:hover {
  background: #2563eb;
}

.error {
  color: #ef4444;
  margin-top: 1rem;
  font-size: 0.875rem;
}

.demo-creds {
  margin-top: 1.5rem;
  padding: 1rem;
  background: #f1f5f9;
  border-radius: 16px;
  font-size: 0.75rem;
}

.demo-creds code {
  background: white;
  padding: 0.25rem 0.5rem;
  border-radius: 8px;
  display: inline-block;
  margin-top: 0.5rem;
}
</style>