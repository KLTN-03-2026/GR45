<script setup>
import {
  ref,
  reactive,
  computed,
  onMounted,
  watch,
  nextTick,
  onUnmounted,
} from "vue";
import maplibregl from "@openmapvn/openmapvn-gl";
import "@openmapvn/openmapvn-gl/dist/maplibre-gl.css";
import adminApi from "@/api/adminApi";
import { formatDateTime } from "@/utils/format";

// --- State ---
const completedTrips = ref([]);
const selectedTrip = ref(null);
const searchQuery = ref("");
const loading = reactive({ trips: false, tracking: false, replay: false });
const trackingData = reactive({ hien_tai: null, lich_su: [], meta: {} });
const pagination = reactive({ current_page: 1, last_page: 1, total: 0 });
const currentPoint = ref(null);

// Map
const mapContainer = ref(null);
let mapInstance = null;
let routeMarker = null;
let replayTimer = null;
const replayIndex = ref(0);
const isReplaying = ref(false);

// --- Replay Speed ---
const replaySpeed = ref(1);
const speedOptions = [
  { label: "0.5×", value: 0.5 },
  { label: "1×", value: 1 },
  { label: "2×", value: 2 },
  { label: "4×", value: 4 },
];
const getIntervalMs = () => Math.round(400 / replaySpeed.value);

// --- Alarm Modal ---
const alarmModal = reactive({
  visible: false,
  loading: false,
  alarms: [],
  mode: "point", // 'point' | 'all'
  pausedAtIndex: -1,
  pausedPoint: null,
});

const alarmTypeMap = {
  ngu_gat: "Ngủ gật",
  qua_toc_do: "Quá tốc độ",
  phanh_gap: "Phanh gấp",
  lac_lan: "Lạc làn",
  roi_khoi_hanh_trinh: "Rời hành trình",
  khong_phan_hoi: "Không phản hồi",
  thiet_bi_loi: "Thiết bị lỗi",
  su_dung_dien_thoai: "Dùng điện thoại",
  hut_thuoc: "Hút thuốc",
  mang_vu_khi: "Mang vũ khí",
  khong_quan_sat: "Không quan sát",
  bao_dong_khan_cap: "Khẩn cấp SOS",
  vi_pham_khac: "Vi phạm khác",
};
const mucDoMap = {
  thong_tin: "Thông tin",
  canh_bao: "Cảnh báo",
  nguy_hiem: "Nguy hiểm",
  khan_cap: "Khẩn cấp",
};
const trangThaiAlarmMap = {
  moi: "Mới",
  da_xem: "Đã xem",
  da_xu_ly: "Đã xử lý",
  bo_qua: "Bỏ qua",
};
const mucDoColor = {
  thong_tin: "#64748b",
  canh_bao: "#f59e0b",
  nguy_hiem: "#f97316",
  khan_cap: "#dc2626",
};
const formatAlarmType = (t) => alarmTypeMap[t] || t;
const formatMucDo = (m) => mucDoMap[m] || m;
const formatTrangThaiAlarm = (t) => trangThaiAlarmMap[t] || t;

// --- Status Mapping ---
const statusMap = {
  binh_thuong: "Bình thường",
  met_moi: "Mệt mỏi",
  buon_ngu: "Buồn ngủ",
  mat_tap_trung: "Mất tập trung",
  hut_thuoc: "Hút thuốc",
  nghe_dien_thoai: "Nghe điện thoại",
  canh_bao: "Cảnh báo!",
};

const formatStatus = (s) => statusMap[s] || s || "Bình thường";

// --- Computed ---
const filteredTrips = computed(() => {
  // Server-side search is used instead
  return completedTrips.value;
});

// --- Map ---
const initMap = () => {
  if (!mapContainer.value || mapInstance) return;
  const apiKey = import.meta.env.VITE_OPENMAP_API_KEY;
  mapInstance = new maplibregl.Map({
    container: mapContainer.value,
    style: `https://maptiles.openmap.vn/styles/day-v1/style.json?apikey=${apiKey}`,
    center: [106.660172, 10.762622],
    zoom: 12,
  });
  mapInstance.addControl(new maplibregl.NavigationControl(), "top-right");
  mapInstance.on("load", () => {
    mapInstance.addSource("history-route", {
      type: "geojson",
      data: {
        type: "Feature",
        properties: {},
        geometry: { type: "LineString", coordinates: [] },
      },
    });
    mapInstance.addLayer({
      id: "history-route",
      type: "line",
      source: "history-route",
      layout: { "line-join": "round", "line-cap": "round" },
      paint: {
        "line-color": "#8b5cf6",
        "line-width": 4,
        "line-opacity": 0.85,
        "line-dasharray": [3, 2],
      },
    });
  });
  const el = document.createElement("div");
  el.innerHTML = `<div style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);width:36px;height:36px;border-radius:50%;border:3px solid white;box-shadow:0 0 14px rgba(139,92,246,0.4),0 3px 8px rgba(0,0,0,0.2);display:flex;align-items:center;justify-content:center;color:white;font-size:16px;transition:all 0.3s">🚌</div>`;
  routeMarker = new maplibregl.Marker({ element: el })
    .setLngLat([106.660172, 10.762622])
    .addTo(mapInstance);
};

