<script setup>
import { ref, reactive, onMounted, onUnmounted, computed } from "vue";
import { Armchair, Edit, ArrowRightLeft, StepForward, Trash2 } from "lucide-vue-next";
import operatorApi from "@/api/operatorApi";
import BaseTable from "@/components/common/BaseTable.vue";
import BaseButton from "@/components/common/BaseButton.vue";
import BaseInput from "@/components/common/BaseInput.vue";
import BaseModal from "@/components/common/BaseModal.vue";
import BaseToast from "@/components/common/BaseToast.vue";
import {
  formatCurrency,
  formatDate,
  formatDateTime,
  formatDateOnly,
  formatTimeOnly,
} from "@/utils/format";

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

// --- TRẠNG THÁI CHUYẾN XE (cập nhật theo backend) ---
const tripStatusMap = {
  hoat_dong: { text: "Chờ khởi hành", cls: "badge-yellow" },
  dang_di_chuyen: { text: "Đang di chuyển", cls: "badge-blue" },
  hoan_thanh: { text: "Hoàn thành", cls: "badge-green" },
  huy: { text: "Đã hủy", cls: "badge-red" },
};
const tripStatusOptions = [
  { value: "hoat_dong", label: "Chờ khởi hành" },
  { value: "dang_di_chuyen", label: "Đang di chuyển" },
  { value: "hoan_thanh", label: "Hoàn thành" },
  { value: "huy", label: "Đã hủy" },
];
const getTripStatus = (s) => tripStatusMap[s] || { text: s || "—", cls: "" };

// --- DANH SÁCH CHUYẾN XE ---
const trips = ref([]);
const loading = ref(false);
const pagination = reactive({
  currentPage: 1,
  perPage: 15,
  total: 0,
  lastPage: 1,
});
const searchQuery = ref(""); // tìm tuyến đường / biển số xe / tên tài xế
const filterStatus = ref("");

const tableColumns = [
  { key: "id", label: "ID" },
  { key: "tuyen_duong", label: "Tuyến Đường" },
  { key: "ngay_gio", label: "Ngày / Giờ KH" },
  { key: "xe", label: "Xe" },
  { key: "tai_xe", label: "Tài Xế" },
  { key: "trang_thai", label: "Trạng Thái" },
  { key: "actions", label: "Hành Động" },
];

const fetchTrips = async (page = 1) => {
  try {
    loading.value = true;
    const res = await operatorApi.getTrips({
      per_page: pagination.perPage,
      search: searchQuery.value || undefined,
      trang_thai: filterStatus.value || undefined,
      page,
    });
    let list = [],
      info = {};
    if (res.data?.data?.data?.data) {
      list = res.data.data.data.data;
      info = res.data.data.data;
    } else if (res.data?.data?.data) {
      list = res.data.data.data;
      info = res.data.data;
    } else if (Array.isArray(res.data?.data)) {
      list = res.data.data;
      info = res.data;
    }
    trips.value = Array.isArray(list) ? list : [];
    pagination.currentPage = info.current_page || 1;
    pagination.total = info.total || 0;
    pagination.lastPage = info.last_page || 1;
  } catch (e) {
    console.error(e);
    showToast("Không thể tải danh sách chuyến xe!", "error");
  } finally {
    loading.value = false;
  }
};

// --- FORM THÊM / SỬA ---
const isShowModal = ref(false);
const isEditMode = ref(false);
const modalLoading = ref(false);
const currentId = ref(null);

const initForm = () => ({
  id_tuyen_duong: "",
  id_xe: "",
  id_tai_xe: "",
  ngay_khoi_hanh: "",
  gio_khoi_hanh: "07:00",
  tong_tien: null,
  thanh_toan_sau: 0,
  trang_thai: "hoat_dong",
});
const formData = reactive(initForm());

const openCreateModal = () => {
  isEditMode.value = false;
  currentId.value = null;
  Object.assign(formData, initForm());
  isShowModal.value = true;
};

const openEditModal = (trip) => {
  isEditMode.value = true;
  currentId.value = trip.id;
  Object.assign(formData, {
    id_tuyen_duong: trip.id_tuyen_duong || "",
    id_xe: trip.id_xe || "",
    id_tai_xe: trip.id_tai_xe || "",
    ngay_khoi_hanh: trip.ngay_khoi_hanh || "",
    gio_khoi_hanh: trip.gio_khoi_hanh || "07:00",
    tong_tien: trip.tong_tien || null,
    thanh_toan_sau: trip.thanh_toan_sau || 0,
    trang_thai: trip.trang_thai || "hoat_dong",
  });
  isShowModal.value = true;
};

const submitForm = async () => {
  try {
    modalLoading.value = true;
    const payload = { ...formData };
    if (!payload.tong_tien) delete payload.tong_tien;
    if (isEditMode.value) {
      await operatorApi.updateTrip(currentId.value, payload);
      showToast("Cập nhật chuyến xe thành công!");
    } else {
      await operatorApi.createTrip(payload);
      showToast("Thêm chuyến xe thành công!");
    }
    isShowModal.value = false;
    fetchTrips(pagination.currentPage);
  } catch (e) {
    const msg = e.response?.data?.errors
      ? Object.values(e.response.data.errors).flat()[0]
      : e.response?.data?.message || "Có lỗi xảy ra!";
    showToast(msg, "error");
  } finally {
    modalLoading.value = false;
  }
};

