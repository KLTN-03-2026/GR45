<script setup>
/**
 * Thống kê nhà xe — ưu tiên GET /v1/nha-xe/bao-cao/*; fallback tổng hợp từ /v1/nha-xe/ve nếu báo cáo thiếu dữ liệu.
 */
// Trang Thống Kê & Báo Cáo Doanh Thu – Nhà Xe (dữ liệu riêng của nhà xe đăng nhập)
import { ref, computed, onMounted } from 'vue'
import {
  TrendingUp, DollarSign, Ticket, BusFront, Users,
  Calendar, Download, RefreshCw, Filter, MapPin,
  CheckCircle, XCircle, Clock, ArrowUpRight, ArrowDownRight,
  Star, ChevronLeft, ChevronRight, AlertTriangle, Percent, FileText, PieChart
} from 'lucide-vue-next'
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, PointElement, LineElement,
  BarElement, ArcElement, Title, Tooltip, Legend, Filler
} from 'chart.js'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import operatorApi from '@/api/operatorApi'

ChartJS.register(
  CategoryScale, LinearScale, PointElement, LineElement,
  BarElement, ArcElement, Title, Tooltip, Legend, Filler
)

// ─── Bộ lọc ─────────────────────────────────────────────────────────
const filterType    = ref('month')
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const selectedYear  = ref(String(new Date().getFullYear()))
const selectedQuarterYear = ref(String(new Date().getFullYear()))
const selectedQuarterNum  = ref('1')
const dateFrom = ref('')
const dateTo   = ref('')

const filterTabs = [
  { key: 'range',   label: 'Khoảng ngày' },
  { key: 'month',   label: 'Theo tháng'  },
  { key: 'quarter', label: 'Theo quý'    },
  { key: 'year',    label: 'Theo năm'    },
]

// ─── Tính tu_ngay / den_ngay từ filter ──────────────────────────────
const buildDateRange = () => {
  if (filterType.value === 'range') return { tu_ngay: dateFrom.value, den_ngay: dateTo.value }
  if (filterType.value === 'month') {
    const [y, m] = selectedMonth.value.split('-').map(Number)
    const last = new Date(y, m, 0).getDate()
    return { tu_ngay: `${y}-${String(m).padStart(2,'0')}-01`, den_ngay: `${y}-${String(m).padStart(2,'0')}-${String(last).padStart(2,'0')}` }
  }
  if (filterType.value === 'quarter') {
    const y = Number(selectedQuarterYear.value), q = Number(selectedQuarterNum.value)
    const sm = (q-1)*3+1, em = q*3, last = new Date(y, em, 0).getDate()
    return { tu_ngay: `${y}-${String(sm).padStart(2,'0')}-01`, den_ngay: `${y}-${String(em).padStart(2,'0')}-${String(last).padStart(2,'0')}` }
  }
  return { tu_ngay: `${selectedYear.value}-01-01`, den_ngay: `${selectedYear.value}-12-31` }
}

// ─── KPI tổng hợp ────────────────────────────────────────────────────
const kpi = ref({
  tongDoanhThu: 0,
  tongVeDaBan: 0,
  tongVe: 0,
  tongChuyenXe: 0,
  /** Khách có tương tác vé trong kỳ (distinct); KH mới thật cần API khách hàng */
  khachHangMoi: 0,
  veHoanThanh: 0,
  veHuy: 0,
  veCho: 0,
  tyLeHoanThanh: 0,
  tyLeHuy: 0,
  tyLeLapDay: 0,
})
const ticketVeSlice = ref({ daThanhToanNonCash: 0, tienMat: 0, daHuy: 0 })
const routeTop = ref([])
const routeBottom = ref([])
const isLoading = ref(false)
const isExporting = ref(false)
const isExportingPdf = ref(false)

// ─── Dữ liệu biểu đồ ────────────────────────────────────────────────
const MONTHS = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12']
const revenueByMonth  = ref(Array(12).fill(0))
const ticketsByMonth  = ref(Array(12).fill(0))
const revenueByRoute  = ref([])   // [{ ten_tuyen, doanh_thu, so_ve }]
const revenueByDriver = ref([])   // [{ ten_tai_xe, so_chuyen, so_ve }]

// ─── Vé gần đây (bảng) ───────────────────────────────────────────────
const tickets        = ref([])
const ticketsMeta    = ref({ current_page:1, last_page:1, total:0 })
const ticketsLoading = ref(false)
const ticketsError   = ref(null)
const ticketFilter   = ref({ search:'', trang_thai:'', page:1, per_page:15 })

// ─── Helpers ─────────────────────────────────────────────────────────
const fmt = (n) => {
  n = Number(n) || 0
  if (n >= 1e9) return (n/1e9).toFixed(2) + ' tỷ'
  if (n >= 1e6) return (n/1e6).toFixed(1) + ' tr'
  return n.toLocaleString('vi-VN') + ' đ'
}
const fmtFull = (n) => (Number(n)||0).toLocaleString('vi-VN') + ' ₫'
const fmtDate = (d) => d ? new Date(d).toLocaleString('vi-VN') : '—'

const unwrapApi = (res) => {
  if (res == null) return null
  if (res.success === false) return null
  return res.data !== undefined ? res.data : res
}

const normalizeTuyenRow = (x) => {
  if (!x || typeof x !== 'object') return null
  const label =
    x.ten_tuyen_duong ||
    x.ten_tuyen ||
    x.label ||
    [x.diem_bat_dau, x.diem_ket_thuc].filter(Boolean).join(' → ') ||
    (typeof x.tuyen_duong === 'string' ? x.tuyen_duong : null) ||
    `Tuyến #${x.id_tuyen_duong ?? x.id ?? ''}`
  return {
    ten_tuyen: label,
    doanh_thu: Number(x.doanh_thu ?? x.tong_doanh_thu ?? 0),
    so_ve: Number(x.so_ve ?? x.so_luong_ve ?? 0),
  }
}
const mapTuyenList = (raw) => (Array.isArray(raw) ? raw.map(normalizeTuyenRow).filter(Boolean) : [])

const getTinhTrang = (v) => String(v?.tinh_trang ?? v?.trang_thai ?? '').toLowerCase()
const isHuyVe = (v) => ['huy', 'da_huy'].includes(getTinhTrang(v))
const isDaTT = (v) => getTinhTrang(v) === 'da_thanh_toan'
const isCho = (v) => getTinhTrang(v) === 'dang_cho'
const isTienMat = (v) => String(v?.phuong_thuc_thanh_toan ?? '').toLowerCase() === 'tien_mat'

