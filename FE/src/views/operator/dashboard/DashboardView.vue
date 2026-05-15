<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { RouterLink } from "vue-router";
import Echo from "laravel-echo";
import { buildLaravelEchoTransportOptions } from "@/utils/echo.js";
import {
  DollarSign,
  Ticket,
  BusFront,
  ShieldAlert,
  TrendingUp,
  BellRing,
  ArrowRight,
  TriangleAlert,
  Radio,
  Wallet,
  MapPin,
  Clock3,
  AlertCircle,
  Phone,
} from "lucide-vue-next";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from "chart.js";
import { Line, Doughnut } from "vue-chartjs";
import operatorApi from "@/api/operatorApi";
import { useOperatorStore } from "@/stores/operatorStore.js";

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler,
);

const store = useOperatorStore();
const now = ref(new Date());
const loading = ref(true);

// Focus mode - nhấp nháy đỏ khi tài xế ngủ gật >= 3 lần trong 10 phút
const focusMode = ref(false);
const focusDriver = ref("");
const drowsyTracker = ref({}); // { taiXeId: [timestamp1, timestamp2, ...] }

// Withdraw modal
const showWithdraw = ref(false);
const withdrawAmount = ref("");
const withdrawing = ref(false);
const withdrawMsg = ref("");

// AI blink
const aiBlinkVisible = ref(false);
const aiBlinkMsg = ref("");
let aiBlinkTimer = null;

// Data
const vi = ref({
  so_du: 0,
  han_muc_toi_thieu: 2000000,
  co_the_rut: 0,
  ngan_hang: null,
  so_tai_khoan: null,
});
const chuyenDangChay = ref(0);
const veMoiHomNay = ref(0);
const viPham = ref({
  tong: 0,
  chi_tiet: { ngu_gat: 0, su_dung_dien_thoai: 0, hut_thuoc: 0, khac: 0 },
});
const dt7 = ref([]);
const suCo = ref([]);
const txNguyCo = ref([]);
const xeChay = ref([]);

let echoInst = null,
  clockTimer = null;
const hasWs = ref(false);

const fmt = (n) => {
  if (!n) return "0đ";
  if (n >= 1e9) return (n / 1e9).toFixed(2) + " tỷ";
  if (n >= 1e6) return (n / 1e6).toFixed(1) + " tr";
  return n.toLocaleString("vi-VN") + "đ";
};
const fmtTime = (t) => (t ? new Date(t).toLocaleTimeString("vi-VN") : "--:--");
const dateText = computed(() =>
  now.value.toLocaleString("vi-VN", {
    weekday: "long",
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  }),
);

const loaiLabel = (l) =>
  ({
    ngu_gat: "Ngủ gật",
    su_dung_dien_thoai: "Dùng ĐT",
    hut_thuoc: "Hút thuốc",
    qua_toc_do: "Quá tốc độ",
    bao_dong_khan_cap: "SOS",
    vi_pham_khac: "Khác",
  })[l] || l;
const mucDoClass = (m) =>
  ({
    khan_cap: "badge-red",
    nguy_hiem: "badge-orange",
    canh_bao: "badge-yellow",
  })[m] || "badge-blue";
const mucDoLabel = (m) =>
  ({ khan_cap: "Khẩn cấp", nguy_hiem: "Nguy hiểm", canh_bao: "Cảnh báo" })[m] ||
  "Thông tin";

