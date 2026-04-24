<script setup>
/**
 * Thống kê Admin — GET /v1/admin/bao-cao/* cho biểu đồ/tuyến; KPI tổng doanh thu = tổng tiền thanh toán thành công
 * (GET /v1/admin/thanh-toan, phân trang đủ trang theo tu_ngay/den_ngay). /thanh-toan/thong-ke + vé: bổ sung/fallback.
 */
// Trang Thống Kê & Báo Cáo Doanh Thu – Admin (toàn hệ thống)
import { ref, computed, onMounted } from 'vue'
import {
  TrendingUp, DollarSign, Ticket, Users,
  BarChart2, PieChart, Calendar, Download, RefreshCw,
  CheckCircle, XCircle, Clock, ArrowUpRight, ArrowDownRight,
  Bus, MapPin, Star, Filter, Building2, Percent, FileText
} from 'lucide-vue-next'
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, PointElement, LineElement,
  BarElement, ArcElement, Title, Tooltip, Legend, Filler
} from 'chart.js'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import adminApi from '@/api/adminApi'

// Đăng ký các thành phần ChartJS cần dùng
ChartJS.register(
  CategoryScale, LinearScale, PointElement, LineElement,
  BarElement, ArcElement, Title, Tooltip, Legend, Filler
)

// ─── Bộ lọc thời gian ───────────────────────────────────────────────
const filterType = ref('year') // 'range' | 'month' | 'quarter' | 'year'
const y0 = String(new Date().getFullYear())
const dateFrom   = ref(`${y0}-01-01`)
const dateTo     = ref(`${y0}-12-31`)
const selectedMonth       = ref(`${y0}-01`)
const selectedQuarterYear = ref(y0)    // năm quý
const selectedQuarterNum  = ref('1')       // số quý 1-4
const selectedYear        = ref(y0)
const compareMode = ref(false)

const filterTabs = [
  { key: 'range',   label: 'Khoảng ngày' },
  { key: 'month',   label: 'Theo tháng' },
  { key: 'quarter', label: 'Theo quý' },
  { key: 'year',    label: 'Theo năm' },
]

// ─── Dữ liệu chart (doanh thu/vé theo tháng, nhà xe – lấy từ API) ──
const monthlyRevenue = ref(Array(12).fill(0))
const monthlyTickets = ref(Array(12).fill(0))
const revenueByBus   = ref([])

// KPI tổng quan – sẽ được cập nhật từ API
const totalStats = ref({
  tongDoanhThu: 0, tongVe: 0, tongChuyenXe: 0,
  tongKhachHang: 0, tongNhaXe: 0,
  veHoanThanh: 0, veHuy: 0, veCho: 0,
  tyLeHoanThanh: 0, tyLeHuy: 0, tyLeCho: 0,
  tangTruong: 'N/A', tangTruongPositive: true,
  /** Tiêu chí chấp nhận */
  tongVeDaBan: 0,
  tyLeLapDay: 0,
  khachHangMoi: 0,
})

/** Phân bổ vé cho biểu đồ tròn: Đã TT (không tiền mặt) | Tiền mặt | Đã hủy */
const ticketVeSlice = ref({ daThanhToanNonCash: 0, tienMat: 0, daHuy: 0 })

/** Tăng mỗi lần áp dụng bộ lọc — bỏ qua phản hồi cũ (race). Chart/tuyến ưu tiên bao-cao; KPI tổng doanh thu lấy từ tổng API thanh-toan trong kỳ. */
const thongKeLoadGen = ref(0)
const baoCaoSetsPrimaryKpi = ref(false)

/** Thống kê theo tuyến (từ danh sách vé) */
const routeTop = ref([])
const routeBottom = ref([])

const unwrapApi = (res) => {
  if (res == null) return null
  if (res.success === false) return null
  return res.data !== undefined ? res.data : res
}

const extractPaginatedRows = (res) => {
  const payload = unwrapApi(res)
  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  return []
}

const extractPaginatedMeta = (res) => {
  const payload = unwrapApi(res)
  if (!payload || typeof payload !== 'object') {
    return { current_page: 1, last_page: 1, total: 0, per_page: 15 }
  }
  const metaSource = payload?.data?.current_page ? payload.data : payload
  return {
    current_page: Number(metaSource?.current_page ?? 1),
    last_page: Number(metaSource?.last_page ?? 1),
    total: Number(metaSource?.total ?? 0),
    per_page: Number(metaSource?.per_page ?? 15),
  }
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
    label,
    ten_tuyen: label,
    doanh_thu: Number(x.doanh_thu ?? x.tong_doanh_thu ?? x.doanhThu ?? 0),
    so_ve: Number(x.so_ve ?? x.so_luong_ve ?? x.soVe ?? 0),
  }
}

