<script setup>
import { ref, reactive, onMounted, onUnmounted } from "vue";
import { Eye, Armchair, Edit, ArrowRightLeft, Ticket, Trash2, StepForward } from "lucide-vue-next";
import adminApi from "@/api/adminApi";
import BaseTable from "@/components/common/BaseTable.vue";
import BaseButton from "@/components/common/BaseButton.vue";
import BaseInput from "@/components/common/BaseInput.vue";
import BaseModal from "@/components/common/BaseModal.vue";
import { formatCurrency, formatDate } from "@/utils/format";

// --- TRẠNG THÁI VÀ DỮ LIỆU ---
const trips = ref([]);
const loading = ref(false);
const autoGenLoading = ref(false);
const routesList = ref([]); // Để chọn filter

const pagination = reactive({
  currentPage: 1,
  perPage: 15,
  total: 0,
});

const filters = reactive({
  id_tuyen_duong: "",
  ngay_khoi_hanh: "",
  trang_thai: "",
});

// Cấu hình cột BaseTable
const tableColumns = [
  { key: "id", label: "ID" },
  { key: "tuyen_duong", label: "Tuyến Đường" },
  { key: "ngay_gio", label: "Khởi Hành" },
  { key: "xe", label: "Thông Tin Xe" },
  { key: "thanh_toan", label: "Thanh Toán" },
  { key: "trang_thai", label: "Trạng Thái" },
  { key: "actions", label: "Hành Động" },
];

// --- MODALS KHÁC NHAU ---
// 1. TẠO / SỬA CHUYẾN XE
const isShowModal = ref(false);
const isEditMode = ref(false);
const modalLoading = ref(false);
const currentId = ref(null);

const initialForm = {
  id_tuyen_duong: "",
  id_xe: "",
  id_tai_xe: "",
  ngay_khoi_hanh: "",
  gio_khoi_hanh: "",
  thanh_toan_sau: 0,
  tong_tien: 0,
  trang_thai: "hoat_dong",
};
const formData = reactive({ ...initialForm });

// 2. CONFIRM MODAL
const confirmModal = reactive({
  show: false,
  title: "",
  message: "",
  action: null,
  id: null,
  loading: false,
});

// 3. ĐỔI XE MODAL
const isShowBusModal = ref(false);
const busModalLoading = ref(false);
const busFormData = reactive({
  id: null,
  id_xe: "",
});

// 4. MÀN HÌNH SƠ ĐỒ GHẾ (Modal)
const isShowSeatModal = ref(false);
const seatLoading = ref(false);
const seatData = ref([]);
const currentTripForSeats = ref(null);
const selectedSeats = ref([]);

const toggleSeatSelection = (seat) => {
  if (seat.trang_thai === "da_dat") return;
  const seatId = seat.ma_ghe;
  const index = selectedSeats.value.indexOf(seatId);
  if (index > -1) {
    selectedSeats.value.splice(index, 1);
  } else {
    selectedSeats.value.push(seatId);
  }
};

const handleBookSelectedSeats = () => {
  if (selectedSeats.value.length === 0) return;
  isShowSeatModal.value = false;
  openBookModal(currentTripForSeats.value, selectedSeats.value.join(", "));
};

// 5. ĐẶT VÉ NHANH MODAL
const isShowBookModal = ref(false);
const bookModalLoading = ref(false);
const bookModalLoadingStations = ref(false);
const tramDons = ref([]);
const tramTras = ref([]);
const bookFormData = reactive({
  id_chuyen_xe: null,
  danh_sach_ghe: "", // Admin nhập chuỗi cách nhau bởi dấu phẩy, vd: A01,A02
  id_tram_don: "",
  id_tram_tra: "",
  id_khach_hang: "", // Optional
  sdt_khach_hang: "", // SĐT nếu không có id_khach_hang
  ghi_chu: "",
  tinh_trang: "da_thanh_toan", // Mặc định Đã thanh toán
  phuong_thuc_thanh_toan: "tien_mat",
});

// 6. XEM CHI TIẾT MODAL
const isShowDetailModal = ref(false);
const detailLoading = ref(false);
const detailData = ref(null);

const openDetailModal = async (id) => {
  isShowDetailModal.value = true;
  detailLoading.value = true;
  detailData.value = null;
  try {
    const res = await adminApi.getTripDetails(id);
    detailData.value = res.data?.data || res.data;
  } catch (error) {
    console.error("Lỗi khi lấy chi tiết chuyến xe:", error);
    alert("Có lỗi xảy ra khi lấy chi tiết chuyến xe.");
    isShowDetailModal.value = false;
  } finally {
    detailLoading.value = false;
  }
};

// --- HÀM HỖ TRỢ (UTILS LOCAL) ---
const getTripStatusLabel = (status) => {
  switch (status) {
    case "hoat_dong":
      return { text: "Hoạt động", class: "status-pending" };
    case "dang_di_chuyen":
      return { text: "Đang di chuyển", class: "status-info" };
    case "hoan_thanh":
      return { text: "Hoàn thành", class: "status-approved" };
    case "huy":
      return { text: "Đã hủy", class: "status-rejected" };
    default:
      return { text: status || "Không rõ", class: "" };
  }
};

const getPaymentLabel = (thanh_toan_sau) => {
  return Number(thanh_toan_sau) === 1 ? "Trả sau" : "Trả trước";
};

// --- API FETCH KHÁC ---
const fetchRoutesList = async () => {
  try {
    const res = await adminApi.getRoutes({ per_page: 999 });
    // Bóc tách API format của DoAnPrivate
    let dataArr = [];
    if (res.data?.data?.data?.data) dataArr = res.data.data.data.data;
    else if (res.data?.data?.data) dataArr = res.data.data.data;
    else if (res.data?.data) dataArr = res.data.data;

    if (Array.isArray(dataArr)) {
      routesList.value = dataArr.map((r) => ({
        value: r.id,
        label: `${r.id} - ${r.ten_tuyen_duong || r.diem_bat_dau + " -> " + r.diem_ket_thuc}`,
      }));
    }
  } catch (error) {
    console.error("Lỗi tải danh sách tuyến đường", error);
  }
};