// --- MODAL XÁC NHẬN (XÓA / TOGGLE) ---
const confirmModal = reactive({
  show: false,
  action: "",
  id: null,
  title: "",
  message: "",
  loading: false,
});

const openConfirm = (action, trip) => {
  confirmModal.action = action;
  confirmModal.id = trip.id;
  confirmModal.title =
    action === "delete" ? "Xác nhận xóa chuyến xe" : "Đổi trạng thái nhanh";
  confirmModal.message =
    action === "delete"
      ? `Bạn có chắc muốn xóa chuyến xe #${trip.id}? Hành động này không thể hoàn tác!`
      : `Hệ thống sẽ tự động chuyển trạng thái chuyến xe #${trip.id}.`;
  confirmModal.show = true;
};

const executeConfirm = async () => {
  try {
    confirmModal.loading = true;
    if (confirmModal.action === "delete") {
      await operatorApi.deleteTrip(confirmModal.id);
      showToast("Đã xóa chuyến xe thành công!");
    } else if (confirmModal.action === "toggle") {
      await operatorApi.changeTripStatus(confirmModal.id);
      showToast("Đã đổi trạng thái chuyến xe!");
    }
    fetchTrips(pagination.currentPage);
    confirmModal.show = false;
  } catch (e) {
    showToast(e.response?.data?.message || "Có lỗi xảy ra!", "error");
  } finally {
    confirmModal.loading = false;
  }
};

// --- MODAL ĐỔI XE ---
const isChangeBusModal = ref(false);
const changeBusId = ref(null);
const newBusId = ref("");
const changeBusLoading = ref(false);

const openChangeBusModal = (trip) => {
  changeBusId.value = trip.id;
  newBusId.value = "";
  isChangeBusModal.value = true;
};
const submitChangeBus = async () => {
  try {
    changeBusLoading.value = true;
    await operatorApi.changeTripBus(changeBusId.value, {
      id_xe: Number(newBusId.value),
    });
    showToast("Đổi xe thành công!");
    isChangeBusModal.value = false;
    fetchTrips(pagination.currentPage);
  } catch (e) {
    showToast(e.response?.data?.message || "Đổi xe thất bại!", "error");
  } finally {
    changeBusLoading.value = false;
  }
};

// ============================================================
// --- MODAL SƠ ĐỒ GHẾ + ĐẶT VÉ ---
// Response từ API: data là flat array [{ id_ghe, ma_ghe, tang, trang_thai }]
// ============================================================
const isSeatModal = ref(false);
const seatLoading = ref(false);
const seatList = ref([]); // flat array từ API
const selectedTrip = ref(null);

// Ghế được chọn để đặt
const selectedSeats = ref([]); // mảng ma_ghe

const openSeatModal = async (trip) => {
  selectedTrip.value = trip;
  isSeatModal.value = true;
  seatLoading.value = true;
  seatList.value = [];
  selectedSeats.value = [];
  bookFormTrip.id_tram_don = "";
  bookFormTrip.id_tram_tra = "";
  bookFormTrip.sdt_khach_hang = "";
  bookFormTrip.phuong_thuc_thanh_toan = "tien_mat";
  bookFormTrip.tinh_trang = "da_thanh_toan";
  bookFormTrip.ghi_chu = "";
  try {
    const res = await operatorApi.getTripSeats(trip.id);
    // Response: { success: true, data: [...] }
    const rawData = res.data?.data ?? res.data;
    seatList.value = Array.isArray(rawData) ? rawData : [];
  } catch (e) {
    showToast("Không thể tải sơ đồ ghế!", "error");
    isSeatModal.value = false;
  } finally {
    seatLoading.value = false;
  }
};

// Nhóm ghế theo tầng, mỗi tầng chia thành các hàng 5 ghế
const seatsByFloor = computed(() => {
  const result = {};
  seatList.value.forEach((g) => {
    const tang = g.tang || 1;
    if (!result[tang]) result[tang] = [];
    result[tang].push(g);
  });
  return result;
});

const seatStats = computed(() => {
  const total = seatList.value.length;
  const booked = seatList.value.filter((g) => g.trang_thai === "da_dat").length;
  return { total, booked, available: total - booked };
});

// Toggle chọn ghế
const toggleSeat = (seat) => {
  if (seat.trang_thai === "da_dat") return; // không chọn ghế đã đặt
  const idx = selectedSeats.value.indexOf(seat.ma_ghe);
  if (idx === -1) {
    selectedSeats.value.push(seat.ma_ghe);
  } else {
    selectedSeats.value.splice(idx, 1);
  }
};

const isSeatSelected = (seat) => selectedSeats.value.includes(seat.ma_ghe);

// --- Form đặt vé từ modal sơ đồ ghế ---
const bookFormTrip = reactive({
  id_tram_don: "",
  id_tram_tra: "",
  sdt_khach_hang: "",
  phuong_thuc_thanh_toan: "tien_mat",
  tinh_trang: "da_thanh_toan",
  ghi_chu: "",
});

