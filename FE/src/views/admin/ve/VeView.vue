<script setup>
import { ref, reactive, onMounted } from "vue";
import adminApi from "@/api/adminApi";
import BaseTable from "@/components/common/BaseTable.vue";
import BaseButton from "@/components/common/BaseButton.vue";
import BaseInput from "@/components/common/BaseInput.vue";
import BaseModal from "@/components/common/BaseModal.vue";
import { formatCurrency, formatDate } from "@/utils/format";
import { getTicketStatus } from "@/utils/status";

// --- DATA & STATE ---
const tickets = ref([]);
const loading = ref(false);

const pagination = reactive({
  currentPage: 1,
  perPage: 15,
  total: 0,
});

const filters = reactive({
  id_chuyen_xe: "",
  tinh_trang: "",
  search: "",
});

// Cột BaseTable
const tableColumns = [
  { key: "id", label: "Mã Vé" },
  { key: "chuyen_xe", label: "Chuyến Xe" },
  { key: "khach_hang", label: "Khách Hàng" },
  { key: "ghe", label: "Ghế" },
  { key: "tong_tien", label: "Tổng Tiền" },
  { key: "tinh_trang", label: "Trạng Thái" },
  { key: "actions", label: "Hành Động" },
];

// Modal Cập Nhật Trạng Thái
const statusModal = reactive({
  show: false,
  id: null,
  tinh_trang: "",
  loading: false,
});

// Modal Xác Nhận Hủy
const cancelModal = reactive({
  show: false,
  id: null,
  loading: false,
});