const fetchBaoCaoOperator = async (range) => {
  try {
    const [dashRes, tuyenRes, ttRes] = await Promise.all([
      operatorApi.getBaoCaoDashboard(range),
      operatorApi.getBaoCaoTheoTuyenDuong(range),
      operatorApi.getBaoCaoTrangThaiVe(range),
    ])
    const d = unwrapApi(dashRes)
    if (d && typeof d === 'object') {
      const rev = Number(d.tong_doanh_thu ?? d.tongDoanhThu ?? 0)
      if (!Number.isNaN(rev) && rev >= 0) kpi.value.tongDoanhThu = rev
      if (d.tong_ve_da_ban != null) kpi.value.tongVeDaBan = Number(d.tong_ve_da_ban)
      if (d.ty_le_lap_day_tb != null) kpi.value.tyLeLapDay = Number(d.ty_le_lap_day_tb)
      if (d.khach_hang_moi != null) kpi.value.khachHangMoi = Number(d.khach_hang_moi)
      if (d.tong_ve != null) kpi.value.tongVe = Number(d.tong_ve)
      if (Array.isArray(d.theo_thang) && d.theo_thang.length) {
        const rev = Array(12).fill(0)
        const tkt = Array(12).fill(0)
        d.theo_thang.forEach((item) => {
          const m =
            typeof item.thang === 'string'
              ? parseInt(item.thang.split('-')[1], 10) - 1
              : Number(item.thang) - 1
          if (m >= 0 && m < 12) {
            rev[m] = Number(item.doanh_thu ?? 0)
            tkt[m] = Number(item.so_ve ?? 0)
          }
        })
        revenueByMonth.value = rev
        ticketsByMonth.value = tkt
      }
      if (Array.isArray(d.top_chuyen_tai_xe) || Array.isArray(d.theo_tai_xe)) {
        const arr = d.top_chuyen_tai_xe ?? d.theo_tai_xe
        revenueByDriver.value = arr.slice(0, 5).map((x) => ({
          ten_tai_xe: x.ten_tai_xe ?? x.ten ?? '—',
          so_chuyen: Number(x.so_chuyen ?? x.so_chuyen_xe ?? 0),
          so_ve: Number(x.so_ve ?? 0),
        }))
      }
    }
    const t = unwrapApi(tuyenRes)
    if (t) {
      if (Array.isArray(t.cao) || Array.isArray(t.tuyen_doanh_thu_cao)) {
        const arr = mapTuyenList(t.cao ?? t.tuyen_doanh_thu_cao)
        if (arr.length) routeTop.value = arr.slice(0, 8)
      }
      if (Array.isArray(t.thap) || Array.isArray(t.tuyen_it_khach)) {
        const arr = mapTuyenList(t.thap ?? t.tuyen_it_khach)
        if (arr.length) routeBottom.value = arr.slice(0, 8)
      }
      if (!routeTop.value.length && Array.isArray(t.du_lieu)) {
        const arr = mapTuyenList(t.du_lieu)
        routeTop.value = [...arr].sort((a, b) => b.doanh_thu - a.doanh_thu).slice(0, 8)
        routeBottom.value = [...arr].sort((a, b) => a.so_ve - b.so_ve).slice(0, 5)
      }
    }
    const v = unwrapApi(ttRes)
    if (v && typeof v === 'object') {
      ticketVeSlice.value = {
        daThanhToanNonCash: Number(v.da_thanh_toan_khong_tien_mat ?? v.khong_tien_mat ?? 0),
        tienMat: Number(v.tien_mat ?? v.ve_tien_mat ?? 0),
        daHuy: Number(v.da_huy ?? v.huy ?? 0),
      }
    }
  } catch (e) {
    console.warn('[ThongKe] bao-cao nha xe:', e?.message ?? e)
  }
}

// ─── Fallback: tổng hợp từ danh sách vé ───────────────────────────────
const fetchDataFromTickets = async () => {
  const range = buildDateRange()
  try {
    const allRes = await operatorApi.getTickets({ ...range, per_page: 9999, page: 1 })
    const allPayload = allRes?.data ?? allRes
    const allList = Array.isArray(allPayload?.data) ? allPayload.data : []

    const getSoTien = (v) => Number(v.tong_tien ?? v.gia_ve ?? v.so_tien ?? 0)
    const veDaTT = allList.filter(isDaTT)
    const veHuy = allList.filter(isHuyVe)
    const veCho = allList.filter((v) => isCho(v) || (!isDaTT(v) && !isHuyVe(v)))

    const tongVe = allList.length
    const tongDoanhThu = veDaTT.reduce((s, v) => s + getSoTien(v), 0)
    const khachIds = new Set(
      allList.map((v) => v.id_khach_hang ?? v.khach_hang?.id ?? v.khach_hang_id).filter(Boolean)
    )

    let nonCash = 0
    let tm = 0
    let huyC = 0
    allList.forEach((v) => {
      if (isHuyVe(v)) huyC += 1
      else if (isDaTT(v)) {
        if (isTienMat(v)) tm += 1
        else nonCash += 1
      }
    })
    ticketVeSlice.value = { daThanhToanNonCash: nonCash, tienMat: tm, daHuy: huyC }

    const tripSeat = {}
    const routeMap = {}
    allList.forEach((v) => {
      const cx = v.chuyen_xe || {}
      const td = cx.tuyen_duong || {}
      const key =
        [td.diem_bat_dau, td.diem_ket_thuc].filter(Boolean).join(' → ') ||
        td.ten_tuyen_duong ||
        v.ten_tuyen ||
        v.tuyen_duong ||
        `Tuyến #${td.id ?? v.tuyen_duong_id ?? ''}`
      if (!routeMap[key]) routeMap[key] = { ten_tuyen: key, doanh_thu: 0, so_ve: 0 }
      if (isDaTT(v)) routeMap[key].doanh_thu += getSoTien(v)
      routeMap[key].so_ve += 1

      const cid = cx.id ?? v.chuyen_xe_id
      if (cid) {
        const cap0 = Number(cx.xe?.so_cho_ngoi ?? cx.phuong_tien?.so_cho_ngoi ?? cx.tong_so_ghe ?? 40) || 40
        if (!tripSeat[cid]) tripSeat[cid] = { sold: 0, cap: cap0 }
        tripSeat[cid].sold += 1
        const cap = Number(cx.xe?.so_cho_ngoi ?? cx.phuong_tien?.so_cho_ngoi ?? cx.tong_so_ghe ?? tripSeat[cid].cap)
        if (cap > 0) tripSeat[cid].cap = cap
      }
    })
    const routes = Object.values(routeMap)
    revenueByRoute.value = [...routes].sort((a, b) => b.doanh_thu - a.doanh_thu).slice(0, 8)
    routeTop.value = revenueByRoute.value
    routeBottom.value = [...routes].sort((a, b) => a.so_ve - b.so_ve).slice(0, 5)

    const ratios = Object.values(tripSeat).map((x) =>
      x.cap > 0 ? Math.min(100, (x.sold / x.cap) * 100) : 0
    )
    const tyLeLapDay =
      ratios.length > 0 ? +((ratios.reduce((a, b) => a + b, 0) / ratios.length).toFixed(1)) : 0

    kpi.value = {
      tongDoanhThu,
      tongVeDaBan: veDaTT.length,
      tongVe,
      tongChuyenXe: new Set(allList.map((v) => v.chuyen_xe_id ?? v.chuyen_xe?.id).filter(Boolean)).size,
      khachHangMoi: khachIds.size,
      veHoanThanh: veDaTT.length,
      veHuy: veHuy.length,
      veCho: veCho.length,
      tyLeHoanThanh: tongVe > 0 ? +((veDaTT.length / tongVe) * 100).toFixed(1) : 0,
      tyLeHuy: tongVe > 0 ? +((veHuy.length / tongVe) * 100).toFixed(1) : 0,
      tyLeLapDay,
    }

    const rev = Array(12).fill(0)
    const tkt = Array(12).fill(0)
    allList.forEach((v) => {
      const m = new Date(v.created_at ?? v.thoi_gian_dat ?? v.ngay_dat ?? Date.now()).getMonth()
      rev[m] += getSoTien(v)
      tkt[m] += 1
    })
    revenueByMonth.value = rev
    ticketsByMonth.value = tkt

    const driverMap = {}
    allList.forEach((v) => {
      const key = v.ten_tai_xe ?? `Chuyến #${v.chuyen_xe_id ?? '?'}`
      if (!driverMap[key]) driverMap[key] = { ten_tai_xe: key, so_chuyen: new Set(), so_ve: 0 }
      if (v.chuyen_xe_id) driverMap[key].so_chuyen.add(v.chuyen_xe_id)
      driverMap[key].so_ve++
    })
    revenueByDriver.value = Object.values(driverMap)
      .map((d) => ({ ...d, so_chuyen: d.so_chuyen.size }))
      .sort((a, b) => b.so_ve - a.so_ve)
      .slice(0, 5)
  } catch (e) {
    console.error('[ThongKe Operator] fallback vé:', e)
  }
}

