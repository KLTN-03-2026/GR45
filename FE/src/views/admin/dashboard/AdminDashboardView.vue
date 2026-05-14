<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import Echo from 'laravel-echo'
import { buildLaravelEchoTransportOptions } from '@/utils/echo.js'
import {
  DollarSign, Ticket, ShieldAlert, BusFront, Users, TrendingUp,
  Siren, MapPin, BellRing, Activity, Clock3, Radio, ArrowRight,
  TriangleAlert, Building2, Route, CreditCard, IdCard, AlertCircle
} from 'lucide-vue-next'
import {
  Chart as ChartJS, CategoryScale, LinearScale, PointElement,
  LineElement, ArcElement, Title, Tooltip, Legend, Filler
} from 'chart.js'
import { Line, Doughnut } from 'vue-chartjs'
import adminApi from '@/api/adminApi'
import { useAdminStore } from '@/stores/adminStore.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Title, Tooltip, Legend, Filler)

const adminStore = useAdminStore()
const now = ref(new Date())
const loading = ref(true)
const aiBlinkVisible = ref(false)
const aiBlinkMessage = ref('')
const aiBlinkTimeout = ref(null)
const hasWs = ref(false)
let echoInstance = null
let clockTicker = null

// KPI Data
const kd = ref({ doanh_thu_hom_nay: 0, doanh_thu_thang_nay: 0, tong_ve_da_ban_hom_nay: 0, ty_le_lap_day_ghe: 0, khach_hang_moi_24h: 0, doanh_thu_7_ngay: [] })
const at = ref({ so_sos_chua_xu_ly: 0, vi_pham_ai_24h: { ngu_gat: 0, su_dung_dien_thoai: 0, hut_thuoc: 0, khac: 0 }, tai_xe_nguy_co: [], su_co_moi_nhat: [] })
const vh = ref({ chuyen_xe_dang_chay: 0, nha_xe_cho_duyet: 0, tuyen_duong_cho_duyet: 0, tai_xe_bang_lai_sap_het_han: 0 })
const tc = ref({ tong_quy_ky_quy: 0, yeu_cau_rut_tien_cho: 0, khieu_nai_chua_giai_quyet: 0 })

const tongViPham24h = computed(() => {
  const v = at.value.vi_pham_ai_24h
  return (v.ngu_gat || 0) + (v.su_dung_dien_thoai || 0) + (v.hut_thuoc || 0) + (v.khac || 0)
})

const dateText = computed(() => now.value.toLocaleString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }))

const fmt = (n) => { if (!n) return '0 đ'; if (n >= 1e9) return (n/1e9).toFixed(2)+' tỷ'; if (n >= 1e6) return (n/1e6).toFixed(1)+' tr'; return n.toLocaleString('vi-VN')+' đ' }
const fmtFull = (n) => (n ?? 0).toLocaleString('vi-VN') + ' ₫'
const fmtTime = (t) => t ? new Date(t).toLocaleTimeString('vi-VN') : '--:--'
const fmtDate = (t) => t ? new Date(t).toLocaleDateString('vi-VN') : '--'

const loaiBaoDongLabel = (l) => {
  const m = { ngu_gat:'Ngủ gật', su_dung_dien_thoai:'Dùng ĐT', hut_thuoc:'Hút thuốc', qua_toc_do:'Quá tốc độ', phanh_gap:'Phanh gấp', bao_dong_khan_cap:'SOS', vi_pham_khac:'Khác', khong_quan_sat:'Không quan sát' }
  return m[l] || l
}
const mucDoClass = (m) => { if (m==='khan_cap') return 'badge-red'; if (m==='nguy_hiem') return 'badge-orange'; if (m==='canh_bao') return 'badge-yellow'; return 'badge-blue' }
const mucDoLabel = (m) => { if (m==='khan_cap') return 'Khẩn cấp'; if (m==='nguy_hiem') return 'Nguy hiểm'; if (m==='canh_bao') return 'Cảnh báo'; return 'Thông tin' }

// Charts
const lineData = computed(() => {
  const d7 = kd.value.doanh_thu_7_ngay || []
  return {
    labels: d7.map(i => { const d = new Date(i.ngay); return `${d.getDate()}/${d.getMonth()+1}` }),
    datasets: [{
      label: 'Doanh thu (triệu đ)', data: d7.map(i => +(Number(i.doanh_thu)/1e6).toFixed(1)),
      borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.08)',
      borderWidth: 3, pointRadius: 5, pointHoverRadius: 8, tension: 0.4, fill: true
    }]
  }
})
const lineOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(15,23,42,0.9)', titleColor: '#f8fafc', bodyColor: '#cbd5e1', padding: 10, cornerRadius: 8 } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } }, x: { grid: { display: false } } } }

