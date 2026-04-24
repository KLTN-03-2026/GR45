<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Star, Search, ListChecks, ThumbsUp, ThumbsDown } from "lucide-vue-next";
import operatorApi from "@/api/operatorApi.js";
import adminApi from "@/api/adminApi.js";
import BaseTable from "@/components/common/BaseTable.vue";
import BaseInput from "@/components/common/BaseInput.vue";
import BaseButton from "@/components/common/BaseButton.vue";
import {
  formatDateTime,
  formatDateOnly,
  formatTimeOnly,
} from "@/utils/format.js";

const ratingTableColumns = [
  { key: "id", label: "ID" },
  { key: "tuyen", label: "Tuyến đường" },
  { key: "khach", label: "Khách hàng" },
  { key: "sao", label: "Đánh giá" },
  { key: "ngay", label: "Ngày gửi" },
  { key: "nhan_xet", label: "Nhận xét" },
];

const props = defineProps({
  role: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    default: "Đánh giá chuyến xe",
  },
});

const loading = ref(false);
const ratings = ref([]);
const ratingFilter = ref("all");
const searchQuery = ref("");
const errorMessage = ref("");

const detailOpen = ref(false);
const selectedRating = ref(null);

const fetchRatings = async () => {
  loading.value = true;
  errorMessage.value = "";
  try {
    const api = props.role === "admin" ? adminApi : operatorApi;
    const res = await api.getRatings();
    const list =
      res?.data?.ratings ?? res?.ratings ?? (Array.isArray(res?.data) ? res.data : []);
    ratings.value = Array.isArray(list) ? list : [];
  } catch (error) {
    ratings.value = [];
    errorMessage.value =
      error?.response?.data?.message || "Không thể tải dữ liệu đánh giá.";
  } finally {
    loading.value = false;
  }
};

const chuyenOf = (r) => r?.chuyen_xe || r?.chuyenXe || null;
const tuyenOf = (r) => {
  const c = chuyenOf(r);
  return c?.tuyen_duong || c?.tuyenDuong || null;
};

const routeLine = (r) => {
  const t = tuyenOf(r);
  const from = t?.diem_bat_dau || t?.diemBatDau || "—";
  const to = t?.diem_ket_thuc || t?.diemKetThuc || "—";
  if (from === "—" && to === "—" && (t?.ten_tuyen_duong || t?.tenTuyenDuong)) {
    return t.ten_tuyen_duong || t.tenTuyenDuong;
  }
  return `${from} → ${to}`;
};

const customerName = (r) =>
  (r?.khach_hang?.ho_va_ten || r?.khachHang?.ho_va_ten || "").trim() ||
  "Khách hàng";

const filteredRatings = computed(() => {
  let data = [...ratings.value];
  if (ratingFilter.value !== "all") {
    const score = Number(ratingFilter.value);
    data = data.filter((r) => Number(r?.diem_so || 0) === score);
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase();
    data = data.filter((r) => {
      const name = customerName(r).toLowerCase();
      const comment = (r?.noi_dung || "").toLowerCase();
      const route = routeLine(r).toLowerCase();
      const tenTuyen = (
        tuyenOf(r)?.ten_tuyen_duong ||
        tuyenOf(r)?.tenTuyenDuong ||
        ""
      ).toLowerCase();
      return (
        name.includes(q) ||
        comment.includes(q) ||
        route.includes(q) ||
        tenTuyen.includes(q)
      );
    });
  }
  return data;
});

const averageRating = computed(() => {
  if (!ratings.value.length) return 0;
  return (
    ratings.value.reduce((s, r) => s + Number(r?.diem_so || 0), 0) /
    ratings.value.length
  );
});

const positiveCount = computed(() =>
  ratings.value.filter((r) => Number(r?.diem_so || 0) >= 4).length,
);

const negativeCount = computed(() =>
  ratings.value.filter((r) => Number(r?.diem_so || 0) <= 2).length,
);