// --- API HÀM ---
const fetchTickets = async (page = 1) => {
  try {
    loading.value = true;
    const params = {
      page: page,
      per_page: pagination.perPage,
    };
    if (filters.id_chuyen_xe) params.id_chuyen_xe = filters.id_chuyen_xe;
    if (filters.tinh_trang) params.tinh_trang = filters.tinh_trang;
    if (filters.search) params.search = filters.search;

    const res = await adminApi.getTickets(params);

    let listData = [];
    let pageInfo = {};

    // Parse lồng dữ liệu
    if (res.data?.data?.data?.data) {
      listData = res.data.data.data.data;
      pageInfo = res.data.data.data;
    } else if (res.data?.data?.data) {
      listData = res.data.data.data;
      pageInfo = res.data.data;
    } else if (res.data?.data) {
      listData = res.data.data;
      pageInfo = res.data;
    } else if (Array.isArray(res.data)) {
      listData = res.data;
    }

    tickets.value = Array.isArray(listData) ? listData : [];
    pagination.currentPage = pageInfo.current_page || 1;
    pagination.total = pageInfo.total || 0;
  } catch (error) {
    console.error("Lỗi khi tải danh sách vé:", error);
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  fetchTickets(1);
};

// --- ACTION METHODS ---
const openStatusModal = (ticket) => {
  statusModal.id = ticket.id;
  statusModal.tinh_trang = ticket.tinh_trang;
  statusModal.show = true;
};

const submitUpdateStatus = async () => {
  try {
    statusModal.loading = true;
    await adminApi.updateTicketStatus(statusModal.id, {
      tinh_trang: statusModal.tinh_trang,
    });
    statusModal.show = false;
    fetchTickets(pagination.currentPage);
  } catch (error) {
    console.error("Lỗi cập nhật trạng thái:", error);
    alert("Không thể cập nhật trạng thái vé.");
  } finally {
    statusModal.loading = false;
  }
};

const openCancelModal = (id) => {
  cancelModal.id = id;
  cancelModal.show = true;
};

const executeCancelTicket = async () => {
  try {
    cancelModal.loading = true;
    await adminApi.cancelTicket(cancelModal.id);
    cancelModal.show = false;
    fetchTickets(pagination.currentPage);
  } catch (error) {
    console.error("Lỗi hủy vé:", error);
    alert(error.response?.data?.message || "Có lỗi khi hủy vé.");
  } finally {
    cancelModal.loading = false;
  }
};

onMounted(() => {
  fetchTickets();
});
</script>

<template>
  <div class="admin-page">
    <div class="page-header mb-4">
      <h1 class="page-title">Quản lý Vé</h1>
      <p class="text-muted">
        Danh sách tất cả vé (của toàn hệ thống) được đặt trên các chuyến xe.
      </p>
    </div>

    <!-- Bộ lọc -->
    <div class="filter-card">
      <div class="filter-grid">
        <div class="filter-item search-field">
          <span class="filter-label">Tìm kiếm</span>
          <BaseInput
            v-model="filters.search"
            placeholder="Mã vé, SĐT, Tên khách..."
            @keyup.enter="handleSearch"
          />
        </div>

        <div class="filter-item">
          <span class="filter-label">Chuyến xe ID</span>
          <input
            type="number"
            v-model="filters.id_chuyen_xe"
            class="custom-input"
            placeholder="VD: 5"
            @change="handleSearch"
          />
        </div>

        <div class="filter-item">
          <span class="filter-label">Trạng thái</span>
          <select
            v-model="filters.tinh_trang"
            class="custom-select"
            @change="handleSearch"
          >
            <option value="">-- Tất cả --</option>
            <option value="dang_cho">Đang chờ</option>
            <option value="da_thanh_toan">Đã thanh toán</option>
            <option value="huy">Đã hủy</option>
          </select>
        </div>

        <div class="filter-item action-field">
          <span class="filter-label text-white">.</span>
          <BaseButton @click="handleSearch" variant="primary" block
            >Tìm & Lọc</BaseButton
          >
        </div>
      </div>
    </div>

    <!-- Bảng Dữ Liệu -->
    <div class="table-card">
      <BaseTable :columns="tableColumns" :data="tickets" :loading="loading">
        <!-- Mã Vé -->
        <template #cell(id)="{ value }">
          <span class="fw-bold">V-{{ value.toString().padStart(5, "0") }}</span>
        </template>

        <!-- Chuyến Xe -->
        <template #cell(chuyen_xe)="{ item }">
          <div class="text-sm">
            Chuyến ID: <span class="fw-bold">{{ item.id_chuyen_xe }}</span>
          </div>
        </template>

        <!-- Khách Hàng -->
        <template #cell(khach_hang)="{ item }">
          <div class="fw-bold text-dark">
            {{ item.khach_hang.ten_khach_hang || "Khách vãng lai" }}
          </div>
          <div class="text-sm text-muted">
            {{ item.khach_hang.so_dien_thoai || "Chưa cung cấp SĐT" }}
          </div>
        </template>

        <!-- Ghế -->
        <template #cell(ghe)="{ item }">
          <div class="fw-bold text-primary">
            {{
              Array.isArray(item.danh_sach_ghe)
                ? item.danh_sach_ghe.join(", ")
                : item.danh_sach_ghe
            }}
          </div>
        </template>

        <!-- Tổng Tiền -->
        <template #cell(tong_tien)="{ value }">
          <span class="fw-bold">{{ formatCurrency(value) }}</span>
        </template>

        <!-- Trạng Thái -->
        <template #cell(tinh_trang)="{ value }">
          <span :class="['status-badge', getTicketStatus(value).class]">
            {{ getTicketStatus(value).text }}
          </span>
        </template>

        <!-- Actions -->
        <template #cell(actions)="{ item }">
          <div class="action-buttons">
            <BaseButton
              size="sm"
              variant="outline"
              @click="openStatusModal(item)"
              title="Đổi Trạng Thái"
              >TT</BaseButton
            >

            <BaseButton
              v-if="
                item.tinh_trang !== 'huy' && item.tinh_trang !== 'da_thanh_toan'
              "
              size="sm"
              variant="danger"
              @click="openCancelModal(item.id)"
              >Hủy</BaseButton
            >
          </div>
        </template>
      </BaseTable>

      <!-- Phân trang -->
      <div
        class="pagination-container mt-4 d-flex justify-content-between align-items-center"
      >
        <div class="per-page-selector">
          <span class="text-muted text-sm">Hiển thị: </span>
          <select
            v-model="pagination.perPage"
            @change="handleSearch"
            class="custom-select d-inline-block w-auto mx-2 py-1 px-2"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="30">30</option>
            <option :value="50">50</option>
          </select>
          <span class="text-muted text-sm"> / trang</span>
          <span class="text-muted text-sm ms-3" v-if="pagination.total > 0"
            >(Tổng: {{ pagination.total }})</span
          >
        </div>

        <div class="pagination-controls d-flex align-items-center gap-2">
          <BaseButton
            size="sm"
            variant="outline"
            :disabled="pagination.currentPage <= 1"
            @click="fetchTickets(pagination.currentPage - 1)"
            >Trước</BaseButton
          >
          <span class="page-info fw-bold px-2"
            >Trang {{ pagination.currentPage }}</span
          >
          <BaseButton
            size="sm"
            variant="outline"
            :disabled="tickets.length < pagination.perPage"
            @click="fetchTickets(pagination.currentPage + 1)"
            >Sau</BaseButton
          >
        </div>
      </div>
    </div>

    <!-- MODAL CẬP NHẬT TRẠNG THÁI -->
    <BaseModal
      v-model="statusModal.show"
      title="Cập Nhật Trạng Thái Vé"
      maxWidth="400px"
    >
      <div class="form-group mb-4">
        <label class="base-input-label">Trạng Thái Mới</label>
        <select v-model="statusModal.tinh_trang" class="custom-select">
          <option value="dang_cho">Đang chờ</option>
          <option value="da_thanh_toan">Đã thanh toán (xác nhận cọc)</option>
          <option value="huy">Hủy vé</option>
        </select>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="statusModal.show = false"
          >Đóng</BaseButton
        >
        <BaseButton
          variant="primary"
          :loading="statusModal.loading"
          @click="submitUpdateStatus"
          >Lưu Trạng Thái</BaseButton
        >
      </template>
    </BaseModal>

    <!-- MODAL XÁC NHẬN HỦY VÉ -->
    <BaseModal
      v-model="cancelModal.show"
      title="⚠️ Xác Nhận Hủy Vé"
      maxWidth="450px"
    >
      <div class="text-center py-3">
        <p class="mb-0 text-dark" style="font-size: 1.05rem">
          Bạn có chắc chắn muốn hủy vé này không? Hành động này sẽ giải phóng
          ghế để phục vụ khách khác.
        </p>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="cancelModal.show = false"
          >Giữ Lại</BaseButton
        >
        <BaseButton
          variant="danger"
          :loading="cancelModal.loading"
          @click="executeCancelTicket"
          >Xác Nhận Hủy</BaseButton
        >
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.admin-page {
  padding: 1.5rem;
  font-family: "Inter", system-ui, sans-serif;
}
.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}
.text-muted {
  color: #64748b;
}
.text-dark {
  color: #1e293b;
}
.text-primary {
  color: #4f46e5;
}
.text-sm {
  font-size: 0.875rem;
}
.fw-bold {
  font-weight: 600;
}
.mb-4 {
  margin-bottom: 1.5rem;
}
.mb-0 {
  margin-bottom: 0;
}
.py-3 {
  padding-top: 1rem;
  padding-bottom: 1rem;
}
.ms-3 {
  margin-left: 1rem;
}
.mx-2 {
  margin-left: 0.5rem;
  margin-right: 0.5rem;
}
.px-2 {
  padding-left: 0.5rem;
  padding-right: 0.5rem;
}
.py-1 {
  padding-top: 0.25rem;
  padding-bottom: 0.25rem;
}
.gap-2 {
  gap: 0.5rem;
}
.d-flex {
  display: flex;
}
.d-inline-block {
  display: inline-block;
}
.w-auto {
  width: auto;
}
.justify-content-between {
  justify-content: space-between;
}
.align-items-center {
  align-items: center;
}
.text-center {
  text-align: center;
}

