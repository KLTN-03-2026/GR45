<script setup>
import { ref, computed, watch, onMounted } from "vue";
import axiosClient from "@/api/axiosClient";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from "chart.js";
import { Line } from "vue-chartjs";

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
);

const props = defineProps({
  /** Đường dẫn axiosClient từ `/v1/...`, ví dụ `/v1/admin/ho-tro/khach-hang/stats-daily` */
  apiPath: { type: String, required: true },
  /** Gợi ý phạm vi: admin = toàn hệ thống; nhà xe = chỉ nhà xe đăng nhập */
  scopeHint: { type: String, default: "" },
  /** Nhãn dataset 1–3 */
  labels: {
    type: Array,
    default: () => [
      "Tổng phiên",
      "Đang mở",
      "Phiên đã xử lý",
    ],
  },
  keys: {
    type: Array,
    default: () => ["total", "open_sessions", "in_resolved_sessions"],
  },
});

const dateFrom = ref("");
const dateTo = ref("");
const daily = ref([]);
const loading = ref(false);
const loadError = ref("");

function padRange() {
  const to = new Date();
  const from = new Date(to);
  from.setDate(from.getDate() - 6);
  const iso = (d) => {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };
  dateFrom.value = iso(from);
  dateTo.value = iso(to);
}

async function load() {
  loadError.value = "";
  loading.value = true;
  try {
    const res = await axiosClient.get(props.apiPath, {
      params: {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
      },
    });
    // axiosClient interceptor trả về body JSON. BE đóng gói `{ success, data: { daily } }`,
    // nhưng nhận thêm shape `{ daily }` nếu BE đổi format sau này.
    const rows = Array.isArray(res?.data?.daily)
      ? res.data.daily
      : Array.isArray(res?.daily)
        ? res.daily
        : [];
    daily.value = rows;
  } catch (e) {
    daily.value = [];
    loadError.value =
      e?.response?.data?.message ||
      e?.message ||
      "Không tải được biểu đồ.";
  } finally {
    loading.value = false;
  }
}

const allZeroData = computed(() => {
  if (!daily.value.length) return false;
  return daily.value.every((r) => {
    return props.keys.every((k) => Number(r?.[k]) === 0);
  });
});

/**
 * Tổng cộng theo từng dataset trên cả khoảng ngày đang xem.
 *
 * Chart 200px với 7 ngày + 1 ngày spike trông như đường thẳng — user khó nhận ra
 * có dữ liệu hay không. Bảng số này hiển thị tổng tuyệt đối kế trên chart để
 * khỏi bị nhầm "chart hiện 0".
 */
const totalsByLabel = computed(() => {
  return props.keys.map((key, idx) => ({
    label: props.labels[idx] ?? key,
    value: daily.value.reduce((sum, r) => sum + (Number(r?.[key]) || 0), 0),
  }));
});

function rowLabels(rows) {
  return rows.map((r) => {
    const parts = String(r.date || "").split("-");
    if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
    return String(r.date || "");
  });
}

const chartData = computed(() => ({
  labels: rowLabels(daily.value),
  datasets: props.keys.map((key, index) => {
    const colors = [
      ["rgba(37, 99, 235, 1)", "rgba(37, 99, 235, 0.08)"],
      ["rgba(245, 158, 11, 1)", "rgba(245, 158, 11, 0.06)"],
      ["rgba(22, 163, 74, 1)", "rgba(22, 163, 74, 0.06)"],
    ];
    return {
      label: props.labels[index] ?? key,
      data: daily.value.map((r) => Number(r[key]) || 0),
      borderColor: colors[index]?.[0] ?? "rgba(100, 116, 139, 1)",
      backgroundColor: colors[index]?.[1] ?? "rgba(100, 116, 139, 0.06)",
      tension: 0.35,
      fill: true,
      pointRadius: 3,
    };
  }),
}));

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: true,
      position: "bottom",
      labels: { boxWidth: 12, font: { size: 11 } },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { font: { size: 11 }, color: "#64748b" },
    },
    y: {
      beginAtZero: true,
      // suggestedMax giữ trục y có chiều cao khi tất cả series = 0 (lines vẫn thấy
      // rõ tại baseline, không bị Chart.js render thành dải mờ sát đáy).
      suggestedMax: allZeroData.value ? 4 : undefined,
      ticks: { font: { size: 11 }, color: "#64748b", precision: 0 },
      grid: { color: "rgba(148, 163, 184, 0.25)" },
    },
  },
}));

