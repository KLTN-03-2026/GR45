<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useAdminStore } from '@/stores/adminStore.js';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';

const router    = useRouter();
const adminStore = useAdminStore();
const form      = reactive({ email: '', password: '' });

const handleLogin = async () => {
  const success = await adminStore.login(form);
  if (success) router.push('/admin');
};
</script>

<template>
  <div class="admin-login-container">
    <div class="glow-bg"></div>
    <div class="form-wrapper">
      <div class="card-title text-center mb-8">
        <div class="logo-circle">🛡️</div>
        <h2>Hệ Thống Quản Trị</h2>
        <p>Trung tâm điều hành DATN</p>
      </div>
      <BaseCard class="admin-card">
        <form @submit.prevent="handleLogin" class="login-form">
          <BaseInput v-model="form.email" type="email" label="Email quản trị" placeholder="admin@example.com" />
          <BaseInput v-model="form.password" type="password" label="Mật khẩu" placeholder="••••••••" />

          <div class="forgot-link">
            <router-link :to="{ name: 'forgot-password', query: { role: 'admin' } }">
              Quên mật khẩu?
            </router-link>
          </div>
          
          <div v-if="adminStore.error" class="error-msg">{{ adminStore.error }}</div>
          
          <BaseButton type="submit" variant="primary" block :loading="adminStore.loading" class="mt-6 submit-btn">
            Đăng nhập hệ thống
          </BaseButton>
        </form>
      </BaseCard>
    </div>
  </div>
</template>

<style scoped>
.admin-login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #0f172a; /* Slate 900 */
  position: relative;
  overflow: hidden;
  padding: 1.5rem; /* Mobile safe area */
}

.glow-bg {
  position: absolute;
  width: 100%;
  max-width: 800px;
  height: 800px;
  background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, rgba(15,23,42,0) 60%);
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  pointer-events: none;
}

.form-wrapper {
  width: 100%;
  max-width: 420px;
  z-index: 10;
}

.text-center { text-align: center; }
.mb-8 { margin-bottom: 2rem; }
.mt-6 { margin-top: 1.75rem; }

.logo-circle {
  width: 72px;
  height: 72px;
  background: rgba(30, 41, 59, 0.8);
  border: 1px solid rgba(71, 85, 105, 0.5);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  margin: 0 auto 1.25rem auto;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
}

.card-title h2 {
  font-size: 1.75rem;
  font-weight: 700;
  margin: 0;
  background: linear-gradient(to right, #f8fafc, #94a3b8);
  background-clip: text;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.card-title p {
  font-size: 0.95rem;
  color: #64748b;
  margin: 0.5rem 0 0 0;
}

.admin-card {
  background: rgba(30, 41, 59, 0.4) !important;
  backdrop-filter: blur(20px) saturate(150%);
  -webkit-backdrop-filter: blur(20px) saturate(150%);
  border: 1px solid rgba(71, 85, 105, 0.4) !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6) !important;
  border-radius: 20px !important;
  padding: 1.5rem 0.5rem;
}

.login-form { display: flex; flex-direction: column; }

.forgot-link {
  text-align: right;
  margin-top: -0.25rem;
  margin-bottom: 1rem;
}

.forgot-link a {
  font-size: 0.9rem;
  color: #a5b4fc;
  font-weight: 600;
  text-decoration: none;
}

.forgot-link a:hover {
  text-decoration: underline;
  color: #c7d2fe;
}

/* CSS cho input trong Dark mode */
.login-form :deep(.base-input) {
  background-color: rgba(15, 23, 42, 0.5) !important;
  border-color: rgba(71, 85, 105, 0.5) !important;
  color: #f8fafc !important;
  font-size: 1.05rem;
  padding: 1rem 1.125rem;
  border-radius: 12px;
}

.login-form :deep(.base-input-label) {
  color: #94a3b8 !important;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.login-form :deep(.base-input:focus) {
  border-color: #6366f1 !important;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25) !important;
  background-color: rgba(15, 23, 42, 0.8) !important;
}

.submit-btn {
  font-weight: 700;
  letter-spacing: 0.5px;
  padding: 1rem;
  font-size: 1.05rem;
  border-radius: 12px;
  background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%) !important;
  border: none !important;
  box-shadow: 0 8px 15px -3px rgba(79, 70, 229, 0.3) !important;
}
.submit-btn:hover {
  background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%) !important;
  transform: translateY(-2px);
  box-shadow: 0 12px 20px -3px rgba(79, 70, 229, 0.4) !important;
}

.error-msg {
  color: #f87171;
  font-size: 0.9rem;
  margin-top: 0.5rem;
  text-align: center;
  background: rgba(239, 68, 68, 0.1);
  padding: 0.6rem;
  border-radius: 8px;
  border: 1px solid rgba(239, 68, 68, 0.2);
}
</style>
