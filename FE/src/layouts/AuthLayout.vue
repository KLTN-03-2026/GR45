<script setup>
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import ChatAiWidget from '@/components/chat/ChatAiWidget.vue'

const route = useRoute()

/** Đăng nhập / đăng ký / kích hoạt / quên mật khẩu — khách, không hiện trên admin/operator/driver login */
const CLIENT_AUTH_ROUTE_NAMES = new Set([
  'client-login',
  'client-register',
  'forgot-password',
  'reset-password',
  'activate-account',
  'check-email',
])

const showClientChatAi = computed(() => CLIENT_AUTH_ROUTE_NAMES.has(String(route.name || '')))
</script>

<template>
  <div class="auth-layout">
    <RouterView />
    <ChatAiWidget v-if="showClientChatAi" />
  </div>
</template>

<style scoped>
</style>
