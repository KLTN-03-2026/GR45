<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import {
  Siren,
  ShieldAlert,
  BusFront,
  MapPin,
  BellRing,
  TriangleAlert,
  Activity,
  Clock3,
  Radio,
  ArrowRight,
} from 'lucide-vue-next'
import { useAdminStore } from '@/stores/adminStore.js'

const adminStore = useAdminStore()
const now = ref(new Date())
const feedLimit = 12
const trackLimit = 10
const alarmLimit = 8

const systemState = ref('Đang giám sát')
const aiBlinkMessage = ref('')
const aiBlinkVisible = ref(false)
const aiBlinkTimeout = ref(null)
const hasRealtimeConnection = ref(false)

const trackingEvents = ref([
  {
    id: 1,
    bienSo: '51B-01234',
    tenTaiXe: 'Nguyễn Thanh Bình',
    loaiSuCo: 'Dừng khẩn trên cao tốc',
    capDo: 'critical',
    viDo: 10.90911,
    kinhDo: 106.74421,
    khuVuc: 'QL1A - Km 182',
    tocDo: 0,
    thoiGian: new Date(Date.now() - 2 * 60 * 1000).toISOString(),
  },
  {
    id: 2,
    bienSo: '30F-77588',
    tenTaiXe: 'Trần Minh Châu',
    loaiSuCo: 'Mất tín hiệu camera trong cabin',
    capDo: 'warning',
    viDo: 21.03548,
    kinhDo: 105.86141,
    khuVuc: 'Vành đai 3 - Hà Nội',
    tocDo: 28,
    thoiGian: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
  },
])

const violationFeed = ref([
  {
    id: 101,
    loai: 'ngu_gat',
    bienSo: '43A-22391',
    tenTaiXe: 'Lê Văn Hưng',
    moTa: 'Phát hiện mắt nhắm liên tục > 2 giây',
    capDo: 'critical',
    viDo: 16.05166,
    kinhDo: 108.21986,
    thoiGian: new Date(Date.now() - 90 * 1000).toISOString(),
  },
  {
    id: 102,
    loai: 'hanh_vi',
    bienSo: '29H-65211',
    tenTaiXe: 'Phạm Quốc Anh',
    moTa: 'Không thắt dây an toàn khi xe đang chạy',
    capDo: 'warning',
    viDo: 20.99567,
    kinhDo: 105.79811,
    thoiGian: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
  },
])

const emergencyAlarms = ref([])
const vehicleRegistry = ref(new Map())
let echoInstance = null
let clockTicker = null
let mockTicker = null
let localSeed = 200

const dashboardDateText = computed(() =>
  now.value.toLocaleString('vi-VN', {
    weekday: 'long',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }),
)

const totals = computed(() => {
  const trackCount = trackingEvents.value.length
  const criticalEvents = violationFeed.value.filter((item) => item.capDo === 'critical').length
  const warningEvents = violationFeed.value.filter((item) => item.capDo === 'warning').length
  const activeVehicles = vehicleRegistry.value.size || new Set(trackingEvents.value.map((item) => item.bienSo)).size
  return {
    trackCount,
    criticalEvents,
    warningEvents,
    activeVehicles,
  }
})

const orderedTracking = computed(() =>
  [...trackingEvents.value]
    .sort((a, b) => new Date(b.thoiGian).getTime() - new Date(a.thoiGian).getTime())
    .slice(0, trackLimit),
)

const orderedViolations = computed(() =>
  [...violationFeed.value]
    .sort((a, b) => new Date(b.thoiGian).getTime() - new Date(a.thoiGian).getTime())
    .slice(0, feedLimit),
)

const orderedAlarms = computed(() =>
  [...emergencyAlarms.value]
    .sort((a, b) => new Date(b.thoiGian).getTime() - new Date(a.thoiGian).getTime())
    .slice(0, alarmLimit),
)

const liveConnectionStatus = computed(() =>
  hasRealtimeConnection.value ? 'Kết nối WebSocket realtime' : 'Chế độ mô phỏng cục bộ',
)

