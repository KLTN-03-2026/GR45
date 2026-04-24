<script setup>
import { computed, ref } from 'vue'
import { Headset, Clock3, CircleAlert, Ticket, HandCoins, RefreshCcw, ShieldCheck } from 'lucide-vue-next'

const filterType = ref('all')
const filterPriority = ref('all')
const filterStatus = ref('all')
const onlyOverdue = ref(false)
const actionMessage = ref('')

const supportTickets = ref([
  {
    id: 'HT-2026-001',
    loai: 'hoan_ve',
    mucDo: 'cao',
    trangThai: 'moi_tao',
    khachHang: 'Nguyễn Văn Thuận',
    maVe: 'VE101229',
    tuyen: 'TP.HCM - Đà Lạt',
    nhaXe: 'Xe Khách Miền Nam',
    soTienHoan: 420000,
    yeuCau: 'Khách hủy trước giờ khởi hành 10 tiếng, đề nghị hoàn 80%',
    taoLuc: '2026-04-15 09:10',
    hanSla: '2026-04-15 09:40',
    xuLyVien: 'CSKH - Linh Trần',
    lichSu: [
      { time: '09:10', text: 'Khách tạo ticket hoàn vé từ app.' },
      { time: '09:13', text: 'Hệ thống tự động đánh mức ưu tiên cao.' },
    ],
  },
  {
    id: 'HT-2026-002',
    loai: 'thanh_toan',
    mucDo: 'trung_binh',
    trangThai: 'dang_xu_ly',
    khachHang: 'Lê Minh Tú',
    maVe: 'VE102887',
    tuyen: 'Hà Nội - Huế',
    nhaXe: 'Xe Khách Bắc Trung',
    soTienHoan: 0,
    yeuCau: 'Đã thanh toán nhưng vé chưa chuyển trạng thái thành công.',
    taoLuc: '2026-04-15 08:42',
    hanSla: '2026-04-15 10:42',
    xuLyVien: 'CSKH - Hoàng Nam',
    lichSu: [
      { time: '08:42', text: 'Khách gửi ảnh giao dịch ngân hàng.' },
      { time: '08:55', text: 'CSKH đang đối soát với hệ thống thanh toán.' },
    ],
  },
  {
    id: 'HT-2026-003',
    loai: 'khieu_nai',
    mucDo: 'khan_cap',
    trangThai: 'cho_xac_nhan',
    khachHang: 'Trần Quốc Đạt',
    maVe: 'VE103129',
    tuyen: 'Đà Nẵng - Quảng Ngãi',
    nhaXe: 'Xe Khách Smart Bus',
    soTienHoan: 300000,
    yeuCau: 'Khiếu nại xe trễ 70 phút, yêu cầu hoàn vé theo chính sách.',
    taoLuc: '2026-04-15 07:50',
    hanSla: '2026-04-15 07:55',
    xuLyVien: 'Điều hành - Mai Hương',
    lichSu: [
      { time: '07:50', text: 'Khách gọi hotline, tạo ticket khẩn cấp.' },
      { time: '08:05', text: 'Điều hành xác nhận xe trễ do sự cố kỹ thuật.' },
      { time: '08:30', text: 'Đề xuất hoàn 100%, chờ admin duyệt.' },
    ],
  },
])

const selectedTicketId = ref(supportTickets.value[0]?.id || '')
const selectedTicket = computed(
  () => supportTickets.value.find((ticket) => ticket.id === selectedTicketId.value) || null,
)

const getPriorityClass = (value) => {
  if (value === 'khan_cap') return 'priority-critical'
  if (value === 'cao') return 'priority-high'
  if (value === 'trung_binh') return 'priority-medium'
  return 'priority-low'
}

const getPriorityLabel = (value) => {
  if (value === 'khan_cap') return 'Khẩn cấp'
  if (value === 'cao') return 'Cao'
  if (value === 'trung_binh') return 'Trung bình'
  return 'Thấp'
}

