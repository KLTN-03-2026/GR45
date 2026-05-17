<script setup>
// Driver Schedule View with HMR reload
import { ref, computed, onMounted, watch } from "vue";
import driverApi from "@/api/driverApi";
import BaseModal from "@/components/common/BaseModal.vue";
import {
  Calendar,
  ChevronLeft,
  ChevronRight,
  MapPin,
  Clock,
  Bus,
  Route,
  Navigation,
  CircleDot,
  Loader2,
  CalendarDays,
  ArrowRight,
  Info,
  Flag,
} from "lucide-vue-next";

// --- Trạng thái chính ---
const isLoading = ref(false);
const lichTrinhList = ref([]);

// Lịch tháng hiện tại
const currentMonth = ref(new Date().getMonth());
const currentYear = ref(new Date().getFullYear());
const today = new Date();

// Modal chi tiết chuyến xe
const showDetailModal = ref(false);
const detailLoading = ref(false);
const selectedChuyen = ref(null);
const lichTrinhTram = ref([]);

// --- Tên tháng và ngày tiếng Việt ---
const monthNames = [
  "Tháng 1",
  "Tháng 2",
  "Tháng 3",
  "Tháng 4",
  "Tháng 5",
  "Tháng 6",
  "Tháng 7",
  "Tháng 8",
  "Tháng 9",
  "Tháng 10",
  "Tháng 11",
  "Tháng 12",
];

const dayNames = ["CN", "Thứ 2", "Thứ 3", "Thứ 4", "Thứ 5", "Thứ 6", "Thứ 7"];

// --- Tính toán lịch ---
const daysInMonth = computed(() => {
  return new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
});

const firstDayOfMonth = computed(() => {
  return new Date(currentYear.value, currentMonth.value, 1).getDay();
});

// Tính số ngày tháng trước cần hiển thị
const prevMonthDays = computed(() => {
  const prevMonth = currentMonth.value === 0 ? 11 : currentMonth.value - 1;
  const prevYear =
    currentMonth.value === 0 ? currentYear.value - 1 : currentYear.value;
  return new Date(prevYear, prevMonth + 1, 0).getDate();
});

// Tạo mảng các tuần để render lịch dạng bảng
const calendarWeeks = computed(() => {
  const weeks = [];
  let currentWeek = [];

  // Các ngày của tháng trước (mờ)
  for (let i = 0; i < firstDayOfMonth.value; i++) {
    const day = prevMonthDays.value - firstDayOfMonth.value + i + 1;
    currentWeek.push({ day, date: null, isOtherMonth: true });
  }

  // Các ngày của tháng hiện tại
  for (let d = 1; d <= daysInMonth.value; d++) {
    const dateStr = formatToDateString(
      currentYear.value,
      currentMonth.value,
      d,
    );
    currentWeek.push({ day: d, date: dateStr, isOtherMonth: false });
    if (currentWeek.length === 7) {
      weeks.push(currentWeek);
      currentWeek = [];
    }
  }

  // Điền ngày tháng sau nếu hàng cuối chưa đủ 7
  if (currentWeek.length > 0) {
    let nextDay = 1;
    while (currentWeek.length < 7) {
      currentWeek.push({ day: nextDay++, date: null, isOtherMonth: true });
    }
    weeks.push(currentWeek);
  }

  return weeks;
});

// Lấy tất cả chuyến xe của tháng hiện tại
const fetchMonthTrips = async () => {
  try {
    isLoading.value = true;
    const startDate = formatToDateString(
      currentYear.value,
      currentMonth.value,
      1,
    );
    const endDate = formatToDateString(
      currentYear.value,
      currentMonth.value,
      daysInMonth.value,
    );
    const params = {
      ngay_bat_dau: startDate,
      ngay_ket_thuc: endDate,
      per_page: 100,
    };
    const response = await driverApi.getLichTrinhCaNhan(params);
    if (response.success) {
      lichTrinhList.value = response.data.data || response.data || [];
    }
  } catch (error) {
    console.error("Lỗi khi lấy lịch trình tháng:", error);
  } finally {
    isLoading.value = false;
  }
};

