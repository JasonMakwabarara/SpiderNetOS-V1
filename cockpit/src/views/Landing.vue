<template>
  <div class="landing">
    <nav class="navbar">
      <div class="nav-container">
        <div class="logo">
          <span class="logo-icon">🕷️</span>
          <span class="logo-text">SpiderNetOS</span>
        </div>
        <div class="nav-links">
          <router-link to="/features">Features</router-link>
          <router-link to="/pricing">Pricing</router-link>
          <router-link to="/docs">Docs</router-link>
          <button class="btn-outline" @click="goToLogin">Sign In</button>
          <button class="btn-primary" @click="goToSignup">Get Started</button>
        </div>
      </div>
    </nav>

    <section class="hero">
      <div class="hero-content">
        <h1>The <span class="gradient-text">AI Operating System</span><br />for Modern Business</h1>
        <p class="hero-subtitle">Build, deploy, and scale intelligent agents and workflows with enterprise-grade security.</p>
        <div class="hero-buttons">
          <button class="btn-primary btn-large" @click="goToDashboard">🚀 Launch Dashboard</button>
          <button class="btn-outline btn-large" @click="goToSignup">Get Started →</button>
        </div>
      </div>
    </section>

    <footer class="footer">
      <p>© 2026 SpiderNetOS – AI Operating System. All rights reserved.</p>
    </footer>
  </div>
</template>

<script>
export default {
  methods: {
    goToLogin() { this.$router.push('/login') },
    goToSignup() { this.$router.push('/signup') },
    goToDashboard() { 
      const token = localStorage.getItem('token')
      if (token) {
        this.$router.push('/dashboard')
      } else {
        this.$router.push('/login')
      }
    }
  }
}
</script>

<style scoped>
.landing { min-height: 100vh; background: linear-gradient(135deg, #0f0c29 0%, #1a1a3e 50%, #24243e 100%); color: white; }
.navbar { padding: 1.5rem 2rem; backdrop-filter: blur(10px); background: rgba(15, 23, 42, 0.7); position: sticky; top: 0; z-index: 100; }
.nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.logo { display: flex; align-items: center; gap: 0.5rem; font-size: 1.5rem; font-weight: 700; }
.logo-icon { font-size: 2rem; }
.nav-links { display: flex; gap: 2rem; align-items: center; flex-wrap: wrap; }
.nav-links a { color: #e2e8f0; text-decoration: none; font-weight: 500; transition: color 0.2s; }
.nav-links a:hover { color: #3b82f6; }
.btn-outline { background: transparent; border: 1px solid #3b82f6; color: #3b82f6; padding: 0.5rem 1.25rem; border-radius: 40px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
.btn-outline:hover { background: #3b82f6; color: white; }
.btn-primary { background: #3b82f6; border: none; color: white; padding: 0.5rem 1.25rem; border-radius: 40px; cursor: pointer; font-weight: 600; transition: background 0.2s; }
.btn-primary:hover { background: #2563eb; }
.btn-large { padding: 0.75rem 2rem; font-size: 1rem; }
.hero { text-align: center; padding: 5rem 2rem; max-width: 1200px; margin: 0 auto; }
.hero h1 { font-size: 3.5rem; font-weight: 800; line-height: 1.2; margin-bottom: 1.5rem; }
.gradient-text { background: linear-gradient(135deg, #3b82f6, #8b5cf6); -webkit-background-clip: text; background-clip: text; color: transparent; }
.hero-subtitle { font-size: 1.25rem; color: #cbd5e1; max-width: 700px; margin: 0 auto 2rem; }
.hero-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
.footer { text-align: center; padding: 2rem; border-top: 1px solid #1e293b; color: #94a3b8; }
@media (min-width: 2560px) { .hero h1 { font-size: 5rem; } .hero-subtitle { font-size: 1.5rem; } }
</style>