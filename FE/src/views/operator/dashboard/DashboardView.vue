<script setup>
// Dashboard tổng quan của Nhà Xe
import { ref, computed } from 'vue'
import {
  TrendingUp, Ticket, BusFront, Users, AlertTriangle,
  CheckCircle, Clock, ArrowUpRight, ArrowDownRight,
  MoreHorizontal, Activity, DollarSign, Map
} from 'lucide-vue-next'

// --- Dữ liệu thống kê nhanh ---
const statCards = ref([
  {
    id: 'revenue',
    label: 'Doanh thu hôm nay',
    value: '42.800.000',
    unit: 'đ',
    change: '+12.4%',
    positive: true,
    icon: DollarSign,
    color: 'green',
    bg: 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)'
  },
  {
    id: 'tickets',
    label: 'Vé đã bán hôm nay',
    value: '318',
    unit: 'vé',
    change: '+8.2%',
    positive: true,
    icon: Ticket,
    color: 'blue',
    bg: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)'
  },
  {
    id: 'trips',
    label: 'Chuyến xe đang chạy',
    value: '24',
    unit: 'chuyến',
    change: '-2 so với hôm qua',
    positive: false,
    icon: BusFront,
    color: 'indigo',
    bg: 'linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)'
  },
  {
    id: 'alerts',
    label: 'Cảnh báo chưa xử lý',
    value: '3',
    unit: 'cảnh báo',
    change: 'Khẩn cấp: 1',
    positive: false,
    icon: AlertTriangle,
    color: 'red',
    bg: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)'
  },
])

// --- Chuyến xe đang hoạt động ---
const activeTrips = ref([
  { id: 'CX-001', route: 'TP.HCM → Đà Lạt', driver: 'Nguyễn Văn A', plate: '51B-12345', departure: '06:00', status: 'on_route', seats: '42/45', progress: 65 },
  { id: 'CX-002', route: 'TP.HCM → Vũng Tàu', driver: 'Trần Văn B', plate: '51B-23456', departure: '07:30', status: 'on_route', seats: '38/45', progress: 40 },
  { id: 'CX-003', route: 'Hà Nội → Huế', driver: 'Lê Văn C', plate: '30A-45678', departure: '08:00', status: 'warning', seats: '45/45', progress: 20 },
  { id: 'CX-004', route: 'TP.HCM → Cần Thơ', driver: 'Phạm Thị D', plate: '51B-56789', departure: '09:00', status: 'on_route', seats: '30/45', progress: 10 },
])

// --- Cảnh báo gần đây ---
const recentAlerts = ref([
  { id: 1, type: 'drowsy', driver: 'Nguyễn Văn C', trip: 'CX-003', msg: 'Phát hiện buồn ngủ', time: '5 phút trước', level: 'critical' },
  { id: 2, type: 'speed', driver: 'Trần Văn E', trip: 'CX-007', msg: 'Vượt quá tốc độ 85km/h', time: '12 phút trước', level: 'warning' },
  { id: 3, type: 'delay', driver: '', trip: 'CX-010', msg: 'Chuyến xe trễ 15 phút', time: '25 phút trước', level: 'info' },
])

// --- Vé mới nhất ---
const recentTickets = ref([
  { id: 'V001234', customer: 'Nguyễn Thị Mai', route: 'TP.HCM → Đà Lạt', time: '06:00', seats: 'A01, A02', amount: '500.000đ', status: 'confirmed' },
  { id: 'V001235', customer: 'Trần Văn Hùng', route: 'TP.HCM → Vũng Tàu', time: '07:30', seats: 'B05', amount: '150.000đ', status: 'confirmed' },
  { id: 'V001236', customer: 'Lê Thị Lan', route: 'Hà Nội → Huế', time: '08:00', seats: 'C01', amount: '350.000đ', status: 'pending' },
  { id: 'V001237', customer: 'Phạm Văn Đức', route: 'TP.HCM → Cần Thơ', time: '09:00', seats: 'A10', amount: '120.000đ', status: 'cancelled' },
])