const getStatusClass = (value) => {
  if (value === 'moi_tao') return 'status-new'
  if (value === 'dang_xu_ly') return 'status-processing'
  if (value === 'cho_xac_nhan') return 'status-waiting'
  if (value === 'da_giai_quyet') return 'status-done'
  if (value === 'tu_choi') return 'status-rejected'
  return ''
}

const getStatusLabel = (value) => {
  if (value === 'moi_tao') return 'Mới tạo'
  if (value === 'dang_xu_ly') return 'Đang xử lý'
  if (value === 'cho_xac_nhan') return 'Chờ khách xác nhận'
  if (value === 'da_giai_quyet') return 'Đã giải quyết'
  if (value === 'tu_choi') return 'Từ chối'
  return value
}

const getTypeLabel = (value) => {
  if (value === 'hoan_ve') return 'Hoàn vé'
  if (value === 'thanh_toan') return 'Thanh toán'
  if (value === 'khieu_nai') return 'Khiếu nại'
  if (value === 'doi_lich') return 'Đổi lịch'
  return 'Khác'
}

const formatMoney = (value) => {
  if (!value) return '0 đ'
  return `${Number(value).toLocaleString('vi-VN')} đ`
}

const isOverdue = (ticket) => new Date(ticket.hanSla).getTime() < Date.now() && ticket.trangThai !== 'da_giai_quyet'

const filteredTickets = computed(() =>
  supportTickets.value.filter((ticket) => {
    if (filterType.value !== 'all' && ticket.loai !== filterType.value) return false
    if (filterPriority.value !== 'all' && ticket.mucDo !== filterPriority.value) return false
    if (filterStatus.value !== 'all' && ticket.trangThai !== filterStatus.value) return false
    if (onlyOverdue.value && !isOverdue(ticket)) return false
    return true
  }),
)

const dashboardStats = computed(() => {
  const data = filteredTickets.value
  return {
    total: data.length,
    overdue: data.filter((ticket) => isOverdue(ticket)).length,
    refundPending: data.filter((ticket) => ticket.loai === 'hoan_ve' && ticket.trangThai !== 'da_giai_quyet').length,
    solved: data.filter((ticket) => ticket.trangThai === 'da_giai_quyet').length,
  }
})

const setSelectedTicket = (ticketId) => {
  selectedTicketId.value = ticketId
  actionMessage.value = ''
}

const updateTicketStatus = (nextStatus, note) => {
  if (!selectedTicket.value) return
  const idx = supportTickets.value.findIndex((ticket) => ticket.id === selectedTicket.value.id)
  if (idx === -1) return
  supportTickets.value[idx].trangThai = nextStatus
  supportTickets.value[idx].lichSu.unshift({
    time: new Date().toLocaleTimeString('vi-VN'),
    text: note,
  })
  actionMessage.value = `Đã cập nhật ticket ${supportTickets.value[idx].id}: ${getStatusLabel(nextStatus)}.`
}
</script>