const pieData = computed(() => {
  const v = at.value.vi_pham_ai_24h
  return {
    labels: ['Ngủ gật', 'Dùng ĐT', 'Hút thuốc', 'Khác'],
    datasets: [{ data: [v.ngu_gat||0, v.su_dung_dien_thoai||0, v.hut_thuoc||0, v.khac||0], backgroundColor: ['#ef4444','#f59e0b','#8b5cf6','#64748b'], borderWidth: 0 }]
  }
})
const pieOpts = { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, pointStyle: 'circle' } } } }

// Fetch data
const fetchKpis = async () => {
  loading.value = true
  try {
    const res = await adminApi.getDashboardKpis()
    const d = res?.data?.data ?? res?.data ?? {}
    if (d.kinh_doanh) kd.value = { ...kd.value, ...d.kinh_doanh }
    if (d.an_toan) at.value = { ...at.value, ...d.an_toan }
    if (d.van_hanh) vh.value = { ...vh.value, ...d.van_hanh }
    if (d.tai_chinh) tc.value = { ...tc.value, ...d.tai_chinh }
  } catch (e) { console.warn('Dashboard KPIs error:', e?.message) }
  loading.value = false
}

// WebSocket realtime
const triggerBlink = (msg) => {
  aiBlinkMessage.value = msg; aiBlinkVisible.value = true
  clearTimeout(aiBlinkTimeout.value)
  aiBlinkTimeout.value = setTimeout(() => { aiBlinkVisible.value = false }, 8000)
}

const onRealtimeViolation = (payload) => {
  const loai = payload?.loai_bao_dong || payload?.loai || ''
  const bien = payload?.bien_so || 'N/A'
  const ten = payload?.ten_tai_xe || payload?.ho_va_ten || ''
  // Update AI violation counts
  const v = { ...at.value.vi_pham_ai_24h }
  if (loai === 'ngu_gat') v.ngu_gat = (v.ngu_gat||0)+1
  else if (loai === 'su_dung_dien_thoai') v.su_dung_dien_thoai = (v.su_dung_dien_thoai||0)+1
  else if (loai === 'hut_thuoc') v.hut_thuoc = (v.hut_thuoc||0)+1
  else v.khac = (v.khac||0)+1
  at.value.vi_pham_ai_24h = v
  // Push to incidents
  at.value.su_co_moi_nhat = [{ id: Date.now(), loai_bao_dong: loai, muc_do: payload?.muc_do||'canh_bao', trang_thai:'moi', ten_tai_xe: ten, bien_so: bien, created_at: new Date().toISOString() }, ...at.value.su_co_moi_nhat].slice(0,5)
  if (loai === 'ngu_gat' || loai === 'bao_dong_khan_cap') {
    triggerBlink(`⚠ ${bien} — ${ten || 'Tài xế'}: ${loaiBaoDongLabel(loai)}`)
    if (loai === 'bao_dong_khan_cap') at.value.so_sos_chua_xu_ly++
  }
}

const initWs = () => {
  if (!adminStore.token) return
  try {
    const transport = buildLaravelEchoTransportOptions()
    if (!transport) return
    let url = import.meta.env.VITE_API_URL || 'https://api.bussafe.io.vn/api/'
    if (!url.endsWith('/')) url += '/'
    /** Kênh `he-thong.giam-sat` là public — không cần auth; vẫn giữ transport Reverb/Pusher thống nhất. */
    echoInstance = new Echo(transport)
    const ch = echoInstance.channel('he-thong.giam-sat')
    ch.listen('.bao-dong.vi-pham', onRealtimeViolation)
    ch.listen('.ai.canh-bao', onRealtimeViolation)
    hasWs.value = true
  } catch (e) { console.warn('WS init failed:', e?.message); hasWs.value = false }
}

onMounted(async () => {
  clockTicker = setInterval(() => { now.value = new Date() }, 1000)
  await fetchKpis()
  initWs()
})
onUnmounted(() => {
  clearInterval(clockTicker); clearTimeout(aiBlinkTimeout.value)
  if (echoInstance) echoInstance.leave('he-thong.giam-sat')
})
</script>

