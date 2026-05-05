<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  TrendingUp, DollarSign, Ticket, BusFront, Users,
  Calendar, Download, RefreshCw, Filter, MapPin,
  AlertTriangle, ArrowUpRight, Star,
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

const endOfMonth = (year, month) => new Date(year, month, 0).toISOString().slice(0, 10)

const buildParams = () => {
  if (filterType.value === 'range') return { tu_ngay: dateFrom.value, den_ngay: dateTo.value }
  if (filterType.value === 'month') {
    const [year, month] = selectedMonth.value.split('-').map(Number)
    return { tu_ngay: `${selectedMonth.value}-01`, den_ngay: endOfMonth(year, month) }
  }
  if (filterType.value === 'quarter') {
    const q = Number(selectedQuarterNum.value)
    const year = Number(selectedQuarterYear.value)
    const startMonth = (q - 1) * 3 + 1
    const endMonth = q * 3
    return {
      tu_ngay: `${year}-${String(startMonth).padStart(2, '0')}-01`,
      den_ngay: endOfMonth(year, endMonth),
    }
  }
  const y = Number(selectedYear.value)
  return { tu_ngay: `${y}-01-01`, den_ngay: `${y}-12-31` }
}

const kpi = ref({ tongDoanhThu: 0, tongVe: 0, tongChuyenXe: 0, tongKhachHang: 0 })
const isLoading = ref(false)
const exportLoading = ref(false)

// ── Toast Notification ─────────────────────────────────────────────
const toasts = ref([])
let _toastId = 0
const showToast = (message, type = 'success', duration = 4000) => {
  const id = ++_toastId
  toasts.value.push({ id, message, type })
  if (duration > 0) setTimeout(() => { toasts.value = toasts.value.filter(t => t.id !== id) }, duration)
  return id
}
const removeToast = (id) => { toasts.value = toasts.value.filter(t => t.id !== id) }
const removeToastByType = (type) => { toasts.value = toasts.value.filter(t => t.type !== type) }

const MONTHS = ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12']
const revenueByTime = ref(Array(12).fill(0))
const ticketsByTime = ref(Array(12).fill(0))
const revenueByRoute = ref([])
const topTrips = ref([])
const topCustomers = ref([])
const pieBreakdown = ref([0, 0, 0])
const activeChartTab = ref('revenue')



const fmt = (n) => {
  n = Number(n) || 0
  if (n >= 1e9) return (n / 1e9).toFixed(2) + ' tỷ'
  if (n >= 1e6) return (n / 1e6).toFixed(1) + ' tr'
  return n.toLocaleString('vi-VN') + ' đ'
}
const fmtFull = (n) => (Number(n) || 0).toLocaleString('vi-VN') + ' ₫'
const fmtDate = (d) => d ? new Date(d).toLocaleString('vi-VN') : '—'

// ── Fetch thống kê ────────────────────────────────────────────────
const fetchStats = async () => {
  isLoading.value = true
  try {
    const res = await operatorApi.getStatistics(buildParams())
    const data = res?.data?.data ?? res?.data ?? res
    kpi.value.tongDoanhThu = Number(data.tong_doanh_thu || 0)
    kpi.value.tongVe = Number(data.tong_ve_ban || 0)
    kpi.value.tongChuyenXe = Number(data.tong_chuyen_xe || 0)
    kpi.value.tongKhachHang = Number(data.tong_khach_hang || 0)

    const theoThoiGian = Array.isArray(data.theo_thoi_gian) ? data.theo_thoi_gian : []
    const rev = Array(12).fill(0)
    const tkt = Array(12).fill(0)
    theoThoiGian.forEach((row) => {
      const dateKey = row.ngay || row.period
      const dateObj = dateKey ? new Date(dateKey) : null
      if (!dateObj || Number.isNaN(dateObj.getTime())) return
      const m = dateObj.getMonth()
      rev[m] += Number(row.doanh_thu || 0)
      tkt[m] += Number(row.so_ve || 0)
    })
    revenueByTime.value = rev
    ticketsByTime.value = tkt

    const routeRes = await operatorApi.getStatisticsByRoute(buildParams())
    const routeData = routeRes?.data?.data ?? routeRes?.data ?? routeRes
    revenueByRoute.value = Array.isArray(routeData) ? routeData : []

    const ticketStatusRes = await operatorApi.getStatisticsTicketStatus(buildParams())
    const ticketStatusData = ticketStatusRes?.data?.data ?? ticketStatusRes?.data ?? ticketStatusRes
    pieBreakdown.value = [
      Number(ticketStatusData?.hoan_thanh || 0),
      Number(ticketStatusData?.da_huy || 0),
      Number(ticketStatusData?.cho_xac_nhan || 0),
    ]

    topTrips.value = Array.isArray(data.top_chuyen_xe) ? data.top_chuyen_xe : []
    topCustomers.value = Array.isArray(data.top_khach_hang) ? data.top_khach_hang : []

    showToast('Tải dữ liệu thành công', 'success')
  } catch (e) {
    console.error('[ThongKe Operator] lỗi:', e)
    showToast('Mất kết nối với Database', 'error')
  } finally {
    isLoading.value = false
  }
}

const handleApply = () => {
  fetchStats()
}