/** KPI phía trên bảng — cùng layout Tổng quan hệ thống (DashboardView nhà xe) */
const ratingStatCards = computed(() => {
  const n = ratings.value.length;
  const avg = averageRating.value;
  return [
    {
      id: "avg",
      label: "Điểm trung bình",
      value: n ? avg.toFixed(1) : "—",
      unit: "/5",
      sub: n ? "Trên tất cả đánh giá đã tải" : "Chưa có đánh giá",
      icon: Star,
      bg: "linear-gradient(135deg, #f59e0b 0%, #d97706 100%)",
    },
    {
      id: "total",
      label: "Tổng đánh giá",
      value: String(n),
      unit: "lượt",
      sub: "Số bản ghi trong hệ thống",
      icon: ListChecks,
      bg: "linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)",
    },
    {
      id: "pos",
      label: "Tích cực (4–5★)",
      value: String(positiveCount.value),
      unit: "lượt",
      sub: "Khách đánh giá cao",
      icon: ThumbsUp,
      bg: "linear-gradient(135deg, #22c55e 0%, #16a34a 100%)",
    },
    {
      id: "neg",
      label: "Tiêu cực (1–2★)",
      value: String(negativeCount.value),
      unit: "lượt",
      sub: "Cần chú ý chất lượng",
      icon: ThumbsDown,
      bg: "linear-gradient(135deg, #ef4444 0%, #dc2626 100%)",
    },
  ];
});

const formatListDate = (value) => {
  if (!value) return "";
  try {
    return new Date(value).toLocaleDateString("vi-VN");
  } catch {
    return value;
  }
};

const clampScore = (raw) => {
  const n = Math.round(Number(raw));
  if (Number.isNaN(n)) return 0;
  return Math.min(5, Math.max(0, n));
};

const subStars = (raw) => {
  if (raw == null || raw === "") return 0;
  return clampScore(raw);
};

const subLabel = (raw) => {
  if (raw == null || raw === "") return "—";
  return `${clampScore(raw)}/5`;
};

const openDetail = (r) => {
  selectedRating.value = r;
  detailOpen.value = true;
};

const isAdmin = computed(() => props.role === "admin");

const ratingTableRows = computed(() =>
  filteredRatings.value.map((r) => {
    const t = tuyenOf(r);
    return {
      _rating: r,
      id: r.id,
      ten_tuyen: t?.ten_tuyen_duong || t?.tenTuyenDuong || "",
      path_from: t?.diem_bat_dau || t?.diemBatDau || "—",
      path_to: t?.diem_ket_thuc || t?.diemKetThuc || "—",
      khach: customerName(r),
      sao: clampScore(r.diem_so),
      ngay: formatListDate(r.created_at),
      nhan_xet: (r.noi_dung || "").trim(),
    };
  }),
);

const onRatingTableRowClick = (row) => {
  if (row?._rating) openDetail(row._rating);
};

const tripContext = computed(() => {
  const r = selectedRating.value;
  if (!r) return null;
  const c = chuyenOf(r);
  const t = tuyenOf(r);
  const xe = c?.xe || c?.Xe;
  const nx = xe?.nha_xe || xe?.nhaXe;
  if (!c && !t) return null;
  return {
    chuyenId: c?.id ?? r?.id_chuyen_xe ?? "—",
    trangThai: c?.trang_thai ?? c?.trangThai,
    ngayKH: c?.ngay_khoi_hanh ?? c?.ngayKhoiHanh,
    gioKH: c?.gio_khoi_hanh ?? c?.gioKhoiHanh,
    tenTuyen: t?.ten_tuyen_duong || t?.tenTuyenDuong || "—",
    diemDi: t?.diem_bat_dau || t?.diemBatDau || "—",
    diemDen: t?.diem_ket_thuc || t?.diemKetThuc || "—",
    bienSo: xe?.bien_so || xe?.bienSo,
    tenXe: xe?.ten_xe || xe?.tenXe,
    tenNhaXe: nx?.ten_nha_xe || nx?.tenNhaXe,
  };
});

