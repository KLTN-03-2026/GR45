<script setup>
import { reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useClientStore } from '@/stores/clientStore.js';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseButton from '@/components/common/BaseButton.vue';

const router      = useRouter();
const route       = useRoute();
const clientStore = useClientStore();
const form        = reactive({ email: '', password: '' });

const handleLogin = async () => {
  const success = await clientStore.login(form);
  if (success) {
    const redirect = route.query.redirect || '/';
    router.push(redirect);
  }
};

const handleGoogleLogin = () => {
  // Redirect trực tiếp tới route Backend xử lý OAuth
  window.location.href = 'http://localhost:8000/auth/google';
};
</script>

<template>
  <div class="client-login-container">
    <div class="illustration-side">
      <div class="overlay-glass">
        <h1>Khám Phá Hành Trình Tuyệt Vời</h1>
        <p>Đặt vé nhanh chóng, thanh toán tiện lợi và trải nghiệm chuyến đi an toàn với hệ thống giám sát AI thông minh.</p>
        <!-- <div class="features">
          <span class="badge">Mạng lưới toàn quốc</span>
          <span class="badge">Trí tuệ nhân tạo AI</span>
          <span class="badge">Thanh toán 1 chạm</span>
        </div> -->
      </div>
    </div>
    
    <div class="form-side">
      <div class="form-wrapper">
        <div class="auth-header">
          <div class="mobile-logo">DATN Bus</div>
          <h2>Xin chào!</h2>
          <p>Mừng bạn quay trở lại hệ thống</p>
        </div>
        
        <form @submit.prevent="handleLogin" class="login-form">
          <BaseInput v-model="form.email" type="email" label="Email / Số điện thoại" placeholder="Nhập định danh..." />
          <BaseInput v-model="form.password" type="password" label="Mật khẩu" placeholder="••••••••" />
          
          <div class="flex-between">
            <label class="remember-me">
              <input type="checkbox" /> Ghi nhớ
            </label>
            <router-link :to="{ name: 'forgot-password' }" class="forgot-link">Quên mật khẩu?</router-link>
          </div>
          
          <div v-if="clientStore.error" class="error-msg">{{ clientStore.error }}</div>
          
          <BaseButton type="submit" block :loading="clientStore.loading" class="client-btn">
            ĐĂNG NHẬP
          </BaseButton>

          <div class="divider">
            <span>Hoặc đăng nhập bằng</span>
          </div>

          <button type="button" @click="handleGoogleLogin" class="google-btn">
            <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google" />
            Đăng nhập với Google
          </button>
          
          <div class="register-prompt">
            Chưa có tài khoản? <router-link to="/auth/register">Đăng ký ngay</router-link>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.client-login-container {
  min-height: 100vh;
  display: flex;
  background-color: #f8fafc;
}

/* Bên trái: Poster minh hoạ (Ẩn trên Mobile) */
.illustration-side {
  flex: 1.25;
  background: url('https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&q=80') center/cover no-repeat;
  display: none;
  position: relative;
}

@media (min-width: 900px) {
  .illustration-side {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4rem;
  }
}

.overlay-glass {
  position: relative;
  z-index: 10;
  max-width: 550px;
  color: white;
}

.overlay-glass h1 {
  font-size: 3.5rem;
  font-weight: 800;
  line-height: 1.15;
  margin-bottom: 1.5rem;
}

.overlay-glass p {
  font-size: 1.15rem;
  line-height: 1.6;
  opacity: 0.9;
  margin-bottom: 2.5rem;
}

.features {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}
.badge {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  padding: 0.6rem 1.25rem;
  border-radius: 20px;
  font-weight: 500;
  font-size: 0.95rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
  display: inline-flex;
  align-items: center;
}

/* Bên phải: Form đăng nhập */
.form-side {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #ffffff;
  padding: 2rem 1.5rem;
}

.form-wrapper {
  width: 100%;
  max-width: 400px;
}

.mobile-logo {
  display: none;
  font-size: 1.75rem;
  font-weight: 800;
  color: #0066ff;
  margin-bottom: 2rem;
  text-align: center;
}

@media (max-width: 899px) {
  .form-side {
    background: #f8fafc;
  }
  .form-wrapper {
    background: white;
    padding: 2.5rem 2rem;
    border-radius: 24px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
  }
  .mobile-logo { display: block; }
}

.auth-header {
  margin-bottom: 2.5rem;
}
.auth-header h2 {
  font-size: 2.25rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 0.5rem 0;
}
.auth-header p {
  color: #64748b;
  font-size: 1.05rem;
  margin: 0;
}

.login-form :deep(.base-input) {
  font-size: 1.05rem;
  padding: 1rem 1.125rem;
  border-radius: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}
.login-form :deep(.base-input:focus) {
  background: #ffffff;
  border-color: #0066ff !important;
  box-shadow: 0 0 0 3px rgba(0, 102, 255, 0.15) !important;
}
.login-form :deep(.base-input-label) {
  font-size: 0.95rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.flex-between {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 0.5rem;
  margin-bottom: 2rem;
}
.remember-me {
  font-size: 0.95rem;
  color: #64748b;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}
.forgot-link {
  font-size: 0.95rem;
  color: #0066ff;
  font-weight: 600;
  text-decoration: none;
}
.forgot-link:hover { text-decoration: underline; }

.client-btn {
  background: linear-gradient(135deg, #0066ff 0%, #0052cc 100%) !important;
  border: none !important;
  padding: 1.125rem;
  font-size: 1.1rem;
  border-radius: 14px;
  font-weight: 700;
  box-shadow: 0 8px 15px -3px rgba(0, 102, 255, 0.3) !important;
}
.client-btn:hover {
  background: linear-gradient(135deg, #3385ff 0%, #0066ff 100%) !important;
  transform: translateY(-2px);
  box-shadow: 0 12px 20px -3px rgba(0, 102, 255, 0.4) !important;
}
.client-btn:active {
  transform: scale(0.98);
}

/* Divider styling */
.divider {
  display: flex;
  align-items: center;
  text-align: center;
  margin: 1.5rem 0;
  color: #94a3b8;
  font-size: 0.9rem;
}
.divider::before,
.divider::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid #e2e8f0;
}
.divider:not(:empty)::before { margin-right: .75em; }
.divider:not(:empty)::after { margin-left: .75em; }

/* Google Button Premium Style */
.google-btn {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 1rem;
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  color: #334155;
  font-size: 1.05rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.google-btn img {
  width: 20px;
  height: 20px;
}
.google-btn:hover {
  background-color: #f8fafc;
  border-color: #cbd5e1;
  transform: translateY(-1px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.google-btn:active {
  transform: scale(0.98);
}

.register-prompt {
  text-align: center;
  margin-top: 2rem;
  font-size: 1rem;
  color: #64748b;
}
.register-prompt a {
  color: #0066ff;
  font-weight: 700;
  text-decoration: none;
}
.register-prompt a:hover {
  text-decoration: underline;
}

.error-msg {
  color: #ef4444;
  font-size: 0.9rem;
  margin-bottom: 1.5rem;
  text-align: center;
  background: #fef2f2;
  padding: 0.75rem;
  border-radius: 12px;
  border: 1px solid #fee2e2;
}
</style>