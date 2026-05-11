<script setup>
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useClientStore } from '@/stores/clientStore.js';

const route = useRoute();
const router = useRouter();
const clientStore = useClientStore();

onMounted(async () => {
  const token = route.query.token;

  if (token) {
    // Lưu token vào store và localStorage theo đúng key của dự án
    const TOKEN_KEY = 'auth.client.token';
    clientStore.token = token;
    localStorage.setItem(TOKEN_KEY, token);
    
    // Fetch profile để đồng bộ dữ liệu user
    const success = await clientStore.fetchProfile();
    
    if (success) {
      router.push('/');
    } else {
      router.push('/auth/login?error=profile_fetch_failed');
    }
  } else {
    router.push('/auth/login?error=no_token');
  }
});
</script>

<template>
  <div class="callback-container">
    <div class="loader-wrapper">
      <div class="spinner"></div>
      <p>Đang xác thực tài khoản Google...</p>
    </div>
  </div>
</template>

<style scoped>
.callback-container {
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
}
.loader-wrapper {
  text-align: center;
}
.spinner {
  width: 50px;
  height: 50px;
  border: 5px solid #e2e8f0;
  border-top: 5px solid #0066ff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
p {
  color: #64748b;
  font-weight: 500;
}
</style>