// Nhóm chuyến xe theo ngày: Map ngày (YYYY-MM-DD) -> [chuyến]
const tripsByDate = computed(() => {
  const map = {};
  lichTrinhList.value.forEach((chuyen) => {
    // API trả về ngay_khoi_hanh dạng "2026-04-22T00:00:00.000000Z" => lấy 10 ký tự đầu
    const ngay = extractDateFromISO(chuyen.ngay_khoi_hanh);
    if (!map[ngay]) map[ngay] = [];
    map[ngay].push(chuyen);
  });
  return map;
});

// Trích xuất YYYY-MM-DD từ ISO string hoặc chuỗi ngày
const extractDateFromISO = (isoStr) => {
  if (!isoStr) return "";
  return isoStr.substring(0, 10);
};

// Kiểm tra ngày có phải hôm nay không
const isToday = (dateStr) => {
  if (!dateStr) return false;
  const todayStr = formatToDateString(
    today.getFullYear(),
    today.getMonth(),
    today.getDate(),
  );
  return dateStr === todayStr;
};

// Mở modal chi tiết chuyến xe (gọi API)
const openTripDetail = async (chuyen) => {
  showDetailModal.value = true;
  detailLoading.value = true;
  selectedChuyen.value = null;
  lichTrinhTram.value = [];

  try {
    // Gọi API lấy chi tiết chuyến xe
    const detailRes = await driverApi.getChuyenXeDetail(chuyen.id);
    if (detailRes.success) {
      selectedChuyen.value = detailRes.data;
    }

    // Gọi API lấy danh sách trạm dừng
    const lichTrinhRes = await driverApi.getLichTrinhChuyen(chuyen.id);
    if (lichTrinhRes.success) {
      lichTrinhTram.value = lichTrinhRes.data?.lich_trinh || [];
    }
  } catch (error) {
    console.error("Lỗi khi lấy chi tiết chuyến xe:", error);
    // Fallback: dùng dữ liệu từ danh sách nếu API detail lỗi
    selectedChuyen.value = chuyen;
    lichTrinhTram.value = chuyen.tuyen_duong?.tram_dungs || [];
  } finally {
    detailLoading.value = false;
  }
};

// --- Điều hướng tháng ---
const prevMonth = () => {
  if (currentMonth.value === 0) {
    currentMonth.value = 11;
    currentYear.value--;
  } else {
    currentMonth.value--;
  }
};

const nextMonth = () => {
  if (currentMonth.value === 11) {
    currentMonth.value = 0;
    currentYear.value++;
  } else {
    currentMonth.value++;
  }
};

const goToToday = () => {
  currentMonth.value = today.getMonth();
  currentYear.value = today.getFullYear();
};

// --- Hàm tiện ích ---
const formatToDateString = (year, month, day) => {
  const m = String(month + 1).padStart(2, "0");
  const d = String(day).padStart(2, "0");
  return `${year}-${m}-${d}`;
};

const formatDate = (ds) => {
  if (!ds) return "";
  const d = new Date(ds);
  return d.toLocaleDateString("vi-VN", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  });
};

const formatTime = (ts) => (ts ? ts.substring(0, 5) : "--:--");

const formatTrangThai = (s) =>
  ({
    ChoChay: "Chờ chạy",
    hoat_dong: "Sẵn sàng",
    dang_di_chuyen: "Đang chạy",
    hoan_thanh: "Hoàn thành",
    da_huy: "Đã hủy",
  })[s] || s;

const getStatusClass = (s) => {
  if (["hoat_dong", "ChoChay"].includes(s)) return "status-ready";
  if (s === "dang_di_chuyen") return "status-running";
  if (s === "hoan_thanh") return "status-done";
  if (s === "da_huy") return "status-cancel";
  return "status-default";
};

const statsGioLam = ref({ gio_du_kien: 0, gio_thuc_te: 0, tuan_hien_tai: "" });

const fetchThongKeGioLam = async () => {
  try {
    const res = await driverApi.getThongKeGioLam();
    if (res.success) {
      statsGioLam.value = res.data;
    }
  } catch (error) {
    console.error("Lỗi khi lấy thống kê giờ làm:", error);
  }
};

// Theo dõi thay đổi tháng để fetch lại dữ liệu
watch([currentMonth, currentYear], () => {
  fetchMonthTrips();
});