// --- MAIN API FETCH (GET TRIPS) ---
const fetchTrips = async (page = 1) => {
  try {
    loading.value = true;

    // Thu gọn filters (chỉ gửi những param có data)
    const params = {
      page: page,
      per_page: pagination.perPage,
    };
    if (filters.id_tuyen_duong) params.id_tuyen_duong = filters.id_tuyen_duong;
    if (filters.ngay_khoi_hanh) params.ngay_khoi_hanh = filters.ngay_khoi_hanh;
    if (filters.trang_thai) params.trang_thai = filters.trang_thai;

    const res = await adminApi.getTrips(params);

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

    trips.value = Array.isArray(listData) ? listData : [];
    pagination.currentPage = pageInfo.current_page || 1;
    pagination.total = pageInfo.total || 0;
  } catch (error) {
    console.error("Lỗi khi tải chuyến xe:", error);
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  fetchTrips(1);
};

// --- ACTIONS (CREATE/EDIT) ---
const openCreateModal = () => {
  isEditMode.value = false;
  Object.assign(formData, initialForm);
  // Default ngày hôm nay
  const today = new Date().toISOString().split("T")[0];
  formData.ngay_khoi_hanh = today;
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
    gio_khoi_hanh: trip.gio_khoi_hanh || "",
    thanh_toan_sau: trip.thanh_toan_sau ?? 0,
    tong_tien: Number(trip.tong_tien) || 0,
    trang_thai: trip.trang_thai || "hoat_dong",
  });

  // Format gio_khoi_hanh to HH:mm (bỏ qua seconds nếu có API trả về HH:mm:ss)
  if (
    formData.gio_khoi_hanh &&
    formData.gio_khoi_hanh.split(":").length === 3
  ) {
    formData.gio_khoi_hanh = formData.gio_khoi_hanh.substring(0, 5);
  }

  isShowModal.value = true;
};

const submitForm = async () => {
  try {
    modalLoading.value = true;
    if (isEditMode.value) {
      await adminApi.updateTrip(currentId.value, formData);
    } else {
      await adminApi.createTrip(formData);
    }
    isShowModal.value = false;
    fetchTrips(pagination.currentPage);
  } catch (error) {
    console.error("Lỗi lưu chuyến xe:", error);
    alert("Có lỗi xảy ra khi lưu thông tin chuyến xe!");
  } finally {
    modalLoading.value = false;
  }
};

// --- AUTO GENERATE ---
const handleAutoGenerate = async () => {
  if (
    !confirm(
      "Hệ thống sẽ tự động tạo các chuyến xe cho 30 ngày tới dựa trên Tuyến đường. Khởi chạy quá trình này?",
    )
  )
    return;
  try {
    autoGenLoading.value = true;
    const res = await adminApi.autoGenerateTrips();
    alert(res?.data?.message || "Tạo tự động thành công!");
    fetchTrips(1);
  } catch (error) {
    console.error("Lỗi tạo hàng loạt:", error);
    alert("Có lỗi xảy ra khi tạo tự động.");
  } finally {
    autoGenLoading.value = false;
  }
};

// --- DIRECTIVES ---

// --- DIRECTIVES (for click-outside) vClickOutside không cần được sử dụng nữa nhưng để rỗng cho đỡ lỗi ---
const vClickOutside = {
  mounted(el, binding) {
    el.clickOutsideEvent = (event) => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value(event);
      }
    };
    document.body.addEventListener("click", el.clickOutsideEvent);
  },
  unmounted(el) {
    document.body.removeEventListener("click", el.clickOutsideEvent);
  },
};

// --- NEXT STATUS & DELETE ---
const openConfirmModal = (action, id) => {
  confirmModal.action = action;
  confirmModal.id = id;
  if (action === "status") {
    confirmModal.title = "Xác nhận chuyển trạng thái";
    confirmModal.message =
      "Cập nhật trạng thái chuyến xe tiếp theo (Chờ Chạy -> Đang Chạy -> Hoàn Thành)?";
  } else if (action === "delete") {
    confirmModal.title = "Xác nhận xóa";
    confirmModal.message =
      "Bạn có chắc muốn xóa chuyến xe này không? Hành động này không thể hoàn tác!";
  }
  confirmModal.show = true;
};

const executeConfirmAction = async () => {
  const { action, id } = confirmModal;
  try {
    confirmModal.loading = true;
    if (action === "status") {
      await adminApi.toggleTripStatus(id);
    } else if (action === "delete") {
      await adminApi.deleteTrip(id);
    }
    fetchTrips(pagination.currentPage);
    confirmModal.show = false;
  } catch (error) {
    console.error(`Lỗi thực hiện ${action}:`, error);
    alert(
      "Lỗi: " +
        (error.response?.data?.message ||
          "Hành động không hợp lệ ở trạng thái hiện tại."),
    );
  } finally {
    confirmModal.loading = false;
  }
};

// --- ĐỔI XE ---
const openChangeBusModal = (id) => {
  busFormData.id = id;
  busFormData.id_xe = "";
  isShowBusModal.value = true;
};

const submitChangeBus = async () => {
  if (!busFormData.id_xe) {
    return alert("Vui lòng nhập ID Xe thay thế!");
  }
  try {
    busModalLoading.value = true;
    await adminApi.changeTripBus(busFormData.id, { id_xe: busFormData.id_xe });
    isShowBusModal.value = false;
    alert("Đổi xe thành công!");
    fetchTrips(pagination.currentPage);
  } catch (error) {
    console.error("Lỗi đổi xe:", error);
    alert(
      "Lỗi: " +
        (error.response?.data?.message ||
          "Đổi xe thất bại (Xe mới không phù hợp)."),
    );
  } finally {
    busModalLoading.value = false;
  }
};

// --- XEM SƠ ĐỒ GHẾ ---
const openSeatModal = async (trip) => {
  currentTripForSeats.value = trip;
  isShowSeatModal.value = true;
  seatData.value = [];
  selectedSeats.value = [];
  try {
    seatLoading.value = true;
    const res = await adminApi.getTripSeats(trip.id);
    // Parse mảng ghế trả về
    if (res.data?.data && Array.isArray(res.data.data)) {
      seatData.value = res.data.data;
    } else if (Array.isArray(res.data)) {
      seatData.value = res.data;
    }
  } catch (error) {
    console.error("Lỗi tải sơ đồ ghế:", error);
  } finally {
    seatLoading.value = false;
  }
};

