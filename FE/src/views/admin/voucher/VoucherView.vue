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
const operators = ref([]);
const customers = ref([]);
const loading = ref(false);
const fetchLoading = ref(false);

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
    showToast("Không thể tải danh sách voucher!", "error");
  } finally {
    loading.value = false;
  }
};

const fetchOperators = async () => {
  try {
    const res = await adminApi.getOperatorsListMinimal();
    operators.value = res.data?.data || res.data || [];
  } catch (error) {
    console.error("Lỗi khi tải danh sách nhà xe:", error);
  }
};

const fetchCustomers = async () => {
  try {
    const res = await adminApi.getClientsListMinimal();
    customers.value = res.data?.data || res.data || [];
  } catch (error) {
    console.error("Lỗi khi tải danh sách khách hàng:", error);
  }
};

// --- MODAL TẠO VOUCHER ADMIN ---
const createModal = reactive({
  show: false,
  loading: false,
  form: {
    ten_voucher: "",
    loai_voucher: "percent",
    gia_tri: "",
    so_luong: "",
    ngay_bat_dau: "",
    ngay_ket_thuc: "",
    dieu_kien: "",
    target_type: "all", // all, specific_nha_xe, specific_khach_hang, target_criteria
    id_nha_xes: [],
    id_khach_hangs: [],
    tinh_trang_khach_hangs: [],
    hang_thanh_viens: [],
    is_public: false,
  }
});

const openCreateModal = () => {
  createModal.form = {
    ten_voucher: "",
    loai_voucher: "percent",
    gia_tri: "",
    so_luong: "",
    ngay_bat_dau: "",
    ngay_ket_thuc: "",
    dieu_kien: "",
    target_type: "all",
    id_nha_xes: [],
    id_khach_hangs: [],
    tinh_trang_khach_hangs: [],
    hang_thanh_viens: [],
    is_public: false,
  };
  createModal.show = true;
};

const submitCreate = async () => {
  try {
    createModal.loading = true;
    const payload = { ...createModal.form };
    
    // Xử lý targeting
    if (payload.target_type === 'all') {
      payload.id_nha_xes = [];
      payload.id_khach_hangs = [];
    } else if (payload.target_type === 'specific_nha_xe') {
      payload.id_khach_hangs = [];
    } else if (payload.target_type === 'specific_khach_hang') {
      payload.id_nha_xes = [];
      payload.tinh_trang_khach_hangs = [];
      payload.hang_thanh_viens = [];
    } else if (payload.target_type === 'target_criteria') {
      payload.id_nha_xes = [];
      payload.id_khach_hangs = [];
    }
    
    delete payload.target_type;

    await adminApi.createVoucher(payload);
    showToast("Tạo voucher admin thành công!");
    createModal.show = false;
    fetchVouchers();
  } catch (error) {
    let msg = "Lỗi khi tạo voucher!";
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      msg = Object.values(errors)[0][0]; // Lấy lỗi đầu tiên
    } else if (error.response?.data?.message) {
      msg = error.response.data.message;
    }
    showToast(msg, "error");
  } finally {
    createModal.loading = false;
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
    let msg = "Không thể cập nhật trạng thái!";
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      msg = Object.values(errors)[0][0];
    } else if (error.response?.data?.message) {
      msg = error.response.data.message;
    }
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
};

onMounted(() => {
  fetchVouchers();
  fetchOperators();
  fetchCustomers();
});
</script>

