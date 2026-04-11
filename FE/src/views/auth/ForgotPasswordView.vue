<script setup>
import { reactive, ref, computed } from 'vue';
import { useRoute } from 'vue-router';
import authApi from '@/api/authApi';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseButton from '@/components/common/BaseButton.vue';

const route = useRoute();
const ALLOWED_ROLES = ['khach_hang', 'nha_xe', 'admin'];

const effectiveRole = computed(() => {
  const r = route.query.role;
  return typeof r === 'string' && ALLOWED_ROLES.includes(r) ? r : 'khach_hang';
});

const form = reactive({
  email: '',
});
const loading = ref(false);
const message = ref('');
const error = ref('');

const submit = async () => {
  message.value = '';
  error.value = '';
  try {
    loading.value = true;
    const res = await authApi.requestPasswordReset({
      email: form.email,
      role: effectiveRole.value,
    });
    message.value = res?.message || 'Nếu email tồn tại, hệ thống đã gửi hướng dẫn đặt lại mật khẩu.';
  } catch (err) {
    error.value = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()[0]
      : (err.response?.data?.message || 'Không gửi được yêu cầu đặt lại mật khẩu. Vui lòng thử lại.');
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="auth-card-wrap">
    <div class="auth-card">
      <h1>Quên mật khẩu</h1>
      <p>Nhập email để nhận link đặt lại mật khẩu.</p>

      <form @submit.prevent="submit">
        <BaseInput v-model="form.email" type="email" label="Email" placeholder="name@example.com" />

        <div v-if="message" class="msg success">{{ message }}</div>
        <div v-if="error" class="msg error">{{ error }}</div>

        <BaseButton type="submit" block :loading="loading">Gửi link đặt lại mật khẩu</BaseButton>
      </form>
    </div>
  </div>
</template>

<style scoped>
.auth-card-wrap {
  min-height: 100vh;
  display: grid;
  place-items: center;
  background: #f8fafc;
  padding: 20px;
}

.auth-card {
  width: 100%;
  max-width: 480px;
  background: #fff;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
}

h1 {
  margin: 0 0 6px;
  font-size: 24px;
}

p {
  margin: 0 0 16px;
  color: #64748b;
}

.msg {
  margin: 10px 0 12px;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 14px;
}

.msg.success {
  background: #ecfdf3;
  color: #166534;
  border: 1px solid #bbf7d0;
}

.msg.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}
</style>