onMounted(() => {
  fetchMonthTrips();
  fetchThongKeGioLam();
});
</script>

<template>
  <div class="lich-trinh-wrapper pb-24">
    <!-- Thống kê làm việc -->
    <div class="stats-overview-modern mb-4">
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <div
            class="stat-card modern-card p-3 d-flex flex-column justify-content-center h-100 bg-white rounded shadow-sm border-0 border-start border-4"
            style="border-left-color: #6366f1 !important"
          >
            <h6
              class="text-secondary fw-semibold mb-1 d-flex align-items-center"
            >
              <CalendarDays class="icon-inline text-info me-2" size="18" />
              Tuần hiện tại
            </h6>
            <div class="fs-6 fw-medium text-dark mt-auto">
              {{ statsGioLam.tuan_hien_tai }}
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div
            class="stat-card modern-card p-3 d-flex align-items-center h-100 bg-white rounded shadow-sm border-0 border-start border-4"
            style="border-left-color: var(--primary-color) !important"
          >
            <div
              class="stat-icon-wrapper text-primary me-3 p-2 rounded-circle"
              style="background-color: rgba(var(--primary-color-rgb), 0.1)"
            >
              <Clock size="24" />
            </div>
            <div>
              <div class="text-secondary small fw-medium mb-1">Giờ dự kiến</div>
              <div class="fs-4 fw-bold text-dark lh-1">
                {{ statsGioLam.gio_du_kien
                }}<span class="fs-6 text-muted fw-normal ms-1">giờ</span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-4">
          <div
            class="stat-card modern-card p-3 d-flex align-items-center h-100 bg-white rounded shadow-sm border-0 border-start border-4 border-success"
          >
            <div
              class="stat-icon-wrapper bg-success bg-opacity-10 text-success me-3 p-2 rounded-circle"
            >
              <CircleDot size="24" />
            </div>
            <div>
              <div class="text-secondary small fw-medium mb-1">Giờ thực tế</div>
              <div class="fs-4 fw-bold text-dark lh-1">
                {{ statsGioLam.gio_thuc_te
                }}<span class="fs-6 text-muted fw-normal ms-1">giờ</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Header bar -->
    <div class="lt-header">
      <div class="lt-header-left">
        <div class="lt-header-icon">
          <CalendarDays />
        </div>
        <div>
          <h1 class="lt-title">Lịch trình làm việc</h1>
          <p class="lt-subtitle">Xem lịch phân công chuyến xe theo từng ngày</p>
        </div>
      </div>
      <div class="lt-header-actions">
        <!-- Chú thích trạng thái -->
        <div class="header-legends">
          <div class="legend-item">
            <span class="legend-dot legend-ready"></span>
            <span>Sẵn sàng</span>
          </div>
          <div class="legend-item">
            <span class="legend-dot legend-running"></span>
            <span>Đang chạy</span>
          </div>
          <div class="legend-item">
            <span class="legend-dot legend-done"></span>
            <span>Hoàn thành</span>
          </div>
          <div class="legend-item">
            <span class="legend-dot legend-cancel"></span>
            <span>Đã hủy</span>
          </div>
        </div>
        <button class="btn-today" @click="goToToday">
          <Calendar class="btn-icon" />
          Hôm nay
        </button>
      </div>
    </div>

    <!-- Lịch dạng bảng full-width -->
    <div class="calendar-container">
      <!-- Thanh điều hướng tháng -->
      <div class="cal-nav">
        <button class="cal-nav-btn" @click="prevMonth" title="Tháng trước">
          <ChevronLeft />
        </button>
        <h2 class="cal-month-label">
          {{ monthNames[currentMonth] }}, {{ currentYear }}
        </h2>
        <button class="cal-nav-btn" @click="nextMonth" title="Tháng sau">
          <ChevronRight />
        </button>
      </div>

      <!-- Loading overlay -->
      <div v-if="isLoading" class="cal-loading-overlay">
        <Loader2 class="spin-icon" />
        <span>Đang tải lịch trình...</span>
      </div>

      <!-- Bảng lịch -->
      <div class="cal-table-wrap">
        <table class="cal-table">
          <thead>
            <tr>
              <th
                v-for="name in dayNames"
                :key="name"
                :class="{ 'th-sun': name === 'CN' }"
              >
                {{ name }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(week, wIdx) in calendarWeeks" :key="wIdx">
              <td
                v-for="(cell, dIdx) in week"
                :key="dIdx"
                class="cal-cell"
                :class="{
                  'cell-other-month': cell.isOtherMonth,
                  'cell-today': isToday(cell.date),
                  'cell-sunday': dIdx === 0 && !cell.isOtherMonth,
                  'cell-has-trips':
                    !cell.isOtherMonth && tripsByDate[cell.date]?.length > 0,
                }"
              >
                <!-- Số ngày -->
                <div
                  class="cell-day-num"
                  :class="{ 'today-badge': isToday(cell.date) }"
                >
                  {{ cell.day }}
                </div>

                <!-- Danh sách chuyến xe hiển thị trực tiếp trên ô lịch -->
                <div
                  v-if="!cell.isOtherMonth && tripsByDate[cell.date]"
                  class="cell-trips"
                >
                  <div
                    v-for="trip in tripsByDate[cell.date]"
                    :key="trip.id"
                    class="cell-trip-chip"
                    :class="getStatusClass(trip.trang_thai)"
                    @click.stop="openTripDetail(trip)"
                    :title="`${formatTime(trip.gio_khoi_hanh)} - ${trip.tuyen_duong?.ten_tuyen_duong || 'Chuyến xe'}`"
                  >
                    <span class="chip-time">{{
                      formatTime(trip.gio_khoi_hanh)
                    }}</span>
                    <span class="chip-name">{{
                      trip.tuyen_duong?.ten_tuyen_duong || "Chuyến xe"
                    }}</span>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ============================== -->
    <!-- Modal Chi Tiết Chuyến Xe       -->
    <!-- ============================== -->
    <BaseModal
      v-model="showDetailModal"
      title="Chi tiết chuyến xe"
      maxWidth="580px"
    >
      <!-- Loading -->
      <div v-if="detailLoading" class="modal-loading">
        <Loader2 class="spin-icon-lg" />
        <p>Đang tải thông tin chi tiết...</p>
      </div>

      <!-- Nội dung -->
      <div v-else-if="selectedChuyen" class="detail-content">
        <!-- Trạng thái + ngày -->
        <div class="detail-status-row">
          <span
            class="detail-status-badge"
            :class="getStatusClass(selectedChuyen.trang_thai)"
          >
            {{ formatTrangThai(selectedChuyen.trang_thai) }}
          </span>
          <span class="detail-date">
            {{ formatDate(selectedChuyen.ngay_khoi_hanh) }}
          </span>
        </div>

        <!-- Tuyến đường -->
        <div class="detail-section">
          <h4 class="detail-section-title">
            <Route class="section-icon" />
            Tuyến đường
          </h4>
          <div class="detail-route-card">
            <div class="route-endpoint">
              <div class="endpoint-dot start-dot"></div>
              <div class="endpoint-info">
                <span class="endpoint-label">Điểm đi</span>
                <span class="endpoint-name">
                  {{ selectedChuyen.tuyen_duong?.diem_bat_dau || "---" }}
                </span>
              </div>
            </div>
            <div class="route-line-vertical"></div>
            <div class="route-endpoint">
              <div class="endpoint-dot end-dot"></div>
              <div class="endpoint-info">
                <span class="endpoint-label">Điểm đến</span>
                <span class="endpoint-name">
                  {{ selectedChuyen.tuyen_duong?.diem_ket_thuc || "---" }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Thông tin chuyến -->
        <div class="detail-section">
          <h4 class="detail-section-title">
            <Info class="section-icon" />
            Thông tin chuyến
          </h4>
          <div class="detail-info-grid">
            <div class="detail-info-item">
              <Clock class="detail-info-icon icon-blue" />
              <div>
                <span class="detail-info-label">Giờ khởi hành</span>
                <span class="detail-info-value">{{
                  formatTime(selectedChuyen.gio_khoi_hanh)
                }}</span>
              </div>
            </div>
            <div class="detail-info-item">
              <Bus class="detail-info-icon icon-purple" />
              <div>
                <span class="detail-info-label">Biển số xe</span>
                <span class="detail-info-value">{{
                  selectedChuyen.xe?.bien_so || "Chưa P.Công"
                }}</span>
              </div>
            </div>
            <div class="detail-info-item">
              <Navigation class="detail-info-icon icon-green" />
              <div>
                <span class="detail-info-label">Quãng đường</span>
                <span class="detail-info-value"
                  >{{ selectedChuyen.tuyen_duong?.quang_duong || "0" }} km</span
                >
              </div>
            </div>
            <div class="detail-info-item">
              <CircleDot class="detail-info-icon icon-amber" />
              <div>
                <span class="detail-info-label">Số trạm dừng</span>
                <span class="detail-info-value"
                  >{{
                    lichTrinhTram.length ||
                    selectedChuyen.tuyen_duong?.tram_dungs?.length ||
                    0
                  }}
                  trạm</span
                >
              </div>
            </div>
          </div>
        </div>

        <!-- Danh sách trạm dừng -->
        <div
          v-if="
            lichTrinhTram.length ||
            selectedChuyen.tuyen_duong?.tram_dungs?.length
          "
          class="detail-section"
        >
          <h4 class="detail-section-title">
            <MapPin class="section-icon" />
            Lộ trình trạm dừng
          </h4>
          <div class="stops-timeline">
            <div
              v-for="(tram, idx) in lichTrinhTram.length
                ? lichTrinhTram
                : selectedChuyen.tuyen_duong?.tram_dungs || []"
              :key="tram.id"
              class="timeline-item"
            >
              <div class="timeline-left">
                <div
                  class="timeline-marker"
                  :class="
                    tram.loai_tram === 'don'
                      ? 'marker-pickup'
                      : 'marker-dropoff'
                  "
                >
                  <span class="marker-num">{{ idx + 1 }}</span>
                </div>
                <div
                  v-if="
                    idx <
                    (lichTrinhTram.length
                      ? lichTrinhTram
                      : selectedChuyen.tuyen_duong?.tram_dungs || []
                    ).length -
                      1
                  "
                  class="timeline-connector"
                ></div>
              </div>
              <div class="timeline-content">
                <span class="timeline-name">{{ tram.ten_tram }}</span>
                <div class="timeline-meta">
                  <span
                    class="timeline-type"
                    :class="
                      tram.loai_tram === 'don' ? 'type-pickup' : 'type-dropoff'
                    "
                  >
                    {{ tram.loai_tram === "don" ? "Đón khách" : "Trả khách" }}
                  </span>
                  <span v-if="tram.dia_chi" class="timeline-addr">{{
                    tram.dia_chi
                  }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Nút vào điều khiển -->
        <div
          class="detail-actions"
          v-if="
            ['hoat_dong', 'ChoChay', 'dang_di_chuyen'].includes(
              selectedChuyen.trang_thai,
            )
          "
        >
          <router-link
            :to="{
              name: 'driver-dashboard',
              query: { chuyen: String(selectedChuyen.id) },
            }"
            class="btn-go-drive"
          >
            <Navigation class="btn-drive-icon" />
            Vào điều khiển chuyến
            <ArrowRight class="btn-drive-icon" />
          </router-link>
        </div>
      </div>
    </BaseModal>
  </div>
</template>

<style scoped>
/* ====================================== */
/*  BIẾN TOÀN CỤC                         */
/* ====================================== */
.lich-trinh-wrapper {
  --color-primary: #4f6ef7;
  --color-primary-light: #eef1ff;
  --color-primary-dark: #3a4fcf;
  --color-ready: #3b82f6;
  --color-running: #f59e0b;
  --color-done: #10b981;
  --color-cancel: #ef4444;
  --radius-lg: 16px;
  --radius-md: 12px;
  --radius-sm: 8px;
  --shadow-card: 0 1px 3px rgba(0, 0, 0, 0.05), 0 4px 16px rgba(0, 0, 0, 0.04);

  min-height: 100vh;
  padding: 20px;
  font-family:
    "Inter",
    system-ui,
    -apple-system,
    sans-serif;
}

/* ====================================== */
/*  HEADER                                 */
/* ====================================== */
.lt-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;
}