// --- ĐẶT VÉ NHANH ---
const openBookModal = async (trip, prefilledSeats = "") => {
  bookFormData.id_chuyen_xe = trip.id;
  bookFormData.danh_sach_ghe = prefilledSeats;
  bookFormData.id_tram_don = "";
  bookFormData.id_tram_tra = "";
  bookFormData.id_khach_hang = "";
  bookFormData.sdt_khach_hang = "";
  bookFormData.ghi_chu = "";
  bookFormData.tinh_trang = "da_thanh_toan";
  bookFormData.phuong_thuc_thanh_toan = "tien_mat";

  tramDons.value = [];
  tramTras.value = [];
  isShowBookModal.value = true;
  bookModalLoadingStations.value = true;

  try {
    const res = await adminApi.getTripDetails(trip.id);
    const tripDetails = res.data?.data || res.data;

    if (tripDetails?.tuyen_duong?.tram_dungs) {
      const stops = tripDetails.tuyen_duong.tram_dungs;
      // Lọc trạm đón
      tramDons.value = stops
        .filter((s) => s.loai_tram === "don" || s.loai_tram === "ca_hai")
        .sort((a, b) => a.thu_tu - b.thu_tu);
      // Lọc trạm trả
      tramTras.value = stops
        .filter((s) => s.loai_tram === "tra" || s.loai_tram === "ca_hai")
        .sort((a, b) => a.thu_tu - b.thu_tu);
    }
  } catch (error) {
    console.error("Lỗi tải thông tin trạm", error);
  } finally {
    bookModalLoadingStations.value = false;
  }
};

const isShowConfirmBookModal = ref(false);
const confirmBookLoading = ref(false);

const openConfirmBookModal = () => {
  if (
    !bookFormData.danh_sach_ghe ||
    !bookFormData.id_tram_don ||
    !bookFormData.id_tram_tra
  ) {
    return alert("Vui lòng điền đủ Mã ghế, Trạm đón, Trạm trả!");
  }
  isShowConfirmBookModal.value = true;
};

const submitBookTicket = async () => {
  if (confirmBookLoading.value) return;
  try {
    confirmBookLoading.value = true;

    // Tách mảng ghế. Vd: "A01, A02" -> ["A01", "A02"]
    const gheArray = bookFormData.danh_sach_ghe
      .split(",")
      .map((s) => s.trim())
      .filter((s) => s);

    const payload = { ...bookFormData, danh_sach_ghe: gheArray };
    if (!payload.id_khach_hang) delete payload.id_khach_hang;
    if (!payload.sdt_khach_hang) delete payload.sdt_khach_hang;

    await adminApi.bookTicket(payload);
    alert("Đặt vé thành công!");
    isShowConfirmBookModal.value = false;
    isShowBookModal.value = false;
    fetchTrips(pagination.currentPage); // Refresh số lượng/state
  } catch (error) {
    console.error("Lỗi đặt vé:", error);
    alert(error.response?.data?.message || "Có lỗi xảy ra khi đặt vé.");
  } finally {
    confirmBookLoading.value = false;
  }
};

// Init
onMounted(() => {
  fetchRoutesList();
  fetchTrips(1);
});
</script>