const normalizeEventPayload = (payload = {}) => {
  const bienSo = payload.bien_so || payload.bienSo || payload.license_plate || 'Chưa rõ biển số'
  const tenTaiXe = payload.ten_tai_xe || payload.tenTaiXe || payload.driver_name || 'Chưa rõ tài xế'
  const loaiRaw = payload.loai || payload.type || payload.violation_type || 'hanh_vi'
  const loai = String(loaiRaw).toLowerCase()
  const moTa = payload.mo_ta || payload.message || payload.description || 'Nhận tín hiệu vi phạm từ AI'
  const viDo = Number(payload.vi_do ?? payload.lat ?? payload.latitude ?? 0)
  const kinhDo = Number(payload.kinh_do ?? payload.lng ?? payload.longitude ?? 0)
  const khuVuc = payload.khu_vuc || payload.area || payload.zone || 'Chưa xác định khu vực'
  const tocDo = Number(payload.toc_do ?? payload.speed ?? 0)
  const capDo = payload.cap_do || payload.level || (loai.includes('ngu_gat') ? 'critical' : 'warning')
  const thoiGian = payload.thoi_gian || payload.time || new Date().toISOString()
  return {
    id: payload.id || Date.now() + Math.floor(Math.random() * 1000),
    bienSo,
    tenTaiXe,
    loai,
    moTa,
    viDo,
    kinhDo,
    khuVuc,
    tocDo,
    capDo,
    thoiGian,
  }
}

const formatClock = (isoTime) => {
  if (!isoTime) return '--:--:--'
  return new Date(isoTime).toLocaleTimeString('vi-VN')
}

const formatLocation = (lat, lng) => {
  if (!lat && !lng) return 'GPS chưa có'
  return `${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}`
}

const getBadgeClass = (level) => {
  if (level === 'critical') return 'badge-critical'
  if (level === 'warning') return 'badge-warning'
  return 'badge-info'
}

const getLevelText = (level) => {
  if (level === 'critical') return 'Khẩn cấp'
  if (level === 'warning') return 'Cảnh báo'
  return 'Thông tin'
}

const triggerDrowsyBlink = (plate) => {
  aiBlinkMessage.value = `Xe ${plate} - Tài xế có dấu hiệu ngủ gật`
  aiBlinkVisible.value = true
  clearTimeout(aiBlinkTimeout.value)
  aiBlinkTimeout.value = setTimeout(() => {
    aiBlinkVisible.value = false
  }, 10000)
}

const pushTrackingEvent = (eventData) => {
  trackingEvents.value.unshift({
    id: eventData.id,
    bienSo: eventData.bienSo,
    tenTaiXe: eventData.tenTaiXe,
    loaiSuCo: eventData.moTa,
    capDo: eventData.capDo,
    viDo: eventData.viDo,
    kinhDo: eventData.kinhDo,
    khuVuc: eventData.khuVuc,
    tocDo: eventData.tocDo,
    thoiGian: eventData.thoiGian,
  })
  trackingEvents.value = trackingEvents.value.slice(0, trackLimit + 4)
}

const pushViolationEvent = (eventData) => {
  violationFeed.value.unshift({
    id: eventData.id,
    loai: eventData.loai,
    bienSo: eventData.bienSo,
    tenTaiXe: eventData.tenTaiXe,
    moTa: eventData.moTa,
    capDo: eventData.capDo,
    viDo: eventData.viDo,
    kinhDo: eventData.kinhDo,
    thoiGian: eventData.thoiGian,
  })
  violationFeed.value = violationFeed.value.slice(0, feedLimit + 6)
}

const pushAlarm = (eventData) => {
  emergencyAlarms.value.unshift({
    id: `${eventData.id}-alarm`,
    tieuDe: `Tín hiệu ${getLevelText(eventData.capDo).toLowerCase()} từ AI`,
    noiDung: `${eventData.bienSo} • ${eventData.tenTaiXe} • ${eventData.moTa}`,
    thoiGian: eventData.thoiGian,
    capDo: eventData.capDo,
  })
  emergencyAlarms.value = emergencyAlarms.value.slice(0, alarmLimit + 4)
}

