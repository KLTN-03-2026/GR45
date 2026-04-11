<script setup>
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import authApi from '@/api/authApi';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseButton from '@/components/common/BaseButton.vue';

const route = useRoute();
const router = useRouter();

const form = reactive({
  role: route.query.role || 'khach_hang',
  email: route.query.email || '',
  token: route.query.token || '',
  mat_khau_moi: '',
  mat_khau_moi_confirmation: '',
});

const hasResetToken = computed(() => typeof form.token === 'string' && form.token.length > 0);

function loginRouteForRole(role) {
  const r = (role || 'khach_hang').toLowerCase();
  if (r === 'admin') return { name: 'admin-login' };
  if (r === 'nha_xe') return { name: 'operator-login' };
  return { name: 'client-login' };
}

const loading = ref(false);
const message = ref('');
const error = ref('');

const submit = async () => {
  message.value = '';
  error.value = '';
  if (!hasResetToken.value) {
    error.value = 'Liên kết không hợp lệ. Vui lòng mở đúng link trong email đặt lại mật khẩu.';
    return;
  }
  try {
    loading.value = true;
    const res = await authApi.resetPassword(form);
    message.value = res?.message || 'Đặt lại mật khẩu thành công.';
    setTimeout(() => router.push(loginRouteForRole(form.role)), 1200);
  } catch (err) {
    error.value = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat()[0]
      : (err.response?.data?.message || 'Đặt lại mật khẩu thất bại. Vui lòng thử lại.');
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="auth-card-wrap">
    <div class="auth-card">
      <h1>Đặt lại mật khẩu</h1>
      <p>Tạo mật khẩu mới cho tài khoản của bạn.</p>

      <form @submit.prevent="submit">
        <BaseInput v-model="form.email" type="email" label="Email" placeholder="name@example.com" />
        <p v-if="!hasResetToken" class="hint warning">
          Thiếu token trên đường dẫn. Hãy dùng đúng liên kết gửi kèm trong email.
        </p>
        <BaseInput v-model="form.mat_khau_moi" type="password" label="Mật khẩu mới" placeholder="Tối thiểu 6 ký tự" />
        <BaseInput v-model="form.mat_khau_moi_confirmation" type="password" label="Xác nhận mật khẩu mới"
          placeholder="Nhập lại mật khẩu mới" />

        <div v-if="message" class="msg success">{{ message }}</div>
        <div v-if="error" class="msg error">{{ error }}</div>

        <BaseButton type="submit" block :loading="loading" :disabled="!hasResetToken">Xác nhận đặt lại mật khẩu
        </BaseButton>
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

.hint.warning {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 14px;
  background: #fffbeb;
  color: #92400e;
  border: 1px solid #fde68a;
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