const khachContext = computed(() => {
  const r = selectedRating.value;
  if (!r) return null;
  const k = r.khach_hang || r.khachHang;
  return {
    ten: customerName(r),
    email: k?.email || "—",
    sdt: k?.so_dien_thoai || "—",
  };
});

onMounted(fetchRatings);

const onRatingDetailKeydown = (e) => {
  if (e.key === "Escape" && detailOpen.value) {
    detailOpen.value = false;
  }
};

watch(detailOpen, (open) => {
  document.body.style.overflow = open ? "hidden" : "";
  if (open) document.addEventListener("keydown", onRatingDetailKeydown);
  else document.removeEventListener("keydown", onRatingDetailKeydown);
});

onUnmounted(() => {
  document.removeEventListener("keydown", onRatingDetailKeydown);
  document.body.style.overflow = "";
});
</script>

<template>
  <div :class="isAdmin ? 'admin-ratings admin-page w-full' : 'ratings-operator'">
    <!-- —— Admin: cùng pattern Quản lý nhà xe / khách hàng —— -->
    <template v-if="isAdmin">
      <div class="admin-ratings-header">
        <div class="admin-ratings-header__left">
          <div class="header-icon-wrap">
            <Star class="header-icon" />
          </div>
          <div>
            <h1 class="page-title admin-ratings-header__title">{{ title }}</h1>
            <p class="admin-ratings-header__sub">
              Theo dõi đánh giá toàn hệ thống, xem chuyến — khách — nhận xét chi tiết.
            </p>
          </div>
        </div>
      </div>

      <div class="stat-grid stat-grid--ratings">
        <div
          v-for="card in ratingStatCards"
          :key="card.id"
          class="stat-card"
          :style="{ '--card-gradient': card.bg }"
        >
          <div class="stat-icon" :style="{ background: card.bg }">
            <component :is="card.icon" class="stat-icon-svg" />
          </div>
          <div class="stat-info">
            <p class="stat-label">{{ card.label }}</p>
            <h2 class="stat-value">
              {{ card.value }} <span class="stat-unit">{{ card.unit }}</span>
            </h2>
            <p class="stat-sub">{{ card.sub }}</p>
          </div>
        </div>
      </div>

      <div class="filter-card admin-filter-card">
        <div class="admin-filter-row">
          <div class="admin-filter-field admin-filter-field--search">
            <label class="filter-label" for="admin-ratings-search">Tìm kiếm</label>
            <div class="admin-search-wrap">
              <Search class="admin-search-icon" :size="18" aria-hidden="true" />
              <input
                id="admin-ratings-search"
                v-model="searchQuery"
                type="search"
                class="admin-search-input"
                placeholder="Khách hàng, tuyến, nhận xét…"
                autocomplete="off"
              />
            </div>
          </div>
          <div class="admin-filter-field admin-filter-field--stars">
            <label class="filter-label" for="admin-ratings-stars">Số sao</label>
            <select id="admin-ratings-stars" v-model="ratingFilter" class="custom-select admin-star-select">
              <option value="all">Tất cả số sao</option>
              <option value="5">5 sao</option>
              <option value="4">4 sao</option>
              <option value="3">3 sao</option>
              <option value="2">2 sao</option>
              <option value="1">1 sao</option>
            </select>
          </div>
        </div>
      </div>

      <div v-if="errorMessage" class="alert-admin-error mb-3">
        {{ errorMessage }}
      </div>

      <div class="table-card">
        <BaseTable
          :columns="ratingTableColumns"
          :data="ratingTableRows"
          :loading="loading"
          @row-click="onRatingTableRowClick"
        >
          <template #cell(tuyen)="{ item }">
            <div class="route-cell">
              <span v-if="item.ten_tuyen" class="route-name route-name--admin">{{
                item.ten_tuyen
              }}</span>
              <span class="route-path"
                >{{ item.path_from }} → {{ item.path_to }}</span
              >
            </div>
          </template>
          <template #cell(khach)="{ value }">
            <span class="driver-name">{{ value }}</span>
          </template>
          <template #cell(sao)="{ item }">
            <div class="rating-stars-cell">
              <span
                v-for="s in 5"
                :key="`${item.id}-adm-s-${s}`"
                class="rating-star-glyph"
                :class="{ 'rating-star-glyph--on': s <= item.sao }"
                >★</span
              >
              <span class="rating-score-pill">{{ item.sao }}/5</span>
            </div>
          </template>
          <template #cell(ngay)="{ value }">
            <div class="date-cell">
              <span class="date-main">{{ value }}</span>
            </div>
          </template>
          <template #cell(nhan_xet)="{ value }">
            <span class="nx-preview" :title="value">{{ value || "—" }}</span>
          </template>
        </BaseTable>
      </div>
    </template>

    <!-- —— Nhà xe: cùng layout Quản lý Chuyến Xe (operator) —— -->
    <template v-else>
      <div class="operator-page">
        <div class="page-header">
          <div>
            <h1 class="page-title">{{ title }}</h1>
            <p class="page-sub">
              Xem đánh giá của khách sau chuyến; bấm một dòng để mở chi tiết.
            </p>
          </div>
        </div>

        <div class="stat-grid stat-grid--ratings">
          <div
            v-for="card in ratingStatCards"
            :key="card.id"
            class="stat-card"
            :style="{ '--card-gradient': card.bg }"
          >
            <div class="stat-icon" :style="{ background: card.bg }">
              <component :is="card.icon" class="stat-icon-svg" />
            </div>
            <div class="stat-info">
              <p class="stat-label">{{ card.label }}</p>
              <h2 class="stat-value">
                {{ card.value }} <span class="stat-unit">{{ card.unit }}</span>
              </h2>
              <p class="stat-sub">{{ card.sub }}</p>
            </div>
          </div>
        </div>

        <div class="filter-card">
          <div class="filter-row">
            <div class="search-box">
              <BaseInput
                v-model="searchQuery"
                placeholder="Tìm khách hàng, tuyến, nhận xét..."
                @keyup.enter="fetchRatings"
              />
              <BaseButton variant="secondary" @click="fetchRatings">Tìm</BaseButton>
            </div>
            <div class="filter-group">
              <label class="filter-label">Số sao</label>
              <select v-model="ratingFilter" class="custom-select">
                <option value="all">Tất cả</option>
                <option value="5">5 sao</option>
                <option value="4">4 sao</option>
                <option value="3">3 sao</option>
                <option value="2">2 sao</option>
                <option value="1">1 sao</option>
              </select>
            </div>
            <BaseButton
              variant="outline"
              @click="
                searchQuery = '';
                ratingFilter = 'all';
                fetchRatings();
              "
            >
              Đặt lại
            </BaseButton>
          </div>
        </div>

        <div v-if="errorMessage" class="rating-error-banner">
          {{ errorMessage }}
        </div>

        <div class="table-card">
          <BaseTable
            :columns="ratingTableColumns"
            :data="ratingTableRows"
            :loading="loading"
            @row-click="onRatingTableRowClick"
          >
            <template #cell(tuyen)="{ item }">
              <div class="route-cell">
                <span v-if="item.ten_tuyen" class="route-name">{{ item.ten_tuyen }}</span>
                <span class="route-path">{{ item.path_from }} → {{ item.path_to }}</span>
              </div>
            </template>
            <template #cell(khach)="{ value }">
              <span class="driver-name">{{ value }}</span>
            </template>
            <template #cell(sao)="{ item }">
              <div class="rating-stars-cell">
                <span
                  v-for="s in 5"
                  :key="`${item.id}-op-s-${s}`"
                  class="rating-star-glyph"
                  :class="{ 'rating-star-glyph--on': s <= item.sao }"
                  >★</span
                >
                <span class="rating-score-pill rating-score-pill--op">{{ item.sao }}/5</span>
              </div>
            </template>
            <template #cell(ngay)="{ value }">
              <div class="date-cell">
                <span class="date-main">{{ value }}</span>
              </div>
            </template>
            <template #cell(nhan_xet)="{ value }">
              <span class="nx-preview" :title="value">{{ value || "—" }}</span>
            </template>
          </BaseTable>
        </div>
      </div>
    </template>

    <Teleport to="body">
      <Transition name="rt-rating-modal-fade">
        <div
          v-if="detailOpen && selectedRating"
          class="trip-rating-detail-overlay"
          role="presentation"
          @click.self="detailOpen = false"
        >
          <div
            class="trip-rating-detail-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rt-rating-detail-title"
          >
            <div class="trip-rating-detail-header">
              <h3 id="rt-rating-detail-title">Chi tiết đánh giá</h3>
              <button type="button" class="trip-rating-detail-close" @click="detailOpen = false">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path
                    d="M18 6L6 18M6 6L18 18"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </div>
            <div class="trip-rating-detail-body">
              <div v-if="tripContext" class="trip-rating-detail-trip">
                <div class="trip-rating-detail-trip-title">Chuyến xe</div>
                <div class="trip-rating-detail-trip-route">
                  {{ tripContext.diemDi }} → {{ tripContext.diemDen }}
                </div>
                <div class="trip-rating-detail-trip-meta">
                  <span>{{ tripContext.tenTuyen }}</span>
                  <span v-if="tripContext.tenNhaXe"> · {{ tripContext.tenNhaXe }}</span>
                </div>
                <div class="trip-rating-detail-trip-meta">
                  <span>Ngày: {{ formatDateOnly(tripContext.ngayKH) }}</span>
                  <span> · Giờ: {{ formatTimeOnly(tripContext.gioKH) }}</span>
                  <span v-if="tripContext.bienSo"> · Xe: {{ tripContext.bienSo }}</span>
                </div>
                <div v-if="tripContext.tenXe || tripContext.trangThai" class="trip-rating-detail-trip-meta">
                  <span v-if="tripContext.tenXe">Tên xe: {{ tripContext.tenXe }}</span>
                  <span v-if="tripContext.trangThai">
                    <template v-if="tripContext.tenXe"> · </template>Trạng thái: {{ tripContext.trangThai }}
                  </span>
                </div>
                <div class="trip-rating-detail-trip-meta">
                  <span>ID chuyến: #{{ tripContext.chuyenId }}</span>
                </div>
              </div>

              <div class="trip-rating-detail-top">
                <span class="trip-rating-detail-name">{{ customerName(selectedRating) }}</span>
                <div class="trip-rating-detail-stars-row">
                  <span
                    v-for="star in 5"
                    :key="`rt-main-${star}`"
                    class="trip-rating-detail-star"
                    :class="{
                      'trip-rating-detail-star--on': star <= clampScore(selectedRating?.diem_so),
                    }"
                    aria-hidden="true"
                    >★</span
                  >
                  <span class="trip-rating-detail-score">{{
                    clampScore(selectedRating?.diem_so)
                  }}/5</span>
                </div>
              </div>

              <div v-if="khachContext" class="trip-rating-detail-trip">
                <div class="trip-rating-detail-trip-title">Liên hệ & vé</div>
                <div class="trip-rating-detail-trip-meta">
                  SĐT: {{ khachContext.sdt }} · Email: {{ khachContext.email }}
                </div>
                <div class="trip-rating-detail-trip-meta">
                  Mã vé:
                  <strong class="trip-rating-detail-mono">{{ selectedRating.ma_ve || "—" }}</strong>
                  · ID đánh giá: #{{ selectedRating.id }}
                </div>
                <div class="trip-rating-detail-trip-meta">
                  Gửi lúc: {{ formatDateTime(selectedRating.created_at) }}
                  <template
                    v-if="
                      selectedRating.updated_at &&
                      selectedRating.updated_at !== selectedRating.created_at
                    "
                  >
                    · Cập nhật: {{ formatDateTime(selectedRating.updated_at) }}
                  </template>
                </div>
              </div>

              <div class="trip-rating-detail-grid">
                <div class="trip-rating-detail-metric">
                  <span class="trip-rating-detail-metric-label">Dịch vụ</span>
                  <div class="trip-rating-detail-metric-stars">
                    <span
                      v-for="star in 5"
                      :key="`rt-dv-${star}`"
                      class="trip-rating-detail-star trip-rating-detail-star--sm"
                      :class="{
                        'trip-rating-detail-star--on':
                          star <= subStars(selectedRating?.diem_dich_vu),
                      }"
                      aria-hidden="true"
                      >★</span
                    >
                    <strong>{{ subLabel(selectedRating?.diem_dich_vu) }}</strong>
                  </div>
                </div>
                <div class="trip-rating-detail-metric">
                  <span class="trip-rating-detail-metric-label">An toàn</span>
                  <div class="trip-rating-detail-metric-stars">
                    <span
                      v-for="star in 5"
                      :key="`rt-at-${star}`"
                      class="trip-rating-detail-star trip-rating-detail-star--sm"
                      :class="{
                        'trip-rating-detail-star--on':
                          star <= subStars(selectedRating?.diem_an_toan),
                      }"
                      aria-hidden="true"
                      >★</span
                    >
                    <strong>{{ subLabel(selectedRating?.diem_an_toan) }}</strong>
                  </div>
                </div>
                <div class="trip-rating-detail-metric">
                  <span class="trip-rating-detail-metric-label">Sạch sẽ</span>
                  <div class="trip-rating-detail-metric-stars">
                    <span
                      v-for="star in 5"
                      :key="`rt-ss-${star}`"
                      class="trip-rating-detail-star trip-rating-detail-star--sm"
                      :class="{
                        'trip-rating-detail-star--on':
                          star <= subStars(selectedRating?.diem_sach_se),
                      }"
                      aria-hidden="true"
                      >★</span
                    >
                    <strong>{{ subLabel(selectedRating?.diem_sach_se) }}</strong>
                  </div>
                </div>
                <div class="trip-rating-detail-metric">
                  <span class="trip-rating-detail-metric-label">Thái độ</span>
                  <div class="trip-rating-detail-metric-stars">
                    <span
                      v-for="star in 5"
                      :key="`rt-td-${star}`"
                      class="trip-rating-detail-star trip-rating-detail-star--sm"
                      :class="{
                        'trip-rating-detail-star--on':
                          star <= subStars(selectedRating?.diem_thai_do),
                      }"
                      aria-hidden="true"
                      >★</span
                    >
                    <strong>{{ subLabel(selectedRating?.diem_thai_do) }}</strong>
                  </div>
                </div>
              </div>

              <p class="trip-rating-detail-note">
                {{ selectedRating?.noi_dung || "Không có nhận xét." }}
              </p>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* —— Admin skin (align NhaXeView / admin list pages) —— */