const bookLoading = ref(false);
const isBookPanelOpen = ref(false);

const submitBookFromSeat = async () => {
  if (!selectedSeats.value.length) {
    showToast("Vui lòng chọn ít nhất 1 ghế!", "error");
    return;
  }
  if (!bookFormTrip.id_tram_don || !bookFormTrip.id_tram_tra) {
    showToast("Vui lòng nhập ID trạm đón và trạm trả!", "error");
    return;
  }
  try {
    bookLoading.value = true;
    const payload = {
      id_chuyen_xe: selectedTrip.value.id,
      danh_sach_ghe: [...selectedSeats.value],
      id_tram_don: Number(bookFormTrip.id_tram_don),
      id_tram_tra: Number(bookFormTrip.id_tram_tra),
      phuong_thuc_thanh_toan: bookFormTrip.phuong_thuc_thanh_toan,
      tinh_trang: bookFormTrip.tinh_trang,
    };
    if (bookFormTrip.sdt_khach_hang)
      payload.sdt_khach_hang = bookFormTrip.sdt_khach_hang;
    if (bookFormTrip.ghi_chu) payload.ghi_chu = bookFormTrip.ghi_chu;

    await operatorApi.bookTicket(payload);
    showToast(`🎫 Đặt ${selectedSeats.value.length} vé thành công!`);
    // Reload ghế để cập nhật trạng thái
    selectedSeats.value = [];
    const res = await operatorApi.getTripSeats(selectedTrip.value.id);
    const rawData = res.data?.data ?? res.data;
    seatList.value = Array.isArray(rawData) ? rawData : [];
    isBookPanelOpen.value = false;
  } catch (e) {
    const msg = e.response?.data?.errors
      ? Object.values(e.response.data.errors).flat()[0]
      : e.response?.data?.message || "Đặt vé thất bại!";
    showToast(msg, "error");
  } finally {
    bookLoading.value = false;
  }
};

// --- Modal xem chi tiết chuyến ---
const isDetailModal = ref(false);
const detailLoading = ref(false);
const detailData = ref(null);

const openDetailModal = async (id) => {
  isDetailModal.value = true;
  detailLoading.value = true;
  detailData.value = null;
  try {
    const res = await operatorApi.getTripDetails(id);
    detailData.value = res.data?.data || res.data;
  } catch (error) {
    showToast("Có lỗi xảy ra khi lấy chi tiết chuyến xe.", "error");
    isDetailModal.value = false;
  } finally {
    detailLoading.value = false;
  }
};

// (Removed dropdown logic)

onMounted(() => {
  fetchTrips();
});
</script>

