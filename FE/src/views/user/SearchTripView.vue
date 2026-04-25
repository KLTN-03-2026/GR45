<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import clientApi from "@/api/clientApi";
import { useClientStore } from "@/stores/clientStore.js";
import CustomDatePicker from "@/components/common/CustomDatePicker.vue";

const route = useRoute();
const router = useRouter();
const clientStore = useClientStore();

const isLoading = ref(true);
const dsChuyenXe = ref([]);

// Danh sách tỉnh thành từ API
const provinces = ref([]);

// Form tìm kiếm: lưu ID tỉnh thành để hiển thị dropdown, lưu tên để gọi API
const searchForm = ref({
  tinh_thanh_di_id: "",
  tinh_thanh_den_id: "",
  ngay_di: route.query.ngay_di || new Date().toISOString().split("T")[0],
});

// Lấy tham số tìm kiếm từ URL query (dùng tên tỉnh thành)
const searchParams = ref({
  diem_di: route.query.diem_di || "",
  diem_den: route.query.diem_den || "",
  ngay_di: route.query.ngay_di || "",
});

// State cho custom dropdown
const isOpenFrom = ref(false);
const isOpenTo = ref(false);
const fromSelectRef = ref(null);
const toSelectRef = ref(null);

// Loại bỏ prefix "Thành phố" / "Tỉnh" để khớp LIKE search BE
const cleanProvinceName = (name) => {
  if (!name) return "";
  return name.replace(/^(Thành phố |Tỉnh )/i, "").trim();
};

// Lấy tên tỉnh thành từ ID
const getProvinceName = (id) => {
  const p = provinces.value.find((x) => x.id === id);
  return p ? p.ten_tinh_thanh : "";
};

// Lấy danh sách tỉnh thành từ API
const fetchProvinces = async () => {
  try {
    const res = await clientApi.getProvinces();
    if (res && res.data) {
      provinces.value = Array.isArray(res.data)
        ? res.data
        : res.data.data || [];
      // Nếu URL có sẵn diem_di / diem_den, map ngược lại ID để hiển thị dropdown
      if (searchParams.value.diem_di) {
        const found = provinces.value.find(
          (p) =>
            cleanProvinceName(p.ten_tinh_thanh) === searchParams.value.diem_di
        );
        if (found) searchForm.value.tinh_thanh_di_id = found.id;
      }
      if (searchParams.value.diem_den) {
        const found = provinces.value.find(
          (p) =>
            cleanProvinceName(p.ten_tinh_thanh) === searchParams.value.diem_den
        );
        if (found) searchForm.value.tinh_thanh_den_id = found.id;
      }
    }
  } catch (e) {
    console.error("Lỗi khi lấy danh sách tỉnh thành:", e);
  }
};

// Đảo chiều điểm đi - điểm đến
const handleSwap = () => {
  const temp = searchForm.value.tinh_thanh_di_id;
  searchForm.value.tinh_thanh_di_id = searchForm.value.tinh_thanh_den_id;
  searchForm.value.tinh_thanh_den_id = temp;
};

// Submit tìm kiếm mới — cập nhật URL query params
const submitNewSearch = () => {
  const diemDi = cleanProvinceName(
    getProvinceName(searchForm.value.tinh_thanh_di_id)
  );
  const diemDen = cleanProvinceName(
    getProvinceName(searchForm.value.tinh_thanh_den_id)
  );
  if (!diemDi || !diemDen || !searchForm.value.ngay_di) return;
  router.push({
    path: "/search",
    query: {
      diem_di: diemDi,
      diem_den: diemDen,
      ngay_di: searchForm.value.ngay_di,
    },
  });
};

// Đóng dropdown khi click ngoài
const handleOutsideClick = (e) => {
  if (fromSelectRef.value && !fromSelectRef.value.contains(e.target))
    isOpenFrom.value = false;
  if (toSelectRef.value && !toSelectRef.value.contains(e.target))
    isOpenTo.value = false;
};

// Bộ lọc nâng cao
const filters = ref({
  gia_ve_tu: "",
  gia_ve_den: "",
  gio_khoi_hanh_tu: "",
  gio_khoi_hanh_den: "",
});

// Sắp xếp
const sortBy = ref("gio_som");

// Bộ lọc giờ khởi hành nhanh
const predefinedTimeFilters = [
  {
    label: "Sáng sớm (00:00 - 06:00)",
    value: "dawn",
    tu: "00:00",
    den: "06:00",
    icon: "dark_mode",
  },
  {
    label: "Buổi sáng (06:00 - 12:00)",
    value: "morning",
    tu: "06:00",
    den: "12:00",
    icon: "light_mode",
  },
  {
    label: "Buổi chiều (12:00 - 18:00)",
    value: "afternoon",
    tu: "12:00",
    den: "18:00",
    icon: "wb_sunny",
  },
  {
    label: "Buổi tối (18:00 - 24:00)",
    value: "evening",
    tu: "18:00",
    den: "23:59",
    icon: "nightlight",
  },
];
const selectedPredefinedTime = ref(null);

// Mobile filter toggle
const isFilterOpen = ref(false);

// Áp dụng lọc giờ khởi hành nhanh
const applyPredefinedTime = (timeFilter) => {
  if (selectedPredefinedTime.value === timeFilter.value) {
    // Bỏ chọn nếu đã chọn rồi
    selectedPredefinedTime.value = null;
    filters.value.gio_khoi_hanh_tu = "";
    filters.value.gio_khoi_hanh_den = "";
  } else {
    selectedPredefinedTime.value = timeFilter.value;
    filters.value.gio_khoi_hanh_tu = timeFilter.tu;
    filters.value.gio_khoi_hanh_den = timeFilter.den;
  }
  performSearch();
};

// Gọi API tìm kiếm chuyến xe
const performSearch = async () => {
  isLoading.value = true;
  try {
    const params = {
      diem_di: searchParams.value.diem_di,
      diem_den: searchParams.value.diem_den,
      ngay_khoi_hanh: searchParams.value.ngay_di,
    };

    if (filters.value.gia_ve_tu) params.gia_ve_tu = filters.value.gia_ve_tu;
    if (filters.value.gia_ve_den) params.gia_ve_den = filters.value.gia_ve_den;
    if (filters.value.gio_khoi_hanh_tu)
      params.gio_khoi_hanh_tu = filters.value.gio_khoi_hanh_tu;
    if (filters.value.gio_khoi_hanh_den)
      params.gio_khoi_hanh_den = filters.value.gio_khoi_hanh_den;

    const res = await clientApi.searchTrips(params);
    if (res && res.data) {
      dsChuyenXe.value = Array.isArray(res.data)
        ? res.data
        : res.data.data || [];
    } else {
      dsChuyenXe.value = [];
    }
  } catch (error) {
    console.error("Lỗi khi tìm kiếm chuyến xe:", error);
    dsChuyenXe.value = [];
  } finally {
    isLoading.value = false;
  }
};

// Lấy giá vé từ chuyến xe
const getPrice = (chuyen) => parseFloat(chuyen.tuyen_duong?.gia_ve_co_ban || 0);

// Tính giờ đến dự kiến
const calcArrivalTime = (gioKhoiHanh, gioDuKien) => {
  if (!gioKhoiHanh || !gioDuKien) return "--:--";
  const parts = gioKhoiHanh.split(":");
  const h = parseInt(parts[0]) + parseInt(gioDuKien);
  const m = parts[1] || "00";
  return `${String(h % 24).padStart(2, "0")}:${m}`;
};

// Danh sách chuyến xe đã sắp xếp
const sortedTrips = computed(() => {
  const list = [...dsChuyenXe.value];
  switch (sortBy.value) {
    case "gio_som":
      return list.sort((a, b) =>
        (a.gio_khoi_hanh || "").localeCompare(b.gio_khoi_hanh || "")
      );
    case "gio_muon":
      return list.sort((a, b) =>
        (b.gio_khoi_hanh || "").localeCompare(a.gio_khoi_hanh || "")
      );
    case "gia_tang":
      return list.sort((a, b) => getPrice(a) - getPrice(b));
    case "gia_giam":
      return list.sort((a, b) => getPrice(b) - getPrice(a));
    default:
      return list;
  }
});

const applyFilters = () => {
  performSearch();
};

const resetFilters = () => {
  filters.value = {
    gia_ve_tu: "",
    gia_ve_den: "",
    gio_khoi_hanh_tu: "",
    gio_khoi_hanh_den: "",
  };
  selectedPredefinedTime.value = null;
  performSearch();
};

