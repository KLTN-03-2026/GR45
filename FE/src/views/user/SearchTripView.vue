<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import clientApi from "@/api/clientApi";
import { useClientStore } from "@/stores/clientStore.js";
import { formatCurrency, formatTime, calcArrivalTime, formatFullDate, formatDateOnly } from "@/utils/format";

// New Components
import SearchTripForm from "@/components/user/search/SearchTripForm.vue";
import SearchTripSidebar from "@/components/user/search/SearchTripSidebar.vue";
import SearchTripCard from "@/components/user/search/SearchTripCard.vue";
import SearchTripDetailModal from "@/components/user/search/SearchTripDetailModal.vue";
import SearchTripRatingModal from "@/components/user/search/SearchTripRatingModal.vue";

const route = useRoute();
const router = useRouter();
const clientStore = useClientStore();

const isLoading = ref(true);
const dsChuyenXe = ref([]);
const provinces = ref([]);

// Form tìm kiếm
const searchForm = ref({
  tinh_thanh_di_id: "",
  tinh_thanh_den_id: "",
  ngay_di: route.query.ngay_di || new Date().toISOString().split("T")[0],
});

// Lấy tham số tìm kiếm từ URL query
const searchParams = ref({
  diem_di: route.query.diem_di || "",
  diem_den: route.query.diem_den || "",
  ngay_di: route.query.ngay_di || "",
});

const isFilterOpen = ref(false);
const sortBy = ref("gio_som");
const filters = ref({
  gia_ve_tu: "",
  gia_ve_den: "",
  gio_khoi_hanh_tu: "",
  gio_khoi_hanh_den: "",
});

const selectedPredefinedTime = ref(null);
const predefinedTimeFilters = [
  { label: "Sáng sớm (00:00 - 06:00)", value: "dawn", tu: "00:00", den: "06:00", icon: "dark_mode" },
  { label: "Buổi sáng (06:00 - 12:00)", value: "morning", tu: "06:00", den: "12:00", icon: "light_mode" },
  { label: "Buổi chiều (12:00 - 18:00)", value: "afternoon", tu: "12:00", den: "18:00", icon: "wb_sunny" },
  { label: "Buổi tối (18:00 - 24:00)", value: "evening", tu: "18:00", den: "23:59", icon: "nightlight" },
];

// Modal & Ratings state
const selectedTrip = ref(null);
const showModal = ref(false);
const detailRatings = ref([]);
const ratingSummary = ref({ total_ratings: 0, avg_diem_so: null });
const ratingLoading = ref(false);
const showRatingDetailModal = ref(false);
const selectedRatingGroup = ref(null);

const cleanProvinceName = (name) => {
  if (!name) return "";
  return name.replace(/^(Thành phố |Tỉnh )/i, "").trim();
};

const getProvinceName = (id) => {
  const p = provinces.value.find((x) => x.id === id);
  return p ? p.ten_tinh_thanh : "";
};

const fetchProvinces = async () => {
  try {
    const res = await clientApi.getProvinces();
    if (res && res.data) {
      provinces.value = Array.isArray(res.data) ? res.data : res.data.data || [];
      if (searchParams.value.diem_di) {
        const found = provinces.value.find(p => cleanProvinceName(p.ten_tinh_thanh) === searchParams.value.diem_di);
        if (found) searchForm.value.tinh_thanh_di_id = found.id;
      }
      if (searchParams.value.diem_den) {
        const found = provinces.value.find(p => cleanProvinceName(p.ten_tinh_thanh) === searchParams.value.diem_den);
        if (found) searchForm.value.tinh_thanh_den_id = found.id;
      }
    }
  } catch (e) { console.error(e); }
};

const handleSwap = () => {
  const temp = searchForm.value.tinh_thanh_di_id;
  searchForm.value.tinh_thanh_di_id = searchForm.value.tinh_thanh_den_id;
  searchForm.value.tinh_thanh_den_id = temp;
};

const submitNewSearch = () => {
  const diemDi = cleanProvinceName(getProvinceName(searchForm.value.tinh_thanh_di_id));
  const diemDen = cleanProvinceName(getProvinceName(searchForm.value.tinh_thanh_den_id));
  if (!diemDi || !diemDen || !searchForm.value.ngay_di) return;
  router.push({ path: "/search", query: { diem_di: diemDi, diem_den: diemDen, ngay_di: searchForm.value.ngay_di } });
};

