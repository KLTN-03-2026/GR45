<script setup>
import { ref, reactive, onMounted, onUnmounted, computed, watch } from "vue";
import {
  Armchair,
  Edit,
  ArrowRightLeft,
  StepForward,
  Trash2,
} from "lucide-vue-next";
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

// --- TỰ ĐỘNG XẾP LỊCH TÀI XẾ ---
const autoAssignLoading = ref(false);
const isShowReportModal = ref(false);
const reportData = ref({
  total_trips: 0,
  assigned_count: 0,
  success_trips: [],
  failed_trips: [],
});

const handleAutoAssign = async () => {
  try {
    autoAssignLoading.value = true;
    const res = await operatorApi.autoAssignDrivers();
    if (res.success || res?.success) {
      reportData.value = res.data;
      isShowReportModal.value = true;
      showToast("Tự động xếp lịch tài xế thành công!", "success");
      fetchTrips(1);
    } else {
      showToast(res.message || "Có lỗi xảy ra khi xếp lịch!", "error");
    }
  } catch (e) {
    console.error(e);
    showToast(
      e.response?.data?.message || "Không thể thực hiện xếp lịch!",
      "error",
    );
  } finally {
    autoAssignLoading.value = false;
  }
};

const tableColumns = [
  { key: "stt", label: "STT" },
  { key: "ten_tuyen_duong", label: "Tuyến đường" },
  { key: "ngay_gio", label: "Ngày / Giờ KH" },
  { key: "xe", label: "Xe" },
  { key: "tai_xe", label: "Tài xế" },
  { key: "ten_nha_xe", label: "Nhà xe" },
  { key: "trang_thai", label: "Trạng Thái" },
  { key: "actions", label: "Hành Động" },
];

