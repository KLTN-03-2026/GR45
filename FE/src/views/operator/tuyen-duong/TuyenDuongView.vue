<script setup>
import { ref, reactive, onMounted, computed } from "vue";
import operatorApi from "@/api/operatorApi";
import BaseTable from "@/components/common/BaseTable.vue";
import BaseButton from "@/components/common/BaseButton.vue";
import BaseInput from "@/components/common/BaseInput.vue";
import BaseModal from "@/components/common/BaseModal.vue";
import BaseToast from "@/components/common/BaseToast.vue";
import mapApi from "@/api/mapApi";
import { formatCurrency } from "@/utils/format";
import { getRouteStatus as getStatusLabel } from "@/utils/status";

// --- TOAST ---
const toast = reactive({ visible: false, message: "", type: "success" });
const showToast = (message, type = "success") => {
  toast.message = message;
  toast.type = type;
  toast.visible = true;
  setTimeout(() => {
    toast.visible = false;
  }, 3500);
};

// --- DỮ LIỆU DANH SÁCH ---
const routes = ref([]);
const loading = ref(false);
const pagination = reactive({
  currentPage: 1,
  perPage: 15,
  total: 0,
  lastPage: 1,
});
const searchQuery = ref("");
const filterStatus = ref("");

const routesTableRows = computed(() =>
  routes.value.map((r, i) => ({
    ...r,
    stt: (pagination.currentPage - 1) * pagination.perPage + i + 1,
    ten_nha_xe:
      r.nha_xe?.ten_nha_xe || r.nhaXe?.ten_nha_xe || r.ma_nha_xe || "—",
  })),
);

// Cấu hình cột bảng - Nhà xe không thấy cột Mã Nhà Xe (vì chỉ xem của mình)
const tableColumns = [
  { key: "stt", label: "STT" },
  { key: "ten_tuyen_duong", label: "Tên Tuyến" },
  { key: "ten_nha_xe", label: "Nhà xe" },
  { key: "lo_trinh", label: "Lộ Trình" },
  { key: "quang_duong", label: "Km" },
  { key: "gio_khoi_hanh", label: "Giờ chạy" },
  { key: "gia_ve_co_ban", label: "Giá Vé" },
  { key: "so_tram", label: "Trạm dừng" },
  { key: "tinh_trang", label: "Trạng Thái" },
  { key: "actions", label: "Hành Động" },
];

// --- FORM MODAL THÊM / SỬA ---
const isShowModal = ref(false);
const isEditMode = ref(false);
const modalLoading = ref(false);
const currentId = ref(null);
const geoLoadingStationIndex = ref(null);

const initialForm = () => ({
  ten_tuyen_duong: "",
  diem_bat_dau: "",
  diem_ket_thuc: "",
  quang_duong: 0,
  cac_ngay_trong_tuan: [0, 1, 2, 3, 4, 5, 6],
  gio_khoi_hanh: "06:00",
  gio_ket_thuc: "18:00",
  gio_du_kien: 0,
  so_ngay: 1,
  gia_ve_co_ban: 0,
  xe: null,
  mo_ta: "",
  tram_dungs: [],
});

const formData = reactive(initialForm());

const daysOfWeek = [
  { value: 0, label: "CN" },
  { value: 1, label: "T2" },
  { value: 2, label: "T3" },
  { value: 3, label: "T4" },
  { value: 4, label: "T5" },
  { value: 5, label: "T6" },
  { value: 6, label: "T7" },
];

// --- TRẠM DỪNG ---
const getEmptyStation = (loai = "don", thuTu = 1) => ({
  ten_tram: "",
  dia_chi: "",
  id_phuong_xa: 1,
  loai_tram: loai,
  thu_tu: thuTu,
  toa_do_x: null,
  toa_do_y: null,
});

const initDefaultStations = () => {
  if (formData.tram_dungs.length === 0) {
    formData.tram_dungs.push(getEmptyStation("don", 1));
    formData.tram_dungs.push(getEmptyStation("tra", 2));
  }
};

const addStation = () => {
  formData.tram_dungs.push(
    getEmptyStation("ca_hai", formData.tram_dungs.length + 1),
  );
};

