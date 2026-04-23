<script setup>
import { reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useOperatorStore } from '@/stores/operatorStore.js';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseCard from '@/components/common/BaseCard.vue';

const router        = useRouter();
const operatorStore = useOperatorStore();
const form          = reactive({ email: '', password: '' });

const handleLogin = async () => {
  const success = await operatorStore.login(form);
  if (success) router.push('/nha-xe');
};
</script>

<template>
  <div class="operator-login-container">
    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>
    
    <div class="form-wrapper">
      <BaseCard class="operator-card">
        <div class="card-header-custom">
          <div class="icon-box">🏢</div>
          <h2>Cổng Điều Hành</h2>
          <p>Dành riêng cho các Đối Tác Nhà Xe</p>
        </div>
        
        <form @submit.prevent="handleLogin" class="login-form">
          <BaseInput v-model="form.email" type="email" label="Email doanh nghiệp" placeholder="nhaxe@example.com" />
          <BaseInput v-model="form.password" type="password" label="Mật khẩu" placeholder="••••••••" />
          
          <div class="forgot-link">
            <router-link :to="{ name: 'forgot-password', query: { role: 'nha_xe' } }">
              Quên mật khẩu?
            </router-link>
          </div>
          
          <div v-if="operatorStore.error" class="error-msg">{{ operatorStore.error }}</div>
          
          <BaseButton type="submit" block :loading="operatorStore.loading" class="mt-4 custom-btn">
            Đăng Nhập Quản Lý
          </BaseButton>
        </form>
      </BaseCard>
    </div>
  </div>
</template>

<style scoped>
.operator-login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f8fafc; /* Nền xám nhạt */
  position: relative;
  overflow: hidden;
  padding: 1.5rem;
}

/* Các hình khối trang trí nền */
.bg-shape {
  position: absolute;
  border-radius: 50%;
  filter: blur(60px);
  z-index: 1;
  opacity: 0.5;
}
.shape-1 {
  width: 400px;
  height: 400px;
  background: #ccfbf1;
  top: -100px;
  right: -100px;
}
.shape-2 {
  width: 500px;
  height: 500px;
  background: #e0e7ff;
  bottom: -150px;
  left: -150px;
}

.form-wrapper {
  width: 100%;
  max-width: 420px;
  z-index: 10;
}

.operator-card {
  background: rgba(255, 255, 255, 0.85) !important;
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 1) !important;
  border-radius: 24px !important;
  box-shadow: 0 20px 40px -15px rgba(13, 148, 136, 0.15) !important;
  padding: 1.5rem 0.5rem;
}

.card-header-custom {
  text-align: center;
  margin-bottom: 2rem;
}

.icon-box {
  width: 64px;
  height: 64px;
  background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
  color: white;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  margin: 0 auto 1.25rem auto;
  box-shadow: 0 10px 15px -3px rgba(13, 148, 136, 0.3);
}

.card-header-custom h2 {
  font-size: 1.6rem;
  font-weight: 800;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.card-header-custom p {
  font-size: 0.95rem;
  color: #64748b;
  margin: 0;
}

.login-form {
  padding: 0 1rem;
}

.login-form :deep(.base-input) {
  font-size: 1.05rem;
  padding: 0.875rem 1rem;
  border-radius: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}
.login-form :deep(.base-input:focus) {
  background: #ffffff;
  border-color: #0d9488 !important;
  box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15) !important;
}

.forgot-link {
  text-align: right;
  margin-top: -0.25rem;
  margin-bottom: 1.5rem;
}
.forgot-link a {
  font-size: 0.9rem;
  color: #0d9488;
  font-weight: 600;
  text-decoration: none;
}
.forgot-link a:hover {
  text-decoration: underline;
  color: #0f766e;
}

.custom-btn {
  background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%) !important;
  border: none !important;
  padding: 1rem;
  font-size: 1.05rem;
  border-radius: 12px;
  font-weight: 700;
  box-shadow: 0 8px 15px -3px rgba(13, 148, 136, 0.3) !important;
}
.custom-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 20px -3px rgba(13, 148, 136, 0.4) !important;
}

.error-msg {
  color: #ef4444;
  font-size: 0.9rem;
  margin-top: 0.5rem;
  text-align: center;
  background: #fef2f2;
  padding: 0.6rem;
  border-radius: 8px;
  border: 1px solid #fee2e2;
}
</style>