const updateVehicleRegistry = (eventData) => {
  const nextMap = new Map(vehicleRegistry.value)
  nextMap.set(eventData.bienSo, {
    bienSo: eventData.bienSo,
    tenTaiXe: eventData.tenTaiXe,
    viDo: eventData.viDo,
    kinhDo: eventData.kinhDo,
    tocDo: eventData.tocDo,
    thoiGian: eventData.thoiGian,
    capDo: eventData.capDo,
  })
  vehicleRegistry.value = nextMap
}

const receiveRealtimeEvent = (payload) => {
  const parsed = normalizeEventPayload(payload)
  updateVehicleRegistry(parsed)
  pushTrackingEvent(parsed)
  pushViolationEvent(parsed)
  pushAlarm(parsed)
  if (parsed.loai.includes('ngu_gat') || parsed.moTa.toLowerCase().includes('ngủ gật')) {
    triggerDrowsyBlink(parsed.bienSo)
  }
}

const subscribeEchoChannel = (channel, eventNames = []) => {
  if (!channel || !Array.isArray(eventNames) || !eventNames.length) return
  eventNames.forEach((eventName) => {
    channel.listen(eventName, (eventPayload) => {
      receiveRealtimeEvent(eventPayload)
    })
  })
}

const initWebSocket = () => {
  if (!adminStore.token) return
  try {
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY
    const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER
    if (!pusherKey || !pusherCluster) return

    window.Pusher = Pusher
    let apiUrl = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/'
    if (!apiUrl.endsWith('/')) apiUrl += '/'
    echoInstance = new Echo({
      broadcaster: 'pusher',
      key: pusherKey,
      cluster: pusherCluster,
      forceTLS: true,
      authEndpoint: `${apiUrl}v1/admin/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${adminStore.token}`,
          Accept: 'application/json',
        },
      },
    })

    const adminId = adminStore.user?.id || adminStore.user?.ma_admin || 'global'
    const privateChannel = echoInstance.private(`admin.${adminId}`)
    const systemChannel = echoInstance.channel('he-thong.giam-sat')

    subscribeEchoChannel(privateChannel, ['.bao-dong.vi-pham', '.ai.canh-bao', '.tracking.khan-cap'])
    subscribeEchoChannel(systemChannel, ['.bao-dong.vi-pham', '.ai.canh-bao', '.tracking.khan-cap'])
    hasRealtimeConnection.value = true
  } catch (error) {
    console.warn('Không khởi tạo được WebSocket admin:', error?.message || error)
    echoInstance = null
    hasRealtimeConnection.value = false
  }
}

const startMockRealtime = () => {
  const mockPlates = ['51B-99123', '77B-12888', '29H-77777', '43A-55661']
  const mockDrivers = ['Đặng Minh Quân', 'Ngô Thị Hạnh', 'Trịnh Văn Sơn', 'Lý Minh Tú']
  const mockAreas = ['Cao tốc Trung Lương', 'QL14 - Đắk Lắk', 'Nút giao Cầu Giẽ', 'Đèo Hải Vân']
  const mockViolations = ['ngu_gat', 'hanh_vi', 'hanh_vi', 'ngu_gat']
  const mockMessages = [
    'Tài xế có dấu hiệu ngủ gật liên tục',
    'Sử dụng điện thoại khi điều khiển xe',
    'Không giữ khoảng cách an toàn',
    'Phát hiện ngủ gật qua camera cabin',
  ]

  mockTicker = setInterval(() => {
    localSeed += 1
    const index = Math.floor(Math.random() * mockPlates.length)
    const payload = {
      id: localSeed,
      bien_so: mockPlates[index],
      ten_tai_xe: mockDrivers[index],
      loai: mockViolations[index],
      message: mockMessages[index],
      level: mockViolations[index] === 'ngu_gat' ? 'critical' : 'warning',
      vi_do: 10 + Math.random() * 12,
      kinh_do: 105 + Math.random() * 5,
      khu_vuc: mockAreas[index],
      toc_do: Math.floor(Math.random() * 90),
      thoi_gian: new Date().toISOString(),
    }
    receiveRealtimeEvent(payload)
  }, 9000)
}