// --- API ---
const fetchCompletedTrips = async (page = 1) => {
  loading.trips = true;
  try {
    const params = { page, per_page: 20 };
    if (searchQuery.value) params.search = searchQuery.value;
    const res = await adminApi.getCompletedTrips(params);
    const payload = res?.data || res;
    completedTrips.value = payload?.data || [];
    if (payload?.meta) {
      pagination.current_page = payload.meta.current_page;
      pagination.last_page = payload.meta.last_page;
      pagination.total = payload.meta.total;
    }
  } catch (e) {
    console.error("Lỗi tải chuyến hoàn thành:", e);
  } finally {
    loading.trips = false;
  }
};

const fetchTrackingHistory = async (tripId) => {
  loading.tracking = true;
  try {
    const res = await adminApi.getTripTrackingHistory(tripId, {
      sample_seconds: 60,
      limit: 2000,
    });
    const payload = res?.data || res;
    const data = payload?.data || payload || {};
    trackingData.hien_tai = data.hien_tai || null;
    trackingData.lich_su = Array.isArray(data.lich_su) ? data.lich_su : [];
    trackingData.meta = data.meta || {};
    currentPoint.value = trackingData.hien_tai;
    renderHistoryOnMap();
  } catch (e) {
    console.error("Lỗi tải tracking:", e);
  } finally {
    loading.tracking = false;
  }
};

const renderHistoryOnMap = () => {
  if (!mapInstance) return;
  const points = trackingData.lich_su
    .map((p) => {
      const lng = Number(p.kinh_do),
        lat = Number(p.vi_do);
      return Number.isFinite(lng) && Number.isFinite(lat) ? [lng, lat] : null;
    })
    .filter(Boolean);

  const src = mapInstance.getSource("history-route");
  if (src)
    src.setData({
      type: "Feature",
      properties: {},
      geometry: { type: "LineString", coordinates: [] },
    });

  if (points.length > 0) {
    // Move marker to last point
    const last = points[points.length - 1];
    routeMarker.setLngLat(last);
    // Fit bounds
    const bounds = new maplibregl.LngLatBounds(points[0], points[0]);
    for (const coord of points) bounds.extend(coord);
    mapInstance.fitBounds(bounds, { padding: 60, maxZoom: 16 });
  }
};

// --- Chọn chuyến ---
const selectTrip = (trip) => {
  stopReplay();
  selectedTrip.value = trip;
  trackingData.lich_su = [];
  trackingData.hien_tai = null;
  currentPoint.value = null;
  fetchTrackingHistory(trip.id);
};

// --- Phát lại hành trình ---
const buildValidPoints = () =>
  trackingData.lich_su
    .map((p) => {
      const lng = Number(p.kinh_do),
        lat = Number(p.vi_do);
      return Number.isFinite(lng) && Number.isFinite(lat) ? [lng, lat] : null;
    })
    .filter(Boolean);

const startReplayFrom = (fromIndex = 0) => {
  if (trackingData.lich_su.length < 2) return;
  if (replayTimer) {
    clearInterval(replayTimer);
    replayTimer = null;
  }
  isReplaying.value = true;
  replayIndex.value = fromIndex;
  const points = buildValidPoints();

  replayTimer = setInterval(() => {
    if (replayIndex.value >= points.length) {
      stopReplay();
      return;
    }
    const coord = points[replayIndex.value];
    const rawPoint = trackingData.lich_su[replayIndex.value];
    currentPoint.value = rawPoint;
    routeMarker.setLngLat(coord);
    mapInstance.panTo(coord);
    const partial = points.slice(0, replayIndex.value + 1);
    const src = mapInstance.getSource("history-route");
    if (src)
      src.setData({
        type: "Feature",
        properties: {},
        geometry: { type: "LineString", coordinates: partial },
      });

    // Auto-pause on unsafe status
    if (
      rawPoint.trang_thai_tai_xe &&
      rawPoint.trang_thai_tai_xe !== "binh_thuong"
    ) {
      replayIndex.value++;
      clearInterval(replayTimer);
      replayTimer = null;
      isReplaying.value = false;
      openAlarmAtPoint(rawPoint, replayIndex.value);
      return;
    }
    replayIndex.value++;
  }, getIntervalMs());
};

const startReplay = () => startReplayFrom(0);

// Change speed while replaying
const setSpeed = (s) => {
  replaySpeed.value = s;
  if (isReplaying.value) {
    // Restart timer with new interval from current position
    const cur = replayIndex.value;
    clearInterval(replayTimer);
    replayTimer = null;
    startReplayFrom(cur);
  }
};