// ─── Tải thống kê: báo cáo BE → vé nếu cần ───────────────────────────
const fetchData = async () => {
  isLoading.value = true
  try {
    const range = buildDateRange()
    await fetchBaoCaoOperator(range)
    const pie =
      ticketVeSlice.value.daThanhToanNonCash +
      ticketVeSlice.value.tienMat +
      ticketVeSlice.value.daHuy
    if (!kpi.value.tongDoanhThu && pie === 0 && !routeTop.value.length) {
      await fetchDataFromTickets()
    }
  } catch (e) {
    console.error('[ThongKe Operator] lỗi:', e)
  } finally {
    isLoading.value = false
  }
}

// ─── Bảng vé phân trang ───────────────────────────────────────────────
const fetchTickets = async () => {
  ticketsLoading.value = true
  ticketsError.value   = null
  try {
    const range = buildDateRange()
    const { trang_thai, ...tfRest } = ticketFilter.value
    const params = Object.fromEntries(
      Object.entries({
        ...range,
        ...tfRest,
        ...(trang_thai ? { tinh_trang: trang_thai } : {}),
      }).filter(([, v]) => v !== '' && v !== null && v !== undefined)
    )
    const res = await operatorApi.getTickets(params)
    const p   = res?.data ?? res
    tickets.value     = p?.data ?? []
    ticketsMeta.value = { current_page: p?.current_page??1, last_page: p?.last_page??1, total: p?.total??0 }
  } catch(e) { ticketsError.value = e?.message ?? 'Lỗi tải dữ liệu' }
  finally { ticketsLoading.value = false }
}

const handleApply = () => {
  ticketFilter.value.page = 1
  fetchData()
  fetchTickets()
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

// ─── Nhãn trạng thái vé (API: tinh_trang) ────────────────────────────
const trangThaiVe = (tt) => {
  const s = String(tt ?? '').toLowerCase()
  if (s === 'da_thanh_toan') return { text: 'Đã thanh toán', cls: 'badge-green' }
  if (s === 'dang_cho') return { text: 'Chờ thanh toán', cls: 'badge-yellow' }
  if (s === 'huy' || s === 'da_huy') return { text: 'Đã hủy', cls: 'badge-red' }
  return { text: tt ?? '—', cls: '' }
}

// ─── Biểu đồ line doanh thu ──────────────────────────────────────────
const lineData = computed(() => ({
  labels: MONTHS,
  datasets: [{
    label: 'Doanh thu (triệu đ)',
    data: revenueByMonth.value.map(v => +(v/1e6).toFixed(1)),
    borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.12)',
    fill: true, tension: 0.4, pointRadius: 4, pointHoverRadius: 7,
    pointBackgroundColor: '#22c55e', borderWidth: 2.5,
  }]
}))

const lineOpts = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.raw} triệu đ` } } },
  scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: v => v+'tr' } } }
}

// ─── Biểu đồ bar vé theo tháng ───────────────────────────────────────
const barData = computed(() => ({
  labels: MONTHS,
  datasets: [{
    label: 'Số vé',
    data: ticketsByMonth.value,
    backgroundColor: 'rgba(59,130,246,0.8)', borderRadius: 8, borderSkipped: false,
  }]
}))

const barOpts = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { x: { grid: { display: false } }, y: { beginAtZero: true } }
}

// ─── Biểu đồ donut: Đã TT (không TM) | Tiền mặt | Đã hủy ─────────────
const donutData = computed(() => {
  const s = ticketVeSlice.value
  const sum = s.daThanhToanNonCash + s.tienMat + s.daHuy
  if (!sum) {
    return {
      labels: ['Chưa có dữ liệu'],
      datasets: [{ data: [1], backgroundColor: ['#e2e8f0'], borderColor: ['#cbd5e1'], borderWidth: 2 }],
    }
  }
  return {
    labels: ['Đã TT (không TM)', 'Tiền mặt', 'Đã hủy'],
    datasets: [{
      data: [s.daThanhToanNonCash, s.tienMat, s.daHuy],
      backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
      borderColor: ['#16a34a', '#d97706', '#dc2626'],
      borderWidth: 2,
      hoverOffset: 8,
    }],
  }
})

const donutOpts = {
  responsive: true, maintainAspectRatio: false, cutout: '68%',
  plugins: { legend: { position: 'right', labels: { usePointStyle: true, padding: 16 } } }
}

const activeChartTab = ref('revenue')

const handleExportPdf = async () => {
  const range = buildDateRange()
  const k = kpi.value
  const v = ticketVeSlice.value
  isExportingPdf.value = true
  try {
    const blob = await operatorApi.exportBaoCao({ ...range, format: 'pdf' })
    if (blob instanceof Blob && blob.size > 0 && (blob.type.includes('pdf') || blob.type === 'application/octet-stream')) {
      const url = URL.createObjectURL(blob)
      window.open(url, '_blank')
      setTimeout(() => URL.revokeObjectURL(url), 120000)
      isExportingPdf.value = false
      return
    }
  } catch (e) {
    console.warn('[ThongKe] export PDF BE:', e)
  }
  const w = window.open('', '_blank')
  if (!w) {
    isExportingPdf.value = false
    alert('Trình duyệt chặn popup — cho phép để in PDF.')
    return
  }
  const rt = routeTop.value.map((r) => `<tr><td>${r.ten_tuyen}</td><td style="text-align:right">${r.doanh_thu}</td><td style="text-align:right">${r.so_ve}</td></tr>`).join('')
  const rb = routeBottom.value.map((r) => `<tr><td>${r.ten_tuyen}</td><td style="text-align:right">${r.doanh_thu}</td><td style="text-align:right">${r.so_ve}</td></tr>`).join('')
  w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"/><title>Thống kê nhà xe</title>
  <style>body{font-family:Segoe UI,Arial,sans-serif;padding:24px}table{border-collapse:collapse;width:100%;margin:12px 0}th,td{border:1px solid #ccc;padding:8px;font-size:13px}</style></head><body>
  <h1>Báo cáo thống kê — Nhà xe</h1><p>${range.tu_ngay} → ${range.den_ngay}</p>
  <p>Doanh thu: ${k.tongDoanhThu.toLocaleString('vi-VN')} ₫ · Vé đã TT: ${k.tongVeDaBan} · Lấp đầy TB: ${k.tyLeLapDay}% · Khách (kỳ): ${k.khachHangMoi}</p>
  <p>Vé: Đã TT không TM ${v.daThanhToanNonCash} · Tiền mặt ${v.tienMat} · Hủy ${v.daHuy}</p>
  <h2>Tuyến doanh thu cao</h2><table><tr><th>Tuyến</th><th>Doanh thu</th><th>Vé</th></tr>${rt}</table>
  <h2>Tuyến ít khách</h2><table><tr><th>Tuyến</th><th>Doanh thu</th><th>Vé</th></tr>${rb}</table>
  </body></html>`)
  w.document.close()
  w.focus()
  setTimeout(() => {
    w.print()
    isExportingPdf.value = false
  }, 300)
}