watch(
  () => props.apiPath,
  () => {
    void load();
  },
);

onMounted(() => {
  padRange();
  void load();
});

defineExpose({ reload: load, padRange });
</script>

<template>
  <article class="stats-chart-card card border-0 shadow-sm rounded-4 mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
          <h6 class="fw-bold text-primary mb-0 small text-uppercase tracking-wide">
            Thống kê phiên live chat theo ngày
          </h6>
          <span v-if="scopeHint" class="text-muted small d-block">{{ scopeHint }}</span>
          <span class="text-muted small">Chọn khoảng ngày để xem biểu đồ</span>
        </div>
        <div class="d-flex flex-wrap align-items-end gap-2">
          <label class="small text-muted mb-0">
            Từ
            <input v-model="dateFrom" type="date" class="form-control form-control-sm" />
          </label>
          <label class="small text-muted mb-0">
            Đến
            <input v-model="dateTo" type="date" class="form-control form-control-sm" />
          </label>
          <button
            type="button"
            class="btn btn-sm btn-primary"
            :disabled="loading"
            @click="load"
          >
            Xem
          </button>
        </div>
      </div>
      <p v-if="loadError" class="text-danger small mb-2">{{ loadError }}</p>
      <div
        v-if="daily.length && !loading && !loadError"
        class="stats-totals d-flex flex-wrap gap-3 mb-2"
      >
        <div
          v-for="(t, i) in totalsByLabel"
          :key="i"
          class="stats-total-pill"
          :class="`stats-total-pill--ds${i}`"
        >
          <span class="stats-total-pill__label">{{ t.label }}</span>
          <strong class="stats-total-pill__value">{{ t.value }}</strong>
        </div>
      </div>
      <p
        v-if="allZeroData && !loading && !loadError"
        class="text-muted small mb-2"
      >
        Chưa có hoạt động trong khoảng này — biểu đồ hiển thị mức 0.
      </p>
      <div class="chart-shell position-relative">
        <div v-if="loading" class="chart-loading position-absolute top-50 start-50 translate-middle">
          <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        </div>
        <Line
          v-if="daily.length"
          :data="chartData"
          :options="chartOptions"
        />
        <p v-else-if="!loading" class="text-muted small text-center py-5 mb-0">
          Chưa có dữ liệu trong khoảng đã chọn.
        </p>
      </div>
    </div>
  </article>
</template>

<style scoped>
.chart-shell {
  height: 220px;
  width: 100%;
}
.stats-chart-card {
  background: #fff;
}
.stats-totals {
  align-items: center;
}
.stats-total-pill {
  display: inline-flex;
  flex-direction: column;
  padding: 0.4rem 0.85rem;
  border-radius: 0.6rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  min-width: 7rem;
  line-height: 1.2;
}
.stats-total-pill__label {
  font-size: 0.7rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}
.stats-total-pill__value {
  font-size: 1.1rem;
  font-weight: 700;
  color: #0f172a;
}
.stats-total-pill--ds0 {
  border-left: 4px solid rgba(37, 99, 235, 1);
}
.stats-total-pill--ds1 {
  border-left: 4px solid rgba(245, 158, 11, 1);
}
.stats-total-pill--ds2 {
  border-left: 4px solid rgba(22, 163, 74, 1);
}
</style>