const statusLabel = (s) => {
  if (s === 'on_route') return { text: 'Đang chạy', cls: 'status-running' }
  if (s === 'warning') return { text: '⚠ Cảnh báo', cls: 'status-warning' }
  if (s === 'done') return { text: 'Hoàn thành', cls: 'status-done' }
  return { text: s, cls: '' }
}

const ticketStatusLabel = (s) => {
  if (s === 'confirmed') return { text: 'Đã xác nhận', cls: 'badge-green' }
  if (s === 'pending') return { text: 'Chờ xử lý', cls: 'badge-yellow' }
  if (s === 'cancelled') return { text: 'Đã huỷ', cls: 'badge-red' }
  return { text: s, cls: '' }
}

const alertLevel = (l) => {
  if (l === 'critical') return { text: 'Nguy hiểm', cls: 'alert-critical' }
  if (l === 'warning') return { text: 'Cảnh báo', cls: 'alert-warning' }
  return { text: 'Thông tin', cls: 'alert-info' }
}
</script>

<template>
  <div class="dashboard-page">

    <!-- Tiêu đề trang -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Tổng quan hệ thống</h1>
        <p class="page-sub">Theo dõi hoạt động nhà xe trong thời gian thực</p>
      </div>
      <div class="header-actions">
        <span class="date-badge">{{ new Date().toLocaleDateString('vi-VN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
      <div
        v-for="card in statCards"
        :key="card.id"
        class="stat-card"
        :style="{ '--card-gradient': card.bg }"
      >
        <div class="stat-icon" :style="{ background: card.bg }">
          <component :is="card.icon" class="stat-icon-svg" />
        </div>
        <div class="stat-info">
          <p class="stat-label">{{ card.label }}</p>
          <h2 class="stat-value">{{ card.value }} <span class="stat-unit">{{ card.unit }}</span></h2>
          <p class="stat-change" :class="card.positive ? 'positive' : 'negative'">
            <component :is="card.positive ? ArrowUpRight : ArrowDownRight" class="change-icon" />
            {{ card.change }}
          </p>
        </div>
      </div>
    </div>

    <!-- Grid chính: Chuyến xe + Cảnh báo -->
    <div class="main-grid">

      <!-- Chuyến xe đang hoạt động -->
      <div class="panel">
        <div class="panel-header">
          <h3 class="panel-title">
            <Activity class="panel-icon" />
            Chuyến xe đang hoạt động
          </h3>
          <button class="btn-link">Xem tất cả</button>
        </div>
        <div class="trips-list">
          <div v-for="trip in activeTrips" :key="trip.id" class="trip-card">
            <div class="trip-row">
              <div class="trip-left">
                <span class="trip-id">{{ trip.id }}</span>
                <p class="trip-route">{{ trip.route }}</p>
                <p class="trip-meta">{{ trip.driver }} • {{ trip.plate }} • KH: {{ trip.departure }}</p>
              </div>
              <div class="trip-right">
                <span class="trip-seats">{{ trip.seats }}</span>
                <span :class="statusLabel(trip.status).cls" class="status-badge">
                  {{ statusLabel(trip.status).text }}
                </span>
              </div>
            </div>
            <!-- Progress bar -->
            <div class="progress-bar-wrap">
              <div class="progress-bar-fill" :style="{ width: trip.progress + '%' }"></div>
            </div>
            <div class="progress-labels">
              <span>Xuất phát</span>
              <span>{{ trip.progress }}% hành trình</span>
              <span>Điểm đến</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Cảnh báo AI -->
      <div class="panel panel-alerts">
        <div class="panel-header">
          <h3 class="panel-title">
            <AlertTriangle class="panel-icon text-red" />
            Cảnh báo gần đây
          </h3>
          <button class="btn-link" style="color: #ef4444">Xem tất cả</button>
        </div>
        <div class="alerts-list">
          <div v-for="a in recentAlerts" :key="a.id" class="alert-item" :class="alertLevel(a.level).cls">
            <div class="alert-dot"></div>
            <div class="alert-content">
              <p class="alert-msg">{{ a.msg }}</p>
              <p class="alert-meta">{{ a.trip }} {{ a.driver ? `• ${a.driver}` : '' }}</p>
              <span class="alert-time">{{ a.time }}</span>
            </div>
            <span class="alert-level-badge">{{ alertLevel(a.level).text }}</span>
          </div>
        </div>

        <!-- Tóm tắt cảnh báo -->
        <div class="alert-summary">
          <div class="summary-item critical">
            <span class="sum-count">1</span>
            <span class="sum-label">Nguy hiểm</span>
          </div>
          <div class="summary-item warning">
            <span class="sum-count">1</span>
            <span class="sum-label">Cảnh báo</span>
          </div>
          <div class="summary-item info-s">
            <span class="sum-count">1</span>
            <span class="sum-label">Thông tin</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Vé gần đây -->
    <div class="panel" style="margin-top: 24px">
      <div class="panel-header">
        <h3 class="panel-title">
          <Ticket class="panel-icon" />
          Vé đặt gần đây
        </h3>
        <button class="btn-link">Xem tất cả vé</button>
      </div>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>Mã vé</th>
              <th>Khách hàng</th>
              <th>Tuyến đường</th>
              <th>Giờ khởi hành</th>
              <th>Ghế</th>
              <th>Tổng tiền</th>
              <th>Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in recentTickets" :key="t.id">
              <td><span class="ticket-id">{{ t.id }}</span></td>
              <td>{{ t.customer }}</td>
              <td>{{ t.route }}</td>
              <td>{{ t.time }}</td>
              <td>{{ t.seats }}</td>
              <td class="font-bold text-green">{{ t.amount }}</td>
              <td>
                <span :class="ticketStatusLabel(t.status).cls" class="badge">
                  {{ ticketStatusLabel(t.status).text }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<style scoped>
.dashboard-page {
  padding: 8px 0;
  font-family: 'Inter', sans-serif;
}

/* == Tiêu đề trang == */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 28px;
  flex-wrap: wrap;
  gap: 12px;
}
.page-title {
  font-size: 26px;
  font-weight: 800;
  color: #0d4f35;
  margin: 0 0 4px 0;
}
.page-sub {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}
.date-badge {
  background: linear-gradient(135deg, #f0fdf4, #dcfce7);
  border: 1px solid #bbf7d0;
  color: #16a34a;
  font-size: 13px;
  font-weight: 600;
  padding: 8px 16px;
  border-radius: 20px;
}

/* == Stat Cards == */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  margin-bottom: 24px;
}
.stat-card {
  background: white;
  border-radius: 18px;
  padding: 24px;
  box-shadow: 0 2px 16px rgba(0,0,0,0.04);
  border: 1px solid #f0fdf4;
  display: flex;
  align-items: flex-start;
  gap: 18px;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  cursor: default;
}
.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}
.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 14px rgba(0,0,0,0.15);
}
.stat-icon-svg { width: 28px; height: 28px; color: white; }
.stat-info { flex: 1; }
.stat-label { font-size: 12px; color: #64748b; margin: 0 0 6px 0; font-weight: 500; }
.stat-value { font-size: 24px; font-weight: 800; color: #0d4f35; margin: 0 0 6px 0; line-height: 1; }
.stat-unit { font-size: 14px; font-weight: 500; color: #64748b; }
.stat-change {
  font-size: 12px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 2px;
  margin: 0;
}
.stat-change.positive { color: #16a34a; }
.stat-change.negative { color: #ef4444; }
.change-icon { width: 14px; height: 14px; }

/* == Panel == */
.panel {
  background: white;
  border-radius: 18px;
  box-shadow: 0 2px 16px rgba(0,0,0,0.04);
  border: 1px solid #f0fdf4;
  overflow: hidden;
}
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #f8fafc;
}
.panel-title {
  font-size: 16px;
  font-weight: 700;
  color: #0d4f35;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.panel-icon {
  width: 20px;
  height: 20px;
  color: #16a34a;
}
.text-red { color: #ef4444 !important; }
.btn-link {
  background: none;
  border: none;
  color: #16a34a;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.btn-link:hover { text-decoration: underline; }

/* == Main Grid == */
.main-grid {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 24px;
}

/* == Trip Cards == */
.trips-list { padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.trip-card {
  background: #f8fafc;
  border-radius: 14px;
  padding: 16px;
  border: 1px solid #f1f5f9;
  transition: border-color 0.2s;
}
.trip-card:hover { border-color: #bbf7d0; }
.trip-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.trip-left { flex: 1; }
.trip-id { font-size: 11px; font-weight: 700; color: #16a34a; background: #f0fdf4; padding: 2px 8px; border-radius: 6px; }
.trip-route { font-size: 14px; font-weight: 700; color: #0d4f35; margin: 6px 0 2px 0; }
.trip-meta { font-size: 12px; color: #64748b; margin: 0; }
.trip-right { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
.trip-seats { font-size: 12px; font-weight: 600; color: #3b82f6; }
.status-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 8px;
}
.status-running { background: #dcfce7; color: #16a34a; }
.status-warning { background: #fef9c3; color: #ca8a04; }
.status-done { background: #f1f5f9; color: #64748b; }

.progress-bar-wrap {
  height: 6px;
  background: #f1f5f9;
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 4px;
}
.progress-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, #22c55e, #16a34a);
  border-radius: 10px;
  transition: width 0.5s ease;
}
.progress-labels {
  display: flex;
  justify-content: space-between;
  font-size: 10px;
  color: #94a3b8;
}

/* == Alerts == */
.alerts-list { padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.alert-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 12px;
  border-left: 3px solid transparent;
}
.alert-critical { background: #fef2f2; border-left-color: #ef4444; }
.alert-warning { background: #fffbeb; border-left-color: #f59e0b; }
.alert-info { background: #eff6ff; border-left-color: #3b82f6; }

.alert-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  margin-top: 6px;
  flex-shrink: 0;
  background: currentColor;
}
.alert-critical .alert-dot { background: #ef4444; }
.alert-warning .alert-dot { background: #f59e0b; }
.alert-info .alert-dot { background: #3b82f6; }

.alert-content { flex: 1; }
.alert-msg { font-size: 13px; font-weight: 700; color: #0f172a; margin: 0 0 2px 0; }
.alert-meta { font-size: 12px; color: #64748b; margin: 0 0 2px 0; }
.alert-time { font-size: 11px; color: #94a3b8; }
.alert-level-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  white-space: nowrap;
}
.alert-critical .alert-level-badge { background: #fecaca; color: #b91c1c; }
.alert-warning .alert-level-badge { background: #fde68a; color: #92400e; }
.alert-info .alert-level-badge { background: #bfdbfe; color: #1d4ed8; }

.alert-summary {
  display: flex;
  border-top: 1px solid #f1f5f9;
}
.summary-item {
  flex: 1;
  padding: 12px;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.summary-item + .summary-item { border-left: 1px solid #f1f5f9; }
.sum-count { font-size: 22px; font-weight: 800; line-height: 1; }
.sum-label { font-size: 11px; color: #64748b; font-weight: 500; }
.critical .sum-count { color: #ef4444; }
.warning .sum-count { color: #f59e0b; }
.info-s .sum-count { color: #3b82f6; }

/* == Table == */
.table-wrapper { overflow-x: auto; }
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
.data-table th {
  padding: 12px 20px;
  text-align: left;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f8fafc;
  border-bottom: 1px solid #f1f5f9;
}
.data-table td {
  padding: 14px 20px;
  border-bottom: 1px solid #f8fafc;
  color: #374151;
}
.data-table tr:hover td { background: #f8fafc; }
.ticket-id { font-weight: 700; color: #16a34a; }
.font-bold { font-weight: 700; }
.text-green { color: #16a34a; }

.badge {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 10px;
}
.badge-green { background: #dcfce7; color: #16a34a; }
.badge-yellow { background: #fef9c3; color: #ca8a04; }
.badge-red { background: #fef2f2; color: #dc2626; }

/* == Responsive == */
@media (max-width: 1280px) {
  .stat-grid { grid-template-columns: repeat(2, 1fr); }
  .main-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .stat-grid { grid-template-columns: 1fr; }
  .page-title { font-size: 20px; }
}
</style>
