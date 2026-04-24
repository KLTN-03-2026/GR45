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
  <div class="register-page">
    <div class="register-card">
      <div class="header">
        <h1>Tạo tài khoản khách hàng</h1>
        <p>Điền thông tin cơ bản để bắt đầu đặt vé nhanh chóng.</p>
      </div>

      <form class="register-form" @submit.prevent="handleRegister">
        <BaseInput
          v-model="form.ho_va_ten"
          label="Họ và tên *"
          placeholder="Nguyen Van A"
          :error="errors.ho_va_ten"
        />

        <BaseInput
          v-model="form.email"
          type="email"
          label="Email"
          placeholder="name@example.com"
          :error="errors.email"
        />

        <div class="row">
          <BaseInput
            v-model="form.password"
            type="password"
            label="Mật khẩu *"
            placeholder="Tối thiểu 8 ký tự"
            :error="errors.password"
          />
          <BaseInput
            v-model="form.password_confirmation"
            type="password"
            label="Xác nhận mật khẩu *"
            placeholder="Nhập lại mật khẩu"
            :error="errors.password_confirmation"
          />
        </div>

        <div class="row">
          <BaseInput
            v-model="form.so_dien_thoai"
            type="text"
            label="Số điện thoại *"
            placeholder="VD: 0912345678"
            :error="errors.so_dien_thoai"
          />
          <BaseInput
            v-model="form.ngay_sinh"
            type="date"
            label="Ngày sinh"
            :error="errors.ngay_sinh"
          />
        </div>

        <BaseInput
          v-model="form.dia_chi"
          type="text"
          label="Địa chỉ"
          placeholder="Số nhà, đường, quận/huyện, tỉnh/thành"
          :error="errors.dia_chi"
        />

        <p v-if="serverError" class="server-error">{{ serverError }}</p>
        <p v-if="successMessage" class="success-msg">{{ successMessage }}</p>

        <BaseButton type="submit" block :loading="loading" class="submit-btn">
          ĐĂNG KÝ TÀI KHOẢN
        </BaseButton>

        <p class="auth-switch">
          Đã có tài khoản?
          <router-link to="/auth/login">Đăng nhập ngay</router-link>
        </p>
      </form>
    </div>
  </div>
</template>

<style scoped>
.register-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
  background: radial-gradient(circle at top, #f1f5f9 0%, #e2e8f0 100%);
}

.register-card {
  width: 100%;
  max-width: 760px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
  padding: 2rem;
}

.header {
  margin-bottom: 1.75rem;
}

.header h1 {
  margin: 0;
  font-size: 1.75rem;
  color: #0f172a;
  letter-spacing: -0.02em;
}

.header p {
  margin: 0.5rem 0 0;
  color: #64748b;
}

.row {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}

@media (min-width: 768px) {
  .row {
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }
}

.register-form :deep(.base-input) {
  background: #f8fafc;
  border-radius: 10px;
  border-color: #dbe3ed;
}

.register-form :deep(.base-input:focus) {
  background: #ffffff;
  border-color: #6366f1 !important;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14) !important;
}

.submit-btn {
  margin-top: 0.5rem;
  border-radius: 12px !important;
  padding: 0.9rem 1rem !important;
  font-weight: 600;
}

.server-error,
.success-msg {
  margin: 0.5rem 0 1rem;
  padding: 0.75rem;
  border-radius: 10px;
  font-size: 0.92rem;
}

.server-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
}

.success-msg {
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #047857;
}

.auth-switch {
  text-align: center;
  margin-top: 1rem;
  color: #64748b;
  font-size: 0.95rem;
}

.auth-switch a {
  color: #4f46e5;
  font-weight: 600;
  text-decoration: none;
}

.auth-switch a:hover {
  text-decoration: underline;
}
</style>
