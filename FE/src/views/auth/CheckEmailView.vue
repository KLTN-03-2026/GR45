<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BaseButton from '@/components/common/BaseButton.vue';

const route = useRoute();
const router = useRouter();

const email = computed(() => String(route.query?.email || '').trim());

const goLogin = () => {
  router.push('/auth/login');
};
</script>

<template>
  <div class="check-email-wrap">
    <div class="check-email-card">
      <h1>Kiểm tra email của bạn</h1>
      <p class="desc">
        Tài khoản đã được tạo thành công. Chúng tôi đã gửi liên kết kích hoạt đến:
      </p>
      <p class="email">{{ email || 'email đã đăng ký' }}</p>
      <p class="note">
        Vui lòng mở hộp thư và nhấn vào liên kết để kích hoạt tài khoản trước khi đăng nhập.
      </p>
      <BaseButton type="button" block @click="goLogin">
        TÔI ĐÃ KÍCH HOẠT, ĐI ĐĂNG NHẬP
      </BaseButton>
    </div>
  </div>
</template>

<style scoped>
.check-email-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  padding: 1rem;
}

.check-email-card {
  width: 100%;
  max-width: 560px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
  padding: 1.5rem;
}

.check-email-card h1 {
  margin: 0 0 0.75rem;
  font-size: 1.6rem;
  color: #0f172a;
}

.desc {
  margin: 0 0 0.35rem;
  color: #475569;
}

.email {
  margin: 0 0 0.75rem;
  font-weight: 700;
  color: #1d4ed8;
}

.note {
  margin: 0 0 1rem;
  color: #334155;
}
</style>