.admin-page {
  padding: 1.5rem;
  width: 100%;
}

.admin-ratings-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-bottom: 1.25rem;
}

.admin-ratings-header__left {
  display: flex;
  align-items: center;
  gap: 16px;
  min-width: 0;
}

.admin-ratings-header__title {
  margin: 0;
}

.admin-ratings-header__sub {
  margin: 0;
  font-size: 14px;
  color: #64748b;
  line-height: 1.45;
  max-width: 52rem;
}

.admin-filter-card {
  background: #fff;
  border: 1px solid #e2e8f0;
}

.admin-filter-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 1rem 1.25rem;
}

.admin-filter-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 0;
}

.admin-filter-field--search {
  flex: 1 1 280px;
  min-width: min(100%, 260px);
}

.admin-filter-field--stars {
  flex: 0 0 auto;
  width: 100%;
  max-width: 200px;
}

.admin-search-wrap {
  position: relative;
  width: 100%;
}

.admin-search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
  z-index: 1;
}

.admin-search-input {
  box-sizing: border-box;
  width: 100%;
  height: 40px;
  padding: 0 12px 0 40px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.875rem;
  color: #0f172a;
  background: #fff;
  transition:
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.admin-search-input::placeholder {
  color: #94a3b8;
}

.admin-search-input:hover {
  border-color: #cbd5e1;
}

.admin-search-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.admin-star-select {
  height: 40px;
  width: 100%;
  border-radius: 10px;
}

@media (max-width: 640px) {
  .admin-filter-field--stars {
    max-width: none;
  }
}

.header-icon-wrap {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #fde68a;
  flex-shrink: 0;
}
.header-icon {
  color: #d97706;
  width: 24px;
  height: 24px;
}
.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
}