onMounted(() => {
  systemState.value = 'Hệ thống đang hoạt động'
  clockTicker = setInterval(() => {
    now.value = new Date()
  }, 1000)

  initWebSocket()
  if (!hasRealtimeConnection.value) {
    startMockRealtime()
  }
})

onUnmounted(() => {
  clearInterval(clockTicker)
  clearInterval(mockTicker)
  clearTimeout(aiBlinkTimeout.value)

  if (echoInstance) {
    const adminId = adminStore.user?.id || adminStore.user?.ma_admin || 'global'
    echoInstance.leave(`private-admin.${adminId}`)
    echoInstance.leave('he-thong.giam-sat')
  }
})
</script>

<template>
  <section class="admin-dashboard-page">
    <header class="dashboard-head glass-panel">
      <div class="head-left">
        <div class="head-icon-wrap">
          <Siren class="head-icon" />
        </div>
        <div>
          <h1 class="head-title">Dashboard Quản trị An toàn Nhà xe</h1>
          <p class="head-sub">
            Giám sát sự cố khẩn cấp, tiếp nhận cảnh báo AI và theo dõi vi phạm tài xế theo thời gian thực.
          </p>
        </div>
      </div>
      <div class="head-right">
        <div class="status-pill">
          <Radio class="status-icon" />
          <span>{{ systemState }}</span>
        </div>
        <p class="time-text">{{ dashboardDateText }}</p>
        <p class="conn-text">{{ liveConnectionStatus }}</p>
      </div>
    </header>

    <div v-if="aiBlinkVisible" class="ai-blink-alert" role="alert">
      <TriangleAlert class="blink-icon" />
      <p>{{ aiBlinkMessage }}</p>
    </div>

    <div class="overview-grid">
      <article class="overview-card primary">
        <div class="overview-top">
          <Activity class="overview-icon" />
          <span>Live Tracking Sự cố</span>
        </div>
        <h3>{{ totals.trackCount }}</h3>
        <p>Sự cố đang hiển thị trên trung tâm điều hành</p>
      </article>

      <article class="overview-card danger">
        <div class="overview-top">
          <ShieldAlert class="overview-icon" />
          <span>Vi phạm nguy hiểm</span>
        </div>
        <h3>{{ totals.criticalEvents }}</h3>
        <p>Trường hợp khẩn cấp cần ưu tiên xử lý ngay</p>
      </article>

      <article class="overview-card warning">
        <div class="overview-top">
          <BellRing class="overview-icon" />
          <span>Cảnh báo mức trung bình</span>
        </div>
        <h3>{{ totals.warningEvents }}</h3>
        <p>Vi phạm hành vi và tín hiệu AI cần theo dõi</p>
      </article>

      <article class="overview-card success">
        <div class="overview-top">
          <BusFront class="overview-icon" />
          <span>Xe đang online</span>
        </div>
        <h3>{{ totals.activeVehicles }}</h3>
        <p>Phương tiện có dữ liệu GPS cập nhật mới nhất</p>
      </article>
    </div>

    <div class="main-grid">
      <article class="glass-panel panel">
        <div class="panel-header">
          <h2>
            <MapPin class="panel-icon" />
            Giám sát sự cố khẩn cấp (Live Tracking)
          </h2>
          <RouterLink class="panel-link" to="/admin/tracking">
            Mở trung tâm tracking
            <ArrowRight class="panel-link-icon" />
          </RouterLink>
        </div>
        <div class="tracking-list">
          <div
            v-for="item in orderedTracking"
            :key="item.id"
            class="tracking-item"
            :class="getBadgeClass(item.capDo)"
          >
            <div class="tracking-main">
              <p class="tracking-title">{{ item.bienSo }} • {{ item.tenTaiXe }}</p>
              <p class="tracking-meta">{{ item.loaiSuCo }}</p>
              <p class="tracking-meta">
                GPS: {{ formatLocation(item.viDo, item.kinhDo) }} • {{ item.khuVuc }} • {{ item.tocDo }} km/h
              </p>
            </div>
            <div class="tracking-side">
              <span class="level-badge" :class="getBadgeClass(item.capDo)">{{ getLevelText(item.capDo) }}</span>
              <span class="time-badge">{{ formatClock(item.thoiGian) }}</span>
            </div>
          </div>
        </div>
      </article>

      <article class="glass-panel panel">
        <div class="panel-header">
          <h2>
            <ShieldAlert class="panel-icon danger-icon" />
            Trợ lý lái xe an toàn (WebSocket)
          </h2>
        </div>
        <p class="panel-description">
          Mọi vi phạm từ camera AI trong xe (ngủ gật, hành vi) được đẩy về dashboard ngay lập tức kèm tọa độ GPS.
        </p>
        <div class="feed-list">
          <div
            v-for="item in orderedViolations"
            :key="item.id"
            class="feed-item"
            :class="getBadgeClass(item.capDo)"
          >
            <div class="feed-item-top">
              <p class="feed-title">{{ item.bienSo }} - {{ item.tenTaiXe }}</p>
              <span class="level-badge" :class="getBadgeClass(item.capDo)">{{ getLevelText(item.capDo) }}</span>
            </div>
            <p class="feed-message">{{ item.moTa }}</p>
            <p class="feed-meta">
              <Clock3 class="feed-meta-icon" />
              {{ formatClock(item.thoiGian) }}
              <span>•</span>
              <MapPin class="feed-meta-icon" />
              {{ formatLocation(item.viDo, item.kinhDo) }}
            </p>
          </div>
        </div>
      </article>
    </div>

    <article class="glass-panel panel alarm-panel">
      <div class="panel-header">
        <h2>
          <BellRing class="panel-icon danger-icon" />
          Tiếp nhận cảnh báo AI
        </h2>
      </div>
      <div class="alarm-list">
        <div
          v-for="alarm in orderedAlarms"
          :key="alarm.id"
          class="alarm-item"
          :class="getBadgeClass(alarm.capDo)"
        >
          <p class="alarm-title">{{ alarm.tieuDe }}</p>
          <p class="alarm-content">{{ alarm.noiDung }}</p>
          <p class="alarm-time">{{ formatClock(alarm.thoiGian) }}</p>
        </div>
        <p v-if="!orderedAlarms.length" class="alarm-empty">
          Chưa có cảnh báo mới. Hệ thống sẽ tự động cập nhật khi nhận tín hiệu AI từ xe.
        </p>
      </div>
    </article>
  </section>
