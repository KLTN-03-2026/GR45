<script setup>
import { ref, reactive, onMounted, watch, computed } from "vue";
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
import { getTicketStatus } from "@/utils/status";

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

// --- NHÃN LOẠI VÉ ---
const loaiVeLabel = (l) => {
  // 'khach_hang', 'nha_xe', 'admin'
  if (l === "khach_hang") return { text: "KH tự đặt", cls: "badge-purple" };
  if (l === "nha_xe") return { text: "Nhà xe đặt", cls: "badge-blue" };
  if (l === "admin") return { text: "Admin đặt", cls: "badge-orange" };
  return { text: "—", cls: "" };
};

const ptttLabel = (p) => {
  if (p === "tien_mat") return "💵 Tiền mặt";
  if (p === "chuyen_khoan") return "🏦 Chuyển khoản";
  if (p === "vi_dien_tu") return "📱 Ví điện tử";
  return p || "—";
};

// --- DANH SÁCH VÉ ---
const tickets = ref([]);
const loading = ref(false);
const pagination = reactive({
  currentPage: 1,
  perPage: 15,
  total: 0,
  lastPage: 1,
});
const searchQuery = ref("");
const filterStatus = ref("");
const filterTripId = ref("");

const tableColumns = [
  { key: "ma_ve", label: "Mã Vé" },
  { key: "khach", label: "Khách Hàng" },
  { key: "chuyen", label: "Chuyến Xe" },
  { key: "ghe", label: "Ghế" },
  { key: "tong_tien", label: "Tổng Tiền" },
  { key: "loai_ve", label: "Loại Vé" },
  { key: "tinh_trang", label: "Trạng Thái" },
  { key: "thoi_gian_dat", label: "Thời Gian Đặt" },
  { key: "actions", label: "Hành Động" },
];