/* KPI — giống Tổng quan hệ thống (operator/DashboardView) */
.stat-grid.stat-grid--ratings {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 24px;
}

.stat-grid.stat-grid--ratings .stat-card {
  background: white;
  border-radius: 18px;
  padding: 24px;
  box-shadow: 0 2px 16px rgba(0, 0, 0, 0.04);
  display: flex;
  align-items: flex-start;
  gap: 18px;
  transition:
    transform 0.3s ease,
    box-shadow 0.3s ease;
}

.operator-page .stat-grid.stat-grid--ratings .stat-card {
  border: 1px solid #f0fdf4;
}

.admin-ratings .stat-grid.stat-grid--ratings .stat-card {
  border: 1px solid #e2e8f0;
}

.stat-grid.stat-grid--ratings .stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
}

.stat-grid.stat-grid--ratings .stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
}

.stat-grid.stat-grid--ratings .stat-icon-svg {
  width: 28px;
  height: 28px;
  color: white;
}

.stat-grid.stat-grid--ratings .stat-info {
  flex: 1;
  min-width: 0;
}

.stat-grid.stat-grid--ratings .stat-label {
  font-size: 12px;
  color: #64748b;
  margin: 0 0 6px 0;
  font-weight: 500;
}

.stat-grid.stat-grid--ratings .stat-value {
  font-size: 24px;
  font-weight: 800;
  color: #0d4f35;
  margin: 0 0 6px 0;
  line-height: 1;
}