/* Filter Card */
.filter-card {
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(10px);
  border: 1px solid #e2e8f0;
  padding: 1rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.filter-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 160px;
  gap: 1.25rem;
  align-items: flex-start;
}
.filter-item {
  width: 100%;
}
.search-field {
  min-width: 250px;
}
.action-field {
  /* canh giữa chiều cao */
}
.filter-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #475569;
  margin-bottom: 0.4rem;
}
.form-group {
  margin-bottom: 0.5rem;
}

/* Base Input/Select */
.base-input-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.35rem;
}
.custom-input,
.custom-select {
  width: 100%;
  padding: 0.625rem 0.875rem;
  font-size: 1rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background-color: #ffffff;
  color: #1f2937;
  transition: all 0.2s ease-in-out;
  box-sizing: border-box;
}
.custom-input:focus,
.custom-select:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

/* Table Card */
.table-card {
  background: white;
  border-radius: 12px;
  padding: 1rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05),
    0 4px 6px -2px rgba(0, 0, 0, 0.025);
  border: 1px solid rgba(226, 232, 240, 0.8);
}
.action-buttons {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

/* Status Badges */
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-block;
}
.status-pending {
  background: #fdf6b2;
  color: #8a4b08;
}
.status-info {
  background: #dbeafe;
  color: #1e40af;
}
.status-approved {
  background: #dcfce3;
  color: #16a34a;
}
.status-rejected {
  background: #fee2e2;
  color: #dc2626;
}

@media (max-width: 1024px) {
  .filter-grid {
    grid-template-columns: 1fr 1fr;
  }
  .search-field {
    grid-column: span 2;
  }
}

@media (max-width: 640px) {
  .filter-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  .search-field {
    grid-column: span 1;
  }
}
</style>