.lt-header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.lt-header-icon {
  width: 46px;
  height: 46px;
  border-radius: var(--radius-md);
  background: linear-gradient(
    135deg,
    var(--color-primary),
    var(--color-primary-dark)
  );
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(79, 110, 247, 0.25);
}

.lt-header-icon svg {
  width: 22px;
  height: 22px;
}

.lt-title {
  font-size: 1.4rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
  letter-spacing: -0.02em;
}

.lt-subtitle {
  font-size: 0.82rem;
  color: #94a3b8;
  margin: 3px 0 0;
}

.lt-header-actions {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.header-legends {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 500;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 3px;
  flex-shrink: 0;
}

.legend-ready {
  background: var(--color-ready);
}
.legend-running {
  background: var(--color-running);
}
.legend-done {
  background: var(--color-done);
}
.legend-cancel {
  background: var(--color-cancel);
}

.btn-today {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  border-radius: var(--radius-sm);
  border: 1.5px solid #e2e8f0;
  background: white;
  color: var(--color-primary);
  font-weight: 600;
  font-size: 0.84rem;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-today:hover {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
  box-shadow: 0 4px 12px rgba(79, 110, 247, 0.25);
}

.btn-today .btn-icon {
  width: 16px;
  height: 16px;
}

/* ====================================== */
/*  CALENDAR CONTAINER (FULL-WIDTH)        */
/* ====================================== */
.calendar-container {
  background: white;
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
  border: 1px solid rgba(226, 232, 240, 0.8);
  overflow: hidden;
  position: relative;
}

/* Thanh điều hướng tháng */
.cal-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #fafbff 0%, #f8fafc 100%);
}

.cal-nav-btn {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  border: 1.5px solid #e2e8f0;
  background: white;
  color: #475569;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.cal-nav-btn:hover {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
  box-shadow: 0 2px 8px rgba(79, 110, 247, 0.25);
}

.cal-nav-btn svg {
  width: 20px;
  height: 20px;
}

.cal-month-label {
  font-size: 1.2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
  letter-spacing: -0.01em;
}

/* Loading overlay */
.cal-loading-overlay {
  position: absolute;
  top: 70px;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(4px);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  z-index: 10;
  color: #64748b;
  font-size: 0.88rem;
  font-weight: 500;
}

.spin-icon {
  width: 24px;
  height: 24px;
  animation: spin 1s linear infinite;
  color: var(--color-primary);
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

/* ====================================== */
/*  BẢNG LỊCH DẠNG TABLE                  */
/* ====================================== */
.cal-table-wrap {
  overflow-x: auto;
}

.cal-table {
  width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  min-width: 700px;
}

.cal-table thead th {
  padding: 12px 8px;
  font-size: 0.78rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  text-align: center;
  border-bottom: 2px solid #f1f5f9;
  background: #fafbff;
  position: sticky;
  top: 0;
  z-index: 2;
}

.th-sun {
  color: #ef4444 !important;
}

/* ====================================== */
/*  CÁC Ô NGÀY (CELLS)                    */
/* ====================================== */
.cal-cell {
  border: 1px solid #f1f5f9;
  vertical-align: top;
  padding: 6px 8px;
  min-height: 110px;
  height: 110px;
  transition: background 0.15s ease;
  position: relative;
}

.cal-cell:hover:not(.cell-other-month) {
  background: #fafbff;
}

.cell-other-month {
  background: #fafcfe;
}

.cell-other-month .cell-day-num {
  color: #cbd5e1;
}

.cell-today {
  background: var(--color-primary-light) !important;
}

.cell-sunday .cell-day-num {
  color: #ef4444;
}

.cell-day-num {
  font-size: 0.82rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 4px;
  line-height: 1;
}

.today-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: var(--color-primary);
  color: white !important;
  font-weight: 700;
  font-size: 0.8rem;
}

/* ====================================== */
/*  TRIP CHIPS (CHUYẾN XE TRÊN Ô LỊCH)    */
/* ====================================== */
.cell-trips {
  display: flex;
  flex-direction: column;
  gap: 3px;
  overflow: hidden;
}

.cell-trip-chip {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 3px 6px;
  border-radius: 4px;
  font-size: 0.68rem;
  line-height: 1.3;
  cursor: pointer;
  transition: all 0.2s ease;
  overflow: hidden;
  white-space: nowrap;
  border-left: 3px solid transparent;
}

.cell-trip-chip:hover {
  filter: brightness(0.92);
  transform: scale(1.02);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.chip-time {
  font-weight: 700;
  flex-shrink: 0;
}

.chip-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 500;
}

/* Trạng thái chip trên lịch */
.cell-trip-chip.status-ready {
  background: #eff6ff;
  color: #1d4ed8;
  border-left-color: var(--color-ready);
}

.cell-trip-chip.status-running {
  background: #fffbeb;
  color: #92400e;
  border-left-color: var(--color-running);
}

.cell-trip-chip.status-done {
  background: #ecfdf5;
  color: #065f46;
  border-left-color: var(--color-done);
}

.cell-trip-chip.status-cancel {
  background: #fef2f2;
  color: #991b1b;
  border-left-color: var(--color-cancel);
}

.cell-trip-chip.status-default {
  background: #f8fafc;
  color: #475569;
  border-left-color: #94a3b8;
}

/* ====================================== */
/*  MODAL - CHI TIẾT CHUYẾN XE             */
/* ====================================== */
.modal-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 20px;
  gap: 14px;
  color: #94a3b8;
}