// tracking.thoi_diem_ghi: API trả UTC (Z), DB alarm lưu VN local (UTC+7).
// Sau khi sync DB, alarm timestamps khớp với tracking points (trong vài giây).
// Dùng window ±5 phút gửi lên BE (dạng VN local string để khớp created_at trong DB).
const toVNLocalString = (utcDate) => {
  const vnMs = utcDate.getTime() + 7 * 60 * 60 * 1000;
  return new Date(vnMs).toISOString().slice(0, 19).replace('T', ' ');
};

const ALARM_WINDOW_MIN = 5; // phút

const openAlarmAtPoint = async (point, resumeFromIndex) => {
  alarmModal.visible = true;
  alarmModal.mode = 'point';
  alarmModal.loading = true;
  alarmModal.alarms = [];
  alarmModal.pausedAtIndex = resumeFromIndex;
  alarmModal.pausedPoint = point;
  try {
    // Chuyển thời gian UTC của tracking point sang VN rồi tính window ±5 phút
    const t = new Date(point.thoi_diem_ghi); // UTC
    const tu  = toVNLocalString(new Date(t.getTime() - ALARM_WINDOW_MIN * 60 * 1000));
    const den = toVNLocalString(new Date(t.getTime() + ALARM_WINDOW_MIN * 60 * 1000));
    const res = await adminApi.getAlerts({
      id_chuyen_xe: selectedTrip.value.id,
      tu_ngay: tu, den_ngay: den, limit: 20,
    });
    const payload = res?.data || res;
    alarmModal.alarms = Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []);
  } catch (e) { console.error('Lỗi tải báo động:', e); }
  finally { alarmModal.loading = false; }
};


const openAllAlarms = async () => {
  if (!selectedTrip.value) return;
  alarmModal.visible = true;
  alarmModal.mode = "all";
  alarmModal.loading = true;
  alarmModal.alarms = [];
  alarmModal.pausedAtIndex = -1;
  try {
    const res = await adminApi.getAlerts({
      id_chuyen_xe: selectedTrip.value.id,
      limit: 200,
    });
    const payload = res?.data || res;
    alarmModal.alarms = Array.isArray(payload?.data)
      ? payload.data
      : Array.isArray(payload)
        ? payload
        : [];
  } catch (e) {
    console.error("Lỗi tải báo động:", e);
  } finally {
    alarmModal.loading = false;
  }
};

const closeAlarmModal = () => {
  alarmModal.visible = false;
};
const resumeReplay = () => {
  alarmModal.visible = false;
  if (alarmModal.pausedAtIndex >= 0) startReplayFrom(alarmModal.pausedAtIndex);
};
const restartReplay = () => {
  alarmModal.visible = false;
  startReplay();
};

const markAlarmSeen = async (alarm) => {
  try {
    await adminApi.toggleAlertStatus(alarm.id, { trang_thai: "da_xem" });
    alarm.trang_thai = "da_xem";
  } catch (e) {
    console.error("Lỗi cập nhật:", e);
  }
};

const stopReplay = () => {
  isReplaying.value = false;
  if (replayTimer) {
    clearInterval(replayTimer);
    replayTimer = null;
  }
  if (
    trackingData.hien_tai &&
    replayIndex.value >= trackingData.lich_su.length
  ) {
    currentPoint.value = trackingData.hien_tai;
  }
};

// Search debounce
let searchTimeout = null;
watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => fetchCompletedTrips(1), 400);
});

onMounted(async () => {
  await nextTick();
  initMap();
  await fetchCompletedTrips();
});

onUnmounted(() => {
  stopReplay();
  if (mapInstance) {
    mapInstance.remove();
    mapInstance = null;
  }
});
</script>

