<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import operatorApi from "@/api/operatorApi";
import BaseTable from "@/components/common/BaseTable.vue";
import BaseButton from "@/components/common/BaseButton.vue";
import BaseInput from "@/components/common/BaseInput.vue";
import BaseSelect from "@/components/common/BaseSelect.vue";
import BaseModal from "@/components/common/BaseModal.vue";
import BaseToast from "@/components/common/BaseToast.vue";
import { formatCurrency, formatDateOnly } from "@/utils/format";
import { getVoucherStatus } from "@/utils/status";

// --- TOAST ---
const toast = reactive({ visible: false, message: "", type: "success" });
const showToast = (msg, type = "success") => {
  toast.message = msg;
  toast.type = type;
  toast.visible = true;
  setTimeout(() => {
    toast.visible = false;
  }, 3500);
};

// --- DATA & STATE ---
const vouchers = ref([]);
const loading = ref(false);

// Bộ lọc phía client
const searchQuery = ref("");
const filterStatus = ref("");

// Cột bảng
const tableColumns = [
  { key: "ma_voucher", label: "Mã Voucher" },
  { key: "ten_voucher", label: "Tên Voucher" },
  { key: "loai_gia_tri", label: "Loại / Giá Trị" },
  { key: "so_luong", label: "Số Lượng" },
  { key: "thoi_gian", label: "Thời Gian" },
  { key: "trang_thai", label: "Trạng Thái" },
  { key: "tong_giam", label: "Tổng Giảm" },
];

// --- LỌC DỮ LIỆU ---
const filteredVouchers = computed(() => {
  let list = vouchers.value;
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase();
    list = list.filter(
      (v) =>
        v.ma_voucher?.toLowerCase().includes(q) ||
        v.ten_voucher?.toLowerCase().includes(q)
    );
  }
  if (filterStatus.value) {
    list = list.filter((v) => v.trang_thai === filterStatus.value);
  }
  return list;
});

// --- GỌI API ---
const fetchVouchers = async () => {
  try {
    loading.value = true;
    const res = await operatorApi.getVouchers();
    let listData = [];
    if (res.data?.data && Array.isArray(res.data.data)) {
      listData = res.data.data;
    } else if (Array.isArray(res.data)) {
      listData = res.data;
    }
    vouchers.value = listData;
  } catch (error) {
    console.error("Lỗi khi tải danh sách voucher:", error);
    showToast("Không thể tải danh sách voucher!", "error");
  } finally {
    loading.value = false;
  }
};

// --- MODAL TẠO VOUCHER MỚI ---
const isCreateModal = ref(false);
const createLoading = ref(false);
const createForm = reactive({
  ten_voucher: "",
  loai_voucher: "percent",
  gia_tri: "",
  so_luong: "",
  ngay_bat_dau: "",
  ngay_ket_thuc: "",
  dieu_kien: "",
});

const openCreateModal = () => {
  Object.assign(createForm, {
    ten_voucher: "",
    loai_voucher: "percent",
    gia_tri: "",
    so_luong: "",
    ngay_bat_dau: "",
    ngay_ket_thuc: "",
    dieu_kien: "",
  });
  isCreateModal.value = true;
};

const submitCreate = async () => {
  // Kiểm tra dữ liệu cơ bản
  if (!createForm.ten_voucher.trim()) {
    showToast("Vui lòng nhập tên voucher!", "error");
    return;
  }
  if (!createForm.gia_tri || Number(createForm.gia_tri) <= 0) {
    showToast("Vui lòng nhập giá trị hợp lệ!", "error");
    return;
  }
  if (!createForm.so_luong || Number(createForm.so_luong) <= 0) {
    showToast("Vui lòng nhập số lượng!", "error");
    return;
  }
  if (!createForm.ngay_bat_dau || !createForm.ngay_ket_thuc) {
    showToast("Vui lòng chọn ngày bắt đầu và kết thúc!", "error");
    return;
  }

  try {
    createLoading.value = true;
    const payload = {
      ten_voucher: createForm.ten_voucher.trim(),
      loai_voucher: createForm.loai_voucher,
      gia_tri: Number(createForm.gia_tri),
      so_luong: Number(createForm.so_luong),
      ngay_bat_dau: createForm.ngay_bat_dau,
      ngay_ket_thuc: createForm.ngay_ket_thuc,
    };
    if (createForm.dieu_kien.trim()) {
      payload.dieu_kien = createForm.dieu_kien.trim();
    }

    await operatorApi.createVoucher(payload);
    showToast("Gửi yêu cầu tạo voucher thành công, vui lòng chờ Admin duyệt!");
    isCreateModal.value = false;
    fetchVouchers();
  } catch (error) {
    // Hiển thị lỗi validation chi tiết nếu có
    const errors = error.response?.data?.errors;
    if (errors) {
      const firstErr = Object.values(errors).flat()[0];
      showToast(firstErr, "error");
    } else {
      showToast(
        error.response?.data?.message || "Tạo voucher thất bại!",
        "error"
      );
    }
  } finally {
    createLoading.value = false;
  }
};