const tripsTableRows = computed(() =>
  trips.value.map((item, idx) => {
    const td = item.tuyen_duong;
    const nx = td?.nha_xe || td?.nhaXe;
    const line =
      td?.ten_tuyen_duong ||
      [td?.diem_bat_dau, td?.diem_ket_thuc].filter(Boolean).join(" → ") ||
      "—";
    return {
      ...item,
      stt: (pagination.currentPage - 1) * pagination.perPage + idx + 1,
      ten_tuyen_duong: line,
      ten_nha_xe: nx?.ten_nha_xe || nx?.tenNhaXe || "—",
    };
  }),
);

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
  so_ngay: 1,
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

  // Trích xuất YYYY-MM-DD từ ngay_khoi_hanh để <input type="date"> hiển thị chính xác
  let ngayKhoiHanh = trip.ngay_khoi_hanh || "";
  if (ngayKhoiHanh.length > 10) {
    ngayKhoiHanh = ngayKhoiHanh.substring(0, 10);
  }

  // Trích xuất HH:mm từ gio_khoi_hanh để <input type="time"> hiển thị chính xác
  let gioKhoiHanh = trip.gio_khoi_hanh || "07:00";
  if (gioKhoiHanh.split(":").length === 3) {
    gioKhoiHanh = gioKhoiHanh.substring(0, 5);
  }

  Object.assign(formData, {
    id_tuyen_duong: trip.id_tuyen_duong || "",
    id_xe: trip.id_xe || "",
    id_tai_xe: trip.id_tai_xe || "",
    ngay_khoi_hanh: ngayKhoiHanh,
    gio_khoi_hanh: gioKhoiHanh,
    tong_tien: trip.tong_tien || null,
    thanh_toan_sau: trip.thanh_toan_sau || 0,
    trang_thai: trip.trang_thai || "hoat_dong",
    so_ngay: trip.so_ngay || 1,
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

  const route =
    routesList.value.find((r) => r.value === trip.id_tuyen_duong) ||
    trip.tuyen_duong;
  if (route && route.ma_nha_xe) {
    fetchVehiclesList(route.ma_nha_xe);
  } else {
    vehiclesList.value = [];
  }
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
const pickupStops = ref([]); // Danh sách trạm đón
const dropoffStops = ref([]); // Danh sách trạm trả

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
  bookFormTrip.ten_khach_hang = "";
  bookFormTrip.phuong_thuc_thanh_toan = "tien_mat";
  bookFormTrip.tinh_trang = "da_thanh_toan";
  bookFormTrip.ghi_chu = "";
  try {
    const [seatRes, stopRes] = await Promise.all([
      operatorApi.getTripSeats(trip.id),
      operatorApi.getTripStops(trip.id),
    ]);

    // Xử lý dữ liệu ghế
    const rawSeats = seatRes.data?.data ?? seatRes.data;
    seatList.value = Array.isArray(rawSeats) ? rawSeats : [];

    // Xử lý dữ liệu trạm dừng - bóc tách đúng tầng data từ Laravel
    const stopsPayload = stopRes?.data?.data || stopRes?.data || stopRes || {};
    pickupStops.value = Array.isArray(stopsPayload?.tram_don)
      ? stopsPayload.tram_don
      : Array.isArray(stopsPayload?.data?.tram_don)
        ? stopsPayload.data.tram_don
        : [];
    dropoffStops.value = Array.isArray(stopsPayload?.tram_tra)
      ? stopsPayload.tram_tra
      : Array.isArray(stopsPayload?.data?.tram_tra)
        ? stopsPayload.data.tram_tra
        : [];

    // Tự động chọn trạm đầu của danh sách đón và trạm cuối của danh sách trả
    if (pickupStops.value.length > 0) {
      bookFormTrip.id_tram_don = pickupStops.value[0].id;
    }
    if (dropoffStops.value.length > 0) {
      bookFormTrip.id_tram_tra =
        dropoffStops.value[dropoffStops.value.length - 1].id;
    }
  } catch (e) {
    showToast("Không thể tải thông tin chuyến xe!", "error");
    isSeatModal.value = false;
  } finally {
    seatLoading.value = false;
  }
};

const splitSeatsIntoRows = (seats, rows = 2) => {
  const list = Array.isArray(seats) ? [...seats] : [];
  if (!list.length) return [];
  const safeRows = Math.min(8, Math.max(1, Number(rows) || 2));
  const perRow = Math.ceil(list.length / safeRows);
  const out = [];
  for (let i = 0; i < list.length; i += perRow) {
    out.push(list.slice(i, i + perRow));
  }
  return out;
};

// Nhóm ghế theo tầng
const seatsByFloor = computed(() => {
  const result = {};
  seatList.value.forEach((g) => {
    const tang = Number(g.tang || 1);
    if (!result[tang]) result[tang] = [];
    result[tang].push(g);
  });
  return Object.entries(result)
    .sort((a, b) => Number(a[0]) - Number(b[0]))
    .map(([floor, seats]) => ({
      floor: Number(floor),
      seats: [...seats].sort((x, y) =>
        String(x.ma_ghe || "").localeCompare(String(y.ma_ghe || "")),
      ),
    }));
});

const seatStats = computed(() => {
  const total = seatList.value.length;
  const booked = seatList.value.filter((g) => g.trang_thai === "da_dat").length;
  return { total, booked, available: total - booked };
});

// Toggle chọn ghế
const toggleSeat = (seat) => {
  if (seat.trang_thai === "da_dat" || seat.trang_thai === "bao_tri_hoac_khoa")
    return;
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
  ten_khach_hang: "", // Thêm tên khách hàng
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
    showToast("Vui lòng chọn trạm đón và trạm trả!", "error");
    return;
  }
  if (bookFormTrip.id_tram_don === bookFormTrip.id_tram_tra) {
    showToast("Trạm đón và trạm trả không được trùng nhau!", "error");
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
    if (bookFormTrip.ten_khach_hang)
      payload.ten_khach_hang = bookFormTrip.ten_khach_hang;
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

const routesList = ref([]);
const fetchRoutesList = async () => {
  try {
    const res = await operatorApi.getRoutes({ per_page: 999 });
    let dataArr =
      res.data?.data?.data?.data ||
      res.data?.data?.data ||
      res.data?.data ||
      [];
    if (Array.isArray(dataArr)) {
      routesList.value = dataArr.map((r) => ({
        value: r.id,
        label:
          r.ten_tuyen_duong ||
          `${r.diem_bat_dau || ""} → ${r.diem_ket_thuc || ""}`.trim() ||
          `Tuyến #${r.id}`,
        ma_nha_xe: r.ma_nha_xe,
      }));
    }
  } catch (error) {
    console.error("Lỗi tải danh sách tuyến đường", error);
  }
};

const vehiclesList = ref([]);
const fetchVehiclesList = async (ma_nha_xe) => {
  if (!ma_nha_xe) {
    vehiclesList.value = [];
    return;
  }
  try {
    const res = await operatorApi.getVehicles({ per_page: 999, ma_nha_xe });
    let dataArr =
      res.data?.data?.data?.data ||
      res.data?.data?.data ||
      res.data?.data ||
      [];
    vehiclesList.value = Array.isArray(dataArr) ? dataArr : [];
  } catch (error) {
    console.error("Lỗi tải danh sách xe", error);
  }
};

const driversList = ref([]);
const fetchDriversList = async (ma_nha_xe) => {
  if (!ma_nha_xe) {
    driversList.value = [];
    return;
  }
  try {
    const res = await operatorApi.getDrivers({ per_page: 999, ma_nha_xe });
    let dataArr =
      res.data?.data?.data?.data ||
      res.data?.data?.data ||
      res.data?.data ||
      [];
    driversList.value = Array.isArray(dataArr) ? dataArr : [];
  } catch (error) {
    console.error("Lỗi tải danh sách tài xế", error);
  }
};

watch(
  () => formData.id_tuyen_duong,
  (newId) => {
    if (newId) {
      const route = routesList.value.find((r) => r.value === newId);
      if (route && route.ma_nha_xe) {
        fetchVehiclesList(route.ma_nha_xe);
        fetchDriversList(route.ma_nha_xe);
        return;
      }
    }
    vehiclesList.value = [];
    driversList.value = [];
  },
);

// (Removed dropdown logic)

onMounted(() => {
  fetchRoutesList();
  fetchTrips(1);
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
      <div style="display: flex; gap: 10px; align-items: center">
        <button
          @click="handleAutoAssign"
          class="btn-magic-assign"
          :disabled="autoAssignLoading"
        >
          <span v-if="autoAssignLoading" class="spinner"></span>
          <span v-else>🪄 Tự động xếp lịch tài xế</span>
        </button>
        <BaseButton @click="openCreateModal" variant="primary"
          >+ Thêm Chuyến Xe</BaseButton
        >
      </div>
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
        :data="tripsTableRows"
        :loading="loading"
        @row-click="openDetailModal($event.id)"
      >
        <template #cell(ngay_gio)="{ item }">
          <div class="date-cell">
            <span class="date-main">
              {{ formatDateOnly(item.ngay_khoi_hanh) }}
              <span v-if="item.so_ngay >= 2" class="so-ngay-badge"
                >+1 ngày</span
              >
            </span>
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

    <!-- ===== MODAL BÁO CÁO TỰ ĐỘNG XẾP LỊCH ===== -->
    <BaseModal
      v-model="isShowReportModal"
      title="🪄 Báo cáo Tự động Xếp lịch Tài xế"
      maxWidth="800px"
    >
      <div class="report-container">
        <!-- Tóm tắt -->
        <div class="report-summary-cards">
          <div class="summary-card total">
            <span class="card-title">Tổng số chuyến xe cần xếp</span>
            <span class="card-value">{{ reportData.total_trips }}</span>
          </div>
          <div class="summary-card success">
            <span class="card-title">Xếp lịch thành công</span>
            <span class="card-value text-green">{{
              reportData.assigned_count
            }}</span>
          </div>
          <div class="summary-card failed">
            <span class="card-title">Cần can thiệp thủ công</span>
            <span class="card-value text-red">{{
              reportData.total_trips - reportData.assigned_count
            }}</span>
          </div>
        </div>

        <!-- Chuyến thành công -->
        <div
          v-if="reportData.success_trips && reportData.success_trips.length > 0"
          class="report-section"
        >
          <h4 class="section-title text-green">
            ✅ Danh sách chuyến xếp lịch thành công
          </h4>
          <div class="table-wrapper">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Tuyến đường</th>
                  <th>Ngày khởi hành</th>
                  <th>Giờ</th>
                  <th>Tài xế phân công</th>
                  <th>Giờ lái tích lũy (tuần)</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in reportData.success_trips"
                  :key="item.trip_id"
                >
                  <td style="font-weight: 600">{{ item.tuyen_duong }}</td>
                  <td>{{ formatDateOnly(item.ngay_khoi_hanh) }}</td>
                  <td>{{ item.gio_khoi_hanh }}</td>
                  <td>
                    <span class="driver-badge">{{ item.driver_name }}</span>
                  </td>
                  <td>
                    <span class="hours-badge"
                      >{{ item.accumulated_hours }}h</span
                    >
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Chuyến thất bại / cần can thiệp -->
        <div
          v-if="reportData.failed_trips && reportData.failed_trips.length > 0"
          class="report-section"
        >
          <h4 class="section-title text-red">
            ⚠️ Danh sách chuyến cần can thiệp thủ công
          </h4>
          <div class="table-wrapper">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Tuyến đường</th>
                  <th>Khởi hành</th>
                  <th>Lý do không thể xếp lịch tự động</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in reportData.failed_trips"
                  :key="item.trip_id"
                  class="failed-row"
                >
                  <td style="font-weight: 600">{{ item.tuyen_duong }}</td>
                  <td>
                    {{ formatDateOnly(item.ngay_khoi_hanh) }}
                    {{ item.gio_khoi_hanh }}
                  </td>
                  <td class="text-red" style="font-weight: 500">
                    {{ item.reason }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <template #footer>
        <div
          style="
            display: flex;
            justify-content: flex-end;
            padding: 10px 0 0 0;
            gap: 10px;
          "
        >
          <BaseButton @click="isShowReportModal = false" variant="secondary"
            >Đóng báo cáo</BaseButton
          >
        </div>
      </template>
    </BaseModal>

    <!-- ===== MODAL THÊM / SỬA ===== -->
    <BaseModal
      v-model="isShowModal"
      :title="isEditMode ? 'Cập Nhật Chuyến Xe' : 'Thêm Chuyến Xe Mới'"
      maxWidth="680px"
    >
      <form @submit.prevent="submitForm" class="form-grid-2">
        <div class="form-group full-width">
          <label class="form-label">Tuyến Đường *</label>
          <select
            v-model="formData.id_tuyen_duong"
            class="custom-select"
            required
          >
            <option value="" disabled>-- Chọn Tuyến Đường --</option>
            <option
              v-for="route in routesList"
              :key="route.value"
              :value="route.value"
            >
              {{ route.label }}
            </option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Xe *</label>
          <select v-model="formData.id_xe" class="custom-select" required>
            <option value="" disabled>-- Chọn Xe --</option>
            <option
              v-for="vehicle in vehiclesList"
              :key="vehicle.id"
              :value="vehicle.id"
            >
              {{
                [vehicle.bien_so || "—", vehicle.ten_xe]
                  .filter(Boolean)
                  .join(" — ")
              }}
            </option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Tài Xế *</label>
          <select v-model="formData.id_tai_xe" class="custom-select" required>
            <option value="" disabled>-- Chọn Tài Xế --</option>
            <option
              v-for="driver in driversList"
              :key="driver.id"
              :value="driver.id"
            >
              {{
                [
                  driver.ho_ten || driver.ho_va_ten || "Không tên",
                  driver.so_dien_thoai,
                ]
                  .filter(Boolean)
                  .join(" — ")
              }}
            </option>
          </select>
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
        <div class="form-group">
          <label class="form-label">Số Ngày Chạy *</label>
          <select v-model="formData.so_ngay" class="custom-select" required>
            <option :value="1">1 ngày (Trong ngày)</option>
            <option :value="2">2 ngày (Qua ngày hôm sau)</option>
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
        <label class="form-label">Chọn xe mới *</label>
        <select v-model="newBusId" class="custom-select" required>
          <option value="" disabled>-- Chọn Xe --</option>
          <option
            v-for="vehicle in vehiclesList"
            :key="vehicle.id"
            :value="vehicle.id"
          >
            #{{ vehicle.id }} - {{ vehicle.ten_xe }} ({{ vehicle.bien_so }})
          </option>
        </select>
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
            ><span class="seat-dot dot-active"></span> Hoạt động (bấm để
            chọn)</span
          >
          <span class="legend-item"
            ><span class="seat-dot dot-booked"></span> Đã đặt</span
          >
          <span class="legend-item"
            ><span class="seat-dot dot-locked"></span> Khóa / bảo trì</span
          >
          <span class="legend-item"
            ><span class="seat-dot dot-selected"></span> Đang chọn</span
          >
        </div>

        <!-- Sơ đồ từng tầng -->
        <div
          v-for="floor in seatsByFloor"
          :key="floor.floor"
          class="floor-section"
        >
          <h4 class="floor-title">Tầng {{ floor.floor }}</h4>
          <div
            v-for="(row, ri) in splitSeatsIntoRows(floor.seats, 2)"
            :key="ri"
            class="seat-row"
            :style="{ '--seat-cols': Math.max(row.length, 1) }"
          >
            <button
              v-for="seat in row"
              :key="seat.id_ghe || seat.ma_ghe"
              type="button"
              class="seat-tile"
              :disabled="
                seat.trang_thai === 'da_dat' ||
                seat.trang_thai === 'bao_tri_hoac_khoa'
              "
              :class="{
                booked: seat.trang_thai === 'da_dat',
                blocked: seat.trang_thai === 'bao_tri_hoac_khoa',
                selected: isSeatSelected(seat),
              }"
              @click="toggleSeat(seat)"
            >
              {{ seat.ma_ghe }}
            </button>
          </div>
        </div>

        <div class="booked-note">
          <span>
            Ghế đã chọn:
            <strong>{{
              selectedSeats.length ? selectedSeats.join(", ") : "Chưa chọn"
            }}</strong>
          </span>
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
                  <label class="form-label">Trạm Đón *</label>
                  <select
                    v-model="bookFormTrip.id_tram_don"
                    class="custom-select"
                  >
                    <option value="" disabled>-- Chọn trạm đón --</option>
                    <option
                      v-for="stop in pickupStops"
                      :key="stop.id"
                      :value="stop.id"
                    >
                      {{ stop.ten_tram }} ({{ stop.dia_chi }})
                    </option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Trạm Trả *</label>
                  <select
                    v-model="bookFormTrip.id_tram_tra"
                    class="custom-select"
                  >
                    <option value="" disabled>-- Chọn trạm trả --</option>
                    <option
                      v-for="stop in dropoffStops"
                      :key="stop.id"
                      :value="stop.id"
                    >
                      {{ stop.ten_tram }} ({{ stop.dia_chi }})
                    </option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">SĐT Khách</label>
                  <input
                    type="tel"
                    v-model="bookFormTrip.sdt_khach_hang"
                    class="custom-input"
                    placeholder="0901234567"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">Tên Khách</label>
                  <input
                    type="text"
                    v-model="bookFormTrip.ten_khach_hang"
                    class="custom-input"
                    placeholder="Nguyễn Văn A..."
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

.so-ngay-badge {
  display: inline-block;
  font-size: 10px;
  background-color: #fef3c7;
  color: #d97706;
  border: 1px solid #fde68a;
  padding: 1px 6px;
  border-radius: 6px;
  font-weight: 700;
  margin-left: 6px;
  vertical-align: middle;
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

.dot-active {
  background: #dcfce7;
  border: 1px solid #86efac;
}

.dot-booked {
  background: #ffedd5;
  border: 1px solid #ea580c;
}

.dot-locked {
  background: #e2e8f0;
  border: 1px solid #475569;
}

.dot-selected {
  background: #dbeafe;
  border: 1px solid #60a5fa;
}

.floor-section {
  margin-bottom: 20px;
}

.floor-title {
  font-size: 12px;
  color: #64748b;
  margin: 0 0 8px;
  font-weight: 600;
}

.seat-row {
  display: grid;
  grid-template-columns: repeat(var(--seat-cols), minmax(0, 1fr));
  gap: 7px;
  margin-bottom: 10px;
}

.seat-tile {
  width: 100%;
  border: 1px solid #86efac;
  background: #dcfce7;
  color: #166534;
  border-radius: 9px;
  padding: 8px 4px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.seat-tile:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 10px rgba(22, 163, 74, 0.2);
}

.seat-tile.booked {
  border-color: #fb923c;
  background: #fff7ed;
  color: #c2410c;
  cursor: not-allowed;
}

.seat-tile.blocked {
  border-color: #64748b;
  background: #f1f5f9;
  color: #1e293b;
  cursor: not-allowed;
}

.seat-tile.selected {
  border-color: #60a5fa;
  background: #dbeafe;
  color: #1d4ed8;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}

.booked-note {
  margin-top: 6px;
  font-size: 12px;
  color: #64748b;
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

/* ===== TỰ ĐỘNG XẾP LỊCH TÀI XẾ STYLES ===== */
.btn-magic-assign {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border: none;
  font-weight: 600;
  font-size: 14px;
  padding: 10px 18px;
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s ease;
  box-shadow:
    0 4px 6px -1px rgba(16, 185, 129, 0.2),
    0 2px 4px -1px rgba(16, 185, 129, 0.1);
}

.btn-magic-assign:hover {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  transform: translateY(-1px);
  box-shadow:
    0 10px 15px -3px rgba(16, 185, 129, 0.3),
    0 4px 6px -2px rgba(16, 185, 129, 0.05);
}

.btn-magic-assign:active {
  transform: translateY(0);
}

.btn-magic-assign:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: white;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.report-container {
  padding: 10px;
}

.report-summary-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.summary-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.summary-card.total {
  border-left: 4px solid #64748b;
}

.summary-card.success {
  border-left: 4px solid #10b981;
  background: #f0fdf4;
}

.summary-card.failed {
  border-left: 4px solid #ef4444;
  background: #fef2f2;
}

.card-title {
  font-size: 13px;
  color: #64748b;
  margin-bottom: 6px;
  font-weight: 500;
  text-align: center;
}

.card-value {
  font-size: 26px;
  font-weight: 800;
  color: #1e293b;
}

.text-green {
  color: #10b981 !important;
}

.text-red {
  color: #ef4444 !important;
}

.report-section {
  margin-bottom: 24px;
}

.section-title {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 12px;
}

.table-wrapper {
  max-height: 250px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.report-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 13px;
}

.report-table th {
  background: #f1f5f9;
  padding: 10px 12px;
  font-weight: 600;
  color: #475569;
  border-bottom: 1px solid #e2e8f0;
}

.report-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e2e8f0;
  color: #334155;
}

.report-table tr:hover {
  background: #f8fafc;
}

.driver-badge {
  background: #ecfdf5;
  color: #047857;
  padding: 4px 8px;
  border-radius: 6px;
  font-weight: 600;
}

.hours-badge {
  background: #f1f5f9;
  color: #475569;
  padding: 4px 8px;
  border-radius: 6px;
  font-weight: 500;
}

.failed-row {
  background: #fffaf0;
}

.failed-row:hover {
  background: #fff5e6 !important;
}

/* Removed dropdown styles */
</style>