<template>
  <div class="history-wrapper">
    <!-- Header -->
    <header class="ht-header">
      <div class="ht-header-left">
        <div class="ht-header-icon">📋</div>
        <div>
          <h1 class="ht-title">Lịch Sử Hành Trình</h1>
          <p class="ht-subtitle">
            Xem lại hành trình chuyến xe đã hoàn thành trên bản đồ
          </p>
        </div>
      </div>
      <div class="ht-header-right">
        <span class="ht-total-badge"
          >{{ pagination.total }} chuyến có dữ liệu</span
        >
      </div>
    </header>

    <!-- Main Layout -->
    <div class="ht-main">
      <!-- Sidebar -->
      <aside class="ht-sidebar">
        <div class="ht-search-box">
          <svg
            class="ht-search-icon"
            width="15"
            height="15"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
          >
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.35-4.35" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            class="ht-search-input"
            placeholder="Tìm theo tuyến, biển số..."
          />
        </div>

        <div class="ht-trip-list" v-if="!loading.trips">
          <div
            v-for="trip in filteredTrips"
            :key="trip.id"
            class="ht-trip-card"
            :class="{ selected: selectedTrip?.id === trip.id }"
            @click="selectTrip(trip)"
          >
            <div class="ht-trip-top">
              <span class="ht-trip-id">#{{ trip.id }}</span>
              <span class="ht-trip-points">{{ trip.tracking_count }} điểm</span>
            </div>
            <div class="ht-trip-route">
              {{ trip.tuyen_duong?.ten_tuyen_duong || "—" }}
            </div>
            <div class="ht-trip-meta">
              <span v-if="trip.xe">🚌 {{ trip.xe.bien_so }}</span>
              <span v-if="trip.tai_xe">👤 {{ trip.tai_xe.ho_ten }}</span>
            </div>
            <div class="ht-trip-date">
              📅 {{ trip.ngay_khoi_hanh }} — {{ trip.gio_khoi_hanh }}
            </div>
          </div>

          <div v-if="filteredTrips.length === 0" class="ht-empty">
            <span>📭</span>
            <span>Không tìm thấy chuyến xe nào</span>
          </div>
        </div>
        <div v-else class="ht-loading">
          <div class="ht-spinner"></div>
          <span>Đang tải...</span>
        </div>

        <!-- Pagination -->
        <div class="ht-pagination" v-if="pagination.last_page > 1">
          <button
            class="ht-page-btn"
            :disabled="pagination.current_page <= 1"
            @click="fetchCompletedTrips(pagination.current_page - 1)"
          >
            ‹
          </button>
          <span class="ht-page-info"
            >{{ pagination.current_page }} / {{ pagination.last_page }}</span
          >
          <button
            class="ht-page-btn"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="fetchCompletedTrips(pagination.current_page + 1)"
          >
            ›
          </button>
        </div>
      </aside>

      <!-- Map Area -->
      <div class="ht-map-area">
        <div ref="mapContainer" class="ht-map"></div>

        <!-- Replay Controls -->
        <div class="ht-replay-bar" v-if="selectedTrip">
          <div class="ht-replay-info">
            <strong>{{
              selectedTrip.tuyen_duong?.ten_tuyen_duong ||
              "Chuyến #" + selectedTrip.id
            }}</strong>
            <span class="ht-replay-meta"
              >{{ trackingData.lich_su.length }} điểm tracking</span
            >
          </div>
          <div class="ht-replay-actions">
            <!-- Speed Selector -->
            <div class="ht-speed-group">
              <span class="ht-speed-label">⏱</span>
              <button
                v-for="opt in speedOptions"
                :key="opt.value"
                class="ht-speed-btn"
                :class="{ active: replaySpeed === opt.value }"
                @click="setSpeed(opt.value)"
              >
                {{ opt.label }}
              </button>
            </div>

            <!-- Play/Stop -->
            <button
              class="ht-replay-btn"
              :class="{ playing: isReplaying }"
              @click="isReplaying ? stopReplay() : startReplay()"
              :disabled="trackingData.lich_su.length < 2"
            >
              {{ isReplaying ? "⏸ Dừng" : "▶ Phát lại" }}
            </button>

            <!-- All Alarms Button -->
            <button
              class="ht-alarm-all-btn"
              @click="openAllAlarms"
              :disabled="!selectedTrip"
            >
              🔔 Xem báo động
            </button>

            <!-- Progress -->
            <div
              class="ht-replay-progress"
              v-if="isReplaying || replayIndex > 0"
            >
              <div class="ht-progress-bar">
                <div
                  class="ht-progress-fill"
                  :style="{
                    width:
                      (replayIndex / Math.max(trackingData.lich_su.length, 1)) *
                        100 +
                      '%',
                  }"
                ></div>
              </div>
              <span class="ht-progress-text"
                >{{ replayIndex }}/{{ trackingData.lich_su.length }}</span
              >
            </div>
          </div>
        </div>

        <!-- Info Bar -->
        <div class="ht-info-bar" v-if="currentPoint">
          <div class="ht-info-item">
            <span class="ht-info-label">{{
              isReplaying ? "Vị trí hiện tại" : "Vị trí điểm cuối"
            }}</span>
            <span class="ht-info-value"
              >{{ Number(currentPoint.vi_do).toFixed(6) }},
              {{ Number(currentPoint.kinh_do).toFixed(6) }}</span
            >
          </div>
          <div class="ht-info-item">
            <span class="ht-info-label">Vận tốc</span>
            <span class="ht-info-value accent"
              >{{ Number(currentPoint.van_toc || 0).toFixed(0) }} km/h</span
            >
          </div>
          <div class="ht-info-item">
            <span class="ht-info-label">Trạng thái tài xế</span>
            <span
              class="ht-info-value"
              :class="{
                warning:
                  currentPoint.trang_thai_tai_xe &&
                  currentPoint.trang_thai_tai_xe !== 'binh_thuong',
              }"
            >
              <span
                v-if="
                  currentPoint.trang_thai_tai_xe &&
                  currentPoint.trang_thai_tai_xe !== 'binh_thuong'
                "
                class="ht-warn-pulse"
                >⚠️</span
              >
              {{ formatStatus(currentPoint.trang_thai_tai_xe) }}
            </span>
          </div>
          <div class="ht-info-item">
            <span class="ht-info-label">Thời gian ghi</span>
            <span class="ht-info-value">{{
              formatDateTime(currentPoint.thoi_diem_ghi)
            }}</span>
          </div>
          <div class="ht-info-item">
            <span class="ht-info-label">Tổng số điểm</span>
            <span class="ht-info-value">{{
              trackingData.meta.returned || trackingData.lich_su.length
            }}</span>
          </div>
        </div>

        <div class="ht-info-bar empty" v-else-if="!selectedTrip">
          <span class="ht-info-hint"
            >👈 Chọn chuyến xe bên trái để xem lịch sử hành trình</span
          >
        </div>

        <div class="ht-info-bar empty" v-else-if="loading.tracking">
          <div class="ht-spinner-sm"></div>
          <span class="ht-info-hint">Đang tải dữ liệu tracking...</span>
        </div>
      </div>
    </div>

    <!-- ===== ALARM MODAL ===== -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="alarmModal.visible"
          class="ht-modal-overlay"
          @click.self="closeAlarmModal"
        >
          <div class="ht-modal-card">
            <!-- Modal Header -->
            <div
              class="ht-modal-header"
              :class="alarmModal.mode === 'all' ? 'all-mode' : 'warn-mode'"
            >
              <div class="ht-modal-header-left">
                <span class="ht-modal-icon">{{
                  alarmModal.mode === "all" ? "🔔" : "⚠️"
                }}</span>
                <div>
                  <h2 class="ht-modal-title">
                    {{
                      alarmModal.mode === "all"
                        ? "Tất cả báo động — Chuyến #" + selectedTrip?.id
                        : "Cảnh báo tại điểm dừng"
                    }}
                  </h2>
                  <p
                    class="ht-modal-subtitle"
                    v-if="alarmModal.mode === 'point' && alarmModal.pausedPoint"
                  >
                    Trạng thái:
                    <strong>{{
                      formatStatus(alarmModal.pausedPoint.trang_thai_tai_xe)
                    }}</strong>
                    — {{ formatDateTime(alarmModal.pausedPoint.thoi_diem_ghi) }}
                  </p>
                </div>
              </div>
              <button class="ht-modal-close" @click="closeAlarmModal">✕</button>
            </div>

            <!-- Modal Body -->
            <div class="ht-modal-body">
              <!-- Loading -->
              <div v-if="alarmModal.loading" class="ht-modal-loading">
                <div class="ht-spinner"></div>
                <span>Đang tải báo động...</span>
              </div>

              <!-- Empty -->
              <div
                v-else-if="alarmModal.alarms.length === 0"
                class="ht-modal-empty"
              >
                <span style="font-size: 2rem">✅</span>
                <span>Không tìm thấy báo động trong khoảng thời gian này</span>
              </div>

              <!-- Alarm List -->
              <div v-else class="ht-alarm-list">
                <div
                  v-for="alarm in alarmModal.alarms"
                  :key="alarm.id"
                  class="ht-alarm-item"
                >
                  <!-- Severity Badge -->
                  <div
                    class="ht-alarm-badge"
                    :style="{
                      background: mucDoColor[alarm.muc_do] || '#64748b',
                    }"
                  >
                    {{ formatMucDo(alarm.muc_do) }}
                  </div>
                  <div class="ht-alarm-content">
                    <div class="ht-alarm-top">
                      <span class="ht-alarm-type">{{
                        formatAlarmType(alarm.loai_bao_dong)
                      }}</span>
                      <span class="ht-alarm-status" :class="alarm.trang_thai">{{
                        formatTrangThaiAlarm(alarm.trang_thai)
                      }}</span>
                    </div>
                    <div class="ht-alarm-meta">
                      <span>🕐 {{ formatDateTime(alarm.created_at) }}</span>
                      <span v-if="alarm.vi_do_luc_bao"
                        >📍 {{ Number(alarm.vi_do_luc_bao).toFixed(5) }},
                        {{ Number(alarm.kinh_do_luc_bao).toFixed(5) }}</span
                      >
                    </div>
                    <!-- Image -->
                    <img
                      v-if="alarm.anh_url"
                      :src="alarm.anh_url"
                      class="ht-alarm-img"
                      alt="Ảnh vi phạm"
                    />
                    <!-- Detection data -->
                    <div
                      v-if="
                        alarm.du_lieu_phat_hien &&
                        Object.keys(alarm.du_lieu_phat_hien).length
                      "
                      class="ht-alarm-data"
                    >
                      <span
                        v-for="(v, k) in alarm.du_lieu_phat_hien"
                        :key="k"
                        class="ht-alarm-chip"
                        >{{ k }}: {{ v }}</span
                      >
                    </div>
                  </div>
                  <!-- Mark seen -->
                  <button
                    v-if="alarm.trang_thai === 'moi'"
                    class="ht-alarm-seen-btn"
                    @click="markAlarmSeen(alarm)"
                  >
                    ✓ Đã xem
                  </button>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="ht-modal-footer">
              <button class="ht-modal-btn secondary" @click="closeAlarmModal">
                Đóng
              </button>
              <div
                class="ht-modal-footer-right"
                v-if="
                  alarmModal.mode === 'point' && alarmModal.pausedAtIndex >= 0
                "
              >
                <button class="ht-modal-btn outline" @click="restartReplay">
                  ↩ Phát từ đầu
                </button>
                <button class="ht-modal-btn primary" @click="resumeReplay">
                  ▶ Tiếp tục xem
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* ===== WRAPPER ===== */
.history-wrapper {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 80px);
  font-family: "Inter", "Segoe UI", system-ui, sans-serif;
  gap: 1rem;
}