<template>
  <div class="admin-page">
    <div
      class="page-header d-flex justify-content-between align-items-center mb-4"
    >
      <h1 class="page-title">Quản lý Chuyến Xe</h1>
      <div class="header-actions" style="display: flex; gap: 0.75rem">
        <BaseButton
          @click="handleAutoGenerate"
          variant="outline"
          :loading="autoGenLoading"
        >
          <svg
            width="18"
            height="18"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            class="me-2"
            style="margin-right: 6px"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
            />
          </svg>
          Tạo Tự Động (30 ngày)
        </BaseButton>
        <BaseButton @click="openCreateModal" variant="primary">
          + Thêm Chuyến Xe
        </BaseButton>
      </div>
    </div>

    <!-- Bộ lọc -->
    <div class="filter-card">
      <div class="filter-grid">
        <div class="filter-item">
          <span class="filter-label">Tuyến đường</span>
          <select
            v-model="filters.id_tuyen_duong"
            class="custom-select"
            @change="handleSearch"
          >
            <option value="">-- Tất cả tuyến đường --</option>
            <option
              v-for="route in routesList"
              :key="route.value"
              :value="route.value"
            >
              {{ route.label }}
            </option>
          </select>
        </div>

        <div class="filter-item">
          <span class="filter-label">Ngày đi</span>
          <input
            type="date"
            v-model="filters.ngay_khoi_hanh"
            class="custom-input"
            @change="handleSearch"
          />
        </div>

        <div class="filter-item">
          <span class="filter-label">Trạng thái</span>
          <select
            v-model="filters.trang_thai"
            class="custom-select"
            @change="handleSearch"
          >
            <option value="">-- Tất cả --</option>
            <option value="hoat_dong">Hoạt động</option>
            <option value="dang_di_chuyen">Đang di chuyển</option>
            <option value="hoan_thanh">Hoàn thành</option>
            <option value="huy">Đã hủy</option>
          </select>
        </div>

        <div class="filter-item filter-btn-wrapper">
          <BaseButton @click="handleSearch" variant="secondary" block
            >Tìm & Lọc</BaseButton
          >
        </div>
      </div>
    </div>

    <!-- Bảng Dữ Liệu -->
    <div class="table-card">
      <BaseTable
        :columns="tableColumns"
        :data="trips"
        :loading="loading"
        @row-click="openDetailModal($event.id)"
      >
        <!-- Route column -->
        <template #cell(tuyen_duong)="{ item }">
          <div class="fw-bold text-dark">ID: {{ item.id_tuyen_duong }}</div>
          <div
            v-if="item.tuyen_duong?.ten_tuyen_duong"
            class="text-xs text-muted"
          >
            {{ item.tuyen_duong.ten_tuyen_duong }}
          </div>
        </template>

        <!-- Date Time column -->
        <template #cell(ngay_gio)="{ item }">
          <div class="fw-bold text-primary">
            {{
              formatDate(item.ngay_khoi_hanh).split(" ")[0] ||
              item.ngay_khoi_hanh
            }}
          </div>
          <div class="text-sm">{{ item.gio_khoi_hanh }}</div>
        </template>

        <!-- Bus/Driver column -->
        <template #cell(xe)="{ item }">
          <div class="text-sm">
            Xe ID: <span class="fw-bold">{{ item.id_xe }}</span>
          </div>
          <div class="text-sm text-muted">Tài Xế ID: {{ item.id_tai_xe }}</div>
        </template>

        <!-- Payment column -->
        <template #cell(thanh_toan)="{ item }">
          <div class="badge-payment">
            {{ getPaymentLabel(item.thanh_toan_sau) }}
          </div>
          <div class="fw-bold mt-1">{{ formatCurrency(item.tong_tien) }}</div>
        </template>

        <!-- Status column -->
        <template #cell(trang_thai)="{ value }">
          <span :class="['status-badge', getTripStatusLabel(value).class]">
            {{ getTripStatusLabel(value).text }}
          </span>
        </template>

        <!-- Actions -->
        <!-- Actions -->
        <template #cell(actions)="{ item }">
          <div class="action-buttons">
            <BaseButton
              title="Chi tiết chuyến xe"
              variant="outline"
              size="sm"
              class="btn-icon"
              @click.stop="openDetailModal(item.id)"
            >
              <Eye size="16" class="text-primary" />
            </BaseButton>
            
            <BaseButton
              title="Sơ đồ ghế"
              variant="outline"
              size="sm"
              class="btn-icon"
              @click.stop="openSeatModal(item)"
            >
              <Armchair size="16" class="text-info" />
            </BaseButton>

            <BaseButton
              title="Sửa"
              variant="outline"
              size="sm"
              class="btn-icon"
              @click.stop="openEditModal(item)"
            >
              <Edit size="16" class="text-warning" />
            </BaseButton>

            <template v-if="item.trang_thai !== 'hoan_thanh' && item.trang_thai !== 'huy'">
              <BaseButton
                title="Chuyển trạng thái"
                variant="outline"
                size="sm"
                class="btn-icon"
                @click.stop="openConfirmModal('status', item.id)"
              >
                <StepForward size="16" class="text-primary" />
              </BaseButton>
              <BaseButton
                title="Bán vé"
                variant="outline"
                size="sm"
                class="btn-icon"
                @click.stop="openBookModal(item)"
              >
                <Ticket size="16" class="text-success" />
              </BaseButton>
            </template>

            <template v-if="item.trang_thai === 'hoat_dong'">
              <BaseButton
                title="Đổi xe"
                variant="outline"
                size="sm"
                class="btn-icon"
                @click.stop="openChangeBusModal(item.id)"
              >
                <ArrowRightLeft size="16" class="text-secondary" />
              </BaseButton>
              <BaseButton
                title="Xóa chuyến"
                variant="outline"
                size="sm"
                class="btn-icon border-danger"
                @click.stop="openConfirmModal('delete', item.id)"
              >
                <Trash2 size="16" class="text-danger" />
              </BaseButton>
            </template>
          </div>
        </template>
      </BaseTable>

      <!-- Phân trang -->
      <div
        class="pagination-container mt-4 d-flex justify-content-between align-items-center"
      >
        <div class="per-page-selector">
          <span style="color: #64748b; font-size: 0.875rem">Hiển thị: </span>
          <select
            v-model="pagination.perPage"
            @change="handleSearch"
            class="custom-select"
            style="
              width: auto;
              display: inline-block;
              padding: 0.25rem 0.75rem;
              margin: 0 0.5rem;
              min-width: 70px;
            "
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="30">30</option>
            <option :value="50">50</option>
          </select>
          <span style="color: #64748b; font-size: 0.875rem"> dòng / trang</span>
          <span
            style="color: #64748b; font-size: 0.875rem; margin-left: 1rem"
            v-if="pagination.total > 0"
            >(Tổng: {{ pagination.total }})</span
          >
        </div>

        <div
          class="pagination-controls d-flex align-items-center"
          style="gap: 0.5rem"
        >
          <BaseButton
            size="sm"
            variant="outline"
            :disabled="pagination.currentPage <= 1"
            @click="fetchTrips(pagination.currentPage - 1)"
            >Trước</BaseButton
          >
          <span class="page-info fw-bold" style="padding: 0 0.75rem"
            >Trang {{ pagination.currentPage }}</span
          >
          <BaseButton
            size="sm"
            variant="outline"
            :disabled="trips.length < pagination.perPage"
            @click="fetchTrips(pagination.currentPage + 1)"
            >Sau</BaseButton
          >
        </div>
      </div>
    </div>

    <!-- 1. MODAL ADD/EDIT -->
    <BaseModal
      v-model="isShowModal"
      :title="isEditMode ? 'Cập Nhật Chuyến Xe' : 'Tạo Chuyến Xe Mới'"
      maxWidth="700px"
    >
      <form @submit.prevent="submitForm" class="form-grid">
        <div class="form-group">
          <label class="base-input-label">Tuyến Đường ID (*)</label>
          <input
            type="number"
            v-model="formData.id_tuyen_duong"
            class="custom-input"
            required
          />
        </div>

        <div class="form-group">
          <label class="base-input-label">Tài Xế ID (*)</label>
          <input
            type="number"
            v-model="formData.id_tai_xe"
            class="custom-input"
            required
          />
        </div>

        <div class="form-group">
          <label class="base-input-label">Xe ID (*)</label>
          <input
            type="number"
            v-model="formData.id_xe"
            class="custom-input"
            required
          />
        </div>

        <div class="form-group">
          <label class="base-input-label">Ngày Khởi Hành (*)</label>
          <input
            type="date"
            v-model="formData.ngay_khoi_hanh"
            class="custom-input"
            required
          />
        </div>

        <div class="form-group">
          <label class="base-input-label">Giờ Khởi Hành (*)</label>
          <input
            type="time"
            v-model="formData.gio_khoi_hanh"
            class="custom-input"
            required
          />
        </div>

        <div class="form-group">
          <label class="base-input-label">Trạng Thái</label>
          <select v-model="formData.trang_thai" class="custom-select">
            <option value="hoat_dong">Hoạt động</option>
            <option value="dang_di_chuyen">Đang di chuyển</option>
            <option value="hoan_thanh">Hoàn thành</option>
            <option value="huy">Đã hủy</option>
          </select>
        </div>

        <div class="form-group">
          <label class="base-input-label">Hình thức thanh toán</label>
          <select v-model="formData.thanh_toan_sau" class="custom-select">
            <option :value="0">Trả trước</option>
            <option :value="1">Trả sau</option>
          </select>
        </div>

        <div class="form-group">
          <label class="base-input-label">Tổng thu dự kiến (VNĐ)</label>
          <input
            type="number"
            v-model="formData.tong_tien"
            class="custom-input"
            min="0"
          />
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
          >{{ isEditMode ? "Lưu Lại" : "Tạo Chuyến" }}</BaseButton
        >
      </template>
    </BaseModal>

    <!-- MODAL CHI TIẾT CHUYẾN XE -->
    <BaseModal
      v-model="isShowDetailModal"
      title="Chi Tiết Chuyến Xe"
      maxWidth="900px"
      customClass="modern-detail-modal"
    >
      <div v-if="detailLoading" class="text-center p-5">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Đang tải...</span>
        </div>
        <p class="mt-3 text-muted">Đang tải thông tin chuyến xe...</p>
      </div>

      <div v-else-if="detailData" class="detail-content p-2">
        <!-- Header Info Card -->
        <div class="card mb-4 border-0 shadow-sm rounded-4 header-card">
          <div
            class="card-body p-4 d-flex justify-content-between align-items-center"
          >
            <div class="d-flex align-items-center gap-3">
              <div class="icon-circle bg-primary bg-opacity-10 text-primary">
                <i class="fas fa-route fs-4"></i>
              </div>
              <div>
                <h4 class="mb-1 text-dark fw-bold">
                  Chuyến #{{ detailData.id }}
                </h4>
                <div class="d-flex align-items-center gap-2 text-muted fs-6">
                  <i class="far fa-calendar-alt"></i>
                  <span>{{
                    formatDate(detailData.ngay_khoi_hanh)?.split(" ")[0]
                  }}</span>
                  <span class="text-primary fw-bold ms-2">{{
                    detailData.gio_khoi_hanh
                  }}</span>
                </div>
              </div>
            </div>
            <div class="d-flex flex-column align-items-end gap-2">
              <span
                :class="[
                  'status-badge lh-1',
                  getTripStatusLabel(detailData.trang_thai).class,
                ]"
                style="font-size: 0.9rem; padding: 0.5rem 1rem"
              >
                {{ getTripStatusLabel(detailData.trang_thai).text }}
              </span>
              <div class="badge bg-light text-dark border">
                {{
                  detailData.thanh_toan_sau === 0
                    ? "Thanh toán trả trước"
                    : "Thanh toán trả sau"
                }}
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <!-- Cột Trái -->
          <div class="col-lg-6">
            <!-- Thông tin Tuyến Đường -->
            <div class="card h-100 border-0 shadow-sm rounded-4 info-card">
              <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                <h5
                  class="card-title text-primary fw-bold d-flex align-items-center gap-2 m-0"
                >
                  <i class="fas fa-map-marked-alt"></i> Lộ Trình & Tuyến
                </h5>
              </div>
              <div class="card-body p-4" v-if="detailData.tuyen_duong">
                <h6 class="fw-bold mb-4 text-dark">
                  {{ detailData.tuyen_duong.ten_tuyen_duong }}
                </h6>

                <div class="route-timeline position-relative mb-4 ms-2">
                  <div class="timeline-line"></div>
                  <div class="timeline-item position-relative mb-3 ps-4">
                    <div class="timeline-dot start-dot"></div>
                    <div class="fw-medium">
                      {{ detailData.tuyen_duong.diem_bat_dau }}
                    </div>
                    <small class="text-muted">Điểm khởi hành</small>
                  </div>
                  <div class="timeline-item position-relative ps-4">
                    <div class="timeline-dot end-dot"></div>
                    <div class="fw-medium">
                      {{ detailData.tuyen_duong.diem_ket_thuc }}
                    </div>
                    <small class="text-muted">Điểm đến</small>
                  </div>
                </div>

                <div class="row g-3 bg-light rounded-3 p-3 mx-0">
                  <div class="col-6">
                    <div class="text-muted small mb-1">Quãng đường</div>
                    <div class="fw-semibold">
                      {{ detailData.tuyen_duong.quang_duong }} km
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted small mb-1">Cơ bản dự kiến</div>
                    <div class="fw-semibold">
                      {{ detailData.tuyen_duong.gio_du_kien }} giờ
                    </div>
                  </div>
                  <div class="col-12 mt-3 pt-3 border-top">
                    <div
                      class="d-flex justify-content-between align-items-center"
                    >
                      <span class="text-muted">Giá vé cơ bản</span>
                      <span class="fs-5 fw-bold text-success">{{
                        formatCurrency(detailData.tuyen_duong.gia_ve_co_ban)
                      }}</span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="card-body p-4 text-muted text-center" v-else>
                Đang tải hoặc không có thông tin tuyến đường...
              </div>
            </div>
          </div>

          <!-- Cột Phải -->
          <div class="col-lg-6">
            <!-- Phương Tiện -->
            <div
              class="card border-0 shadow-sm rounded-4 info-card mb-4"
              v-if="detailData.xe"
            >
              <div class="card-body p-4">
                <h5
                  class="card-title text-primary fw-bold d-flex align-items-center gap-2 mb-3"
                >
                  <i class="fas fa-bus"></i> Phương Tiện (Xe #{{
                    detailData.xe.id
                  }})
                </h5>
                <div
                  class="d-flex justify-content-between align-items-center mb-3"
                >
                  <div>
                    <div class="text-muted small mb-1">Tên Xe</div>
                    <div class="fw-semibold">{{ detailData.xe.ten_xe }}</div>
                  </div>
                  <div class="border rounded px-3 py-2 bg-light text-center">
                    <div
                      class="text-muted fs-7 mb-1"
                      style="font-size: 0.75rem"
                    >
                      Biển Số
                    </div>
                    <div class="fw-bold text-dark font-monospace">
                      {{ detailData.xe.bien_so }}
                    </div>
                  </div>
                </div>

                <div
                  class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-3"
                >
                  <span class="text-muted">Sức chứa:</span>
                  <span class="fw-bold"
                    >{{ detailData.xe.so_ghe_thuc_te }} ghế</span
                  >
                </div>

                <div
                  class="d-flex flex-wrap gap-2"
                  v-if="detailData.xe.thong_tin_cai_dat"
                >
                  <span
                    v-if="detailData.xe.thong_tin_cai_dat.camera_ai"
                    class="badge rounded-pill bg-success-subtle text-success border border-success px-3 py-2"
                  >
                    <i class="fas fa-video me-1"></i> Camera AI
                  </span>
                  <span
                    v-if="detailData.xe.thong_tin_cai_dat.gps"
                    class="badge rounded-pill bg-primary-subtle text-primary border border-primary px-3 py-2"
                  >
                    <i class="fas fa-map-marker-alt me-1"></i> GPS Tracker
                  </span>
                </div>
              </div>
            </div>

            <!-- Tài Xế -->
            <div class="card border-0 shadow-sm rounded-4 info-card">
              <div class="card-body p-4">
                <h5
                  class="card-title text-primary fw-bold d-flex align-items-center gap-2 mb-3"
                >
                  <i class="fas fa-id-card"></i> Tài Xế Phụ Trách
                </h5>

                <div
                  class="d-flex align-items-center gap-3 p-3 bg-light rounded-3"
                  v-if="detailData.tai_xe && detailData.tai_xe.id"
                >
                  <div
                    class="driver-avatar bg-white border shadow-sm d-flex align-items-center justify-content-center rounded-circle"
                    style="width: 50px; height: 50px"
                  >
                    <i class="fas fa-user-tie text-primary fs-4"></i>
                  </div>
                  <div class="flex-grow-1">
                    <div class="fw-bold text-dark fs-6">
                      {{ detailData.tai_xe.ho_ten }}
                    </div>
                    <div class="text-muted small">
                      Tài xế #{{ detailData.tai_xe.id }}
                    </div>
                  </div>
                  <a
                    :href="`tel:${detailData.tai_xe.so_dien_thoai}`"
                    class="btn btn-sm btn-outline-primary rounded-circle p-2"
                    title="Gọi ngay"
                  >
                    <i class="fas fa-phone"></i>
                  </a>
                </div>
                <div
                  v-else
                  class="text-center p-3 bg-light rounded-3 text-muted border border-dashed"
                >
                  <i class="fas fa-user-times fs-4 mb-2 d-block"></i>
                  Chưa có thông tin phân công tài xế
                </div>
              </div>
            </div>

            <!-- Doanh Thu Dự Kiến (Nếu admin) -->
            <div
              class="card border-0 shadow-sm rounded-4 info-card mt-4 bg-primary bg-opacity-10 border border-primary border-opacity-25 pb-0"
            >
              <div
                class="card-body p-3 d-flex justify-content-between align-items-center"
              >
                <span class="text-primary fw-medium"
                  ><i class="fas fa-coins me-2"></i>Tổng thu dự kiến</span
                >
                <span class="fs-5 fw-bold text-danger">{{
                  formatCurrency(detailData.tong_tien)
                }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center p-5 text-danger">
        <i class="fas fa-exclamation-triangle fs-1 mb-3"></i>
        <p>Không thể tải thông tin chuyến xe.</p>
      </div>

      <template #footer>
        <BaseButton
          variant="secondary"
          @click="isShowDetailModal = false"
          class="px-4 py-2 rounded-pill shadow-sm"
        >
          Đóng chi tiết
        </BaseButton>
      </template>
    </BaseModal>

    <!-- 2. MODAL CONFIRM (Xóa / Đổi Trạng Thái) -->
    <BaseModal
      v-model="confirmModal.show"
      :title="confirmModal.title"
      maxWidth="450px"
    >
      <div style="padding: 1rem 0; text-align: center">
        <p style="font-size: 1.05rem; color: #334155; margin: 0">
          {{ confirmModal.message }}
        </p>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="confirmModal.show = false"
          >Hủy</BaseButton
        >
        <BaseButton
          :variant="confirmModal.action === 'delete' ? 'danger' : 'primary'"
          :loading="confirmModal.loading"
          @click="executeConfirmAction"
        >
          Xác nhận
        </BaseButton>
      </template>
    </BaseModal>

    <!-- 3. MODAL ĐỔI XE -->
    <BaseModal v-model="isShowBusModal" title="Đổi Xe Mới" maxWidth="400px">
      <div class="form-group">
        <label class="base-input-label">Nhập mã ID xe thay thế (*)</label>
        <input
          type="number"
          v-model="busFormData.id_xe"
          class="custom-input"
          placeholder="VD: 5"
          required
        />
        <p class="text-xs text-muted mt-2">
          Hệ thống sẽ đồng bộ khách đã đặt vé sang sơ đồ ghế mới cùng mã.
        </p>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="isShowBusModal = false"
          >Hủy</BaseButton
        >
        <BaseButton
          variant="primary"
          :loading="busModalLoading"
          @click="submitChangeBus"
          >Đổi Xe</BaseButton
        >
      </template>
    </BaseModal>

    <!-- 4. MODAL SƠ ĐỒ GHẾ -->
    <BaseModal
      v-model="isShowSeatModal"
      :title="`Sơ đồ xe - Chuyến #${currentTripForSeats?.id}`"
      maxWidth="800px"
    >
      <div v-if="seatLoading" class="text-center py-4">
        Đang tải biểu đồ ghế...
      </div>
      <div
        v-else-if="seatData.length === 0"
        class="text-center py-4 text-muted"
      >
        Không tìm thấy dữ liệu ghế cho chuyến xe này.
      </div>
      <div v-else class="seat-map-wrapper">
        <div class="seat-grid">
          <div
            v-for="seat in seatData"
            :key="seat.id_ghe || seat.ma_ghe"
            :class="[
              'seat-box',
              seat.trang_thai === 'da_dat' ? 'seat-booked' : 'seat-free',
              selectedSeats.includes(seat.ma_ghe) ? 'seat-selected' : '',
            ]"
            @click.stop="toggleSeatSelection(seat)"
            :style="{
              cursor: seat.trang_thai === 'da_dat' ? 'not-allowed' : 'pointer',
            }"
          >
            <div class="seat-code">{{ seat.ma_ghe }}</div>
            <div class="seat-status">
              {{
                seat.trang_thai === "da_dat"
                  ? "Đã đặt"
                  : selectedSeats.includes(seat.ma_ghe)
                    ? "Đang chọn"
                    : "Trống"
              }}
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <div
          class="w-100 d-flex justify-content-between align-items-center"
          style="width: 100%"
        >
          <div class="text-sm">
            <span v-if="selectedSeats.length > 0"
              >Đã chọn:
              <span class="fw-bold text-primary">{{
                selectedSeats.join(", ")
              }}</span>
              ({{ selectedSeats.length }} ghế)</span
            >
          </div>
          <div style="display: flex; gap: 0.5rem">
            <BaseButton variant="secondary" @click="isShowSeatModal = false"
              >Đóng</BaseButton
            >
            <BaseButton
              v-if="
                currentTripForSeats?.trang_thai !== 'hoan_thanh' &&
                currentTripForSeats?.trang_thai !== 'huy'
              "
              variant="primary"
              :disabled="selectedSeats.length === 0"
              @click="handleBookSelectedSeats"
            >
              Đặt Vé Các Ghế Này
            </BaseButton>
          </div>
        </div>
      </template>
    </BaseModal>

    <!-- 5. MODAL ĐẶT VÉ NHANH -->
    <BaseModal
      v-model="isShowBookModal"
      title="🎟 Đặt Vé Nhanh (Bán Tại Bến)"
      maxWidth="600px"
    >
      <form @submit.prevent="openConfirmBookModal" class="form-grid">
        <div class="form-group" style="grid-column: span 2">
          <label class="base-input-label">Mã Ghế (*)</label>
          <input
            type="text"
            v-model="bookFormData.danh_sach_ghe"
            class="custom-input"
            placeholder="Nhập các mã ghế cách nhau bởi dấu phẩy. VD: A01, A02"
            required
          />
          <span class="text-xs text-muted mt-1 d-block"
            >Hãy xem nút 'Ghế' ở cột ngoài để xem trước mã ghế còn trống.</span
          >
        </div>

        <div class="form-group">
          <label class="base-input-label">Trạm Đón (*)</label>
          <select
            v-model="bookFormData.id_tram_don"
            class="custom-select"
            required
            :disabled="bookModalLoadingStations"
          >
            <option value="" disabled>
              {{
                bookModalLoadingStations ? "Đang tải..." : "-- Chọn Trạm Đón --"
              }}
            </option>
            <option v-for="tram in tramDons" :key="tram.id" :value="tram.id">
              {{ tram.ten_tram }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label class="base-input-label">Trạm Trả (*)</label>
          <select
            v-model="bookFormData.id_tram_tra"
            class="custom-select"
            required
            :disabled="bookModalLoadingStations"
          >
            <option value="" disabled>
              {{
                bookModalLoadingStations ? "Đang tải..." : "-- Chọn Trạm Trả --"
              }}
            </option>
            <option v-for="tram in tramTras" :key="tram.id" :value="tram.id">
              {{ tram.ten_tram }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label class="base-input-label">ID Khách Hàng (Tùy chọn)</label>
          <input
            type="number"
            v-model="bookFormData.id_khach_hang"
            class="custom-input"
            placeholder="Nếu khách có tài khoản"
          />
        </div>

        <div class="form-group">
          <label class="base-input-label">SĐT Khách Vãng Lai</label>
          <input
            type="text"
            v-model="bookFormData.sdt_khach_hang"
            class="custom-input"
            placeholder="09xx..."
          />
        </div>

        <div class="form-group" style="grid-column: span 2">
          <label class="base-input-label">Ghi Chú</label>
          <input
            type="text"
            v-model="bookFormData.ghi_chu"
            class="custom-input"
            placeholder="Mang nhiều hành lý, đón ở ngã tư..."
          />
        </div>

        <div class="form-group" style="grid-column: span 2">
          <label class="base-input-label">Phương Thức Thanh Toán</label>
          <select
            v-model="bookFormData.phuong_thuc_thanh_toan"
            class="custom-select"
          >
            <option value="tien_mat">Tiền mặt</option>
            <option value="chuyen_khoan">Chuyển khoản</option>
            <option value="vi_dien_tu">Ví điện tử</option>
          </select>
        </div>

        <div class="form-group" style="grid-column: span 2">
          <label class="base-input-label">Trạng Thái Vé</label>
          <select v-model="bookFormData.tinh_trang" class="custom-select">
            <option value="dang_cho">Đang chờ</option>
            <option value="da_thanh_toan">Đã thanh toán (xác nhận cọc)</option>
            <option value="huy">Đã hủy</option>
          </select>
        </div>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="isShowBookModal = false"
          >Hủy</BaseButton
        >
        <BaseButton variant="primary" @click="openConfirmBookModal"
          >Tiếp tục</BaseButton
        >
      </template>
    </BaseModal>

    <!-- 6. MODAL XÁC NHẬN ĐẶT VÉ -->
    <BaseModal
      v-model="isShowConfirmBookModal"
      title="Xác nhận thông tin đặt vé"
      maxWidth="500px"
    >
      <div
        class="confirm-booking-details"
        style="font-size: 0.95rem; line-height: 1.8; color: #334155"
      >
        <p>
          <strong>Mã ghế:</strong>
          <span class="text-primary fw-bold">{{
            bookFormData.danh_sach_ghe
          }}</span>
        </p>
        <p>
          <strong>Trạm đón:</strong>
          {{
            tramDons.find((t) => t.id === bookFormData.id_tram_don)?.ten_tram ||
            "Chưa chọn"
          }}
        </p>
        <p>
          <strong>Trạm trả:</strong>
          {{
            tramTras.find((t) => t.id === bookFormData.id_tram_tra)?.ten_tram ||
            "Chưa chọn"
          }}
        </p>
        <p v-if="bookFormData.sdt_khach_hang">
          <strong>SĐT Khách:</strong> {{ bookFormData.sdt_khach_hang }}
        </p>
        <p v-if="bookFormData.id_khach_hang">
          <strong>ID Khách:</strong> {{ bookFormData.id_khach_hang }}
        </p>
        <p>
          <strong>Thanh toán:</strong>
          {{
            bookFormData.phuong_thuc_thanh_toan === "tien_mat"
              ? "Tiền mặt"
              : bookFormData.phuong_thuc_thanh_toan === "chuyen_khoan"
                ? "Chuyển khoản"
                : "Ví điện tử"
          }}
        </p>
        <p>
          <strong>Trạng thái:</strong>
          <span
            :class="
              bookFormData.tinh_trang === 'dang_cho'
                ? 'text-warning fw-bold'
                : bookFormData.tinh_trang === 'huy'
                  ? 'text-danger fw-bold'
                  : 'text-success fw-bold'
            "
            >{{
              bookFormData.tinh_trang === "dang_cho"
                ? "Đang chờ"
                : bookFormData.tinh_trang === "huy"
                  ? "Đã hủy"
                  : "Đã thanh toán"
            }}</span
          >
        </p>
        <p v-if="bookFormData.ghi_chu">
          <strong>Ghi chú:</strong> {{ bookFormData.ghi_chu }}
        </p>

        <div class="mt-4 text-center">
          <p class="text-sm text-muted">
            Vui lòng kiểm tra kỹ các thông tin trước khi xác nhận tạo vé.
          </p>
        </div>
      </div>
      <template #footer>
        <BaseButton
          variant="secondary"
          @click="isShowConfirmBookModal = false"
          :disabled="confirmBookLoading"
          >Quay lại</BaseButton
        >
        <BaseButton
          variant="primary"
          :loading="confirmBookLoading"
          @click="submitBookTicket"
          >Chốt Vé</BaseButton
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
  margin: 0;
}

.d-flex {
  display: flex;
}
.justify-content-between {
  justify-content: space-between;
}
.align-items-center {
  align-items: center;
}
.mb-4 {
  margin-bottom: 1.5rem;
}
.mt-4 {
  margin-top: 1.5rem;
}
.mt-2 {
  margin-top: 0.5rem;
}
.me-2 {
  margin-right: 0.5rem;
}
.py-4 {
  padding-top: 2rem;
  padding-bottom: 2rem;
}
.text-center {
  text-align: center;
}
.fw-bold {
  font-weight: 600;
}
.text-primary {
  color: #4f46e5;
}
.text-dark {
  color: #1e293b;
}
.text-muted {
  color: #64748b;
}
.text-xs {
  font-size: 0.75rem;
}
.text-sm {
  font-size: 0.875rem;
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
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  align-items: flex-end;
}

.filter-item {
  flex: 1;
  min-width: 200px;
}

.filter-btn-wrapper {
  flex: 0 0 auto;
}

.filter-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #475569;
  margin-bottom: 0.4rem;
}