const removeStation = (index) => {
  formData.tram_dungs.splice(index, 1);
  // Cập nhật lại thứ tự sau khi xóa
  formData.tram_dungs.forEach((s, i) => {
    s.thu_tu = i + 1;
  });
};

const toggleDay = (dayValue) => {
  const idx = formData.cac_ngay_trong_tuan.indexOf(dayValue);
  if (idx === -1) {
    formData.cac_ngay_trong_tuan.push(dayValue);
  } else {
    formData.cac_ngay_trong_tuan.splice(idx, 1);
  }
};

const getStationGeoQueries = (station) => {
  const queries = [
    station?.dia_chi,
    [station?.ten_tram, station?.dia_chi].filter(Boolean).join(", "),
    [station?.ten_tram, formData.diem_bat_dau].filter(Boolean).join(", "),
    [formData.diem_bat_dau, formData.diem_ket_thuc].filter(Boolean).join(" - "),
    station?.ten_tram,
  ]
    .map((item) => item?.trim())
    .filter(Boolean);

  return [...new Set(queries)];
};

const fetchStationCoordinates = async (station, index) => {
  const queries = getStationGeoQueries(station);

  if (!queries.length) {
    showToast(
      "Vui lòng nhập địa chỉ hoặc tên trạm trước khi lấy tọa độ.",
      "error",
    );
    return;
  }

  geoLoadingStationIndex.value = index;
  try {
    let result = null;

    // Thử từng query, dừng ngay khi có kết quả.
    // mapApi.searchCoordinatesByAddress đã tự rate-limit (≥1.2s/req) + retry 429.
    for (const query of queries) {
      const response = await mapApi.searchCoordinatesByAddress(query);
      if (response?.data?.length) {
        result = response.data[0];
        break; // Không gọi thêm – tiết kiệm quota Nominatim
      }
    }

    if (!result) {
      showToast(
        "Không tìm thấy tọa độ. Thử nhập địa chỉ đầy đủ hơn hoặc nhập thủ công.",
        "warning",
      );
      return;
    }

    station.toa_do_x = Number(result.lon);
    station.toa_do_y = Number(result.lat);
    showToast(
      `✓ Tọa độ: ${result.lat.toFixed(5)}, ${result.lon.toFixed(5)}`,
      "success",
    );
  } catch (error) {
    const status = error?.response?.status;
    if (status === 429) {
      showToast(
        "Đang bị giới hạn tốc độ (429). Vui lòng chờ vài giây rồi thử lại.",
        "error",
      );
    } else {
      console.error("Lỗi lấy tọa độ map:", error);
      showToast(
        "Không thể lấy tọa độ. Kiểm tra kết nối hoặc nhập thủ công.",
        "error",
      );
    }
  } finally {
    geoLoadingStationIndex.value = null;
  }
};

// --- GỌI API ---
const fetchRoutes = async (page = 1) => {
  try {
    loading.value = true;
    const res = await operatorApi.getRoutes({
      per_page: pagination.perPage,
      search: searchQuery.value || undefined,
      tinh_trang: filterStatus.value || undefined,
      page,
    });

    const d = res?.data;
    let listData = [];
    let pageInfo = {};
    if (d && Array.isArray(d.data)) {
      listData = d.data;
      pageInfo = d;
    } else if (Array.isArray(d)) {
      listData = d;
    }

    routes.value = Array.isArray(listData) ? listData : [];
    pagination.currentPage = pageInfo.current_page || 1;
    pagination.total = pageInfo.total || 0;
    pagination.lastPage = pageInfo.last_page || 1;
  } catch (error) {
    console.error("Lỗi khi tải danh sách tuyến đường:", error);
    showToast("Không thể tải danh sách tuyến đường!", "error");
  } finally {
    loading.value = false;
  }
};

const handleSearch = () => {
  fetchRoutes(1);
};

const handleResetFilter = () => {
  searchQuery.value = "";
  filterStatus.value = "";
  fetchRoutes(1);
};

// --- MỞ MODAL ---
const openCreateModal = () => {
  isEditMode.value = false;
  currentId.value = null;
  Object.assign(formData, initialForm());
  initDefaultStations();
  isShowModal.value = true;
};