.admin-ratings .stat-grid.stat-grid--ratings .stat-value {
  color: #0f172a;
}

.stat-grid.stat-grid--ratings .stat-unit {
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
}

.stat-grid.stat-grid--ratings .stat-sub {
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
  margin: 0;
  line-height: 1.35;
}

@media (max-width: 1280px) {
  .stat-grid.stat-grid--ratings {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 640px) {
  .stat-grid.stat-grid--ratings {
    grid-template-columns: 1fr;
  }
}

.filter-card {
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(10px);
  border: 1px solid #e2e8f0;
  padding: 1rem;
  border-radius: 12px;
  margin-bottom: 1rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.filter-grid {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  align-items: flex-end;
}
.filter-item {
  flex: 0 1 auto;
  min-width: 140px;
}
.filter-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #475569;
  margin-bottom: 0.4rem;
}
.custom-select {
  width: 100%;
  padding: 0.55rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.875rem;
  color: #1e293b;
  background: #fff;
}
.custom-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
}

.alert-admin-error {
  padding: 0.85rem 1rem;
  border-radius: 10px;
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 0.875rem;
}

.admin-ratings .table-card {
  background: white;
  border-radius: 12px;
  padding: 1rem;
  box-shadow:
    0 10px 15px -3px rgba(0, 0, 0, 0.05),
    0 4px 6px -2px rgba(0, 0, 0, 0.025);
  border: 1px solid rgba(226, 232, 240, 0.8);
}