/* Table Card */
.table-card {
  background: white;
  border-radius: 12px;
  padding: 1rem;
  box-shadow:
    0 10px 15px -3px rgba(0, 0, 0, 0.05),
    0 4px 6px -2px rgba(0, 0, 0, 0.025);
  border: 1px solid rgba(226, 232, 240, 0.8);
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

.badge-payment {
  background: #f1f5f9;
  color: #475569;
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  display: inline-block;
}

/* Actions */
.action-buttons {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.btn-icon {
  padding: 0.35rem 0.6rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  background: white;
  color: #64748b;
  border-color: #e2e8f0;
  transition: all 0.2s;
}

.btn-icon:hover,
.action-dropdown.active .btn-icon {
  background: #f8fafc;
  color: #3b82f6;
  border-color: #cbd5e1;
}

.dropdown-menu {
  position: absolute;
  display: none;
  padding: 0.5rem 0;
  background-color: #fff;
  border: 1px solid rgba(0, 0, 0, 0.15);
  border-radius: 0.5rem;
  box-shadow:
    0 10px 15px -3px rgba(0, 0, 0, 0.1),
    0 4px 6px -2px rgba(0, 0, 0, 0.05);
  z-index: 1050;
  min-width: 13rem;
}

.dropdown-menu.show {
  display: block;
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 1.25rem;
  font-size: 0.875rem;
  color: #334155;
  transition: all 0.2s;
  text-decoration: none;
}

.dropdown-item i {
  width: 1.25rem;
  text-align: center;
  font-size: 1rem;
}

.dropdown-item:hover {
  background-color: #f1f5f9;
  color: #0f172a;
}

.dropdown-divider {
  margin: 0.5rem 0;
  border-top: 1px solid #e2e8f0;
}

/* Modern Detail Modal Styles */
.modern-detail-modal .modal-content {
  border-radius: 16px;
  border: none;
}

.header-card {
  background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
  border: 1px solid #e2e8f0 !important;
}

.icon-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.info-card {
  border: 1px solid #f1f5f9 !important;
  background-color: #ffffff;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.info-card:hover {
  box-shadow:
    0 10px 25px -5px rgba(0, 0, 0, 0.05),
    0 8px 10px -6px rgba(0, 0, 0, 0.01) !important;
}

.info-card .card-header {
  background-color: transparent !important;
}

.route-timeline {
  margin-left: 0.5rem;
}

.timeline-line {
  position: absolute;
  left: 5px;
  top: 10px;
  bottom: 25px;
  width: 2px;
  background-color: #cbd5e1;
  border-radius: 2px;
}

.timeline-dot {
  position: absolute;
  left: 0;
  top: 4px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background-color: white;
  border: 2px solid #3b82f6;
  z-index: 1;
}

.timeline-dot.end-dot {
  border-color: #10b981;
}

.driver-avatar {
  border: 2px solid #e2e8f0;
}

/* Form Styles */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

.form-group {
  margin-bottom: 0.5rem;
}

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

/* Sơ đồ ghế */
.seat-map-wrapper {
  background: #f8fafc;
  padding: 1.5rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}
.seat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
  gap: 1rem;
}
.seat-box {
  background: white;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.75rem 0.5rem;
  text-align: center;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s;
}
.seat-box:hover {
  transform: translateY(-2px);
}
.seat-booked {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #dc2626;
}
.seat-free {
  background: #f0fdf4;
  border-color: #86efac;
  color: #16a34a;
}
.seat-selected {
  background: #e0e7ff;
  border-color: #6366f1;
  color: #4f46e5;
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.5);
}
.seat-code {
  font-weight: bold;
  font-size: 1rem;
  margin-bottom: 0.25rem;
}
.seat-status {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Responsive */
@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  .filter-item {
    min-width: 100%;
  }
}
</style>
