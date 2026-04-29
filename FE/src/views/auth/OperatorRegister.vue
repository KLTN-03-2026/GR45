<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
// import authApi from '@/api/authApi.js';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseButton from '@/components/common/BaseButton.vue';

const router = useRouter();

const form = reactive({
  ten_nha_xe: '',
  dia_chi: '',
  email: '',
  so_dien_thoai: '',
});

const errors = reactive({
  ten_nha_xe: '',
  dia_chi: '',
  email: '',
  so_dien_thoai: '',
});

const loading = ref(false);
const successMessage = ref('');
const serverError = ref('');

const clearErrors = () => {
  Object.keys(errors).forEach((key) => {
    errors[key] = '';
  });
  serverError.value = '';
};

const validateForm = () => {
  clearErrors();
  let valid = true;

  if (!form.ten_nha_xe.trim()) {
    errors.ten_nha_xe = 'Vui lòng nhập tên nhà xe.';
    valid = false;
  }

  if (!form.dia_chi.trim()) {
    errors.dia_chi = 'Vui lòng nhập địa chỉ trụ sở.';
    valid = false;
  }

  if (form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    errors.email = 'Email không hợp lệ.';
    valid = false;
  }

  if (!form.so_dien_thoai.trim()) {
    errors.so_dien_thoai = 'Vui lòng nhập số điện thoại người đại diện.';
    valid = false;
  } else if (!/^(0|\+84)[0-9]{9,10}$/.test(form.so_dien_thoai.trim())) {
    errors.so_dien_thoai = 'Số điện thoại không đúng định dạng.';
    valid = false;
  }

  return valid;
};

const handleRegister = async () => {
  successMessage.value = '';
  if (!validateForm()) return;

  loading.value = true;
  serverError.value = '';

  try {
    // API Call - Tạm thời mô phỏng xử lý thành công vì chưa có api backend cho operator register
    // const payload = { ...form };
    // await authApi.operatorRegister(payload);
    
    await new Promise(resolve => setTimeout(resolve, 1000));
    successMessage.value = 'Đăng ký thành công! Chúng tôi sẽ liên hệ lại trong thời gian sớm nhất.';
    
    setTimeout(() => {
      router.push('/');
    }, 2000);

  } catch (err) {
    serverError.value = err.response?.data?.message || 'Không thể đăng ký lúc này. Vui lòng thử lại.';
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="operator-register-container">
    <div class="illustration-side">
      <div class="overlay-glass">
        <h1>Nâng Tầm Dịch Vụ Nhà Xe</h1>
        <p>Gia nhập hệ sinh thái BusSafe để quản lý chuyến đi thông minh, mở rộng mạng lưới khách hàng và tăng trưởng doanh thu.</p>
        
        <div class="features">
          <span class="badge">
            <span class="material-symbols-outlined">dashboard_customize</span> Quản lý tập trung
          </span>
          <span class="badge">
            <span class="material-symbols-outlined">trending_up</span> Tăng doanh thu
          </span>
          <span class="badge">
            <span class="material-symbols-outlined">share_location</span> Live Tracking
          </span>
        </div>
      </div>
    </div>
    
    <div class="form-side">
      <div class="form-wrapper">
        <div class="auth-header">
          <div class="mobile-logo">BusSafe Partner</div>
          <h2>Đăng ký đối tác</h2>
          <p>Điền thông tin để bắt đầu hợp tác cùng BusSafe</p>
        </div>
        
        <form @submit.prevent="handleRegister" class="register-form">
          <div class="scroll-area">
            <BaseInput 
              v-model="form.ten_nha_xe" 
              label="Tên nhà xe *" 
              placeholder="Nhập tên nhà xe (VD: Phương Trang, Thành Bưởi...)" 
              :error="errors.ten_nha_xe" 
            />
            
            <BaseInput 
              v-model="form.dia_chi" 
              label="Địa chỉ trụ sở *" 
              placeholder="Nhập địa chỉ trụ sở chính..." 
              :error="errors.dia_chi" 
            />

            <div class="flex-row">
              <BaseInput 
                v-model="form.so_dien_thoai" 
                label="SĐT người đại diện *" 
                placeholder="09xx xxx xxx" 
                :error="errors.so_dien_thoai" 
              />
              <BaseInput 
                v-model="form.email" 
                type="email" 
                label="Email liên hệ (tùy chọn)" 
                placeholder="name@example.com" 
                :error="errors.email" 
              />
            </div>
          </div>

          <div v-if="serverError" class="error-msg">{{ serverError }}</div>
          <div v-if="successMessage" class="success-msg">{{ successMessage }}</div>
          
          <BaseButton type="submit" block :loading="loading" class="operator-btn">
            ĐĂNG KÝ HỢP TÁC
          </BaseButton>
          
          <div class="login-prompt">
            Đã có tài khoản đối tác? <router-link to="/auth/operator-login">Đăng nhập</router-link>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.operator-register-container {
  min-height: 100vh;
  display: flex;
  background-color: #f8fafc;
}

/* Illustration Side */
.illustration-side {
  flex: 1.25;
  background: url('https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&q=80') center/cover no-repeat;
  display: none;
  position: relative;
}

.illustration-side::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  background: linear-gradient(135deg, rgba(30, 58, 138, 0.85) 0%, rgba(37, 99, 235, 0.65) 100%);
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
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
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
  gap: 0.4rem;
}

/* Form Side */
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
  max-width: 500px;
}

.mobile-logo {
  display: none;
  font-size: 1.75rem;
  font-weight: 800;
  color: #1e40af;
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
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
  }
  .mobile-logo { display: block; }
}

.auth-header {
  margin-bottom: 2rem;
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

.flex-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0;
}

@media (min-width: 640px) {
  .flex-row {
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
  }
}

.register-form :deep(.base-input) {
  font-size: 1rem;
  padding: 0.85rem 1rem;
  border-radius: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}
.register-form :deep(.base-input:focus) {
  background: #ffffff;
  border-color: #1e40af !important;
  box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15) !important;
}
.register-form :deep(.base-input-label) {
  font-size: 0.9rem;
  font-weight: 600;
  margin-bottom: 0.4rem;
}

.operator-btn {
  background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%) !important;
  border: none !important;
  padding: 1rem;
  font-size: 1.1rem;
  border-radius: 14px;
  font-weight: 700;
  margin-top: 1.5rem;
  box-shadow: 0 8px 15px -3px rgba(30, 64, 175, 0.3) !important;
}
.operator-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 20px -3px rgba(30, 64, 175, 0.4) !important;
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
}

.login-prompt {
  text-align: center;
  margin-top: 1.5rem;
  font-size: 1rem;
  color: #64748b;
}
.login-prompt a {
  color: #2563eb;
  font-weight: 700;
  text-decoration: none;
}
.login-prompt a:hover {
  text-decoration: underline;
}

.error-msg, .success-msg {
  padding: 0.85rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  font-size: 0.95rem;
  text-align: center;
  font-weight: 500;
}

.error-msg {
  background: #fff1f2;
  color: #be123c;
  border: 1px solid #ffe4e6;
}

.success-msg {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #dcfce7;
}
</style>