/* ===== HEADER ===== */
.ht-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-radius: 16px;
  background: linear-gradient(135deg, #3b0764 0%, #6b21a8 60%, #7c3aed 100%);
  color: white;
  box-shadow: 0 6px 24px rgba(59, 7, 100, 0.25);
  flex-shrink: 0;
}
.ht-header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}
.ht-header-icon {
  font-size: 28px;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.12);
  border-radius: 14px;
}
.ht-title {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}
.ht-subtitle {
  margin: 3px 0 0;
  color: #d8b4fe;
  font-size: 0.82rem;
}
.ht-total-badge {
  padding: 8px 16px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  font-size: 0.82rem;
  font-weight: 700;
  color: #e9d5ff;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

/* ===== MAIN LAYOUT ===== */
.ht-main {
  display: flex;
  flex: 1;
  gap: 1rem;
  min-height: 0;
}

/* ===== SIDEBAR ===== */
.ht-sidebar {
  width: 340px;
  min-width: 290px;
  flex-shrink: 0;
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.06);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.ht-search-box {
  position: relative;
  padding: 14px 14px 10px;
  border-bottom: 1px solid #f1f5f9;
}
.ht-search-icon {
  position: absolute;
  left: 26px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}
.ht-search-input {
  width: 100%;
  padding: 10px 12px 10px 36px;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.85rem;
  color: #1e293b;
  background: #f8fafc;
  outline: none;
  transition: all 0.2s;
}
.ht-search-input:focus {
  border-color: #8b5cf6;
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
  background: white;
}
.ht-trip-list {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

/* Trip Card */
.ht-trip-card {
  padding: 12px 14px;
  border-radius: 12px;
  border: 1.5px solid #e2e8f0;
  cursor: pointer;
  transition: all 0.2s;
  background: white;
}
.ht-trip-card:hover {
  border-color: #d8b4fe;
  background: #fdf4ff;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(139, 92, 246, 0.08);
}
.ht-trip-card.selected {
  border-color: #8b5cf6;
  background: linear-gradient(135deg, #faf5ff, #f3e8ff);
  box-shadow: 0 4px 16px rgba(139, 92, 246, 0.15);
}

.ht-trip-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}
.ht-trip-id {
  font-size: 0.72rem;
  font-weight: 700;
  color: #8b5cf6;
}
.ht-trip-points {
  font-size: 0.68rem;
  font-weight: 700;
  color: #94a3b8;
  background: #f1f5f9;
  padding: 2px 8px;
  border-radius: 999px;
}
.ht-trip-route {
  font-size: 0.88rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 4px;
  line-height: 1.35;
}
.ht-trip-meta {
  display: flex;
  gap: 12px;
  font-size: 0.75rem;
  color: #64748b;
  margin-bottom: 4px;
}
.ht-trip-date {
  font-size: 0.72rem;
  color: #94a3b8;
}

.ht-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 40px 20px;
  color: #94a3b8;
  font-size: 0.85rem;
  text-align: center;
}
.ht-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 40px;
  color: #64748b;
  font-size: 0.85rem;
}
.ht-spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #e2e8f0;
  border-top-color: #8b5cf6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
