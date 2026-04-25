<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import authApi from '@/api/authApi.js';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseButton from '@/components/common/BaseButton.vue';

const router = useRouter();

const form = reactive({
  ho_va_ten: '',
  email: '',
  password: '',
  password_confirmation: '',
  so_dien_thoai: '',
  dia_chi: '',
  ngay_sinh: '',
});

const errors = reactive({
  ho_va_ten: '',
  email: '',
  password: '',
  password_confirmation: '',
  so_dien_thoai: '',
  dia_chi: '',
  ngay_sinh: '',
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

  if (!form.ho_va_ten.trim()) {
    errors.ho_va_ten = 'Vui lòng nhập họ và tên.';
    valid = false;
  } else if (form.ho_va_ten.trim().length > 100) {
    errors.ho_va_ten = 'Họ và tên không vượt quá 100 ký tự.';
    valid = false;
  }

  if (form.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    errors.email = 'Email không hợp lệ.';
    valid = false;
  }

  if (!form.so_dien_thoai.trim()) {
    errors.so_dien_thoai = 'Vui lòng nhập số điện thoại.';
    valid = false;
  } else if (!/^(0|\+84)[0-9]{9,10}$/.test(form.so_dien_thoai.trim())) {
    errors.so_dien_thoai = 'Số điện thoại không đúng định dạng.';
    valid = false;
  }

  if (!form.password) {
    errors.password = 'Vui lòng nhập mật khẩu.';
    valid = false;
  } else if (form.password.length < 8) {
    errors.password = 'Mật khẩu phải có ít nhất 8 ký tự.';
    valid = false;
  }

  if (!form.password_confirmation) {
    errors.password_confirmation = 'Vui lòng xác nhận mật khẩu.';
    valid = false;
  } else if (form.password_confirmation !== form.password) {
    errors.password_confirmation = 'Mật khẩu xác nhận không khớp.';
    valid = false;
  }

  if (form.dia_chi && form.dia_chi.length > 255) {
    errors.dia_chi = 'Địa chỉ không vượt quá 255 ký tự.';
    valid = false;
  }

  return valid;
};

const mapServerErrors = (apiErrors = {}) => {
  Object.keys(apiErrors).forEach((field) => {
    if (field in errors) {
      errors[field] = Array.isArray(apiErrors[field]) ? apiErrors[field][0] : String(apiErrors[field]);
    }
  });
};

const handleRegister = async () => {
  successMessage.value = '';
  if (!validateForm()) return;

  loading.value = true;
  serverError.value = '';

  try {
    const payload = {
      ...form,
      ho_va_ten: form.ho_va_ten.trim(),
      email: form.email.trim() || null,
      so_dien_thoai: form.so_dien_thoai.trim(),
      dia_chi: form.dia_chi?.trim() || null,
      ngay_sinh: form.ngay_sinh || null,
    };

    const res = await authApi.clientRegister(payload);
    const data = res?.data || res;
    successMessage.value = data?.message || 'Đăng ký thành công.';
    const needsEmailActivation = Boolean(
      data?.data?.requires_email_activation ?? data?.requires_email_activation ?? false,
    );

    setTimeout(() => {
      if (needsEmailActivation) {
        router.push({
          name: 'check-email',
          query: { email: payload.email },
        });
        return;
      }
      router.push('/auth/login');
    }, 900);
  } catch (err) {
    const apiErrors = err.response?.data?.errors;
    if (apiErrors) {
      mapServerErrors(apiErrors);
      serverError.value = '';
      return;
    }
    serverError.value = err.response?.data?.message || 'Không thể đăng ký lúc này. Vui lòng thử lại.';
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="client-register-container">
    <div class="illustration-side">
      <div class="overlay-glass">
        <h1>Khám Phá Hành Trình Tuyệt Vời</h1>
        <p>Đặt vé nhanh chóng, thanh toán tiện lợi và trải nghiệm chuyến đi an toàn với hệ thống giám sát AI thông minh.</p>
      </div>
    </div>
    
    <div class="form-side">
      <div class="form-wrapper">
        <div class="auth-header">
          <div class="mobile-logo">BusSafe</div>
          <h2>Tạo tài khoản</h2>
          <p>Trở thành thành viên của BusSafe ngay hôm nay</p>
        </div>
        
        <form @submit.prevent="handleRegister" class="register-form">
          <div class="scroll-area">
            <BaseInput v-model="form.ho_va_ten" label="Họ và tên *" placeholder="Nhập họ và tên..." :error="errors.ho_va_ten" />
            
            <div class="flex-row">
              <BaseInput v-model="form.so_dien_thoai" label="Số điện thoại *" placeholder="09xx xxx xxx" :error="errors.so_dien_thoai" />
              <BaseInput v-model="form.ngay_sinh" type="date" label="Ngày sinh" :error="errors.ngay_sinh" />
            </div>

            <BaseInput v-model="form.email" type="email" label="Email" placeholder="name@example.com" :error="errors.email" />
            
            <div class="flex-row">
              <BaseInput v-model="form.password" type="password" label="Mật khẩu *" placeholder="••••••••" :error="errors.password" />
              <BaseInput v-model="form.password_confirmation" type="password" label="Xác nhận mật khẩu *" placeholder="••••••••" :error="errors.password_confirmation" />
            </div>

            <BaseInput v-model="form.dia_chi" label="Địa chỉ" placeholder="Số nhà, tên đường, tỉnh/thành..." :error="errors.dia_chi" />
          </div>

          <div v-if="serverError" class="error-msg">{{ serverError }}</div>
          <div v-if="successMessage" class="success-msg">{{ successMessage }}</div>
          
          <BaseButton type="submit" block :loading="loading" class="client-btn">
            ĐĂNG KÝ
          </BaseButton>
          
          <div class="login-prompt">
            Đã có tài khoản? <router-link to="/auth/login">Đăng nhập</router-link>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.client-register-container {
  min-height: 100vh;
  display: flex;
  background-color: #f8fafc;
}

/* Illustration Side */
.illustration-side {
  flex: 1.25;
  background: url('https://images.unsplash.com/photo-1570125909232-eb263c188f7e?auto=format&fit=crop&q=80') center/cover no-repeat;
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
  color: #4f46e5;
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
  border-color: #4f46e5 !important;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15) !important;
}
.register-form :deep(.base-input-label) {
  font-size: 0.9rem;
  font-weight: 600;
  margin-bottom: 0.4rem;
}

.client-btn {
  background: linear-gradient(135deg, #0066ff 0%, #0052cc 100%) !important;
  border: none !important;
  padding: 1rem;
  font-size: 1.1rem;
  border-radius: 14px;
  font-weight: 700;
  margin-top: 1.5rem;
  box-shadow: 0 8px 15px -3px rgba(0, 102, 255, 0.3) !important;
}
.client-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 20px -3px rgba(0, 102, 255, 0.4) !important;
  background: linear-gradient(135deg, #3385ff 0%, #0066ff 100%) !important;
}

.login-prompt {
  text-align: center;
  margin-top: 1.5rem;
  font-size: 1rem;
  color: #64748b;
}
.login-prompt a {
  color: #0066ff;
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