// ── Modal chi tiết chuyến xe ────────────────────────────
const selectedTrip = ref(null);
const showModal = ref(false);
const detailRatings = ref([]);
const ratingSummary = ref({ total_ratings: 0, avg_diem_so: null });
const ratingLoading = ref(false);
const showRatingDetailModal = ref(false);
const selectedRating = ref(null);

const fetchTripRatings = async (tripId) => {
  if (!tripId) {
    detailRatings.value = [];
    ratingSummary.value = { total_ratings: 0, avg_diem_so: null };
    return;
  }
  try {
    ratingLoading.value = true;
    const body = await clientApi.getTripRatings(tripId, { per_page: 50 });
    const paginator = body?.data;
    const list = paginator?.data ?? (Array.isArray(paginator) ? paginator : []);
    detailRatings.value = Array.isArray(list) ? list : [];
    const s = body?.summary;
    const totalFromSummary = Number(s?.total_ratings);
    const totalFromPage = Number(paginator?.total);
    ratingSummary.value = {
      total_ratings:
        Number.isFinite(totalFromSummary) && totalFromSummary >= 0
          ? totalFromSummary
          : Number.isFinite(totalFromPage) && totalFromPage >= 0
            ? totalFromPage
            : detailRatings.value.length,
      avg_diem_so:
        s?.avg_diem_so != null && s?.avg_diem_so !== ""
          ? Number(s.avg_diem_so)
          : null,
    };
  } catch (error) {
    detailRatings.value = [];
    ratingSummary.value = { total_ratings: 0, avg_diem_so: null };
  } finally {
    ratingLoading.value = false;
  }
};

const openTripDetail = (chuyen) => {
  selectedTrip.value = chuyen;
  detailRatings.value = [];
  ratingSummary.value = { total_ratings: 0, avg_diem_so: null };
  showModal.value = true;
  fetchTripRatings(chuyen?.id);
};

const closeModal = () => {
  showModal.value = false;
  selectedTrip.value = null;
  detailRatings.value = [];
  ratingSummary.value = { total_ratings: 0, avg_diem_so: null };
  ratingLoading.value = false;
  showRatingDetailModal.value = false;
  selectedRating.value = null;
};

const openRatingDetail = (rating) => {
  selectedRating.value = rating;
  showRatingDetailModal.value = true;
};

const getRatingCustomerName = (rating) => {
  const name =
    rating?.khach_hang?.ho_va_ten ||
    rating?.khachHang?.ho_va_ten ||
    rating?.khach_hang?.ho_ten ||
    rating?.khachHang?.ho_ten ||
    rating?.ho_va_ten ||
    rating?.ho_ten ||
    rating?.ten_khach_hang ||
    "";
  return name.trim() || "—";
};

/** Điểm sao 0–5 (số nguyên) cho hiển thị ★ */
const clampRatingScore = (raw) => {
  const n = Math.round(Number(raw));
  if (Number.isNaN(n)) return 0;
  return Math.min(5, Math.max(0, n));
};

const subRatingStars = (raw) => {
  if (raw == null || raw === "") return 0;
  return clampRatingScore(raw);
};

const subRatingLabel = (raw) => {
  if (raw == null || raw === "") return "—";
  return `${clampRatingScore(raw)}/5`;
};

/** Điểm TB chuyến (header): ưu tiên summary API, fallback từ trang đã tải */
const tripHeaderAvgScore = computed(() => {
  const a = ratingSummary.value?.avg_diem_so;
  if (a != null && !Number.isNaN(Number(a))) return Number(a);
  const rows = detailRatings.value;
  if (!rows.length) return null;
  const sum = rows.reduce((acc, r) => acc + Number(r?.diem_so ?? 0), 0);
  return Math.round((sum / rows.length) * 10) / 10;
});

const tripHeaderTotalRatings = computed(() => {
  const t = Number(ratingSummary.value?.total_ratings);
  if (Number.isFinite(t) && t >= 0) return t;
  return detailRatings.value.length;
});

const tripHeaderStarFill = computed(() => {
  const avg = tripHeaderAvgScore.value;
  if (avg == null || Number.isNaN(avg)) return 0;
  return clampRatingScore(Math.round(avg));
});

const handleBookTicket = () => {
  if (!selectedTrip.value) return;
  const tripId = selectedTrip.value.id;
  closeModal();

  if (clientStore.isLoggedIn) {
    router.push({ name: 'booking', query: { id_chuyen_xe: tripId } });
  } else {
    router.push({ name: 'client-login', query: { redirect: `/dat-ve?id_chuyen_xe=${tripId}` } });
  }
};

// ── Hàm tiện ích format ──────────────────────────────
const formatPrice = (price) => {
  if (!price) return "0đ";
  return new Intl.NumberFormat("vi-VN").format(price) + "đ";
};

