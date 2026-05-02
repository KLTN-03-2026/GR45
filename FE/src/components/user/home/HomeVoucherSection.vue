<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import clientApi from '@/api/clientApi';
import { formatCurrency } from '@/utils/format';

const router = useRouter();
const vouchers = ref([]);
const loading = ref(false);

const fetchTopVouchers = async () => {
  loading.value = true;
  try {
    const res = await clientApi.getHuntableVouchers();
    // Lấy tối đa 3 voucher hot nhất để hiển thị ở Home
    vouchers.value = (res.data || []).slice(0, 3);
  } catch (error) {
    console.error('Lỗi khi tải voucher hot:', error);
  } finally {
    loading.value = false;
  }
};

const goToHunting = () => {
  router.push('/san-voucher');
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('vi-VN');
};

onMounted(fetchTopVouchers);
</script>

<template>
  <section v-if="vouchers.length > 0" class="voucher-section">
    <div class="section-header">
      <div class="header-content">
        <h2 class="section-title">Ưu Đãi Độc Quyền 🎁</h2>
        <p class="section-desc">Săn ngay voucher giảm giá cực hời để chuyến đi thêm trọn vẹn.</p>
      </div>
      <button @click="goToHunting" class="view-all-btn">
        Xem tất cả
        <span class="material-symbols-outlined">chevron_right</span>
      </button>
    </div>

    <div class="voucher-list">
      <div v-for="v in vouchers" :key="v.id" class="home-voucher-card" @click="goToHunting">
        <div class="v-left">
          <span class="v-label">{{ v.loai_voucher === 'percent' ? 'GIẢM %' : 'GIẢM TIỀN' }}</span>
          <span class="v-value">{{ v.loai_voucher === 'percent' ? parseFloat(v.gia_tri) + '%' : formatCurrency(v.gia_tri) }}</span>
        </div>
        <div class="v-right">
          <h3 class="v-title">{{ v.ten_voucher }}</h3>
          <p class="v-hsd">HSD: {{ formatDate(v.ngay_ket_thuc) }}</p>
          <div class="v-footer">
            <span class="v-stock">Còn lại {{ v.so_luong_con_lai }}</span>
            <span class="v-action">Lấy mã</span>
          </div>
        </div>
        <!-- Trang trí đục lỗ -->
        <div class="punch punch-top"></div>
        <div class="punch punch-bottom"></div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.voucher-section {
  padding: 2rem 0;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 2rem;
}

.section-title {
  font-size: 1.875rem;
  font-weight: 800;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.section-desc {
  color: #64748b;
  font-size: 1.05rem;
}

.view-all-btn {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  color: #4f46e5;
  font-weight: 700;
  font-size: 0.95rem;
  background: none;
  border: none;
  cursor: pointer;
  transition: gap 0.2s;
}

.view-all-btn:hover {
  gap: 0.5rem;
}

.voucher-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 1.5rem;
}

.home-voucher-card {
  position: relative;
  display: flex;
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
  border: 1px solid #e2e8f0;
  cursor: pointer;
  transition: transform 0.3s, box-shadow 0.3s;
}

.home-voucher-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.v-left {
  width: 100px;
  background: linear-gradient(135deg, #4f46e5, #3730a3);
  color: white;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  text-align: center;
}

.v-label {
  font-size: 0.65rem;
  font-weight: 700;
  opacity: 0.8;
  margin-bottom: 2px;
}

.v-value {
  font-size: 1.25rem;
  font-weight: 800;
}

.v-right {
  flex: 1;
  padding: 1rem;
  display: flex;
  flex-direction: column;
}

.v-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 4px;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.v-hsd {
  font-size: 0.75rem;
  color: #94a3b8;
  margin-bottom: auto;
}

.v-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 1rem;
}

.v-stock {
  font-size: 0.7rem;
  font-weight: 600;
  color: #64748b;
  background: #f1f5f9;
  padding: 2px 8px;
  border-radius: 4px;
}

.v-action {
  font-size: 0.8rem;
  font-weight: 700;
  color: #4f46e5;
}

.punch {
  position: absolute;
  left: 90px;
  width: 20px;
  height: 20px;
  background: #f8fafc; /* Match page bg */
  border-radius: 50%;
  z-index: 2;
  border: 1px solid #e2e8f0;
}

.punch-top { top: -10px; }
.punch-bottom { bottom: -10px; }

@media (max-width: 640px) {
  .section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
  }
}
</style>