<template>
  <section class="support-page">
    <header class="page-header glass-card">
      <div class="header-left">
        <div class="icon-wrap">
          <Headset class="head-icon" />
        </div>
        <div>
          <h1>Quản lý hỗ trợ (Support Tickets)</h1>
          <p>Trung tâm tiếp nhận, phân loại, xử lý ticket và phê duyệt hoàn vé theo SLA.</p>
        </div>
      </div>
      <button class="btn-refresh">
        <RefreshCcw class="btn-icon" />
        Làm mới dữ liệu
      </button>
    </header>

    <div class="stats-grid">
      <article class="stat-card stat-blue">
        <Ticket class="stat-icon" />
        <div>
          <p>Tổng ticket</p>
          <h3>{{ dashboardStats.total }}</h3>
        </div>
      </article>
      <article class="stat-card stat-red">
        <Clock3 class="stat-icon" />
        <div>
          <p>Quá SLA</p>
          <h3>{{ dashboardStats.overdue }}</h3>
        </div>
      </article>
      <article class="stat-card stat-orange">
        <HandCoins class="stat-icon" />
        <div>
          <p>Hoàn vé chờ duyệt</p>
          <h3>{{ dashboardStats.refundPending }}</h3>
        </div>
      </article>
      <article class="stat-card stat-green">
        <ShieldCheck class="stat-icon" />
        <div>
          <p>Đã giải quyết</p>
          <h3>{{ dashboardStats.solved }}</h3>
        </div>
      </article>
    </div>

    <div class="filter-bar glass-card">
      <select v-model="filterType">
        <option value="all">Loại ticket: Tất cả</option>
        <option value="hoan_ve">Hoàn vé</option>
        <option value="thanh_toan">Thanh toán</option>
        <option value="khieu_nai">Khiếu nại</option>
        <option value="doi_lich">Đổi lịch</option>
      </select>
      <select v-model="filterPriority">
        <option value="all">Mức độ: Tất cả</option>
        <option value="khan_cap">Khẩn cấp</option>
        <option value="cao">Cao</option>
        <option value="trung_binh">Trung bình</option>
        <option value="thap">Thấp</option>
      </select>
      <select v-model="filterStatus">
        <option value="all">Trạng thái: Tất cả</option>
        <option value="moi_tao">Mới tạo</option>
        <option value="dang_xu_ly">Đang xử lý</option>
        <option value="cho_xac_nhan">Chờ khách xác nhận</option>
        <option value="da_giai_quyet">Đã giải quyết</option>
        <option value="tu_choi">Từ chối</option>
      </select>
      <label class="overdue-checkbox">
        <input v-model="onlyOverdue" type="checkbox" />
        Chỉ hiển thị ticket quá SLA
      </label>
    </div>

    <div class="main-grid">
      <article class="glass-card ticket-list-panel">
        <h2>Danh sách tickets</h2>
        <div class="ticket-list">
          <button
            v-for="ticket in filteredTickets"
            :key="ticket.id"
            class="ticket-item"
            :class="{ active: selectedTicketId === ticket.id }"
            @click="setSelectedTicket(ticket.id)"
          >
            <div class="ticket-top">
              <strong>{{ ticket.id }}</strong>
              <span class="pill" :class="getPriorityClass(ticket.mucDo)">{{ getPriorityLabel(ticket.mucDo) }}</span>
            </div>
            <p class="ticket-sub">{{ getTypeLabel(ticket.loai) }} • {{ ticket.khachHang }} • {{ ticket.maVe }}</p>
            <p class="ticket-sub">SLA: {{ ticket.hanSla }} • {{ ticket.xuLyVien }}</p>
            <div class="ticket-status-row">
              <span class="pill" :class="getStatusClass(ticket.trangThai)">{{ getStatusLabel(ticket.trangThai) }}</span>
              <span v-if="isOverdue(ticket)" class="overdue-chip">
                <CircleAlert class="chip-icon" />
                Quá SLA
              </span>
            </div>
          </button>
          <p v-if="!filteredTickets.length" class="empty-text">Không có ticket phù hợp với bộ lọc.</p>
        </div>
      </article>

      <article class="glass-card detail-panel" v-if="selectedTicket">
        <h2>Chi tiết ticket</h2>
        <div class="detail-grid">
          <p><span>Mã ticket:</span> <strong>{{ selectedTicket.id }}</strong></p>
          <p><span>Loại:</span> <strong>{{ getTypeLabel(selectedTicket.loai) }}</strong></p>
          <p><span>Khách hàng:</span> <strong>{{ selectedTicket.khachHang }}</strong></p>
          <p><span>Tuyến:</span> <strong>{{ selectedTicket.tuyen }}</strong></p>
          <p><span>Nhà xe:</span> <strong>{{ selectedTicket.nhaXe }}</strong></p>
          <p><span>Mã vé:</span> <strong>{{ selectedTicket.maVe }}</strong></p>
          <p><span>Số tiền hoàn đề xuất:</span> <strong class="text-money">{{ formatMoney(selectedTicket.soTienHoan) }}</strong></p>
          <p><span>SLA:</span> <strong>{{ selectedTicket.hanSla }}</strong></p>
        </div>

        <div class="request-box">
          <h3>Nội dung yêu cầu</h3>
          <p>{{ selectedTicket.yeuCau }}</p>
        </div>

        <div class="action-row">
          <button
            class="btn-action btn-accept"
            @click="updateTicketStatus('da_giai_quyet', 'Admin duyệt yêu cầu và chuyển ticket sang Đã giải quyết.')"
          >
            Duyệt xử lý
          </button>
          <button
            class="btn-action btn-pending"
            @click="updateTicketStatus('cho_xac_nhan', 'Admin yêu cầu khách hàng xác nhận thông tin trước khi chốt.')"
          >
            Chờ xác nhận
          </button>
          <button
            class="btn-action btn-reject"
            @click="updateTicketStatus('tu_choi', 'Admin từ chối yêu cầu do không đủ điều kiện theo chính sách.')"
          >
            Từ chối
          </button>
        </div>

        <p v-if="actionMessage" class="action-message">{{ actionMessage }}</p>

        <div class="timeline-box">
          <h3>Timeline xử lý</h3>
          <ul>
            <li v-for="(item, index) in selectedTicket.lichSu" :key="`${selectedTicket.id}-${index}`">
              <strong>{{ item.time }}</strong>
              <span>{{ item.text }}</span>
            </li>
          </ul>
        </div>
      </article>
    </div>

    <article class="glass-card policy-card">
      <h2>Đề xuất chính sách hoàn vé áp dụng nhanh</h2>
      <div class="policy-grid">
        <div>
          <h4>Theo thời điểm hủy trước giờ chạy</h4>
          <p>> 24h: hoàn 90-100%</p>
          <p>6h - 24h: hoàn 70-80%</p>
          <p>< 6h: hoàn 30-50%</p>
        </div>
        <div>
          <h4>Hoàn 100% ngoại lệ</h4>
          <p>Nhà xe hủy chuyến, trễ quá ngưỡng cam kết, hoặc đổi xe không tương đương.</p>
        </div>
        <div>
          <h4>SLA vận hành</h4>
          <p>Khẩn cấp: &lt; 5 phút • Cao: &lt; 30 phút • Trung bình: &lt; 2 giờ • Thấp: &lt; 24 giờ.</p>
        </div>
      </div>
    </article>
  </section>