// --- NHÃN LOẠI VOUCHER ---
const loaiVoucherLabel = (type) => {
  if (type === "percent") return { text: "Phần trăm (%)", cls: "badge-purple" };
  if (type === "fixed") return { text: "Cố định (VNĐ)", cls: "badge-blue" };
  return { text: "—", cls: "" };
};

const displayGiaTri = (voucher) => {
  if (voucher.loai_voucher === "percent") {
    return `${parseFloat(voucher.gia_tri)}%`;
  }
  return formatCurrency(voucher.gia_tri);
};

// --- ĐẶT LẠI BỘ LỌC ---
const resetFilters = () => {
  searchQuery.value = "";
  filterStatus.value = "";
};

onMounted(() => {
  fetchVouchers();
});
</script>

<template>
  <div class="operator-page">
    <BaseToast :visible="toast.visible" :message="toast.message" :type="toast.type" />

    <!-- Tiêu đề -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Voucher</h1>
        <p class="page-sub">
          Quản lý và tạo yêu cầu phát hành voucher giảm giá cho khách hàng.
        </p>
      </div>
      <BaseButton @click="openCreateModal" variant="primary">+ Tạo Voucher Mới</BaseButton>
    </div>

    <!-- Bộ lọc -->
    <div class="filter-card">
      <div class="filter-row">
        <div class="search-box">
          <BaseInput v-model="searchQuery" placeholder="Tìm mã voucher, tên voucher..." />
        </div>

        <div class="filter-group">
          <BaseSelect v-model="filterStatus" label="Trạng thái" :options="[
            { value: '', label: 'Tất cả' },
            { value: 'cho_duyet', label: 'Chờ duyệt' },
            { value: 'hoat_dong', label: 'Hoạt động' },
            { value: 'tam_ngung', label: 'Tạm ngưng' },
            { value: 'vo_hieu', label: 'Vô hiệu' },
            { value: 'het_han', label: 'Hết hạn' },
          ]" />
        </div>

        <BaseButton @click="resetFilters" class="mb-3" variant="outline">Đặt lại</BaseButton>
      </div>
    </div>

    <!-- Bảng -->
    <div class="table-card">
      <BaseTable :columns="tableColumns" :data="filteredVouchers" :loading="loading">
        <!-- Mã Voucher -->
        <template #cell(ma_voucher)="{ value }">
          <span class="code-badge">{{ value }}</span>
        </template>

        <!-- Tên Voucher -->
        <template #cell(ten_voucher)="{ item }">
          <div class="voucher-name">{{ item.ten_voucher }}</div>
          <div class="voucher-condition" v-if="item.dieu_kien">
            {{ item.dieu_kien }}
          </div>
        </template>

        <!-- Loại / Giá Trị -->
        <template #cell(loai_gia_tri)="{ item }">
          <span :class="['mini-badge', loaiVoucherLabel(item.loai_voucher).cls]">
            {{ loaiVoucherLabel(item.loai_voucher).text }}
          </span>
          <div class="value-display">{{ displayGiaTri(item) }}</div>
        </template>

        <!-- Số Lượng -->
        <template #cell(so_luong)="{ item }">
          <div class="quantity-display">
            <span class="qty-remaining">{{ item.so_luong_con_lai }}</span>
            <span class="qty-separator">/</span>
            <span class="qty-total">{{ item.so_luong }}</span>
          </div>
          <div class="qty-bar">
            <div class="qty-bar-fill" :style="{
              width:
                item.so_luong > 0
                  ? (item.so_luong_con_lai / item.so_luong) * 100 + '%'
                  : '0%',
            }"></div>
          </div>
        </template>

        <!-- Thời Gian -->
        <template #cell(thoi_gian)="{ item }">
          <div class="time-range">
            <div class="time-item">
              <span class="time-icon">📅</span>
              {{ formatDateOnly(item.ngay_bat_dau) }}
            </div>
            <div class="time-item">
              <span class="time-icon">🏁</span>
              {{ formatDateOnly(item.ngay_ket_thuc) }}
            </div>
          </div>
        </template>

        <!-- Trạng Thái -->
        <template #cell(trang_thai)="{ value }">
          <span :class="['status-badge', getVoucherStatus(value).class]">
            {{ getVoucherStatus(value).text }}
          </span>
        </template>

        <!-- Tổng Giảm -->
        <template #cell(tong_giam)="{ item }">
          <span class="price-value">{{
            formatCurrency(item.tong_tien_giam)
          }}</span>
        </template>
      </BaseTable>

      <!-- Thông tin tổng -->
      <div class="table-footer" v-if="filteredVouchers.length > 0">
        <span class="text-muted">
          Hiển thị {{ filteredVouchers.length }} / {{ vouchers.length }} voucher
        </span>
      </div>
    </div>

    <!-- ===== MODAL TẠO VOUCHER MỚI ===== -->
    <BaseModal v-model="isCreateModal" title="Tạo Yêu Cầu Phát Hành Voucher" maxWidth="620px">
      <div class="info-banner" style="margin-bottom: 16px">
        💡 Sau khi tạo, voucher sẽ ở trạng thái <strong>Chờ duyệt</strong> cho
        đến khi Admin phê duyệt.
      </div>

      <form @submit.prevent="submitCreate" class="form-grid-2">
        <!-- Tên voucher -->
        <div class="form-group full-width">
          <label class="form-label">Tên Voucher *</label>
          <input type="text" v-model="createForm.ten_voucher" class="custom-input"
            placeholder="VD: Giảm giá khách hàng mới" required />
        </div>

        <!-- Loại voucher -->
        <div class="form-group">
          <BaseSelect v-model="createForm.loai_voucher" label="Loại Voucher *" :options="[
            { value: 'percent', label: '📊 Phần trăm (%)' },
            { value: 'fixed', label: '💰 Cố định (VNĐ)' },
          ]" />
        </div>

        <!-- Giá trị -->
        <div class="form-group">
          <label class="form-label">
            Giá Trị *
            <span class="hint">
              ({{ createForm.loai_voucher === "percent" ? "%" : "VNĐ" }})
            </span>
          </label>
          <input type="number" v-model="createForm.gia_tri" class="custom-input" :placeholder="createForm.loai_voucher === 'percent' ? 'VD: 10' : 'VD: 50000'
            " min="1" required />
        </div>

        <!-- Số lượng -->
        <div class="form-group">
          <label class="form-label">Số Lượng Phát Hành *</label>
          <input type="number" v-model="createForm.so_luong" class="custom-input" placeholder="VD: 100" min="1"
            required />
        </div>

        <!-- Ngày bắt đầu -->
        <div class="form-group">
          <label class="form-label">Ngày Bắt Đầu *</label>
          <input type="date" v-model="createForm.ngay_bat_dau" class="custom-input" required />
        </div>

        <!-- Ngày kết thúc -->
        <div class="form-group full-width-half">
          <label class="form-label">Ngày Kết Thúc *</label>
          <input type="date" v-model="createForm.ngay_ket_thuc" class="custom-input" required />
        </div>

        <!-- Điều kiện -->
        <div class="form-group full-width">
          <label class="form-label">
            Điều Kiện Áp Dụng
            <span class="optional">(không bắt buộc)</span>
          </label>
          <input type="text" v-model="createForm.dieu_kien" class="custom-input"
            placeholder="VD: Áp dụng cho vé trên 200k" />
        </div>
      </form>

      <template #footer>
        <BaseButton variant="secondary" @click="isCreateModal = false">Hủy</BaseButton>
        <BaseButton variant="primary" :loading="createLoading" @click="submitCreate">
          🎟️ Gửi Yêu Cầu
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.operator-page {
  padding: 1.5rem;
  font-family: "Inter", system-ui, sans-serif;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.35rem 0;
}

