<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import adminApi from "@/api/adminApi";
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

// Bộ lọc tìm kiếm
const searchQuery = ref("");
const filterStatus = ref("");
const filterType = ref("");

// Cột hiển thị bảng
const tableColumns = [
  { key: "ma_voucher", label: "Mã Voucher" },
  { key: "ten_voucher", label: "Tên Voucher" },
  { key: "loai_gia_tri", label: "Loại / Giá Trị" },
  { key: "so_luong", label: "Số Lượng" },
  { key: "thoi_gian", label: "Thời Gian" },
  { key: "nha_xe", label: "Nhà Xe" },
  { key: "trang_thai", label: "Trạng Thái" },
  { key: "actions", label: "Hành Động" },
];

// --- LỌC DỮ LIỆU PHÍA CLIENT ---
const filteredVouchers = computed(() => {
  let list = vouchers.value;

  // Lọc theo từ khoá tìm kiếm (mã hoặc tên voucher)
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase();
    list = list.filter(
      (v) =>
        v.ma_voucher?.toLowerCase().includes(q) ||
        v.ten_voucher?.toLowerCase().includes(q),
    );
  }

  // Lọc theo trạng thái
  if (filterStatus.value) {
    list = list.filter((v) => v.trang_thai === filterStatus.value);
  }

  // Lọc theo loại voucher
  if (filterType.value) {
    list = list.filter((v) => v.loai_voucher === filterType.value);
  }

  return list;
});

// --- GỌI API ---
const fetchVouchers = async () => {
  try {
    loading.value = true;
    const res = await adminApi.getVouchers();
    // Parse cấu trúc lồng dữ liệu quen thuộc
    let listData = [];
    if (res.data?.data && Array.isArray(res.data.data)) {
      listData = res.data.data;
    } else if (Array.isArray(res.data)) {
      listData = res.data;
    }
    vouchers.value = listData;
  } catch (error) {
    console.error("Lỗi khi tải danh sách voucher:", error);
    showToast(`Không thể tải danh sách voucher: ${error.message || error.response?.statusText}`, "error");
  } finally {
    loading.value = false;
  }
};

// --- MODAL DUYỆT / ĐỔI TRẠNG THÁI ---
const statusModal = reactive({
  show: false,
  id: null,
  ma_voucher: "",
  current: "",
  trang_thai: "",
  loading: false,
});

const openStatusModal = (voucher) => {
  statusModal.id = voucher.id;
  statusModal.ma_voucher = voucher.ma_voucher;
  statusModal.current = voucher.trang_thai;
  statusModal.trang_thai = voucher.trang_thai;
  statusModal.show = true;
};

const submitApprove = async () => {
  try {
    statusModal.loading = true;
    await adminApi.approveVoucher(statusModal.id, {
      trang_thai: statusModal.trang_thai,
    });
    showToast("Cập nhật trạng thái voucher thành công!");
    statusModal.show = false;
    fetchVouchers();
  } catch (error) {
    const msg =
      error.response?.data?.message || "Không thể cập nhật trạng thái!";
    showToast(msg, "error");
  } finally {
    statusModal.loading = false;
  }
};

// --- NHÃN LOẠI VOUCHER ---
const loaiVoucherLabel = (type) => {
  if (type === "percent") return { text: "Phần trăm (%)", cls: "badge-purple" };
  if (type === "fixed") return { text: "Cố định (VNĐ)", cls: "badge-blue" };
  return { text: "—", cls: "" };
};

// Hiển thị giá trị tuỳ loại
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
  filterType.value = "";
  fetchVouchers(); // Gọi lại API để làm mới table
};

onMounted(() => {
  fetchVouchers();
});
</script>

