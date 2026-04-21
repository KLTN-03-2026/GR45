<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  TrendingUp, DollarSign, Ticket, BusFront, Users,
  Calendar, Download, RefreshCw, Filter, MapPin,
  CheckCircle, XCircle, ArrowUpRight, AlertTriangle, Star,
  FileSpreadsheet, FileText
} from 'lucide-vue-next'
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, PointElement, LineElement,
  BarElement, ArcElement, Title, Tooltip, Legend, Filler
} from 'chart.js'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import operatorApi from '@/api/operatorApi'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title, Tooltip, Legend, Filler)

const filterType = ref('month')
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const selectedYear = ref(String(new Date().getFullYear()))
const selectedQuarterYear = ref(String(new Date().getFullYear()))
const selectedQuarterNum = ref('1')
const dateFrom = ref('')
const dateTo = ref('')

const filterTabs = [
  { key: 'range', label: 'Khoảng ngày' },
  { key: 'month', label: 'Theo tháng' },
  { key: 'quarter', label: 'Theo quý' },
  { key: 'year', label: 'Theo năm' },
]

const buildParams = () => {
  if (filterType.value === 'range') return { mode: 'range', tu_ngay: dateFrom.value, den_ngay: dateTo.value }
  if (filterType.value === 'month') return { mode: 'month', month: selectedMonth.value }
  if (filterType.value === 'quarter') return { mode: 'quarter', year: selectedQuarterYear.value, quarter: selectedQuarterNum.value }
  return { mode: 'year', year: selectedYear.value }
}

const kpi = ref({ tongDoanhThu: 0, tongVe: 0, tongChuyenXe: 0, tongKhachHang: 0 })
const isLoading = ref(false)
const exportLoading = ref(false)
const exportError = ref('')

const MONTHS = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12']
const revenueByTime = ref(Array(12).fill(0))
const ticketsByTime = ref(Array(12).fill(0))
const revenueByRoute = ref([])
const topTrips = ref([])
const topCustomers = ref([])
const pieBreakdown = computed(() => {
  const total = Math.max(kpi.value.tongVe, 1)
  const completed = Math.min(ticketsByTime.value.reduce((a, b) => a + b, 0), total)
  const cancelled = Math.max(Math.round(total * 0.15), 0)
  const pending = Math.max(total - completed - cancelled, 0)
  return [completed, cancelled, pending]
})

const tickets = ref([])
const ticketsMeta = ref({ current_page: 1, last_page: 1, total: 0 })
const ticketsLoading = ref(false)
const ticketsError = ref(null)
const ticketFilter = ref({ search: '', trang_thai: '', page: 1, per_page: 15 })
const ticketStatusOptions = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'hoan_thanh', label: 'Hoàn thành' },
  { value: 'da_huy', label: 'Đã huỷ' },
  { value: 'cho_xac_nhan', label: 'Chờ xác nhận' },
]

const fmt = (n) => {
  n = Number(n) || 0
  if (n >= 1e9) return (n/1e9).toFixed(2) + ' tỷ'
  if (n >= 1e6) return (n/1e6).toFixed(1) + ' tr'
  return n.toLocaleString('vi-VN') + ' đ'
}
const fmtFull = (n) => (Number(n)||0).toLocaleString('vi-VN') + ' ₫'
const fmtDate = (d) => d ? new Date(d).toLocaleString('vi-VN') : '—'

const fetchStats = async () => {
  isLoading.value = true
  try {
    const res = await operatorApi.getStatistics(buildParams())
    const data = res?.data?.data ?? res?.data ?? res
    kpi.value.tongDoanhThu = Number(data.tong_doanh_thu || 0)
    kpi.value.tongVe = Number(data.tong_ve_ban || 0)
    kpi.value.tongChuyenXe = Number(data.tong_chuyen_xe || 0)
    kpi.value.tongKhachHang = Number(data.tong_khach_hang || 0)

    const theoThoiGian = data.theo_thoi_gian || []
    const rev = Array(12).fill(0)
    const tkt = Array(12).fill(0)
    theoThoiGian.forEach((row) => {
      const m = new Date(row.ngay).getMonth()
      rev[m] += Number(row.doanh_thu || 0)
      tkt[m] += Number(row.so_ve || 0)
    })
    revenueByTime.value = rev
    ticketsByTime.value = tkt
    revenueByRoute.value = data.doanh_thu_theo_tuyen || []
    topTrips.value = data.top_chuyen_xe || []
    topCustomers.value = data.top_khach_hang || []
  } catch (e) {
    console.error('[ThongKe Operator] lỗi:', e)
  } finally {
    isLoading.value = false
  }
}