// Charts
const lineData = computed(() => ({
  labels: dt7.value.map((i) => {
    const d = new Date(i.ngay);
    return `${d.getDate()}/${d.getMonth() + 1}`;
  }),
  datasets: [
    {
      label: "Doanh thu",
      data: dt7.value.map((i) => +(Number(i.doanh_thu) / 1e6).toFixed(1)),
      borderColor: "#22c55e",
      backgroundColor: "rgba(34,197,94,.08)",
      borderWidth: 3,
      pointRadius: 5,
      tension: 0.4,
      fill: true,
    },
  ],
}));
const lineOpts = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    y: { beginAtZero: true, grid: { color: "rgba(0,0,0,.04)" } },
    x: { grid: { display: false } },
  },
};
const pieData = computed(() => {
  const c = viPham.value.chi_tiet;
  return {
    labels: ["Ngủ gật", "Dùng ĐT", "Hút thuốc", "Khác"],
    datasets: [
      {
        data: [c.ngu_gat, c.su_dung_dien_thoai, c.hut_thuoc, c.khac],
        backgroundColor: ["#ef4444", "#f59e0b", "#8b5cf6", "#64748b"],
        borderWidth: 0,
      },
    ],
  };
});
const pieOpts = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: "65%",
  plugins: {
    legend: {
      position: "bottom",
      labels: { padding: 10, usePointStyle: true },
    },
  },
};

const fetchKpis = async () => {
  loading.value = true;
  try {
    const res = await operatorApi.getDashboardKpis();
    const d = res?.data?.data ?? res?.data ?? {};
    if (d.vi_nha_xe) vi.value = { ...vi.value, ...d.vi_nha_xe };
    chuyenDangChay.value = d.chuyen_xe_dang_chay ?? 0;
    veMoiHomNay.value = d.ve_moi_hom_nay ?? 0;
    if (d.vi_pham_ai) viPham.value = { ...viPham.value, ...d.vi_pham_ai };
    dt7.value = d.doanh_thu_7_ngay ?? [];
    suCo.value = d.su_co_moi_nhat ?? [];
    txNguyCo.value = d.tai_xe_nguy_co ?? [];
    xeChay.value = d.xe_dang_chay ?? [];
  } catch (e) {
    console.warn("Dashboard KPIs error:", e?.message);
  }
  loading.value = false;
};

// Focus mode: track drowsy violations per driver
const checkFocusMode = (taiXeId, tenTaiXe) => {
  const now = Date.now();
  if (!drowsyTracker.value[taiXeId]) drowsyTracker.value[taiXeId] = [];
  drowsyTracker.value[taiXeId].push(now);
  // Keep only events within last 10 minutes
  drowsyTracker.value[taiXeId] = drowsyTracker.value[taiXeId].filter(
    (t) => now - t < 600000,
  );
  if (drowsyTracker.value[taiXeId].length >= 3) {
    focusMode.value = true;
    focusDriver.value = tenTaiXe || `Tài xế #${taiXeId}`;
  }
};

const dismissFocus = () => {
  focusMode.value = false;
  focusDriver.value = "";
};

const onRealtime = (payload) => {
  const loai = payload?.loai_bao_dong || payload?.loai || "";
  const bien = payload?.bien_so || "";
  const ten = payload?.ten_tai_xe || "";
  const txId = payload?.id_tai_xe;
  const c = { ...viPham.value.chi_tiet };
  if (loai === "ngu_gat") {
    c.ngu_gat++;
    if (txId) checkFocusMode(txId, ten);
  } else if (loai === "su_dung_dien_thoai") c.su_dung_dien_thoai++;
  else if (loai === "hut_thuoc") c.hut_thuoc++;
  else c.khac++;
  viPham.value = { tong: viPham.value.tong + 1, chi_tiet: c };
  suCo.value = [
    {
      id: Date.now(),
      loai_bao_dong: loai,
      muc_do: payload?.muc_do || "canh_bao",
      trang_thai: "moi",
      ten_tai_xe: ten,
      bien_so: bien,
      anh_url: payload?.anh_url || null,
      created_at: new Date().toISOString(),
    },
    ...suCo.value,
  ].slice(0, 5);
  if (loai === "ngu_gat" || loai === "bao_dong_khan_cap") {
    aiBlinkMsg.value = `⚠ ${bien} — ${ten || "Tài xế"}: ${loaiLabel(loai)}`;
    aiBlinkVisible.value = true;
    clearTimeout(aiBlinkTimer);
    aiBlinkTimer = setTimeout(() => {
      aiBlinkVisible.value = false;
    }, 8000);
  }
};

