<script setup>
import ClientHeader from '@/components/layout/ClientHeader.vue'
import ClientFooter from '@/components/layout/ClientFooter.vue'
</script>

<template>
  <div class="default-layout">
    <ClientHeader />
    <main class="main-content">
      <RouterView />
    </main>
    <ClientFooter />
  </div>
</template>

<style scoped>
</style>