/** Gộp dữ liệu từ BaoCaoController (admin) */
const fetchBaoCaoAdmin = async (tuNgay, denNgay, gen) => {
  if (!tuNgay || !denNgay) return
  const prm = { tu_ngay: tuNgay, den_ngay: denNgay }
  try {
    const [dashRes, tuyenRes, ttRes] = await Promise.all([
      adminApi.getBaoCaoDashboard(prm),
      adminApi.getBaoCaoTheoTuyenDuong(prm),
      adminApi.getBaoCaoTrangThaiVe(prm),
    ])
    if (gen !== undefined && gen !== thongKeLoadGen.value) return

    const d = unwrapApi(dashRes)
    if (d && typeof d === 'object') {
      baoCaoSetsPrimaryKpi.value =
        (Array.isArray(d.theo_thang) && d.theo_thang.length > 0) ||
        (Array.isArray(d.theo_nha_xe) && d.theo_nha_xe.length > 0) ||
        (d.tong_ve_da_ban != null && d.tong_ve_da_ban !== '')
      if (d.tong_ve_da_ban != null) totalStats.value.tongVeDaBan = Number(d.tong_ve_da_ban)
      if (d.ty_le_lap_day_tb != null) totalStats.value.tyLeLapDay = Number(d.ty_le_lap_day_tb)
      if (d.khach_hang_moi != null) totalStats.value.khachHangMoi = Number(d.khach_hang_moi)
      if (d.tong_ve != null) totalStats.value.tongVe = Number(d.tong_ve)
      if (d.tong_giao_dich != null) totalStats.value.tongVe = Number(d.tong_giao_dich)
      if (Array.isArray(d.theo_thang) && d.theo_thang.length) {
        const arrR = Array(12).fill(0)
        const arrV = Array(12).fill(0)
        d.theo_thang.forEach((item) => {
          const m =
            typeof item.thang === 'string'
              ? parseInt(item.thang.split('-')[1], 10) - 1
              : Number(item.thang) - 1
          if (m >= 0 && m < 12) {
            arrR[m] = Number(item.doanh_thu ?? item.doanhThu ?? 0)
            arrV[m] = Number(item.so_ve ?? item.soVe ?? 0)
          }
        })
        monthlyRevenue.value = arrR
        monthlyTickets.value = arrV
      }
      if (Array.isArray(d.theo_nha_xe) && d.theo_nha_xe.length) {
        const maxDT = Math.max(...d.theo_nha_xe.map((x) => Number(x.doanh_thu ?? 0)), 1)
        revenueByBus.value = d.theo_nha_xe.slice(0, 7).map((x) => ({
          name: x.ten_nha_xe ?? x.name ?? 'N/A',
          revenue: Number(x.doanh_thu ?? 0),
          tickets: Number(x.so_ve ?? 0),
          pct: Math.round((Number(x.doanh_thu ?? 0) / maxDT) * 100),
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
        const arr = mapTuyenList(t.du_lieu).filter(Boolean)
        routeTop.value = [...arr].sort((a, b) => b.doanh_thu - a.doanh_thu).slice(0, 8)
        routeBottom.value = [...arr].sort((a, b) => a.so_ve - b.so_ve).slice(0, 5)
      }
    }

    const v = unwrapApi(ttRes)
    if (v && typeof v === 'object') {
      ticketVeSlice.value = {
        daThanhToanNonCash: Number(
          v.da_thanh_toan_khong_tien_mat ?? v.khong_tien_mat ?? v.da_tt_khong_tm ?? 0
        ),
        tienMat: Number(v.tien_mat ?? v.ve_tien_mat ?? v.thanh_toan_tien_mat ?? 0),
        daHuy: Number(v.da_huy ?? v.huy ?? 0),
      }
    }
  } catch (e) {
    console.warn('[ThongKe] bao-cao admin:', e?.message ?? e)
  }
}

function mapTuyenList(raw) {
  if (!Array.isArray(raw)) return []
  return raw.map(normalizeTuyenRow).filter(Boolean)
}

// ─── Helper tính khoảng ngày từ filterType ──────────────────────────
const buildDateRange = () => {
  if (filterType.value === 'range') return { tuNgay: dateFrom.value, denNgay: dateTo.value }
  if (filterType.value === 'month') {
    const [y, m] = selectedMonth.value.split('-').map(Number)
    return { tuNgay: `${y}-${String(m).padStart(2,'0')}-01`, denNgay: `${y}-${String(m).padStart(2,'0')}-${new Date(y, m, 0).getDate()}` }
  }
  if (filterType.value === 'quarter') {
    const y = Number(selectedQuarterYear.value), q = Number(selectedQuarterNum.value)
    const sm = (q-1)*3+1, em = q*3
    return { tuNgay: `${y}-${String(sm).padStart(2,'0')}-01`, denNgay: `${y}-${String(em).padStart(2,'0')}-${new Date(y, em, 0).getDate()}` }
  }
  return { tuNgay: `${selectedYear.value}-01-01`, denNgay: `${selectedYear.value}-12-31` }
}

const parseTicketsFromResponse = (res) => {
  const d = res?.data ?? res
  let list = []
  if (d?.data?.data?.data) list = d.data.data.data
  else if (d?.data?.data) list = d.data.data
  else if (Array.isArray(d?.data)) list = d.data
  else if (Array.isArray(d)) list = d
  return Array.isArray(list) ? list : []
}

const isHuyVe = (t) => {
  const s = String(t?.tinh_trang ?? '').toLowerCase()
  return s === 'huy' || s === 'da_huy'
}
const isDaThanhToan = (t) => String(t?.tinh_trang ?? '').toLowerCase() === 'da_thanh_toan'
const isTienMat = (t) => String(t?.phuong_thuc_thanh_toan ?? '').toLowerCase() === 'tien_mat'

/** Lấy toàn bộ vé trong khoảng ngày (phân trang) để tính tuyến / pie / lấp đầy */
const fetchTicketsAnalytics = async (tuNgay, denNgay, gen) => {
  const acc = []
  let page = 1
  const perPage = 100
  try {
    do {
      const params = { page, per_page: perPage }
      if (tuNgay) params.tu_ngay = tuNgay
      if (denNgay) params.den_ngay = denNgay
      const res = await adminApi.getTickets(params)
      const chunk = parseTicketsFromResponse(res)
      acc.push(...chunk)
      if (chunk.length < perPage) break
      page += 1
      if (page > 50) break
    } while (true)
  } catch (e) {
    console.warn('[ThongKe] getTickets analytics:', e?.message)
  }

  if (gen !== undefined && gen !== thongKeLoadGen.value) return

  // Lọc client-side theo thời điểm tạo bản ghi để khớp với API thanh toán.
  const fromMs = tuNgay ? new Date(tuNgay).getTime() : null
  const toMs = denNgay ? new Date(`${denNgay}T23:59:59`).getTime() : null
  const inRangeTickets = acc.filter((t) => {
    const rawDate = t.created_at || t.ngay_tao || t.thoi_gian_dat || t?.chuyen_xe?.ngay_khoi_hanh || ''
    const d = new Date(rawDate)
    if (Number.isNaN(d.getTime())) return false
    if (fromMs && d.getTime() < fromMs) return false
    if (toMs && d.getTime() > toMs) return false
    return true
  })

  // Vé đã bán: không tính vé hủy (dùng cho KPI số vé, không dùng để ghi đè doanh thu).
  const soldTickets = inRangeTickets.filter((t) => !isHuyVe(t))
  totalStats.value.tongVeDaBan = soldTickets.length

  // Không cập nhật `tongDoanhThu` ở đây nữa vì KPI này phải lấy theo bảng `thanh_toan`.
  // Phần vé chỉ dùng cho biểu đồ/tuyến/số lượng vé.

  let nonCash = 0
  let tm = 0
  let huy = 0
  inRangeTickets.forEach((t) => {
    if (isHuyVe(t)) huy += 1
    else if (isDaThanhToan(t)) {
      if (isTienMat(t)) tm += 1
      else nonCash += 1
    }
  })
  ticketVeSlice.value = { daThanhToanNonCash: nonCash, tienMat: tm, daHuy: huy }

  const routeMap = {}
  const tripSeat = {}
  inRangeTickets.forEach((t) => {
    const cx = t.chuyen_xe || {}
    const td = cx.tuyen_duong || {}
    const label =
      [td.diem_bat_dau, td.diem_ket_thuc].filter(Boolean).join(' → ') ||
      td.ten_tuyen_duong ||
      t.ten_tuyen ||
      `Tuyến #${td.id ?? ''}`
    if (!routeMap[label]) routeMap[label] = { label, doanh_thu: 0, so_ve: 0 }
    const tien = Number(t.tong_tien ?? t.gia_ve ?? 0)
    if (isDaThanhToan(t)) routeMap[label].doanh_thu += tien
    routeMap[label].so_ve += 1

    const cid = cx.id
    if (cid) {
      const cap0 = Number(cx.xe?.so_cho_ngoi ?? cx.phuong_tien?.so_cho_ngoi ?? cx.tong_so_ghe ?? 40) || 40
      if (!tripSeat[cid]) tripSeat[cid] = { sold: 0, cap: cap0 }
      tripSeat[cid].sold += 1
      const cap = Number(cx.xe?.so_cho_ngoi ?? cx.phuong_tien?.so_cho_ngoi ?? cx.tong_so_ghe ?? tripSeat[cid].cap)
      if (cap > 0) tripSeat[cid].cap = cap
    }
  })
  const routes = Object.values(routeMap).sort((a, b) => b.doanh_thu - a.doanh_thu)
  routeTop.value = routes.slice(0, 8)
  routeBottom.value = [...routes].sort((a, b) => a.so_ve - b.so_ve).slice(0, 5)

  const ratios = Object.values(tripSeat)
    .map((x) => (x.cap > 0 ? Math.min(100, (x.sold / x.cap) * 100) : 0))
  totalStats.value.tyLeLapDay =
    ratios.length > 0 ? +((ratios.reduce((a, b) => a + b, 0) / ratios.length).toFixed(1)) : 0
}

/** Khách hàng mới trong kỳ */
const fetchNewClientsInRange = async (tuNgay, denNgay) => {
  totalStats.value.khachHangMoi = 0
  if (!tuNgay || !denNgay) return
  const from = new Date(tuNgay).getTime()
  const to = new Date(denNgay + 'T23:59:59').getTime()
  const countFromRows = (rows) =>
    rows.filter((c) => {
      const raw = c.created_at ?? c.ngay_tao ?? c.createdAt
      if (!raw) return false
      const t = new Date(raw).getTime()
      return t >= from && t <= to
    }).length
  const extractRows = (res) => {
    const p = res?.data ?? res
    if (Array.isArray(p?.data)) return p.data
    if (Array.isArray(p?.data?.data)) return p.data.data
    if (Array.isArray(p)) return p
    return []
  }
  try {
    const res = await adminApi.getClients({ per_page: 500, page: 1, tu_ngay: tuNgay, den_ngay: denNgay })
    totalStats.value.khachHangMoi = countFromRows(extractRows(res))
  } catch {
    /* ignore */
  }
  if (totalStats.value.khachHangMoi > 0) return
  try {
    const res = await adminApi.getClients({ per_page: 500, page: 1 })
    totalStats.value.khachHangMoi = countFromRows(extractRows(res))
  } catch (e) {
    console.warn('[ThongKe] khach hang moi:', e)
  }
}

// ─── Fetch thống kê doanh thu (endpoint thong-ke, fallback danh sách) ──
const statsLoading = ref(false)
const fetchStats = async (tuNgay, denNgay, gen) => {
  statsLoading.value = true
  try {
    const res = await adminApi.getPaymentStats({ tu_ngay: tuNgay, den_ngay: denNgay })
    if (gen !== undefined && gen !== thongKeLoadGen.value) return
    const pl = unwrapApi(res) ?? {}
    console.log('[ThongKe] stats payload:', pl)

    const tongDT = Number(
      pl?.tong_doanh_thu ??
      pl?.tong_thuc_thu ??
      pl?.tong_tien ??
      pl?.total_revenue ??
      0
    )
    const tc = Number(pl?.trang_thai?.thanh_cong ?? pl?.so_thanh_cong ?? 0)
    const tb = Number(pl?.trang_thai?.that_bai   ?? pl?.so_that_bai   ?? 0)
    const cho= Number(pl?.trang_thai?.cho_xu_ly  ?? pl?.so_cho_xu_ly  ?? 0)
    const tot= tc + tb + cho || Number(pl?.so_giao_dich ?? pl?.total_count ?? 0)

    const preferBaoCao = baoCaoSetsPrimaryKpi.value

    const vb = pl?.ve_phan_bo ?? pl?.phan_bo_ve
    if (!preferBaoCao && vb && typeof vb === 'object') {
      ticketVeSlice.value = {
        daThanhToanNonCash: Number(vb.da_thanh_toan_khong_tien_mat ?? vb.non_cash ?? 0),
        tienMat: Number(vb.tien_mat ?? vb.tien_mat_da_thanh_toan ?? 0),
        daHuy: Number(vb.da_huy ?? vb.huy ?? 0),
      }
    }
    if (!preferBaoCao && pl?.tong_ve_da_ban != null) totalStats.value.tongVeDaBan = Number(pl.tong_ve_da_ban)
    if (!preferBaoCao && pl?.ty_le_lap_day_tb != null) totalStats.value.tyLeLapDay = Number(pl.ty_le_lap_day_tb)
    if (!preferBaoCao && pl?.khach_hang_moi != null) totalStats.value.khachHangMoi = Number(pl.khach_hang_moi)

    // Nếu endpoint trả về dữ liệu có nghĩa (tongVe > 0 hoặc tongDoanhThu > 0), dùng
    if (tot > 0 || tongDT > 0) {
      if (tongDT > 0) totalStats.value.tongDoanhThu = tongDT
      if (!preferBaoCao) {
        if (Array.isArray(pl?.theo_thang) && pl.theo_thang.length) {
          const arrR = Array(12).fill(0), arrV = Array(12).fill(0)
          pl.theo_thang.forEach(item => {
            const m = typeof item.thang === 'string' ? parseInt(item.thang.split('-')[1]) - 1 : Number(item.thang) - 1
            if (m >= 0 && m < 12) { arrR[m] = Number(item.doanh_thu ?? 0); arrV[m] = Number(item.so_ve ?? 0) }
          })
          monthlyRevenue.value = arrR; monthlyTickets.value = arrV
        }
        if (Array.isArray(pl?.theo_nha_xe) && pl.theo_nha_xe.length) {
          const maxDT = Math.max(...pl.theo_nha_xe.map(x => Number(x.doanh_thu ?? 0)), 1)
          revenueByBus.value = pl.theo_nha_xe.slice(0, 7).map(x => ({ name: x.ten_nha_xe ?? x.name ?? 'N/A', revenue: Number(x.doanh_thu ?? 0), tickets: Number(x.so_ve ?? 0), pct: Math.round((Number(x.doanh_thu ?? 0) / maxDT) * 100) }))
        }
        if (Array.isArray(pl?.theo_tuyen) && pl.theo_tuyen.length) {
          const mapped = pl.theo_tuyen.map((x) => ({
            label: x.ten_tuyen ?? x.tuyen ?? x.label ?? '—',
            doanh_thu: Number(x.doanh_thu ?? x.revenue ?? 0),
            so_ve: Number(x.so_ve ?? x.so_luong_ve ?? 0),
          }))
          routeTop.value = [...mapped].sort((a, b) => b.doanh_thu - a.doanh_thu).slice(0, 8)
          routeBottom.value = [...mapped].sort((a, b) => a.so_ve - b.so_ve).slice(0, 5)
        }
      }
      totalStats.value = {
        ...totalStats.value,
        tongVe: tot,
        veHoanThanh: tc,
        veHuy: tb,
        veCho: cho,
        tyLeHoanThanh: tot > 0 ? +((tc / tot) * 100).toFixed(1) : 0,
        tyLeHuy: tot > 0 ? +((tb / tot) * 100).toFixed(1) : 0,
        tyLeCho: tot > 0 ? +((cho / tot) * 100).toFixed(1) : 0,
      }
    } else {
      // Endpoint trả về rỗng hoặc không match field → fallback sang danh sách
      if (!preferBaoCao) {
        console.warn('[ThongKe] thong-ke trả về tổng = 0, fallback sang list')
        await fetchStatsFromList(tuNgay, denNgay, gen)
      }
    }
  } catch (err) {
    if (gen !== undefined && gen !== thongKeLoadGen.value) return
    if (!baoCaoSetsPrimaryKpi.value) {
      console.warn('[ThongKe] thong-ke lỗi, fallback:', err?.message)
      await fetchStatsFromList(tuNgay, denNgay, gen)
    }
  } finally { statsLoading.value = false }
}

// Fallback: tính aggregate từ danh sách khi endpoint thong-ke chưa có hoặc trả về 0
const fetchStatsFromList = async (tuNgay, denNgay, gen) => {
  if (baoCaoSetsPrimaryKpi.value) return
  try {
    // Lấy toàn bộ danh sách (BE có thể không filter theo ngày)
    const res  = await adminApi.getPayments({ per_page: 2000, page: 1 })
    if (gen !== undefined && gen !== thongKeLoadGen.value) return
    const list = extractPaginatedRows(res)
    if (!list.length) return
    console.log('[ThongKe-fallback] tong ban ghi BE:', list.length, '| sample:', JSON.stringify(list[0]))

    // ── Helper lấy ngày thanh toán (ưu tiên thoi_gian_thanh_toan) ────────
    const getDate = (pm) => new Date(
      pm.thoi_gian_thanh_toan ?? pm.created_at ?? pm.ngay_tao ?? pm.thoi_gian ?? ''
    )

    // ── Lọc client-side theo khoảng ngày đang chọn ───────────────────────
    const fromMs = tuNgay  ? new Date(tuNgay).getTime()              : null
    const toMs   = denNgay ? new Date(denNgay + 'T23:59:59').getTime() : null
    const filteredList = list.filter(pm => {
      const d = getDate(pm)
      if (isNaN(d)) return false                         // bỏ record không có ngày
      if (fromMs && d.getTime() < fromMs) return false
      if (toMs   && d.getTime() > toMs)   return false
      return true
    })
    console.log('[ThongKe-fallback] sau khi loc theo ngay:', filteredList.length, 'ban ghi (', tuNgay, '->', denNgay, ')')

    if (!filteredList.length) {
      // Không có dữ liệu trong khoảng thời gian → đặt về 0
      monthlyRevenue.value = Array(12).fill(0)
      monthlyTickets.value = Array(12).fill(0)
      totalStats.value = { ...totalStats.value, tongDoanhThu: 0, tongVe: 0, veHoanThanh: 0, veHuy: 0, veCho: 0, tyLeHoanThanh: 0, tyLeHuy: 0, tyLeCho: 0 }
      return
    }

    // ── Chỉ tính thanh toán thành công trong bảng `thanh_toan` ─────
    const getST = pm => Number(pm.so_tien_thuc_thu ?? pm.so_tien ?? pm.tong_tien ?? pm.amount ?? pm.gia_tien ?? 0)
    const isSuccess = pm => isPmThanhToanCong(pm)

    const thanhCongList = filteredList.filter(isSuccess)
    const thatBaiList = filteredList.filter(pm => !isSuccess(pm) && String(pm.trang_thai ?? pm.status ?? '').toLowerCase().trim() !== 'cho' && String(pm.trang_thai ?? pm.status ?? '').toLowerCase().trim() !== 'pending')
    const choList = filteredList.filter(pm => !isSuccess(pm) && !thatBaiList.includes(pm))

    const tot = filteredList.length
    const tongDoanhThu = thanhCongList.reduce((s, pm) => s + getST(pm), 0)

    totalStats.value = {
      ...totalStats.value,
      tongDoanhThu,
      tongVe:        tot,
      veHoanThanh:   thanhCongList.length,
      veHuy:         thatBaiList.length,
      veCho:         choList.length,
      tyLeHoanThanh: tot > 0 ? +((thanhCongList.length / tot) * 100).toFixed(1) : 0,
      tyLeHuy:       tot > 0 ? +((thatBaiList.length   / tot) * 100).toFixed(1) : 0,
      tyLeCho:       tot > 0 ? +((choList.length        / tot) * 100).toFixed(1) : 0,
    }

    // ── Chart: doanh thu + số vé theo tháng ──────────────────────────────
    const arrR = Array(12).fill(0), arrV = Array(12).fill(0)
    filteredList.forEach(pm => {
      const d = getDate(pm)
      if (!isNaN(d)) { arrR[d.getMonth()] += getST(pm); arrV[d.getMonth()] += 1 }
    })
    monthlyRevenue.value = arrR
    monthlyTickets.value = arrV
  } catch (err) { console.error('[ThongKe-fallback] error:', err) }
}


// ─── Fetch KPI riêng lẻ ─────────────────────────────────────────────
const fetchKhachHang = async () => {
  try { const r = await adminApi.getClients({ per_page: 1, page: 1 }); const p = r?.data ?? r; totalStats.value.tongKhachHang = Number(p?.total ?? p?.meta?.total ?? p?.data?.total ?? 0) } catch (e) { console.error('[ThongKe] khach-hang:', e) }
}
const fetchNhaXe = async () => {
  try { const r = await adminApi.getOperators({ per_page: 1, page: 1 }); const p = r?.data ?? r; totalStats.value.tongNhaXe = Number(p?.total ?? p?.meta?.total ?? p?.data?.total ?? 0) } catch (e) { console.error('[ThongKe] nha-xe:', e) }
}
const fetchChuyenXe = async () => {
  try { const r = await adminApi.getTrips({ per_page: 1, page: 1 }); const p = r?.data ?? r; totalStats.value.tongChuyenXe = Number(p?.total ?? p?.meta?.total ?? p?.data?.total ?? 0) } catch (e) { console.error('[ThongKe] chuyen-xe:', e) }
}

// ─── Dữ liệu thanh toán từ API ─────────────────────────────────────
const payments      = ref([])       // danh sách thanh toán trang hiện tại
const paymentsMeta  = ref(null)     // meta phân trang { current_page, last_page, total, per_page }
const paymentsError = ref(null)     // lỗi nếu có
const paymentsLoading = ref(false)

// Bộ lọc cho bảng thanh toán (độc lập với bộ lọc biểu đồ)
const pmFilter = ref({
  search:      '',
  trang_thai:  '',   // '' = tất cả | 1 = thành công | 0 = thất bại | 2 = hoàn tiền
  phuong_thuc: '',   // '' = tất cả | 1 = VNPay | 2 = Momo | 3 = tiền mặt ...
  tu_ngay:     '',
  den_ngay:    '',
  page:        1,
  per_page:    15,
})

// Nhãn trạng thái thanh toán
const pmTrangThaiLabel = (tt) => {
  if (tt == 1 || tt === 'thanh_cong') return { text: 'Thành công', cls: 'badge-green' }
  if (tt == 0 || tt === 'that_bai')   return { text: 'Thất bại',   cls: 'badge-red' }
  if (tt == 2 || tt === 'hoan_tien')  return { text: 'Hoàn tiền',  cls: 'badge-yellow' }
  return { text: tt ?? 'N/A', cls: '' }
}

// Nhãn phương thức thanh toán – khớp với giá trị string thực tế trong DB
const pmPhuongThucLabel = (pt) => {
  if (!pt) return 'Khác'
  const v = String(pt).toLowerCase()
  if (v === 'momo'    || v === '2') return 'MoMo'
  if (v === 'vnpay'   || v === '1') return 'VNPay'
  if (v === 'tien_mat'|| v === '3') return 'Tiền mặt'
  if (v === 'zalopay' || v === '4') return 'ZaloPay'
  return pt
}

// Gọi API lấy danh sách thanh toán (phân trang cho bảng)
const fetchPayments = async () => {
  paymentsLoading.value = true
  paymentsError.value   = null
  try {
    // Chỉ gửi các param có giá trị
    const params = Object.fromEntries(
      Object.entries(pmFilter.value).filter(([, v]) => v !== '' && v !== null)
    )
    const res = await adminApi.getPayments(params)
    payments.value = extractPaginatedRows(res)
    paymentsMeta.value = extractPaginatedMeta(res)
  } catch (err) {
    paymentsError.value = err?.message ?? 'Không thể tải dữ liệu thanh toán'
  } finally {
    paymentsLoading.value = false
  }
}

// Chuyển trang bảng thanh toán
const pmGoToPage = (p) => {
  if (p < 1 || p > (paymentsMeta.value?.last_page ?? 1)) return
  pmFilter.value.page = p
  fetchPayments()
}

// Áp dụng bộ lọc cho bảng thanh toán riêng (bộ lọc bên trong bảng)
const pmApplyFilter = () => {
  pmFilter.value.page = 1
  fetchPayments()
}

// Computed số trang bảng thanh toán (window ±2)
const pmPageNumbers = computed(() => {
  const total = paymentsMeta.value?.last_page ?? 1
  const cur   = paymentsMeta.value?.current_page ?? 1
  const range = []
  for (let i = Math.max(1, cur - 2); i <= Math.min(total, cur + 2); i++) range.push(i)
  return range
})

/** Số tiền một bản ghi thanh toán (khớp cột bảng + fallback list). */
const getPmSoTienForTotal = (pm) =>
  Number(pm.so_tien_thuc_thu ?? pm.so_tien ?? pm.tong_tien ?? pm.amount ?? pm.gia_tien ?? 0)

/**
 * Giao dịch được tính vào doanh thu: thành công / hoàn tất; loại thất bại, hoàn tiền, chờ.
 * Bản ghi không có trạng thái (tiền mặt…) vẫn cộng nếu không thuộc nhóm loại trừ.
 */
const isPmThanhToanCong = (pm) => {
  const raw = pm?.trang_thai ?? pm?.status
  if (raw === null || raw === undefined || raw === '') return true
  if (raw === 1 || raw === true) return true
  const tt = String(raw).toLowerCase().trim()
  if (tt === 'thanh_cong' || tt === 'success' || tt === 'completed' || tt === 'da_thanh_toan') return true
  if (raw === 0 || tt === '0' || tt === 'that_bai' || tt === 'failed' || tt === 'failure') return false
  if (raw === 2 || tt === '2' || tt === 'hoan_tien' || tt === 'refund' || tt === 'refunded') return false
  if (tt === 'huy' || tt === 'da_huy' || tt === 'cancelled' || tt === 'cancel') return false
  if (tt === 'cho' || tt === 'pending' || tt === 'cho_xu_ly' || tt === 'processing' || tt === 'dang_cho') return false
  return true
}

/** Tổng doanh thu KPI = cộng toàn bộ số tiền hiển thị trong danh sách thanh toán theo bộ lọc hiện tại. */
const applyPaymentRevenueTotal = async (tuNgay, denNgay, gen) => {
  if (!tuNgay || !denNgay) return
  let sum = 0
  try {
    let page = 1
    const perPage = 200
    let lastPage = 1
    for (;;) {
      const res = await adminApi.getPayments({
        tu_ngay: tuNgay,
        den_ngay: denNgay,
        page,
        per_page: perPage,
      })
      if (gen !== undefined && gen !== thongKeLoadGen.value) return
      const rows = extractPaginatedRows(res)
      const meta = extractPaginatedMeta(res)
      lastPage = Math.max(1, Number(meta?.last_page ?? 1) || 1)
      for (const pm of rows) {
        sum += getPmSoTienForTotal(pm)
      }
      if (page >= lastPage || rows.length < perPage) break
      page += 1
      if (page > 200) break
    }
    if (gen !== undefined && gen !== thongKeLoadGen.value) return
    if (sum > 0) {
      totalStats.value.tongDoanhThu = sum
    }
  } catch (e) {
    console.warn('[ThongKe] tổng doanh thu (thanh-toan):', e?.message ?? e)
  }
}

// ─── Nút "Áp dụng" chính ─────────────────────────────────────────────
const handleApplyFilter = async () => {
  const myGen = ++thongKeLoadGen.value
  baoCaoSetsPrimaryKpi.value = false
  const { tuNgay, denNgay } = buildDateRange()
  pmFilter.value.tu_ngay = tuNgay; pmFilter.value.den_ngay = denNgay; pmFilter.value.page = 1
  await fetchBaoCaoAdmin(tuNgay, denNgay, myGen)
  if (myGen !== thongKeLoadGen.value) return
  await fetchStats(tuNgay, denNgay, myGen)
  if (myGen !== thongKeLoadGen.value) return
  await applyPaymentRevenueTotal(tuNgay, denNgay, myGen)
  if (myGen !== thongKeLoadGen.value) return
  const tasks = [fetchPayments(), fetchTicketsAnalytics(tuNgay, denNgay, myGen)]
  if (!totalStats.value.khachHangMoi) tasks.push(fetchNewClientsInRange(tuNgay, denNgay))
  await Promise.all(tasks)
}

const onChangeFilterTab = (key) => {
  filterType.value = key
  const now = new Date()
  const yy = String(now.getFullYear())
  if (key === 'year') {
    selectedYear.value = yy
  } else if (key === 'month') {
    selectedMonth.value = `${yy}-${String(now.getMonth() + 1).padStart(2, '0')}`
  } else if (key === 'quarter') {
    selectedQuarterYear.value = yy
    selectedQuarterNum.value = String(Math.floor(now.getMonth() / 3) + 1)
  } else if (key === 'range') {
    const m = String(now.getMonth() + 1).padStart(2, '0')
    const d = String(now.getDate()).padStart(2, '0')
    dateFrom.value = `${yy}-${m}-01`
    dateTo.value = `${yy}-${m}-${d}`
  }
  handleApplyFilter()
}

onMounted(async () => {
  const myGen = ++thongKeLoadGen.value
  baoCaoSetsPrimaryKpi.value = false
  const { tuNgay, denNgay } = buildDateRange()
  pmFilter.value.tu_ngay = tuNgay; pmFilter.value.den_ngay = denNgay
  await fetchBaoCaoAdmin(tuNgay, denNgay, myGen)
  if (myGen !== thongKeLoadGen.value) return
  await fetchStats(tuNgay, denNgay, myGen)
  if (myGen !== thongKeLoadGen.value) return
  await applyPaymentRevenueTotal(tuNgay, denNgay, myGen)
  if (myGen !== thongKeLoadGen.value) return
  const tasks = [fetchPayments(), fetchKhachHang(), fetchNhaXe(), fetchChuyenXe(), fetchTicketsAnalytics(tuNgay, denNgay, myGen)]
  if (!totalStats.value.khachHangMoi) tasks.push(fetchNewClientsInRange(tuNgay, denNgay))
  await Promise.all(tasks)
})


// ─── Helpers ─────────────────────────────────────────────────────────
const formatMoney = (n) => {
  if (!n) return '0 đ'
  if (n >= 1_000_000_000) return (n / 1_000_000_000).toFixed(2) + ' tỷ'
  if (n >= 1_000_000)     return (n / 1_000_000).toFixed(1) + ' triệu'
  return n.toLocaleString('vi-VN') + ' đ'
}
const formatFull = (n) => (n ?? 0).toLocaleString('vi-VN') + ' ₫'

// ─── Refresh ─────────────────────────────────────────────────────────
const isLoading = ref(false)
const handleRefresh = async () => {
  isLoading.value = true
  await Promise.all([handleApplyFilter(), fetchKhachHang(), fetchNhaXe(), fetchChuyenXe()])
  isLoading.value = false
}

// ─── Computed chart data ──────────────────────────────────────────────
const baseTooltip = {
  backgroundColor: 'rgba(15,23,42,0.92)', titleColor: '#f8fafc',
  bodyColor: '#cbd5e1', borderColor: 'rgba(255,255,255,0.08)',
  borderWidth: 1, padding: 12, cornerRadius: 10,
}

const revenueLineData = computed(() => ({
  labels: ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'],
  datasets: [{
    label: 'Doanh thu (triệu đ)',
    data: monthlyRevenue.value.map(v => +(v / 1_000_000).toFixed(1)),
    borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.10)',
    borderWidth: 3, pointBackgroundColor: '#16a34a',
    pointRadius: 5, pointHoverRadius: 8, tension: 0.4, fill: true,
  }]
}))

const ticketBarData = computed(() => ({
  labels: ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'],
  datasets: [{
    label: 'Số vé',
    data: monthlyTickets.value,
    backgroundColor: 'rgba(99,102,241,0.85)',
    borderRadius: 8, borderSkipped: false,
  }]
}))

const quarterBarData = computed(() => {
  const m = monthlyRevenue.value
  const qYear = filterType.value === 'quarter' ? selectedQuarterYear.value : selectedYear.value
  const qs = [
    { label: `Q1/${qYear}`, value: m[0]+m[1]+m[2] },
    { label: `Q2/${qYear}`, value: m[3]+m[4]+m[5] },
    { label: `Q3/${qYear}`, value: m[6]+m[7]+m[8] },
    { label: `Q4/${qYear}`, value: m[9]+m[10]+m[11] },
  ]
  return {
    labels: qs.map(q => q.label),
    datasets: [{
      label: 'Doanh thu (tỷ đ)',
      data: qs.map(q => +(q.value / 1_000_000_000).toFixed(3)),
      backgroundColor: ['rgba(34,197,94,0.85)','rgba(59,130,246,0.85)','rgba(168,85,247,0.85)','rgba(249,115,22,0.85)'],
      borderRadius: 10, borderSkipped: false,
    }]
  }
})

/** Biểu đồ tròn theo tiêu chí: Đã thanh toán (không tiền mặt), Tiền mặt, Đã hủy */
const ticketPieData = computed(() => {
  const s = ticketVeSlice.value
  const sum = s.daThanhToanNonCash + s.tienMat + s.daHuy
  if (sum === 0) {
    return {
      labels: ['Chưa có dữ liệu vé'],
      datasets: [{ data: [1], backgroundColor: ['#e2e8f0'], borderColor: ['#cbd5e1'], borderWidth: 2, hoverOffset: 4 }],
    }
  }
  return {
    labels: ['Đã thanh toán (không tiền mặt)', 'Thanh toán tiền mặt', 'Đã hủy'],
    datasets: [{
      data: [s.daThanhToanNonCash, s.tienMat, s.daHuy],
      backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
      borderColor: ['#16a34a', '#d97706', '#dc2626'],
      borderWidth: 2,
      hoverOffset: 8,
    }],
  }
})

const lineOptions = computed(() => ({
  responsive: true, maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { ...baseTooltip, callbacks: { label: ctx => ' ' + ctx.raw + ' triệu đ' } }
  },
  scales: {
    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 12 } } },
    y: { grid: { color: 'rgba(226,232,240,0.5)' }, ticks: { color: '#64748b', font: { size: 12 }, callback: v => v + 'M' } }
  }
}))