<template>
  <div class="admin-page">
    <BaseToast :visible="toast.visible" :message="toast.message" :type="toast.type" />

    <!-- Tiêu đề trang -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Voucher</h1>
        <p class="page-sub">
          Xem toàn bộ voucher trên hệ thống. Duyệt hoặc thay đổi trạng thái
          voucher do Nhà xe yêu cầu.
        </p>
      </div>
      <div class="header-stats" v-if="vouchers.length > 0">
        <div class="stat-chip stat-total">
          <span class="stat-number">{{ vouchers.length }}</span>
          <span class="stat-label">Tổng</span>
        </div>
        <div class="stat-chip stat-pending">
          <span class="stat-number">{{
            vouchers.filter((v) => v.trang_thai === "cho_duyet" || v.trang_thai === "pending").length
          }}</span>
          <span class="stat-label">Chờ duyệt</span>
        </div>
        <div class="stat-chip stat-active">
          <span class="stat-number">{{
            vouchers.filter((v) => v.trang_thai === "hoat_dong" || v.trang_thai === "active").length
          }}</span>
          <span class="stat-label">Hoạt động</span>
        </div>
      </div>
    </div>

    <!-- Bộ lọc -->
    <div class="filter-card">
      <div class="filter-row">
        <div class="search-box">
          <label class="filter-label">Tìm kiếm</label>
          <BaseInput v-model="searchQuery" placeholder="Tìm mã voucher, tên voucher..." />
        </div>

        <div class="filter-group">
          <BaseSelect v-model="filterStatus" label="Trạng thái" :options="[
            { value: '', label: 'Tất cả' },
            { value: 'pending', label: 'Chờ duyệt' },
            { value: 'active', label: 'Hoạt động' },
            { value: 'stopped', label: 'Tạm ngưng' },
            { value: 'expired', label: 'Vô hiệu / Hết hạn' },
          ]" />
        </div>

        <div class="filter-group">
          <BaseSelect v-model="filterType" label="Loại" :options="[
            { value: '', label: 'Tất cả' },
            { value: 'percent', label: 'Phần trăm' },
            { value: 'fixed', label: 'Cố định' },
          ]" />
        </div>
        <div class="filter-action">
          <BaseButton type="button" @click.prevent="resetFilters" variant="outline" class="btn-reset">
            Đặt lại
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- Bảng dữ liệu -->
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

        <!-- Nhà Xe -->
        <template #cell(nha_xe)="{ item }">
          <span v-if="item.nha_xe" class="nha-xe-name">
            {{ item.nha_xe.ten_nha_xe }}
          </span>
          <span v-else class="text-muted">ID: {{ item.id_nha_xe }}</span>
        </template>

        <!-- Trạng Thái -->
        <template #cell(trang_thai)="{ value }">
          <span :class="['status-badge', getVoucherStatus(value).class]">
            {{ getVoucherStatus(value).text }}
          </span>
        </template>

        <!-- Hành Động -->
        <template #cell(actions)="{ item }">
          <div class="action-buttons">
            <BaseButton size="sm" variant="primary" @click="openStatusModal(item)">
              Duyệt / TT
            </BaseButton>
          </div>
        </template>
      </BaseTable>

      <!-- Thông tin tổng -->
      <div class="table-footer" v-if="filteredVouchers.length > 0">
        <span class="text-muted">
          Hiển thị {{ filteredVouchers.length }} / {{ vouchers.length }} voucher
        </span>
      </div>
    </div>

    <!-- ===== MODAL DUYỆT / ĐỔI TRẠNG THÁI ===== -->
    <BaseModal v-model="statusModal.show" title="Duyệt / Thay Đổi Trạng Thái Voucher" maxWidth="480px">
      <div class="status-update-body">
        <p class="voucher-code-label">
          Voucher: <strong>{{ statusModal.ma_voucher }}</strong>
        </p>
        <p class="current-status-label">
          Trạng thái hiện tại:
          <span :class="[
            'status-badge',
            getVoucherStatus(statusModal.current).class,
          ]">
            {{ getVoucherStatus(statusModal.current).text }}
          </span>
        </p>

        <div class="form-group" style="margin-top: 16px">
          <label class="form-label">Trạng Thái Mới *</label>
          <div class="status-options">
            <label v-for="opt in [
              { v: 'pending', t: '⏳ Chờ duyệt' },
              { v: 'active', t: '✅ Hoạt động' },
              { v: 'stopped', t: '⏸️ Tạm ngưng' },
              { v: 'expired', t: '🚫 Vô hiệu / Hết hạn' },
            ]" :key="opt.v" class="status-radio-opt" :class="{ 'opt-active': statusModal.trang_thai === opt.v }">
              <input type="radio" :value="opt.v" v-model="statusModal.trang_thai" style="display: none" />
              {{ opt.t }}
            </label>
          </div>
        </div>

        <div class="info-banner" style="margin-top: 12px" v-if="statusModal.trang_thai === 'active'">
          ✅ Khi duyệt voucher, khách hàng sẽ có thể sử dụng mã này khi đặt vé.
        </div>
        <div class="info-banner warning-banner" style="margin-top: 12px" v-if="statusModal.trang_thai === 'expired'">
          ⚠️ Vô hiệu / Hết hạn voucher sẽ khiến mã này không thể sử dụng được nữa.
        </div>
      </div>

      <template #footer>
        <BaseButton variant="secondary" @click="statusModal.show = false">Hủy</BaseButton>
        <BaseButton variant="primary" :loading="statusModal.loading" @click="submitApprove">Xác Nhận</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.admin-page {
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

/* Thẻ thống kê nhanh */
.header-stats {
  display: flex;
  gap: 0.75rem;
}

.stat-chip {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.5rem 1rem;
  border-radius: 12px;
  min-width: 64px;
}

.stat-number {
  font-size: 1.25rem;
  font-weight: 700;
}

.stat-label {
  font-size: 0.7rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.stat-total {
  background: #f1f5f9;
  color: #475569;
}

.stat-pending {
  background: #fef3c7;
  color: #92400e;
}

.stat-active {
  background: #dcfce7;
  color: #166534;
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
  gap: 1.25rem;
  align-items: flex-end;
  flex-wrap: wrap;
}

.search-box {
  flex: 1;
  min-width: 250px;
}

.filter-group {
  min-width: 170px;
}

.filter-action {
  display: flex;
  align-items: flex-end;
  margin-bottom: 1rem;
}

.btn-reset {
  height: 42px;
  /* Đồng nhất chiều cao */
}

.filter-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 500;
  color: #475569;
  margin-bottom: 0.35rem;
}

.custom-select {
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

.custom-select:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

/* Bảng */
.table-card {
  background: white;
  border-radius: 12px;
  padding: 1rem;
  box-shadow:
    0 10px 15px -3px rgba(0, 0, 0, 0.05),
    0 4px 6px -2px rgba(0, 0, 0, 0.025);
  border: 1px solid rgba(226, 232, 240, 0.8);
}

.table-footer {
  padding: 0.75rem 0.5rem 0;
  font-size: 0.85rem;
}

/* Mã voucher */
.code-badge {
  background: linear-gradient(135deg, #eef2ff, #e0e7ff);
  color: #4338ca;
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

/* Badge loại voucher */
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

/* Nhà xe */
.nha-xe-name {
  font-weight: 600;
  color: #1e293b;
  font-size: 0.9rem;
}

/* Status badges */
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

/* Hành động */
.action-buttons {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.text-muted {
  color: #64748b;
  font-size: 0.85rem;
}

/* Modal nội dung */
.status-update-body {
  padding: 0.5rem 0;
}

.voucher-code-label {
  font-size: 1rem;
  color: #334155;
  margin: 0 0 8px 0;
}

.current-status-label {
  font-size: 0.925rem;
  color: #475569;
  margin: 0;
}

.form-group {
  margin-bottom: 0.5rem;
}

.form-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.5rem;
}

.status-options {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.status-radio-opt {
  padding: 0.5rem 1rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  font-size: 0.875rem;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.status-radio-opt:hover {
  border-color: #a5b4fc;
  background: #f8fafc;
}

.status-radio-opt.opt-active {
  border-color: #4f46e5;
  background: #eef2ff;
  color: #4338ca;
  font-weight: 600;
  box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.15);
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

.warning-banner {
  background: #fef3c7;
  border-color: #fcd34d;
  color: #92400e;
}

/* Responsive */
@media (max-width: 1024px) {
  .header-stats {
    width: 100%;
    justify-content: flex-start;
  }

  .filter-row {
    flex-direction: column;
    align-items: stretch;
  }

  .search-box {
    min-width: 100%;
  }
}

@media (max-width: 640px) {
  .admin-page {
    padding: 1rem;
  }

  .page-header {
    flex-direction: column;
  }
}
</style>