<template>
  <section class="adm-dash">
    <!-- HEADER -->
    <header class="dash-head glass">
      <div class="head-l">
        <div class="head-ico"><Siren :size="26" /></div>
        <div>
          <h1>Dashboard Quản trị Hệ thống</h1>
          <p class="sub">Trung tâm giám sát kinh doanh, an toàn AI, vận hành và tài chính.</p>
        </div>
      </div>
      <div class="head-r">
        <div class="ws-pill"><Radio :size="14" /> {{ hasWs ? 'Realtime WebSocket' : 'Đang kết nối...' }}</div>
        <p class="time-txt">{{ dateText }}</p>
      </div>
    </header>

    <!-- AI BLINK ALERT -->
    <div v-if="aiBlinkVisible" class="ai-blink" role="alert">
      <TriangleAlert :size="20" /> <span>{{ aiBlinkMessage }}</span>
    </div>

    <!-- TOP 4 KPI CARDS -->
    <div class="kpi-grid">
      <article class="kpi-card grad-green">
        <div class="kpi-top"><DollarSign :size="18" /> Doanh thu hôm nay</div>
        <h3>{{ fmt(kd.doanh_thu_hom_nay) }}</h3>
        <p>Tháng: {{ fmt(kd.doanh_thu_thang_nay) }}</p>
      </article>
      <article class="kpi-card grad-blue">
        <div class="kpi-top"><Ticket :size="18" /> Vé đã bán hôm nay</div>
        <h3>{{ kd.tong_ve_da_ban_hom_nay }}</h3>
        <p>Lấp đầy: {{ kd.ty_le_lap_day_ghe }}%</p>
      </article>
      <article class="kpi-card grad-red">
        <div class="kpi-top"><ShieldAlert :size="18" /> SOS khẩn cấp</div>
        <h3>{{ at.so_sos_chua_xu_ly }}</h3>
        <p>Vi phạm AI 24h: {{ tongViPham24h }}</p>
      </article>
      <article class="kpi-card grad-teal">
        <div class="kpi-top"><BusFront :size="18" /> Xe đang chạy</div>
        <h3>{{ vh.chuyen_xe_dang_chay }}</h3>
        <p>KH mới 24h: {{ kd.khach_hang_moi_24h }}</p>
      </article>
    </div>

    <!-- ROW 2: LINE CHART + PIE CHART -->
    <div class="row2">
      <article class="glass panel chart-line-wrap">
        <div class="panel-hd"><h2><TrendingUp :size="18" /> Doanh thu 7 ngày gần nhất</h2></div>
        <div class="chart-box" v-if="kd.doanh_thu_7_ngay.length"><Line :data="lineData" :options="lineOpts" /></div>
        <p v-else class="empty-msg">Chưa có dữ liệu doanh thu.</p>
      </article>
      <article class="glass panel chart-pie-wrap">
        <div class="panel-hd"><h2><ShieldAlert :size="18" class="txt-red" /> Vi phạm AI (24h)</h2></div>
        <div class="chart-box" v-if="tongViPham24h > 0"><Doughnut :data="pieData" :options="pieOpts" /></div>
        <p v-else class="empty-msg">Chưa có vi phạm trong 24h qua.</p>
        <div class="pie-stats">
          <span class="pie-tag red">Ngủ gật: {{ at.vi_pham_ai_24h.ngu_gat }}</span>
          <span class="pie-tag yellow">ĐT: {{ at.vi_pham_ai_24h.su_dung_dien_thoai }}</span>
          <span class="pie-tag purple">Thuốc: {{ at.vi_pham_ai_24h.hut_thuoc }}</span>
          <span class="pie-tag gray">Khác: {{ at.vi_pham_ai_24h.khac }}</span>
        </div>
      </article>
    </div>

    <!-- ROW 3: HIGH-RISK DRIVERS + LATEST INCIDENTS -->
    <div class="row3">
      <article class="glass panel">
        <div class="panel-hd">
          <h2><AlertCircle :size="18" class="txt-orange" /> Tài xế nguy cơ cao</h2>
        </div>
        <table class="mini-tbl" v-if="at.tai_xe_nguy_co.length">
          <thead><tr><th>#</th><th>Tài xế</th><th>Biển số</th><th>Vi phạm</th></tr></thead>
          <tbody>
            <tr v-for="(tx, i) in at.tai_xe_nguy_co" :key="tx.id">
              <td>{{ i+1 }}</td><td>{{ tx.ho_va_ten }}</td><td>{{ tx.bien_so }}</td>
              <td><span class="badge-red">{{ tx.so_vi_pham }}</span></td>
            </tr>
          </tbody>
        </table>
        <p v-else class="empty-msg">Không có tài xế vi phạm trong 24h.</p>
      </article>
      <article class="glass panel">
        <div class="panel-hd">
          <h2><BellRing :size="18" class="txt-red" /> 5 sự cố AI mới nhất</h2>
          <RouterLink class="panel-link" to="/admin/tracking">Tracking <ArrowRight :size="14" /></RouterLink>
        </div>
        <table class="mini-tbl" v-if="at.su_co_moi_nhat.length">
          <thead><tr><th>Loại</th><th>Mức độ</th><th>Tài xế</th><th>BSX</th><th>Thời gian</th></tr></thead>
          <tbody>
            <tr v-for="sc in at.su_co_moi_nhat" :key="sc.id">
              <td>{{ loaiBaoDongLabel(sc.loai_bao_dong) }}</td>
              <td><span :class="mucDoClass(sc.muc_do)" class="badge-sm">{{ mucDoLabel(sc.muc_do) }}</span></td>
              <td>{{ sc.ten_tai_xe || '—' }}</td>
              <td>{{ sc.bien_so || '—' }}</td>
              <td>{{ fmtTime(sc.created_at) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="empty-msg">Chưa có sự cố AI.</p>
      </article>
    </div>

    <!-- ROW 4: OPERATIONS + FINANCIAL -->
    <div class="row4">
      <article class="glass panel">
        <div class="panel-hd"><h2><Activity :size="18" /> Quản trị Vận hành</h2></div>
        <div class="ops-grid">
          <RouterLink to="/admin/chuyen-xe" class="ops-item">
            <BusFront :size="22" class="txt-blue" />
            <div><span class="ops-num">{{ vh.chuyen_xe_dang_chay }}</span><span class="ops-lbl">Chuyến đang chạy</span></div>
          </RouterLink>
          <RouterLink to="/admin/nha-xe" class="ops-item">
            <Building2 :size="22" class="txt-purple" />
            <div><span class="ops-num">{{ vh.nha_xe_cho_duyet }}</span><span class="ops-lbl">NX chờ duyệt</span></div>
          </RouterLink>
          <RouterLink to="/admin/tuyen-duong" class="ops-item">
            <Route :size="22" class="txt-teal" />
            <div><span class="ops-num">{{ vh.tuyen_duong_cho_duyet }}</span><span class="ops-lbl">Tuyến chờ duyệt</span></div>
          </RouterLink>
          <RouterLink to="/admin/tai-xe" class="ops-item">
            <IdCard :size="22" class="txt-orange" />
            <div><span class="ops-num">{{ vh.tai_xe_bang_lai_sap_het_han }}</span><span class="ops-lbl">GPLX sắp hết hạn</span></div>
          </RouterLink>
        </div>
      </article>
      <article class="glass panel">
        <div class="panel-hd"><h2><CreditCard :size="18" /> Sức khỏe Tài chính</h2></div>
        <div class="fin-list">
          <div class="fin-row">
            <span class="fin-lbl">Tổng quỹ ký quỹ (Escrow)</span>
            <span class="fin-val txt-green">{{ fmt(tc.tong_quy_ky_quy) }}</span>
          </div>
          <div class="fin-row">
            <span class="fin-lbl">Yêu cầu rút tiền chờ duyệt</span>
            <span class="fin-val txt-orange">{{ tc.yeu_cau_rut_tien_cho }}</span>
          </div>
          <div class="fin-row">
            <span class="fin-lbl">Khiếu nại chưa giải quyết</span>
            <span class="fin-val txt-red">{{ tc.khieu_nai_chua_giai_quyet }}</span>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.adm-dash { display:flex; flex-direction:column; gap:18px; color:#0f172a; min-height:100%; }
.glass { background:rgba(255,255,255,.82); border:1px solid rgba(226,232,240,.95); border-radius:16px; box-shadow:0 12px 32px rgba(15,23,42,.07); backdrop-filter:blur(6px); }
.panel { padding:18px; }

/* Header */
.dash-head { padding:18px 22px; display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.head-l { display:flex; gap:12px; align-items:center; }
.head-ico { width:50px; height:50px; border-radius:14px; background:linear-gradient(135deg,#2563eb,#0ea5e9); display:flex; align-items:center; justify-content:center; color:#fff; }
.dash-head h1 { margin:0 0 4px; font-size:1.3rem; font-weight:800; }
.sub { margin:0; color:#475569; font-size:.88rem; }
.head-r { display:flex; flex-direction:column; align-items:flex-end; gap:4px; }
.ws-pill { display:inline-flex; align-items:center; gap:5px; font-weight:700; font-size:.82rem; border-radius:999px; border:1px solid #bfdbfe; background:#eff6ff; padding:5px 12px; color:#1e40af; }
.time-txt { margin:0; color:#64748b; font-size:.8rem; }

/* AI Blink */
.ai-blink { display:flex; align-items:center; gap:10px; border-radius:14px; background:#ef4444; color:#fff; padding:12px 16px; font-weight:800; font-size:.95rem; animation:blink-d 1s infinite; }
@keyframes blink-d { 0%,100%{opacity:1;box-shadow:0 0 0 rgba(239,68,68,.8)} 50%{opacity:.5;box-shadow:0 0 20px rgba(239,68,68,.5)} }

/* KPI Grid */
.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
.kpi-card { padding:16px; border-radius:16px; color:#fff; box-shadow:0 10px 24px rgba(15,23,42,.12); }
.kpi-card h3 { font-size:2rem; margin:8px 0 4px; line-height:1; }
.kpi-card p { margin:0; font-size:.82rem; opacity:.9; }
.kpi-top { display:flex; gap:6px; align-items:center; font-weight:700; font-size:.84rem; }
.grad-green { background:linear-gradient(135deg,#16a34a,#22c55e); }
.grad-blue { background:linear-gradient(135deg,#2563eb,#3b82f6); }
.grad-red { background:linear-gradient(135deg,#dc2626,#f97316); }
.grad-teal { background:linear-gradient(135deg,#0d9488,#14b8a6); }

/* Row layouts */
.row2 { display:grid; grid-template-columns:1.5fr 1fr; gap:16px; }
.row3,.row4 { display:grid; grid-template-columns:1fr 1.3fr; gap:16px; }
.chart-box { height:220px; position:relative; }
.chart-line-wrap .chart-box { height:240px; }

/* Panel header */
.panel-hd { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.panel-hd h2 { margin:0; font-size:.95rem; font-weight:800; display:flex; align-items:center; gap:6px; }
.panel-link { display:inline-flex; align-items:center; gap:3px; text-decoration:none; color:#2563eb; font-size:.82rem; font-weight:700; }

/* Mini table */
.mini-tbl { width:100%; border-collapse:collapse; font-size:.84rem; }
.mini-tbl th { text-align:left; padding:8px 6px; border-bottom:2px solid #e2e8f0; color:#64748b; font-weight:700; font-size:.78rem; text-transform:uppercase; letter-spacing:.3px; }
.mini-tbl td { padding:8px 6px; border-bottom:1px solid #f1f5f9; }
.mini-tbl tr:hover td { background:rgba(59,130,246,.04); }

/* Badges */
.badge-sm { padding:2px 8px; border-radius:6px; font-size:.75rem; font-weight:700; }
.badge-red { background:#fef2f2; color:#dc2626; }
.badge-orange { background:#fff7ed; color:#ea580c; }
.badge-yellow { background:#fefce8; color:#ca8a04; }
.badge-blue { background:#eff6ff; color:#2563eb; }

/* Pie stats */
.pie-stats { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
.pie-tag { padding:3px 10px; border-radius:8px; font-size:.78rem; font-weight:700; }
.pie-tag.red { background:#fef2f2; color:#dc2626; }
.pie-tag.yellow { background:#fefce8; color:#ca8a04; }
.pie-tag.purple { background:#f5f3ff; color:#7c3aed; }
.pie-tag.gray { background:#f1f5f9; color:#475569; }

/* Operations */
.ops-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.ops-item { display:flex; gap:10px; align-items:center; padding:14px; border-radius:12px; border:1px solid #e2e8f0; text-decoration:none; color:#0f172a; transition:all .2s; background:#fff; }
.ops-item:hover { border-color:#3b82f6; box-shadow:0 4px 12px rgba(59,130,246,.1); transform:translateY(-1px); }
.ops-num { font-size:1.4rem; font-weight:800; display:block; line-height:1; }
.ops-lbl { font-size:.78rem; color:#64748b; }

/* Financial */
.fin-list { display:flex; flex-direction:column; gap:14px; }
.fin-row { display:flex; justify-content:space-between; align-items:center; padding:14px; border-radius:12px; border:1px solid #e2e8f0; background:#fff; }
.fin-lbl { font-size:.88rem; color:#334155; font-weight:600; }
.fin-val { font-size:1.2rem; font-weight:800; }

/* Utility colors */
.txt-red { color:#dc2626; }
.txt-orange { color:#ea580c; }
.txt-green { color:#16a34a; }
.txt-blue { color:#2563eb; }
.txt-purple { color:#7c3aed; }
.txt-teal { color:#0d9488; }

.empty-msg { text-align:center; color:#94a3b8; padding:24px 0; font-size:.88rem; }

@media (max-width:900px) {
  .kpi-grid { grid-template-columns:repeat(2,1fr); }
  .row2,.row3,.row4 { grid-template-columns:1fr; }
  .ops-grid { grid-template-columns:1fr; }
}
</style>