const openEditModal = (route) => {
  isEditMode.value = true;
  currentId.value = route.id;
  Object.assign(formData, {
    ten_tuyen_duong: route.ten_tuyen_duong || "",
    diem_bat_dau: route.diem_bat_dau || "",
    diem_ket_thuc: route.diem_ket_thuc || "",
    quang_duong: Number(route.quang_duong) || 0,
    cac_ngay_trong_tuan: route.cac_ngay_trong_tuan || [],
    gio_khoi_hanh: route.gio_khoi_hanh || "06:00",
    gio_ket_thuc: route.gio_ket_thuc || "18:00",
    gio_du_kien: Number(route.gio_du_kien) || 0,
    so_ngay: Number(route.so_ngay) || 1,
    gia_ve_co_ban: Number(route.gia_ve_co_ban) || 0,
    xe: route.id_xe || null,
    mo_ta: route.ghi_chu || "",
    tram_dungs: (route.tram_dungs || []).map((t) => ({
      ten_tram: t.ten_tram || "",
      dia_chi: t.dia_chi || "",
      id_phuong_xa: t.id_phuong_xa || 1,
      loai_tram: t.loai_tram || "don",
      thu_tu: t.thu_tu || 1,
      toa_do_x: t.toa_do_x || null,
      toa_do_y: t.toa_do_y || null,
    })),
  });
  isShowModal.value = true;
};

// --- GỬI FORM ---
const submitForm = async () => {
  if (formData.tram_dungs.length < 2) {
    showToast("Phải có ít nhất 2 trạm dừng!", "error");
    return;
  }
  try {
    modalLoading.value = true;
    if (isEditMode.value) {
      await operatorApi.updateRoute(currentId.value, formData);
      showToast(
        "Cập nhật tuyến đường thành công! Đang chờ Admin duyệt lại.",
        "success",
      );
    } else {
      await operatorApi.createRoute(formData);
      showToast(
        "Thêm tuyến đường thành công! Đang chờ Admin duyệt.",
        "success",
      );
    }
    isShowModal.value = false;
    fetchRoutes(pagination.currentPage);
  } catch (error) {
    console.error("Lỗi lưu tuyến đường:", error);
    const errMsg = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat()[0]
      : error.response?.data?.message || "Có lỗi xảy ra!";
    showToast(errMsg, "error");
  } finally {
    modalLoading.value = false;
  }
};

// --- MODAL XEM CHI TIẾT ---
const isDetailModal = ref(false);
const detailLoading = ref(false);
const detailData = ref(null);

const openDetailModal = async (route) => {
  isDetailModal.value = true;
  detailLoading.value = true;
  detailData.value = null;
  try {
    const res = await operatorApi.getRouteDetails(route.id);
    const d = res.data?.data || res.data;
    detailData.value = d;
  } catch (err) {
    console.error("Lỗi xem chi tiết:", err);
    showToast("Không thể tải chi tiết tuyến đường!", "error");
    isDetailModal.value = false;
  } finally {
    detailLoading.value = false;
  }
};

// Utility hiển thị các ngày trong tuần
const formatDays = (days) => {
  if (!days || !days.length) return "—";
  const labels = ["CN", "T2", "T3", "T4", "T5", "T6", "T7"];
  return [...days]
    .sort((a, b) => a - b)
    .map((d) => labels[d])
    .join(", ");
};

const loaiTramLabel = (loai) => {
  if (loai === "don") return "Đón";
  if (loai === "tra") return "Trả";
  if (loai === "ca_hai") return "Đón & Trả";
  return loai;
};

onMounted(() => {
  fetchRoutes();
});
</script>