const formatDate = (dateStr) => {
  if (!dateStr) return "...";
  const parts = dateStr.split("-");
  if (parts.length === 3) {
    const days = [
      "Chủ nhật",
      "Thứ 2",
      "Thứ 3",
      "Thứ 4",
      "Thứ 5",
      "Thứ 6",
      "Thứ 7",
    ];
    const d = new Date(dateStr);
    const dayName = days[d.getDay()];
    return `${dayName}, ${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  return dateStr;
};

const formatFullDate = (isoStr) => {
  if (!isoStr) return "...";
  const d = new Date(isoStr);
  const days = [
    "Chủ nhật",
    "Thứ 2",
    "Thứ 3",
    "Thứ 4",
    "Thứ 5",
    "Thứ 6",
    "Thứ 7",
  ];
  return `${days[d.getDay()]}, ${String(d.getDate()).padStart(2, "0")}/${String(
    d.getMonth() + 1
  ).padStart(2, "0")}/${d.getFullYear()}`;
};

const formatTime = (timeStr) => {
  if (!timeStr) return "--:--";
  return timeStr.slice(0, 5);
};

// Theo dõi thay đổi query params trên URL
watch(
  () => route.query,
  (newQuery) => {
    searchParams.value = {
      diem_di: newQuery.diem_di || "",
      diem_den: newQuery.diem_den || "",
      ngay_di: newQuery.ngay_di || "",
    };
    performSearch();
  },
  { deep: true }
);

onMounted(() => {
  fetchProvinces();
  performSearch();
  document.addEventListener("click", handleOutsideClick);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleOutsideClick);
});
</script>

<template>
  <div class="search-page">
    <!-- ── Hero Header với Form tìm kiếm ─────────────────── -->
    <div class="search-hero">
      <div class="search-hero__bg"></div>
      <div class="search-hero__content">
        <!-- Form tìm kiếm chuyến xe -->
        <div class="search-hero__form">
          <!-- Điểm đi -->
          <div class="search-hero__field" ref="fromSelectRef">
            <span
              class="material-symbols-outlined search-hero__field-icon search-hero__field-icon--from"
              >location_on</span
            >
            <div
              class="search-hero__select"
              :class="{ 'search-hero__select--open': isOpenFrom }"
              @click="
                isOpenFrom = !isOpenFrom;
                isOpenTo = false;
              "
            >
              <span
                :class="
                  searchForm.tinh_thanh_di_id
                    ? 'search-hero__select-text--filled'
                    : ''
                "
              >
                {{
                  searchForm.tinh_thanh_di_id
                    ? getProvinceName(searchForm.tinh_thanh_di_id)
                    : "Điểm đi"
                }}
              </span>
              <span
                class="material-symbols-outlined search-hero__chevron"
                :class="{ 'search-hero__chevron--open': isOpenFrom }"
                >expand_more</span
              >
            </div>
            <!-- Dropdown -->
            <div v-if="isOpenFrom" class="search-hero__dropdown">
              <div class="search-hero__dropdown-scroll">
                <button
                  v-for="p in provinces"
                  :key="p.id"
                  @click.stop="
                    searchForm.tinh_thanh_di_id = p.id;
                    isOpenFrom = false;
                  "
                  class="search-hero__dropdown-item"
                  :class="{
                    'search-hero__dropdown-item--active':
                      searchForm.tinh_thanh_di_id === p.id,
                  }"
                >
                  <span class="material-symbols-outlined">{{
                    searchForm.tinh_thanh_di_id === p.id
                      ? "my_location"
                      : "location_on"
                  }}</span>
                  {{ p.ten_tinh_thanh }}
                </button>
              </div>
            </div>
          </div>

          <!-- Nút đảo chiều -->
          <button
            class="search-hero__swap"
            @click="handleSwap"
            title="Đảo chiều"
          >
            <span class="material-symbols-outlined">swap_horiz</span>
          </button>

          <!-- Điểm đến -->
          <div class="search-hero__field" ref="toSelectRef">
            <span
              class="material-symbols-outlined search-hero__field-icon search-hero__field-icon--to"
              >location_on</span
            >
            <div
              class="search-hero__select"
              :class="{ 'search-hero__select--open': isOpenTo }"
              @click="
                isOpenTo = !isOpenTo;
                isOpenFrom = false;
              "
            >
              <span
                :class="
                  searchForm.tinh_thanh_den_id
                    ? 'search-hero__select-text--filled'
                    : ''
                "
              >
                {{
                  searchForm.tinh_thanh_den_id
                    ? getProvinceName(searchForm.tinh_thanh_den_id)
                    : "Điểm đến"
                }}
              </span>
              <span
                class="material-symbols-outlined search-hero__chevron"
                :class="{ 'search-hero__chevron--open': isOpenTo }"
                >expand_more</span
              >
            </div>
            <!-- Dropdown -->
            <div v-if="isOpenTo" class="search-hero__dropdown">
              <div class="search-hero__dropdown-scroll">
                <button
                  v-for="p in provinces"
                  :key="p.id"
                  @click.stop="
                    searchForm.tinh_thanh_den_id = p.id;
                    isOpenTo = false;
                  "
                  class="search-hero__dropdown-item"
                  :class="{
                    'search-hero__dropdown-item--active':
                      searchForm.tinh_thanh_den_id === p.id,
                  }"
                >
                  <span class="material-symbols-outlined">{{
                    searchForm.tinh_thanh_den_id === p.id
                      ? "my_location"
                      : "location_on"
                  }}</span>
                  {{ p.ten_tinh_thanh }}
                </button>
              </div>
            </div>
          </div>

          <!-- Ngày khởi hành -->
          <div class="search-hero__field search-hero__field--date">
            <CustomDatePicker v-model="searchForm.ngay_di" />
          </div>

          <!-- Nút tìm kiếm -->
          <button class="search-hero__search-btn" @click="submitNewSearch">
            <span class="material-symbols-outlined">search</span>
            <span class="search-hero__search-btn-text">Tìm chuyến</span>
          </button>
        </div>

        <!-- Thông tin lộ trình hiện tại -->
        <div
          class="search-hero__summary"
          v-if="searchParams.diem_di && searchParams.diem_den"
        >
          <span class="material-symbols-outlined">route</span>
          <strong>{{ searchParams.diem_di }}</strong>
          <span
            class="material-symbols-outlined"
            style="font-size: 16px; opacity: 0.5"
            >arrow_forward</span
          >
          <strong>{{ searchParams.diem_den }}</strong>
          <span class="search-hero__summary-sep">•</span>
          <span>{{ formatDate(searchParams.ngay_di) }}</span>
        </div>
      </div>
    </div>

    <!-- ── Nội dung chính ──────────────────────────────────── -->
    <div class="search-body mt-3">
      <!-- Nút mở bộ lọc (mobile) -->
      <button
        class="search-filter-toggle"
        @click="isFilterOpen = !isFilterOpen"
      >
        <span class="material-symbols-outlined">tune</span>
        Bộ lọc
        <span
          v-if="
            selectedPredefinedTime || filters.gia_ve_tu || filters.gia_ve_den
          "
          class="search-filter-toggle__badge"
        ></span>
      </button>

      <div class="search-layout">
        <!-- ── Sidebar bộ lọc ──────────────────────────────── -->
        <aside
          class="search-sidebar"
          :class="{ 'search-sidebar--open': isFilterOpen }"
        >
          <!-- Overlay mobile -->
          <div
            class="search-sidebar__overlay"
            @click="isFilterOpen = false"
          ></div>

          <div class="search-sidebar__inner">
            <div class="search-sidebar__header">
              <h2 class="search-sidebar__title">
                <span class="material-symbols-outlined">tune</span>
                Bộ lọc tìm kiếm
              </h2>
              <button @click="resetFilters" class="search-sidebar__reset">
                Xóa lọc
              </button>
            </div>

            <!-- Lọc giờ khởi hành nhanh -->
            <div class="search-filter-group">
              <h3 class="search-filter-group__title">
                <span class="material-symbols-outlined">schedule</span>
                Giờ khởi hành
              </h3>
              <div class="search-filter-time-grid">
                <button
                  v-for="tf in predefinedTimeFilters"
                  :key="tf.value"
                  @click="applyPredefinedTime(tf)"
                  class="search-filter-time-btn"
                  :class="{
                    'search-filter-time-btn--active':
                      selectedPredefinedTime === tf.value,
                  }"
                >
                  <span
                    class="material-symbols-outlined search-filter-time-btn__icon"
                    >{{ tf.icon }}</span
                  >
                  <span class="search-filter-time-btn__label">{{
                    tf.label
                  }}</span>
                </button>
              </div>
            </div>

            <!-- Lọc giá vé -->
            <div class="search-filter-group">
              <h3 class="search-filter-group__title">
                <span class="material-symbols-outlined">payments</span>
                Khoảng giá (VNĐ)
              </h3>
              <div class="search-filter-price">
                <input
                  type="number"
                  v-model="filters.gia_ve_tu"
                  placeholder="Từ..."
                  class="search-filter-input"
                />
                <span class="search-filter-price__sep">—</span>
                <input
                  type="number"
                  v-model="filters.gia_ve_den"
                  placeholder="Đến..."
                  class="search-filter-input"
                />
              </div>
              <button @click="applyFilters" class="search-filter-apply-btn">
                Áp dụng
              </button>
            </div>

            <!-- Nút đóng cho mobile -->
            <button
              class="search-sidebar__close-btn"
              @click="isFilterOpen = false"
            >
              Áp dụng bộ lọc
            </button>
          </div>
        </aside>

        <!-- ── Danh sách kết quả ───────────────────────────── -->
        <main class="search-results">
          <!-- Thanh trạng thái -->
          <div class="search-results__header">
            <div class="search-results__count">
              <template v-if="!isLoading">
                Tìm thấy <strong>{{ dsChuyenXe.length }}</strong> chuyến xe
              </template>
              <template v-else>Đang tìm kiếm...</template>
            </div>
            <div class="search-results__sort">
              <span class="material-symbols-outlined search-results__sort-icon"
                >sort</span
              >
              <select v-model="sortBy" class="search-results__sort-select">
                <option value="gio_som">Giờ sớm nhất</option>
                <option value="gio_muon">Giờ muộn nhất</option>
                <option value="gia_tang">Giá thấp nhất</option>
                <option value="gia_giam">Giá cao nhất</option>
              </select>
            </div>
          </div>

          <!-- Loading -->
          <div v-if="isLoading" class="search-results__loading">
            <div class="search-results__spinner"></div>
            <p>Đang tìm kiếm chuyến xe phù hợp...</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="!sortedTrips.length" class="search-results__empty">
            <div class="search-results__empty-icon">
              <span class="material-symbols-outlined">search_off</span>
            </div>
            <h3>Không tìm thấy chuyến xe</h3>
            <p>
              Rất tiếc, không có chuyến xe nào phù hợp với tìm kiếm của bạn. Hãy
              thử chọn ngày khác hoặc bỏ bớt bộ lọc.
            </p>
            <button @click="$router.push('/')" class="search-results__back-btn">
              <span class="material-symbols-outlined">arrow_back</span>
              Quay lại trang chủ
            </button>
          </div>

          <!-- Danh sách chuyến xe -->
          <div v-else class="search-results__list">
            <div
              v-for="chuyen in sortedTrips"
              :key="chuyen.id"
              class="trip-card"
              @click="openTripDetail(chuyen)"
            >
              <div class="trip-card__body">
                <!-- Cột thời gian + lộ trình -->
                <div class="trip-card__timeline">
                  <!-- Thông tin nhà xe -->
                  <div class="trip-card__operator">
                    <span
                      class="material-symbols-outlined trip-card__operator-icon"
                      >directions_bus</span
                    >
                    <span class="trip-card__operator-name">{{
                      chuyen.tuyen_duong?.nha_xe?.ten_nha_xe || "Nhà xe"
                    }}</span>
                    <span class="trip-card__vehicle-badge">{{
                      chuyen.xe?.ten_xe
                    }}</span>
                  </div>

                  <!-- Lộ trình -->
                  <div class="trip-card__route">
                    <div class="trip-card__stop">
                      <div class="trip-card__stop-time">
                        {{ formatTime(chuyen.gio_khoi_hanh) }}
                      </div>
                      <div
                        class="trip-card__stop-dot trip-card__stop-dot--from"
                      ></div>
                      <div class="trip-card__stop-name">
                        {{ chuyen.tuyen_duong?.diem_bat_dau }}
                      </div>
                    </div>

                    <div class="trip-card__duration">
                      <div class="trip-card__duration-line"></div>
                      <span class="trip-card__duration-text">
                        <span class="material-symbols-outlined">schedule</span>
                        ~{{ chuyen.tuyen_duong?.gio_du_kien || "?" }}h
                      </span>
                      <div class="trip-card__duration-line"></div>
                    </div>

                    <div class="trip-card__stop">
                      <div class="trip-card__stop-time">
                        {{
                          calcArrivalTime(
                            chuyen.gio_khoi_hanh,
                            chuyen.tuyen_duong?.gio_du_kien
                          )
                        }}
                      </div>
                      <div
                        class="trip-card__stop-dot trip-card__stop-dot--to"
                      ></div>
                      <div class="trip-card__stop-name">
                        {{ chuyen.tuyen_duong?.diem_ket_thuc }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Cột thông tin xe -->
                <div class="trip-card__info">
                  <div class="trip-card__details">
                    <div class="trip-card__detail-item">
                      <span class="material-symbols-outlined">event</span>
                      {{ formatFullDate(chuyen.ngay_khoi_hanh) }}
                    </div>
                    <div class="trip-card__detail-item">
                      <span class="material-symbols-outlined"
                        >airline_seat_recline_normal</span
                      >
                      {{ chuyen.xe?.so_ghe_thuc_te || "?" }} ghế
                    </div>
                    <div class="trip-card__detail-item">
                      <span class="material-symbols-outlined"
                        >confirmation_number</span
                      >
                      {{ chuyen.xe?.bien_so }}
                    </div>
                  </div>
                </div>

                <!-- Cột giá + nút đặt -->
                <div class="trip-card__action">
                  <div class="trip-card__price">
                    {{ formatPrice(chuyen.tuyen_duong?.gia_ve_co_ban) }}
                  </div>
                  <button
                    class="trip-card__book-btn"
                    @click.stop="openTripDetail(chuyen)"
                  >
                    Chọn chuyến
                    <span class="material-symbols-outlined">arrow_forward</span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>

    <!-- ── Modal chi tiết chuyến xe ──────────────────────── -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="showModal && selectedTrip"
          class="trip-modal-overlay"
          @click.self="closeModal"
        >
          <div class="trip-modal">
            <!-- Header Modal -->
            <div class="trip-modal__header">
              <div class="trip-modal__header-text">
                <h2 class="trip-modal__title">Chi tiết chuyến xe</h2>
                <span class="trip-modal__route-badge">
                  {{ selectedTrip.tuyen_duong?.diem_bat_dau }} →
                  {{ selectedTrip.tuyen_duong?.diem_ket_thuc }}
                </span>
              </div>
              <div class="trip-modal__header-rating" aria-live="polite">
                <template v-if="ratingLoading">
                  <span class="trip-modal__header-rating-loading">Đang tải…</span>
                </template>
                <template
                  v-else-if="tripHeaderTotalRatings > 0 && tripHeaderAvgScore != null"
                >
                  <div class="trip-modal__header-rating-stars">
                    <span
                      v-for="star in 5"
                      :key="`hdr-star-${star}`"
                      class="trip-modal__header-star"
                      :class="{
                        'trip-modal__header-star--on':
                          star <= tripHeaderStarFill,
                      }"
                      aria-hidden="true"
                      >★</span
                    >
                  </div>
                  <div class="trip-modal__header-rating-meta">
                    <span class="trip-modal__header-rating-score"
                      >{{ tripHeaderAvgScore }}/5</span
                    >
                    <span class="trip-modal__header-rating-count"
                      >{{ tripHeaderTotalRatings }} đánh giá</span
                    >
                  </div>
                </template>
                <template v-else>
                  <span class="trip-modal__header-rating-empty"
                    >Chưa có đánh giá</span
                  >
                </template>
              </div>
              <button class="trip-modal__close" @click="closeModal">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>

            <!-- Body Modal -->
            <div class="trip-modal__body">
              <div
                v-if="ratingLoading || detailRatings.length"
                class="trip-modal__ratings-top"
              >
                <div v-if="ratingLoading" class="trip-modal__ratings-top-loading">
                  Đang tải đánh giá...
                </div>
                <div v-else class="trip-modal__rating-list">
                  <div
                    v-for="rating in detailRatings"
                    :key="rating.id"
                    class="trip-modal__rating-item"
                    @click="openRatingDetail(rating)"
                  >
                    <div class="trip-modal__rating-head">
                      <span class="trip-modal__rating-name">
                        {{ getRatingCustomerName(rating) }}
                      </span>
                    </div>
                    <div class="trip-modal__rating-stars-row">
                      <span
                        v-for="star in 5"
                        :key="`${rating.id}-list-${star}`"
                        class="trip-modal__rating-star"
                        :class="{
                          'trip-modal__rating-star--on':
                            star <= clampRatingScore(rating.diem_so),
                        }"
                        aria-hidden="true"
                        >★</span
                      >
                      <span class="trip-modal__rating-score"
                        >{{ clampRatingScore(rating.diem_so) }}/5</span
                      >
                    </div>
                  </div>
                </div>
              </div>

              <!-- Nhà xe -->
              <div class="trip-modal__section">
                <div class="trip-modal__section-icon">
                  <span class="material-symbols-outlined">apartment</span>
                </div>
                <div>
                  <h3 class="trip-modal__section-title">Nhà xe</h3>
                  <p class="trip-modal__section-value">
                    {{ selectedTrip.tuyen_duong?.nha_xe?.ten_nha_xe }}
                  </p>
                  <p class="trip-modal__section-sub">
                    SĐT: {{ selectedTrip.tuyen_duong?.nha_xe?.so_dien_thoai }}
                  </p>
                </div>
              </div>

              <!-- Lộ trình -->
              <div class="trip-modal__section">
                <div class="trip-modal__section-icon">
                  <span class="material-symbols-outlined">route</span>
                </div>
                <div>
                  <h3 class="trip-modal__section-title">Lộ trình</h3>
                  <p class="trip-modal__section-value">
                    {{ selectedTrip.tuyen_duong?.ten_tuyen_duong }}
                  </p>
                  <p class="trip-modal__section-sub">
                    Quãng đường: {{ selectedTrip.tuyen_duong?.quang_duong }} km
                  </p>
                </div>
              </div>

              <!-- Timeline giờ -->
              <div class="trip-modal__timeline">
                <div class="trip-modal__tl-point">
                  <div
                    class="trip-modal__tl-dot trip-modal__tl-dot--from"
                  ></div>
                  <div>
                    <div class="trip-modal__tl-time">
                      {{ formatTime(selectedTrip.gio_khoi_hanh) }}
                    </div>
                    <div class="trip-modal__tl-place">
                      {{ selectedTrip.tuyen_duong?.diem_bat_dau }}
                    </div>
                  </div>
                </div>
                <div class="trip-modal__tl-line">
                  <span class="trip-modal__tl-duration"
                    >~{{ selectedTrip.tuyen_duong?.gio_du_kien }}h di
                    chuyển</span
                  >
                </div>
                <div class="trip-modal__tl-point">
                  <div class="trip-modal__tl-dot trip-modal__tl-dot--to"></div>
                  <div>
                    <div class="trip-modal__tl-time">
                      {{
                        calcArrivalTime(
                          selectedTrip.gio_khoi_hanh,
                          selectedTrip.tuyen_duong?.gio_du_kien
                        )
                      }}
                    </div>
                    <div class="trip-modal__tl-place">
                      {{ selectedTrip.tuyen_duong?.diem_ket_thuc }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Thông tin chi tiết -->
              <div class="trip-modal__grid">
                <div class="trip-modal__grid-item">
                  <span class="material-symbols-outlined">calendar_month</span>
                  <div>
                    <span class="trip-modal__grid-label">Ngày khởi hành</span>
                    <span class="trip-modal__grid-value">{{
                      formatFullDate(selectedTrip.ngay_khoi_hanh)
                    }}</span>
                  </div>
                </div>
                <div class="trip-modal__grid-item">
                  <span class="material-symbols-outlined">directions_bus</span>
                  <div>
                    <span class="trip-modal__grid-label">Xe</span>
                    <span class="trip-modal__grid-value">{{
                      selectedTrip.xe?.ten_xe
                    }}</span>
                  </div>
                </div>
                <div class="trip-modal__grid-item">
                  <span class="material-symbols-outlined">pin</span>
                  <div>
                    <span class="trip-modal__grid-label">Biển số</span>
                    <span class="trip-modal__grid-value">{{
                      selectedTrip.xe?.bien_so
                    }}</span>
                  </div>
                </div>
                <div class="trip-modal__grid-item">
                  <span class="material-symbols-outlined"
                    >airline_seat_recline_normal</span
                  >
                  <div>
                    <span class="trip-modal__grid-label">Số ghế</span>
                    <span class="trip-modal__grid-value"
                      >{{ selectedTrip.xe?.so_ghe_thuc_te }} ghế</span
                    >
                  </div>
                </div>
              </div>

              <!-- Ghi chú -->
              <div
                v-if="selectedTrip.tuyen_duong?.ghi_chu"
                class="trip-modal__note"
              >
                <span class="material-symbols-outlined">info</span>
                <span>{{ selectedTrip.tuyen_duong?.ghi_chu }}</span>
              </div>
            </div>

            <!-- Footer Modal -->
            <div class="trip-modal__footer">
              <div class="trip-modal__footer-price">
                <span class="trip-modal__footer-price-label">Giá vé</span>
                <span class="trip-modal__footer-price-value">{{
                  formatPrice(selectedTrip.tuyen_duong?.gia_ve_co_ban)
                }}</span>
              </div>
              <button class="trip-modal__footer-btn" @click="handleBookTicket">
                <span class="material-symbols-outlined"
                  >confirmation_number</span
                >
                Đặt vé ngay
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="showRatingDetailModal && selectedRating"
          class="trip-rating-detail-overlay"
          @click.self="showRatingDetailModal = false"
        >
          <div class="trip-rating-detail-modal">
            <div class="trip-rating-detail-header">
              <h3>Chi tiết đánh giá</h3>
              <button type="button" @click="showRatingDetailModal = false">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>
            <div class="trip-rating-detail-body">
              <div v-if="selectedTrip" class="trip-rating-detail-trip">
                <div class="trip-rating-detail-trip-title">Chuyến xe</div>
                <div class="trip-rating-detail-trip-route">
                  {{ selectedTrip.tuyen_duong?.diem_bat_dau }} →
                  {{ selectedTrip.tuyen_duong?.diem_ket_thuc }}
                </div>
                <div class="trip-rating-detail-trip-meta">
                  <span>{{ selectedTrip.tuyen_duong?.ten_tuyen_duong }}</span>
                  <span v-if="selectedTrip.tuyen_duong?.nha_xe?.ten_nha_xe">
                    · {{ selectedTrip.tuyen_duong.nha_xe.ten_nha_xe }}
                  </span>
                </div>
                <div class="trip-rating-detail-trip-meta">
                  <span>Ngày: {{ formatFullDate(selectedTrip.ngay_khoi_hanh) }}</span>
                  <span> · Giờ: {{ formatTime(selectedTrip.gio_khoi_hanh) }}</span>
                  <span v-if="selectedTrip.xe?.bien_so">
                    · Xe: {{ selectedTrip.xe.bien_so }}</span
                  >
                </div>
              </div>
              <div class="trip-rating-detail-top">
                <span class="trip-rating-detail-name">
                  {{ getRatingCustomerName(selectedRating) }}
                </span>
                <div class="trip-rating-detail-stars-row">
                  <span
                    v-for="star in 5"
                    :key="`detail-main-${star}`"
                    class="trip-rating-detail-star"
                    :class="{
                      'trip-rating-detail-star--on':
                        star <= clampRatingScore(selectedRating?.diem_so),
                    }"
                    aria-hidden="true"
                    >★</span
                  >
                  <span class="trip-rating-detail-score">{{
                    clampRatingScore(selectedRating?.diem_so)
                  }}/5</span>
                </div>
              </div>
              <div class="trip-rating-detail-grid">
                <div class="trip-rating-detail-metric">
                  <span class="trip-rating-detail-metric-label">Dịch vụ</span>
                  <div class="trip-rating-detail-metric-stars">
                    <span
                      v-for="star in 5"
                      :key="`dv-${star}`"
                      class="trip-rating-detail-star trip-rating-detail-star--sm"
                      :class="{
                        'trip-rating-detail-star--on':
                          star <= subRatingStars(selectedRating?.diem_dich_vu),
                      }"
                      aria-hidden="true"
                      >★</span
                    >
                    <strong>{{ subRatingLabel(selectedRating?.diem_dich_vu) }}</strong>
                  </div>
                </div>
                <div class="trip-rating-detail-metric">
                  <span class="trip-rating-detail-metric-label">An toàn</span>
                  <div class="trip-rating-detail-metric-stars">
                    <span
                      v-for="star in 5"
                      :key="`at-${star}`"
                      class="trip-rating-detail-star trip-rating-detail-star--sm"
                      :class="{
                        'trip-rating-detail-star--on':
                          star <= subRatingStars(selectedRating?.diem_an_toan),
                      }"
                      aria-hidden="true"
                      >★</span
                    >
                    <strong>{{ subRatingLabel(selectedRating?.diem_an_toan) }}</strong>
                  </div>
                </div>
                <div class="trip-rating-detail-metric">
                  <span class="trip-rating-detail-metric-label">Sạch sẽ</span>
                  <div class="trip-rating-detail-metric-stars">
                    <span
                      v-for="star in 5"
                      :key="`ss-${star}`"
                      class="trip-rating-detail-star trip-rating-detail-star--sm"
                      :class="{
                        'trip-rating-detail-star--on':
                          star <= subRatingStars(selectedRating?.diem_sach_se),
                      }"
                      aria-hidden="true"
                      >★</span
                    >
                    <strong>{{ subRatingLabel(selectedRating?.diem_sach_se) }}</strong>
                  </div>
                </div>
                <div class="trip-rating-detail-metric">
                  <span class="trip-rating-detail-metric-label">Thái độ</span>
                  <div class="trip-rating-detail-metric-stars">
                    <span
                      v-for="star in 5"
                      :key="`td-${star}`"
                      class="trip-rating-detail-star trip-rating-detail-star--sm"
                      :class="{
                        'trip-rating-detail-star--on':
                          star <= subRatingStars(selectedRating?.diem_thai_do),
                      }"
                      aria-hidden="true"
                      >★</span
                    >
                    <strong>{{ subRatingLabel(selectedRating?.diem_thai_do) }}</strong>
                  </div>
                </div>
              </div>
              <p class="trip-rating-detail-note">{{ selectedRating?.noi_dung || "Không có nhận xét." }}</p>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* ─── Trang tìm kiếm ──────────────────────────────────── */
.search-page {
  min-height: 100vh;
  background: #f8fafc;
  font-family: "Manrope", sans-serif;
}

/* ─── Hero Header ──────────────────────────────────────── */
.search-hero {
  position: relative;
  padding: 1.5rem 1rem 2.5rem;
  overflow: visible;
  z-index: 100; /* Tăng z-index để dropdown hiển thị trên search-body */
}

.search-hero__bg {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 50%, #3b82f6 100%);
}

.search-hero__bg::after {
  content: "";
  position: absolute;
  inset: 0;
  background: radial-gradient(
      ellipse at 20% 50%,
      rgba(56, 189, 248, 0.15) 0%,
      transparent 50%
    ),
    radial-gradient(
      ellipse at 80% 20%,
      rgba(168, 85, 247, 0.1) 0%,
      transparent 50%
    );
}

.search-hero__content {
  position: relative;
  z-index: 1;
  max-width: 1100px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

/* ── Form tìm kiếm ── */
.search-hero__form {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  border-radius: 20px;
  padding: 0.6rem;
  width: 100%;
  box-shadow: 0 10px 40px rgba(15, 23, 42, 0.15);
  flex-wrap: wrap;
}

@media (min-width: 768px) {
  .search-hero__form {
    flex-wrap: nowrap;
  }
}

.search-hero__field {
  position: relative;
  flex: 1;
  min-width: 0;
}

.search-hero__field--date {
  flex: 0 0 auto;
  min-width: 160px;
}

@media (max-width: 767px) {
  .search-hero__field {
    flex: 1 1 calc(50% - 1.5rem);
  }
  .search-hero__field--date {
    flex: 1 1 100%;
  }
}

.search-hero__field-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 20px;
  z-index: 2;
  pointer-events: none;
}

.search-hero__field-icon--from {
  color: #3b82f6;
}
.search-hero__field-icon--to {
  color: #f97316;
}
.search-hero__field-icon--date {
  color: #10b981;
}

/* ── Custom Select ── */
.search-hero__select {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 48px;
  padding: 0 12px 0 40px;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 14px;
  font-size: 0.88rem;
  font-weight: 600;
  color: #94a3b8;
  cursor: pointer;
  transition: all 0.2s;
  user-select: none;
  font-family: inherit;
}

.search-hero__select:hover {
  border-color: #cbd5e1;
}

.search-hero__select--open {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  background: #fff;
}

.search-hero__select-text--filled {
  color: #1e293b;
}

.search-hero__chevron {
  font-size: 20px;
  color: #94a3b8;
  transition: transform 0.25s;
  flex-shrink: 0;
}

.search-hero__chevron--open {
  transform: rotate(180deg);
}

/* ── Dropdown ── */
.search-hero__dropdown {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  width: 100%;
  min-width: 260px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 10px 40px rgba(15, 23, 42, 0.12);
  border: 1px solid #f1f5f9;
  z-index: 100;
  overflow: hidden;
}

.search-hero__dropdown-scroll {
  max-height: 280px;
  overflow-y: auto;
  padding: 0.35rem 0;
}

.search-hero__dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  padding: 0.65rem 1rem;
  border: none;
  background: none;
  font-size: 0.85rem;
  font-weight: 600;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
  text-align: left;
  font-family: inherit;
}

.search-hero__dropdown-item:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.search-hero__dropdown-item--active {
  background: #eff6ff;
  color: #1e40af;
  font-weight: 700;
}

.search-hero__dropdown-item .material-symbols-outlined {
  font-size: 18px;
  color: #94a3b8;
}

.search-hero__dropdown-item--active .material-symbols-outlined {
  color: #3b82f6;
}

/* ── Swap button ── */
.search-hero__swap {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  border: 1.5px solid #e2e8f0;
  background: #f8fafc;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.25s;
  flex-shrink: 0;
  font-family: inherit;
}

.search-hero__swap:hover {
  border-color: #3b82f6;
  color: #3b82f6;
  background: #eff6ff;
  transform: rotate(180deg);
}

.search-hero__swap .material-symbols-outlined {
  font-size: 20px;
}

/* ── Date input ── */
.search-hero__date-input {
  width: 100%;
  height: 48px;
  padding: 0 12px 0 40px;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 14px;
  font-size: 0.88rem;
  font-weight: 600;
  color: #1e293b;
  outline: none;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}

.search-hero__date-input:focus {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
  background: #fff;
}

/* ── Nút tìm kiếm ── */
.search-hero__search-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  height: 48px;
  padding: 0 1.5rem;
  background: linear-gradient(135deg, #3b82f6, #1e40af);
  color: #fff;
  border: none;
  border-radius: 14px;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.25s;
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
  flex-shrink: 0;
  font-family: inherit;
  white-space: nowrap;
}

.search-hero__search-btn:hover {
  box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
  transform: translateY(-1px);
}

.search-hero__search-btn:active {
  transform: scale(0.97);
}

.search-hero__search-btn .material-symbols-outlined {
  font-size: 20px;
}

@media (max-width: 767px) {
  .search-hero__search-btn {
    width: 100%;
  }
  .search-hero__search-btn-text {
    display: inline;
  }
}

/* ── Thanh tóm tắt lộ trình hiện tại ── */
.search-hero__summary {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1.25rem;
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 50px;
  color: rgba(255, 255, 255, 0.85);
  font-size: 0.85rem;
  font-weight: 600;
  flex-wrap: wrap;
  justify-content: center;
}

.search-hero__summary .material-symbols-outlined {
  font-size: 18px;
}
.search-hero__summary strong {
  color: #fff;
}
.search-hero__summary-sep {
  opacity: 0.4;
}

/* ─── Body ─────────────────────────────────────────────── */
.search-body {
  max-width: 1240px;
  margin: 0 auto;
  padding: 0 1rem;
  margin-top: -1.5rem;
  position: relative;
  z-index: 10;
}

/* ── Mobile filter toggle ── */
.search-filter-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.25rem;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.875rem;
  font-weight: 700;
  color: #475569;
  cursor: pointer;
  margin-bottom: 1rem;
  font-family: inherit;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
  position: relative;
}

.search-filter-toggle .material-symbols-outlined {
  font-size: 20px;
}

.search-filter-toggle__badge {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #3b82f6;
  position: absolute;
  top: 8px;
  right: 8px;
}

@media (min-width: 1024px) {
  .search-filter-toggle {
    display: none;
  }
}

/* ── Layout ── */
.search-layout {
  display: flex;
  gap: 1.5rem;
  align-items: flex-start;
}

/* ─── Sidebar ──────────────────────────────────────────── */
.search-sidebar {
  width: 300px;
  flex-shrink: 0;
  display: none;
}

@media (min-width: 1024px) {
  .search-sidebar {
    display: block;
  }
}

.search-sidebar__overlay {
  display: none;
}

/* Mobile sidebar: overlay + slide-in */
.search-sidebar--open {
  display: block;
  position: fixed;
  inset: 0;
  z-index: 200;
}

.search-sidebar--open .search-sidebar__overlay {
  display: block;
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(4px);
  z-index: 1;
}

.search-sidebar--open .search-sidebar__inner {
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  width: 320px;
  max-width: 85vw;
  z-index: 2;
  overflow-y: auto;
  border-radius: 0 20px 20px 0;
  animation: slideInLeft 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideInLeft {
  from {
    transform: translateX(-100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@media (min-width: 1024px) {
  .search-sidebar--open {
    position: static;
  }
  .search-sidebar--open .search-sidebar__overlay {
    display: none;
  }
  .search-sidebar--open .search-sidebar__inner {
    position: static;
    width: auto;
    border-radius: 20px;
    animation: none;
  }
}

.search-sidebar__inner {
  background: #fff;
  border-radius: 20px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
  border: 1px solid #f1f5f9;
  position: sticky;
  top: 88px;
}

.search-sidebar__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.search-sidebar__title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1rem;
  font-weight: 800;
  color: #1e293b;
}

.search-sidebar__title .material-symbols-outlined {
  font-size: 20px;
  color: #3b82f6;
}

.search-sidebar__reset {
  font-size: 0.8rem;
  font-weight: 600;
  color: #3b82f6;
  background: none;
  border: none;
  cursor: pointer;
  font-family: inherit;
  transition: color 0.2s;
}

.search-sidebar__reset:hover {
  color: #1e40af;
}

/* ── Filter Groups ── */
.search-filter-group {
  margin-bottom: 1.5rem;
}

.search-filter-group__title {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  font-weight: 700;
  color: #475569;
  margin-bottom: 0.85rem;
}

.search-filter-group__title .material-symbols-outlined {
  font-size: 18px;
  color: #94a3b8;
}

/* ── Lưới chọn giờ nhanh ── */
.search-filter-time-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}

.search-filter-time-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.3rem;
  padding: 0.65rem 0.5rem;
  background: #f8fafc;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-family: inherit;
}

.search-filter-time-btn:hover {
  border-color: #93c5fd;
  background: #eff6ff;
}

.search-filter-time-btn--active {
  border-color: #3b82f6;
  background: #eff6ff;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.search-filter-time-btn__icon {
  font-size: 22px;
  color: #94a3b8;
}

.search-filter-time-btn--active .search-filter-time-btn__icon {
  color: #3b82f6;
}

.search-filter-time-btn__label {
  font-size: 0.7rem;
  font-weight: 600;
  color: #64748b;
  text-align: center;
  line-height: 1.3;
}

.search-filter-time-btn--active .search-filter-time-btn__label {
  color: #1e40af;
  font-weight: 700;
}

/* ── Lọc giá ── */
.search-filter-price {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.search-filter-price__sep {
  color: #cbd5e1;
  font-weight: 600;
  flex-shrink: 0;
}

.search-filter-input {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.85rem;
  outline: none;
  transition: all 0.2s;
  font-family: inherit;
  background: #f8fafc;
}

.search-filter-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
  background: #fff;
}

.search-filter-apply-btn {
  width: 100%;
  padding: 0.6rem;
  background: #f1f5f9;
  border: none;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 700;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
}

.search-filter-apply-btn:hover {
  background: #e2e8f0;
}

/* Nút đóng sidebar mobile */
.search-sidebar__close-btn {
  display: none;
  width: 100%;
  padding: 0.85rem;
  margin-top: 1rem;
  background: linear-gradient(135deg, #3b82f6, #1e40af);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
}

@media (max-width: 1023px) {
  .search-sidebar__close-btn {
    display: block;
  }
}

/* ─── Results Area ─────────────────────────────────────── */
.search-results {
  flex: 1;
  min-width: 0;
}

.search-results__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  border-radius: 16px;
  padding: 0.85rem 1.25rem;
  margin-bottom: 1rem;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
  border: 1px solid #f1f5f9;
}

.search-results__count {
  font-size: 0.9rem;
  color: #64748b;
  font-weight: 500;
}

.search-results__count strong {
  color: #1e40af;
  font-size: 1.1rem;
  font-weight: 800;
}

.search-results__sort {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.search-results__sort-icon {
  font-size: 18px;
  color: #94a3b8;
}

.search-results__sort-select {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.35rem 0.65rem;
  font-size: 0.8rem;
  outline: none;
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  transition: border-color 0.2s;
}

.search-results__sort-select:focus {
  border-color: #3b82f6;
}

/* ── Loading ── */
.search-results__loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 5rem 2rem;
  background: #fff;
  border-radius: 20px;
  border: 1px solid #f1f5f9;
  gap: 1rem;
}

.search-results__spinner {
  width: 44px;
  height: 44px;
  border: 4px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.search-results__loading p {
  color: #64748b;
  font-weight: 600;
  font-size: 0.9rem;
}

/* ── Empty State ── */
.search-results__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 4rem 2rem;
  background: #fff;
  border-radius: 20px;
  border: 1px solid #f1f5f9;
}

.search-results__empty-icon {
  width: 80px;
  height: 80px;
  background: #f1f5f9;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.5rem;
}

.search-results__empty-icon .material-symbols-outlined {
  font-size: 40px;
  color: #cbd5e1;
}

.search-results__empty h3 {
  font-size: 1.25rem;
  font-weight: 800;
  color: #1e293b;
  margin-bottom: 0.5rem;
}

.search-results__empty p {
  color: #64748b;
  max-width: 400px;
  line-height: 1.6;
  margin-bottom: 1.5rem;
  font-size: 0.9rem;
}

.search-results__back-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  background: linear-gradient(135deg, #3b82f6, #1e40af);
  color: #fff;
  border: none;
  border-radius: 50px;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.25s;
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
  font-family: inherit;
}

.search-results__back-btn:hover {
  box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
  transform: translateY(-1px);
}

/* ─── Trip Card ────────────────────────────────────────── */
.search-results__list {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding-bottom: 2rem;
}

.trip-card {
  background: #fff;
  border-radius: 20px;
  border: 1px solid #f1f5f9;
  overflow: hidden;
  transition: all 0.25s ease;
  position: relative;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

.trip-card:hover {
  box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
  border-color: #e2e8f0;
  transform: translateY(-2px);
}

.trip-card__ribbon {
  position: absolute;
  top: 12px;
  right: -28px;
  background: linear-gradient(135deg, #f59e0b, #f97316);
  color: #fff;
  font-size: 0.65rem;
  font-weight: 800;
  padding: 0.2rem 2.5rem;
  transform: rotate(45deg);
  letter-spacing: 0.1em;
  z-index: 5;
  box-shadow: 0 2px 8px rgba(249, 115, 22, 0.3);
}

.trip-card__body {
  display: flex;
  flex-direction: column;
  padding: 1.25rem;
  gap: 1rem;
}

@media (min-width: 768px) {
  .trip-card__body {
    flex-direction: row;
    align-items: center;
    padding: 1.5rem;
    gap: 1.5rem;
  }
}

/* ── Timeline ── */
.trip-card__timeline {
  flex: 1;
  min-width: 0;
}

.trip-card__operator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.trip-card__operator-icon {
  font-size: 22px;
  color: #3b82f6;
}

.trip-card__operator-name {
  font-size: 0.95rem;
  font-weight: 800;
  color: #1e293b;
}

.trip-card__vehicle-type {
  font-size: 0.7rem;
  font-weight: 700;
  color: #f59e0b;
  background: #fffbeb;
  border: 1px solid #fef3c7;
  padding: 0.15rem 0.5rem;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* ── Lộ trình ── */
.trip-card__route {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.trip-card__stop {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  min-width: 0;
}

.trip-card__stop-time {
  font-size: 1.3rem;
  font-weight: 800;
  color: #1e293b;
  letter-spacing: -0.02em;
  flex-shrink: 0;
}

.trip-card__stop-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  flex-shrink: 0;
}

.trip-card__stop-dot--from {
  background: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.trip-card__stop-dot--to {
  background: #f97316;
  box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
}

.trip-card__stop-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.trip-card__duration {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-shrink: 0;
  padding: 0 0.25rem;
}

.trip-card__duration-line {
  width: 16px;
  height: 1.5px;
  background: #e2e8f0;
}

.trip-card__duration-text {
  display: flex;
  align-items: center;
  gap: 0.2rem;
  font-size: 0.7rem;
  font-weight: 600;
  color: #94a3b8;
  white-space: nowrap;
}

.trip-card__duration-text .material-symbols-outlined {
  font-size: 14px;
}

/* ── Info ── */
.trip-card__info {
  flex-shrink: 0;
}

@media (min-width: 768px) {
  .trip-card__info {
    padding: 0 1rem;
    border-left: 1px dashed #e2e8f0;
    border-right: 1px dashed #e2e8f0;
  }
}

.trip-card__details {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

@media (min-width: 768px) {
  .trip-card__details {
    flex-direction: column;
    gap: 0.4rem;
  }
}

.trip-card__detail-item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b;
  background: #f8fafc;
  padding: 0.35rem 0.65rem;
  border-radius: 8px;
  border: 1px solid #f1f5f9;
}

.trip-card__detail-item .material-symbols-outlined {
  font-size: 16px;
  color: #94a3b8;
}

.trip-card__detail-item--seat {
  color: #16a34a;
  background: #f0fdf4;
  border-color: #dcfce7;
}

.trip-card__detail-item--seat .material-symbols-outlined {
  color: #16a34a;
}

/* ── Action ── */
.trip-card__action {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-shrink: 0;
}

@media (min-width: 768px) {
  .trip-card__action {
    flex-direction: column;
    align-items: flex-end;
    width: 160px;
  }
}

.trip-card__price {
  font-size: 1.4rem;
  font-weight: 800;
  color: #1e40af;
  letter-spacing: -0.02em;
}

.trip-card__book-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.7rem 1.5rem;
  background: linear-gradient(135deg, #f97316, #ea580c);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 14px rgba(249, 115, 22, 0.25);
  font-family: inherit;
  white-space: nowrap;
}

.trip-card__book-btn:hover {
  box-shadow: 0 6px 20px rgba(249, 115, 22, 0.35);
  transform: translateY(-1px);
}

.trip-card__book-btn:active {
  transform: scale(0.97);
}

.trip-card__book-btn .material-symbols-outlined {
  font-size: 18px;
}

.trip-card__vehicle-badge {
  font-size: 0.7rem;
  font-weight: 700;
  color: #3b82f6;
  background: #eff6ff;
  border: 1px solid #dbeafe;
  padding: 0.15rem 0.5rem;
  border-radius: 6px;
}

/* ─── Responsive ───────────────────────────────────────── */
@media (max-width: 640px) {
  .trip-card__route {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
  }
  .trip-card__duration {
    padding-left: 1rem;
  }
  .trip-card__stop-time {
    font-size: 1.1rem;
    width: 50px;
  }
}

/* ─── Modal chi tiết chuyến xe ─────────────────────────── */
.trip-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9000;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.trip-modal {
  background: #fff;
  border-radius: 24px;
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.2);
  animation: modalSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalSlideUp {
  from {
    transform: translateY(30px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

/* Transition */
.modal-fade-enter-active {
  transition: opacity 0.25s ease;
}
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

/* Header */
.trip-modal__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.65rem 1rem;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 100%);
  color: #fff;
  border-radius: 24px 24px 0 0;
}

.trip-modal__header-text {
  flex: 1 1 auto;
  min-width: 0;
}

.trip-modal__title {
  font-size: 1.1rem;
  font-weight: 800;
  margin: 0 0 0.35rem;
}

.trip-modal__route-badge {
  display: inline-block;
  font-size: 0.8rem;
  font-weight: 600;
  opacity: 0.85;
}

.trip-modal__header-rating {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.15rem;
  flex: 0 0 auto;
  text-align: right;
}

.trip-modal__header-rating-stars {
  display: flex;
  align-items: center;
  gap: 0.06rem;
  line-height: 1;
}

.trip-modal__header-star {
  font-size: 0.95rem;
  color: rgba(255, 255, 255, 0.32);
  letter-spacing: -0.06em;
}

.trip-modal__header-star--on {
  color: #fcd34d;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.trip-modal__header-rating-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.05rem;
}

.trip-modal__header-rating-score {
  font-size: 0.88rem;
  font-weight: 800;
  color: #fef08a;
  line-height: 1.2;
}

.trip-modal__header-rating-count {
  font-size: 0.72rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.88);
}

.trip-modal__header-rating-loading,
.trip-modal__header-rating-empty {
  font-size: 0.78rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.75);
  white-space: nowrap;
}

.trip-modal__close {
  background: rgba(255, 255, 255, 0.15);
  border: none;
  border-radius: 50%;
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #fff;
  transition: background 0.2s;
  flex-shrink: 0;
}

.trip-modal__close:hover {
  background: rgba(255, 255, 255, 0.25);
}

/* Body */
.trip-modal__body {
  padding: 1.25rem 1.5rem;
  overflow-y: auto;
  flex: 1;
}

.trip-modal__section {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  margin-bottom: 1.25rem;
}

.trip-modal__section-icon {
  width: 40px;
  height: 40px;
  background: #f1f5f9;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  color: #3b82f6;
}

.trip-modal__section-icon .material-symbols-outlined {
  font-size: 22px;
}
.trip-modal__section-title {
  font-size: 0.75rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.15rem;
}
.trip-modal__section-value {
  font-size: 0.95rem;
  font-weight: 700;
  color: #1e293b;
}
.trip-modal__section-sub {
  font-size: 0.8rem;
  color: #64748b;
  margin-top: 0.15rem;
}

.trip-modal__ratings-top {
  margin: -0.25rem 0 1rem;
}

.trip-modal__ratings-top-loading {
  font-size: 0.78rem;
  color: #64748b;
  padding: 0.35rem 0;
}

.trip-modal__rating-list {
  margin-top: 0;
  display: flex;
  flex-direction: row;
  gap: 0.5rem;
  overflow-x: auto;
  padding-bottom: 0.2rem;
  scroll-snap-type: x mandatory;
}

.trip-modal__rating-item {
  min-width: 220px;
  max-width: 240px;
  flex: 0 0 auto;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  padding: 0.55rem 0.7rem;
  scroll-snap-align: start;
  cursor: pointer;
  transition: all 0.18s ease;
}

.trip-modal__rating-item:hover {
  border-color: #93c5fd;
  background: #eff6ff;
}

.trip-modal__rating-head {
  display: flex;
  justify-content: flex-start;
  align-items: center;
}

.trip-modal__rating-name {
  font-size: 0.82rem;
  font-weight: 700;
  color: #1e293b;
}

.trip-modal__rating-stars-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.1rem 0.2rem;
  margin-top: 0.35rem;
}

.trip-modal__rating-star {
  font-size: 0.78rem;
  line-height: 1;
  color: #cbd5e1;
  letter-spacing: -0.06em;
}

.trip-modal__rating-star--on {
  color: #f59e0b;
}

.trip-modal__rating-score {
  font-size: 0.78rem;
  font-weight: 800;
  color: #d97706;
  margin-left: 0.25rem;
}

.trip-rating-detail-overlay {
  position: fixed;
  inset: 0;
  z-index: 9100;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.trip-rating-detail-modal {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25);
  border: 1px solid #e2e8f0;
}

.trip-rating-detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
}

.trip-rating-detail-header h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 800;
  color: #1e293b;
}

.trip-rating-detail-header button {
  border: none;
  background: transparent;
  cursor: pointer;
  color: #64748b;
}

.trip-rating-detail-body {
  padding: 0.9rem 1rem 1rem;
}

.trip-rating-detail-trip {
  margin-bottom: 0.85rem;
  padding: 0.65rem 0.75rem;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.78rem;
  color: #334155;
}

.trip-rating-detail-trip-title {
  font-size: 0.65rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
  margin-bottom: 0.35rem;
}

.trip-rating-detail-trip-route {
  font-weight: 800;
  color: #1e40af;
  font-size: 0.82rem;
  margin-bottom: 0.25rem;
}

.trip-rating-detail-trip-meta {
  line-height: 1.45;
  color: #475569;
}

.trip-rating-detail-top {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.35rem;
}

.trip-rating-detail-name {
  font-size: 0.85rem;
  font-weight: 700;
  color: #1e293b;
}

.trip-rating-detail-stars-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.08rem 0.15rem;
}

.trip-rating-detail-star {
  font-size: 0.95rem;
  line-height: 1;
  color: #cbd5e1;
  letter-spacing: -0.05em;
}

.trip-rating-detail-star--sm {
  font-size: 0.72rem;
}

.trip-rating-detail-star--on {
  color: #f59e0b;
}

.trip-rating-detail-score {
  font-size: 0.8rem;
  font-weight: 800;
  color: #d97706;
  margin-left: 0.3rem;
}

.trip-rating-detail-grid {
  margin-top: 0.65rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem 0.65rem;
  font-size: 0.78rem;
  color: #475569;
}

.trip-rating-detail-metric {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.trip-rating-detail-metric-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.trip-rating-detail-metric-stars {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.06rem 0.12rem;
}

.trip-rating-detail-metric-stars strong {
  margin-left: 0.25rem;
  font-size: 0.78rem;
  color: #334155;
}

.trip-rating-detail-note {
  margin-top: 0.65rem;
  margin-bottom: 0;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  border-radius: 10px;
  padding: 0.55rem 0.65rem;
  font-size: 0.8rem;
  color: #334155;
}

/* Timeline */
.trip-modal__timeline {
  background: #f8fafc;
  border-radius: 16px;
  padding: 1.25rem;
  margin-bottom: 1.25rem;
  border: 1px solid #f1f5f9;
}

.trip-modal__tl-point {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.trip-modal__tl-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  flex-shrink: 0;
}

.trip-modal__tl-dot--from {
  background: #3b82f6;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
}
.trip-modal__tl-dot--to {
  background: #f97316;
  box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15);
}

.trip-modal__tl-time {
  font-size: 1.2rem;
  font-weight: 800;
  color: #1e293b;
}
.trip-modal__tl-place {
  font-size: 0.8rem;
  font-weight: 600;
  color: #64748b;
}

.trip-modal__tl-line {
  display: flex;
  align-items: center;
  padding: 0.5rem 0 0.5rem 6px;
  border-left: 2px dashed #e2e8f0;
  margin-left: 6px;
}

.trip-modal__tl-duration {
  font-size: 0.75rem;
  font-weight: 700;
  color: #94a3b8;
  background: #fff;
  padding: 0.25rem 0.75rem;
  border-radius: 50px;
  border: 1px solid #e2e8f0;
  margin-left: 1rem;
}

/* Grid info */
.trip-modal__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.trip-modal__grid-item {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.65rem;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #f1f5f9;
}

.trip-modal__grid-item .material-symbols-outlined {
  font-size: 20px;
  color: #94a3b8;
}
.trip-modal__grid-label {
  display: block;
  font-size: 0.68rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
}
.trip-modal__grid-value {
  display: block;
  font-size: 0.85rem;
  font-weight: 700;
  color: #1e293b;
}

/* Note */
.trip-modal__note {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  padding: 0.75rem;
  background: #fffbeb;
  border: 1px solid #fef3c7;
  border-radius: 12px;
  font-size: 0.82rem;
  color: #92400e;
  font-weight: 600;
}

.trip-modal__note .material-symbols-outlined {
  font-size: 18px;
  color: #f59e0b;
  flex-shrink: 0;
  margin-top: 1px;
}

/* Footer */
.trip-modal__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  border-top: 1px solid #f1f5f9;
  background: #fafbfc;
  border-radius: 0 0 24px 24px;
}

.trip-modal__footer-price-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
}
.trip-modal__footer-price-value {
  font-size: 1.5rem;
  font-weight: 800;
  color: #1e40af;
  letter-spacing: -0.02em;
}

.trip-modal__footer-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.85rem 1.75rem;
  background: linear-gradient(135deg, #f97316, #ea580c);
  color: #fff;
  border: none;
  border-radius: 14px;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(249, 115, 22, 0.3);
  transition: all 0.2s;
  font-family: inherit;
}

.trip-modal__footer-btn:hover {
  box-shadow: 0 6px 20px rgba(249, 115, 22, 0.4);
  transform: translateY(-1px);
}

.trip-modal__footer-btn .material-symbols-outlined {
  font-size: 20px;
}
</style>