.ht-spinner-sm {
  width: 20px;
  height: 20px;
  border: 2px solid #e2e8f0;
  border-top-color: #8b5cf6;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Pagination */
.ht-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 10px;
  border-top: 1px solid #f1f5f9;
}
.ht-page-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: white;
  color: #334155;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}
.ht-page-btn:hover:not(:disabled) {
  background: #f5f3ff;
  border-color: #8b5cf6;
  color: #8b5cf6;
}
.ht-page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.ht-page-info {
  font-size: 0.82rem;
  font-weight: 600;
  color: #64748b;
}

/* ===== MAP AREA ===== */
.ht-map-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.06);
  background: white;
  min-width: 0;
}
.ht-map {
  flex: 1;
  width: 100%;
  min-height: 400px;
}

/* Replay Bar */
.ht-replay-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  padding: 10px 16px;
  border-top: 1px solid #f1f5f9;
  background: #faf5ff;
  gap: 8px;
}
.ht-replay-info strong {
  font-size: 0.88rem;
  color: #1e293b;
}
.ht-replay-meta {
  margin-left: 10px;
  font-size: 0.75rem;
  color: #94a3b8;
}
.ht-replay-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.ht-replay-btn {
  padding: 7px 16px;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, #8b5cf6, #7c3aed);
  color: white;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 3px 12px rgba(139, 92, 246, 0.25);
}
.ht-replay-btn:hover {
  background: linear-gradient(135deg, #7c3aed, #6d28d9);
  transform: translateY(-1px);
}
.ht-replay-btn.playing {
  background: linear-gradient(135deg, #dc2626, #b91c1c);
  box-shadow: 0 3px 12px rgba(220, 38, 38, 0.25);
}
.ht-replay-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}
.ht-progress-bar {
  flex: 1;
  height: 4px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
}
.ht-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #8b5cf6, #a78bfa);
  border-radius: 4px;
  transition: width 0.15s;
}