.spin-icon-lg {
  width: 36px;
  height: 36px;
  animation: spin 1s linear infinite;
  color: var(--color-primary);
}

.detail-content {
  display: flex;
  flex-direction: column;
  gap: 22px;
}

.detail-status-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.detail-status-badge {
  display: inline-flex;
  align-items: center;
  padding: 5px 14px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.detail-status-badge.status-ready {
  background: #eff6ff;
  color: var(--color-ready);
  border: 1px solid #bfdbfe;
}

.detail-status-badge.status-running {
  background: #fffbeb;
  color: #b45309;
  border: 1px solid #fde68a;
}

.detail-status-badge.status-done {
  background: #ecfdf5;
  color: var(--color-done);
  border: 1px solid #a7f3d0;
}

.detail-status-badge.status-cancel {
  background: #fef2f2;
  color: var(--color-cancel);
  border: 1px solid #fecaca;
}

.detail-date {
  font-size: 0.82rem;
  color: #64748b;
  font-weight: 500;
}

/* Section */
.detail-section {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.detail-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.88rem;
  font-weight: 700;
  color: #334155;
  margin: 0;
}

.section-icon {
  width: 17px;
  height: 17px;
  color: var(--color-primary);
}

/* Route Card */
.detail-route-card {
  background: linear-gradient(135deg, #f8faff 0%, #f1f5f9 100%);
  border-radius: var(--radius-md);
  padding: 18px;
  border: 1px solid #e8ecf4;
}

.route-endpoint {
  display: flex;
  align-items: center;
  gap: 14px;
}

.endpoint-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  flex-shrink: 0;
  border: 3px solid white;
}

.start-dot {
  background: var(--color-done);
  box-shadow:
    0 0 0 2px var(--color-done),
    0 2px 6px rgba(16, 185, 129, 0.3);
}

.end-dot {
  background: var(--color-cancel);
  box-shadow:
    0 0 0 2px var(--color-cancel),
    0 2px 6px rgba(239, 68, 68, 0.3);
}

.endpoint-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.endpoint-label {
  font-size: 0.68rem;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  font-weight: 600;
}

.endpoint-name {
  font-size: 0.92rem;
  font-weight: 600;
  color: #1e293b;
}

.route-line-vertical {
  width: 2px;
  height: 22px;
  margin-left: 6px;
  background: repeating-linear-gradient(
    to bottom,
    #cbd5e1 0,
    #cbd5e1 4px,
    transparent 4px,
    transparent 8px
  );
}

/* Info Grid */
.detail-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.detail-info-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px;
  background: #f8fafc;
  border-radius: var(--radius-sm);
  border: 1px solid #f1f5f9;
  transition: all 0.2s ease;
}