const applyPredefinedTime = (timeFilter) => {
  if (selectedPredefinedTime.value === timeFilter.value) {
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

const performSearch = async () => {
  isLoading.value = true;
  try {
    const params = {
      diem_di: searchParams.value.diem_di,
      diem_den: searchParams.value.diem_den,
      ngay_khoi_hanh: searchParams.value.ngay_di,
      ...filters.value
    };
    const res = await clientApi.searchTrips(params);
    dsChuyenXe.value = res?.data?.data || res?.data || [];
  } catch (error) {
    dsChuyenXe.value = [];
  } finally {
    isLoading.value = false;
  }
};

const applyFilters = () => performSearch();
const resetAllFilters = () => {
  filters.value = { gia_ve_tu: "", gia_ve_den: "", gio_khoi_hanh_tu: "", gio_khoi_hanh_den: "" };
  selectedPredefinedTime.value = null;
  performSearch();
};

const sortedTrips = computed(() => {
  const list = [...dsChuyenXe.value];
  const getPrice = (c) => parseFloat(c.tuyen_duong?.gia_ve_co_ban || 0);
  switch (sortBy.value) {
    case "gio_som": return list.sort((a, b) => (a.gio_khoi_hanh || "").localeCompare(b.gio_khoi_hanh || ""));
    case "gio_muon": return list.sort((a, b) => (b.gio_khoi_hanh || "").localeCompare(a.gio_khoi_hanh || ""));
    case "gia_tang": return list.sort((a, b) => getPrice(a) - getPrice(b));
    case "gia_giam": return list.sort((a, b) => getPrice(b) - getPrice(a));
    default: return list;
  }
});

const fetchTripRatings = async (tripId) => {
  if (!tripId) return;
  try {
    ratingLoading.value = true;
    const res = await clientApi.getTripRatings(tripId, { per_page: 100 });
    detailRatings.value = res?.data?.data || res?.data || [];
    ratingSummary.value = res?.summary || { total_ratings: detailRatings.value.length, avg_diem_so: null };
  } catch (error) {
    detailRatings.value = [];
  } finally {
    ratingLoading.value = false;
  }
};

const openTripDetail = (chuyen) => {
  selectedTrip.value = chuyen;
  showModal.value = true;
  fetchTripRatings(chuyen?.id);
};

const closeModal = () => {
  showModal.value = false;
  selectedTrip.value = null;
};

const openRatingDetail = (group) => {
  selectedRatingGroup.value = group;
  showRatingDetailModal.value = true;
};

const groupedRatings = computed(() => {
  const map = new Map();
  for (const r of detailRatings.value) {
    const name = r?.khach_hang?.ho_va_ten || "Khách hàng";
    const key = r?.id_khach_hang || name;
    if (!map.has(key)) map.set(key, { key, name, ratings: [] });
    map.get(key).ratings.push(r);
  }
  return Array.from(map.values()).map(g => ({
    ...g,
    total: g.ratings.length,
    avgScore: Math.round((g.ratings.reduce((s, r) => s + Number(r.diem_so), 0) / g.ratings.length) * 10) / 10
  }));
});

const tripHeaderAvgScore = computed(() => {
  if (ratingSummary.value?.avg_diem_so) return ratingSummary.value.avg_diem_so;
  if (!detailRatings.value.length) return null;
  return Math.round((detailRatings.value.reduce((s, r) => s + Number(r.diem_so), 0) / detailRatings.value.length) * 10) / 10;
});

const tripHeaderTotalRatings = computed(() => ratingSummary.value?.total_ratings || detailRatings.value.length);
const tripHeaderStarFill = computed(() => Math.round(Number(tripHeaderAvgScore.value || 0)));

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

watch(() => route.query, () => {
  searchParams.value = { diem_di: route.query.diem_di || "", diem_den: route.query.diem_den || "", ngay_di: route.query.ngay_di || "" };
  performSearch();
}, { deep: true });

onMounted(() => {
  fetchProvinces();
  performSearch();
});
</script>

<template>
  <div class="search-page">
    <!-- Hero Header / Search Box -->
    <SearchTripForm
      :search-form="searchForm"
      :search-params="searchParams"
      :provinces="provinces"
      @swap="handleSwap"
      @submit="submitNewSearch"
    />

    <!-- Nội dung chính -->
    <div class="search-body mt-3">
      <!-- Nút mở bộ lọc (mobile) -->
      <button
        class="search-filter-toggle"
        @click="isFilterOpen = !isFilterOpen"
      >
        <span class="material-symbols-outlined">tune</span>
        Bộ lọc
        <span
          v-if="selectedPredefinedTime || filters.gia_ve_tu || filters.gia_ve_den"
          class="search-filter-toggle__badge"
        ></span>
      </button>

      <div class="search-layout">
        <!-- Sidebar bộ lọc -->
        <SearchTripSidebar
          v-model:filters="filters"
          :predefined-time-filters="predefinedTimeFilters"
          :selected-predefined-time="selectedPredefinedTime"
          :is-filter-open="isFilterOpen"
          @apply-time="applyPredefinedTime"
          @apply="applyFilters"
          @reset="resetAllFilters"
          @close="isFilterOpen = false"
        />

        <!-- Danh sách kết quả -->
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
              <span class="material-symbols-outlined search-results__sort-icon">sort</span>
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
            <SearchTripCard
              v-for="chuyen in sortedTrips"
              :key="chuyen.id"
              :chuyen="chuyen"
              @select="openTripDetail"
            />
          </div>
        </main>
      </div>
    </div>

    <!-- Modal chi tiết chuyến xe -->
    <SearchTripDetailModal
      :show="showModal"
      :selected-trip="selectedTrip"
      :rating-loading="ratingLoading"
      :trip-header-avg-score="tripHeaderAvgScore"
      :trip-header-total-ratings="tripHeaderTotalRatings"
      :trip-header-star-fill="tripHeaderStarFill"
      :grouped-ratings="groupedRatings"
      :detail-ratings="detailRatings"
      @close="closeModal"
      @book="handleBookTicket"
      @open-rating="openRatingDetail"
    />

    <!-- Modal chi tiết đánh giá -->
    <SearchTripRatingModal
      :show="showRatingDetailModal"
      :selected-rating-group="selectedRatingGroup"
      @close="showRatingDetailModal = false"
    />
  </div>
</template>

<style scoped>
.search-page {
  min-height: 100vh;
  background: #f8fafc;
  font-family: "Manrope", sans-serif;
}

.search-body {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
}

.search-layout {
  display: flex;
  gap: 2rem;
  padding-top: 2rem;
}

@media (max-width: 1024px) {
  .search-layout {
    flex-direction: column;
    padding-top: 1rem;
  }
}

.search-results {
  flex: 1;
  min-width: 0;
}

.search-results__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.search-results__count {
  font-size: 0.95rem;
  color: #64748b;
}

.search-results__count strong {
  color: #0f172a;
}

.search-results__sort {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: #fff;
  padding: 0.5rem 1rem;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
}

.search-results__sort-icon {
  color: #94a3b8;
  font-size: 1.25rem;
}

.search-results__sort-select {
  border: none;
  background: none;
  font-size: 0.9rem;
  font-weight: 700;
  color: #0f172a;
  outline: none;
  cursor: pointer;
}

.search-results__loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 5rem 0;
  gap: 1.5rem;
  color: #64748b;
}

.search-results__spinner {
  width: 48px;
  height: 48px;
  border: 4px solid #f1f5f9;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.search-results__empty {
  text-align: center;
  padding: 5rem 2rem;
  background: #fff;
  border-radius: 32px;
  border: 1px solid #f1f5f9;
}

.search-results__empty-icon {
  width: 80px;
  height: 80px;
  background: #f8fafc;
  border-radius: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
}

.search-results__empty-icon .material-symbols-outlined {
  font-size: 2.5rem;
  color: #94a3b8;
}

.search-results__empty h3 {
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.75rem;
}

.search-results__empty p {
  color: #64748b;
  max-width: 400px;
  margin: 0 auto 2rem;
  line-height: 1.6;
}

.search-results__back-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  background: #3b82f6;
  color: #fff;
  border: none;
  padding: 1rem 2rem;
  border-radius: 16px;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.2s;
}

.search-results__back-btn:hover {
  background: #2563eb;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.search-results__list {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.search-filter-toggle {
  display: none;
  width: 100%;
  background: #fff;
  border: 1px solid #e2e8f0;
  padding: 1rem;
  border-radius: 16px;
  font-weight: 700;
  color: #0f172a;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
  cursor: pointer;
}

.search-filter-toggle__badge {
  width: 8px;
  height: 8px;
  background: #3b82f6;
  border-radius: 50%;
}

@media (max-width: 1024px) {
  .search-filter-toggle {
    display: flex;
  }
}
</style>