const initWs = () => {
  if (!store.token) return;
  try {
    const transport = buildLaravelEchoTransportOptions();
    if (!transport) return;
    let url =
      import.meta.env.VITE_API_URL || "https://api.bussafe.io.vn/api/";
    if (!url.endsWith("/")) url += "/";
    echoInst = new Echo({
      ...transport,
      authEndpoint: `${url}v1/nha-xe/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${store.token}`,
          Accept: "application/json",
          "ngrok-skip-browser-warning": "true",
        },
      },
    });
    const maNhaXe =
      store.user?.nhaXe?.ma_nha_xe || store.user?.ma_nha_xe || "global";
    const ch = echoInst.private(`nha-xe.${maNhaXe}`);
    ch.listen(".bao-dong.vi-pham", onRealtime);
    ch.listen(".ai.canh-bao", onRealtime);
    hasWs.value = true;
  } catch (e) {
    console.warn("WS error:", e?.message);
  }
};

// Withdraw
const canWithdraw = computed(
  () => vi.value.co_the_rut > 0 && vi.value.ngan_hang && vi.value.so_tai_khoan,
);
const handleWithdraw = async () => {
  const amt = Number(withdrawAmount.value);
  if (!amt || amt < 10000) {
    withdrawMsg.value = "Số tiền tối thiểu 10,000đ";
    return;
  }
  if (amt > vi.value.co_the_rut) {
    withdrawMsg.value = "Vượt quá số tiền có thể rút";
    return;
  }
  withdrawing.value = true;
  withdrawMsg.value = "";
  try {
    await operatorApi.requestWithdraw({ amount: amt });
    withdrawMsg.value = "✅ Đã gửi yêu cầu rút tiền!";
    vi.value.so_du -= amt;
    vi.value.co_the_rut = Math.max(
      0,
      vi.value.so_du - vi.value.han_muc_toi_thieu,
    );
    withdrawAmount.value = "";
  } catch (e) {
    withdrawMsg.value = e?.response?.data?.message || "Lỗi rút tiền";
  }
  withdrawing.value = false;
};

onMounted(async () => {
  clockTimer = setInterval(() => {
    now.value = new Date();
  }, 1000);
  await fetchKpis();
  initWs();
});
onUnmounted(() => {
  clearInterval(clockTimer);
  clearTimeout(aiBlinkTimer);
  if (echoInst) {
    const m = store.user?.nhaXe?.ma_nha_xe || "global";
    echoInst.leave(`private-nha-xe.${m}`);
  }
});
</script>