const fetchTickets = async (page = 1) => {
  try {
    loading.value = true;
    const res = await operatorApi.getTickets({
      per_page: pagination.perPage,
      search: searchQuery.value || undefined,
      tinh_trang: filterStatus.value || undefined,
      id_chuyen_xe: filterTripId.value || undefined,
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
    tickets.value = Array.isArray(list) ? list : [];
    pagination.currentPage = info.current_page || 1;
    pagination.total = info.total || 0;
    pagination.lastPage = info.last_page || 1;
  } catch (e) {
    console.error(e);
    showToast("Không thể tải danh sách vé!", "error");
  } finally {
    loading.value = false;
  }
};

// --- MODAL ĐẶT VÉ HỘ ---
const isBookModal = ref(false);
const bookLoading = ref(false);
const tripOptions = ref([]);
const availableSeats = ref([]);
const pickupStops = ref([]);
const dropoffStops = ref([]);
const loadingBookingData = ref(false);
const searchTrip = ref("");
const searchPickup = ref("");
const searchDropoff = ref("");
const bookForm = reactive({
  id_chuyen_xe: "",
  danh_sach_ghe: [],
  id_tram_don: "",
  id_tram_tra: "",
  ten_khach_hang: "",
  sdt_khach_hang: "",
  ghi_chu: "",
  phuong_thuc_thanh_toan: "tien_mat",
  tinh_trang: "da_thanh_toan",
});

const openBookModal = () => {
  Object.assign(bookForm, {
    id_chuyen_xe: "",
    danh_sach_ghe: [],
    id_tram_don: "",
    id_tram_tra: "",
    ten_khach_hang: "",
    sdt_khach_hang: "",
    ghi_chu: "",
    phuong_thuc_thanh_toan: "tien_mat",
    tinh_trang: "da_thanh_toan",
  });
  searchTrip.value = "";
  searchPickup.value = "";
  searchDropoff.value = "";
  isBookModal.value = true;
  loadTripsForBooking();
};

const tripLabel = (trip) => {
  const routeName = trip?.tuyen_duong?.ten_tuyen_duong || `Chuyến #${trip.id}`;
  return `${routeName} - ${formatDateOnly(trip.ngay_khoi_hanh)} ${formatTimeOnly(trip.gio_khoi_hanh)}`;
};

const normalizeText = (val) =>
  String(val || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "");

const filteredTripOptions = computed(() => {
  const q = normalizeText(searchTrip.value);
  if (!q) return tripOptions.value;
  return tripOptions.value.filter((trip) =>
    [
      trip.id,
      trip?.tuyen_duong?.ten_tuyen_duong,
      trip?.tuyen_duong?.diem_bat_dau,
      trip?.tuyen_duong?.diem_ket_thuc,
      formatDateOnly(trip.ngay_khoi_hanh),
      formatTimeOnly(trip.gio_khoi_hanh),
    ].some((f) => normalizeText(f).includes(q))
  );
});

const filteredPickupStops = computed(() => {
  const q = normalizeText(searchPickup.value);
  if (!q) return pickupStops.value;
  return pickupStops.value.filter((stop) =>
    [stop.ten_tram, stop.dia_chi].some((f) => normalizeText(f).includes(q))
  );
});

const filteredDropoffStops = computed(() => {
  const q = normalizeText(searchDropoff.value);
  if (!q) return dropoffStops.value;
  return dropoffStops.value.filter((stop) =>
    [stop.ten_tram, stop.dia_chi].some((f) => normalizeText(f).includes(q))
  );
});

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

const bookingSeatsByFloor = computed(() => {
  const grouped = availableSeats.value.reduce((acc, seat) => {
    const floor = Number(seat.tang || 1);
    if (!acc[floor]) acc[floor] = [];
    acc[floor].push(seat);
    return acc;
  }, {});
  return Object.entries(grouped)
    .sort((a, b) => Number(a[0]) - Number(b[0]))
    .map(([floor, seats]) => ({
      floor: Number(floor),
      seats: [...seats].sort((x, y) =>
        String(x.ma_ghe || "").localeCompare(String(y.ma_ghe || ""))
      ),
    }));
});

const isBookSeatBlocked = (seat) =>
  seat?.trang_thai === "da_dat" || seat?.trang_thai === "bao_tri_hoac_khoa";

const seatText = (item) => {
  const seats = Array.isArray(item?.chi_tiet_ves)
    ? item.chi_tiet_ves.map((ct) => ct?.ghe?.ma_ghe).filter(Boolean)
    : [];
  return seats.length ? seats.join(", ") : "—";
};

const loadTripsForBooking = async () => {
  try {
    loadingBookingData.value = true;
    const res = await operatorApi.getTrips({ per_page: 100, trang_thai: "hoat_dong" });
    if (Array.isArray(res.data?.data?.data?.data)) {
      tripOptions.value = res.data.data.data.data;
    } else if (Array.isArray(res.data?.data?.data)) {
      tripOptions.value = res.data.data.data;
    } else if (Array.isArray(res.data?.data)) {
      tripOptions.value = res.data.data;
    } else {
      tripOptions.value = [];
    }
  } catch (e) {
    tripOptions.value = [];
    showToast("Không tải được danh sách chuyến xe để đặt vé hộ.", "error");
  } finally {
    loadingBookingData.value = false;
  }
};

const loadTripBookingData = async (tripId) => {
  if (!tripId) {
    availableSeats.value = [];
    pickupStops.value = [];
    dropoffStops.value = [];
    bookForm.danh_sach_ghe = [];
    bookForm.id_tram_don = "";
    bookForm.id_tram_tra = "";
    return;
  }

  try {
    loadingBookingData.value = true;
    const [seatRes, stopRes] = await Promise.all([
      operatorApi.getTripSeats(tripId),
      operatorApi.getTripStops(tripId),
    ]);
    const seatData = seatRes.data?.data ?? seatRes.data;
    availableSeats.value = Array.isArray(seatData) ? seatData : [];

    const stopPayload = stopRes?.data?.data || stopRes?.data || {};
    pickupStops.value = Array.isArray(stopPayload?.tram_don)
      ? stopPayload.tram_don
      : Array.isArray(stopPayload?.data?.tram_don)
        ? stopPayload.data.tram_don
        : [];
    dropoffStops.value = Array.isArray(stopPayload?.tram_tra)
      ? stopPayload.tram_tra
      : Array.isArray(stopPayload?.data?.tram_tra)
        ? stopPayload.data.tram_tra
        : [];

    bookForm.danh_sach_ghe = [];
    bookForm.id_tram_don = pickupStops.value[0]?.id || "";
    bookForm.id_tram_tra = dropoffStops.value[dropoffStops.value.length - 1]?.id || "";
  } catch (e) {
    availableSeats.value = [];
    pickupStops.value = [];
    dropoffStops.value = [];
    showToast("Không tải được ghế hoặc trạm của chuyến xe.", "error");
  } finally {
    loadingBookingData.value = false;
  }
};

const toggleBookSeat = (seat) => {
  if (isBookSeatBlocked(seat)) return;
  const code = seat.ma_ghe;
  const idx = bookForm.danh_sach_ghe.indexOf(code);
  if (idx >= 0) {
    bookForm.danh_sach_ghe.splice(idx, 1);
  } else {
    bookForm.danh_sach_ghe.push(code);
  }
};

const submitBook = async () => {
  const danhSach = Array.isArray(bookForm.danh_sach_ghe)
    ? bookForm.danh_sach_ghe.filter(Boolean)
    : [];

  if (!bookForm.id_chuyen_xe) {
    showToast("Vui lòng chọn chuyến xe.", "error");
    return;
  }

  if (!danhSach.length) {
    showToast("Vui lòng chọn ít nhất 1 ghế.", "error");
    return;
  }
  if (!bookForm.id_tram_don || !bookForm.id_tram_tra) {
    showToast("Vui lòng chọn trạm đón và trạm trả.", "error");
    return;
  }
  if (Number(bookForm.id_tram_don) === Number(bookForm.id_tram_tra)) {
    showToast("Trạm đón và trạm trả không được trùng nhau.", "error");
    return;
  }
  if (!String(bookForm.ten_khach_hang || "").trim()) {
    showToast("Vui lòng nhập tên khách hàng.", "error");
    return;
  }
  if (!String(bookForm.sdt_khach_hang || "").trim()) {
    showToast("Vui lòng nhập SĐT khách hàng.", "error");
    return;
  }

  try {
    bookLoading.value = true;
    const payload = {
      id_chuyen_xe: Number(bookForm.id_chuyen_xe),
      danh_sach_ghe: danhSach,
      id_tram_don: Number(bookForm.id_tram_don),
      id_tram_tra: Number(bookForm.id_tram_tra),
      ten_khach_hang: String(bookForm.ten_khach_hang || "").trim(),
      sdt_khach_hang: String(bookForm.sdt_khach_hang || "").trim(),
      phuong_thuc_thanh_toan: bookForm.phuong_thuc_thanh_toan,
      tinh_trang: bookForm.tinh_trang,
    };
    if (bookForm.ghi_chu) payload.ghi_chu = bookForm.ghi_chu;

    await operatorApi.bookTicket(payload);
    showToast(`Đặt ${danhSach.length} vé thành công!`);
    isBookModal.value = false;
    fetchTickets(1);
  } catch (e) {
    const msg = e.response?.data?.errors
      ? Object.values(e.response.data.errors).flat()[0]
      : e.response?.data?.message || "Đặt vé thất bại!";
    showToast(msg, "error");
  } finally {
    bookLoading.value = false;
  }
};

// --- MODAL CẬP NHẬT TRẠNG THÁI ---
const isStatusModal = ref(false);
const statusForm = reactive({
  id: null,
  ma_ve: "",
  tinh_trang: "",
  current: "",
});
const statusLoading = ref(false);

const openStatusModal = (ticket) => {
  statusForm.id = ticket.id;
  statusForm.ma_ve = ticket.ma_ve;
  statusForm.current = ticket.tinh_trang;
  statusForm.tinh_trang = ticket.tinh_trang;
  isStatusModal.value = true;
};

const submitStatus = async () => {
  try {
    statusLoading.value = true;
    await operatorApi.updateTicketStatus(statusForm.id, {
      tinh_trang: statusForm.tinh_trang,
    });
    showToast("Cập nhật trạng thái vé thành công!");
    isStatusModal.value = false;
    fetchTickets(pagination.currentPage);
  } catch (e) {
    showToast(e.response?.data?.message || "Cập nhật thất bại!", "error");
  } finally {
    statusLoading.value = false;
  }
};

// --- MODAL HỦY VÉ ---
const isCancelModal = ref(false);
const cancelId = ref(null);
const cancelCode = ref("");
const cancelLoading = ref(false);

const openCancelModal = (ticket) => {
  cancelId.value = ticket.id;
  cancelCode.value = ticket.ma_ve;
  isCancelModal.value = true;
};

const submitCancel = async () => {
  try {
    cancelLoading.value = true;
    await operatorApi.cancelTicket(cancelId.value);
    showToast(`Đã hủy vé ${cancelCode.value} thành công!`);
    isCancelModal.value = false;
    fetchTickets(pagination.currentPage);
  } catch (e) {
    showToast(e.response?.data?.message || "Hủy vé thất bại!", "error");
  } finally {
    cancelLoading.value = false;
  }
};

// --- MODAL CHI TIẾT VÉ ---
const isDetailModal = ref(false);
const detailLoading = ref(false);
const detailData = ref(null);

const openDetailModal = async (ticket) => {
  isDetailModal.value = true;
  detailLoading.value = true;
  detailData.value = null;
  try {
    const res = await operatorApi.getTicketDetail(ticket.id);
    detailData.value = res.data?.data || res.data;
  } catch (e) {
    showToast("Không thể tải chi tiết vé!", "error");
    isDetailModal.value = false;
  } finally {
    detailLoading.value = false;
  }
};

watch(
  () => bookForm.id_chuyen_xe,
  async (value) => {
    await loadTripBookingData(Number(value));
  }
);

onMounted(() => fetchTickets());
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
        <h1 class="page-title">Quản lý Vé</h1>
        <p class="page-sub">Xem, đặt hộ, cập nhật và hủy vé cho khách hàng</p>
      </div>
      <BaseButton @click="openBookModal" variant="primary"
        >+ Đặt Vé Hộ</BaseButton
      >
    </div>

    <!-- Bộ lọc -->
    <div class="filter-card">
      <div class="filter-row">
        <div class="search-box">
          <BaseInput
            v-model="searchQuery"
            placeholder="Tìm mã vé, SĐT khách hàng..."
            @keyup.enter="fetchTickets(1)"
          />
          <BaseButton @click="fetchTickets(1)" variant="secondary"
            >Tìm</BaseButton
          >
        </div>

        <div class="filter-group">
          <label class="filter-label">Trạng thái</label>
          <select
            v-model="filterStatus"
            @change="fetchTickets(1)"
            class="custom-select"
          >
            <option value="">Tất cả</option>
            <option value="dang_cho">Đang chờ</option>
            <option value="da_thanh_toan">Đã thanh toán</option>
            <option value="huy">Đã hủy</option>
          </select>
        </div>

        <div class="filter-group">
          <label class="filter-label">ID Chuyến xe</label>
          <input
            type="number"
            v-model="filterTripId"
            @change="fetchTickets(1)"
            class="custom-input filter-trip"
            placeholder="Lọc theo ID..."
            min="1"
          />
        </div>

        <BaseButton
          @click="
            searchQuery = '';
            filterStatus = '';
            filterTripId = '';
            fetchTickets(1);
          "
          variant="outline"
          >Đặt lại</BaseButton
        >
      </div>
    </div>

    <!-- Bảng -->
    <div class="table-card">
      <BaseTable :columns="tableColumns" :data="tickets" :loading="loading">
        <template #cell(ma_ve)="{ value }">
          <span class="code-badge">{{ value }}</span>
        </template>

        <template #cell(khach)="{ item }">
          <div v-if="item.khach_hang" class="customer-cell">
            <span class="customer-name">{{ item.khach_hang.ho_va_ten }}</span>
            <span class="customer-phone"
              >📞 {{ item.khach_hang.so_dien_thoai }}</span
            >
          </div>
          <span v-else class="text-muted">KH #{{ item.id_khach_hang }}</span>
        </template>

        <template #cell(chuyen)="{ item }">
          <div v-if="item.chuyen_xe" class="trip-cell">
            <span class="trip-name">{{
              item.chuyen_xe.tuyen_duong?.ten_tuyen_duong ||
              `Chuyến #${item.id_chuyen_xe}`
            }}</span>
            <span class="trip-date"
              >📅 {{ item.chuyen_xe.ngay_khoi_hanh }}</span
            >
          </div>
          <span v-else class="text-muted">#{{ item.id_chuyen_xe }}</span>
        </template>

        <template #cell(ghe)="{ item }">
          <span class="seat-chip">{{ seatText(item) }}</span>
        </template>

        <template #cell(tong_tien)="{ value }">
          <span class="price-value">{{ formatCurrency(value) }}</span>
        </template>

        <template #cell(loai_ve)="{ value }">
          <span :class="['mini-badge', loaiVeLabel(value).cls]">{{
            loaiVeLabel(value).text
          }}</span>
        </template>

        <template #cell(tinh_trang)="{ value }">
          <span :class="['status-badge', getTicketStatus(value).class]">
            {{ getTicketStatus(value).text }}
          </span>
        </template>

        <template #cell(thoi_gian_dat)="{ value }">
          <span class="time-text">{{
            value ? formatDateTime(value) : "—"
          }}</span>
        </template>

        <template #cell(actions)="{ item }">
          <div class="action-buttons">
            <BaseButton
              size="sm"
              variant="outline"
              @click="openDetailModal(item)"
              >Chi tiết</BaseButton
            >

            <!-- Chỉ cho cập nhật khi chưa bị hủy -->
            <BaseButton
              v-if="item.tinh_trang !== 'huy'"
              size="sm"
              variant="primary"
              @click="openStatusModal(item)"
            >
              Trạng thái
            </BaseButton>

            <!-- Chỉ cho hủy khi chưa hủy -->
            <BaseButton
              v-if="item.tinh_trang !== 'huy'"
              size="sm"
              variant="danger"
              @click="openCancelModal(item)"
            >
              Hủy vé
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
            @change="fetchTickets(1)"
            class="custom-select per-page-select"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
          </select>
          <span>dòng / trang</span>
          <span v-if="pagination.total > 0" class="total-label"
            >(Tổng: {{ pagination.total }} vé)</span
          >
        </div>
        <div class="pagination-controls">
          <BaseButton
            size="sm"
            variant="outline"
            :disabled="pagination.currentPage <= 1"
            @click="fetchTickets(pagination.currentPage - 1)"
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
            @click="fetchTickets(pagination.currentPage + 1)"
            >Sau →</BaseButton
          >
        </div>
      </div>
    </div>

    <!-- ===== MODAL ĐẶT VÉ HỘ ===== -->
    <BaseModal
      v-model="isBookModal"
      title="Đặt Vé Hộ Khách Hàng"
      maxWidth="680px"
    >
      <div class="info-banner">
        💡 Nhà xe đặt hộ: <strong>loại vé = 2</strong>. Nếu nhập SĐT, hệ thống
        tự tìm hoặc tạo khách hàng mới.
      </div>
      <div v-if="loadingBookingData" class="muted" style="margin-top: 8px">
        Đang tải dữ liệu chuyến xe, ghế và trạm...
      </div>

      <form
        @submit.prevent="submitBook"
        class="form-grid-2"
        style="margin-top: 14px"
      >
        <div class="form-group full-width section-title-row">
          <h4 class="section-sub">Thông tin chuyến & ghế</h4>
        </div>

        <div class="form-group">
          <label class="form-label">Chuyến Xe *</label>
          <input
            type="text"
            v-model="searchTrip"
            class="custom-input"
            placeholder="Tìm chuyến theo tên tuyến/ngày/giờ..."
            style="margin-bottom: 0.5rem"
          />
          <select
            v-model="bookForm.id_chuyen_xe"
            class="custom-select"
            required
          >
            <option value="" disabled>-- Chọn chuyến xe --</option>
            <option
              v-for="trip in filteredTripOptions"
              :key="trip.id"
              :value="trip.id"
            >
              {{ tripLabel(trip) }}
            </option>
          </select>
        </div>

        <div class="form-group full-width">
          <label class="form-label">Ghế *</label>
          <div v-if="!availableSeats.length" class="muted">
            Không có dữ liệu ghế cho chuyến này.
          </div>
          <div v-else class="booking-seat-map-wrap">
            <div class="booking-seat-legend">
              <span class="legend-item"
                ><span class="seat-dot dot-active"></span> Hoạt động</span
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
            <div
              v-for="floor in bookingSeatsByFloor"
              :key="floor.floor"
              class="booking-seat-floor-block"
            >
              <h4 class="booking-seat-floor-title">Tầng {{ floor.floor }}</h4>
              <div
                v-for="(row, ri) in splitSeatsIntoRows(floor.seats, 2)"
                :key="ri"
                class="booking-seat-row"
                :style="{ '--seat-cols': Math.max(row.length, 1) }"
              >
                <button
                  v-for="seat in row"
                  :key="seat.id_ghe || seat.ma_ghe"
                  type="button"
                  class="booking-seat-tile"
                  :disabled="isBookSeatBlocked(seat)"
                  :class="{
                    booked: seat.trang_thai === 'da_dat',
                    blocked: seat.trang_thai === 'bao_tri_hoac_khoa',
                    selected: bookForm.danh_sach_ghe.includes(seat.ma_ghe),
                  }"
                  @click="toggleBookSeat(seat)"
                >
                  {{ seat.ma_ghe }}
                </button>
              </div>
            </div>
          </div>
          <small class="hint"
            >Ghế đã chọn:
            <strong>{{
              bookForm.danh_sach_ghe.length
                ? bookForm.danh_sach_ghe.join(", ")
                : "Chưa chọn"
            }}</strong></small
          >
        </div>

        <div class="form-group">
          <label class="form-label">Trạm Đón *</label>
          <input
            type="text"
            v-model="searchPickup"
            class="custom-input"
            placeholder="Tìm trạm đón theo tên/địa chỉ..."
            style="margin-bottom: 0.5rem"
          />
          <select
            v-model="bookForm.id_tram_don"
            class="custom-select"
            required
          >
            <option value="" disabled>-- Chọn trạm đón --</option>
            <option
              v-for="stop in filteredPickupStops"
              :key="stop.id"
              :value="stop.id"
            >
              {{ stop.ten_tram }} - {{ stop.dia_chi }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Trạm Trả *</label>
          <input
            type="text"
            v-model="searchDropoff"
            class="custom-input"
            placeholder="Tìm trạm trả theo tên/địa chỉ..."
            style="margin-bottom: 0.5rem"
          />
          <select
            v-model="bookForm.id_tram_tra"
            class="custom-select"
            required
          >
            <option value="" disabled>-- Chọn trạm trả --</option>
            <option
              v-for="stop in filteredDropoffStops"
              :key="stop.id"
              :value="stop.id"
            >
              {{ stop.ten_tram }} - {{ stop.dia_chi }}
            </option>
          </select>
        </div>

        <div class="form-group full-width section-title-row">
          <h4 class="section-sub">Thông tin khách hàng</h4>
        </div>

        <div class="form-group">
          <label class="form-label">Tên Khách Hàng *</label>
          <input
            type="text"
            v-model="bookForm.ten_khach_hang"
            class="custom-input"
            placeholder="Nhập tên khách..."
            required
          />
        </div>

        <div class="form-group">
          <label class="form-label">SĐT Khách Hàng *</label>
          <input
            type="tel"
            v-model="bookForm.sdt_khach_hang"
            class="custom-input"
            placeholder="0901234567"
            required
          />
        </div>

        <div class="form-group">
          <label class="form-label">Phương Thức Thanh Toán</label>
          <select
            v-model="bookForm.phuong_thuc_thanh_toan"
            class="custom-select"
          >
            <option value="tien_mat">💵 Tiền mặt</option>
            <option value="chuyen_khoan">🏦 Chuyển khoản</option>
            <option value="vi_dien_tu">📱 Ví điện tử</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Trạng Thái Vé</label>
          <select v-model="bookForm.tinh_trang" class="custom-select">
            <option value="da_thanh_toan">✅ Đã thanh toán ngay</option>
            <option value="dang_cho">⏳ Chờ thanh toán</option>
          </select>
        </div>

        <div class="form-group full-width">
          <label class="form-label">Ghi Chú</label>
          <input
            type="text"
            v-model="bookForm.ghi_chu"
            class="custom-input"
            placeholder="Ghi chú thêm cho vé..."
          />
        </div>
      </form>

      <template #footer>
        <BaseButton variant="secondary" @click="isBookModal = false"
          >Hủy</BaseButton
        >
        <BaseButton
          variant="primary"
          :loading="bookLoading"
          @click="submitBook"
        >
          🎫 Xác Nhận Đặt Vé
        </BaseButton>
      </template>
    </BaseModal>

    <!-- ===== MODAL CẬP NHẬT TRẠNG THÁI ===== -->
    <BaseModal
      v-model="isStatusModal"
      title="Cập Nhật Trạng Thái Vé"
      maxWidth="420px"
    >
      <div class="status-update-body">
        <p class="ticket-code-label">
          Vé: <strong>{{ statusForm.ma_ve }}</strong>
        </p>
        <p class="current-status-label">
          Trạng thái hiện tại:
          <span
            :class="['status-badge', getTicketStatus(statusForm.current).class]"
          >
            {{ getTicketStatus(statusForm.current).text }}
          </span>
        </p>

        <div class="form-group" style="margin-top: 16px">
          <label class="form-label">Trạng Thái Mới *</label>
          <div class="status-options">
            <label
              v-for="opt in [
                { v: 'dang_cho', t: '⏳ Đang chờ' },
                { v: 'da_thanh_toan', t: '✅ Đã thanh toán' },
                { v: 'huy', t: '❌ Hủy' },
              ]"
              :key="opt.v"
              class="status-radio-opt"
              :class="{ 'opt-active': statusForm.tinh_trang === opt.v }"
            >
              <input
                type="radio"
                :value="opt.v"
                v-model="statusForm.tinh_trang"
                style="display: none"
              />
              {{ opt.t }}
            </label>
          </div>
        </div>

        <div
          class="info-banner"
          style="margin-top: 12px"
          v-if="statusForm.tinh_trang === 'da_thanh_toan'"
        >
          ✅ Khi chuyển sang "Đã thanh toán", hệ thống sẽ tự ghi nhận thời điểm
          thanh toán.
        </div>
      </div>

      <template #footer>
        <BaseButton variant="secondary" @click="isStatusModal = false"
          >Hủy</BaseButton
        >
        <BaseButton
          variant="primary"
          :loading="statusLoading"
          @click="submitStatus"
          >Xác Nhận</BaseButton
        >
      </template>
    </BaseModal>

    <!-- ===== MODAL HỦY VÉ ===== -->
    <BaseModal v-model="isCancelModal" title="Xác Nhận Hủy Vé" maxWidth="420px">
      <div class="confirm-body">
        <div class="cancel-warning-icon">⚠️</div>
        <p>
          Bạn có chắc muốn hủy vé <strong>{{ cancelCode }}</strong
          >?
        </p>
        <p class="cancel-note">
          Nếu vé đang dùng voucher, hệ thống sẽ tự hoàn lại mã giảm giá.
        </p>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="isCancelModal = false"
          >Quay lại</BaseButton
        >
        <BaseButton
          variant="danger"
          :loading="cancelLoading"
          @click="submitCancel"
          >Xác Nhận Hủy Vé</BaseButton
        >
      </template>
    </BaseModal>

    <!-- ===== MODAL CHI TIẾT VÉ ===== -->
    <BaseModal v-model="isDetailModal" title="Chi Tiết Vé" maxWidth="680px">
      <div v-if="detailLoading" class="loading-state">⏳ Đang tải...</div>

      <div v-else-if="detailData" class="detail-body">
        <!-- Header vé -->
        <div class="ticket-detail-header">
          <div class="ticket-code-big">{{ detailData.ma_ve }}</div>
          <span
            :class="[
              'status-badge',
              getTicketStatus(detailData.tinh_trang).class,
            ]"
          >
            {{ getTicketStatus(detailData.tinh_trang).text }}
          </span>
        </div>

        <!-- Thông tin chính -->
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-label">Khách hàng</span>
            <span class="detail-value">{{
              detailData.khach_hang?.ho_va_ten || "—"
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">SĐT</span>
            <span class="detail-value">{{
              detailData.khach_hang?.so_dien_thoai || "—"
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Tổng tiền</span>
            <span class="detail-value price-value">{{
              formatCurrency(detailData.tong_tien)
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Khuyến mãi</span>
            <span class="detail-value">{{
              formatCurrency(detailData.tien_khuyen_mai)
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Thanh toán</span>
            <span class="detail-value">{{
              ptttLabel(detailData.phuong_thuc_thanh_toan)
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Loại vé</span>
            <span
              :class="['mini-badge', loaiVeLabel(detailData.loai_ve).cls]"
              >{{ loaiVeLabel(detailData.loai_ve).text }}</span
            >
          </div>
          <div class="detail-item">
            <span class="detail-label">Thời gian đặt</span>
            <span class="detail-value">{{
              detailData.thoi_gian_dat
                ? formatDateTime(detailData.thoi_gian_dat)
                : "—"
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Thời gian thanh toán</span>
            <span class="detail-value">{{
              detailData.thoi_gian_thanh_toan
                ? formatDateTime(detailData.thoi_gian_thanh_toan)
                : "—"
            }}</span>
          </div>
        </div>

        <!-- Chi tiết từng ghế -->
        <h4 class="section-sub" style="margin-top: 16px; margin-bottom: 10px">
          Chi Tiết Ghế
        </h4>
        <div class="seat-details-list">
          <div
            v-for="ct in detailData.chi_tiet_ves || []"
            :key="ct.id"
            class="seat-detail-item"
          >
            <div class="seat-code-box">{{ ct.ghe?.ma_ghe || "?" }}</div>
            <div class="seat-detail-info">
              <div class="seat-detail-row">
                <span
                  >Đón:
                  <strong>{{ ct.tram_don?.ten_tram || "—" }}</strong></span
                >
                <span
                  >Trả:
                  <strong>{{ ct.tram_tra?.ten_tram || "—" }}</strong></span
                >
              </div>
              <div class="seat-detail-row">
                <span
                  >Giá:
                  <strong class="price-value">{{
                    formatCurrency(ct.gia_ve)
                  }}</strong></span
                >
                <span
                  :class="[
                    'status-badge',
                    getTicketStatus(ct.tinh_trang).class,
                  ]"
                  >{{ getTicketStatus(ct.tinh_trang).text }}</span
                >
              </div>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <BaseButton variant="secondary" @click="isDetailModal = false"
          >Đóng</BaseButton
        >
        <BaseButton
          v-if="detailData && detailData.tinh_trang !== 'huy'"
          variant="primary"
          @click="
            openStatusModal(detailData);
            isDetailModal = false;
          "
        >
          Đổi Trạng Thái
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
  min-width: 260px;
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
.filter-trip {
  width: 140px;
}

/* Table */
.table-card {
  background: white;
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 4px 20px rgba(0, 80, 40, 0.05);
  border: 1px solid #dcfce7;
}
.code-badge {
  font-family: monospace;
  font-weight: 700;
  font-size: 13px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #16a34a;
  padding: 3px 8px;
  border-radius: 6px;
}
.customer-cell,
.trip-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.customer-name,
.trip-name {
  font-size: 13px;
  font-weight: 700;
  color: #1e293b;
}
.customer-phone,
.trip-date {
  font-size: 11px;
  color: #64748b;
}
.price-value {
  font-weight: 700;
  color: #16a34a;
}
.text-muted {
  color: #94a3b8;
  font-size: 12px;
}
.time-text {
  font-size: 12px;
  color: #64748b;
}
.seat-chip {
  display: inline-block;
  max-width: 180px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 12px;
  font-weight: 600;
  color: #0f766e;
  background: #ecfeff;
  border: 1px solid #a5f3fc;
  padding: 2px 8px;
  border-radius: 6px;
}

/* Status Badges */
.status-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
}
.status-pending {
  background: #fef9c3;
  color: #ca8a04;
}
.status-info {
  background: #dcfce7;
  color: #16a34a;
}
.status-rejected {
  background: #fee2e2;
  color: #dc2626;
}

/* Mini badges */
.mini-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}
.badge-blue {
  background: #dbeafe;
  color: #1d4ed8;
}
.badge-purple {
  background: #ede9fe;
  color: #7c3aed;
}
.badge-orange {
  background: #ffedd5;
  color: #c2410c;
}

/* Actions */
.action-buttons {
  display: flex;
  gap: 5px;
  flex-wrap: wrap;
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

/* === Modal Chung === */
.info-banner {
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 10px;
  padding: 10px 14px;
  font-size: 13px;
  color: #92400e;
}
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
.hint {
  font-size: 11px;
  font-weight: 400;
  color: #94a3b8;
}
.optional {
  font-size: 11px;
  font-weight: 400;
  color: #94a3b8;
}
.section-title-row {
  padding-top: 4px;
  border-top: 1px solid #f0fdf4;
  margin-top: 4px;
}
.section-sub {
  font-size: 14px;
  font-weight: 700;
  color: #0d4f35;
  margin: 0;
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
.custom-select[multiple] {
  min-height: 110px;
}

.booking-seat-map-wrap {
  max-height: 320px;
  overflow-y: auto;
  padding: 0.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}

.booking-seat-legend {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.4rem 0.8rem;
  font-size: 0.75rem;
  color: #334155;
  margin-bottom: 0.75rem;
}

.legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.seat-dot {
  width: 12px;
  height: 12px;
  border-radius: 4px;
  display: inline-block;
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

.booking-seat-floor-block {
  margin-bottom: 0.75rem;
}

.booking-seat-floor-title {
  font-size: 0.75rem;
  color: #64748b;
  margin: 0 0 0.5rem;
  font-weight: 600;
}

.booking-seat-row {
  display: grid;
  grid-template-columns: repeat(var(--seat-cols), minmax(0, 1fr));
  gap: 0.45rem;
  margin-bottom: 0.45rem;
}

.booking-seat-tile {
  width: 100%;
  border: 1px solid #86efac;
  background: #dcfce7;
  color: #166534;
  border-radius: 9px;
  padding: 0.45rem 0.2rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.booking-seat-tile:hover {
  transform: scale(1.04);
  box-shadow: 0 4px 10px rgba(22, 163, 74, 0.2);
}

.booking-seat-tile.booked {
  border-color: #fb923c;
  background: #fff7ed;
  color: #c2410c;
  cursor: not-allowed;
}

.booking-seat-tile.blocked {
  border-color: #64748b;
  background: #f1f5f9;
  color: #1e293b;
  cursor: not-allowed;
}

.booking-seat-tile.selected {
  border-color: #60a5fa;
  background: #dbeafe;
  color: #1d4ed8;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}

/* Cập nhật trạng thái */
.status-update-body {
  padding: 4px 0;
}
.ticket-code-label {
  font-size: 14px;
  color: #374151;
  margin: 0 0 8px 0;
}
.current-status-label {
  font-size: 13px;
  color: #64748b;
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 0 0;
}

.status-options {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 6px;
}
.status-radio-opt {
  padding: 12px 16px;
  border-radius: 10px;
  border: 2px solid #e2e8f0;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  transition: all 0.15s;
}
.status-radio-opt:hover {
  border-color: #16a34a;
  background: #f0fdf4;
}
.status-radio-opt.opt-active {
  border-color: #16a34a;
  background: #f0fdf4;
  font-weight: 700;
  color: #0d4f35;
}

/* Hủy vé */
.confirm-body {
  text-align: center;
  padding: 8px 0;
}
.cancel-warning-icon {
  font-size: 48px;
  margin-bottom: 12px;
}
.confirm-body p {
  font-size: 15px;
  color: #334155;
  margin: 0 0 8px 0;
}
.cancel-note {
  font-size: 13px;
  color: #64748b !important;
}

/* Chi tiết vé */
.loading-state {
  text-align: center;
  padding: 40px;
  color: #64748b;
}
.ticket-detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: #f0fdf4;
  border-radius: 12px;
  margin-bottom: 16px;
}
.ticket-code-big {
  font-family: monospace;
  font-size: 20px;
  font-weight: 800;
  color: #0d4f35;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.detail-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.detail-label {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.detail-value {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

/* Ghế chi tiết */
.seat-details-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.seat-detail-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #f1f5f9;
}
.seat-code-box {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: #dcfce7;
  border: 1px solid #86efac;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 800;
  color: #16a34a;
  flex-shrink: 0;
}
.seat-detail-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.seat-detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  color: #374151;
  gap: 8px;
  flex-wrap: wrap;
}

/* Responsive */
@media (max-width: 768px) {
  .form-grid-2,
  .detail-grid {
    grid-template-columns: 1fr;
  }
  .filter-row {
    flex-direction: column;
    align-items: stretch;
  }
  .action-buttons {
    flex-direction: column;
  }
  .pagination-container {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