</template>

<style scoped>
.admin-dashboard-page {
  min-height: 100%;
  display: flex;
  flex-direction: column;
  gap: 20px;
  color: #0f172a;
}

.glass-panel {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(226, 232, 240, 0.95);
  border-radius: 18px;
  box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
  backdrop-filter: blur(7px);
}

.dashboard-head {
  padding: 20px 22px;
  display: flex;
  justify-content: space-between;
  gap: 14px;
  flex-wrap: wrap;
}

.head-left {
  display: flex;
  gap: 14px;
  align-items: center;
}

.head-icon-wrap {
  width: 54px;
  height: 54px;
  border-radius: 14px;
  background: linear-gradient(135deg, #2563eb, #0ea5e9);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
}

.head-icon {
  width: 26px;
  height: 26px;
}

.head-title {
  margin: 0 0 6px 0;
  font-size: 1.35rem;
  font-weight: 800;
}

.head-sub {
  margin: 0;
  color: #475569;
  font-size: 0.92rem;
}

.head-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #0f172a;
  font-weight: 700;
  border-radius: 999px;
  border: 1px solid #bfdbfe;
  background: #eff6ff;
  padding: 6px 12px;
}

.status-icon {
  width: 15px;
  height: 15px;
}

.time-text,
.conn-text {
  margin: 0;
  color: #64748b;
  font-size: 0.82rem;
}

.ai-blink-alert {
  display: flex;
  align-items: center;
  gap: 10px;
  border-radius: 14px;
  border: 1px solid #fecaca;
  background: #ef4444;
  color: #ffffff;
  padding: 12px 16px;
  font-size: 1rem;
  font-weight: 800;
  animation: blink-danger 1s infinite;
}

.blink-icon {
  width: 21px;
  height: 21px;
  flex-shrink: 0;
}