</template>

<style scoped>
.support-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.glass-card {
  background: rgba(255, 255, 255, 0.86);
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
}

.page-header {
  padding: 16px;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.icon-wrap {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  background: linear-gradient(135deg, #4f46e5, #3b82f6);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.head-icon {
  width: 22px;
  height: 22px;
}

.page-header h1 {
  margin: 0;
  font-size: 24px;
}

.page-header p {
  margin: 4px 0 0;
  color: #64748b;
  font-size: 14px;
}

.btn-refresh {
  border: 1px solid #cbd5e1;
  background: #fff;
  border-radius: 10px;
  padding: 10px 12px;
  font-weight: 700;
  color: #334155;
  display: inline-flex;
  gap: 6px;
  align-items: center;
  cursor: pointer;
}

.btn-icon {
  width: 15px;
  height: 15px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.stat-card {
  border-radius: 12px;
  padding: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
  color: #fff;
}

.stat-card p {
  margin: 0;
  font-size: 13px;
}

.stat-card h3 {
  margin: 4px 0 0;
  font-size: 24px;
}

.stat-icon {
  width: 19px;
  height: 19px;
}

.stat-blue {
  background: linear-gradient(135deg, #1d4ed8, #2563eb);
}

.stat-red {
  background: linear-gradient(135deg, #b91c1c, #ef4444);
}

.stat-orange {
  background: linear-gradient(135deg, #c2410c, #f97316);
}

.stat-green {
  background: linear-gradient(135deg, #166534, #22c55e);
}

.filter-bar {
  padding: 12px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}

.filter-bar select {
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  padding: 10px;
  font-size: 14px;
}

.overdue-checkbox {
  font-size: 14px;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  padding: 10px;
  display: flex;
  gap: 8px;
  align-items: center;
}

.main-grid {
  display: grid;
  grid-template-columns: 1fr 1.1fr;
  gap: 12px;
}

.ticket-list-panel,
.detail-panel,
.policy-card {
  padding: 14px;
}

h2 {
  margin: 0 0 12px;
  font-size: 16px;
}

.ticket-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 520px;
  overflow-y: auto;
}

.ticket-item {
  width: 100%;
  text-align: left;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px;
  cursor: pointer;
}

.ticket-item.active {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.ticket-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.ticket-sub {
  margin: 5px 0 0;
  color: #64748b;
  font-size: 12px;
}

.ticket-status-row {
  display: flex;
  gap: 6px;
  margin-top: 8px;
  align-items: center;
}

.pill {
  border-radius: 999px;
  padding: 3px 8px;
  font-size: 11px;
  font-weight: 700;
}

.priority-critical {
  color: #991b1b;
  background: #fee2e2;
}

.priority-high {
  color: #9a3412;
  background: #ffedd5;
}

.priority-medium {
  color: #92400e;
  background: #fef3c7;
}

.priority-low {
  color: #1e3a8a;
  background: #dbeafe;
}

.status-new {
  color: #1e3a8a;
  background: #dbeafe;
}

.status-processing {
  color: #334155;
  background: #e2e8f0;
}

.status-waiting {
  color: #92400e;
  background: #fef3c7;
}

.status-done {
  color: #166534;
  background: #dcfce7;
}

.status-rejected {
  color: #991b1b;
  background: #fee2e2;
}

.overdue-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #b91c1c;
  font-size: 11px;
  font-weight: 700;
}

.chip-icon {
  width: 12px;
  height: 12px;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.detail-grid p {
  margin: 0;
  font-size: 13px;
}

.detail-grid span {
  color: #64748b;
}

.text-money {
  color: #16a34a;
}

.request-box,
.timeline-box {
  margin-top: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px;
}

h3 {
  margin: 0 0 6px;
  font-size: 14px;
}

.request-box p {
  margin: 0;
  color: #334155;
  font-size: 13px;
}

.action-row {
  display: flex;
  gap: 8px;
  margin-top: 12px;
  flex-wrap: wrap;
}

.btn-action {
  border: none;
  border-radius: 9px;
  padding: 8px 10px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.btn-accept {
  background: #16a34a;
  color: #fff;
}

.btn-pending {
  background: #f59e0b;
  color: #fff;
}

.btn-reject {
  background: #dc2626;
  color: #fff;
}

.action-message {
  margin-top: 10px;
  font-size: 13px;
  color: #0369a1;
}

.timeline-box ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.timeline-box li {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  font-size: 13px;
}

.timeline-box strong {
  min-width: 68px;
  color: #334155;
}

.timeline-box span {
  color: #475569;
}

.policy-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.policy-grid h4 {
  margin: 0 0 6px;
  font-size: 14px;
}

.policy-grid p {
  margin: 0 0 5px;
  font-size: 13px;
  color: #475569;
}

.empty-text {
  font-size: 13px;
  color: #64748b;
}

@media (max-width: 1280px) {
  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .main-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 860px) {
  .filter-bar,
  .detail-grid,
  .policy-grid {
    grid-template-columns: 1fr;
  }
}
</style>