/* Nhà xe — bố cục / màu giống ChuyenXeView (operator) */
.operator-page {
  font-family: "Inter", system-ui, sans-serif;
}

.operator-page .page-header {
  display: flex;
  justify-content: flex-start;
  align-items: flex-start;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;
}

.operator-page .page-title {
  font-size: 22px;
  font-weight: 800;
  color: #0d4f35;
  margin: 0 0 4px 0;
}

.operator-page .page-sub {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}

.operator-page .filter-card {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(10px);
  border: 1px solid #dcfce7;
  padding: 14px 18px;
  border-radius: 14px;
  margin-bottom: 18px;
  box-shadow: 0 4px 12px rgba(0, 80, 40, 0.04);
}

.operator-page .filter-row {
  display: flex;
  gap: 12px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.operator-page .search-box {
  display: flex;
  gap: 8px;
  align-items: flex-end;
  flex: 1;
  min-width: 280px;
}

.operator-page .search-box > :first-child {
  flex: 1;
  margin-bottom: 0;
}

.operator-page .filter-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.operator-page .filter-label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}

.operator-page .custom-select {
  min-width: 140px;
  padding: 0.55rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.875rem;
  color: #1e293b;
  background: #fff;
}

.operator-page .table-card {
  background: white;
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 4px 20px rgba(0, 80, 40, 0.05);
  border: 1px solid #dcfce7;
  overflow: visible;
}