<template>
  <section class="op-dash" :class="{ 'focus-active': focusMode }">
    <!-- FOCUS MODE OVERLAY -->
    <div v-if="focusMode" class="focus-overlay" @click="dismissFocus">
      <div class="focus-box" @click.stop>
        <Phone :size="36" class="focus-phone" />
        <h2>🚨 CẢNH BÁO KHẨN CẤP</h2>
        <p>
          <strong>{{ focusDriver }}</strong> bị phát hiện ngủ gật liên tục (≥3
          lần / 10 phút)!
        </p>
        <p>Hãy GỌI ĐIỆN cho tài xế NGAY LẬP TỨC.</p>
        <button class="btn-dismiss" @click="dismissFocus">Đã xử lý</button>
      </div>
    </div>

    <!-- HEADER -->
    <header class="dash-head glass">
      <div class="head-l">
        <div class="head-ico"><BusFront :size="26" /></div>
        <div>
          <h1>Dashboard Nhà xe</h1>
          <p class="sub">Giám sát hoạt động, an toàn AI và tài chính.</p>
        </div>
      </div>
      <div class="head-r">
        <div class="ws-pill">
          <Radio :size="14" /> {{ hasWs ? "Realtime" : "Đang kết nối..." }}
        </div>
        <p class="time-txt">{{ dateText }}</p>
      </div>
    </header>

    <!-- AI BLINK -->
    <div v-if="aiBlinkVisible" class="ai-blink">
      <TriangleAlert :size="20" /> {{ aiBlinkMsg }}
    </div>

    <!-- 4 KPI CARDS -->
    <div class="kpi-grid">
      <article
        class="kpi-card grad-green"
        @click="showWithdraw = true"
        style="cursor: pointer"
      >
        <div class="kpi-top"><Wallet :size="18" /> Số dư ví</div>
        <h3>{{ fmt(vi.so_du) }}</h3>
        <p>Có thể rút: {{ fmt(vi.co_the_rut) }}</p>
      </article>
      <article class="kpi-card grad-blue">
        <div class="kpi-top"><BusFront :size="18" /> Chuyến đang chạy</div>
        <h3>{{ chuyenDangChay }}</h3>
        <p>Xe đang lăn bánh trên đường</p>
      </article>
      <article class="kpi-card grad-red">
        <div class="kpi-top"><ShieldAlert :size="18" /> Vi phạm AI hôm nay</div>
        <h3>{{ viPham.tong }}</h3>
        <p>Ngủ gật: {{ viPham.chi_tiet.ngu_gat }}</p>
      </article>
      <article class="kpi-card grad-teal">
        <div class="kpi-top"><Ticket :size="18" /> Vé bán mới</div>
        <h3>{{ veMoiHomNay }}</h3>
        <p>Vé đặt trong hôm nay</p>
      </article>
    </div>

    <!-- ROW 2: Chart + Pie -->
    <div class="row2">
      <article class="glass panel">
        <div class="panel-hd">
          <h2><TrendingUp :size="18" /> Doanh thu 7 ngày</h2>
        </div>
        <div class="chart-box" v-if="dt7.length">
          <Line :data="lineData" :options="lineOpts" />
        </div>
        <p v-else class="empty">Chưa có dữ liệu.</p>
      </article>
      <article class="glass panel">
        <div class="panel-hd">
          <h2><ShieldAlert :size="18" class="txt-red" /> Vi phạm AI</h2>
        </div>
        <div class="chart-box sm" v-if="viPham.tong">
          <Doughnut :data="pieData" :options="pieOpts" />
        </div>
        <p v-else class="empty">Chưa có vi phạm.</p>
      </article>
    </div>

    <!-- ROW 3: Safety table + Mini map placeholder -->
    <div class="row3">
      <article class="glass panel">
        <div class="panel-hd">
          <h2>
            <BellRing :size="18" class="txt-red" /> Cảnh báo an toàn thời gian
            thực
          </h2>
          <RouterLink class="plink" to="/operator/canh-bao"
            >Xem tất cả <ArrowRight :size="14"
          /></RouterLink>
        </div>
        <table class="mini-tbl" v-if="suCo.length">
          <thead>
            <tr>
              <th>Ảnh</th>
              <th>Loại</th>
              <th>Mức độ</th>
              <th>Tài xế</th>
              <th>BSX</th>
              <th>Thời gian</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in suCo" :key="s.id">
              <td>
                <img v-if="s.anh_url" :src="s.anh_url" class="thumb" /><span
                  v-else
                  class="no-img"
                  >—</span
                >
              </td>
              <td>{{ loaiLabel(s.loai_bao_dong) }}</td>
              <td>
                <span :class="mucDoClass(s.muc_do)" class="badge-sm">{{
                  mucDoLabel(s.muc_do)
                }}</span>
              </td>
              <td>{{ s.ten_tai_xe || "—" }}</td>
              <td>{{ s.bien_so || "—" }}</td>
              <td>{{ fmtTime(s.created_at) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="empty">Chưa có sự cố AI.</p>
      </article>
      <article class="glass panel">
        <div class="panel-hd">
          <h2><MapPin :size="18" /> Vị trí xe đang chạy</h2>
        </div>
        <div class="map-placeholder" v-if="xeChay.length">
          <div v-for="x in xeChay" :key="x.id_chuyen_xe" class="map-item">
            <BusFront :size="16" class="txt-green" />
            <div>
              <strong>{{ x.bien_so || "N/A" }}</strong> —
              {{ x.ten_tai_xe || "—" }}<br /><small
                >{{ x.diem_bat_dau }} → {{ x.diem_ket_thuc }}</small
              >
            </div>
          </div>
        </div>
        <p v-else class="empty">Không có xe đang chạy.</p>
      </article>
    </div>

    <!-- WITHDRAW MODAL -->
    <div
      v-if="showWithdraw"
      class="modal-overlay"
      @click="showWithdraw = false"
    >
      <div class="modal-box" @click.stop>
        <h2><Wallet :size="20" /> Rút tiền nhanh</h2>
        <div class="modal-info">
          <p>
            Số dư: <strong class="txt-green">{{ fmt(vi.so_du) }}</strong>
          </p>
          <p>
            Quỹ ký quỹ tối thiểu:
            <strong>{{ fmt(vi.han_muc_toi_thieu) }}</strong>
          </p>
          <p>
            Có thể rút:
            <strong class="txt-blue">{{ fmt(vi.co_the_rut) }}</strong>
          </p>
          <p v-if="vi.ngan_hang">
            NH: {{ vi.ngan_hang }} — {{ vi.so_tai_khoan }}
          </p>
          <p v-else class="txt-red">⚠ Chưa cập nhật thông tin ngân hàng</p>
        </div>
        <input
          v-model="withdrawAmount"
          type="number"
          placeholder="Nhập số tiền rút"
          class="modal-input"
          :disabled="!canWithdraw"
        />
        <p
          v-if="withdrawMsg"
          :class="withdrawMsg.startsWith('✅') ? 'txt-green' : 'txt-red'"
          style="font-size: 0.85rem"
        >
          {{ withdrawMsg }}
        </p>
        <div class="modal-btns">
          <button class="btn-cancel" @click="showWithdraw = false">Đóng</button>
          <button
            class="btn-primary"
            @click="handleWithdraw"
            :disabled="!canWithdraw || withdrawing"
          >
            {{ withdrawing ? "Đang xử lý..." : "Rút tiền" }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.op-dash {
  display: flex;
  flex-direction: column;
  gap: 16px;
  color: #0f172a;
  min-height: 100%;
}
.op-dash.focus-active {
  animation: focus-flash 1s infinite;
}
@keyframes focus-flash {
  0%,
  100% {
    box-shadow: inset 0 0 0 rgba(239, 68, 68, 0);
  }
  50% {
    box-shadow: inset 0 0 60px rgba(239, 68, 68, 0.15);
  }
}
.glass {
  background: rgba(255, 255, 255, 0.82);
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 16px;
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.07);
  backdrop-filter: blur(6px);
}
.panel {
  padding: 16px;
}
.dash-head {
  padding: 16px 20px;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.head-l {
  display: flex;
  gap: 12px;
  align-items: center;
}
.head-ico {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: linear-gradient(135deg, #16a34a, #22c55e);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
}
.dash-head h1 {
  margin: 0 0 4px;
  font-size: 1.25rem;
  font-weight: 800;
}
.sub {
  margin: 0;
  color: #475569;
  font-size: 0.85rem;
}
.head-r {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}
.ws-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-weight: 700;
  font-size: 0.8rem;
  border-radius: 999px;
  border: 1px solid #bbf7d0;
  background: #f0fdf4;
  padding: 5px 12px;
  color: #16a34a;
}
.time-txt {
  margin: 0;
  color: #64748b;
  font-size: 0.78rem;
}
.ai-blink {
  display: flex;
  align-items: center;
  gap: 10px;
  border-radius: 14px;
  background: #ef4444;
  color: #fff;
  padding: 12px 16px;
  font-weight: 800;
  font-size: 0.9rem;
  animation: blink-d 1s infinite;
}
@keyframes blink-d {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}
.kpi-card {
  padding: 16px;
  border-radius: 16px;
  color: #fff;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
}
.kpi-card h3 {
  font-size: 1.9rem;
  margin: 8px 0 4px;
  line-height: 1;
}
.kpi-card p {
  margin: 0;
  font-size: 0.8rem;
  opacity: 0.9;
}
.kpi-top {
  display: flex;
  gap: 6px;
  align-items: center;
  font-weight: 700;
  font-size: 0.82rem;
}
.grad-green {
  background: linear-gradient(135deg, #16a34a, #22c55e);
}
.grad-blue {
  background: linear-gradient(135deg, #2563eb, #3b82f6);
}
.grad-red {
  background: linear-gradient(135deg, #dc2626, #f97316);
}
.grad-teal {
  background: linear-gradient(135deg, #0d9488, #14b8a6);
}
.row2 {
  display: grid;
  grid-template-columns: 1.5fr 1fr;
  gap: 16px;
}
.row3 {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 16px;
}
.chart-box {
  height: 220px;
  position: relative;
}
.chart-box.sm {
  height: 180px;
}
.panel-hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.panel-hd h2 {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 6px;
}
.plink {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  text-decoration: none;
  color: #16a34a;
  font-size: 0.8rem;
  font-weight: 700;
}
.mini-tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}
.mini-tbl th {
  text-align: left;
  padding: 7px 6px;
  border-bottom: 2px solid #e2e8f0;
  color: #64748b;
  font-weight: 700;
  font-size: 0.76rem;
  text-transform: uppercase;
}
.mini-tbl td {
  padding: 7px 6px;
  border-bottom: 1px solid #f1f5f9;
}
.thumb {
  width: 48px;
  height: 36px;
  border-radius: 6px;
  object-fit: cover;
}
.no-img {
  color: #94a3b8;
  font-size: 0.8rem;
}
.badge-sm {
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 0.73rem;
  font-weight: 700;
}
.badge-red {
  background: #fef2f2;
  color: #dc2626;
}
.badge-orange {
  background: #fff7ed;
  color: #ea580c;
}
.badge-yellow {
  background: #fefce8;
  color: #ca8a04;
}
.badge-blue {
  background: #eff6ff;
  color: #2563eb;
}
.map-placeholder {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 280px;
  overflow-y: auto;
}
.map-item {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #fff;
}
.map-item small {
  color: #64748b;
  font-size: 0.78rem;
}
.txt-red {
  color: #dc2626;
}
.txt-green {
  color: #16a34a;
}
.txt-blue {
  color: #2563eb;
}
.empty {
  text-align: center;
  color: #94a3b8;
  padding: 20px 0;
  font-size: 0.85rem;
}
/* Focus overlay */
.focus-overlay {
  position: fixed;
  inset: 0;
  background: rgba(220, 38, 38, 0.25);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: focus-pulse 1s infinite;
}
@keyframes focus-pulse {
  0%,
  100% {
    background: rgba(220, 38, 38, 0.2);
  }
  50% {
    background: rgba(220, 38, 38, 0.35);
  }
}
.focus-box {
  background: #fff;
  border-radius: 20px;
  padding: 32px;
  text-align: center;
  max-width: 440px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}
.focus-box h2 {
  color: #dc2626;
  margin: 12px 0 8px;
}
.focus-phone {
  color: #dc2626;
  animation: ring 0.5s infinite alternate;
}
@keyframes ring {
  0% {
    transform: rotate(-10deg);
  }
  100% {
    transform: rotate(10deg);
  }
}
.btn-dismiss {
  margin-top: 16px;
  padding: 10px 28px;
  border: none;
  border-radius: 10px;
  background: #dc2626;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  font-size: 0.9rem;
}
/* Withdraw modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal-box {
  background: #fff;
  border-radius: 18px;
  padding: 28px;
  width: 400px;
  max-width: 90vw;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}
.modal-box h2 {
  margin: 0 0 16px;
  font-size: 1.1rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 8px;
}
.modal-info p {
  margin: 4px 0;
  font-size: 0.88rem;
  color: #334155;
}
.modal-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.95rem;
  margin: 12px 0 8px;
  box-sizing: border-box;
}
.modal-btns {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 12px;
}
.btn-cancel {
  padding: 8px 20px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.85rem;
}
.btn-primary {
  padding: 8px 20px;
  border: none;
  border-radius: 10px;
  background: #16a34a;
  color: #fff;
  cursor: pointer;
  font-weight: 700;
  font-size: 0.85rem;
}
.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
@media (max-width: 900px) {
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .row2,
  .row3 {
    grid-template-columns: 1fr;
  }
}
</style>