<template>
  <div class="operator-page">
    <BaseToast
      :visible="toast.visible"
      :message="toast.message"
      :type="toast.type"
    />

    <!-- Tiêu đề trang -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Tuyến Đường</h1>
        <p class="page-sub">
          Tuyến đường mới cần Admin duyệt trước khi hoạt động
        </p>
      </div>
      <BaseButton @click="openCreateModal" variant="primary">
        + Thêm Tuyến Đường
      </BaseButton>
    </div>

    <!-- Thanh lọc & tìm kiếm -->
    <div class="filter-card">
      <div class="filter-row">
        <div class="search-box">
          <BaseInput
            v-model="searchQuery"
            placeholder="Tìm theo tên tuyến, điểm đầu, điểm cuối..."
            @keyup.enter="handleSearch"
          />
          <BaseButton @click="handleSearch" variant="secondary">Tìm</BaseButton>
        </div>

        <div class="filter-status">
          <label class="filter-label">Trạng thái</label>
          <select
            v-model="filterStatus"
            @change="handleSearch"
            class="custom-select"
          >
            <option value="">Tất cả</option>
            <option value="hoat_dong">Đang hoạt động</option>
            <option value="cho_duyet">Chờ duyệt</option>
            <option value="khong_hoat_dong">Không hoạt động</option>
          </select>
        </div>

        <BaseButton @click="handleResetFilter" variant="outline"
          >Đặt lại</BaseButton
        >
      </div>
    </div>

    <!-- Bảng dữ liệu -->
    <div class="table-card">
      <BaseTable
        :columns="tableColumns"
        :data="routesTableRows"
        :loading="loading"
      >
        <!-- Lộ trình -->
        <template #cell(lo_trinh)="{ item }">
          <div class="route-path">
            <span class="point-start">{{ item.diem_bat_dau }}</span>
            <span class="route-arrow">→</span>
            <span class="point-end">{{ item.diem_ket_thuc }}</span>
          </div>
        </template>

        <!-- Quãng đường -->
        <template #cell(quang_duong)="{ value }">
          <span class="km-value">{{ Number(value) }} km</span>
        </template>

        <!-- Giờ chạy -->
        <template #cell(gio_khoi_hanh)="{ item }">
          <span class="time-range"
            >{{ item.gio_khoi_hanh }} – {{ item.gio_ket_thuc }}</span
          >
        </template>

        <!-- Giá vé -->
        <template #cell(gia_ve_co_ban)="{ value }">
          <span class="price-value">{{ formatCurrency(value) }}</span>
        </template>

        <!-- Số trạm dừng -->
        <template #cell(so_tram)="{ item }">
          <span class="station-count"
            >{{ (item.tram_dungs || []).length }} trạm</span
          >
        </template>

        <!-- Trạng thái -->
        <template #cell(tinh_trang)="{ value }">
          <span :class="['status-badge', getStatusLabel(value).class]">
            {{ getStatusLabel(value).text }}
          </span>
        </template>

        <!-- Hành động — Nhà xe KHÔNG có xóa, duyệt, từ chối -->
        <template #cell(actions)="{ item }">
          <div class="action-buttons">
            <BaseButton
              size="sm"
              variant="outline"
              @click="openDetailModal(item)"
            >
              Chi tiết
            </BaseButton>
            <BaseButton
              size="sm"
              variant="primary"
              @click="openEditModal(item)"
            >
              Sửa
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
            @change="fetchRoutes(1)"
            class="custom-select per-page-select"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
          </select>
          <span>dòng / trang</span>
          <span v-if="pagination.total > 0" class="total-label"
            >(Tổng: {{ pagination.total }} tuyến)</span
          >
        </div>

        <div class="pagination-controls">
          <BaseButton
            size="sm"
            variant="outline"
            :disabled="pagination.currentPage <= 1"
            @click="fetchRoutes(pagination.currentPage - 1)"
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
            @click="fetchRoutes(pagination.currentPage + 1)"
            >Sau →</BaseButton
          >
        </div>
      </div>
    </div>

    <!-- ===== MODAL THÊM / SỬA ===== -->
    <BaseModal
      v-model="isShowModal"
      :title="isEditMode ? 'Cập Nhật Tuyến Đường' : 'Thêm Tuyến Đường Mới'"
      maxWidth="820px"
    >
      <!-- Lưu ý chờ duyệt -->
      <div class="info-banner">
        <span class="info-icon">ℹ️</span>
        <span v-if="isEditMode">
          Sau khi cập nhật, tuyến đường sẽ chuyển sang
          <strong>Chờ Admin duyệt lại</strong>.
        </span>
        <span v-else>
          Tuyến đường mới sẽ có trạng thái
          <strong>Chờ Admin duyệt</strong> trước khi hoạt động.
        </span>
      </div>

      <form @submit.prevent="submitForm" class="route-form">
        <!-- == Thông tin cơ bản == -->
        <h4 class="form-section-title">Thông Tin Cơ Bản</h4>
        <div class="form-grid">
          <div class="form-group full-width">
            <BaseInput
              v-model="formData.ten_tuyen_duong"
              label="Tên Tuyến Đường *"
              placeholder="VD: TP.HCM - Đà Lạt"
              required
            />
          </div>

          <BaseInput
            v-model="formData.diem_bat_dau"
            label="Điểm Bắt Đầu *"
            placeholder="Thành phố / Bến xe xuất phát"
            required
          />
          <BaseInput
            v-model="formData.diem_ket_thuc"
            label="Điểm Kết Thúc *"
            placeholder="Thành phố / Bến xe đến"
            required
          />

          <div class="form-group">
            <label class="base-input-label">Quãng Đường (km) *</label>
            <input
              type="number"
              v-model="formData.quang_duong"
              class="custom-input"
              min="1"
              required
            />
          </div>

          <div class="form-group">
            <label class="base-input-label">Giá Vé Cơ Bản (VNĐ) *</label>
            <input
              type="number"
              v-model="formData.gia_ve_co_ban"
              class="custom-input"
              min="0"
              required
            />
          </div>

          <div class="form-group">
            <label class="base-input-label">Giờ Khởi Hành *</label>
            <input
              type="time"
              v-model="formData.gio_khoi_hanh"
              class="custom-input"
              required
            />
          </div>

          <div class="form-group">
            <label class="base-input-label">Giờ Kết Thúc *</label>
            <input
              type="time"
              v-model="formData.gio_ket_thuc"
              class="custom-input"
              required
            />
          </div>

          <div class="form-group">
            <label class="base-input-label">Số ngày đi *</label>
            <input
              type="number"
              v-model="formData.so_ngay"
              class="custom-input"
              min="1"
              max="2"
              required
            />
          </div>

          <div class="form-group">
            <label class="base-input-label">Thời Gian Dự Kiến (giờ)</label>
            <input
              type="number"
              v-model="formData.gio_du_kien"
              class="custom-input"
              min="0"
              step="0.5"
            />
          </div>

          <div class="form-group">
            <label class="base-input-label">ID Xe (tùy chọn)</label>
            <input
              type="number"
              v-model="formData.xe"
              class="custom-input"
              min="1"
              placeholder="Nhập ID xe..."
            />
          </div>

          <div class="form-group full-width">
            <label class="base-input-label">Ghi Chú / Mô Tả</label>
            <textarea
              v-model="formData.mo_ta"
              class="custom-input custom-textarea"
              placeholder="Mô tả thêm về tuyến đường..."
            ></textarea>
          </div>
        </div>

        <!-- == Lịch chạy == -->
        <h4 class="form-section-title mt-4">Lịch Chạy Trong Tuần *</h4>
        <div class="days-grid">
          <label
            v-for="day in daysOfWeek"
            :key="day.value"
            class="day-checkbox"
            :class="{
              'day-active': formData.cac_ngay_trong_tuan.includes(day.value),
            }"
          >
            <input
              type="checkbox"
              :checked="formData.cac_ngay_trong_tuan.includes(day.value)"
              @change="toggleDay(day.value)"
              style="display: none"
            />
            <span class="day-label">{{ day.label }}</span>
          </label>
        </div>

        <!-- == Trạm dừng == -->
        <div class="stations-header mt-4">
          <h4 class="form-section-title m-0">
            Danh Sách Trạm Dừng *
            <span class="station-hint">(tối thiểu 2 trạm)</span>
          </h4>
          <BaseButton
            size="sm"
            variant="outline"
            type="button"
            @click="addStation"
          >
            + Thêm Trạm
          </BaseButton>
        </div>

        <div class="stations-list">
          <div
            v-for="(station, index) in formData.tram_dungs"
            :key="index"
            class="station-item"
          >
            <div class="station-header">
              <div class="station-order">
                <span class="station-num">{{ index + 1 }}</span>
                <strong>Trạm {{ index + 1 }}</strong>
              </div>
              <BaseButton
                v-if="formData.tram_dungs.length > 2"
                size="sm"
                variant="danger"
                type="button"
                @click="removeStation(index)"
              >
                Xóa
              </BaseButton>
            </div>

            <div class="form-grid">
              <BaseInput
                v-model="station.ten_tram"
                label="Tên Trạm *"
                placeholder="VD: Bến xe Miền Đông"
                required
              />

              <div class="form-group">
                <label class="base-input-label">Loại Trạm *</label>
                <select v-model="station.loai_tram" class="custom-select">
                  <option value="don">🟢 Trạm Đón</option>
                  <option value="tra">🔴 Trạm Trả</option>
                  <option value="ca_hai">🔵 Đón & Trả</option>
                </select>
              </div>

              <div class="form-group full-width">
                <BaseInput
                  v-model="station.dia_chi"
                  label="Địa Chỉ *"
                  placeholder="Địa chỉ chi tiết trạm dừng..."
                  required
                />
              </div>

              <div class="form-group">
                <label class="base-input-label">Kinh Độ (Longitude)</label>
                <input
                  type="number"
                  step="0.000001"
                  v-model="station.toa_do_x"
                  class="custom-input"
                  placeholder="VD: 106.6297"
                />
              </div>

              <div class="form-group">
                <label class="base-input-label">Vĩ Độ (Latitude)</label>
                <input
                  type="number"
                  step="0.000001"
                  v-model="station.toa_do_y"
                  class="custom-input"
                  placeholder="VD: 10.8231"
                />
              </div>

              <div class="form-group full-width station-coordinate-action">
                <BaseButton
                  size="sm"
                  variant="outline"
                  type="button"
                  :loading="geoLoadingStationIndex === index"
                  @click="fetchStationCoordinates(station, index)"
                >
                  Lấy tọa độ từ map Leaflet
                </BaseButton>
              </div>
            </div>
          </div>

          <div v-if="formData.tram_dungs.length === 0" class="empty-stations">
            Chưa có trạm dừng nào. Nhấn "+ Thêm Trạm" để bắt đầu.
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
          {{ isEditMode ? "Lưu Thay Đổi" : "Gửi Duyệt" }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- ===== MODAL CHI TIẾT ===== -->
    <BaseModal
      v-model="isDetailModal"
      title="Chi Tiết Tuyến Đường"
      maxWidth="700px"
    >
      <div v-if="detailLoading" class="detail-loading">Đang tải...</div>

      <div v-else-if="detailData" class="detail-body">
        <!-- Trạng thái nổi bật -->
        <div
          class="detail-status-banner"
          :class="getStatusLabel(detailData.tinh_trang).class"
        >
          <span>{{ getStatusLabel(detailData.tinh_trang).text }}</span>
          <span v-if="detailData.tinh_trang === 'cho_duyet'" class="status-note"
            >— Đang chờ Admin duyệt</span
          >
          <span
            v-if="detailData.tinh_trang === 'khong_hoat_dong'"
            class="status-note"
            >— Đã bị từ chối hoặc chưa hoạt động</span
          >
        </div>

        <!-- Thông tin cơ bản -->
        <div class="detail-grid">
          <div class="detail-item full-width">
            <span class="detail-label">Tên tuyến</span>
            <span class="detail-value route-name">{{
              detailData.ten_tuyen_duong
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Điểm bắt đầu</span>
            <span class="detail-value">{{ detailData.diem_bat_dau }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Điểm kết thúc</span>
            <span class="detail-value">{{ detailData.diem_ket_thuc }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Quãng đường</span>
            <span class="detail-value">{{ detailData.quang_duong }} km</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Giá vé cơ bản</span>
            <span class="detail-value price-value">{{
              formatCurrency(detailData.gia_ve_co_ban)
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Giờ khởi hành</span>
            <span class="detail-value">{{ detailData.gio_khoi_hanh }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Giờ kết thúc</span>
            <span class="detail-value">{{ detailData.gio_ket_thuc }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Số ngày đi</span>
            <span class="detail-value">{{ detailData.so_ngay || 1 }} ngày</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Thời gian dự kiến</span>
            <span class="detail-value"
              >{{ detailData.gio_du_kien || "—" }} giờ</span
            >
          </div>
          <div class="detail-item full-width">
            <span class="detail-label">Lịch chạy</span>
            <span class="detail-value">{{
              formatDays(detailData.cac_ngay_trong_tuan)
            }}</span>
          </div>
          <div class="detail-item full-width" v-if="detailData.ghi_chu">
            <span class="detail-label">Ghi chú</span>
            <span class="detail-value">{{ detailData.ghi_chu }}</span>
          </div>
        </div>

        <!-- Danh sách trạm dừng -->
        <h4 class="detail-section-title">
          Trạm Dừng
          <span class="station-count-badge"
            >{{ (detailData.tram_dungs || []).length }} trạm</span
          >
        </h4>

        <div class="station-timeline">
          <div
            v-for="(tram, idx) in detailData.tram_dungs || []"
            :key="tram.id || idx"
            class="timeline-item"
            :class="`loai-${tram.loai_tram}`"
          >
            <div class="timeline-dot"></div>
            <div class="timeline-content">
              <div class="timeline-header">
                <strong>{{ tram.ten_tram }}</strong>
                <span class="loai-tram-badge">{{
                  loaiTramLabel(tram.loai_tram)
                }}</span>
              </div>
              <p class="timeline-addr">📍 {{ tram.dia_chi }}</p>
              <p v-if="tram.toa_do_x && tram.toa_do_y" class="timeline-coords">
                🗺 {{ tram.toa_do_y }}, {{ tram.toa_do_x }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <BaseButton variant="secondary" @click="isDetailModal = false"
          >Đóng</BaseButton
        >
        <BaseButton
          variant="primary"
          @click="
            openEditModal(detailData);
            isDetailModal = false;
          "
        >
          Chỉnh Sửa
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.operator-page {
  padding: 0;
  font-family: "Inter", system-ui, sans-serif;
}

/* === Tiêu đề trang === */
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

/* === Filter Card === */
.filter-card {
  background: rgba(255, 255, 255, 0.8);
  backdrop-filter: blur(10px);
  border: 1px solid #dcfce7;
  padding: 16px 20px;
  border-radius: 14px;
  margin-bottom: 20px;
  box-shadow: 0 4px 12px rgba(0, 80, 40, 0.04);
}
.filter-row {
  display: flex;
  gap: 16px;
  align-items: flex-end;
  flex-wrap: wrap;
}
.search-box {
  display: flex;
  gap: 10px;
  align-items: flex-end;
  flex: 1;
  min-width: 280px;
}
.search-box > :first-child {
  flex: 1;
  margin-bottom: 0;
}
.filter-status {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.filter-label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

/* === Table Card === */
.table-card {
  background: white;
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 4px 20px rgba(0, 80, 40, 0.05);
  border: 1px solid #dcfce7;
}

.route-path {
  display: flex;
  align-items: center;
  gap: 6px;
}
.point-start {
  font-weight: 600;
  color: #16a34a;
}
.point-end {
  font-weight: 600;
  color: #0d4f35;
}
.route-arrow {
  color: #94a3b8;
  font-weight: bold;
  font-size: 16px;
}
.km-value {
  font-weight: 600;
  color: #475569;
}
.time-range {
  font-size: 13px;
  color: #475569;
  font-weight: 500;
}
.price-value {
  font-weight: 700;
  color: #16a34a;
}
.station-count {
  font-size: 13px;
  color: #64748b;
}

/* Status Badges */
.status-badge {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  display: inline-block;
  white-space: nowrap;
}
.status-pending {
  background: #fef9c3;
  color: #ca8a04;
}
.status-approved {
  background: #dcfce7;
  color: #16a34a;
}
.status-rejected {
  background: #fee2e2;
  color: #dc2626;
}

/* Actions */
.action-buttons {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

/* Pagination */
.pagination-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 16px;
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
  display: inline-block;
}
.total-label {
  color: #94a3b8;
}
.pagination-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}
.page-number {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  padding: 0 8px;
}

/* === Modal Form === */
.info-banner {
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 13px;
  color: #92400e;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.info-icon {
  font-size: 16px;
}

.route-form {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.form-section-title {
  font-size: 15px;
  font-weight: 700;
  color: #0d4f35;
  border-bottom: 2px solid #dcfce7;
  padding-bottom: 8px;
  margin-bottom: 16px;
}
.mt-4 {
  margin-top: 20px;
}
.m-0 {
  margin: 0;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.full-width {
  grid-column: 1 / -1;
}
.base-input-label {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
}

.custom-input,
.custom-select {
  width: 100%;
  padding: 10px 12px;
  font-size: 14px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #ffffff;
  color: #1f2937;
  transition: all 0.2s;
  box-sizing: border-box;
}
.custom-input:focus,
.custom-select:focus {
  outline: none;
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
}
.custom-textarea {
  resize: vertical;
  min-height: 80px;
}

/* Ngày trong tuần */
.days-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 4px;
}
.day-checkbox {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 12px;
  border: 2px solid #e2e8f0;
  background: #f8fafc;
  cursor: pointer;
  transition: all 0.2s;
  user-select: none;
}
.day-checkbox:hover {
  border-color: #16a34a;
  background: #f0fdf4;
}
.day-checkbox.day-active {
  border-color: #16a34a;
  background: #16a34a;
}
.day-checkbox.day-active .day-label {
  color: white;
  font-weight: 700;
}
.day-label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

/* Trạm dừng */
.stations-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.station-hint {
  font-size: 12px;
  font-weight: 400;
  color: #94a3b8;
  margin-left: 6px;
}
.stations-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 420px;
  overflow-y: auto;
  padding-right: 4px;
}
.station-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px;
  transition: border-color 0.2s;
}
.station-item:hover {
  border-color: #bbf7d0;
}
.station-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
  padding-bottom: 10px;
  border-bottom: 1px dashed #cbd5e1;
}
.station-coordinate-action {
  display: flex;
  justify-content: flex-end;
}
.station-order {
  display: flex;
  align-items: center;
  gap: 10px;
}
.station-num {
  width: 28px;
  height: 28px;
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: white;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
}
.empty-stations {
  text-align: center;
  color: #94a3b8;
  font-size: 14px;
  padding: 24px;
  border: 2px dashed #e2e8f0;
  border-radius: 10px;
}

/* === Modal Chi Tiết === */
.detail-loading {
  text-align: center;
  padding: 40px;
  color: #64748b;
  font-size: 14px;
}
.detail-status-banner {
  padding: 12px 16px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 14px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.detail-status-banner.status-approved {
  background: #dcfce7;
  color: #16a34a;
}
.detail-status-banner.status-pending {
  background: #fef9c3;
  color: #ca8a04;
}
.detail-status-banner.status-rejected {
  background: #fee2e2;
  color: #dc2626;
}
.status-note {
  font-weight: 400;
  font-size: 13px;
  opacity: 0.8;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  margin-bottom: 24px;
}
.detail-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.full-width {
  grid-column: 1 / -1;
}
.detail-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.detail-value {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}
.route-name {
  font-size: 16px;
  font-weight: 800;
  color: #0d4f35;
}

.detail-section-title {
  font-size: 15px;
  font-weight: 700;
  color: #0d4f35;
  margin: 0 0 16px 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.station-count-badge {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #16a34a;
  font-size: 12px;
  font-weight: 600;
  padding: 2px 10px;
  border-radius: 10px;
}

/* Timeline trạm dừng */
.station-timeline {
  display: flex;
  flex-direction: column;
  position: relative;
  padding-left: 24px;
}
.station-timeline::before {
  content: "";
  position: absolute;
  left: 9px;
  top: 14px;
  bottom: 14px;
  width: 2px;
  background: linear-gradient(180deg, #22c55e, #e2e8f0);
}
.timeline-item {
  position: relative;
  padding: 12px 14px;
  margin-bottom: 8px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #f1f5f9;
}
.timeline-dot {
  position: absolute;
  left: -24px;
  top: 16px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid white;
  box-shadow: 0 0 0 1px #e2e8f0;
}
.loai-don .timeline-dot {
  background: #22c55e;
}
.loai-tra .timeline-dot {
  background: #ef4444;
}
.loai-ca_hai .timeline-dot {
  background: #3b82f6;
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}
.loai-tram-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}
.loai-don .loai-tram-badge {
  background: #dcfce7;
  color: #16a34a;
}
.loai-tra .loai-tram-badge {
  background: #fee2e2;
  color: #dc2626;
}
.loai-ca_hai .loai-tram-badge {
  background: #eff6ff;
  color: #2563eb;
}

.timeline-addr {
  font-size: 12px;
  color: #64748b;
  margin: 2px 0;
}
.timeline-coords {
  font-size: 11px;
  color: #94a3b8;
  margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  .detail-grid {
    grid-template-columns: 1fr;
  }
  .filter-row {
    flex-direction: column;
    align-items: stretch;
  }
  .search-box {
    flex-direction: column;
  }
  .pagination-container {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