const handleExportExcel = async () => {
  isExporting.value = true
  try {
    const range = buildDateRange()
    try {
      const blob = await operatorApi.exportBaoCao({ ...range, format: 'xlsx' })
      if (blob instanceof Blob && blob.size > 0 && !blob.type.includes('json')) {
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `BaoCao_NhaXe_${range.tu_ngay}_${range.den_ngay}.xlsx`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
        return
      }
    } catch (e) {
      console.warn('[ThongKe] export Excel BE:', e)
    }

    const k = kpi.value
    const v = ticketVeSlice.value
    const esc = (x) => String(x ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    const rows = [
      ['BÁO CÁO THỐNG KÊ NHÀ XE', '', ''],
      [`Kỳ: ${range.tu_ngay} → ${range.den_ngay}`, '', ''],
      [],
      ['Tổng doanh thu', k.tongDoanhThu],
      ['Vé đã thanh toán', k.tongVeDaBan],
      ['Lấp đầy ghế TB (%)', k.tyLeLapDay],
      ['Khách (có vé trong kỳ)', k.khachHangMoi],
      [],
      ['Đã TT không TM', v.daThanhToanNonCash],
      ['Tiền mặt', v.tienMat],
      ['Đã hủy', v.daHuy],
    ]
    routeTop.value.forEach((r) => rows.push([`TOP: ${r.ten_tuyen}`, r.doanh_thu, r.so_ve]))
    routeBottom.value.forEach((r) => rows.push([`ÍT KH: ${r.ten_tuyen}`, r.doanh_thu, r.so_ve]))
    let xml = `<?xml version="1.0"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="ThongKe"><Table>`
    rows.forEach((row) => {
      xml += '<Row>'
      row.forEach((c) => {
        const t = typeof c === 'number' ? 'Number' : 'String'
        xml += `<Cell><Data ss:Type="${t}">${typeof c === 'number' ? c : esc(c)}</Data></Cell>`
      })
      xml += '</Row>'
    })
    xml += '</Table></Worksheet></Workbook>'
    const blob = new Blob([xml], { type: 'application/vnd.ms-excel' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `ThongKe_NhaXe_${range.tu_ngay}_${range.den_ngay}.xls`
    a.click()
    URL.revokeObjectURL(a.href)
  } finally {
    isExporting.value = false
  }
}

onMounted(() => { fetchData(); fetchTickets() })
</script>

<template>
  <div class="tk-page">

    <!-- TIÊU ĐỀ -->
    <div class="tk-header">
      <div class="tk-header-left">
        <div class="tk-icon-wrap">
          <TrendingUp class="tk-icon" />
        </div>
        <div>
          <h1 class="tk-title">Thống Kê & Báo Cáo Doanh Thu</h1>
          <p class="tk-subtitle">Phân tích hiệu quả kinh doanh của nhà xe</p>
        </div>
      </div>
      <div class="tk-header-actions">
        <button class="btn-icon-only" :class="{ spinning: isLoading }" @click="handleApply" title="Làm mới">
          <RefreshCw class="ic16" />
        </button>
        <button type="button" class="btn-export btn-export--light" id="btn-export-pdf" @click="handleExportPdf" :disabled="isExportingPdf">
          <FileText class="ic16" /> {{ isExportingPdf ? '...' : 'PDF' }}
        </button>
        <button type="button" class="btn-export" id="btn-export" @click="handleExportExcel" :disabled="isExporting">
          <Download class="ic16" /> {{ isExporting ? '...' : 'Excel' }}
        </button>
      </div>
    </div>

    <!-- BỘ LỌC -->
    <div class="filter-card">
      <div class="filter-tabs">
        <button
          v-for="tab in filterTabs" :key="tab.key"
          class="filter-tab" :class="{ active: filterType === tab.key }"
          @click="filterType = tab.key" :id="`tab-${tab.key}`"
        >
          <Calendar class="ic14" /> {{ tab.label }}
        </button>
      </div>
      <div class="filter-row">
        <template v-if="filterType === 'range'">
          <div class="fg"><label>Từ ngày</label><input type="date" v-model="dateFrom" class="fi" id="fi-from" /></div>
          <span class="fsep">→</span>
          <div class="fg"><label>Đến ngày</label><input type="date" v-model="dateTo" class="fi" id="fi-to" /></div>
        </template>
        <template v-if="filterType === 'month'">
          <div class="fg"><label><Calendar class="ic13" /> Chọn tháng</label><input type="month" v-model="selectedMonth" class="fi" id="fi-month" /></div>
        </template>
        <template v-if="filterType === 'quarter'">
          <div class="fg">
            <label><Calendar class="ic13" /> Chọn quý</label>
            <div class="qwrap">
              <select v-model="selectedQuarterYear" class="fi qsel" id="fi-qy">
                <option v-for="y in [2026,2025,2024,2023]" :key="y" :value="String(y)">{{ y }}</option>
              </select>
              <span class="fsep">–</span>
              <select v-model="selectedQuarterNum" class="fi qsel" id="fi-qn">
                <option value="1">Quý 1 (T1-T3)</option>
                <option value="2">Quý 2 (T4-T6)</option>
                <option value="3">Quý 3 (T7-T9)</option>
                <option value="4">Quý 4 (T10-T12)</option>
              </select>
            </div>
          </div>
        </template>
        <template v-if="filterType === 'year'">
          <div class="fg">
            <label><Calendar class="ic13" /> Chọn năm</label>
            <select v-model="selectedYear" class="fi" id="fi-year">
              <option v-for="y in [2026,2025,2024,2023]" :key="y" :value="String(y)">Năm {{ y }}</option>
            </select>
          </div>
        </template>
        <button class="btn-apply" :disabled="isLoading" @click="handleApply" id="btn-apply">
          <template v-if="isLoading"><div class="bspinner"></div> Đang tải...</template>
          <template v-else><Filter class="ic16" /> Áp dụng</template>
        </button>
      </div>
    </div>

    <!-- KPI (tiêu chí chấp nhận) -->
    <div class="kpi-grid">
      <div class="kpi-card kc-green">
        <div class="kpi-top"><div class="kpi-icon-w kig-green"><DollarSign class="kpi-ic" /></div></div>
        <p class="kpi-label">Tổng doanh thu</p>
        <h2 class="kpi-val">{{ fmt(kpi.tongDoanhThu) }}</h2>
        <p class="kpi-sub">{{ fmtFull(kpi.tongDoanhThu) }} · vé đã thanh toán</p>
      </div>
      <div class="kpi-card kc-blue">
        <div class="kpi-top"><div class="kpi-icon-w kig-blue"><Ticket class="kpi-ic" /></div></div>
        <p class="kpi-label">Tổng số vé đã bán</p>
        <h2 class="kpi-val">{{ kpi.tongVeDaBan.toLocaleString() }}</h2>
        <p class="kpi-sub">Trạng thái đã thanh toán</p>
      </div>
      <div class="kpi-card kc-indigo">
        <div class="kpi-top"><div class="kpi-icon-w kig-indigo"><Percent class="kpi-ic" /></div></div>
        <p class="kpi-label">Lấp đầy ghế TB</p>
        <h2 class="kpi-val">{{ kpi.tyLeLapDay }}%</h2>
        <p class="kpi-sub">Ước từ vé / sức chứa chuyến</p>
      </div>
      <div class="kpi-card kc-orange">
        <div class="kpi-top"><div class="kpi-icon-w kig-orange"><Users class="kpi-ic" /></div></div>
        <p class="kpi-label">Khách hàng (kỳ)</p>
        <h2 class="kpi-val">{{ kpi.khachHangMoi.toLocaleString() }}</h2>
        <p class="kpi-sub">Distinct khách có vé · KH mới cần API riêng</p>
      </div>
    </div>

    <!-- BIỂU ĐỒ -->
    <div class="chart-card">
      <div class="chart-header">
        <h3 class="panel-title"><TrendingUp class="panel-ic" /> Biểu đồ phân tích</h3>
        <div class="chart-tabs">
          <button v-for="t in [{k:'revenue',l:'Doanh Thu'},{k:'tickets',l:'Số Vé'}]" :key="t.k"
            class="ctab" :class="{active: activeChartTab===t.k}" @click="activeChartTab=t.k">{{ t.l }}</button>
        </div>
      </div>
      <div class="chart-body">
        <div class="chart-main">
          <div class="chart-wrap">
            <Line v-if="activeChartTab==='revenue'" :data="lineData" :options="lineOpts" />
            <Bar  v-else :data="barData" :options="barOpts" />
          </div>
        </div>
        <div class="chart-side">
          <p class="side-title"><PieChart class="ic14" style="vertical-align:-2px;margin-right:4px"/> Trạng thái vé</p>
          <div class="donut-wrap"><Doughnut :data="donutData" :options="donutOpts" /></div>
        </div>
      </div>
    </div>

    <!-- TUYẾN CAO / TUYẾN ÍT KHÁCH & HIỆU SUẤT -->
    <div class="bottom-grid bottom-grid--triple">

      <div class="panel">
        <div class="panel-hd"><h3 class="panel-title"><MapPin class="panel-ic" /> Tuyến doanh thu cao</h3></div>
        <div class="route-list">
          <div v-if="routeTop.length === 0" class="empty-state">Chưa có dữ liệu</div>
          <div v-for="(r, idx) in routeTop" :key="'t-'+r.ten_tuyen" class="route-item">
            <div class="route-rank" :class="idx===0?'gold':idx===1?'silver':idx===2?'bronze':''">{{ idx+1 }}</div>
            <div class="route-info">
              <p class="route-name">{{ r.ten_tuyen }}</p>
              <div class="route-bar-wrap">
                <div class="route-bar" :style="{width: Math.min(r.doanh_thu/(routeTop[0]?.doanh_thu||1)*100,100)+'%', background: idx<3?'#22c55e':'#3b82f6'}"></div>
              </div>
            </div>
            <div class="route-nums">
              <p class="rn-rev">{{ fmt(r.doanh_thu) }}</p>
              <p class="rn-tkt">{{ r.so_ve }} vé</p>
            </div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-hd"><h3 class="panel-title"><MapPin class="panel-ic" /> Tuyến ít khách</h3></div>
        <div class="route-list">
          <div v-if="routeBottom.length === 0" class="empty-state">—</div>
          <div v-for="(r, idx) in routeBottom" :key="'b-'+r.ten_tuyen" class="route-item">
            <div class="route-rank">{{ idx+1 }}</div>
            <div class="route-info">
              <p class="route-name">{{ r.ten_tuyen }}</p>
              <p class="route-meta">{{ r.so_ve }} vé · {{ fmt(r.doanh_thu) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Chỉ số hiệu suất -->
      <div class="panel">
        <div class="panel-hd"><h3 class="panel-title"><Star class="panel-ic" /> Hiệu Suất Hoạt Động</h3></div>
        <div class="rates-body">
          <!-- Tỉ lệ hoàn thành -->
          <div class="rate-card success">
            <div class="rate-hd"><CheckCircle class="ric green" /><span>Đã thanh toán</span></div>
            <div class="rate-circle-wrap">
              <svg class="rate-circle" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="34" fill="none" stroke="#dcfce7" stroke-width="8"/>
                <circle cx="40" cy="40" r="34" fill="none" stroke="#22c55e" stroke-width="8"
                  stroke-dasharray="213.6"
                  :stroke-dashoffset="213.6*(1-kpi.tyLeHoanThanh/100)"
                  stroke-linecap="round" transform="rotate(-90 40 40)" />
                <text x="40" y="44" text-anchor="middle" class="rate-txt">{{ kpi.tyLeHoanThanh }}%</text>
              </svg>
            </div>
            <p class="rate-desc">{{ kpi.veHoanThanh }} vé đã thanh toán</p>
          </div>
          <!-- Tỉ lệ huỷ -->
          <div class="rate-card danger">
            <div class="rate-hd"><XCircle class="ric red" /><span>Đã huỷ</span></div>
            <div class="rate-circle-wrap">
              <svg class="rate-circle" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="34" fill="none" stroke="#fee2e2" stroke-width="8"/>
                <circle cx="40" cy="40" r="34" fill="none" stroke="#ef4444" stroke-width="8"
                  stroke-dasharray="213.6"
                  :stroke-dashoffset="213.6*(1-kpi.tyLeHuy/100)"
                  stroke-linecap="round" transform="rotate(-90 40 40)" />
                <text x="40" y="44" text-anchor="middle" class="rate-txt red-txt">{{ kpi.tyLeHuy }}%</text>
              </svg>
            </div>
            <p class="rate-desc">{{ kpi.veHuy }} vé bị huỷ</p>
          </div>
          <!-- Lấp đầy ghế -->
          <div class="rate-card info">
            <div class="rate-hd"><BusFront class="ric blue" /><span>Lấp đầy ghế</span></div>
            <div class="rate-circle-wrap">
              <svg class="rate-circle" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="34" fill="none" stroke="#dbeafe" stroke-width="8"/>
                <circle cx="40" cy="40" r="34" fill="none" stroke="#3b82f6" stroke-width="8"
                  stroke-dasharray="213.6"
                  :stroke-dashoffset="213.6*(1-kpi.tyLeLapDay/100)"
                  stroke-linecap="round" transform="rotate(-90 40 40)" />
                <text x="40" y="44" text-anchor="middle" class="rate-txt blue-txt">{{ kpi.tyLeLapDay }}%</text>
              </svg>
            </div>
            <p class="rate-desc">Trung bình lấp đầy</p>
          </div>
        </div>

        <!-- Top tài xế / chuyến -->
        <div class="driver-list">
          <p class="driver-title">Top vé theo chuyến</p>
          <div v-for="d in revenueByDriver" :key="d.ten_tai_xe" class="driver-row">
            <div class="driver-avatar">{{ d.ten_tai_xe.charAt(0) }}</div>
            <div class="driver-info">
              <p class="driver-name">{{ d.ten_tai_xe }}</p>
              <p class="driver-sub">{{ d.so_chuyen }} chuyến · {{ d.so_ve }} vé</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- BẢNG VÉ -->
    <div class="panel">
      <div class="panel-hd">
        <h3 class="panel-title"><Ticket class="panel-ic" /> Danh Sách Vé</h3>
        <div class="panel-hd-right">
          <span v-if="ticketsMeta.total > 0" class="count-badge">{{ ticketsMeta.total }} vé</span>
          <button class="btn-icon-only" :class="{spinning: ticketsLoading}" @click="fetchTickets" id="btn-refresh-tkt">
            <RefreshCw class="ic16" />
          </button>
        </div>
      </div>

      <!-- Bộ lọc bảng vé -->
      <div class="tkt-filter">
        <input v-model="ticketFilter.search" class="tfi" placeholder="Tìm mã vé, khách hàng..." id="tkt-search" @keyup.enter="()=>{ticketFilter.page=1;fetchTickets()}" />
        <select v-model="ticketFilter.trang_thai" class="tfs" id="tkt-status">
          <option value="">Tất cả trạng thái</option>
          <option value="da_thanh_toan">Đã thanh toán</option>
          <option value="dang_cho">Chờ thanh toán</option>
          <option value="huy">Đã hủy</option>
        </select>
        <button class="btn-apply-sm" @click="()=>{ticketFilter.page=1;fetchTickets()}" id="btn-tkt-filter">
          <Filter class="ic14" /> Lọc
        </button>
      </div>

      <!-- Trạng thái loading/error -->
      <div v-if="ticketsLoading" class="state-wrap"><div class="spinner"></div><p>Đang tải...</p></div>
      <div v-else-if="ticketsError" class="state-wrap err"><AlertTriangle class="ic24" /><p>{{ ticketsError }}</p></div>

      <!-- Bảng dữ liệu -->
      <div v-else class="tbl-wrap">
        <table class="data-tbl">
          <thead>
            <tr>
              <th>Mã vé</th>
              <th>Khách hàng</th>
              <th>Tuyến đường</th>
              <th>Chuyến xe</th>
              <th>Giá vé</th>
              <th>Trạng thái</th>
              <th>Ngày đặt</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="tickets.length === 0"><td colspan="7" class="empty-cell">Không có dữ liệu vé</td></tr>
            <tr v-for="v in tickets" :key="v.id">
              <td class="fw6">#{{ v.id ?? v.ma_ve }}</td>
              <td>{{ v.ten_khach ?? v.ho_ten ?? '—' }}</td>
              <td>{{ v.ten_tuyen ?? v.tuyen_duong ?? '—' }}</td>
              <td>{{ v.ma_chuyen ?? v.chuyen_xe_id ?? '—' }}</td>
              <td class="money">{{ fmtFull(v.gia_ve ?? v.so_tien ?? 0) }}</td>
              <td><span class="badge" :class="trangThaiVe(v.tinh_trang ?? v.trang_thai).cls">{{ trangThaiVe(v.tinh_trang ?? v.trang_thai).text }}</span></td>
              <td>{{ fmtDate(v.created_at ?? v.ngay_dat) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Phân trang -->
      <div v-if="ticketsMeta.last_page > 1" class="pagi-bar">
        <span class="pagi-info">Trang {{ ticketsMeta.current_page }} / {{ ticketsMeta.last_page }} · {{ ticketsMeta.total }} vé</span>
        <div class="pagi-ctrl">
          <button class="pb" :disabled="ticketsMeta.current_page===1" @click="tktGoToPage(ticketsMeta.current_page-1)" id="btn-tkt-prev">‹</button>
          <button v-if="tktPageNums[0]>1" class="pb pn" @click="tktGoToPage(1)">1</button>
          <span v-if="tktPageNums[0]>2" class="pdots">…</span>
          <button v-for="p in tktPageNums" :key="p" class="pb pn" :class="{active:p===ticketsMeta.current_page}" @click="tktGoToPage(p)" :id="`btn-tkt-p${p}`">{{ p }}</button>
          <span v-if="tktPageNums[tktPageNums.length-1]<ticketsMeta.last_page-1" class="pdots">…</span>
          <button v-if="tktPageNums[tktPageNums.length-1]<ticketsMeta.last_page" class="pb pn" @click="tktGoToPage(ticketsMeta.last_page)">{{ ticketsMeta.last_page }}</button>
          <button class="pb" :disabled="ticketsMeta.current_page===ticketsMeta.last_page" @click="tktGoToPage(ticketsMeta.current_page+1)" id="btn-tkt-next">›</button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
/* ── BASE ── */
.tk-page { padding: 8px 0 40px; font-family: 'Inter', system-ui, sans-serif; }

/* ── HEADER ── */
.tk-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.tk-header-left { display:flex; align-items:center; gap:16px; }
.tk-icon-wrap { width:52px; height:52px; background:linear-gradient(135deg,#22c55e,#15803d); border-radius:16px; display:flex; align-items:center; justify-content:center; box-shadow:0 6px 20px rgba(34,197,94,.35); }
.tk-icon  { width:26px; height:26px; color:white; }
.tk-title { font-size:24px; font-weight:800; color:#0d4f35; margin:0; }
.tk-subtitle { font-size:13px; color:#64748b; margin:4px 0 0; }
.tk-header-actions { display:flex; gap:10px; align-items:center; }
.btn-icon-only { width:40px; height:40px; border-radius:10px; border:1.5px solid #e2e8f0; background:white; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all .2s; color:#475569; }
.btn-icon-only:hover { border-color:#22c55e; color:#16a34a; background:#f0fdf4; }
.btn-icon-only.spinning .ic16 { animation:spin .8s linear infinite; }
.btn-export { display:flex; align-items:center; gap:6px; padding:0 18px; height:40px; border-radius:10px; background:linear-gradient(135deg,#22c55e,#15803d); color:white; font-weight:700; font-size:13px; border:none; cursor:pointer; box-shadow:0 3px 10px rgba(34,197,94,.3); transition:all .25s; }
.btn-export:hover { transform:translateY(-1px); }
.btn-export--light { background:white; color:#0f172a; border:1.5px solid #e2e8f0; box-shadow:none; }
.btn-export--light:hover { background:#f8fafc; }
@keyframes spin { to { transform:rotate(360deg); } }

/* ── FILTER ── */
.filter-card { background:white; border-radius:16px; padding:20px 24px; margin-bottom:24px; box-shadow:0 2px 12px rgba(0,0,0,.05); border:1px solid #f1f5f9; }
.filter-tabs { display:flex; gap:6px; margin-bottom:16px; flex-wrap:wrap; }
.filter-tab { display:flex; align-items:center; gap:6px; padding:8px 16px; border-radius:10px; border:1.5px solid #e2e8f0; background:white; color:#64748b; font-size:13px; font-weight:500; cursor:pointer; transition:all .2s; }
.filter-tab:hover { border-color:#22c55e; color:#16a34a; }
.filter-tab.active { background:linear-gradient(135deg,#f0fdf4,#dcfce7); border-color:#22c55e; color:#15803d; font-weight:700; }
.filter-row { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; }
.fg { display:flex; flex-direction:column; gap:5px; }
.fg label { font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; display:flex; align-items:center; gap:4px; }
.fi { height:40px; padding:0 14px; border-radius:10px; border:1.5px solid #e2e8f0; font-size:14px; color:#374151; background:#f8fafc; outline:none; min-width:160px; transition:border-color .2s; }
.fi:focus { border-color:#22c55e; background:white; }
.fsep { color:#94a3b8; font-size:18px; padding-bottom:6px; }
.qwrap { display:flex; align-items:center; gap:8px; }
.qsel { min-width:80px !important; }
.btn-apply { display:flex; align-items:center; gap:6px; padding:0 22px; height:40px; border-radius:10px; background:linear-gradient(135deg,#22c55e,#15803d); color:white; font-size:13px; font-weight:700; border:none; cursor:pointer; box-shadow:0 3px 10px rgba(34,197,94,.3); transition:all .25s; }
.btn-apply:disabled { opacity:.6; cursor:not-allowed; }
.btn-apply:not(:disabled):hover { transform:translateY(-1px); }
.bspinner { width:14px; height:14px; border:2px solid rgba(255,255,255,.4); border-top-color:white; border-radius:50%; animation:spin .7s linear infinite; }

/* ── KPI GRID ── */
.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; margin-bottom:24px; }
.kpi-card { background:white; border-radius:16px; padding:20px; box-shadow:0 4px 20px rgba(0,0,0,.06); border:1px solid #f1f5f9; transition:transform .25s, box-shadow .25s; }
.kpi-card:hover { transform:translateY(-3px); box-shadow:0 8px 30px rgba(0,0,0,.1); }
.kpi-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
.kpi-icon-w { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; }
.kig-green  { background:linear-gradient(135deg,#22c55e,#15803d); }
.kig-blue   { background:linear-gradient(135deg,#3b82f6,#1d4ed8); }
.kig-indigo { background:linear-gradient(135deg,#6366f1,#4338ca); }
.kig-orange { background:linear-gradient(135deg,#f59e0b,#d97706); }
.kpi-ic { width:22px; height:22px; color:white; }
.kbadge { display:flex; align-items:center; gap:3px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.kbadge.up { background:#dcfce7; color:#15803d; }
.kbadge.dn { background:#fee2e2; color:#dc2626; }
.kpi-label { font-size:12px; color:#64748b; font-weight:600; text-transform:uppercase; margin:0 0 6px; }
.kpi-val   { font-size:26px; font-weight:800; color:#0f172a; margin:0 0 4px; }
.kpi-sub   { font-size:12px; color:#94a3b8; margin:0; }

/* ── BIỂU ĐỒ ── */
.chart-card { background:white; border-radius:16px; padding:20px 24px; margin-bottom:24px; box-shadow:0 2px 12px rgba(0,0,0,.05); border:1px solid #f1f5f9; }
.chart-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.chart-tabs { display:flex; gap:6px; }
.ctab { padding:7px 16px; border-radius:9px; border:1.5px solid #e2e8f0; background:white; color:#64748b; font-size:13px; font-weight:500; cursor:pointer; transition:all .2s; }
.ctab:hover { border-color:#22c55e; color:#16a34a; }
.ctab.active { background:linear-gradient(135deg,#22c55e,#15803d); border-color:transparent; color:white; font-weight:700; }
.chart-body { display:grid; grid-template-columns:2fr 1fr; gap:20px; }
.chart-wrap { height:280px; }
.side-title { font-size:13px; font-weight:600; color:#374151; margin:0 0 12px; }
.donut-wrap { height:220px; }

/* ── BOTTOM GRID ── */
.bottom-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
.bottom-grid--triple { grid-template-columns: repeat(3, 1fr); }
.route-meta { font-size:11px; color:#94a3b8; margin:4px 0 0; }

/* ── PANEL CHUNG ── */
.panel { background:white; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,.05); border:1px solid #f1f5f9; margin-bottom:24px; overflow:hidden; }
.panel-hd { display:flex; align-items:center; justify-content:space-between; padding:18px 20px; border-bottom:1px solid #f8fafc; }
.panel-hd-right { display:flex; align-items:center; gap:8px; }
.panel-title { display:flex; align-items:center; gap:8px; font-size:15px; font-weight:700; color:#0f172a; margin:0; }
.panel-ic { width:18px; height:18px; color:#22c55e; }
.count-badge { background:linear-gradient(135deg,#f0fdf4,#dcfce7); border:1px solid #bbf7d0; color:#15803d; font-size:12px; font-weight:600; padding:4px 12px; border-radius:20px; }

/* ── DANH SÁCH TUYẾN ── */
.route-list { padding:12px 20px 20px; }
.route-item { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid #f8fafc; }
.route-item:last-child { border-bottom:none; }
.route-rank { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; background:#f1f5f9; color:#64748b; flex-shrink:0; }
.route-rank.gold   { background:linear-gradient(135deg,#fef08a,#eab308); color:#713f12; }
.route-rank.silver { background:linear-gradient(135deg,#e2e8f0,#94a3b8); color:#1e293b; }
.route-rank.bronze { background:linear-gradient(135deg,#fed7aa,#f97316); color:#431407; }
.route-info { flex:1; min-width:0; }
.route-name { font-size:13px; font-weight:600; color:#1e293b; margin:0 0 6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.route-bar-wrap { height:5px; background:#f1f5f9; border-radius:10px; overflow:hidden; }
.route-bar { height:100%; border-radius:10px; transition:width .5s ease; }
.route-nums { text-align:right; flex-shrink:0; }
.rn-rev { font-size:13px; font-weight:700; color:#15803d; margin:0 0 2px; }
.rn-tkt { font-size:11px; color:#94a3b8; margin:0; }
.empty-state { text-align:center; padding:32px; color:#94a3b8; font-size:14px; }

/* ── HIỆU SUẤT ── */
.rates-body { display:flex; gap:16px; padding:16px 20px; justify-content:space-around; flex-wrap:wrap; }
.rate-card  { display:flex; flex-direction:column; align-items:center; gap:8px; }
.rate-hd    { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:#475569; }
.ric { width:15px; height:15px; }
.ric.green { color:#22c55e; } .ric.red { color:#ef4444; } .ric.blue { color:#3b82f6; }
.rate-circle-wrap { position:relative; }
.rate-circle { width:80px; height:80px; }
.rate-txt { font-size:13px; font-weight:800; fill:#0f172a; }
.red-txt  { fill:#ef4444; }
.blue-txt { fill:#3b82f6; }
.rate-desc { font-size:10px; color:#94a3b8; text-align:center; }

/* ── TÀI XẾ / CHUYẾN ── */
.driver-list  { padding:0 20px 16px; border-top:1px solid #f1f5f9; }
.driver-title { font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; margin:14px 0 8px; }
.driver-row   { display:flex; align-items:center; gap:10px; padding:8px 0; }
.driver-avatar{ width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,#22c55e,#15803d); display:flex; align-items:center; justify-content:center; color:white; font-weight:800; font-size:14px; flex-shrink:0; }
.driver-info  { flex:1; min-width:0; }
.driver-name  { font-size:13px; font-weight:600; color:#1e293b; margin:0 0 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.driver-sub   { font-size:11px; color:#94a3b8; margin:0; }

/* ── BẢNG VÉ ── */
.tkt-filter { display:flex; align-items:center; gap:10px; padding:14px 20px; border-bottom:1px solid #f1f5f9; background:#fafafa; flex-wrap:wrap; }
.tfi { height:38px; padding:0 12px; border-radius:9px; border:1.5px solid #e2e8f0; font-size:13px; background:white; outline:none; min-width:200px; }
.tfi:focus { border-color:#22c55e; }
.tfs { height:38px; padding:0 10px; border-radius:9px; border:1.5px solid #e2e8f0; font-size:13px; background:white; outline:none; cursor:pointer; }
.btn-apply-sm { display:flex; align-items:center; gap:6px; height:38px; padding:0 16px; border-radius:9px; background:linear-gradient(135deg,#22c55e,#15803d); color:white; font-size:13px; font-weight:600; border:none; cursor:pointer; }
.state-wrap { display:flex; flex-direction:column; align-items:center; gap:8px; padding:48px; color:#64748b; font-size:14px; }
.state-wrap.err { color:#ef4444; }
.ic24 { width:24px; height:24px; }
.spinner { width:28px; height:28px; border:3px solid #e2e8f0; border-top-color:#22c55e; border-radius:50%; animation:spin .8s linear infinite; }
.tbl-wrap { overflow-x:auto; }
.data-tbl { width:100%; border-collapse:collapse; font-size:13px; }
.data-tbl th { padding:12px 16px; text-align:left; background:#f8fafc; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid #f1f5f9; white-space:nowrap; }
.data-tbl td { padding:13px 16px; border-bottom:1px solid #f9fafb; color:#374151; }
.data-tbl tr:hover td { background:#f0fdf4; }
.data-tbl tr:last-child td { border-bottom:none; }
.fw6   { font-weight:600; color:#1e293b; }
.money { font-weight:700; color:#15803d; }
.empty-cell { text-align:center; color:#94a3b8; padding:40px; font-size:14px; }

/* ── BADGE ── */
.badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-green  { background:#dcfce7; color:#15803d; }
.badge-red    { background:#fee2e2; color:#dc2626; }
.badge-yellow { background:#fef9c3; color:#a16207; }

/* ── PHÂN TRANG ── */
.pagi-bar  { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-top:1px solid #f1f5f9; flex-wrap:wrap; gap:10px; }
.pagi-info { font-size:13px; color:#64748b; }
.pagi-ctrl { display:flex; align-items:center; gap:5px; }
.pb  { min-width:36px; height:36px; padding:0 8px; border-radius:9px; border:1.5px solid #e2e8f0; background:white; color:#475569; font-size:13px; font-weight:500; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .2s; }
.pb:hover:not(:disabled) { border-color:#22c55e; color:#16a34a; background:#f0fdf4; }
.pb:disabled { opacity:.35; cursor:not-allowed; }
.pn { min-width:36px; padding:0; }
.pn.active { background:linear-gradient(135deg,#22c55e,#15803d); border-color:transparent; color:white; font-weight:700; box-shadow:0 3px 10px rgba(34,197,94,.35); }
.pdots { color:#94a3b8; line-height:36px; padding:0 4px; }

/* ── ICON SIZE HELPERS ── */
.ic12 { width:12px; height:12px; } .ic13 { width:13px; height:13px; }
.ic14 { width:14px; height:14px; } .ic16 { width:16px; height:16px; }

/* ── RESPONSIVE ── */
@media (max-width: 1280px) {
  .kpi-grid    { grid-template-columns: repeat(2,1fr); }
  .chart-body  { grid-template-columns: 1fr; }
  .bottom-grid { grid-template-columns: 1fr; }
  .bottom-grid--triple { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .kpi-grid { grid-template-columns: 1fr 1fr; }
  .rates-body { justify-content:flex-start; }
  .tfi { min-width:140px; }
}
@media (max-width: 480px) {
  .kpi-grid { grid-template-columns: 1fr; }
  .tk-title { font-size:18px; }
}
</style>
