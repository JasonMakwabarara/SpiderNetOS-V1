<template>
  <AppShell v-if="useShell">
    <router-view />
  </AppShell>
  <router-view v-else />
</template>

<script>
import AppShell from './components/AppShell.vue'

export default {
  components: { AppShell },
  computed: {
    useShell() {
      return this.$route.meta.requiresAuth === true
    },
  },
}
</script>
