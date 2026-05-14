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
      "Tổng tin nhắn",
      "Đã đóng (user)",
      "Đã resolve",
    ],
  },
  keys: {
    type: Array,
    default: () => ["total", "in_closed_sessions", "in_resolved_sessions"],
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
  const iso = (d) => d.toISOString().slice(0, 10);
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
    daily.value = Array.isArray(res?.data?.daily) ? res.data.daily : [];
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

function rowLabels(rows) {
  return rows.map((r) => {
    const parts = String(r.date || "").split("-");
    if (parts.length === 3) return `${parts[2]}/${parts[1]}`;
    return String(r.date || "");
  });
}

const chartData = computed(() => ({
  labels: rowLabels(daily.value),
  datasets: [
    {
      label: props.labels[0],
      data: daily.value.map((r) => Number(r[props.keys[0]]) || 0),
      borderColor: "rgba(37, 99, 235, 1)",
      backgroundColor: "rgba(37, 99, 235, 0.08)",
      tension: 0.35,
      fill: true,
      pointRadius: 3,
    },
    {
      label: props.labels[1],
      data: daily.value.map((r) => Number(r[props.keys[1]]) || 0),
      borderColor: "rgba(245, 158, 11, 1)",
      backgroundColor: "rgba(245, 158, 11, 0.06)",
      tension: 0.35,
      fill: true,
      pointRadius: 3,
    },
    {
      label: props.labels[2],
      data: daily.value.map((r) => Number(r[props.keys[2]]) || 0),
      borderColor: "rgba(22, 163, 74, 1)",
      backgroundColor: "rgba(22, 163, 74, 0.06)",
      tension: 0.35,
      fill: true,
      pointRadius: 3,
    },
  ],
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
            Thống kê tin nhắn theo ngày
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
  height: 200px;
  width: 100%;
}
.stats-chart-card {
  background: #fff;
}
</style>
