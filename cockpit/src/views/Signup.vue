<template>
  <div class="signup-container">
    <div class="signup-card">
      <div class="logo">
        <span class="logo-icon">🕷️</span>
        <h1>SpiderNetOS</h1>
      </div>
      <p class="subtitle">Create your workspace</p>
      
      <form @submit.prevent="signup">
        <div class="input-group">
          <label>Full Name</label>
          <input type="text" v-model="name" placeholder="John Doe" required />
        </div>
        <div class="input-group">
          <label>Email Address</label>
          <input type="email" v-model="email" placeholder="hello@example.com" required />
        </div>
        <div class="input-group">
          <label>Password</label>
          <input type="password" v-model="password" placeholder="Create a password" required />
        </div>
        <button type="submit" class="btn-signup">Get Started →</button>
      </form>
      
      <p v-if="error" class="error">{{ error }}</p>
      <p class="login-link">
        Already have an account? <a href="#" @click.prevent="goToLogin">Sign in</a>
      </p>
    </div>
  </div>
</template>

<script>
import api from '../services/api'

export default {
  data() {
    return {
      name: '',
      email: '',
      password: '',
      error: ''
    }
  },
  methods: {
    async signup() {
      this.error = ''
      if (!this.name || !this.email || !this.password) {
        this.error = 'Please fill all fields'
        return
      }
      try {
        // Mock signup – in production, call backend
        localStorage.setItem('token', 'mock-token-' + Date.now())
        localStorage.setItem('user', JSON.stringify({ name: this.name, email: this.email }))
        this.$router.push('/dashboard')
      } catch (err) {
        this.error = 'Signup failed. Please try again.'
      }
    },
    goToLogin() {
      this.$router.push('/login')
    }
  }
}
</script>

<style scoped>
.signup-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0f0c29, #1a1a3e, #24243e);
  padding: 1.5rem;
}

.signup-card {
  background: white;
  padding: 2.5rem;
  border-radius: 32px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}

.logo-icon {
  font-size: 2.5rem;
}

.logo h1 {
  font-size: 1.8rem;
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}

.subtitle {
  text-align: center;
  color: #64748b;
  margin-bottom: 2rem;
  font-size: 0.9rem;
}

.input-group {
  margin-bottom: 1.25rem;
}

.input-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.85rem;
  color: #334155;
}

.input-group input {
  width: 100%;
  padding: 0.875rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  font-size: 0.95rem;
  transition: all 0.2s;
}

.input-group input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-signup {
  width: 100%;
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
  border: none;
  padding: 0.875rem;
  border-radius: 40px;
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 0.5rem;
}

.btn-signup:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.error {
  color: #ef4444;
  margin-top: 1rem;
  font-size: 0.85rem;
  text-align: center;
}

.login-link {
  margin-top: 1.5rem;
  text-align: center;
  font-size: 0.85rem;
  color: #64748b;
}

.login-link a {
  color: #3b82f6;
  text-decoration: none;
  font-weight: 600;
}

@media (min-width: 2560px) {
  .signup-card { max-width: 580px; padding: 3rem; }
  .input-group input { padding: 1rem; font-size: 1rem; }
  .btn-signup { padding: 1rem; font-size: 1.1rem; }
}
</style>