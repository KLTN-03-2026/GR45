<script setup>
import { ref, onMounted } from 'vue';
import clientApi from '@/api/clientApi';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseToast from '@/components/common/BaseToast.vue';
import { formatCurrency } from '@/utils/format';

const vouchers = ref([]);
const loading = ref(false);
const huntingIds = ref(new Set()); // Track loading for specific vouchers

const toast = ref({
  show: false,
  message: '',
  type: 'success'
});

const showToast = (message, type = 'success') => {
  toast.value = { show: true, message, type };
  setTimeout(() => toast.value.show = false, 3000);
};

const fetchVouchers = async () => {
  loading.value = true;
  try {
    const res = await clientApi.getHuntableVouchers();
    vouchers.value = res.data || [];
  } catch (error) {
    console.error('Lỗi khi tải voucher:', error);
    showToast('Không thể tải danh sách voucher.', 'error');
  } finally {
    loading.value = false;
  }
};

const huntVoucher = async (voucher) => {
  if (huntingIds.value.has(voucher.id)) return;
  
  huntingIds.value.add(voucher.id);
  try {
    await clientApi.huntVoucher(voucher.id);
    showToast(`Chúc mừng! Bạn đã săn được voucher ${voucher.ma_voucher}`);
    // Refresh list to remove the hunted voucher
    fetchVouchers();
  } catch (error) {
    const msg = error.response?.data?.message || 'Săn voucher thất bại!';
    showToast(msg, 'error');
  } finally {
    huntingIds.value.delete(voucher.id);
  }
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  return new Date(dateStr).toLocaleDateString('vi-VN');
};

onMounted(fetchVouchers);
</script>

<template>
  <div class="hunt-page">
    <BaseToast 
      :visible="toast.show" 
      :message="toast.message" 
      :type="toast.type" 
    />

    <div class="page-header">
      <h1 class="page-title">Săn Voucher Cực Hot 🎁</h1>
      <p class="page-desc">Lưu ngay voucher vào ví để sử dụng khi đặt vé!</p>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="loader"></div>
      <p>Đang tìm voucher tốt nhất cho bạn...</p>
    </div>

    <div v-else-if="vouchers.length === 0" class="empty-state">
      <div class="empty-icon">🎫</div>
      <h3>Hiện không có voucher nào để săn</h3>
      <p>Quay lại sau nhé, chúng mình sẽ cập nhật thêm nhiều ưu đãi!</p>
    </div>

    <div v-else class="voucher-grid">
      <div v-for="v in vouchers" :key="v.id" class="voucher-card">
        <div class="voucher-left">
          <div class="voucher-type">
            {{ v.loai_voucher === 'percent' ? 'GIẢM %' : 'GIẢM TIỀN' }}
          </div>
          <div class="voucher-value">
            {{ v.loai_voucher === 'percent' ? parseFloat(v.gia_tri) + '%' : formatCurrency(v.gia_tri) }}
          </div>
        </div>
        
        <div class="voucher-right">
          <div class="voucher-info">
            <h3 class="v-name">{{ v.ten_voucher }}</h3>
            <p class="v-code">Mã: <span>{{ v.ma_voucher }}</span></p>
            <div class="v-meta">
              <span class="v-date">HSD: {{ formatDate(v.ngay_ket_thuc) }}</span>
              <div class="v-progress">
                <div class="progress-info">
                  <span>Còn lại {{ v.so_luong_con_lai }}</span>
                </div>
                <div class="progress-bar">
                  <div 
                    class="progress-fill" 
                    :style="{ width: (v.so_luong_con_lai / v.so_luong * 100) + '%' }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
          
          <BaseButton 
            class="hunt-btn"
            variant="primary" 
            :loading="huntingIds.has(v.id)"
            @click="huntVoucher(v)"
          >
            Lưu Ngay
          </BaseButton>
        </div>

        <!-- Trang trí đục lỗ -->
        <div class="punch-top"></div>
        <div class="punch-bottom"></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.hunt-page {
  max-width: 1000px;
  margin: 0 auto;
  padding: 2rem 1rem;
}

.page-header {
  text-align: center;
  margin-bottom: 2.5rem;
}

.page-title {
  font-size: 2rem;
  font-weight: 800;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.page-desc {
  color: #64748b;
  font-size: 1.1rem;
}

.voucher-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
  gap: 1.5rem;
}

@media (max-width: 640px) {
  .voucher-grid {
    grid-template-columns: 1fr;
  }
}

.voucher-card {
  position: relative;
  display: flex;
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  border: 1px solid #e2e8f0;
  min-height: 140px;
}

.voucher-left {
  width: 120px;
  background: linear-gradient(135deg, #4f46e5, #3730a3);
  color: white;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  text-align: center;
  border-right: 2px dashed #ffffff44;
}

.voucher-type {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  opacity: 0.9;
  margin-bottom: 4px;
}

.voucher-value {
  font-size: 1.5rem;
  font-weight: 800;
}

.voucher-right {
  flex: 1;
  padding: 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.voucher-info {
  flex: 1;
}

.v-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 4px;
}

.v-code {
  font-size: 0.85rem;
  color: #64748b;
  margin-bottom: 12px;
}

.v-code span {
  font-family: 'Fira Code', monospace;
  font-weight: 700;
  color: #4f46e5;
  background: #eef2ff;
  padding: 2px 6px;
  border-radius: 4px;
}

.v-meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.v-date {
  font-size: 0.75rem;
  color: #94a3b8;
  font-weight: 500;
}

.v-progress {
  width: 100%;
}

.progress-info {
  display: flex;
  justify-content: space-between;
  font-size: 0.7rem;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 4px;
}

.progress-bar {
  height: 6px;
  background: #f1f5f9;
  border-radius: 3px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #4f46e5, #818cf8);
  border-radius: 3px;
  transition: width 0.5s ease;
}

.hunt-btn {
  padding: 0.75rem 1.25rem;
  white-space: nowrap;
}

/* Punch holes decoration */
.punch-top, .punch-bottom {
  position: absolute;
  left: 110px;
  width: 20px;
  height: 20px;
  background: #f8fafc; /* Match page background */
  border-radius: 50%;
  z-index: 2;
  border: 1px solid #e2e8f0;
}

.punch-top {
  top: -10px;
}

.punch-bottom {
  bottom: -10px;
}

.loading-state, .empty-state {
  text-align: center;
  padding: 4rem 2rem;
}

.loader {
  border: 4px solid #f3f3f3;
  border-top: 4px solid #4f46e5;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 1.5rem;
}

.empty-state h3 {
  font-size: 1.5rem;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: #64748b;
}
</style>