.rating-error-banner {
  padding: 0.85rem 1rem;
  border-radius: 10px;
  border: 1px solid #fecaca;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 0.875rem;
  margin-bottom: 12px;
}

.route-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.route-name {
  font-size: 13px;
  font-weight: 700;
}

.operator-page .route-name {
  color: #0d4f35;
}

.route-name--admin {
  color: #1e40af;
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

.driver-name {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.rating-stars-cell {
  display: inline-flex;
  align-items: center;
  flex-wrap: nowrap;
  gap: 0 2px;
  white-space: nowrap;
}

.rating-star-glyph {
  font-size: 1rem;
  line-height: 1;
  color: #e2e8f0;
  letter-spacing: 0;
  font-family:
    "Segoe UI Symbol",
    "Apple Color Emoji",
    "Noto Color Emoji",
    system-ui,
    sans-serif;
  display: inline-block;
  width: 1em;
  text-align: center;
}

.rating-star-glyph--on {
  color: #f59e0b;
}

.rating-score-pill {
  margin-left: 6px;
  font-size: 11px;
  font-weight: 800;
  color: #b45309;
  padding: 2px 8px;
  border-radius: 999px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  line-height: 1.35;
}

.rating-score-pill--op {
  color: #14532d;
  background: #ecfdf5;
  border-color: #bbf7d0;
}

.nx-preview {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 12px;
  color: #64748b;
  max-width: 260px;
}

/* Popup chi tiết đánh giá — cùng nhìn SearchTripView (chỉ xem, không gửi) */
.rt-rating-modal-fade-enter-active {
  transition: opacity 0.25s ease;
}
.rt-rating-modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.rt-rating-modal-fade-enter-from,
.rt-rating-modal-fade-leave-to {
  opacity: 0;
}

.trip-rating-detail-overlay {
  position: fixed;
  inset: 0;
  z-index: 10050;
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
  max-height: min(90vh, 640px);
  display: flex;
  flex-direction: column;
}

.trip-rating-detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.trip-rating-detail-header h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 800;
  color: #1e293b;
}

.trip-rating-detail-close {
  border: none;
  background: transparent;
  cursor: pointer;
  color: #64748b;
  padding: 0.2rem;
  line-height: 0;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.trip-rating-detail-close:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.trip-rating-detail-body {
  padding: 0.9rem 1rem 1rem;
  overflow-y: auto;
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

.trip-rating-detail-mono {
  font-family: ui-monospace, monospace;
  font-weight: 600;
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
  white-space: pre-wrap;
}
</style>