const barOptions = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false }, tooltip: baseTooltip },
  scales: {
    x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 12 } } },
    y: { grid: { color: 'rgba(226,232,240,0.5)' }, ticks: { color: '#64748b', font: { size: 12 } } }
  }
}

const quarterOptions = {
  ...barOptions,
  plugins: { legend: { display: false }, tooltip: { ...baseTooltip, callbacks: { label: ctx => ' ' + ctx.raw + ' tỷ đ' } } }
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '65%',
  plugins: {
    legend: {
      display: true,
      position: 'right',
      labels: { font: { size: 12 }, usePointStyle: true, padding: 16, boxWidth: 10 }
    },
    tooltip: baseTooltip
  }
}

// ─── Tab biểu đồ ────────────────────────────────────────────────────
const activeChartTab = ref('revenue')
const chartTabs = [
  { key: 'revenue',  label: 'Doanh thu / Tháng', icon: TrendingUp },
  { key: 'tickets',  label: 'Vé bán / Tháng',    icon: Ticket },
  { key: 'quarter',  label: 'Doanh thu / Quý',   icon: BarChart2 },
]

// dailyData giữ lại để tránh lỗi template (không dùng nữa)
const currentPage = ref(1)
const dailyData   = ref([])

// ─── Xuất PDF (ưu tiên BE blob; không được thì in trình duyệt) ───────
const isExportingPdf = ref(false)
const handleExportPdf = async () => {
  const { tuNgay, denNgay } = buildDateRange()
  const s = totalStats.value
  const v = ticketVeSlice.value
  const period = `${tuNgay} → ${denNgay}`
  isExportingPdf.value = true
  try {
    const blob = await adminApi.exportBaoCao({ tu_ngay: tuNgay, den_ngay: denNgay, format: 'pdf' })
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
    alert('Trình duyệt đã chặn cửa sổ mới — hãy cho phép popup để in PDF.')
    return
  }
  const rowsTop = routeTop.value.map((r) => `<tr><td>${r.label}</td><td style="text-align:right">${r.doanh_thu.toLocaleString('vi-VN')}</td><td style="text-align:right">${r.so_ve}</td></tr>`).join('')
  const rowsLow = routeBottom.value.map((r) => `<tr><td>${r.label}</td><td style="text-align:right">${r.doanh_thu.toLocaleString('vi-VN')}</td><td style="text-align:right">${r.so_ve}</td></tr>`).join('')
  w.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"/><title>Báo cáo thống kê</title>
  <style>body{font-family:Segoe UI,Arial,sans-serif;padding:24px;color:#0f172a}h1{font-size:20px}table{border-collapse:collapse;width:100%;margin:16px 0}th,td{border:1px solid #cbd5e1;padding:8px;font-size:13px}th{background:#1e40af;color:#fff}.kpi{display:flex;gap:12px;flex-wrap:wrap}.box{flex:1;min-width:140px;border:1px solid #e2e8f0;border-radius:8px;padding:12px}</style></head><body>
  <h1>Báo cáo thống kê — Admin</h1><p>Kỳ: ${period}</p>
  <div class="kpi"><div class="box"><strong>Tổng doanh thu</strong><br/>${(s.tongDoanhThu || 0).toLocaleString('vi-VN')} ₫</div>
  <div class="box"><strong>Vé đã bán (đã TT)</strong><br/>${s.tongVeDaBan}</div>
  <div class="box"><strong>Lấp đầy ghế TB</strong><br/>${s.tyLeLapDay}%</div>
  <div class="box"><strong>KH mới (ước lượng)</strong><br/>${s.khachHangMoi}</div></div>
  <h2>Phân bổ vé</h2><p>Đã TT (không TM): ${v.daThanhToanNonCash} · Tiền mặt: ${v.tienMat} · Đã hủy: ${v.daHuy}</p>
  <h2>Tuyến doanh thu cao</h2><table><thead><tr><th>Tuyến</th><th>Doanh thu</th><th>Số vé</th></tr></thead><tbody>${rowsTop || '<tr><td colspan="3">—</td></tr>'}</tbody></table>
  <h2>Tuyến ít khách (theo số vé)</h2><table><thead><tr><th>Tuyến</th><th>Doanh thu</th><th>Số vé</th></tr></thead><tbody>${rowsLow || '<tr><td colspan="3">—</td></tr>'}</tbody></table>
  </body></html>`)
  w.document.close()
  w.focus()
  setTimeout(() => {
    w.print()
    isExportingPdf.value = false
  }, 300)
}

// ─── Xuất Excel ───────────────────────────────────────────────
const isExporting = ref(false)

const handleExportExcel = async () => {
  isExporting.value = true
  try {
    const { tuNgay, denNgay } = buildDateRange()
    try {
      const blob = await adminApi.exportBaoCao({ tu_ngay: tuNgay, den_ngay: denNgay, format: 'xlsx' })
      if (blob instanceof Blob && blob.size > 0 && !blob.type.includes('json')) {
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `BaoCao_Admin_${tuNgay}_${denNgay}.xlsx`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
        return
      }
    } catch (e) {
      console.warn('[ThongKe] export Excel BE:', e)
    }

    const now    = new Date()
    const period = filterType.value === 'year'
      ? `Năm ${selectedYear.value}`
      : filterType.value === 'month'
        ? `Tháng ${selectedMonth.value}`
        : filterType.value === 'quarter'
          ? `Quý ${selectedQuarterNum.value}/${selectedQuarterYear.value}`
          : `${dateFrom.value} → ${dateTo.value}`

    const stats  = totalStats.value
    const months = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12']

    // == S1: Tổng quan ==
    const s1 = [
      ['THỐNG KÊ & BÁO CÁO DOANH THU', '', '', ''],
      [`Kỳ báo cáo: ${period}`, '', '', ''],
      [`Thời gian xuất: ${now.toLocaleString('vi-VN')}`, '', '', ''],
      [],
      ['CHỈ SỐ TỔNG QUAN', '', 'GIÁ TRỊ', 'GHI CHÚ'],
      ['Tổng doanh thu (VNĐ)', '', stats.tongDoanhThu, 'Theo thanh toán / vé đã TT'],
      ['Tổng số vé đã bán (đã thanh toán)', '', stats.tongVeDaBan, ''],
      ['Tỷ lệ lấp đầy ghế TB (%)', '', stats.tyLeLapDay, 'Ước từ vé & sức chứa chuyến'],
      ['Khách hàng mới (kỳ)', '', stats.khachHangMoi],
      ['Tổng số vé giao dịch (TT)', '', stats.tongVe, ''],
      ['Vé hoàn thành', '', stats.veHoanThanh, ''],
      ['Vé đã huỷ', '', stats.veHuy, ''],
      ['Vé đang chờ', '', stats.veCho, ''],
      ['Tỷ lệ hoàn thành (%)', '', stats.tyLeHoanThanh, ''],
      ['Tỷ lệ huỷ (%)', '', stats.tyLeHuy, ''],
      ['Tổng khách hàng', '', stats.tongKhachHang, 'Tổng số trong kỳ'],
      ['Tổng nhà xe đối tác', '', stats.tongNhaXe, 'Đang hoạt động'],
      ['Tổng chuyến xe', '', stats.tongChuyenXe, 'Tổng chuyến đã hoàn thành'],
    ]

    // == S2: Doanh thu theo tháng ==
    const s2 = [
      [`DOANH THU THEO THÁNG – ${period}`, '', '', ''],
      [],
      ['Tháng', 'Doanh thu (VNĐ)', 'Số vé', 'Doanh thu (triệu đ)'],
    ]
    months.forEach((m, i) => {
      s2.push([m, monthlyRevenue.value[i], monthlyTickets.value[i], +(monthlyRevenue.value[i]/1_000_000).toFixed(3)])
    })
    const totalRev = monthlyRevenue.value.reduce((a, b) => a + b, 0)
    const totalTk  = monthlyTickets.value.reduce((a, b) => a + b, 0)
    s2.push([])
    s2.push(['TỔNG CỘNG', totalRev, totalTk, +(totalRev/1_000_000).toFixed(3)])

    // == S3: Doanh thu theo quý ==
    const rev = monthlyRevenue.value
    const s3 = [
      [`DOANH THU THEO QUÝ – ${period}`, '', '', ''],
      [],
      ['Quý', 'Tháng', 'Doanh thu (VNĐ)', 'Tỷ trọng (%)'],
    ]
    const qData = [
      { label: `Q1/${filterType.value === 'year' ? selectedYear.value : ''}`, months: 'T1–T3', val: rev[0]+rev[1]+rev[2] },
      { label: `Q2/${filterType.value === 'year' ? selectedYear.value : ''}`, months: 'T4–T6', val: rev[3]+rev[4]+rev[5] },
      { label: `Q3/${filterType.value === 'year' ? selectedYear.value : ''}`, months: 'T7–T9', val: rev[6]+rev[7]+rev[8] },
      { label: `Q4/${filterType.value === 'year' ? selectedYear.value : ''}`, months: 'T10–T12', val: rev[9]+rev[10]+rev[11] },
    ]
    const qTotal = qData.reduce((s, q) => s + q.val, 0)
    qData.forEach(q => s3.push([q.label, q.months, q.val, qTotal > 0 ? +((q.val/qTotal)*100).toFixed(1) : 0]))
    s3.push([])
    s3.push(['TỔNG CỘNG', '', qTotal, '100%'])

    // == S4: Chi tiết thanh toán (tối đa 2000 bản ghi) ==
    const s4 = [
      [`CHI TIẾT GIAO DỊCH THANH TOÁN – ${period}`, '', '', '', '', '', ''],
      [],
      ['STT', 'Mã TT', 'Mã giao dịch', 'Số tiền (VNĐ)', 'Phương thức', 'Trạng thái', 'Thời gian'],
    ]
    let detailData = payments.value ?? []
    if (detailData.length === 0) {
      // Lấy từ API nếu bảng rỗng
      try {
        const res = await adminApi.getPayments({ per_page: 2000, page: 1, ...(tuNgay && { tu_ngay: tuNgay }), ...(denNgay && { den_ngay: denNgay }) })
        detailData = (res?.data ?? res)?.data ?? []
      } catch {}
    }
    const getST2 = pm => Number(pm.so_tien_thuc_thu ?? pm.so_tien ?? pm.tong_tien ?? 0)
    const getPT  = pm => pmPhuongThucLabel(pm.phuong_thuc)
    const getTT2 = pm => {
      const s = pmTrangThaiLabel(pm.trang_thai ?? pm.status)
      return s.text ?? (pm.trang_thai ?? 'N/A')
    }
    detailData.forEach((pm, idx) => {
      s4.push([
        idx + 1,
        pm.ma_thanh_toan ?? pm.id ?? '',
        pm.ma_giao_dich ?? '',
        getST2(pm),
        getPT(pm),
        getTT2(pm),
        pm.thoi_gian_thanh_toan ?? pm.created_at ?? '',
      ])
    })
    if (detailData.length) {
      const tongTT = detailData.reduce((s, pm) => s + getST2(pm), 0)
      s4.push([])
      s4.push(['', '', 'TỔNG', tongTT, '', '', ''])
    }

    const s5 = [['TUYẾN — DOANH THU CAO', '', ''], [], ['Tuyến', 'Doanh thu (VNĐ)', 'Số vé']]
    routeTop.value.forEach((r) => s5.push([r.label, r.doanh_thu, r.so_ve]))
    const s6 = [['TUYẾN — ÍT KHÁCH (THEO SỐ VÉ)', '', ''], [], ['Tuyến', 'Doanh thu (VNĐ)', 'Số vé']]
    routeBottom.value.forEach((r) => s6.push([r.label, r.doanh_thu, r.so_ve]))
    const s7 = [
      ['PHÂN BỔ VÉ (BIỂU ĐỒ TRÒN)', '', ''],
      [],
      ['Đã thanh toán (không tiền mặt)', ticketVeSlice.value.daThanhToanNonCash],
      ['Thanh toán tiền mặt', ticketVeSlice.value.tienMat],
      ['Đã hủy', ticketVeSlice.value.daHuy],
    ]

    // == Tạo file XML Spreadsheet (Excel .xls) ==
    const xlsHeader = `<?xml version="1.0" encoding="UTF-8"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
<Styles>
  <Style ss:ID="title"><Font ss:Bold="1" ss:Size="14"/><Alignment ss:Horizontal="Center"/></Style>
  <Style ss:ID="header"><Font ss:Bold="1"/><Interior ss:Color="#1a56db" ss:Pattern="Solid"/><Font ss:Color="#FFFFFF" ss:Bold="1"/></Style>
  <Style ss:ID="money"><NumberFormat ss:Format="#,##0"/></Style>
  <Style ss:ID="pct"><NumberFormat ss:Format="0.0"/></Style>
  <Style ss:ID="bold"><Font ss:Bold="1"/></Style>
  <Style ss:ID="alt"><Interior ss:Color="#f0f4ff" ss:Pattern="Solid"/></Style>
</Styles>`

    const escXml = (v) => String(v ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

    const buildSheet = (name, rows) => {
      let out = `<Worksheet ss:Name="${escXml(name)}"><Table>`
      rows.forEach((row, ri) => {
        out += '<Row>'
        row.forEach((cell) => {
          if (cell === '' || cell === null || cell === undefined) {
            out += '<Cell><Data ss:Type="String"></Data></Cell>'
          } else if (typeof cell === 'number') {
            out += `<Cell><Data ss:Type="Number">${cell}</Data></Cell>`
          } else {
            out += `<Cell><Data ss:Type="String">${escXml(cell)}</Data></Cell>`
          }
        })
        out += '</Row>'
      })
      out += '</Table></Worksheet>'
      return out
    }

    const xlsContent = xlsHeader
      + buildSheet('Tổng quan', s1)
      + buildSheet('Doanh thu theo tháng', s2)
      + buildSheet('Doanh thu theo quý', s3)
      + buildSheet('Chi tiết giao dịch', s4)
      + buildSheet('Tuyến doanh thu cao', s5)
      + buildSheet('Tuyến ít khách', s6)
      + buildSheet('Phân bổ vé', s7)
      + '</Workbook>'

    const blob = new Blob([xlsContent], { type: 'application/vnd.ms-excel;charset=utf-8' })
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    const filename = `BaoCao_ThongKe_${period.replace(/[/\\: ]/g,'_')}_${now.toISOString().slice(0,10)}.xls`
    a.href     = url
    a.download = filename
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch (err) {
    console.error('[ExportExcel]', err)
    alert('Xuất Excel thất bại: ' + (err?.message ?? err))
  } finally {
    isExporting.value = false
  }
}
</script>

<template>
  <div class="thongke-page">

    <!-- ═══ TIÊU ĐỀ TRANG ═══ -->
    <div class="page-header">
      <div class="header-left">
        <div class="header-icon-wrap">
          <BarChart2 class="header-icon" />
        </div>
        <div>
          <h1 class="page-title">Thống Kê & Báo Cáo</h1>
          <p class="page-sub">KPI tổng quan, thống kê theo tuyến, trạng thái vé và xuất báo cáo</p>
        </div>
      </div>
      <div class="header-actions">
        <button
          class="btn-compare"
          :class="{ active: compareMode }"
          @click="compareMode = !compareMode"
          id="btn-compare-mode"
        >
          <TrendingUp class="btn-icon" />
          So sánh năm trước
        </button>
        <button class="btn-export btn-export--secondary" type="button" id="btn-export-pdf" @click="handleExportPdf" :disabled="isExportingPdf">
          <FileText class="btn-icon" />
          {{ isExportingPdf ? 'Đang mở...' : 'Xuất PDF' }}
        </button>
        <button class="btn-export" id="btn-export-excel" @click="handleExportExcel" :disabled="isExporting">
          <Download class="btn-icon" />
          {{ isExporting ? 'Đang xuất...' : 'Xuất Excel' }}
        </button>
        <button class="btn-refresh" :class="{ spinning: isLoading }" @click="handleRefresh" id="btn-refresh">
          <RefreshCw class="btn-icon" />
        </button>
      </div>
    </div>

    <!-- ═══ BỘ LỌC THỜI GIAN ═══ -->
    <div class="filter-card">
      <div class="filter-tabs">
        <button
          v-for="tab in filterTabs"
          :key="tab.key"
          class="filter-tab"
          :class="{ active: filterType === tab.key }"
          @click="onChangeFilterTab(tab.key)"
          :id="`filter-tab-${tab.key}`"
        >
          <Calendar class="tab-icon" />
          {{ tab.label }}
        </button>
      </div>

      <div class="filter-inputs">
        <!-- Khoảng ngày -->
        <template v-if="filterType === 'range'">
          <div class="input-group">
            <label>Từ ngày</label>
            <input type="date" v-model="dateFrom" class="date-input" id="input-date-from" @change="handleApplyFilter" />
          </div>
          <div class="input-sep">→</div>
          <div class="input-group">
            <label>Đến ngày</label>
            <input type="date" v-model="dateTo" class="date-input" id="input-date-to" @change="handleApplyFilter" />
          </div>
        </template>

        <template v-if="filterType === 'month'">
          <div class="input-group">
            <label>Chọn tháng</label>
            <input type="month" v-model="selectedMonth" class="date-input" id="input-month" @change="handleApplyFilter" />
          </div>
        </template>

        <template v-if="filterType === 'quarter'">
          <div class="input-group">
            <label><Calendar class="label-icon" /> Chọn quý</label>
            <div class="quarter-wrap">
              <select v-model="selectedQuarterYear" class="date-input quarter-sel" id="input-quarter-year" @change="handleApplyFilter">
                <option value="2026">2026</option>
                <option value="2025">2025</option>
                <option value="2024">2024</option>
                <option value="2023">2023</option>
              </select>
              <span class="input-sep">–</span>
              <select v-model="selectedQuarterNum" class="date-input quarter-sel" id="input-quarter-num" @change="handleApplyFilter">
                <option value="1">Quý 1 (T1-T3)</option>
                <option value="2">Quý 2 (T4-T6)</option>
                <option value="3">Quý 3 (T7-T9)</option>
                <option value="4">Quý 4 (T10-T12)</option>
              </select>
            </div>
          </div>
        </template>

        <template v-if="filterType === 'year'">
          <div class="input-group">
            <label><Calendar class="label-icon" /> Chọn năm</label>
            <select v-model="selectedYear" class="date-input" id="input-year" @change="handleApplyFilter">
              <option value="2026">Năm 2026</option>
              <option value="2025">Năm 2025</option>
              <option value="2024">Năm 2024</option>
              <option value="2023">Năm 2023</option>
            </select>
          </div>
        </template>

        <button
          class="btn-apply"
          id="btn-apply-filter"
          :class="{ loading: paymentsLoading }"
          :disabled="paymentsLoading"
          @click="handleApplyFilter"
        >
          <template v-if="paymentsLoading">
            <div class="btn-spinner"></div>
            Đang tải...
          </template>
          <template v-else>
            <Filter class="btn-icon" />
            Áp dụng
          </template>
        </button>
      </div>
    </div>

    <!-- ═══ KPI TỔNG QUAN (tiêu chí chấp nhận) ═══ -->
    <div class="kpi-grid kpi-grid--four">
      <div class="kpi-card kpi-green">
        <div class="kpi-top">
          <div class="kpi-icon-wrap green"><DollarSign class="kpi-icon" /></div>
        </div>
        <p class="kpi-label">Tổng doanh thu</p>
        <h2 class="kpi-value">{{ formatMoney(totalStats.tongDoanhThu) }}</h2>
        <p class="kpi-sub">{{ formatFull(totalStats.tongDoanhThu) }} · tổng thanh toán thành công trong kỳ</p>
      </div>

      <div class="kpi-card kpi-blue">
        <div class="kpi-top">
          <div class="kpi-icon-wrap blue"><Ticket class="kpi-icon" /></div>
        </div>
        <p class="kpi-label">Tổng số vé đã bán</p>
        <h2 class="kpi-value">{{ totalStats.tongVeDaBan.toLocaleString() }}</h2>
        <p class="kpi-sub">Vé trạng thái đã thanh toán (theo dữ liệu vé)</p>
      </div>

      <div class="kpi-card kpi-purple">
        <div class="kpi-top">
          <div class="kpi-icon-wrap purple"><Percent class="kpi-icon" /></div>
        </div>
        <p class="kpi-label">Tỷ lệ lấp đầy ghế TB</p>
        <h2 class="kpi-value">{{ totalStats.tyLeLapDay }}%</h2>
        <p class="kpi-sub">Ước lượng theo số vé / sức chứa chuyến</p>
      </div>

      <div class="kpi-card kpi-orange">
        <div class="kpi-top">
          <div class="kpi-icon-wrap orange"><Users class="kpi-icon" /></div>
        </div>
        <p class="kpi-label">Khách hàng mới</p>
        <h2 class="kpi-value">{{ totalStats.khachHangMoi.toLocaleString() }}</h2>
        <p class="kpi-sub">Đăng ký trong kỳ</p>
      </div>
    </div>

    <div class="kpi-secondary-bar">
      <span><Building2 class="inline-ic" /> Nhà xe: <strong>{{ totalStats.tongNhaXe }}</strong></span>
      <span><Bus class="inline-ic" /> Chuyến xe (hệ thống): <strong>{{ totalStats.tongChuyenXe }}</strong></span>
      <span><Users class="inline-ic" /> Khách hàng (tổng): <strong>{{ totalStats.tongKhachHang }}</strong></span>
    </div>

    <!-- ═══ BIỂU ĐỒ CHÍNH ═══ -->
    <div class="charts-main-grid">

      <!-- Biểu đồ lớn – line / bar theo tab -->
      <div class="panel chart-panel">
        <div class="panel-header">
          <div class="chart-tabs">
            <button
              v-for="tab in chartTabs"
              :key="tab.key"
              class="chart-tab"
              :class="{ active: activeChartTab === tab.key }"
              @click="activeChartTab = tab.key"
              :id="`chart-tab-${tab.key}`"
            >
              <component :is="tab.icon" class="chart-tab-icon" />
              {{ tab.label }}
            </button>
          </div>
        </div>
        <div class="chart-body">
          <div v-show="activeChartTab === 'revenue'" class="chart-wrap">
            <Line :data="revenueLineData" :options="lineOptions" />
          </div>
          <div v-show="activeChartTab === 'tickets'" class="chart-wrap">
            <Bar :data="ticketBarData" :options="barOptions" />
          </div>
          <div v-show="activeChartTab === 'quarter'" class="chart-wrap">
            <Bar :data="quarterBarData" :options="quarterOptions" />
          </div>
        </div>
      </div>

      <!-- Donut – trạng thái vé theo tiêu chí -->
      <div class="panel donut-panel">
        <div class="panel-header">
          <h3 class="panel-title">
            <PieChart class="panel-icon" />
            Trạng thái vé (tỷ lệ)
          </h3>
        </div>
        <div class="donut-wrap">
          <Doughnut :data="ticketPieData" :options="doughnutOptions" />
        </div>
        <div class="donut-summary">
          <div class="ds-item">
            <CheckCircle class="ds-icon green" />
            <div>
              <p class="ds-label">Đã thanh toán (không TM)</p>
              <p class="ds-value green-text">{{ ticketVeSlice.daThanhToanNonCash.toLocaleString() }}</p>
            </div>
            <span class="ds-count">vé</span>
          </div>
          <div class="ds-item">
            <Clock class="ds-icon yellow" />
            <div>
              <p class="ds-label">Tiền mặt</p>
              <p class="ds-value yellow-text">{{ ticketVeSlice.tienMat.toLocaleString() }}</p>
            </div>
            <span class="ds-count">vé</span>
          </div>
          <div class="ds-item">
            <XCircle class="ds-icon red" />
            <div>
              <p class="ds-label">Đã hủy</p>
              <p class="ds-value red-text">{{ ticketVeSlice.daHuy.toLocaleString() }}</p>
            </div>
            <span class="ds-count">vé</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Thống kê theo tuyến đường ═══ -->
    <div class="route-insight-grid">
      <div class="panel route-panel route-panel--wide">
        <div class="panel-header">
          <h3 class="panel-title">
            <MapPin class="panel-icon" />
            Tuyến doanh thu cao nhất
          </h3>
          <span class="panel-sub">Theo vé đã thanh toán trong kỳ</span>
        </div>
        <div class="route-list">
          <div v-if="routeTop.length === 0" class="empty-hint">Chưa có dữ liệu tuyến — kiểm tra API vé có kèm chuyến/tuyến.</div>
          <div v-for="(r, idx) in routeTop" :key="'top-'+r.label" class="route-item">
            <div class="route-rank" :class="`rank-${idx + 1}`">{{ idx + 1 }}</div>
            <div class="route-info">
              <p class="route-name">{{ r.label }}</p>
              <div class="route-bar-wrap">
                <div
                  class="route-bar-fill"
                  :style="{ width: (routeTop[0]?.doanh_thu ? Math.min(100, (r.doanh_thu / routeTop[0].doanh_thu) * 100) : 0) + '%', background: idx < 3 ? '#22c55e' : '#3b82f6' }"
                />
              </div>
            </div>
            <div class="route-stats">
              <p class="route-revenue">{{ formatMoney(r.doanh_thu) }}</p>
              <p class="route-tickets">{{ r.so_ve.toLocaleString() }} vé</p>
            </div>
          </div>
        </div>
      </div>
      <div class="panel route-panel route-panel--wide">
        <div class="panel-header">
          <h3 class="panel-title">
            <MapPin class="panel-icon" />
            Tuyến ít khách (điều chỉnh lịch)
          </h3>
          <span class="panel-sub">Sắp xếp theo số vé thấp trong kỳ</span>
        </div>
        <div class="route-list">
          <div v-if="routeBottom.length === 0" class="empty-hint">—</div>
          <div v-for="(r, idx) in routeBottom" :key="'low-'+r.label" class="route-item route-item--warn">
            <div class="route-rank rank-muted">{{ idx + 1 }}</div>
            <div class="route-info">
              <p class="route-name">{{ r.label }}</p>
              <p class="route-meta">{{ r.so_ve }} vé · {{ formatMoney(r.doanh_thu) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ GRID DƯỚI: Nhà xe + Chỉ số hiệu suất ═══ -->
    <div class="charts-bottom-grid">

      <!-- Doanh thu theo nhà xe -->
      <div class="panel route-panel">
        <div class="panel-header">
          <h3 class="panel-title">
            <Building2 class="panel-icon" />
            Doanh thu theo nhà xe
          </h3>
          <span class="panel-sub">Top 7 nhà xe</span>
        </div>
        <div class="route-list">
          <div v-for="(bus, idx) in revenueByBus" :key="idx" class="route-item">
            <div class="route-rank" :class="`rank-${idx + 1}`">{{ idx + 1 }}</div>
            <div class="route-info">
              <p class="route-name">{{ bus.name }}</p>
              <div class="route-bar-wrap">
                <div
                  class="route-bar-fill"
                  :style="{ width: bus.pct + '%', background: idx < 3 ? '#22c55e' : '#3b82f6' }"
                ></div>
              </div>
            </div>
            <div class="route-stats">
              <p class="route-revenue">{{ formatMoney(bus.revenue) }}</p>
              <p class="route-tickets">{{ bus.tickets.toLocaleString() }} vé</p>
            </div>
            <div class="route-pct">{{ bus.pct }}%</div>
          </div>
        </div>
      </div>

      <!-- Chỉ số hiệu suất -->
      <div class="panel rates-panel">
        <div class="panel-header">
          <h3 class="panel-title">
            <Star class="panel-icon" />
            Chỉ số hiệu suất
          </h3>
        </div>
        <div class="rates-body">
          <!-- Tỉ lệ hoàn thành -->
          <div class="rate-card success">
            <div class="rate-header">
              <CheckCircle class="rate-icon green" />
              <span>Hoàn thành</span>
            </div>
            <div class="rate-circle-wrap">
              <svg class="rate-circle" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="34" fill="none" stroke="#dcfce7" stroke-width="8"/>
                <circle cx="40" cy="40" r="34" fill="none" stroke="#22c55e" stroke-width="8"
                  stroke-dasharray="213.6"
                  :stroke-dashoffset="213.6 * (1 - totalStats.tyLeHoanThanh / 100)"
                  stroke-linecap="round" transform="rotate(-90 40 40)" />
              </svg>
              <span class="rate-pct green-text">{{ totalStats.tyLeHoanThanh }}%</span>
            </div>
            <p class="rate-desc">{{ totalStats.veHoanThanh.toLocaleString() }} / {{ totalStats.tongVe.toLocaleString() }} vé</p>
          </div>

          <!-- Tỉ lệ huỷ đơn -->
          <div class="rate-card danger">
            <div class="rate-header">
              <XCircle class="rate-icon red" />
              <span>Tỉ lệ huỷ</span>
            </div>
            <div class="rate-circle-wrap">
              <svg class="rate-circle" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="34" fill="none" stroke="#fee2e2" stroke-width="8"/>
                <circle cx="40" cy="40" r="34" fill="none" stroke="#ef4444" stroke-width="8"
                  stroke-dasharray="213.6"
                  :stroke-dashoffset="213.6 * (1 - totalStats.tyLeHuy / 100)"
                  stroke-linecap="round" transform="rotate(-90 40 40)" />
              </svg>
              <span class="rate-pct red-text">{{ totalStats.tyLeHuy }}%</span>
            </div>
            <p class="rate-desc">{{ totalStats.veHuy.toLocaleString() }} vé bị huỷ</p>
          </div>

          <!-- Tỉ lệ lấp đầy chỗ -->
          <div class="rate-card info">
            <div class="rate-header">
              <Bus class="rate-icon blue" />
              <span>Lấp đầy chỗ</span>
            </div>
            <div class="rate-circle-wrap">
              <svg class="rate-circle" viewBox="0 0 80 80">
                <circle cx="40" cy="40" r="34" fill="none" stroke="#dbeafe" stroke-width="8"/>
                <circle cx="40" cy="40" r="34" fill="none" stroke="#3b82f6" stroke-width="8"
                  stroke-dasharray="213.6" :stroke-dashoffset="213.6 * (1 - Math.min(100, totalStats.tyLeLapDay) / 100)"
                  stroke-linecap="round" transform="rotate(-90 40 40)" />
              </svg>
              <span class="rate-pct blue-text">{{ totalStats.tyLeLapDay }}%</span>
            </div>
            <p class="rate-desc">Bình quân lấp đầy / chuyến (ước lượng)</p>
          </div>
        </div>

        <!-- Chỉ số bổ sung -->
        <div class="extra-stats">
          <div class="ex-stat">
            <TrendingUp class="ex-icon green" />
            <div>
              <p class="ex-label">Tăng trưởng DT</p>
              <p class="ex-val green-text">{{ totalStats.tangTruong }}</p>
            </div>
          </div>
          <div class="ex-stat">
            <DollarSign class="ex-icon blue" />
            <div>
              <p class="ex-label">DT / Chuyến TB</p>
              <p class="ex-val blue-text">{{ formatMoney(totalStats.tongChuyenXe ? Math.round(totalStats.tongDoanhThu / totalStats.tongChuyenXe) : 0) }}</p>
            </div>
          </div>
          <div class="ex-stat">
            <Users class="ex-icon purple" />
            <div>
              <p class="ex-label">Vé / Khách TB</p>
              <p class="ex-val purple-text">{{ totalStats.tongKhachHang ? (totalStats.tongVe / totalStats.tongKhachHang).toFixed(1) : '0' }}</p>
            </div>
          </div>
          <div class="ex-stat">
            <Ticket class="ex-icon orange" />
            <div>
              <p class="ex-label">DT / Vé TB</p>
              <p class="ex-val orange-text">{{ formatMoney(totalStats.tongVe ? Math.round(totalStats.tongDoanhThu / totalStats.tongVe) : 0) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ BẢNG DANH SÁCH THANH TOÁN (DỮ LIỆU THỰC TỪ API) ═══ -->
    <div class="panel daily-panel">
      <div class="panel-header">
        <h3 class="panel-title">
          <DollarSign class="panel-icon" />
          Danh sách Thanh Toán
        </h3>
        <div class="panel-header-right">
          <span v-if="paymentsMeta" class="page-info-badge">
            Tổng {{ paymentsMeta.total }} bản ghi
          </span>
          <button class="btn-refresh" :class="{ spinning: paymentsLoading }" @click="fetchPayments" id="btn-refresh-payments">
            <RefreshCw class="btn-icon" />
          </button>
        </div>
      </div>

      <!-- Bộ lọc thanh toán -->
      <div class="pm-filter-bar">
        <input
          v-model="pmFilter.search"
          class="pm-input"
          placeholder="Tìm mã GD, mã vé..."
          id="pm-search"
          @keyup.enter="pmApplyFilter"
        />
        <select v-model="pmFilter.trang_thai" class="pm-select" id="pm-trang-thai">
          <option value="">Tất cả trạng thái</option>
          <option value="1">Thành công</option>
          <option value="0">Thất bại</option>
          <option value="2">Hoàn tiền</option>
        </select>
        <select v-model="pmFilter.phuong_thuc" class="pm-select" id="pm-phuong-thuc">
          <option value="">Tất cả phương thức</option>
          <option value="1">VNPay</option>
          <option value="2">MoMo</option>
          <option value="3">Tiền mặt</option>
        </select>
        <input type="date" v-model="pmFilter.tu_ngay"  class="pm-input pm-date" id="pm-tu-ngay"  />
        <span class="input-sep">→</span>
        <input type="date" v-model="pmFilter.den_ngay" class="pm-input pm-date" id="pm-den-ngay" />
        <button class="btn-apply" @click="pmApplyFilter" id="btn-pm-filter">
          <Filter class="btn-icon" />
          Lọc
        </button>
      </div>

      <!-- Loading / Error -->
      <div v-if="paymentsLoading" class="pm-state-wrap">
        <div class="pm-spinner"></div>
        <p>Đang tải dữ liệu...</p>
      </div>
      <div v-else-if="paymentsError" class="pm-state-wrap pm-error">
        <XCircle class="pm-state-icon" />
        <p>{{ paymentsError }}</p>
      </div>

      <!-- Bảng dữ liệu -->
      <div v-else class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Mã GD</th>
              <th>Mã vé</th>
              <th>Số tiền</th>
              <th>Phương thức</th>
              <th>Trạng thái</th>
              <th>Thời gian</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="payments.length === 0">
              <td colspan="6" class="empty-cell">Không có dữ liệu</td>
            </tr>
            <tr v-for="pm in payments" :key="pm.id">
              <td class="day-cell">#{{ pm.id }}</td>
              <td>{{ pm.ma_ve ?? pm.ve_id ?? '—' }}</td>
              <td class="money-cell">{{ Number(pm.so_tien ?? pm.tong_tien ?? 0).toLocaleString('vi-VN') }} ₫</td>
              <td>{{ pmPhuongThucLabel(pm.phuong_thuc ?? pm.phuong_thuc_id) }}</td>
              <td>
                <span class="badge" :class="pmTrangThaiLabel(pm.trang_thai).cls">
                  {{ pmTrangThaiLabel(pm.trang_thai).text }}
                </span>
              </td>
              <td>{{ pm.created_at ? new Date(pm.created_at).toLocaleString('vi-VN') : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Phân trang server-side -->
      <div v-if="paymentsMeta && paymentsMeta.last_page > 1" class="pagination-bar">
        <span class="pagi-total">
          Trang {{ paymentsMeta.current_page }} / {{ paymentsMeta.last_page }} · {{ paymentsMeta.total }} bản ghi
        </span>
        <div class="pagi-controls">
          <button class="pagi-btn" :disabled="paymentsMeta.current_page === 1" @click="pmGoToPage(paymentsMeta.current_page - 1)" id="btn-pm-prev">
            ‹ Trước
          </button>

          <button v-if="pmPageNumbers[0] > 1" class="pagi-btn pagi-num" @click="pmGoToPage(1)">1</button>
          <span v-if="pmPageNumbers[0] > 2" class="pagi-dots">…</span>

          <button
            v-for="p in pmPageNumbers" :key="p"
            class="pagi-btn pagi-num"
            :class="{ active: p === paymentsMeta.current_page }"
            @click="pmGoToPage(p)"
            :id="`btn-pm-page-${p}`"
          >{{ p }}</button>

          <span v-if="pmPageNumbers[pmPageNumbers.length - 1] < paymentsMeta.last_page - 1" class="pagi-dots">…</span>
          <button
            v-if="pmPageNumbers[pmPageNumbers.length - 1] < paymentsMeta.last_page"
            class="pagi-btn pagi-num"
            @click="pmGoToPage(paymentsMeta.last_page)"
          >{{ paymentsMeta.last_page }}</button>

          <button class="pagi-btn" :disabled="paymentsMeta.current_page === paymentsMeta.last_page" @click="pmGoToPage(paymentsMeta.current_page + 1)" id="btn-pm-next">
            Sau ›
          </button>
        </div>
      </div>
    </div>


  </div>
</template>

<style scoped>
/* ───── BASE ───── */
.thongke-page {
  padding: 8px 0 32px;
  font-family: 'Inter', system-ui, sans-serif;
}

/* ───── HEADER ───── */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}
.header-left { display: flex; align-items: center; gap: 16px; }
.header-icon-wrap {
  width: 52px; height: 52px;
  border-radius: 14px;
  background: linear-gradient(135deg, #22c55e 0%, #15803d 100%);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(34,197,94,0.35);
  flex-shrink: 0;
}
.header-icon { width: 26px; height: 26px; color: white; }
.page-title  { font-size: 24px; font-weight: 800; color: #0d4f35; margin: 0 0 3px 0; }
.page-sub    { font-size: 13px; color: #64748b; margin: 0; }

.header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.btn-compare, .btn-export {
  display: flex; align-items: center; gap: 6px;
  padding: 9px 18px; border-radius: 10px;
  font-size: 13px; font-weight: 600;
  cursor: pointer; transition: all 0.25s ease; border: 1.5px solid;
}
.btn-compare          { background: white; border-color: #e2e8f0; color: #475569; }
.btn-compare:hover,
.btn-compare.active   { background: #f0fdf4; border-color: #22c55e; color: #16a34a; }
.btn-export           { background: linear-gradient(135deg,#22c55e,#15803d); border-color: transparent; color: white; box-shadow: 0 4px 12px rgba(34,197,94,.3); }
.btn-export:hover     { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(34,197,94,.4); }

.btn-refresh {
  width: 40px; height: 40px; border-radius: 10px;
  background: white; border: 1.5px solid #e2e8f0;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.25s; color: #475569;
}
.btn-refresh:hover            { border-color: #22c55e; color: #16a34a; background: #f0fdf4; }
.btn-refresh.spinning .btn-icon { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.btn-icon { width: 16px; height: 16px; }

/* ───── FILTER ───── */
.filter-card {
  background: white; border-radius: 16px;
  padding: 20px 24px; margin-bottom: 24px;
  box-shadow: 0 2px 12px rgba(0,0,0,.05); border: 1px solid #f1f5f9;
}
.filter-tabs { display: flex; gap: 6px; margin-bottom: 18px; flex-wrap: wrap; }
.filter-tab {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 10px;
  border: 1.5px solid #e2e8f0; background: white;
  color: #64748b; font-size: 13px; font-weight: 500;
  cursor: pointer; transition: all 0.2s;
}
.filter-tab:hover  { border-color: #22c55e; color: #16a34a; }
.filter-tab.active { background: linear-gradient(135deg,#f0fdf4,#dcfce7); border-color: #22c55e; color: #15803d; font-weight: 700; }
.tab-icon { width: 14px; height: 14px; }

.filter-inputs { display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap; }
.input-group   { display: flex; flex-direction: column; gap: 5px; }
.input-group label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.date-input {
  height: 40px; padding: 0 14px;
  border-radius: 10px; border: 1.5px solid #e2e8f0;
  font-size: 14px; color: #374151; background: #f8fafc;
  transition: border-color 0.2s; outline: none; min-width: 160px;
}
.date-input:focus { border-color: #22c55e; background: white; }
.input-sep { color: #94a3b8; font-size: 18px; padding-bottom: 8px; }
.input-group label {
  display: flex; align-items: center; gap: 5px;
}
.label-icon { width: 13px; height: 13px; }
.quarter-wrap { display: flex; align-items: center; gap: 8px; }
.quarter-sel  { min-width: 90px !important; }

.btn-apply {
  display: flex; align-items: center; gap: 6px;
  padding: 0 22px; height: 40px; border-radius: 10px;
  background: linear-gradient(135deg,#22c55e,#15803d); color: white;
  font-size: 13px; font-weight: 700; border: none; cursor: pointer;
  transition: all 0.25s; box-shadow: 0 3px 10px rgba(34,197,94,.3);
}
.btn-apply:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(34,197,94,.4); }
.btn-apply:disabled,
.btn-apply.loading { opacity: 0.7; cursor: not-allowed; transform: none; }
.btn-spinner {
  width: 14px; height: 14px; flex-shrink: 0;
  border: 2px solid rgba(255,255,255,0.4); border-top-color: white;
  border-radius: 50%; animation: spin 0.7s linear infinite;
}

/* ───── KPI GRID ───── */
.kpi-grid {
  display: grid; grid-template-columns: repeat(4, 1fr);
  gap: 18px; margin-bottom: 16px;
}
.kpi-grid--four { grid-template-columns: repeat(4, 1fr); }
.kpi-secondary-bar {
  display: flex; flex-wrap: wrap; gap: 22px; align-items: center;
  padding: 12px 18px; margin-bottom: 22px;
  background: #f8fafc; border-radius: 14px; border: 1px solid #e2e8f0;
  font-size: 13px; color: #475569;
}
.kpi-secondary-bar strong { color: #0f172a; }
.inline-ic { width: 15px; height: 15px; display: inline; vertical-align: -3px; margin-right: 4px; }

.route-insight-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 22px; margin-bottom: 22px;
}
.empty-hint { padding: 20px; text-align: center; color: #94a3b8; font-size: 13px; }
.rank-muted { background: #e2e8f0 !important; color: #64748b !important; }
.route-meta { font-size: 12px; color: #94a3b8; margin: 4px 0 0 0; }
.route-item--warn { border-left: 3px solid #f59e0b; padding-left: 10px; }
.btn-export--secondary {
  background: white; color: #0f172a; border: 1px solid #e2e8f0;
}
.btn-export--secondary:hover { background: #f8fafc; }
.kpi-card {
  background: white; border-radius: 18px;
  padding: 22px 20px; box-shadow: 0 2px 14px rgba(0,0,0,.05);
  border: 1px solid #f1f5f9; transition: transform 0.3s, box-shadow 0.3s;
  position: relative; overflow: hidden;
}
.kpi-card::after {
  content: ''; position: absolute; top: 0; right: 0;
  width: 80px; height: 80px; border-radius: 0 18px 0 100%; opacity: 0.07;
}
.kpi-green::after  { background: #22c55e; }
.kpi-blue::after   { background: #3b82f6; }
.kpi-purple::after { background: #a855f7; }
.kpi-orange::after { background: #f59e0b; }
.kpi-teal::after   { background: #06b6d4; }
.kpi-card:hover    { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,.08); }

.kpi-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.kpi-icon-wrap {
  width: 46px; height: 46px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
}
.kpi-icon-wrap.green  { background: linear-gradient(135deg,#22c55e,#16a34a); }
.kpi-icon-wrap.blue   { background: linear-gradient(135deg,#3b82f6,#2563eb); }
.kpi-icon-wrap.purple { background: linear-gradient(135deg,#a855f7,#7c3aed); }
.kpi-icon-wrap.orange { background: linear-gradient(135deg,#f59e0b,#d97706); }
.kpi-icon-wrap.teal   { background: linear-gradient(135deg,#06b6d4,#0891b2); }
.kpi-icon { width: 22px; height: 22px; color: white; }

.kpi-badge {
  display: inline-flex; align-items: center; gap: 2px;
  font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 8px;
}
.badge-up   { background: #dcfce7; color: #16a34a; }
.badge-down { background: #fee2e2; color: #dc2626; }
.badge-icon { width: 12px; height: 12px; }

.kpi-label { font-size: 12px; color: #64748b; font-weight: 500; margin: 0 0 6px 0; }
.kpi-value { font-size: 26px; font-weight: 800; color: #0d4f35; margin: 0 0 4px 0; line-height: 1.1; }
.kpi-sub   { font-size: 11px; color: #94a3b8; margin: 0; }

/* ───── CHARTS GRID ───── */
.charts-main-grid {
  display: grid; grid-template-columns: 1fr 380px;
  gap: 22px; margin-bottom: 22px;
}
.charts-bottom-grid {
  display: grid; grid-template-columns: 1fr 420px;
  gap: 22px; margin-bottom: 22px;
}

/* ───── PANEL ───── */
.panel {
  background: white; border-radius: 18px;
  box-shadow: 0 2px 14px rgba(0,0,0,.05);
  border: 1px solid #f1f5f9; overflow: hidden;
}
.panel-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 18px 22px; border-bottom: 1px solid #f8fafc;
}
.panel-title {
  font-size: 15px; font-weight: 700; color: #0d4f35;
  margin: 0; display: flex; align-items: center; gap: 8px;
}
.panel-icon { width: 18px; height: 18px; color: #22c55e; }
.panel-sub  { font-size: 12px; color: #94a3b8; font-weight: 500; }

/* ───── CHART TABS ───── */
.chart-tabs { display: flex; gap: 4px; flex-wrap: wrap; }
.chart-tab {
  display: flex; align-items: center; gap: 5px;
  padding: 7px 14px; border-radius: 9px;
  border: 1.5px solid #e2e8f0; background: white;
  color: #64748b; font-size: 12px; font-weight: 500;
  cursor: pointer; transition: all 0.2s;
}
.chart-tab:hover  { border-color: #22c55e; color: #16a34a; }
.chart-tab.active { background: #0d4f35; border-color: #0d4f35; color: white; font-weight: 700; }
.chart-tab-icon   { width: 14px; height: 14px; }
.chart-body { padding: 20px; }
.chart-wrap { height: 320px; }

/* ───── DONUT ───── */
.donut-wrap    { padding: 10px 20px; height: 240px; }
.donut-summary { padding: 0 20px 16px; display: flex; flex-direction: column; gap: 10px; }
.ds-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; background: #f8fafc; border-radius: 12px;
}
.ds-icon { width: 18px; height: 18px; flex-shrink: 0; }
.ds-icon.green  { color: #22c55e; }
.ds-icon.red    { color: #ef4444; }
.ds-icon.yellow { color: #f59e0b; }
.ds-label  { font-size: 12px; color: #64748b; margin: 0 0 2px; }
.ds-value  { font-size: 16px; font-weight: 800; margin: 0; }
.ds-count  { margin-left: auto; font-size: 12px; color: #94a3b8; white-space: nowrap; }
.green-text  { color: #16a34a; }
.red-text    { color: #dc2626; }
.yellow-text { color: #d97706; }
.blue-text   { color: #2563eb; }
.purple-text { color: #7c3aed; }
.orange-text { color: #d97706; }

/* ───── ROUTE LIST (nhà xe) ───── */
.route-list { padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.route-item {
  display: flex; align-items: center; gap: 14px;
  padding: 12px 14px; border-radius: 12px;
  background: #f8fafc; border: 1px solid #f1f5f9;
  transition: border-color 0.2s, background 0.2s;
}
.route-item:hover { border-color: #bbf7d0; background: #f0fdf4; }
.route-rank {
  width: 28px; height: 28px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 800; flex-shrink: 0;
  background: #e2e8f0; color: #64748b;
}
.rank-1 { background: linear-gradient(135deg,#fbbf24,#d97706); color: white; }
.rank-2 { background: linear-gradient(135deg,#94a3b8,#64748b); color: white; }
.rank-3 { background: linear-gradient(135deg,#f97316,#ea580c); color: white; }

.route-info   { flex: 1; min-width: 0; }
.route-name   { font-size: 13px; font-weight: 700; color: #1e293b; margin: 0 0 6px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.route-bar-wrap { height: 5px; background: #e2e8f0; border-radius: 10px; overflow: hidden; }
.route-bar-fill { height: 100%; border-radius: 10px; transition: width 0.5s ease; }
.route-stats  { text-align: right; }
.route-revenue { font-size: 13px; font-weight: 700; color: #0d4f35; margin: 0 0 2px; }
.route-tickets { font-size: 11px; color: #94a3b8; margin: 0; }
.route-pct    { font-size: 13px; font-weight: 700; color: #3b82f6; min-width: 36px; text-align: right; }

/* ───── RATES ───── */
.rates-body { display: flex; gap: 12px; padding: 16px 20px; flex-wrap: wrap; }
.rate-card {
  flex: 1; min-width: 110px;
  background: #f8fafc; border-radius: 14px; padding: 14px 12px;
  text-align: center; border: 1.5px solid #f1f5f9; transition: all 0.2s;
}
.rate-card:hover      { transform: translateY(-2px); }
.rate-card.success    { border-color: #bbf7d0; }
.rate-card.danger     { border-color: #fecaca; }
.rate-card.info       { border-color: #bfdbfe; }
.rate-header {
  display: flex; align-items: center; justify-content: center; gap: 5px;
  font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 10px;
}
.rate-icon       { width: 14px; height: 14px; }
.rate-icon.green { color: #22c55e; }
.rate-icon.red   { color: #ef4444; }
.rate-icon.blue  { color: #3b82f6; }
.rate-circle-wrap {
  position: relative; width: 80px; height: 80px; margin: 0 auto 8px;
}
.rate-circle { width: 80px; height: 80px; }
.rate-pct {
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%,-50%); font-size: 16px; font-weight: 800;
}
.rate-desc { font-size: 10px; color: #94a3b8; }

/* ───── EXTRA STATS ───── */
.extra-stats {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 10px; padding: 0 20px 20px;
}
.ex-stat {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; background: #f8fafc;
  border-radius: 12px; border: 1px solid #f1f5f9;
}
.ex-icon        { width: 18px; height: 18px; flex-shrink: 0; }
.ex-icon.green  { color: #22c55e; }
.ex-icon.blue   { color: #3b82f6; }
.ex-icon.purple { color: #a855f7; }
.ex-icon.orange { color: #f59e0b; }
.ex-label { font-size: 11px; color: #64748b; margin: 0 0 2px; }
.ex-val   { font-size: 14px; font-weight: 700; margin: 0; }

/* ───── DAILY TABLE ───── */
.daily-panel { margin-bottom: 8px; }
.table-wrap  { overflow-x: auto; }
.data-table  { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th {
  padding: 12px 18px; text-align: left;
  font-size: 11px; font-weight: 700; color: #64748b;
  text-transform: uppercase; letter-spacing: 0.5px;
  background: #f8fafc; border-bottom: 1.5px solid #f1f5f9;
}
.data-table td { padding: 12px 18px; border-bottom: 1px solid #f8fafc; color: #374151; }
.data-table tr:hover td      { background: #f0fdf4; }
.data-table tr:last-child td { border-bottom: none; }
.day-cell   { font-weight: 600; color: #1e293b; }
.money-cell { font-weight: 700; color: #15803d; }
.mini-bar-wrap { height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden; min-width: 100px; max-width: 180px; }
.mini-bar { height: 100%; background: linear-gradient(90deg,#22c55e,#16a34a); border-radius: 10px; }

/* ───── PANEL HEADER RIGHT ───── */
.panel-header-right { display: flex; align-items: center; gap: 10px; }
.page-info-badge {
  background: linear-gradient(135deg, #f0fdf4, #dcfce7);
  border: 1px solid #bbf7d0; color: #15803d;
  font-size: 12px; font-weight: 600;
  padding: 4px 12px; border-radius: 20px;
}

/* ───── PAGINATION ───── */
.pagination-bar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-top: 1px solid #f1f5f9;
  flex-wrap: wrap; gap: 12px;
}
.pagi-total { font-size: 13px; color: #64748b; }
.pagi-controls { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pagi-btn {
  min-width: 36px; height: 36px; padding: 0 12px;
  border-radius: 9px; border: 1.5px solid #e2e8f0;
  background: white; color: #475569; font-size: 13px; font-weight: 500;
  cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
}
.pagi-btn:hover:not(:disabled) { border-color: #22c55e; color: #16a34a; background: #f0fdf4; }
.pagi-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pagi-btn.pagi-num  { min-width: 36px; padding: 0; }
.pagi-btn.pagi-num.active {
  background: linear-gradient(135deg, #22c55e, #15803d);
  border-color: transparent; color: white; font-weight: 700;
  box-shadow: 0 3px 10px rgba(34,197,94,0.35);
}
.pagi-dots { color: #94a3b8; font-size: 14px; padding: 0 4px; line-height: 36px; }

/* ───── PAYMENT FILTER BAR ───── */
.pm-filter-bar {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 20px; border-bottom: 1px solid #f1f5f9;
  flex-wrap: wrap; background: #fafafa;
}
.pm-input {
  height: 38px; padding: 0 12px;
  border-radius: 9px; border: 1.5px solid #e2e8f0;
  font-size: 13px; color: #374151; background: white;
  outline: none; transition: border-color 0.2s;
}
.pm-input:focus  { border-color: #22c55e; }
.pm-input[placeholder] { min-width: 180px; }
.pm-date  { min-width: 140px; }
.pm-select {
  height: 38px; padding: 0 10px;
  border-radius: 9px; border: 1.5px solid #e2e8f0;
  font-size: 13px; color: #374151; background: white;
  outline: none; cursor: pointer; transition: border-color 0.2s;
}
.pm-select:focus { border-color: #22c55e; }

/* ───── PAYMENT STATE (loading / error) ───── */
.pm-state-wrap {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 10px; padding: 48px 20px; color: #64748b; font-size: 14px;
}
.pm-spinner {
  width: 32px; height: 32px;
  border: 3px solid #e2e8f0; border-top-color: #22c55e;
  border-radius: 50%; animation: spin 0.8s linear infinite;
}
.pm-error      { color: #ef4444; }
.pm-state-icon { width: 28px; height: 28px; }
.empty-cell    { text-align: center; color: #94a3b8; padding: 36px; font-size: 14px; }

/* ───── RESPONSIVE ───── */
@media (max-width: 1280px) {
  .kpi-grid           { grid-template-columns: repeat(2, 1fr); }
  .charts-main-grid   { grid-template-columns: 1fr; }
  .charts-bottom-grid { grid-template-columns: 1fr; }
  .route-insight-grid { grid-template-columns: 1fr; }
  .donut-wrap         { height: 280px; }
}
@media (max-width: 900px) {
  .kpi-grid   { grid-template-columns: repeat(2, 1fr); }
  .rates-body { flex-direction: column; }
}
@media (max-width: 640px) {
  .kpi-grid       { grid-template-columns: 1fr; }
  .page-title     { font-size: 20px; }
  .header-actions { gap: 6px; }
  .extra-stats    { grid-template-columns: 1fr; }
  .chart-tab      { padding: 6px 10px; font-size: 11px; }
}
</style>