// ── Fetch TOÀN BỘ vé để export (duyệt hết trang, lọc date client-side) ─────
// BE /v1/nha-xe/ve không có filter tu_ngay/den_ngay nên lọc ở client
const fetchAllTicketsForExport = async () => {
  const params = buildParams()
  const fromMs = params.tu_ngay ? new Date(params.tu_ngay).getTime() : null
  const toMs = params.den_ngay ? new Date(params.den_ngay + 'T23:59:59').getTime() : null

  const acc = []
  let page = 1
  const perPage = 200

  do {
    const fetchParams = { per_page: perPage, page }
    const res = await operatorApi.getStatisticsTickets(fetchParams)
    const p = res?.data?.data ?? res?.data ?? res
    const chunk = p?.data ?? []
    acc.push(...chunk)
    const lastPage = p?.last_page ?? 1
    if (page >= lastPage || chunk.length < perPage) break
    page++
    if (page > 100) break // bảo vệ vòng lặp
  } while (true)

  // Lọc theo khoảng ngày client-side
  if (!fromMs && !toMs) return acc
  return acc.filter((v) => {
    const raw = v.thoi_gian_dat ?? v.created_at ?? v.ngay_dat ?? ''
    if (!raw) return true
    const d = new Date(raw).getTime()
    if (isNaN(d)) return true
    if (fromMs && d < fromMs) return false
    if (toMs && d > toMs) return false
    return true
  })
}

// ── Helpers ────────────────────────────────────────────────────────
const getPeriodLabel = () => {
  const p = buildParams()
  return `${p.tu_ngay} → ${p.den_ngay}`
}

const trangThaiVe = (tt) => {
  if (tt === 'da_thanh_toan' || tt === 'hoan_thanh' || tt == 1 || tt === 'confirmed') return { text: 'Hoàn thành', cls: 'badge-green' }
  if (tt === 'huy' || tt === 'da_huy' || tt == 0 || tt === 'cancelled') return { text: 'Đã huỷ', cls: 'badge-red' }
  if (tt === 'dang_cho' || tt === 'cho_xac_nhan' || tt === 'pending') return { text: 'Chờ xác nhận', cls: 'badge-yellow' }
  return { text: tt ?? '—', cls: '' }
}

const phuongThucLabel = (pt) => {
  if (!pt) return 'Khác'
  const v = String(pt).toLowerCase()
  if (v === 'tien_mat') return 'Tiền mặt'
  if (v === 'chuyen_khoan') return 'Chuyển khoản'
  if (v === 'vnpay') return 'VNPay'
  if (v === 'momo') return 'MoMo'
  if (v === 'zalopay') return 'ZaloPay'
  return pt
}