/* Info Bar */
.ht-info-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 0;
  border-top: 1px solid #f1f5f9;
  background: white;
  flex-shrink: 0;
}
.ht-info-bar.empty {
  justify-content: center;
  align-items: center;
  gap: 8px;
  padding: 16px;
}
.ht-info-hint {
  color: #94a3b8;
  font-size: 0.85rem;
  font-weight: 500;
}
.ht-info-item {
  flex: 1;
  min-width: 140px;
  padding: 12px 16px;
  border-right: 1px solid #f1f5f9;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.ht-info-item:last-child {
  border-right: none;
}
.ht-info-label {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #94a3b8;
  font-weight: 700;
}
.ht-info-value {
  font-size: 0.85rem;
  color: #1e293b;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.ht-info-value.accent {
  color: #8b5cf6;
  font-weight: 700;
}
.ht-info-value.warning {
  color: #ef4444;
  font-weight: 700;
}

:deep(.maplibregl-popup-content) {
  border-radius: 12px;
  padding: 10px 14px;
  font-size: 0.82rem;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  font-family: "Inter", system-ui, sans-serif;
}
:deep(.maplibregl-ctrl-group) {
  border-radius: 12px !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

/* Replay Bar extras */
.ht-speed-group {
  display: flex;
  align-items: center;
  gap: 4px;
}
.ht-speed-label {
  font-size: 0.75rem;
  color: #94a3b8;
  margin-right: 2px;
}
.ht-speed-btn {
  padding: 4px 10px;
  border-radius: 8px;
  border: 1.5px solid #e2e8f0;
  background: white;
  color: #64748b;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}
.ht-speed-btn:hover {
  border-color: #8b5cf6;
  color: #8b5cf6;
  background: #faf5ff;
}
.ht-speed-btn.active {
  background: linear-gradient(135deg, #8b5cf6, #7c3aed);
  color: white;
  border-color: transparent;
  box-shadow: 0 2px 8px rgba(139, 92, 246, 0.3);
}
.ht-alarm-all-btn {
  padding: 6px 14px;
  border-radius: 10px;
  border: 1.5px solid #fca5a5;
  background: #fff1f2;
  color: #dc2626;
  font-size: 0.78rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}
.ht-alarm-all-btn:hover {
  background: #fecaca;
  border-color: #ef4444;
  transform: translateY(-1px);
}
.ht-alarm-all-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
  transform: none;
}
.ht-replay-progress {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 140px;
}
.ht-progress-text {
  font-size: 0.7rem;
  color: #94a3b8;
  font-weight: 600;
  white-space: nowrap;
}
.ht-warn-pulse {
  animation: warn-blink 0.8s ease-in-out infinite;
  display: inline-block;
}
@keyframes warn-blink {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.3;
  }
}

/* ===== ALARM MODAL ===== */
.ht-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.ht-modal-card {
  background: white;
  border-radius: 20px;
  width: 100%;
  max-width: 620px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}
.ht-modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 18px 22px;
  gap: 14px;
  flex-shrink: 0;
}
.ht-modal-header.warn-mode {
  background: linear-gradient(135deg, #7f1d1d, #dc2626);
  color: white;
}
.ht-modal-header.all-mode {
  background: linear-gradient(135deg, #1e1b4b, #6d28d9);
  color: white;
}
.ht-modal-header-left {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}
.ht-modal-icon {
  font-size: 28px;
  flex-shrink: 0;
  margin-top: 2px;
}
.ht-modal-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
}
.ht-modal-subtitle {
  margin: 4px 0 0;
  font-size: 0.8rem;
  opacity: 0.85;
}
.ht-modal-close {
  background: rgba(255, 255, 255, 0.15);
  border: none;
  color: white;
  width: 32px;
  height: 32px;
  border-radius: 10px;
  cursor: pointer;
  font-size: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
  flex-shrink: 0;
}
.ht-modal-close:hover {
  background: rgba(255, 255, 255, 0.25);
}
.ht-modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 16px 22px;
}
.ht-modal-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 40px;
  color: #64748b;
}
.ht-modal-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 40px;
  color: #94a3b8;
  font-size: 0.9rem;
  text-align: center;
}