<template>
  <div class="admin-page">
    <BaseToast
      :visible="toast.visible"
      :message="toast.message"
      :type="toast.type"
    />

    <!-- Tiêu đề trang -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Voucher</h1>
        <p class="page-sub">
          Xem toàn bộ voucher trên hệ thống. Duyệt hoặc thay đổi trạng thái
          voucher do Nhà xe yêu cầu.
        </p>
      </div>
      <BaseButton variant="primary" @click="openCreateModal">
        + Tạo Voucher Mới
      </BaseButton>
    </div>
    <div class="header-stats" v-if="vouchers.length > 0">
      <div class="stat-card">
        <div class="stat-icon icon-total">📊</div>
        <div class="stat-info">
          <span class="stat-value">{{ vouchers.length }}</span>
          <span class="stat-desc">Tổng Voucher</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon icon-pending">⏳</div>
        <div class="stat-info">
          <span class="stat-value">{{
            vouchers.filter((v) => v.trang_thai === "cho_duyet").length
          }}</span>
          <span class="stat-desc">Chờ duyệt</span>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon icon-active">✅</div>
        <div class="stat-info">
          <span class="stat-value">{{
            vouchers.filter((v) => v.trang_thai === "hoat_dong").length
          }}</span>
          <span class="stat-desc">Hoạt động</span>
        </div>
      </div>
    </div>

    <!-- Bộ lọc -->
    <div class="filter-card">
      <div class="filter-row">
        <div class="search-box">
          <label class="filter-label">Tìm kiếm</label>
          <BaseInput
            v-model="searchQuery"
            placeholder="Tìm mã voucher, tên voucher..."
          />
        </div>

        <div class="filter-group">
          <BaseSelect
            v-model="filterStatus"
            label="Trạng thái"
            :options="[
              { value: '', label: 'Tất cả' },
              { value: 'cho_duyet', label: 'Chờ duyệt' },
              { value: 'hoat_dong', label: 'Hoạt động' },
              { value: 'tam_ngung', label: 'Tạm ngưng' },
              { value: 'vo_hieu', label: 'Vô hiệu' },
              { value: 'het_han', label: 'Hết hạn' },
            ]"
          />
        </div>

        <div class="filter-group">
          <BaseSelect
            v-model="filterType"
            label="Loại"
            :options="[
              { value: '', label: 'Tất cả' },
              { value: 'percent', label: 'Phần trăm' },
              { value: 'fixed', label: 'Cố định' },
            ]"
          />
        </div>
        <div class="filter-group">
          <label class="filter-label">.</label>
          <BaseButton @click="resetFilters" variant="outline"
            >Đặt lại</BaseButton
          >
        </div>
      </div>
    </div>

    <!-- Bảng dữ liệu -->
    <div class="table-card">
      <BaseTable
        :columns="tableColumns"
        :data="filteredVouchers"
        :loading="loading"
      >
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
          <span
            :class="['mini-badge', loaiVoucherLabel(item.loai_voucher).cls]"
          >
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
            <div
              class="qty-bar-fill"
              :style="{
                width:
                  item.so_luong > 0
                    ? (item.so_luong_con_lai / item.so_luong) * 100 + '%'
                    : '0%',
              }"
            ></div>
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
            <BaseButton
              size="sm"
              variant="primary"
              @click="openStatusModal(item)"
            >
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
    <BaseModal
      v-model="statusModal.show"
      title="Duyệt / Thay Đổi Trạng Thái Voucher"
      maxWidth="480px"
    >
      <div class="status-update-body">
        <p class="voucher-code-label">
          Voucher: <strong>{{ statusModal.ma_voucher }}</strong>
        </p>
        <p class="current-status-label">
          Trạng thái hiện tại:
          <span
            :class="[
              'status-badge',
              getVoucherStatus(statusModal.current).class,
            ]"
          >
            {{ getVoucherStatus(statusModal.current).text }}
          </span>
        </p>

        <div class="form-group" style="margin-top: 16px">
          <label class="form-label">Trạng Thái Mới *</label>
          <div class="status-options">
            <label
              v-for="opt in [
                { v: 'cho_duyet', t: '⏳ Chờ duyệt' },
                { v: 'hoat_dong', t: '✅ Hoạt động' },
                { v: 'tam_ngung', t: '⏸️ Tạm ngưng' },
                { v: 'vo_hieu', t: '🚫 Vô hiệu' },
              ]"
              :key="opt.v"
              class="status-radio-opt"
              :class="{ 'opt-active': statusModal.trang_thai === opt.v }"
            >
              <input
                type="radio"
                :value="opt.v"
                v-model="statusModal.trang_thai"
                style="display: none"
              />
              {{ opt.t }}
            </label>
          </div>
        </div>

        <div
          class="info-banner"
          style="margin-top: 12px"
          v-if="statusModal.trang_thai === 'hoat_dong'"
        >
          ✅ Khi duyệt voucher, khách hàng sẽ có thể sử dụng mã này khi đặt vé.
        </div>
        <div
          class="info-banner warning-banner"
          style="margin-top: 12px"
          v-if="statusModal.trang_thai === 'vo_hieu'"
        >
          ⚠️ Vô hiệu hóa voucher sẽ khiến mã này không thể sử dụng được nữa.
        </div>
      </div>

      <template #footer>
        <BaseButton variant="secondary" @click="statusModal.show = false"
          >Hủy</BaseButton
        >
        <BaseButton
          variant="primary"
          :loading="statusModal.loading"
          @click="submitApprove"
          >Xác Nhận</BaseButton
        >
      </template>
    </BaseModal>

    <!-- ===== MODAL TẠO VOUCHER MỚI ===== -->
    <BaseModal
      v-model="createModal.show"
      title="Tạo Voucher Admin Mới"
      maxWidth="700px"
    >
      <form @submit.prevent="submitCreate" class="form-grid-2">
        <div class="form-group full-width">
          <label class="form-label">Tên Voucher *</label>
          <input type="text" v-model="createModal.form.ten_voucher" class="custom-input" placeholder="VD: Khuyến mãi hè 2024" required />
        </div>

        <div class="form-group">
          <BaseSelect
            v-model="createModal.form.loai_voucher"
            label="Loại Voucher *"
            :options="[
              { value: 'percent', label: '📊 Phần trăm (%)' },
              { value: 'fixed', label: '💰 Cố định (VNĐ)' },
            ]"
          />
        </div>

        <div class="form-group">
          <label class="form-label">Giá Trị *</label>
          <input type="number" v-model="createModal.form.gia_tri" class="custom-input" min="1" required />
        </div>

        <div class="form-group">
          <label class="form-label">Số Lượng Phát Hành *</label>
          <input type="number" v-model="createModal.form.so_luong" class="custom-input" min="1" required />
        </div>

        <div class="form-group">
          <label class="form-label">Ngày Bắt Đầu *</label>
          <input type="date" v-model="createModal.form.ngay_bat_dau" class="custom-input" required />
        </div>

        <div class="form-group">
          <label class="form-label">Ngày Kết Thúc *</label>
          <input type="date" v-model="createModal.form.ngay_ket_thuc" class="custom-input" required />
        </div>

        <div class="form-group">
          <BaseSelect
            v-model="createModal.form.target_type"
            label="Đối tượng áp dụng"
            :options="[
              { value: 'all', label: 'Toàn bộ hệ thống' },
              { value: 'specific_nha_xe', label: 'Nhà xe cụ thể' },
              { value: 'specific_khach_hang', label: 'Khách hàng cụ thể' },
              { value: 'target_criteria', label: 'Theo nhóm khách hàng (Tình trạng/Hạng)' },
            ]"
          />
        </div>

        <div class="form-group full-width">
          <label class="checkbox-item public-toggle">
            <input type="checkbox" v-model="createModal.form.is_public" />
            <div class="toggle-text">
              <span class="toggle-title">Voucher Công Khai (Săn Voucher)</span>
              <span class="toggle-desc">Khách hàng có thể nhìn thấy và "săn" voucher này vào ví cá nhân.</span>
            </div>
          </label>
        </div>

        <!-- Target by Criteria (Status/Rank) -->
        <template v-if="createModal.form.target_type === 'target_criteria'">
          <div class="form-group full-width">
            <label class="form-label">Theo Tình Trạng Khách Hàng</label>
            <div class="criteria-list">
              <label class="checkbox-item">
                <input type="checkbox" value="hoat_dong" v-model="createModal.form.tinh_trang_khach_hangs" />
                <span>Hoạt động</span>
              </label>
              <label class="checkbox-item">
                <input type="checkbox" value="chua_xac_nhan" v-model="createModal.form.tinh_trang_khach_hangs" />
                <span>Chưa xác nhận</span>
              </label>
              <label class="checkbox-item">
                <input type="checkbox" value="khoa" v-model="createModal.form.tinh_trang_khach_hangs" />
                <span>Bị khóa</span>
              </label>
            </div>
          </div>
          
          <div class="form-group full-width">
            <label class="form-label">Theo Hạng Thành Viên</label>
            <div class="criteria-list">
              <label class="checkbox-item">
                <input type="checkbox" value="dong" v-model="createModal.form.hang_thanh_viens" />
                <span>Hạng Đồng</span>
              </label>
              <label class="checkbox-item">
                <input type="checkbox" value="bac" v-model="createModal.form.hang_thanh_viens" />
                <span>Hạng Bạc</span>
              </label>
              <label class="checkbox-item">
                <input type="checkbox" value="vang" v-model="createModal.form.hang_thanh_viens" />
                <span>Hạng Vàng</span>
              </label>
              <label class="checkbox-item">
                <input type="checkbox" value="bach_kim" v-model="createModal.form.hang_thanh_viens" />
                <span>Hạng Bạch Kim</span>
              </label>
            </div>
          </div>
        </template>

        <!-- Chọn Nhà Xe -->
        <div class="form-group full-width" v-if="createModal.form.target_type === 'specific_nha_xe'">
          <label class="form-label">Chọn Nhà Xe (Có thể chọn nhiều) *</label>
          <div class="multi-select-list">
            <label v-for="nx in operators" :key="nx.id" class="checkbox-item">
              <input type="checkbox" :value="nx.id" v-model="createModal.form.id_nha_xes" />
              <span>{{ nx.ten_nha_xe }}</span>
            </label>
          </div>
        </div>

        <!-- Chọn Khách Hàng -->
        <div class="form-group full-width" v-if="createModal.form.target_type === 'specific_khach_hang'">
          <label class="form-label">Chọn Khách Hàng (Có thể chọn nhiều) *</label>
          <div class="multi-select-list">
            <label v-for="kh in customers" :key="kh.id" class="checkbox-item">
              <input type="checkbox" :value="kh.id" v-model="createModal.form.id_khach_hangs" />
              <span>{{ kh.ten_khach_hang }} ({{ kh.so_dien_thoai }})</span>
            </label>
          </div>
        </div>

        <div class="form-group full-width">
          <label class="form-label">Điều Kiện Áp Dụng</label>
          <textarea v-model="createModal.form.dieu_kien" class="custom-textarea" placeholder="Mô tả điều kiện sử dụng..."></textarea>
        </div>
      </form>

      <template #footer>
        <BaseButton variant="secondary" @click="createModal.show = false">Hủy</BaseButton>
        <BaseButton variant="primary" :loading="createModal.loading" @click="submitCreate">
          🚀 Phát Hành Voucher
        </BaseButton>
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
  gap: 1.25rem;
  margin-bottom: 2rem;
  flex-wrap: wrap;
}
.stat-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.5rem;
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
  min-width: 180px;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  border-color: #cbd5e1;
}
.stat-icon {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  font-size: 1.25rem;
}
.icon-total {
  background: #f1f5f9;
}
.icon-pending {
  background: #fef3c7;
}
.icon-active {
  background: #dcfce7;
}
.stat-info {
  display: flex;
  flex-direction: column;
}
.stat-value {
  font-size: 1.5rem;
  font-weight: 800;
  color: #1e293b;
  line-height: 1.2;
}
.stat-desc {
  font-size: 0.8rem;
  font-weight: 500;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
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
  align-items: flex-start;
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

.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.full-width {
  grid-column: 1 / -1;
}

.custom-input, .custom-textarea {
  width: 100%;
  padding: 0.625rem 0.875rem;
  font-size: 0.95rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background-color: #fff;
  transition: all 0.2s;
}

.custom-textarea {
  min-height: 80px;
  resize: vertical;
}

.multi-select-list {
  max-height: 150px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.5rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  background: #f8fafc;
}

.checkbox-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.875rem;
  color: #475569;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
}

.checkbox-item:hover {
  background: #eff6ff;
}

.criteria-list {
  display: flex;
  flex-wrap: wrap;
  gap: 1.5rem;
  padding: 0.75rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
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

.public-toggle {
  background: #f0f9ff;
  border: 1px dashed #0ea5e9;
  padding: 1rem !important;
  border-radius: 12px;
  margin-top: 0.5rem;
}
.toggle-text {
  display: flex;
  flex-direction: column;
}
.toggle-title {
  font-weight: 700;
  color: #0369a1;
}
.toggle-desc {
  font-size: 0.8rem;
  color: #0c4a6e;
}
</style>
