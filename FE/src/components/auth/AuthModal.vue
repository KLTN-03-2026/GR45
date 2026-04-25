<script setup>
import { reactive, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useClientStore } from '@/stores/clientStore.js';
import authApi from '@/api/authApi.js';

const props = defineProps({});

const emit = defineEmits(['close']);
const router = useRouter();
const clientStore = useClientStore();

// State
const mode = computed({
  get: () => clientStore.authMode,
  set: (val) => clientStore.authMode = val
});
const loginForm = reactive({ email: '', password: '' });
const regForm = reactive({
  ho_va_ten: '',
  email: '',
  password: '',
  password_confirmation: '',
  so_dien_thoai: '',
  dia_chi: '',
  ngay_sinh: '',
});
const regErrors = reactive({});
const regLoading = ref(false);
const regSuccess = ref('');

// Switch modes
const setMode = (newMode) => {
  mode.value = newMode;
  clientStore.error = '';
  regSuccess.value = '';
};

// Handle Login
const handleLogin = async () => {
  const success = await clientStore.login(loginForm);
  if (success) {
    clientStore.closeAuthModal();
    window.location.reload(); // Reload to update state across header
  }
};

// Handle Register
const handleRegister = async () => {
  regSuccess.value = '';
  Object.keys(regErrors).forEach(key => regErrors[key] = '');
  
  if (!regForm.ho_va_ten || !regForm.so_dien_thoai || !regForm.password) {
     clientStore.error = 'Vui lòng điền đầy đủ các trường bắt buộc.';
     return;
  }

  regLoading.value = true;
  try {
    const res = await authApi.clientRegister({ ...regForm });
    regSuccess.value = res?.data?.message || 'Đăng ký thành công! Đang chuyển sang đăng nhập...';
    setTimeout(() => {
      setMode('login');
      loginForm.email = regForm.email || regForm.so_dien_thoai;
    }, 2000);
  } catch (err) {
    const apiErrors = err.response?.data?.errors;
    if (apiErrors) {
      Object.keys(apiErrors).forEach(k => regErrors[k] = apiErrors[k][0]);
    }
    clientStore.error = err.response?.data?.message || 'Đăng ký không thành công.';
  } finally {
    regLoading.value = false;
  }
};
</script>

<template>
  <Transition name="fade">
    <div v-if="clientStore.isAuthModalOpen" class="auth-modal-overlay" @click.self="clientStore.closeAuthModal()">
      <Transition name="zoom">
        <div class="auth-modal-card">
          <!-- Close Button -->
          <button class="close-btn" @click="clientStore.closeAuthModal()">
            <span class="material-symbols-outlined">close</span>
          </button>

          <!-- Left Side: Branding/Info (Hidden on small mobile) -->
          <div class="auth-modal-info">
            <div class="brand">
              <span class="material-symbols-outlined brand-icon">directions_bus</span>
              <span class="brand-name">BusSafe</span>
            </div>
            <div class="info-content">
              <h3 v-if="mode === 'login'">Chào mừng quay trở lại!</h3>
              <h3 v-else>Bắt đầu hành trình mới!</h3>
              <p>Hệ thống đặt vé thông minh BusSafe mang đến sự an toàn và tiện lợi tối đa cho mỗi chuyến đi của bạn.</p>
            </div>
            <div class="info-footer">
              <p>Hỗ trợ 24/7: hotro@bussafe.vn</p>
            </div>
          </div>

          <!-- Right Side: Forms -->
          <div class="auth-modal-forms">
            <!-- Tabs -->
            <div class="auth-tabs">
              <button :class="{ active: mode === 'login' }" @click="setMode('login')">Đăng nhập</button>
              <button :class="{ active: mode === 'register' }" @click="setMode('register')">Đăng ký</button>
            </div>

            <!-- Login Form -->
            <div v-if="mode === 'login'" class="form-container">
              <form @submit.prevent="handleLogin" class="auth-form">
                <div class="form-group">
                  <label>Email hoặc Số điện thoại</label>
                  <div class="input-box">
                    <span class="material-symbols-outlined">alternate_email</span>
                    <input v-model="loginForm.email" type="text" placeholder="Nhập định danh..." required />
                  </div>
                </div>
                <div class="form-group">
                  <div class="flex-between">
                    <label>Mật khẩu</label>
                    <a href="#" class="forgot-link">Quên?</a>
                  </div>
                  <div class="input-box">
                    <span class="material-symbols-outlined">lock</span>
                    <input v-model="loginForm.password" type="password" placeholder="••••••••" required />
                  </div>
                </div>
                
                <div v-if="clientStore.error && mode === 'login'" class="error-msg">{{ clientStore.error }}</div>
                
                <button type="submit" class="submit-btn" :disabled="clientStore.loading">
                  <span v-if="clientStore.loading" class="spinner"></span>
                  <span v-else>ĐĂNG NHẬP NGAY</span>
                </button>
              </form>
            </div>

            <!-- Register Form -->
            <div v-else class="form-container reg-container">
              <form @submit.prevent="handleRegister" class="auth-form">
                <div class="form-group">
                  <label>Họ và tên *</label>
                  <div class="input-box">
                    <span class="material-symbols-outlined">person</span>
                    <input v-model="regForm.ho_va_ten" type="text" placeholder="Nguyễn Văn A" required />
                  </div>
                </div>
                
                <div class="form-grid">
                   <div class="form-group">
                    <label>Số điện thoại *</label>
                    <div class="input-box" :class="{ error: regErrors.so_dien_thoai }">
                      <input v-model="regForm.so_dien_thoai" type="text" placeholder="0912xxxxxx" required />
                    </div>
                  </div>
                  <div class="form-group">
                    <label>Email</label>
                    <div class="input-box" :class="{ error: regErrors.email }">
                      <input v-model="regForm.email" type="email" placeholder="a@gmail.com" />
                    </div>
                  </div>
                </div>

                <div class="form-grid">
                  <div class="form-group">
                    <label>Mật khẩu *</label>
                    <div class="input-box">
                      <input v-model="regForm.password" type="password" placeholder="••••••••" required />
                    </div>
                  </div>
                  <div class="form-group">
                    <label>Nhập lại *</label>
                    <div class="input-box">
                      <input v-model="regForm.password_confirmation" type="password" placeholder="••••••••" required />
                    </div>
                  </div>
                </div>
                
                <div v-if="clientStore.error && mode === 'register'" class="error-msg">{{ clientStore.error }}</div>
                <div v-if="regSuccess" class="success-msg">{{ regSuccess }}</div>
                
                <button type="submit" class="submit-btn" :disabled="regLoading">
                  <span v-if="regLoading" class="spinner"></span>
                  <span v-else>TẠO TÀI KHOẢN</span>
                </button>
              </form>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </Transition>