.page-sub {
  color: #64748b;
  font-size: 0.925rem;
  margin: 0;
}

/* Bộ lọc */
.filter-card {
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(10px);
  border: 1px solid #e2e8f0;
  padding: 1rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.filter-row {
  display: flex;
  gap: 1rem;
  align-items: flex-end;
  flex-wrap: wrap;
}

.search-box {
  flex: 1;
  min-width: 220px;
}

.filter-group {
  min-width: 140px;
}

.filter-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 500;
  color: #475569;
  margin-bottom: 0.35rem;
}

.custom-select,
.custom-input {
  width: 100%;
  padding: 0.625rem 0.875rem;
  font-size: 0.95rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background-color: #fff;
  color: #1f2937;
  transition: all 0.2s ease-in-out;
  box-sizing: border-box;
}

.custom-select:focus,
.custom-input:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

/* Bảng */
.table-card {
  background: white;
  border-radius: 12px;
  padding: 1rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05),
    0 4px 6px -2px rgba(0, 0, 0, 0.025);
  border: 1px solid rgba(226, 232, 240, 0.8);
}

.table-footer {
  padding: 0.75rem 0.5rem 0;
  font-size: 0.85rem;
}

/* Mã voucher */
.code-badge {
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
  color: #065f46;
  padding: 0.3rem 0.6rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.3px;
  font-family: "Fira Code", "Courier New", monospace;
}