@keyframes blink-danger {
  0% {
    opacity: 1;
    box-shadow: 0 0 0 rgba(239, 68, 68, 0.8);
  }
  50% {
    opacity: 0.45;
    box-shadow: 0 0 24px rgba(239, 68, 68, 0.6);
  }
  100% {
    opacity: 1;
    box-shadow: 0 0 0 rgba(239, 68, 68, 0.8);
  }
}

.overview-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}

.overview-card {
  padding: 16px;
  border-radius: 16px;
  color: #ffffff;
  box-shadow: 0 12px 26px rgba(15, 23, 42, 0.14);
}

.overview-card.primary {
  background: linear-gradient(135deg, #2563eb, #0ea5e9);
}

.overview-card.danger {
  background: linear-gradient(135deg, #dc2626, #f97316);
}

.overview-card.warning {
  background: linear-gradient(135deg, #d97706, #f59e0b);
}

.overview-card.success {
  background: linear-gradient(135deg, #16a34a, #10b981);
}

.overview-top {
  display: flex;
  gap: 7px;
  align-items: center;
  font-weight: 700;
  font-size: 0.86rem;
}

.overview-icon {
  width: 16px;
  height: 16px;
}

.overview-card h3 {
  font-size: 2rem;
  margin: 10px 0 6px;
  line-height: 1;
}

.overview-card p {
  margin: 0;
  font-size: 0.84rem;
  opacity: 0.93;
}

.main-grid {
  display: grid;
  grid-template-columns: 1.45fr 1fr;
  gap: 16px;
}

.panel {
  padding: 16px;
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 12px;
}

.panel-header h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 7px;
}

.panel-icon {
  width: 18px;
  height: 18px;
  color: #2563eb;
}

.danger-icon {
  color: #dc2626;
}

.panel-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  text-decoration: none;
  color: #2563eb;
  font-size: 0.84rem;
  font-weight: 700;
}

.panel-link-icon {
  width: 14px;
  height: 14px;
}

.panel-description {
  margin: 0 0 12px;
  color: #475569;
  font-size: 0.86rem;
}

.tracking-list,
.feed-list,
.alarm-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.tracking-item,
.feed-item,
.alarm-item {
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  padding: 11px 12px;
  background: #ffffff;
}

.tracking-item {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}

.tracking-main {
  min-width: 0;
}

.tracking-title,
.feed-title,
.alarm-title {
  margin: 0 0 4px;
  font-size: 0.9rem;
  font-weight: 800;
}

.tracking-meta,
.feed-message,
.alarm-content {
  margin: 0;
  font-size: 0.82rem;
  color: #475569;
}

.tracking-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;
}

.time-badge,
.alarm-time {
  color: #64748b;
  font-size: 0.76rem;
  margin: 0;
}

.feed-item-top {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: center;
}

.feed-meta {
  margin: 6px 0 0;
  color: #64748b;
  font-size: 0.78rem;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.feed-meta-icon {
  width: 14px;
  height: 14px;
}

.level-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 3px 10px;
  font-size: 0.72rem;
  font-weight: 800;
  border: 1px solid transparent;
}

.badge-critical {
  border-color: #fecaca;
  background: #fef2f2;
}

.level-badge.badge-critical {
  color: #b91c1c;
  background: #fee2e2;
}

.badge-warning {
  border-color: #fde68a;
  background: #fffbeb;
}

.level-badge.badge-warning {
  color: #92400e;
  background: #fef3c7;
}

.badge-info {
  border-color: #bfdbfe;
  background: #eff6ff;
}

.level-badge.badge-info {
  color: #1d4ed8;
  background: #dbeafe;
}

.alarm-panel {
  margin-bottom: 8px;
}

.alarm-empty {
  margin: 0;
  padding: 10px 2px;
  font-size: 0.85rem;
  color: #64748b;
}

@media (max-width: 1280px) {
  .overview-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .main-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .overview-grid {
    grid-template-columns: 1fr;
  }
  .head-right {
    align-items: flex-start;
  }
  .tracking-item {
    flex-direction: column;
  }
  .tracking-side {
    align-items: flex-start;
  }
}
</style>