const fetchTickets = async () => {
  ticketsLoading.value = true
  ticketsError.value = null
  try {
    const params = Object.fromEntries(Object.entries({ ...buildParams(), ...ticketFilter.value }).filter(([, v]) => v !== '' && v !== null))
    const res = await operatorApi.getStatisticsTickets(params)
    const p = res?.data?.data ?? res?.data ?? res
    tickets.value = p?.data ?? []
    ticketsMeta.value = { current_page: p?.current_page ?? 1, last_page: p?.last_page ?? 1, total: p?.total ?? 0 }
  } catch (e) {
    ticketsError.value = e?.message ?? 'Lỗi tải dữ liệu'
    tickets.value = []
    ticketsMeta.value = { current_page: 1, last_page: 1, total: 0 }
  } finally {
    ticketsLoading.value = false
  }
}

const handleApply = () => {
  ticketFilter.value.page = 1
  fetchStats()
  fetchTickets()
}

const downloadBlob = (res, filename) => {
  const blob = new Blob([res.data], { type: res.headers['content-type'] || 'application/octet-stream' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  window.URL.revokeObjectURL(url)
}

const exportPdf = async () => {
  exportLoading.value = true
  exportError.value = ''
  try {
    const res = await operatorApi.exportStatisticsPdf(buildParams())
    downloadBlob(res, 'thong-ke-nha-xe.pdf')
  } catch (e) {
    exportError.value = 'Không thể xuất PDF'
  } finally {
    exportLoading.value = false
  }
}

const exportExcel = async () => {
  exportLoading.value = true
  exportError.value = ''
  try {
    const res = await operatorApi.exportStatisticsExcel(buildParams())
    downloadBlob(res, 'thong-ke-nha-xe.xlsx')
  } catch (e) {
    exportError.value = 'Không thể xuất Excel'
  } finally {
    exportLoading.value = false
  }
}

const tktGoToPage = (p) => {
  if (p < 1 || p > ticketsMeta.value.last_page) return
  ticketFilter.value.page = p
  fetchTickets()
}

const tktPageNums = computed(() => {
  const total = ticketsMeta.value.last_page, cur = ticketsMeta.value.current_page
  const range = []
  for (let i = Math.max(1, cur-2); i <= Math.min(total, cur+2); i++) range.push(i)
  return range
})

const trangThaiVe = (tt) => {
  if (tt === 'hoan_thanh' || tt == 1 || tt === 'confirmed') return { text:'Hoàn thành', cls:'badge-green' }
  if (tt === 'da_huy' || tt == 0 || tt === 'cancelled') return { text:'Đã huỷ', cls:'badge-red' }
  if (tt === 'cho_xac_nhan' || tt === 'pending') return { text:'Chờ xác nhận', cls:'badge-yellow' }
  return { text: tt ?? '—', cls: '' }
}

const lineData = computed(() => ({ labels: MONTHS, datasets: [{ label: 'Doanh thu (triệu đ)', data: revenueByTime.value.map(v => +(v/1e6).toFixed(1)), borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.12)', fill: true, tension: 0.4 }] }))
const lineOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
const barData = computed(() => ({ labels: MONTHS, datasets: [{ label: 'Số vé', data: ticketsByTime.value, backgroundColor: 'rgba(59,130,246,0.8)', borderRadius: 8, borderSkipped: false }] }))
const barOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
const donutData = computed(() => ({ labels: ['Hoàn thành', 'Đã huỷ', 'Đang chờ'], datasets: [{ data: pieBreakdown.value, backgroundColor: ['#22c55e','#ef4444','#f59e0b'] }] }))
const donutOpts = { responsive: true, maintainAspectRatio: false, cutout: '68%' }
const activeChartTab = ref('revenue')

onMounted(() => { fetchStats(); fetchTickets() })
</script>

<template>
  <div class="tk-page">
    <div class="tk-header">
      <div class="tk-header-left">
        <div class="tk-icon-wrap"><TrendingUp class="tk-icon" /></div>
        <div>
          <h1 class="tk-title">Thống Kê & Báo Cáo Doanh Thu</h1>
          <p class="tk-subtitle">Phân tích hiệu quả kinh doanh của nhà xe</p>
        </div>
      </div>
      <div class="tk-header-actions">
        <button class="btn-icon-only" :class="{ spinning: isLoading }" @click="handleApply" title="Làm mới"><RefreshCw class="ic16" /></button>
        <button class="btn-export btn-export-pdf" :disabled="exportLoading" @click="exportPdf"><FileText class="ic16" /> Xuất PDF</button>
        <button class="btn-export" :disabled="exportLoading" @click="exportExcel"><FileSpreadsheet class="ic16" /> Xuất Excel</button>
      </div>
    </div>

    <div v-if="exportError" class="export-error">{{ exportError }}</div>

    <div class="filter-card">
      <div class="filter-tabs">
        <button v-for="tab in filterTabs" :key="tab.key" class="filter-tab" :class="{ active: filterType === tab.key }" @click="filterType = tab.key"><Calendar class="ic14" /> {{ tab.label }}</button>
      </div>
      <div class="filter-row">
        <template v-if="filterType === 'range'">
          <div class="fg"><label>Từ ngày</label><input type="date" v-model="dateFrom" class="fi" /></div>
          <span class="fsep">→</span>
          <div class="fg"><label>Đến ngày</label><input type="date" v-model="dateTo" class="fi" /></div>
        </template>
        <template v-if="filterType === 'month'"><div class="fg"><label>Chọn tháng</label><input type="month" v-model="selectedMonth" class="fi" /></div></template>
        <template v-if="filterType === 'quarter'">
          <div class="fg"><label>Chọn quý</label><div class="qwrap"><select v-model="selectedQuarterYear" class="fi qsel"><option v-for="y in [2026,2025,2024,2023]" :key="y" :value="String(y)">{{ y }}</option></select><span class="fsep">–</span><select v-model="selectedQuarterNum" class="fi qsel"><option value="1">Quý 1</option><option value="2">Quý 2</option><option value="3">Quý 3</option><option value="4">Quý 4</option></select></div></div>
        </template>
        <template v-if="filterType === 'year'"><div class="fg year-field"><label>Chọn năm</label><select v-model="selectedYear" class="fi year-select"><option v-for="y in [2026,2025,2024,2023]" :key="y" :value="String(y)">{{ y }}</option></select></div></template>
        <button class="btn-apply" :disabled="isLoading" @click="handleApply"><template v-if="isLoading"><div class="bspinner"></div> Đang tải...</template><template v-else><Filter class="ic16" /> Áp dụng</template></button>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="kpi-card kc-green"><div class="kpi-top"><div class="kpi-icon-w kig-green"><DollarSign class="kpi-ic" /></div><span class="kbadge up"><ArrowUpRight class="ic12"/>+0%</span></div><p class="kpi-label">Doanh Thu</p><h2 class="kpi-val">{{ fmt(kpi.tongDoanhThu) }}</h2><p class="kpi-sub">{{ fmtFull(kpi.tongDoanhThu) }}</p></div>
      <div class="kpi-card kc-blue"><div class="kpi-top"><div class="kpi-icon-w kig-blue"><Ticket class="kpi-ic" /></div></div><p class="kpi-label">Tổng Vé Bán</p><h2 class="kpi-val">{{ kpi.tongVe.toLocaleString() }}</h2><p class="kpi-sub">Vé trong kỳ lọc</p></div>
      <div class="kpi-card kc-indigo"><div class="kpi-top"><div class="kpi-icon-w kig-indigo"><BusFront class="kpi-ic" /></div></div><p class="kpi-label">Chuyến Xe</p><h2 class="kpi-val">{{ kpi.tongChuyenXe.toLocaleString() }}</h2><p class="kpi-sub">Tổng chuyến có vé</p></div>
      <div class="kpi-card kc-orange"><div class="kpi-top"><div class="kpi-icon-w kig-orange"><Users class="kpi-ic" /></div></div><p class="kpi-label">Khách Hàng</p><h2 class="kpi-val">{{ kpi.tongKhachHang.toLocaleString() }}</h2><p class="kpi-sub">Khách đặt vé kỳ này</p></div>
    </div>

    <div class="chart-card">
      <div class="chart-header"><h3 class="panel-title"><TrendingUp class="panel-ic" /> Biểu đồ phân tích</h3></div>
      <div class="chart-body">
        <div class="chart-main"><div class="chart-wrap"><Line v-if="activeChartTab==='revenue'" :data="lineData" :options="lineOpts" /><Bar v-else :data="barData" :options="barOpts" /></div></div>
        <div class="chart-side"><p class="side-title">Tỉ lệ vé</p><div class="donut-wrap"><Doughnut :data="donutData" :options="donutOpts" /></div></div>
      </div>
    </div>

    <div class="bottom-grid">
      <div class="panel">
        <div class="panel-hd"><h3 class="panel-title"><MapPin class="panel-ic" /> Doanh Thu Theo Tuyến</h3></div>
        <div class="route-list">
          <div v-if="revenueByRoute.length === 0" class="empty-state">Chưa có dữ liệu</div>
          <div v-for="(r, idx) in revenueByRoute" :key="r.ten_tuyen_duong" class="route-item">
            <div class="route-rank" :class="idx===0?'gold':idx===1?'silver':idx===2?'bronze':''">{{ idx+1 }}</div>
            <div class="route-info"><p class="route-name">{{ r.ten_tuyen_duong }}</p><div class="route-bar-wrap"><div class="route-bar" :style="{width: Math.min(r.doanh_thu/(revenueByRoute[0]?.doanh_thu||1)*100,100)+'%'}"></div></div></div>
            <div class="route-nums"><p class="rn-rev">{{ fmt(r.doanh_thu) }}</p><p class="rn-tkt">{{ r.so_ve }} vé</p></div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-hd"><h3 class="panel-title"><Star class="panel-ic" /> Top Chuyến Xe / Khách Hàng</h3></div>
        <div class="driver-list">
          <p class="driver-title">Top chuyến xe</p>
          <div v-for="d in topTrips" :key="d.id_chuyen_xe" class="driver-row">
            <div class="driver-avatar">{{ String(d.id_chuyen_xe).charAt(0) }}</div>
            <div class="driver-info"><p class="driver-name">{{ d.ten_tuyen_duong || ('Chuyến #' + d.id_chuyen_xe) }}</p><p class="driver-sub">{{ d.so_ve }} vé · {{ fmt(d.tong_doanh_thu) }}</p></div>
          </div>
          <p class="driver-title">Top khách hàng</p>
          <div v-for="c in topCustomers" :key="c.id_khach_hang" class="driver-row">
            <div class="driver-avatar">{{ c.ten_khach_hang.charAt(0) }}</div>
            <div class="driver-info"><p class="driver-name">{{ c.ten_khach_hang }}</p><p class="driver-sub">{{ c.so_ve }} vé · {{ fmt(c.tong_doanh_thu) }}</p></div>
          </div>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel-hd"><h3 class="panel-title"><Ticket class="panel-ic" /> Danh Sách Vé</h3></div>
      <div class="tkt-filter">
        <input v-model="ticketFilter.search" class="tfi" placeholder="Tìm mã vé, khách hàng..." @keyup.enter="()=>{ticketFilter.page=1;fetchTickets()}" />
        <select v-model="ticketFilter.trang_thai" class="tfs">
          <option v-for="opt in ticketStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
        <button class="btn-apply-sm" @click="()=>{ticketFilter.page=1;fetchTickets()}"><Filter class="ic14" /> Lọc</button>
      </div>
      <div v-if="ticketsLoading" class="state-wrap"><div class="spinner"></div><p>Đang tải...</p></div>
      <div v-else-if="ticketsError" class="state-wrap err"><AlertTriangle class="ic24" /><p>{{ ticketsError }}</p></div>
      <div v-else class="tbl-wrap">
        <table class="data-tbl">
          <thead><tr><th>Mã vé</th><th>Khách hàng</th><th>Tuyến đường</th><th>Chuyến xe</th><th>Giá vé</th><th>Trạng thái</th><th>Ngày đặt</th></tr></thead>
          <tbody>
            <tr v-if="tickets.length === 0"><td colspan="7" class="empty-cell">Không có dữ liệu vé</td></tr>
            <tr v-for="v in tickets" :key="v.id">
              <td class="fw6">#{{ v.id ?? v.ma_ve }}</td>
              <td>{{ v.ten_khach ?? v.ho_ten ?? v.khachHang?.ho_va_ten ?? '—' }}</td>
              <td>{{ v.chuyen_xe?.tuyen_duong?.ten_tuyen_duong || v.ten_tuyen || v.tuyen_duong || '—' }}</td>
              <td>{{ v.chuyen_xe?.id ?? v.ma_chuyen ?? v.chuyen_xe_id ?? '—' }}</td>
              <td class="money">{{ fmtFull(v.gia_ve ?? v.so_tien ?? v.tong_tien ?? 0) }}</td>
              <td><span class="badge" :class="trangThaiVe(v.tinh_trang ?? v.trang_thai).cls">{{ trangThaiVe(v.tinh_trang ?? v.trang_thai).text }}</span></td>
              <td>{{ fmtDate(v.created_at ?? v.thoi_gian_dat ?? v.ngay_dat) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="ticketsMeta.last_page > 1" class="pagi-bar">
        <span class="pagi-info">Trang {{ ticketsMeta.current_page }} / {{ ticketsMeta.last_page }} · {{ ticketsMeta.total }} vé</span>
        <div class="pagi-ctrl">
          <button class="pb" :disabled="ticketsMeta.current_page===1" @click="tktGoToPage(ticketsMeta.current_page-1)">‹</button>
          <button v-for="p in tktPageNums" :key="p" class="pb pn" :class="{active:p===ticketsMeta.current_page}" @click="tktGoToPage(p)">{{ p }}</button>
          <button class="pb" :disabled="ticketsMeta.current_page===ticketsMeta.last_page" @click="tktGoToPage(ticketsMeta.current_page+1)">›</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tk-page{padding:8px 0 40px;font-family:'Inter',system-ui,sans-serif}.tk-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}.tk-header-left{display:flex;align-items:center;gap:16px}.tk-icon-wrap{width:52px;height:52px;background:linear-gradient(135deg,#22c55e,#15803d);border-radius:16px;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 20px rgba(34,197,94,.35)}.tk-icon{width:26px;height:26px;color:white}.tk-title{font-size:24px;font-weight:800;color:#0d4f35;margin:0}.tk-subtitle{font-size:13px;color:#64748b;margin:4px 0 0}.tk-header-actions{display:flex;gap:10px;align-items:center}.btn-icon-only{width:40px;height:40px;border-radius:10px;border:1.5px solid #e2e8f0;background:white;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;color:#475569}.btn-export{display:flex;align-items:center;gap:6px;padding:0 18px;height:40px;border-radius:10px;background:linear-gradient(135deg,#22c55e,#15803d);color:white;font-weight:700;font-size:13px;border:none;cursor:pointer}.filter-card,.chart-card,.panel{background:white;border-radius:16px;padding:20px 24px;margin-bottom:24px;box-shadow:0 2px 12px rgba(0,0,0,.05);border:1px solid #f1f5f9}.export-error{margin:0 0 12px;color:#b91c1c;background:#fee2e2;padding:10px 12px;border-radius:10px}.filter-tabs{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap}.filter-tab,.ctab{padding:8px 16px;border-radius:10px;border:1.5px solid #e2e8f0;background:white;color:#64748b;font-size:13px;font-weight:500;cursor:pointer}.filter-tab.active,.ctab.active{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-color:#22c55e;color:#15803d;font-weight:700}.filter-row{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap}.fi{height:40px;padding:0 14px;border-radius:10px;border:1.5px solid #e2e8f0;font-size:14px;color:#374151;background:#f8fafc;outline:none;min-width:160px}.btn-apply,.btn-apply-sm{display:flex;align-items:center;gap:6px;padding:0 22px;height:40px;border-radius:10px;background:linear-gradient(135deg,#22c55e,#15803d);color:white;border:none;cursor:pointer}.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px}.kpi-card{padding:20px;border-radius:16px;background:white;border:1px solid #f1f5f9}.kpi-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}.kpi-icon-w{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center}.kig-green{background:linear-gradient(135deg,#22c55e,#15803d)}.kig-blue{background:linear-gradient(135deg,#3b82f6,#1d4ed8)}.kig-indigo{background:linear-gradient(135deg,#6366f1,#4338ca)}.kig-orange{background:linear-gradient(135deg,#f59e0b,#d97706)}.kpi-ic{width:22px;height:22px;color:white}.kpi-label{font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;margin:0 0 6px}.kpi-val{font-size:26px;font-weight:800;color:#0f172a;margin:0 0 4px}.kpi-sub{font-size:12px;color:#94a3b8;margin:0}.chart-body{display:grid;grid-template-columns:2fr 1fr;gap:20px}.chart-wrap{height:280px}.bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}.panel-hd{display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid #f8fafc}.panel-title{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:#0f172a;margin:0}.panel-ic{width:18px;height:18px;color:#22c55e}.route-list,.driver-list{padding:12px 20px 20px}.route-item,.driver-row{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f8fafc}.route-rank,.driver-avatar{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;background:#f1f5f9;color:#64748b;flex-shrink:0}.route-info,.driver-info{flex:1;min-width:0}.route-name,.driver-name{font-size:13px;font-weight:600;color:#1e293b;margin:0 0 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.route-bar-wrap{height:5px;background:#f1f5f9;border-radius:10px;overflow:hidden}.route-bar{height:100%;border-radius:10px;transition:width .5s ease;background:#22c55e}.route-nums{text-align:right;flex-shrink:0}.rn-rev{font-size:13px;font-weight:700;color:#15803d;margin:0 0 2px}.rn-tkt,.driver-sub{font-size:11px;color:#94a3b8;margin:0}.tkt-filter{display:flex;align-items:center;gap:10px;padding:14px 20px;border-bottom:1px solid #f1f5f9;background:#fafafa;flex-wrap:wrap}.tbl-wrap{overflow-x:auto}.data-tbl{width:100%;border-collapse:collapse;font-size:13px}.data-tbl th{padding:12px 16px;text-align:left;background:#f8fafc;color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #f1f5f9;white-space:nowrap}.data-tbl td{padding:13px 16px;border-bottom:1px solid #f9fafb;color:#374151}.badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700}.badge-green{background:#dcfce7;color:#15803d}.badge-red{background:#fee2e2;color:#dc2626}.badge-yellow{background:#fef9c3;color:#a16207}.pagi-bar{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #f1f5f9;flex-wrap:wrap;gap:10px}.pb{min-width:36px;height:36px;padding:0 8px;border-radius:9px;border:1.5px solid #e2e8f0;background:white;color:#475569;font-size:13px;font-weight:500;cursor:pointer}.pb.active{background:linear-gradient(135deg,#22c55e,#15803d);border-color:transparent;color:white}.empty-state,.state-wrap{text-align:center;padding:32px;color:#94a3b8}.spinner,.bspinner{width:28px;height:28px;border:3px solid #e2e8f0;border-top-color:#22c55e;border-radius:50%;animation:spin .8s linear infinite}.ic12{width:12px;height:12px}.ic13{width:13px;height:13px}.ic14{width:14px;height:14px}.ic16{width:16px;height:16px}.export-error{margin-bottom:16px;padding:10px 12px;border-radius:10px;background:#fee2e2;color:#991b1b}.donut-wrap{height:220px}.topgap{margin-top:8px}.year-field{min-width:220px}.year-select{min-width:220px}.btn-export-pdf{margin-left:auto}
@keyframes spin{to{transform:rotate(360deg)}}
@media (max-width:1280px){.kpi-grid{grid-template-columns:repeat(2,1fr)}.chart-body{grid-template-columns:1fr}.bottom-grid{grid-template-columns:1fr}}
@media (max-width:768px){.kpi-grid{grid-template-columns:1fr 1fr}.fi{min-width:140px}}
@media (max-width:480px){.kpi-grid{grid-template-columns:1fr}.tk-title{font-size:18px}}
</style>