/* Tên + điều kiện */
.voucher-name {
  font-weight: 600;
  color: #1e293b;
  font-size: 0.925rem;
}

.voucher-condition {
  font-size: 0.8rem;
  color: #94a3b8;
  margin-top: 2px;
}

/* Badge loại */
.mini-badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}

.badge-purple {
  background: #f3e8ff;
  color: #7c3aed;
}

.badge-blue {
  background: #dbeafe;
  color: #2563eb;
}

.value-display {
  font-weight: 700;
  color: #1e293b;
  font-size: 0.95rem;
  margin-top: 4px;
}

/* Thanh số lượng */
.quantity-display {
  font-size: 0.95rem;
  font-weight: 600;
}

.qty-remaining {
  color: #16a34a;
}

.qty-separator {
  color: #94a3b8;
  margin: 0 2px;
}

.qty-total {
  color: #64748b;
}

.qty-bar {
  width: 100%;
  height: 4px;
  background: #f1f5f9;
  border-radius: 4px;
  margin-top: 4px;
  overflow: hidden;
}

.qty-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #22c55e, #4ade80);
  border-radius: 4px;
  transition: width 0.4s ease;
}

/* Thời gian */
.time-range {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.time-item {
  font-size: 0.85rem;
  color: #475569;
  white-space: nowrap;
}

.time-icon {
  margin-right: 2px;
}

/* Giá */
.price-value {
  font-weight: 700;
  color: #dc2626;
  font-size: 0.925rem;
}

/* Status */
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-block;
  white-space: nowrap;
}

.status-pending {
  background: #fdf6b2;
  color: #8a4b08;
}

.status-approved {
  background: #dcfce3;
  color: #16a34a;
}

.status-info {
  background: #dbeafe;
  color: #1e40af;
}

.status-rejected {
  background: #fee2e2;
  color: #dc2626;
}

.status-expired {
  background: #f1f5f9;
  color: #64748b;
}

.text-muted {
  color: #64748b;
  font-size: 0.85rem;
}

/* Form modal */
.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.full-width {
  grid-column: 1 / -1;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.4rem;
}

.hint {
  font-weight: 400;
  color: #94a3b8;
  font-size: 0.8rem;
}

.optional {
  font-weight: 400;
  color: #94a3b8;
  font-size: 0.8rem;
}

.info-banner {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1e40af;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-size: 0.875rem;
  line-height: 1.5;
}

/* Responsive */
@media (max-width: 768px) {
  .form-grid-2 {
    grid-template-columns: 1fr;
  }

  .filter-row {
    flex-direction: column;
    align-items: stretch;
  }

  .search-box {
    min-width: 100%;
  }

  .page-header {
    flex-direction: column;
  }
}

@media (max-width: 640px) {
  .operator-page {
    padding: 1rem;
  }
}
</style>