<template>
  <div class="operator-page">
    <BaseToast
      :visible="toast.visible"
      :message="toast.message"
      :type="toast.type"
    />

    <!-- Tiêu đề -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Chuyến Xe</h1>
        <p class="page-sub">
          Tạo, điều hành và theo dõi toàn bộ chuyến xe của nhà xe
        </p>
      </div>
      <BaseButton @click="openCreateModal" variant="primary"
        >+ Thêm Chuyến Xe</BaseButton
      >
    </div>

    <!-- Bộ lọc -->
    <div class="filter-card">
      <div class="filter-row">
        <div class="search-box">
          <BaseInput
            v-model="searchQuery"
            placeholder="Tìm tuyến đường, biển số xe, tên tài xế..."
            @keyup.enter="fetchTrips(1)"
          />
          <BaseButton @click="fetchTrips(1)" variant="secondary"
            >Tìm</BaseButton
          >
        </div>
        <div class="filter-group">
          <label class="filter-label">Trạng thái</label>
          <select
            v-model="filterStatus"
            @change="fetchTrips(1)"
            class="custom-select"
          >
            <option value="">Tất cả</option>
            <option
              v-for="s in tripStatusOptions"
              :key="s.value"
              :value="s.value"
            >
              {{ s.label }}
            </option>
          </select>
        </div>
        <BaseButton
          @click="
            searchQuery = '';
            filterStatus = '';
            fetchTrips(1);
          "
          variant="outline"
          >Đặt lại</BaseButton
        >
      </div>
    </div>

    <!-- Bảng — sắp xếp server-side: ngay_khoi_hanh DESC, created_at DESC -->
    <div class="table-card">
      <BaseTable
        :columns="tableColumns"
        :data="trips"
        :loading="loading"
        @row-click="openDetailModal($event.id)"
      >
        <template #cell(tuyen_duong)="{ item }">
          <div v-if="item.tuyen_duong" class="route-cell">
            <span class="route-name">{{
              item.tuyen_duong.ten_tuyen_duong
            }}</span>
            <span class="route-path"
              >{{ item.tuyen_duong.diem_bat_dau }} →
              {{ item.tuyen_duong.diem_ket_thuc }}</span
            >
          </div>
          <span v-else class="text-muted"
            >Tuyến #{{ item.id_tuyen_duong }}</span
          >
        </template>

        <template #cell(ngay_gio)="{ item }">
          <div class="date-cell">
            <span class="date-main">{{
              formatDateOnly(item.ngay_khoi_hanh)
            }}</span>
            <span class="date-time"
              >🕐 {{ formatTimeOnly(item.gio_khoi_hanh) }}</span
            >
          </div>
        </template>

        <template #cell(xe)="{ item }">
          <span v-if="item.xe" class="plate-badge">{{ item.xe.bien_so }}</span>
          <span v-else class="text-muted">Xe #{{ item.id_xe }}</span>
        </template>

        <template #cell(tai_xe)="{ item }">
          <span v-if="item.tai_xe" class="driver-name">{{
            item.tai_xe.ho_va_ten
          }}</span>
          <span v-else class="text-muted">TX #{{ item.id_tai_xe }}</span>
        </template>

        <template #cell(trang_thai)="{ value }">
          <span :class="['status-badge', getTripStatus(value).cls]">
            {{ getTripStatus(value).text }}
          </span>
        </template>

        <template #cell(actions)="{ item }">
          <div class="action-buttons">
            <BaseButton
              size="sm"
              variant="outline"
              title="Sơ đồ ghế"
              @click.stop="openSeatModal(item)"
              class="btn-icon"
            >
              <Armchair size="16" class="text-info" />
            </BaseButton>
            <BaseButton
              size="sm"
              variant="outline"
              title="Sửa chuyến"
              @click.stop="openEditModal(item)"
              class="btn-icon"
            >
              <Edit size="16" class="text-warning" />
            </BaseButton>
            <BaseButton
              size="sm"
              variant="outline"
              title="Đổi xe"
              class="btn-icon"
              @click.stop="openChangeBusModal(item)"
            >
              <ArrowRightLeft size="16" class="text-secondary" />
            </BaseButton>
            <BaseButton
              size="sm"
              variant="outline"
              title="Toggle trạng thái"
              class="btn-icon"
              @click.stop="openConfirm('toggle', item)"
            >
              <StepForward size="16" class="text-primary" />
            </BaseButton>
            <BaseButton
              size="sm"
              variant="outline"
              class="btn-icon border-danger"
              title="Xóa chuyến"
              @click.stop="openConfirm('delete', item)"
            >
              <Trash2 size="16" class="text-danger" />
            </BaseButton>
          </div>
        </template>
      </BaseTable>

      <!-- Phân trang -->
      <div class="pagination-container">
        <div class="page-info-left">
          <span>Hiển thị:</span>
          <select
            v-model="pagination.perPage"
            @change="fetchTrips(1)"
            class="custom-select per-page-select"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
          </select>
          <span>dòng / trang</span>
          <span v-if="pagination.total > 0" class="total-label"
            >(Tổng: {{ pagination.total }})</span
          >
        </div>
        <div class="pagination-controls">
          <BaseButton
            size="sm"
            variant="outline"
            :disabled="pagination.currentPage <= 1"
            @click="fetchTrips(pagination.currentPage - 1)"
            >← Trước</BaseButton
          >
          <span class="page-number"
            >Trang {{ pagination.currentPage }} /
            {{ pagination.lastPage }}</span
          >
          <BaseButton
            size="sm"
            variant="outline"
            :disabled="pagination.currentPage >= pagination.lastPage"
            @click="fetchTrips(pagination.currentPage + 1)"
            >Sau →</BaseButton
          >
        </div>
      </div>
    </div>

    <!-- ===== MODAL THÊM / SỬA ===== -->
    <BaseModal
      v-model="isShowModal"
      :title="isEditMode ? 'Cập Nhật Chuyến Xe' : 'Thêm Chuyến Xe Mới'"
      maxWidth="680px"
    >
      <form @submit.prevent="submitForm" class="form-grid-2">
        <div class="form-group full-width">
          <label class="form-label">ID Tuyến Đường *</label>
          <input
            type="number"
            v-model="formData.id_tuyen_duong"
            class="custom-input"
            min="1"
            required
            placeholder="Nhập ID tuyến đường..."
          />
        </div>
        <div class="form-group">
          <label class="form-label">ID Xe *</label>
          <input
            type="number"
            v-model="formData.id_xe"
            class="custom-input"
            min="1"
            required
            placeholder="Nhập ID xe..."
          />
        </div>
        <div class="form-group">
          <label class="form-label">ID Tài Xế *</label>
          <input
            type="number"
            v-model="formData.id_tai_xe"
            class="custom-input"
            min="1"
            required
            placeholder="Nhập ID tài xế..."
          />
        </div>
        <div class="form-group">
          <label class="form-label">Ngày Khởi Hành *</label>
          <input
            type="date"
            v-model="formData.ngay_khoi_hanh"
            class="custom-input"
            required
          />
        </div>
        <div class="form-group">
          <label class="form-label">Giờ Khởi Hành *</label>
          <input
            type="time"
            v-model="formData.gio_khoi_hanh"
            class="custom-input"
            required
          />
        </div>
        <div class="form-group">
          <label class="form-label">Tổng Tiền (tùy chọn)</label>
          <input
            type="number"
            v-model="formData.tong_tien"
            class="custom-input"
            min="0"
            placeholder="Để trống = theo giá tuyến"
          />
        </div>
        <div class="form-group">
          <label class="form-label">Thanh Toán Sau</label>
          <select v-model="formData.thanh_toan_sau" class="custom-select">
            <option :value="0">Không (thanh toán ngay)</option>
            <option :value="1">Có (thanh toán sau)</option>
          </select>
        </div>
        <div class="form-group full-width" v-if="isEditMode">
          <label class="form-label">Trạng Thái</label>
          <div class="status-radios">
            <label
              v-for="s in tripStatusOptions"
              :key="s.value"
              class="radio-option"
              :class="{ 'radio-active': formData.trang_thai === s.value }"
            >
              <input
                type="radio"
                :value="s.value"
                v-model="formData.trang_thai"
                style="display: none"
              />
              <span :class="['status-badge', getTripStatus(s.value).cls]">{{
                s.label
              }}</span>
            </label>
          </div>
        </div>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="isShowModal = false"
          >Hủy</BaseButton
        >
        <BaseButton
          variant="primary"
          :loading="modalLoading"
          @click="submitForm"
        >
          {{ isEditMode ? "Lưu Thay Đổi" : "Thêm Chuyến Xe" }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- ===== MODAL ĐỔI XE ===== -->
    <BaseModal
      v-model="isChangeBusModal"
      title="Đổi Xe Cho Chuyến"
      maxWidth="420px"
    >
      <div class="info-banner">
        ℹ️ Các ghế đã đặt sẽ được giữ nguyên, hệ thống tự cập nhật ghế sang xe
        mới.
      </div>
      <div class="form-group" style="margin-top: 12px">
        <label class="form-label">ID Xe Mới *</label>
        <input
          type="number"
          v-model="newBusId"
          class="custom-input"
          min="1"
          placeholder="Nhập ID xe muốn thay..."
        />
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="isChangeBusModal = false"
          >Hủy</BaseButton
        >
        <BaseButton
          variant="primary"
          :loading="changeBusLoading"
          @click="submitChangeBus"
          >Xác Nhận Đổi Xe</BaseButton
        >
      </template>
    </BaseModal>

    <!-- ===== MODAL CHI TIẾT CHUYẾN XE ===== -->
    <BaseModal
      v-model="isDetailModal"
      title="Chi Tiết Chuyến Xe"
      maxWidth="800px"
    >
      <div v-if="detailLoading" class="text-center p-4">
        <span>Đang tải thông tin chuyến xe...</span>
      </div>
      <div v-else-if="detailData">
        <div class="row g-3">
          <div class="col-md-6 mb-3">
            <h5 class="text-primary mb-3">Thông Tin Chung</h5>
            <table class="table table-bordered table-sm">
              <tbody>
                <tr>
                  <th style="width: 40%">ID Chuyến:</th>
                  <td>
                    <span class="badge bg-secondary">{{ detailData.id }}</span>
                  </td>
                </tr>
                <tr>
                  <th>Khởi Hành:</th>
                  <td class="fw-bold text-success">
                    {{ detailData.gio_khoi_hanh }} |
                    {{ formatDateOnly(detailData.ngay_khoi_hanh) }}
                  </td>
                </tr>
                <tr>
                  <th>Thanh Toán:</th>
                  <td>
                    <span
                      v-if="detailData.thanh_toan_sau === 0"
                      class="badge bg-info"
                      >Trả trước</span
                    >
                    <span v-else class="badge bg-warning">Trả sau</span>
                  </td>
                </tr>
                <tr>
                  <th>Tổng Tiền:</th>
                  <td class="fw-bold text-danger">
                    {{ formatCurrency(detailData.tong_tien) }}
                  </td>
                </tr>
                <tr>
                  <th>Trạng Thái:</th>
                  <td>
                    <span
                      :class="[
                        'status-badge',
                        getTripStatus(detailData.trang_thai).cls,
                      ]"
                    >
                      {{ getTripStatus(detailData.trang_thai).text }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="col-md-6 mb-3" v-if="detailData.tuyen_duong">
            <h5 class="text-primary mb-3">Tuyến Đường</h5>
            <table class="table table-sm table-bordered">
              <tbody>
                <tr>
                  <th style="width: 40%">Tên Tuyến:</th>
                  <td class="fw-bold">
                    {{ detailData.tuyen_duong.ten_tuyen_duong }}
                  </td>
                </tr>
                <tr>
                  <th>Lộ Trình:</th>
                  <td class="text-primary fw-bold">
                    {{ detailData.tuyen_duong.diem_bat_dau }} →
                    {{ detailData.tuyen_duong.diem_ket_thuc }}
                  </td>
                </tr>
                <tr>
                  <th>Quãng Đường:</th>
                  <td>{{ detailData.tuyen_duong.quang_duong }} km</td>
                </tr>
                <tr>
                  <th>Dự Kiến:</th>
                  <td>{{ detailData.tuyen_duong.gio_du_kien }} giờ</td>
                </tr>
                <tr>
                  <th>Giá Vé Cơ Bản:</th>
                  <td class="fw-bold text-success">
                    {{ formatCurrency(detailData.tuyen_duong.gia_ve_co_ban) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="col-md-6" v-if="detailData.xe">
            <h5 class="text-primary mb-3">Phương Tiện</h5>
            <table class="table table-sm table-bordered">
              <tbody>
                <tr>
                  <th style="width: 40%">Biển Số:</th>
                  <td>
                    <span class="badge bg-dark fs-6">{{
                      detailData.xe.bien_so
                    }}</span>
                  </td>
                </tr>
                <tr>
                  <th>Tên Xe:</th>
                  <td class="fw-bold">{{ detailData.xe.ten_xe }}</td>
                </tr>
                <tr>
                  <th>Số Ghế:</th>
                  <td class="text-primary fw-bold">
                    {{ detailData.xe.so_ghe_thuc_te }} ghế
                  </td>
                </tr>
                <tr v-if="detailData.xe.thong_tin_cai_dat">
                  <th>Cài Đặt:</th>
                  <td>
                    <span
                      v-if="detailData.xe.thong_tin_cai_dat.camera_ai"
                      class="badge bg-success me-1"
                      >Camera AI</span
                    >
                    <span
                      v-if="detailData.xe.thong_tin_cai_dat.gps"
                      class="badge bg-primary"
                      >GPS</span
                    >
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="col-md-6" v-if="detailData.tai_xe">
            <h5 class="text-primary mb-3">Tài Xế</h5>
            <table
              class="table table-sm table-bordered"
              v-if="detailData.tai_xe.id"
            >
              <tbody>
                <tr>
                  <th style="width: 40%">ID Tài Xế:</th>
                  <td>{{ detailData.tai_xe.id }}</td>
                </tr>
                <tr>
                  <th>Họ Tên:</th>
                  <td class="fw-bold text-dark">
                    {{ detailData.tai_xe.ho_ten }}
                  </td>
                </tr>
                <tr>
                  <th>SĐT:</th>
                  <td class="text-primary">
                    {{ detailData.tai_xe.so_dien_thoai }}
                  </td>
                </tr>
              </tbody>
            </table>
            <div v-else class="text-muted fst-italic mt-2">
              Chưa phân công tài xế
            </div>
          </div>
          <div class="col-md-6" v-else>
            <h5 class="text-primary mb-3">Tài Xế</h5>
            <div class="text-muted fst-italic mt-2 p-2 bg-light border rounded">
              Chưa có thông tin tài xế thực hiện chuyến xe này.
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="isDetailModal = false"
          >Đóng</BaseButton
        >
      </template>
    </BaseModal>

    <!-- ===== MODAL SƠ ĐỒ GHẾ + ĐẶT VÉ ===== -->
    <BaseModal
      v-model="isSeatModal"
      :title="`Sơ Đồ Ghế — Chuyến #${selectedTrip?.id || ''}`"
      maxWidth="820px"
    >
      <div v-if="seatLoading" class="loading-state">
        ⏳ Đang tải sơ đồ ghế...
      </div>

      <div v-else-if="seatList.length">
        <!-- Thống kê nhanh -->
        <div class="seat-stats">
          <div class="stat-item">
            <span class="stat-num">{{ seatStats.total }}</span>
            <span class="stat-lbl">Tổng ghế</span>
          </div>
          <div class="stat-item stat-avail">
            <span class="stat-num">{{ seatStats.available }}</span>
            <span class="stat-lbl">Còn trống</span>
          </div>
          <div class="stat-item stat-booked">
            <span class="stat-num">{{ seatStats.booked }}</span>
            <span class="stat-lbl">Đã đặt</span>
          </div>
          <div class="stat-item stat-selected" v-if="selectedSeats.length > 0">
            <span class="stat-num">{{ selectedSeats.length }}</span>
            <span class="stat-lbl">Đang chọn</span>
          </div>
        </div>

        <!-- Chú thích -->
        <div class="seat-legend">
          <span class="legend-item"
            ><span class="seat-dot dot-empty"></span> Còn trống (bấm để
            chọn)</span
          >
          <span class="legend-item"
            ><span class="seat-dot dot-booked"></span> Đã đặt</span
          >
          <span class="legend-item"
            ><span class="seat-dot dot-selected"></span> Đang chọn</span
          >
        </div>

        <!-- Sơ đồ từng tầng — mỗi tầng chia thành hàng 5 ghế -->
        <div
          v-for="(seats, tang) in seatsByFloor"
          :key="tang"
          class="floor-section"
        >
          <h4 class="floor-title">🚌 Tầng {{ tang }}</h4>
          <div class="seat-grid-wrap">
            <div
              v-for="seat in seats"
              :key="seat.id_ghe"
              class="seat-box"
              :class="{
                'seat-empty':
                  seat.trang_thai !== 'da_dat' && !isSeatSelected(seat),
                'seat-booked': seat.trang_thai === 'da_dat',
                'seat-selected': isSeatSelected(seat),
                'seat-clickable': seat.trang_thai !== 'da_dat',
              }"
              :title="`${seat.ma_ghe} — ${
                seat.trang_thai === 'da_dat'
                  ? 'Đã đặt'
                  : isSeatSelected(seat)
                    ? 'Đang chọn'
                    : 'Còn trống'
              }`"
              @click="toggleSeat(seat)"
            >
              {{ seat.ma_ghe }}
            </div>
          </div>
        </div>

        <!-- Panel đặt vé (hiện khi chọn >= 1 ghế) -->
        <transition name="slide-down">
          <div v-if="selectedSeats.length > 0" class="book-panel">
            <div
              class="book-panel-header"
              @click="isBookPanelOpen = !isBookPanelOpen"
            >
              <span class="book-panel-title">
                🎫 Đặt {{ selectedSeats.length }} vé:
                <strong>{{ selectedSeats.join(", ") }}</strong>
              </span>
              <span class="book-panel-toggle">{{
                isBookPanelOpen ? "▲" : "▼"
              }}</span>
            </div>

            <div v-if="isBookPanelOpen" class="book-panel-body">
              <div class="book-form-grid">
                <div class="form-group">
                  <label class="form-label">ID Trạm Đón *</label>
                  <input
                    type="number"
                    v-model="bookFormTrip.id_tram_don"
                    class="custom-input"
                    min="1"
                    placeholder="ID trạm đón..."
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">ID Trạm Trả *</label>
                  <input
                    type="number"
                    v-model="bookFormTrip.id_tram_tra"
                    class="custom-input"
                    min="1"
                    placeholder="ID trạm trả..."
                  />
                </div>
                <div class="form-group">
                  <label class="form-label"
                    >SĐT Khách
                    <span class="optional">(để trống = vãng lai)</span></label
                  >
                  <input
                    type="tel"
                    v-model="bookFormTrip.sdt_khach_hang"
                    class="custom-input"
                    placeholder="0901234567"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">Thanh Toán</label>
                  <select
                    v-model="bookFormTrip.phuong_thuc_thanh_toan"
                    class="custom-select"
                  >
                    <option value="tien_mat">💵 Tiền mặt</option>
                    <option value="chuyen_khoan">🏦 Chuyển khoản</option>
                    <option value="vi_dien_tu">📱 Ví điện tử</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Trạng Thái Vé</label>
                  <select
                    v-model="bookFormTrip.tinh_trang"
                    class="custom-select"
                  >
                    <option value="da_thanh_toan">✅ Đã thanh toán ngay</option>
                    <option value="dang_cho">⏳ Chờ thanh toán</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Ghi Chú</label>
                  <input
                    type="text"
                    v-model="bookFormTrip.ghi_chu"
                    class="custom-input"
                    placeholder="Ghi chú thêm..."
                  />
                </div>
              </div>

              <div class="book-panel-actions">
                <BaseButton
                  variant="secondary"
                  size="sm"
                  @click="selectedSeats = []"
                  >Bỏ chọn</BaseButton
                >
                <BaseButton
                  variant="primary"
                  :loading="bookLoading"
                  @click="submitBookFromSeat"
                >
                  🎫 Xác Nhận Đặt {{ selectedSeats.length }} Vé
                </BaseButton>
              </div>
            </div>
          </div>
        </transition>
      </div>

      <div v-else class="empty-seat">Không có dữ liệu ghế.</div>

      <template #footer>
        <BaseButton variant="secondary" @click="isSeatModal = false"
          >Đóng</BaseButton
        >
      </template>
    </BaseModal>

    <!-- ===== MODAL XÁC NHẬN ===== -->
    <BaseModal
      v-model="confirmModal.show"
      :title="confirmModal.title"
      maxWidth="440px"
    >
      <div class="confirm-body">
        <p>{{ confirmModal.message }}</p>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="confirmModal.show = false"
          >Hủy</BaseButton
        >
        <BaseButton
          :variant="confirmModal.action === 'delete' ? 'danger' : 'primary'"
          :loading="confirmModal.loading"
          @click="executeConfirm"
        >
          Xác Nhận
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.operator-page {
  font-family: "Inter", system-ui, sans-serif;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  color: #0d4f35;
  margin: 0 0 4px 0;
}

.page-sub {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}

/* Filter */
.filter-card {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(10px);
  border: 1px solid #dcfce7;
  padding: 14px 18px;
  border-radius: 14px;
  margin-bottom: 18px;
  box-shadow: 0 4px 12px rgba(0, 80, 40, 0.04);
}

.filter-row {
  display: flex;
  gap: 12px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.search-box {
  display: flex;
  gap: 8px;
  align-items: flex-end;
  flex: 1;
  min-width: 280px;
}

.search-box > :first-child {
  flex: 1;
  margin-bottom: 0;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.filter-label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}

/* Table */
.table-card {
  background: white;
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 4px 20px rgba(0, 80, 40, 0.05);
  border: 1px solid #dcfce7;
  overflow: visible;
}

.route-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.route-name {
  font-size: 13px;
  font-weight: 700;
  color: #0d4f35;
}

.route-path {
  font-size: 11px;
  color: #64748b;
}

.date-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.date-main {
  font-size: 13px;
  font-weight: 700;
  color: #1e293b;
}

.date-time {
  font-size: 12px;
  color: #64748b;
}

.plate-badge {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #16a34a;
  font-size: 12px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 8px;
}

.driver-name {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.text-muted {
  color: #94a3b8;
  font-size: 12px;
}

/* Status */
.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}

.badge-yellow {
  background: #fef9c3;
  color: #ca8a04;
}

.badge-blue {
  background: #dbeafe;
  color: #1d4ed8;
}

.badge-green {
  background: #dcfce7;
  color: #16a34a;
}

.badge-red {
  background: #fee2e2;
  color: #dc2626;
}

/* Actions */
.action-buttons {
  display: flex;
  gap: 5px;
  flex-wrap: nowrap;
  align-items: center;
  position: relative;
}

/* Pagination */
.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 14px;
  flex-wrap: wrap;
  gap: 10px;
}

.page-info-left {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #64748b;
}

.per-page-select {
  width: 70px !important;
}

.total-label {
  color: #94a3b8;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.page-number {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  padding: 0 6px;
}

/* === Modal Form === */
.form-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.full-width {
  grid-column: 1 / -1;
}

.form-label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.optional {
  font-size: 11px;
  font-weight: 400;
  color: #94a3b8;
}

.custom-input,
.custom-select {
  width: 100%;
  padding: 10px 12px;
  font-size: 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: white;
  color: #1f2937;
  box-sizing: border-box;
  transition: all 0.2s;
}

.custom-input:focus,
.custom-select:focus {
  outline: none;
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
}

.status-radios {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.radio-option {
  cursor: pointer;
  opacity: 0.5;
  transition: opacity 0.2s;
}

.radio-option.radio-active {
  opacity: 1;
}

.info-banner {
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 10px;
  padding: 10px 14px;
  font-size: 13px;
  color: #92400e;
}

/* === Sơ đồ ghế === */
.loading-state,
.empty-seat {
  text-align: center;
  padding: 40px;
  color: #64748b;
}

.seat-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}

.stat-item {
  text-align: center;
  padding: 10px 6px;
  background: #f8fafc;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-avail {
  background: #f0fdf4;
}

.stat-booked {
  background: #fef2f2;
}

.stat-selected {
  background: #eff6ff;
}

.stat-num {
  font-size: 22px;
  font-weight: 800;
  color: #0d4f35;
}

.stat-avail .stat-num {
  color: #16a34a;
}

.stat-booked .stat-num {
  color: #ef4444;
}

.stat-selected .stat-num {
  color: #2563eb;
}

.stat-lbl {
  font-size: 11px;
  color: #64748b;
}

.seat-legend {
  display: flex;
  gap: 16px;
  margin-bottom: 14px;
  font-size: 12px;
  color: #374151;
  flex-wrap: wrap;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
}

.seat-dot {
  width: 14px;
  height: 14px;
  border-radius: 4px;
}

.dot-empty {
  background: #dcfce7;
  border: 1px solid #86efac;
}

.dot-booked {
  background: #fecaca;
  border: 1px solid #f87171;
}

.dot-selected {
  background: #bfdbfe;
  border: 1px solid #60a5fa;
}

.floor-section {
  margin-bottom: 20px;
}

.floor-title {
  font-size: 14px;
  font-weight: 700;
  color: #0d4f35;
  margin: 0 0 10px 0;
  padding-bottom: 6px;
  border-bottom: 2px solid #dcfce7;
}

/* Ghế dạng grid responsive — 10 ghế/hàng */
.seat-grid-wrap {
  display: grid;
  grid-template-columns: repeat(10, 1fr);
  gap: 6px;
}

@media (max-width: 620px) {
  .seat-grid-wrap {
    grid-template-columns: repeat(5, 1fr);
  }
}

.seat-box {
  height: 40px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  transition:
    transform 0.15s,
    box-shadow 0.15s;
  user-select: none;
}

.seat-clickable {
  cursor: pointer;
}

.seat-empty {
  background: #dcfce7;
  border: 1px solid #86efac;
  color: #16a34a;
}

.seat-empty:hover {
  transform: scale(1.08);
  box-shadow: 0 4px 10px rgba(22, 163, 74, 0.25);
}

.seat-booked {
  background: #fecaca;
  border: 1px solid #f87171;
  color: #dc2626;
  cursor: not-allowed;
  opacity: 0.75;
}

.seat-selected {
  background: #bfdbfe;
  border: 2px solid #3b82f6;
  color: #1d4ed8;
  transform: scale(1.06);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* Panel đặt vé */
.book-panel {
  margin-top: 16px;
  border: 2px solid #16a34a;
  border-radius: 14px;
  overflow: hidden;
  background: white;
}

.book-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: linear-gradient(135deg, #16a34a, #0d9488);
  cursor: pointer;
}

.book-panel-title {
  font-size: 14px;
  font-weight: 600;
  color: white;
}

.book-panel-toggle {
  color: rgba(255, 255, 255, 0.8);
  font-size: 12px;
}

.book-panel-body {
  padding: 16px;
}

.book-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 14px;
}

.book-panel-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid #f0fdf4;
}

/* Slide animation */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.3s ease;
  overflow: hidden;
}

.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* Confirm */
.confirm-body {
  text-align: center;
  padding: 8px 0;
}

.confirm-body p {
  font-size: 15px;
  color: #334155;
  line-height: 1.6;
  margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
  .form-grid-2,
  .book-form-grid {
    grid-template-columns: 1fr;
  }

  .seat-stats {
    grid-template-columns: repeat(2, 1fr);
  }

  .filter-row {
    flex-direction: column;
  }

  .action-buttons {
    flex-direction: column;
  }

  .pagination-container {
    flex-direction: column;
    align-items: flex-start;
  }
}

/* Removed dropdown styles */
</style>