.detail-info-item:hover {
  background: #f1f5f9;
  border-color: #e2e8f0;
}

.detail-info-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.icon-blue {
  color: var(--color-ready);
}
.icon-purple {
  color: #8b5cf6;
}
.icon-green {
  color: var(--color-done);
}
.icon-amber {
  color: var(--color-running);
}

.detail-info-label {
  display: block;
  font-size: 0.68rem;
  color: #94a3b8;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.detail-info-value {
  display: block;
  font-size: 0.92rem;
  font-weight: 700;
  color: #1e293b;
  margin-top: 2px;
}

/* ====================================== */
/*  TIMELINE TRẠM DỪNG                     */
/* ====================================== */
.stops-timeline {
  display: flex;
  flex-direction: column;
}

.timeline-item {
  display: flex;
  gap: 14px;
}

.timeline-left {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.timeline-marker {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.72rem;
  font-weight: 700;
  color: white;
  z-index: 1;
  flex-shrink: 0;
}

.marker-pickup {
  background: linear-gradient(135deg, #10b981, #059669);
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.marker-dropoff {
  background: linear-gradient(135deg, #f43f5e, #e11d48);
  box-shadow: 0 2px 8px rgba(244, 63, 94, 0.3);
}

.marker-num {
  line-height: 1;
}

.timeline-connector {
  width: 2px;
  flex: 1;
  min-height: 16px;
  background: repeating-linear-gradient(
    to bottom,
    #e2e8f0 0,
    #e2e8f0 4px,
    transparent 4px,
    transparent 8px
  );
}

.timeline-content {
  padding: 4px 0 18px;
  flex: 1;
  min-width: 0;
}

.timeline-name {
  display: block;
  font-size: 0.88rem;
  font-weight: 600;
  color: #1e293b;
  line-height: 1.3;
}

.timeline-meta {
  display: flex;
  flex-direction: column;
  gap: 3px;
  margin-top: 4px;
}

.timeline-type {
  display: inline-flex;
  font-size: 0.7rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 4px;
  width: fit-content;
}

.type-pickup {
  background: #ecfdf5;
  color: #059669;
}

.type-dropoff {
  background: #fef2f2;
  color: #dc2626;
}

.timeline-addr {
  font-size: 0.75rem;
  color: #94a3b8;
  line-height: 1.3;
}

/* Nút "Vào điều khiển" */
.detail-actions {
  padding-top: 10px;
  border-top: 1px solid #f1f5f9;
}

.btn-go-drive {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 13px;
  border-radius: var(--radius-md);
  /* Màu tường minh: nội dung modal Teleport ra body nên không kế thừa --color-primary từ .lich-trinh-wrapper */
  background: linear-gradient(135deg, #4f6ef7 0%, #3a4fcf 100%);
  color: #fff;
  font-weight: 700;
  font-size: 0.92rem;
  text-decoration: none;
  transition: all 0.25s ease;
  box-shadow: 0 4px 14px rgba(79, 110, 247, 0.35);
  border: none;
}

.btn-go-drive:hover {
  background: linear-gradient(135deg, #5b7af8 0%, #4359d4 100%);
  box-shadow: 0 6px 20px rgba(79, 110, 247, 0.5);
  transform: translateY(-2px);
  color: #fff;
}

.btn-drive-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  color: inherit;
  stroke: currentColor;
}

/* ====================================== */
/*  RESPONSIVE                             */
/* ====================================== */
@media (max-width: 768px) {
  .lich-trinh-wrapper {
    padding: 12px;
  }

  .lt-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .lt-header-actions {
    width: 100%;
    justify-content: space-between;
  }

  .header-legends {
    display: none;
  }

  .cal-cell {
    min-height: 80px;
    height: 80px;
    padding: 4px 3px;
  }

  .cell-day-num {
    font-size: 0.75rem;
  }

  .today-badge {
    width: 22px;
    height: 22px;
    font-size: 0.72rem;
  }

  .cell-trip-chip {
    font-size: 0.6rem;
    padding: 2px 3px;
    gap: 2px;
    border-left-width: 2px;
  }

  .chip-time {
    display: none;
  }

  .detail-info-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .cal-table {
    min-width: 340px;
  }

  .cal-cell {
    min-height: 64px;
    height: 64px;
    padding: 3px 2px;
  }

  .cell-trip-chip {
    font-size: 0.56rem;
    padding: 2px;
    border-left-width: 2px;
    border-radius: 3px;
  }
}

/* Desktop rộng */
@media (min-width: 1200px) {
  .cal-cell {
    min-height: 120px;
    height: 120px;
    padding: 8px 10px;
  }

  .cell-trip-chip {
    font-size: 0.72rem;
    padding: 4px 8px;
  }
}
</style>