</template>

<style scoped>
.auth-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, 0.4); /* Giảm độ tối một chút */
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  font-family: 'Manrope', sans-serif;
}

.auth-modal-card {
  width: 100%;
  max-width: 900px;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  border-radius: 40px;
  display: flex;
  overflow: hidden;
  position: relative;
  box-shadow: 0 50px 100px -20px rgba(0, 0, 0, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.5);
}

.close-btn {
  position: absolute;
  top: 1.5rem;
  right: 1.5rem;
  z-index: 20;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #f1f5f9;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #64748b;
  transition: all 0.2s;
}

.close-btn:hover {
  background: #e2e8f0;
  color: #0f172a;
  transform: rotate(90deg);
}

/* Info Side */
.auth-modal-info {
  flex: 0 0 320px;
  background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 100%);
  padding: 3rem 2rem;
  color: white;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
}

.auth-modal-info::before {
  content: "";
  position: absolute;
  inset: 0;
  background: url("https://www.transparenttextures.com/patterns/cubes.png");
  opacity: 0.1;
  pointer-events: none;
}

@media (max-width: 768px) {
  .auth-modal-info { display: none; }
  .auth-modal-card { max-width: 480px; }
}

.brand { display: flex; align-items: center; gap: 0.5rem; }
.brand-icon { font-size: 32px; color: #3b82f6; }
.brand-name { font-size: 1.5rem; font-weight: 800; }

.info-content h3 { font-size: 1.75rem; font-weight: 800; margin-bottom: 1rem; }
.info-content p { font-size: 0.95rem; opacity: 0.8; line-height: 1.6; }
.info-footer { font-size: 0.8rem; opacity: 0.5; }

/* Forms Side */
.auth-modal-forms {
  flex: 1;
  padding: 3rem;
  background: white;
}

@media (max-width: 480px) {
  .auth-modal-forms { padding: 2rem 1.5rem; }
}

.auth-tabs {
  display: flex;
  gap: 2rem;
  margin-bottom: 2.5rem;
  border-bottom: 2px solid #f1f5f9;
}

.auth-tabs button {
  padding-bottom: 0.75rem;
  font-size: 1.1rem;
  font-weight: 800;
  color: #94a3b8;
  border: none;
  background: none;
  cursor: pointer;
  position: relative;
  transition: all 0.3s;
}

.auth-tabs button.active {
  color: #1e40af;
}

.auth-tabs button.active::after {
  content: "";
  position: absolute;
  bottom: -2px;
  left: 0;
  width: 100%;
  height: 2px;
  background: #1e40af;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 700;
  color: #475569;
  margin-bottom: 0.5rem;
}

.input-box {
  display: flex;
  align-items: center;
  background: #f8fafc;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 0 1rem;
  height: 48px;
  transition: all 0.3s;
}

.input-box:focus-within {
  border-color: #3b82f6;
  background: white;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.input-box span { color: #94a3b8; margin-right: 0.75rem; font-size: 20px; }
.input-box input {
  flex: 1;
  background: none;
  border: none;
  outline: none;
  font-family: inherit;
  font-size: 0.95rem;
  font-weight: 600;
  color: #1e293b;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

@media (max-width: 600px) {
  .form-grid { grid-template-columns: 1fr; }
}

.flex-between { display: flex; justify-content: space-between; align-items: center; }
.forgot-link { font-size: 0.85rem; font-weight: 700; color: #3b82f6; text-decoration: none; }

.submit-btn {
  background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
  color: white;
  border: none;
  height: 52px;
  border-radius: 14px;
  font-size: 1rem;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 10px 20px -5px rgba(30, 64, 175, 0.3);
  margin-top: 1rem;
}

.submit-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 15px 30px -5px rgba(30, 64, 175, 0.4);
}

.error-msg { background: #fef2f2; color: #dc2626; padding: 0.75rem; border-radius: 10px; font-size: 0.85rem; font-weight: 700; text-align: center; }
.success-msg { background: #f0fdf4; color: #15803d; padding: 0.75rem; border-radius: 10px; font-size: 0.85rem; font-weight: 700; text-align: center; }

/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.zoom-enter-active, .zoom-leave-active { transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s; }
.zoom-enter-from, .zoom-leave-to { transform: scale(0.9); opacity: 0; }

.spinner {
  width: 20px;
  height: 20px;
  border: 3px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