/* Alarm List */
.ht-alarm-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.ht-alarm-item {
  display: flex;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1.5px solid #f1f5f9;
  background: #fafbfc;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}
.ht-alarm-item:hover {
  border-color: #ddd6fe;
  box-shadow: 0 4px 14px rgba(139, 92, 246, 0.08);
}
.ht-alarm-badge {
  flex-shrink: 0;
  padding: 4px 10px;
  border-radius: 8px;
  color: white;
  font-size: 0.7rem;
  font-weight: 800;
  height: fit-content;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.ht-alarm-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ht-alarm-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.ht-alarm-type {
  font-size: 0.9rem;
  font-weight: 700;
  color: #1e293b;
}
.ht-alarm-status {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
}
.ht-alarm-status.moi {
  background: #fee2e2;
  color: #dc2626;
}
.ht-alarm-status.da_xem {
  background: #e0f2fe;
  color: #0369a1;
}
.ht-alarm-status.da_xu_ly {
  background: #dcfce7;
  color: #15803d;
}
.ht-alarm-status.bo_qua {
  background: #f1f5f9;
  color: #64748b;
}
.ht-alarm-meta {
  display: flex;
  gap: 14px;
  font-size: 0.75rem;
  color: #64748b;
  flex-wrap: wrap;
}
.ht-alarm-img {
  width: 100%;
  max-height: 160px;
  object-fit: cover;
  border-radius: 10px;
  margin-top: 4px;
  border: 1px solid #e2e8f0;
}
.ht-alarm-data {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 2px;
}
.ht-alarm-chip {
  padding: 2px 8px;
  background: #f1f5f9;
  border-radius: 6px;
  font-size: 0.72rem;
  color: #475569;
  font-family: monospace;
}
.ht-alarm-seen-btn {
  flex-shrink: 0;
  align-self: flex-start;
  padding: 5px 12px;
  border-radius: 8px;
  border: 1.5px solid #bbf7d0;
  background: #f0fdf4;
  color: #15803d;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}
.ht-alarm-seen-btn:hover {
  background: #dcfce7;
  border-color: #4ade80;
}

/* Modal Footer */
.ht-modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 22px;
  border-top: 1px solid #f1f5f9;
  flex-shrink: 0;
  gap: 10px;
  flex-wrap: wrap;
}
.ht-modal-footer-right {
  display: flex;
  gap: 8px;
}
.ht-modal-btn {
  padding: 8px 18px;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}
.ht-modal-btn.primary {
  background: linear-gradient(135deg, #8b5cf6, #7c3aed);
  color: white;
  box-shadow: 0 3px 12px rgba(139, 92, 246, 0.25);
}
.ht-modal-btn.primary:hover {
  background: linear-gradient(135deg, #7c3aed, #6d28d9);
  transform: translateY(-1px);
}
.ht-modal-btn.outline {
  background: white;
  color: #8b5cf6;
  border: 1.5px solid #8b5cf6;
}
.ht-modal-btn.outline:hover {
  background: #faf5ff;
}
.ht-modal-btn.secondary {
  background: #f1f5f9;
  color: #475569;
}
.ht-modal-btn.secondary:hover {
  background: #e2e8f0;
}

/* Modal Transition */
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.25s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-active .ht-modal-card,
.modal-fade-leave-active .ht-modal-card {
  transition: transform 0.25s ease;
}
.modal-fade-enter-from .ht-modal-card,
.modal-fade-leave-to .ht-modal-card {
  transform: scale(0.95) translateY(10px);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
  .ht-main {
    flex-direction: column;
  }
  .ht-sidebar {
    width: 100%;
    min-width: 0;
    max-height: 260px;
  }
  .ht-trip-list {
    flex-direction: row;
    overflow-x: auto;
    overflow-y: hidden;
    flex-wrap: nowrap;
  }
  .ht-trip-card {
    min-width: 220px;
    flex-shrink: 0;
  }
}
@media (max-width: 640px) {
  .ht-header {
    flex-direction: column;
    text-align: center;
    gap: 10px;
    padding: 14px;
  }
  .ht-info-bar {
    flex-direction: column;
  }
  .ht-info-item {
    border-right: none;
    border-bottom: 1px solid #f1f5f9;
  }
  .ht-info-item:last-child {
    border-bottom: none;
  }
  .ht-replay-bar {
    flex-direction: column;
    gap: 8px;
    align-items: flex-start;
  }
  .ht-replay-actions {
    flex-wrap: wrap;
  }
  .ht-modal-card {
    max-height: 95vh;
  }
}
</style>
