<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useDriverStore } from '@/stores/driverStore.js';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseButton from '@/components/common/BaseButton.vue';

const driverStore = useDriverStore();
const router      = useRouter();
const form        = reactive({ email: '', password: '' });

const handleLogin = async () => {
  const ok = await driverStore.login(form);
  if (ok) {
    await router.push({ name: 'driver-dashboard' });
  }
};
</script>

<template>
  <div class="driver-login-container">
    <div class="form-wrapper">
      <div class="form-card">
        <div class="brand-info">
          <div class="icon-circle">🚌</div>
          <h2 class="title">Đăng nhập Tài Xế</h2>
        </div>
        
        <form @submit.prevent="handleLogin" class="login-form">
          <div class="input-group">
            <BaseInput v-model="form.email" type="email" label="Số điện thoại / Username" placeholder="Ví dụ: 0987xxx" />
          </div>
          <div class="input-group mt-3">
            <BaseInput v-model="form.password" type="password" label="Mật khẩu App" placeholder="••••••••" />
          </div>
          
          <div v-if="driverStore.error" class="error-msg">{{ driverStore.error }}</div>
          
          <BaseButton type="submit" block :loading="driverStore.loading" class="driver-btn">
            ĐĂNG NHẬP
          </BaseButton>
          
          <div class="help-center">
            <a href="#">📞 Cần hỗ trợ khẩn cấp?</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.driver-login-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
}

.form-wrapper {
  width: 100%;
  max-width: 420px;
}

.form-card {
  background: white;
  border-radius: 24px;
  padding: 2.5rem 2rem;
  box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.1);
}

.brand-info {
  text-align: center;
  margin-bottom: 2rem;
}

.icon-circle {
  width: 64px;
  height: 64px;
  background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  margin: 0 auto 1rem auto;
  color: white;
  box-shadow: 0 8px 16px rgba(234, 88, 12, 0.2);
}

.title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.mt-3 { margin-top: 1.25rem; }

.login-form :deep(.base-input) {
  font-size: 1.05rem;
  padding: 1rem;
  border-radius: 12px;
  background-color: #f8fafc;
  border: 1px solid #cbd5e1;
}

.login-form :deep(.base-input:focus) {
  border-color: #ea580c !important;
  box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.2) !important;
  background-color: #ffffff;
}

.login-form :deep(.base-input-label) {
  color: #475569;
  font-weight: 600;
  font-size: 0.95rem;
  margin-bottom: 0.5rem;
}

.driver-btn {
  background: linear-gradient(135deg, #f97316 0%, #ea580c 100%) !important;
  border: none !important;
  padding: 1rem;
  font-size: 1.05rem;
  font-weight: 700;
  border-radius: 12px;
  margin-top: 2rem;
  box-shadow: 0 8px 15px -3px rgba(234, 88, 12, 0.3) !important;
  letter-spacing: 0.5px;
  width: 100%;
}

.driver-btn:active {
  transform: scale(0.98);
}

.help-center {
  margin-top: 1.5rem;
  text-align: center;
}
.help-center a {
  color: #64748b;
  font-weight: 600;
  font-size: 0.95rem;
  text-decoration: none;
  transition: color 0.2s;
}
.help-center a:hover {
  color: #ea580c;
}

.error-msg {
  color: #dc2626;
  font-size: 0.95rem;
  font-weight: 500;
  margin-top: 1rem;
  text-align: center;
  background: rgba(220, 38, 38, 0.1);
  padding: 0.75rem;
  border-radius: 10px;
}
</style>