const escXml = (v) => String(v ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

const buildXlsSheet = (name, rows) => {
  let out = `<Worksheet ss:Name="${escXml(name)}"><Table>`
  rows.forEach((row) => {
    out += '<Row>'
    if (Array.isArray(row)) {
      row.forEach((cell) => {
        if (cell === '' || cell === null || cell === undefined) {
          out += '<Cell><Data ss:Type="String"></Data></Cell>'
        } else if (typeof cell === 'number') {
          out += `<Cell><Data ss:Type="Number">${cell}</Data></Cell>`
        } else {
          out += `<Cell><Data ss:Type="String">${escXml(cell)}</Data></Cell>`
        }
      })
    }
    out += '</Row>'
  })
  out += '</Table></Worksheet>'
  return out
}

// ── Xuất Excel (XML Spreadsheet .xls) ────────────────────────────
const exportExcel = async () => {
  if (exportLoading.value) return
  exportLoading.value = true
  const loadingToastId = showToast('⏳ Đang tải toàn bộ danh sách vé...', 'warning', 0)
  try {
    const now = new Date()
    const period = getPeriodLabel()

    // Fetch toàn bộ vé trong kỳ lọc
    let allTickets = []
    try {
      allTickets = await fetchAllTicketsForExport()
    } catch (e) {
      console.warn('[ExportExcel] fetch vé thất bại:', e)
      allTickets = []
    }
    removeToast(loadingToastId)

    // Sheet 1: Tổng quan KPI
    const s1 = [
      ['THỐNG KÊ & BÁO CÁO DOANH THU NHÀ XE', '', '', ''],
      [`Kỳ báo cáo: ${period}`, '', '', ''],
      [`Thời gian xuất: ${now.toLocaleString('vi-VN')}`, '', '', ''],
      [],
      ['CHỈ SỐ', 'GIÁ TRỊ', 'GHI CHÚ', ''],
      ['Tổng doanh thu (VNĐ)', kpi.value.tongDoanhThu, 'Trong kỳ lọc', ''],
      ['Tổng vé bán', kpi.value.tongVe, '', ''],
      ['Tổng chuyến xe', kpi.value.tongChuyenXe, '', ''],
      ['Tổng khách hàng', kpi.value.tongKhachHang, '', ''],
      [],
      ['TRẠNG THÁI VÉ', 'SỐ LƯỢNG', '', ''],
      ['Hoàn thành', pieBreakdown.value[0], '', ''],
      ['Đã huỷ', pieBreakdown.value[1], '', ''],
      ['Chờ xác nhận', pieBreakdown.value[2], '', ''],
    ]

    // Sheet 2: Doanh thu theo tháng
    const s2 = [
      [`DOANH THU THEO THÁNG – ${period}`, '', '', ''],
      [],
      ['Tháng', 'Doanh thu (VNĐ)', 'Số vé', 'Doanh thu (triệu đ)'],
    ]
    const totalRev = revenueByTime.value.reduce((a, b) => a + b, 0)
    const totalTkt = ticketsByTime.value.reduce((a, b) => a + b, 0)
    MONTHS.forEach((m, i) => {
      s2.push([m, revenueByTime.value[i], ticketsByTime.value[i], +(revenueByTime.value[i] / 1e6).toFixed(3)])
    })
    s2.push([])
    s2.push(['TỔNG CỘNG', totalRev, totalTkt, +(totalRev / 1e6).toFixed(3)])

    // Sheet 3: Doanh thu theo tuyến
    const s3 = [
      [`DOANH THU THEO TUYẾN – ${period}`, '', ''],
      [],
      ['Tuyến đường', 'Doanh thu (VNĐ)', 'Số vé'],
    ]
    revenueByRoute.value.forEach((r) => {
      s3.push([r.ten_tuyen_duong ?? r.tuyen ?? '—', Number(r.doanh_thu ?? 0), Number(r.so_ve ?? 0)])
    })
    if (!revenueByRoute.value.length) s3.push(['Chưa có dữ liệu', 0, 0])

    // Sheet 4: Top chuyến xe
    const s4 = [
      [`TOP CHUYẾN XE – ${period}`, '', ''],
      [],
      ['Tuyến', 'Số vé', 'Doanh thu (VNĐ)'],
    ]
    topTrips.value.forEach((d) => {
      s4.push([d.ten_tuyen_duong ?? ('Chuyến #' + d.id_chuyen_xe), Number(d.so_ve ?? 0), Number(d.tong_doanh_thu ?? 0)])
    })
    if (!topTrips.value.length) s4.push(['Chưa có dữ liệu', 0, 0])

    // Sheet 5: Top khách hàng
    const s5 = [
      [`TOP KHÁCH HÀNG – ${period}`, '', ''],
      [],
      ['Khách hàng', 'Số vé', 'Tổng chi tiêu (VNĐ)'],
    ]
    topCustomers.value.forEach((c) => {
      s5.push([c.ten_khach_hang ?? '—', Number(c.so_ve ?? 0), Number(c.tong_doanh_thu ?? 0)])
    })
    if (!topCustomers.value.length) s5.push(['Chưa có dữ liệu', 0, 0])

    // Sheet 6: Danh sách vé ĐẦY ĐỦ
    const s6 = [
      [`DANH SÁCH VÉ ĐẦY ĐỦ – ${period}`, '', '', '', '', '', '', '', '', ''],
      [`Tổng số vé: ${allTickets.length}`, '', '', '', '', '', '', '', '', ''],
      [],
      ['STT', 'Mã vé', 'Khách hàng', 'SĐT', 'Tuyến đường', 'Thời gian KH', 'Tổng tiền (VNĐ)', 'Phương thức TT', 'Trạng thái', 'Thời gian đặt'],
    ]
    allTickets.forEach((v, idx) => {
      const kh = v.khach_hang ?? v.khachHang ?? {}
      const tt = trangThaiVe(v.tinh_trang)
      s6.push([
        idx + 1,
        v.ma_ve ?? String(v.id ?? '—'),
        kh.ho_va_ten ?? v.ten_khach ?? v.ho_ten ?? '—',
        kh.so_dien_thoai ?? v.sdt ?? '—',
        v.chuyen_xe?.tuyen_duong?.ten_tuyen_duong ?? v.ten_tuyen ?? '—',
        v.chuyen_xe?.gio_khoi_hanh ?? v.chuyen_xe?.ngay_khoi_hanh ?? '—',
        Number(v.tong_tien ?? v.gia_ve ?? v.so_tien ?? 0),
        phuongThucLabel(v.phuong_thuc_thanh_toan),
        tt.text,
        fmtDate(v.thoi_gian_dat ?? v.created_at ?? ''),
      ])
    })
    if (!allTickets.length) s6.push([0, '—', '—', '—', '—', '—', 0, '—', '—', '—'])

    const xlsHeader = `<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
  <Style ss:ID="title"><Font ss:Bold="1" ss:Size="14"/><Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#15803d" ss:Pattern="Solid"/></Style>
  <Style ss:ID="money"><NumberFormat ss:Format="#,##0"/></Style>
  <Style ss:ID="bold"><Font ss:Bold="1"/></Style>
</Styles>`

    const xlsContent = xlsHeader
      + buildXlsSheet('Tổng quan', s1)
      + buildXlsSheet('Doanh thu theo tháng', s2)
      + buildXlsSheet('Doanh thu theo tuyến', s3)
      + buildXlsSheet('Top chuyến xe', s4)
      + buildXlsSheet('Top khách hàng', s5)
      + buildXlsSheet('Danh sách vé', s6)
      + '</Workbook>'

    const blob = new Blob([xlsContent], { type: 'application/vnd.ms-excel;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    const safePeriod = period.replace(/[/\\: →]/g, '_')
    a.href = url
    a.download = `ThongKe_NhaXe_${safePeriod}_${now.toISOString().slice(0, 10)}.xls`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)

    showToast(`✅ Xuất Excel thành công! ${allTickets.length} vé — File đã tải về.`, 'success')
  } catch (err) {
    removeToast(loadingToastId)
    console.error('[ExportExcel Operator]', err)
    showToast('❌ Xuất Excel thất bại: ' + (err?.message ?? 'Lỗi không xác định'), 'error')
  } finally {
    exportLoading.value = false
  }
}

// ── Xuất PDF (HTML print window) ──────────────────────────────────
const exportPdf = async () => {
  if (exportLoading.value) return
  exportLoading.value = true
  const loadingToastId = showToast('⏳ Đang tải toàn bộ danh sách vé...', 'warning', 0)
  try {
    const period = getPeriodLabel()
    const now = new Date()

    // Fetch toàn bộ vé trong kỳ lọc
    let allTickets = []
    try {
      allTickets = await fetchAllTicketsForExport()
    } catch (e) {
      console.warn('[ExportPdf] fetch vé thất bại:', e)
      allTickets = []
    }
    removeToast(loadingToastId)

    const rowsRoute = revenueByRoute.value.map((r) =>
      `<tr><td>${r.ten_tuyen_duong ?? r.tuyen ?? '—'}</td><td style="text-align:right">${Number(r.doanh_thu ?? 0).toLocaleString('vi-VN')}</td><td style="text-align:right">${r.so_ve ?? 0}</td></tr>`
    ).join('') || '<tr><td colspan="3" style="text-align:center;color:#94a3b8">Chưa có dữ liệu</td></tr>'

    const rowsTrips = topTrips.value.map((d) =>
      `<tr><td>${d.ten_tuyen_duong ?? ('Chuyến #' + d.id_chuyen_xe)}</td><td style="text-align:right">${Number(d.so_ve ?? 0)}</td><td style="text-align:right">${Number(d.tong_doanh_thu ?? 0).toLocaleString('vi-VN')}</td></tr>`
    ).join('') || '<tr><td colspan="3" style="text-align:center;color:#94a3b8">Chưa có dữ liệu</td></tr>'

    const rowsCustomers = topCustomers.value.map((c) =>
      `<tr><td>${c.ten_khach_hang ?? '—'}</td><td style="text-align:right">${Number(c.so_ve ?? 0)}</td><td style="text-align:right">${Number(c.tong_doanh_thu ?? 0).toLocaleString('vi-VN')}</td></tr>`
    ).join('') || '<tr><td colspan="3" style="text-align:center;color:#94a3b8">Chưa có dữ liệu</td></tr>'

    const rowsMonthly = MONTHS.map((m, i) =>
      `<tr><td>${m}</td><td style="text-align:right">${revenueByTime.value[i].toLocaleString('vi-VN')}</td><td style="text-align:right">${ticketsByTime.value[i]}</td></tr>`
    ).join('')

    // Danh sách vé đầy đủ với thông tin chi tiết
    const rowsTickets = allTickets.map((v, idx) => {
      const kh = v.khach_hang ?? v.khachHang ?? {}
      const tt = trangThaiVe(v.tinh_trang)
      const tenKhach = kh.ho_va_ten ?? v.ten_khach ?? v.ho_ten ?? '—'
      const sdt = kh.so_dien_thoai ?? v.sdt ?? '—'
      const tuyen = v.chuyen_xe?.tuyen_duong?.ten_tuyen_duong ?? v.ten_tuyen ?? '—'
      const tienTe = Number(v.tong_tien ?? v.gia_ve ?? v.so_tien ?? 0)
      const pt = phuongThucLabel(v.phuong_thuc_thanh_toan)
      const ngayDat = fmtDate(v.thoi_gian_dat ?? v.created_at ?? '')
      const badge = tt.cls === 'badge-green' ? 'badge-ht' : tt.cls === 'badge-red' ? 'badge-huy' : 'badge-cho'
      return `<tr>
        <td style="text-align:center">${idx + 1}</td>
        <td><strong>${v.ma_ve ?? ('#' + v.id) ?? '—'}</strong></td>
        <td>${tenKhach}</td>
        <td>${sdt}</td>
        <td>${tuyen}</td>
        <td style="text-align:right">${tienTe.toLocaleString('vi-VN')}</td>
        <td>${pt}</td>
        <td class="${badge}">${tt.text}</td>
        <td>${ngayDat}</td>
      </tr>`
    }).join('') || '<tr><td colspan="9" style="text-align:center;color:#94a3b8">Không có dữ liệu vé</td></tr>'

    const w = window.open('', '_blank')
    if (!w) {
      showToast('⚠️ Trình duyệt đã chặn popup. Vui lòng cho phép popup để xuất PDF.', 'warning')
      exportLoading.value = false
      return
    }

    w.document.write(`<!DOCTYPE html><html lang="vi"><head>
<meta charset="utf-8"/>
<title>Báo cáo thống kê nhà xe — ${period}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Arial,sans-serif;padding:24px 28px;color:#0f172a;font-size:12px}
  .header{border-bottom:3px solid #15803d;padding-bottom:14px;margin-bottom:18px}
  .header h1{font-size:20px;color:#15803d;font-weight:800}
  .header p{color:#64748b;font-size:11px;margin-top:4px}
  .kpi-grid{display:flex;gap:12px;margin:16px 0;flex-wrap:wrap}
  .kpi-box{flex:1;min-width:130px;border:1.5px solid #d1fae5;border-radius:8px;padding:12px;background:#f0fdf4}
  .kpi-box .label{font-size:10px;color:#64748b;font-weight:700;text-transform:uppercase;margin-bottom:4px}
  .kpi-box .value{font-size:16px;font-weight:800;color:#15803d}
  .kpi-box .full{font-size:10px;color:#64748b;margin-top:2px}
  h2{font-size:13px;font-weight:700;color:#0f172a;margin:18px 0 8px;border-left:4px solid #22c55e;padding-left:8px}
  .tbl-info{font-size:11px;color:#64748b;margin-bottom:6px}
  table{border-collapse:collapse;width:100%;margin-bottom:6px}
  th{background:#15803d;color:#fff;padding:7px 8px;text-align:left;font-size:10px}
  td{border:1px solid #e2e8f0;padding:5px 7px;font-size:10px}
  tr:nth-child(even) td{background:#f8fafc}
  .badge-ht{color:#15803d;font-weight:700}
  .badge-huy{color:#dc2626;font-weight:700}
  .badge-cho{color:#d97706;font-weight:700}
  .footer{margin-top:18px;text-align:center;font-size:10px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:10px}
  @media print{body{padding:10px}}
</style>
</head><body>
<div class="header">
  <h1>📊 Báo Cáo Thống Kê Doanh Thu Nhà Xe</h1>
  <p>Kỳ báo cáo: <strong>${period}</strong> &nbsp;|&nbsp; Xuất lúc: ${now.toLocaleString('vi-VN')}</p>
</div>

<div class="kpi-grid">
  <div class="kpi-box"><div class="label">Tổng doanh thu</div><div class="value">${fmt(kpi.value.tongDoanhThu)}</div><div class="full">${fmtFull(kpi.value.tongDoanhThu)}</div></div>
  <div class="kpi-box"><div class="label">Tổng vé bán</div><div class="value">${kpi.value.tongVe.toLocaleString()}</div><div class="full">Vé trong kỳ lọc</div></div>
  <div class="kpi-box"><div class="label">Chuyến xe</div><div class="value">${kpi.value.tongChuyenXe.toLocaleString()}</div><div class="full">Tổng chuyến có vé</div></div>
  <div class="kpi-box"><div class="label">Khách hàng</div><div class="value">${kpi.value.tongKhachHang.toLocaleString()}</div><div class="full">Khách đặt vé kỳ này</div></div>
</div>

<h2>Trạng thái vé</h2>
<table><thead><tr><th>Trạng thái</th><th>Số lượng</th></tr></thead>
<tbody>
  <tr><td class="badge-ht">✅ Hoàn thành</td><td style="text-align:right">${pieBreakdown.value[0]}</td></tr>
  <tr><td class="badge-huy">❌ Đã huỷ</td><td style="text-align:right">${pieBreakdown.value[1]}</td></tr>
  <tr><td class="badge-cho">⏳ Chờ xác nhận</td><td style="text-align:right">${pieBreakdown.value[2]}</td></tr>
</tbody></table>

<h2>Doanh thu theo tháng</h2>
<table><thead><tr><th>Tháng</th><th>Doanh thu (₫)</th><th>Số vé</th></tr></thead><tbody>${rowsMonthly}</tbody></table>

<h2>Doanh thu theo tuyến</h2>
<table><thead><tr><th>Tuyến đường</th><th>Doanh thu (₫)</th><th>Số vé</th></tr></thead><tbody>${rowsRoute}</tbody></table>

<h2>Top chuyến xe</h2>
<table><thead><tr><th>Tuyến</th><th>Số vé</th><th>Doanh thu (₫)</th></tr></thead><tbody>${rowsTrips}</tbody></table>

<h2>Top khách hàng</h2>
<table><thead><tr><th>Khách hàng</th><th>Số vé</th><th>Tổng chi tiêu (₫)</th></tr></thead><tbody>${rowsCustomers}</tbody></table>

<h2>Danh Sách Vé Đặt — Đầy Đủ (${allTickets.length} vé)</h2>
<p class="tbl-info">Toàn bộ vé trong kỳ lọc: ${period}</p>
<table>
  <thead><tr><th>STT</th><th>Mã vé</th><th>Khách hàng</th><th>SĐT</th><th>Tuyến đường</th><th>Tổng tiền (₫)</th><th>PT Thanh toán</th><th>Trạng thái</th><th>Thời gian đặt</th></tr></thead>
  <tbody>${rowsTickets}</tbody>
</table>

<div class="footer">Báo cáo được tạo tự động bởi hệ thống quản lý nhà xe &bull; ${now.toLocaleDateString('vi-VN')}</div>
</body></html>`)
    w.document.close()
    w.focus()
    setTimeout(() => {
      w.print()
      exportLoading.value = false
    }, 500)

    showToast(`✅ PDF đã sẵn sàng! (${allTickets.length} vé) Hộp thoại in sẽ mở ra.`, 'success')
  } catch (err) {
    removeToast(loadingToastId)
    console.error('[ExportPdf Operator]', err)
    showToast('❌ Xuất PDF thất bại: ' + (err?.message ?? 'Lỗi không xác định'), 'error')
    exportLoading.value = false
  }
}

// ── Chart data ────────────────────────────────────────────────────
const lineData = computed(() => ({ labels: MONTHS, datasets: [{ label: 'Doanh thu (triệu đ)', data: revenueByTime.value.map(v => +(v / 1e6).toFixed(1)), borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.12)', fill: true, tension: 0.4 }] }))
const lineOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
const barData = computed(() => ({ labels: MONTHS, datasets: [{ label: 'Số vé', data: ticketsByTime.value, backgroundColor: 'rgba(59,130,246,0.8)', borderRadius: 8, borderSkipped: false }] }))
const barOpts = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
const donutData = computed(() => ({ labels: ['Hoàn thành', 'Đã huỷ', 'Đang chờ'], datasets: [{ data: pieBreakdown.value, backgroundColor: ['#22c55e', '#ef4444', '#f59e0b'] }] }))
const donutOpts = { responsive: true, maintainAspectRatio: false, cutout: '68%' }

onMounted(() => { fetchStats() })
</script>

<template>
  <div class="tk-page">

    <!-- ── Toast Notifications ── -->
    <teleport to="body">
      <div class="toast-container">
        <transition-group name="toast-slide" tag="div">
          <div v-for="t in toasts" :key="t.id" class="toast-item" :class="`toast-${t.type}`" @click="removeToast(t.id)">
            <span class="toast-msg">{{ t.message }}</span>
            <button class="toast-close" @click.stop="removeToast(t.id)">×</button>
          </div>
        </transition-group>
      </div>
    </teleport>

    <!-- Header -->
    <div class="tk-header">
      <div class="tk-header-left">
        <div class="tk-icon-wrap">
          <TrendingUp class="tk-icon" />
        </div>
        <div>
          <h1 class="tk-title">Thống Kê &amp; Báo Cáo Doanh Thu</h1>
          <p class="tk-subtitle">Phân tích hiệu quả kinh doanh của nhà xe</p>
        </div>
      </div>
      <div class="tk-header-actions">
        <button class="btn-icon-only" :class="{ spinning: isLoading }" @click="handleApply" title="Làm mới">
          <RefreshCw class="ic16" />
        </button>
        <button class="btn-export btn-export-pdf" :disabled="exportLoading" @click="exportPdf" id="btn-export-pdf">
          <FileText class="ic16" />
          {{ exportLoading ? 'Đang xử lý...' : 'Xuất PDF' }}
        </button>
        <button class="btn-export" :disabled="exportLoading" @click="exportExcel" id="btn-export-excel">
          <FileSpreadsheet class="ic16" />
          {{ exportLoading ? 'Đang xử lý...' : 'Xuất Excel' }}
        </button>
      </div>
    </div>

    <!-- Bộ lọc -->
    <div class="filter-card">
      <div class="filter-tabs">
        <button v-for="tab in filterTabs" :key="tab.key" class="filter-tab" :class="{ active: filterType === tab.key }"
          @click="filterType = tab.key">
          <Calendar class="ic14" /> {{ tab.label }}
        </button>
      </div>
      <div class="filter-row">
        <template v-if="filterType === 'range'">
          <div class="fg"><label>Từ ngày</label><input type="date" v-model="dateFrom" class="fi" /></div>
          <span class="fsep">→</span>
          <div class="fg"><label>Đến ngày</label><input type="date" v-model="dateTo" class="fi" /></div>
        </template>
        <template v-if="filterType === 'month'">
          <div class="fg"><label>Chọn tháng</label><input type="month" v-model="selectedMonth" class="fi" /></div>
        </template>
        <template v-if="filterType === 'quarter'">
          <div class="fg"><label>Chọn quý</label>
            <div class="qwrap">
              <select v-model="selectedQuarterYear" class="fi qsel">
                <option v-for="y in [2026, 2025, 2024, 2023]" :key="y" :value="String(y)">{{ y }}</option>
              </select>
              <span class="fsep">–</span>
              <select v-model="selectedQuarterNum" class="fi qsel">
                <option value="1">Quý 1</option>
                <option value="2">Quý 2</option>
                <option value="3">Quý 3</option>
                <option value="4">Quý 4</option>
              </select>
            </div>
          </div>
        </template>
        <template v-if="filterType === 'year'">
          <div class="fg year-field"><label>Chọn năm</label><select v-model="selectedYear" class="fi year-select">
              <option v-for="y in [2026, 2025, 2024, 2023]" :key="y" :value="String(y)">{{ y }}</option>
            </select></div>
        </template>
        <button class="btn-apply" :disabled="isLoading" @click="handleApply">
          <template v-if="isLoading">
            <div class="bspinner"></div> Đang tải...
          </template>
          <template v-else>
            <Filter class="ic16" /> Áp dụng
          </template>
        </button>
      </div>
    </div>

    <!-- KPI -->
    <div class="kpi-grid">
      <div class="kpi-card kc-green">
        <div class="kpi-top">
          <div class="kpi-icon-w kig-green">
            <DollarSign class="kpi-ic" />
          </div><span class="kbadge up">
            <ArrowUpRight class="ic12" />+0%
          </span>
        </div>
        <p class="kpi-label">Doanh Thu</p>
        <h2 class="kpi-val">{{ fmt(kpi.tongDoanhThu) }}</h2>
        <p class="kpi-sub">{{ fmtFull(kpi.tongDoanhThu) }}</p>
      </div>
      <div class="kpi-card kc-blue">
        <div class="kpi-top">
          <div class="kpi-icon-w kig-blue">
            <Ticket class="kpi-ic" />
          </div>
        </div>
        <p class="kpi-label">Tổng Vé Bán</p>
        <h2 class="kpi-val">{{ kpi.tongVe.toLocaleString() }}</h2>
        <p class="kpi-sub">Vé trong kỳ lọc</p>
      </div>
      <div class="kpi-card kc-indigo">
        <div class="kpi-top">
          <div class="kpi-icon-w kig-indigo">
            <BusFront class="kpi-ic" />
          </div>
        </div>
        <p class="kpi-label">Chuyến Xe</p>
        <h2 class="kpi-val">{{ kpi.tongChuyenXe.toLocaleString() }}</h2>
        <p class="kpi-sub">Tổng chuyến có vé</p>
      </div>
      <div class="kpi-card kc-orange">
        <div class="kpi-top">
          <div class="kpi-icon-w kig-orange">
            <Users class="kpi-ic" />
          </div>
        </div>
        <p class="kpi-label">Khách Hàng</p>
        <h2 class="kpi-val">{{ kpi.tongKhachHang.toLocaleString() }}</h2>
        <p class="kpi-sub">Khách đặt vé kỳ này</p>
      </div>
    </div>

    <!-- Chart -->
    <div class="chart-card">
      <div class="chart-header">
        <h3 class="panel-title">
          <TrendingUp class="panel-ic" /> Biểu đồ phân tích
        </h3>
      </div>
      <div class="chart-body">
        <div class="chart-main">
          <div class="chart-tabs">
            <button class="ctab" :class="{ active: activeChartTab === 'revenue' }" @click="activeChartTab = 'revenue'">Doanh
              thu</button>
            <button class="ctab" :class="{ active: activeChartTab === 'tickets' }" @click="activeChartTab = 'tickets'">Số
              vé</button>
          </div>
          <div class="chart-wrap">
            <Line v-if="activeChartTab === 'revenue'" :data="lineData" :options="lineOpts" />
            <Bar v-else :data="barData" :options="barOpts" />
          </div>
        </div>
        <div class="chart-side">
          <p class="side-title">Tỉ lệ vé</p>
          <div class="donut-wrap">
            <Doughnut :data="donutData" :options="donutOpts" />
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom grid: tuyến + top -->
    <div class="bottom-grid">
      <div class="panel">
        <div class="panel-hd">
          <h3 class="panel-title">
            <MapPin class="panel-ic" /> Doanh Thu Theo Tuyến
          </h3>
        </div>
        <div class="route-list">
          <div v-if="revenueByRoute.length === 0" class="empty-state">Chưa có dữ liệu</div>
          <div v-for="(r, idx) in revenueByRoute" :key="r.ten_tuyen_duong" class="route-item">
            <div class="route-rank" :class="idx === 0 ? 'gold' : idx === 1 ? 'silver' : idx === 2 ? 'bronze' : ''">{{ idx + 1 }}</div>
            <div class="route-info">
              <p class="route-name">{{ r.ten_tuyen_duong }}</p>
              <div class="route-bar-wrap">
                <div class="route-bar"
                  :style="{ width: Math.min(r.doanh_thu / (revenueByRoute[0]?.doanh_thu || 1) * 100, 100) + '%' }"></div>
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
        <div class="panel-hd">
          <h3 class="panel-title">
            <Star class="panel-ic" /> Top Chuyến Xe / Khách Hàng
          </h3>
        </div>
        <div class="driver-list">
          <p class="driver-title">Top chuyến xe</p>
          <div v-for="d in topTrips" :key="d.id_chuyen_xe" class="driver-row">
            <div class="driver-avatar">{{ String(d.id_chuyen_xe).charAt(0) }}</div>
            <div class="driver-info">
              <p class="driver-name">{{ d.ten_tuyen_duong || ('Chuyến #' + d.id_chuyen_xe) }}</p>
              <p class="driver-sub">{{ d.so_ve }} vé · {{ fmt(d.tong_doanh_thu) }}</p>
            </div>
          </div>
          <p class="driver-title">Top khách hàng</p>
          <div v-for="c in topCustomers" :key="c.id_khach_hang" class="driver-row">
            <div class="driver-avatar">{{ (c.ten_khach_hang ?? '?').charAt(0) }}</div>
            <div class="driver-info">
              <p class="driver-name">{{ c.ten_khach_hang }}</p>
              <p class="driver-sub">{{ c.so_ve }} vé · {{ fmt(c.tong_doanh_thu) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>


  </div>
</template>

<style scoped>
/* ── Base ── */
.tk-page {
  padding: 8px 0 40px;
  font-family: 'Inter', system-ui, sans-serif
}

.tk-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 12px
}

.tk-header-left {
  display: flex;
  align-items: center;
  gap: 16px
}

.tk-icon-wrap {
  width: 52px;
  height: 52px;
  background: linear-gradient(135deg, #22c55e, #15803d);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6px 20px rgba(34, 197, 94, .35)
}

.tk-icon {
  width: 26px;
  height: 26px;
  color: white
}

.tk-title {
  font-size: 24px;
  font-weight: 800;
  color: #0d4f35;
  margin: 0
}

.tk-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 4px 0 0
}

.tk-header-actions {
  display: flex;
  gap: 10px;
  align-items: center
}

.btn-icon-only {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all .2s;
  color: #475569
}

.btn-export {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 18px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #22c55e, #15803d);
  color: white;
  font-weight: 700;
  font-size: 13px;
  border: none;
  cursor: pointer;
  transition: opacity .2s
}

.btn-export:disabled {
  opacity: .6;
  cursor: not-allowed
}

.btn-export-pdf {
  background: linear-gradient(135deg, #f97316, #c2410c)
}

/* Cards */
.filter-card,
.chart-card,
.panel {
  background: white;
  border-radius: 16px;
  padding: 20px 24px;
  margin-bottom: 24px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, .05);
  border: 1px solid #f1f5f9
}

/* Filter */
.filter-tabs {
  display: flex;
  gap: 6px;
  margin-bottom: 16px;
  flex-wrap: wrap
}

.filter-tab,
.ctab {
  padding: 8px 16px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  background: white;
  color: #64748b;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer
}

.filter-tab.active,
.ctab.active {
  background: linear-gradient(135deg, #f0fdf4, #dcfce7);
  border-color: #22c55e;
  color: #15803d;
  font-weight: 700
}

.filter-row {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  flex-wrap: wrap
}

.fi {
  height: 40px;
  padding: 0 14px;
  border-radius: 10px;
  border: 1.5px solid #e2e8f0;
  font-size: 14px;
  color: #374151;
  background: #f8fafc;
  outline: none;
  min-width: 160px
}

.fg {
  display: flex;
  flex-direction: column;
  gap: 4px
}

.fg label {
  font-size: 12px;
  color: #64748b;
  font-weight: 600
}

.fsep {
  color: #94a3b8;
  font-weight: 600;
  padding: 0 4px
}

.qwrap {
  display: flex;
  align-items: center;
  gap: 6px
}

.qsel {
  min-width: 100px
}

.btn-apply,
.btn-apply-sm {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 22px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #22c55e, #15803d);
  color: white;
  border: none;
  cursor: pointer;
  font-weight: 600;
  font-size: 13px
}

.btn-apply-sm {
  padding: 0 16px;
  height: 36px;
  font-size: 12px
}

/* KPI */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
  margin-bottom: 24px
}

.kpi-card {
  padding: 20px;
  border-radius: 16px;
  background: white;
  border: 1px solid #f1f5f9;
  box-shadow: 0 2px 8px rgba(0, 0, 0, .04)
}

.kpi-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px
}

.kpi-icon-w {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center
}

.kig-green {
  background: linear-gradient(135deg, #22c55e, #15803d)
}

.kig-blue {
  background: linear-gradient(135deg, #3b82f6, #1d4ed8)
}

.kig-indigo {
  background: linear-gradient(135deg, #6366f1, #4338ca)
}

.kig-orange {
  background: linear-gradient(135deg, #f59e0b, #d97706)
}

.kpi-ic {
  width: 22px;
  height: 22px;
  color: white
}

.kpi-label {
  font-size: 12px;
  color: #64748b;
  font-weight: 600;
  text-transform: uppercase;
  margin: 0 0 6px
}

.kpi-val {
  font-size: 26px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 4px
}

.kpi-sub {
  font-size: 12px;
  color: #94a3b8;
  margin: 0
}

.kbadge {
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 2px
}

.kbadge.up {
  color: #16a34a
}

/* Chart */
.chart-header {
  margin-bottom: 16px
}

.chart-body {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px
}

.chart-main {
  display: flex;
  flex-direction: column
}

.chart-wrap {
  height: 280px
}

.chart-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 12px
}

.chart-side {
  display: flex;
  flex-direction: column;
  align-items: center
}

.side-title {
  font-size: 13px;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 8px
}

.donut-wrap {
  height: 220px;
  width: 100%
}

/* Bottom grid */
.bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 24px
}

.panel-hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #f8fafc
}

.panel-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0
}

.panel-ic {
  width: 18px;
  height: 18px;
  color: #22c55e
}

/* Route list */
.route-list,
.driver-list {
  padding: 12px 20px 20px
}

.route-item,
.driver-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid #f8fafc
}

.route-rank,
.driver-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 800;
  background: #f1f5f9;
  color: #64748b;
  flex-shrink: 0
}

.gold {
  background: linear-gradient(135deg, #fbbf24, #d97706);
  color: white
}

.silver {
  background: linear-gradient(135deg, #94a3b8, #64748b);
  color: white
}

.bronze {
  background: linear-gradient(135deg, #d97706, #92400e);
  color: white
}

.route-info,
.driver-info {
  flex: 1;
  min-width: 0
}

.route-name,
.driver-name {
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis
}

.route-bar-wrap {
  height: 5px;
  background: #f1f5f9;
  border-radius: 10px;
  overflow: hidden
}

.route-bar {
  height: 100%;
  border-radius: 10px;
  transition: width .5s ease;
  background: #22c55e
}

.route-nums {
  text-align: right;
  flex-shrink: 0
}

.rn-rev {
  font-size: 13px;
  font-weight: 700;
  color: #15803d;
  margin: 0 0 2px
}

.rn-tkt,
.driver-sub {
  font-size: 11px;
  color: #94a3b8;
  margin: 0
}

.driver-title {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .5px;
  padding: 12px 0 6px
}

.empty-state,
.state-wrap {
  text-align: center;
  padding: 32px;
  color: #94a3b8
}

.spinner,
.bspinner {
  width: 28px;
  height: 28px;
  border: 3px solid #e2e8f0;
  border-top-color: #22c55e;
  border-radius: 50%;
  animation: spin .8s linear infinite
}

/* Icons */
.ic12 {
  width: 12px;
  height: 12px
}

.ic14 {
  width: 14px;
  height: 14px
}

.ic16 {
  width: 16px;
  height: 16px
}

.ic24 {
  width: 24px;
  height: 24px
}

/* Misc */
.year-field {
  min-width: 200px
}

.year-select {
  min-width: 200px
}

@keyframes spin {
  to {
    transform: rotate(360deg)
  }
}

.btn-icon-only.spinning .ic16 {
  animation: spin .8s linear infinite
}

/* ── Toast ── */
.toast-container {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 99999;
  display: flex;
  flex-direction: column;
  gap: 10px;
  pointer-events: none
}

.toast-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  min-width: 300px;
  max-width: 440px;
  padding: 14px 18px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 8px 24px rgba(0, 0, 0, .15);
  pointer-events: all;
  cursor: pointer;
  transition: all .3s
}

.toast-success {
  background: linear-gradient(135deg, #166534, #15803d);
  color: white;
  border: 1px solid #22c55e
}

.toast-error {
  background: linear-gradient(135deg, #7f1d1d, #dc2626);
  color: white;
  border: 1px solid #ef4444
}

.toast-warning {
  background: linear-gradient(135deg, #78350f, #d97706);
  color: white;
  border: 1px solid #f59e0b
}

.toast-msg {
  flex: 1
}

.toast-close {
  background: none;
  border: none;
  color: inherit;
  font-size: 18px;
  line-height: 1;
  cursor: pointer;
  opacity: .8;
  padding: 0;
  flex-shrink: 0
}

.toast-close:hover {
  opacity: 1
}

.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all .35s ease
}

.toast-slide-enter-from {
  opacity: 0;
  transform: translateX(40px)
}

.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(60px)
}

/* ── Responsive ── */
@media (max-width:1280px) {
  .kpi-grid {
    grid-template-columns: repeat(2, 1fr)
  }

  .chart-body {
    grid-template-columns: 1fr
  }

  .bottom-grid {
    grid-template-columns: 1fr
  }
}

@media (max-width:768px) {
  .kpi-grid {
    grid-template-columns: 1fr 1fr
  }

  .fi {
    min-width: 130px
  }
}

@media (max-width:480px) {
  .kpi-grid {
    grid-template-columns: 1fr
  }

  .tk-title {
    font-size: 18px
  }
}
</style>
