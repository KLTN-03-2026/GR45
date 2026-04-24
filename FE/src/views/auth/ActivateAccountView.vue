<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import authApi from '@/api/authApi.js';
import BaseButton from '@/components/common/BaseButton.vue';

const route = useRoute();
const router = useRouter();

const loading = ref(true);
const success = ref(false);
const message = ref('Đang xác thực liên kết kích hoạt...');

const goLogin = () => {
  router.push('/auth/login');
};

onMounted(async () => {
  const token = String(route.query?.token || '').trim();
  const email = String(route.query?.email || '').trim();

  if (!token || !email) {
    loading.value = false;
    success.value = false;
    message.value = 'Liên kết kích hoạt không hợp lệ.';
    return;
  }

  try {
    const res = await authApi.activateClientAccount({ token, email });
    success.value = true;
    message.value = res?.message || res?.data?.message || 'Kích hoạt tài khoản thành công.';
  } catch (err) {
    success.value = false;
    message.value =
      err?.response?.data?.errors?.token?.[0] ||
      err?.response?.data?.message ||
      'Kích hoạt tài khoản thất bại hoặc liên kết đã hết hạn.';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="activate-wrap">
    <div class="activate-card">
      <h1>Kích hoạt tài khoản</h1>
      <p :class="success ? 'ok' : 'err'">{{ message }}</p>
      <BaseButton type="button" block :loading="loading" @click="goLogin">
        VỀ TRANG ĐĂNG NHẬP
      </BaseButton>
    </div>
  </div>
</template>

<style scoped>
.activate-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  padding: 1rem;
}
.activate-card {
  width: 100%;
  max-width: 520px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
  padding: 1.5rem;
}
.activate-card h1 {
  margin: 0 0 0.75rem;
  font-size: 1.5rem;
}
.ok {
  color: #047857;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  border-radius: 10px;
  padding: 0.75rem;
}
.err {
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  padding: 0.75rem;
}
</style>

