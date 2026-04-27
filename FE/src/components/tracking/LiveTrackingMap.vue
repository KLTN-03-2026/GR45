<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from "vue";
import maplibregl from "@openmapvn/openmapvn-gl";
import "@openmapvn/openmapvn-gl/dist/maplibre-gl.css";
import adminApi from "@/api/adminApi";
import operatorApi from "@/api/operatorApi";
import { useTrackingChannel } from "@/composables/useTrackingChannel";
import { formatDateTime } from "@/utils/format";

const props = defineProps({
  role: { type: String, required: true, validator: (v) => ["admin", "operator"].includes(v) },
});

const api = computed(() => (props.role === "admin" ? adminApi : operatorApi));

// --- State ---
const trips = ref([]);
const selectedTripId = ref(null);
const searchQuery = ref("");
const loading = reactive({ trips: false, live: false });
const liveData = reactive({ chuyen_xe: null, hien_tai: null, duong_di_gan_nhat: [], meta: {} });

// Map
const mapContainer = ref(null);
let mapInstance = null;
const markers = new Map(); // tripId → marker instance
let routeSourceAdded = false;

// WebSocket
const { subscribe, unsubscribe, isSubscribed } = useTrackingChannel();

// Timer
let refreshTimer = null;

// --- Computed ---
const filteredTrips = computed(() => {
  if (!searchQuery.value) return trips.value;
  const q = searchQuery.value.toLowerCase();
  return trips.value.filter(
    (t) =>
      String(t.id).includes(q) ||
      (t.tuyen_duong?.ten_tuyen_duong || "").toLowerCase().includes(q) ||
      (t.xe?.bien_so || "").toLowerCase().includes(q) ||
      (t.tai_xe?.ho_ten || "").toLowerCase().includes(q)
  );
});

const selectedTrip = computed(() => trips.value.find((t) => t.id === selectedTripId.value) || null);

// --- Map ---
const createBusMarkerElement = (isLive = true) => {
  const el = document.createElement("div");
  el.className = "live-bus-marker";
  const color = isLive ? "#6366f1" : "#94a3b8";
  const shadow = isLive ? "rgba(99,102,241,0.5)" : "rgba(148,163,184,0.3)";
  el.innerHTML = `<div style="background:linear-gradient(135deg,${color} 0%,${isLive ? '#8b5cf6' : '#64748b'} 100%);width:38px;height:38px;border-radius:50%;border:3px solid rgba(255,255,255,0.95);box-shadow:0 0 18px ${shadow},0 4px 10px rgba(0,0,0,0.25);display:flex;align-items:center;justify-content:center;color:white;font-size:17px;transition:all 0.3s">🚌</div>`;
  return el;
};

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
    mapInstance.addSource("live-route", {
      type: "geojson",
      data: { type: "Feature", properties: {}, geometry: { type: "LineString", coordinates: [] } },
    });
    mapInstance.addLayer({
      id: "live-route",
      type: "line",
      source: "live-route",
      layout: { "line-join": "round", "line-cap": "round" },
      paint: { "line-color": "#6366f1", "line-width": 4, "line-opacity": 0.8 },
    });
    routeSourceAdded = true;
  });
};

const addOrUpdateMarker = (tripId, lat, lng, isLive, speed, label) => {
  if (!mapInstance || !Number.isFinite(lat) || !Number.isFinite(lng)) return;
  if (markers.has(tripId)) {
    markers.get(tripId).setLngLat([lng, lat]);
  } else {
    const popup = new maplibregl.Popup({ offset: 25, closeButton: false }).setHTML(
      `<strong>${label || "Chuyến #" + tripId}</strong><br/>Vận tốc: ${Number(speed || 0).toFixed(0)} km/h`
    );
    const marker = new maplibregl.Marker({ element: createBusMarkerElement(isLive) })
      .setLngLat([lng, lat])
      .setPopup(popup)
      .addTo(mapInstance);
    markers.set(tripId, marker);
  }
  // Update popup
  const marker = markers.get(tripId);
  if (marker) {
    const popup = marker.getPopup();
    if (popup) popup.setHTML(`<strong>${label || "Chuyến #" + tripId}</strong><br/>Vận tốc: ${Number(speed || 0).toFixed(0)} km/h`);
  }
};

const removeMarker = (tripId) => {
  if (markers.has(tripId)) {
    markers.get(tripId).remove();
    markers.delete(tripId);
  }
};

const updateRouteOnMap = (points) => {
  if (!mapInstance || !routeSourceAdded) return;
  const coords = points
    .map((p) => {
      const lng = Number(p.kinh_do), lat = Number(p.vi_do);
      return Number.isFinite(lng) && Number.isFinite(lat) ? [lng, lat] : null;
    })
    .filter(Boolean);
  const src = mapInstance.getSource("live-route");
  if (src) src.setData({ type: "Feature", properties: {}, geometry: { type: "LineString", coordinates: coords } });
};

const flyToTrip = (trip) => {
  if (!mapInstance) return;
  const lt = trip.last_tracking;
  if (lt && lt.vi_do && lt.kinh_do) {
    mapInstance.flyTo({ center: [Number(lt.kinh_do), Number(lt.vi_do)], zoom: 15, speed: 1.5 });
  }
};

// --- API ---
const fetchActiveTrips = async () => {
  loading.trips = true;
  try {
    const res = await api.value.getActiveTrips();
    const data = res?.data || res;
    trips.value = Array.isArray(data) ? data : (data?.data || []);
    // Render markers cho tất cả xe
    trips.value.forEach((trip) => {
      if (trip.last_tracking) {
        addOrUpdateMarker(
          trip.id,
          Number(trip.last_tracking.vi_do),
          Number(trip.last_tracking.kinh_do),
          trip.is_live,
          trip.last_tracking.van_toc,
          trip.tuyen_duong?.ten_tuyen_duong || `Chuyến #${trip.id}`
        );
      }
    });
    // Remove markers cho trips không còn active
    for (const [tripId] of markers) {
      if (!trips.value.find((t) => t.id === tripId)) removeMarker(tripId);
    }
  } catch (e) {
    console.error("Lỗi tải danh sách chuyến:", e);
  } finally {
    loading.trips = false;
  }
};

const fetchLiveDetail = async (tripId) => {
  loading.live = true;
  try {
    const res = await api.value.getTripTrackingLive(tripId);
    const data = res?.data || res;
    const payload = data?.data || data || {};
    liveData.chuyen_xe = payload.chuyen_xe || null;
    liveData.hien_tai = payload.hien_tai || null;
    liveData.duong_di_gan_nhat = Array.isArray(payload.duong_di_gan_nhat) ? payload.duong_di_gan_nhat : [];
    liveData.meta = payload.meta || {};
    // Vẽ đường đi gần nhất
    if (liveData.duong_di_gan_nhat.length) updateRouteOnMap(liveData.duong_di_gan_nhat);
  } catch (e) {
    console.error("Lỗi tải live detail:", e);
  } finally {
    loading.live = false;
  }
};

// --- Chọn chuyến ---
const selectTrip = (trip) => {
  // Nếu đang chọn lại → bỏ chọn
  if (selectedTripId.value === trip.id) {
    unsubscribe(trip.id);
    selectedTripId.value = null;
    liveData.hien_tai = null;
    liveData.duong_di_gan_nhat = [];
    liveData.meta = {};
    updateRouteOnMap([]);
    return;
  }

  // Bỏ subscribe trip cũ
  if (selectedTripId.value) unsubscribe(selectedTripId.value);

  selectedTripId.value = trip.id;
  flyToTrip(trip);
  fetchLiveDetail(trip.id);

  // Subscribe WebSocket
  subscribe(trip.id, (data) => {
    // Cập nhật marker
    addOrUpdateMarker(
      data.id_chuyen_xe,
      Number(data.vi_do),
      Number(data.kinh_do),
      true,
      data.van_toc,
      selectedTrip.value?.tuyen_duong?.ten_tuyen_duong || `Chuyến #${data.id_chuyen_xe}`
    );

    // Cập nhật live data
    liveData.hien_tai = data;
    liveData.meta.is_live = true;
    liveData.meta.last_update_seconds = 0;

    // Thêm điểm mới vào đường đi
    liveData.duong_di_gan_nhat.push(data);
    if (liveData.duong_di_gan_nhat.length > 50) liveData.duong_di_gan_nhat.shift();
    updateRouteOnMap(liveData.duong_di_gan_nhat);

    // Cập nhật trip trong danh sách
    const tripIdx = trips.value.findIndex((t) => t.id === data.id_chuyen_xe);
    if (tripIdx !== -1) {
      trips.value[tripIdx].last_tracking = data;
      trips.value[tripIdx].is_live = true;
      trips.value[tripIdx].last_update_seconds = 0;
    }

    // Pan map
    if (mapInstance) mapInstance.panTo([Number(data.kinh_do), Number(data.vi_do)]);
  });
};

// --- Lifecycle ---
onMounted(async () => {
  await nextTick();
  initMap();
  await fetchActiveTrips();
  // Auto-refresh mỗi 30s
  refreshTimer = setInterval(fetchActiveTrips, 30000);
});

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer);
  for (const [, marker] of markers) marker.remove();
  markers.clear();
  if (mapInstance) { mapInstance.remove(); mapInstance = null; }
});
</script>

<template>
  <div class="live-tracking-wrapper">
    <!-- Header -->
    <header class="lt-header">
      <div class="lt-header-left">
        <div class="lt-header-icon">📡</div>
        <div>
          <h1 class="lt-title">Live Tracking — Command Center</h1>
          <p class="lt-subtitle">Theo dõi vị trí xe đang chạy realtime qua WebSocket</p>
        </div>
      </div>
      <div class="lt-header-right">
        <div class="lt-live-badge" :class="{ active: trips.length > 0 }">
          <span class="lt-pulse"></span>
          <span>{{ trips.length }} chuyến đang chạy</span>
        </div>
      </div>
    </header>

    <!-- Main Layout -->
    <div class="lt-main">
      <!-- Sidebar -->
      <aside class="lt-sidebar">
        <div class="lt-search-box">
          <svg class="lt-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            class="lt-search-input"
            placeholder="Tìm chuyến xe, biển số..."
          />
        </div>

        <div class="lt-trip-list" v-if="!loading.trips">
          <div
            v-for="trip in filteredTrips"
            :key="trip.id"
            class="lt-trip-card"
            :class="{ selected: selectedTripId === trip.id, live: trip.is_live, offline: !trip.is_live }"
            @click="selectTrip(trip)"
          >
            <div class="lt-trip-top">
              <div class="lt-trip-status">
                <span class="lt-status-dot" :class="trip.is_live ? 'live' : 'offline'"></span>
                <span class="lt-status-text">{{ trip.is_live ? "Live" : "Offline" }}</span>
              </div>
              <span class="lt-trip-id">#{{ trip.id }}</span>
            </div>
            <div class="lt-trip-route">{{ trip.tuyen_duong?.ten_tuyen_duong || "—" }}</div>
            <div class="lt-trip-meta">
              <span v-if="trip.xe">🚌 {{ trip.xe.bien_so }}</span>
              <span v-if="trip.tai_xe">👤 {{ trip.tai_xe.ho_ten }}</span>
            </div>
            <div class="lt-trip-speed" v-if="trip.last_tracking">
              <span class="speed-value">{{ Number(trip.last_tracking.van_toc || 0).toFixed(0) }}</span>
              <span class="speed-unit">km/h</span>
            </div>
          </div>

          <div v-if="filteredTrips.length === 0" class="lt-empty">
            <span>🚫</span>
            <span>Không có chuyến xe nào đang chạy</span>
          </div>
        </div>

        <div v-else class="lt-loading-trips">
          <div class="lt-spinner"></div>
          <span>Đang tải...</span>
        </div>
      </aside>

      <!-- Map Area -->
      <div class="lt-map-area">
        <div ref="mapContainer" class="lt-map"></div>

        <!-- Info Bar -->
        <div class="lt-info-bar" v-if="liveData.hien_tai">
          <div class="lt-info-item">
            <span class="lt-info-label">Vĩ độ</span>
            <span class="lt-info-value">{{ Number(liveData.hien_tai.vi_do).toFixed(6) }}</span>
          </div>
          <div class="lt-info-item">
            <span class="lt-info-label">Kinh độ</span>
            <span class="lt-info-value">{{ Number(liveData.hien_tai.kinh_do).toFixed(6) }}</span>
          </div>
          <div class="lt-info-item">
            <span class="lt-info-label">Vận tốc</span>
            <span class="lt-info-value accent">{{ Number(liveData.hien_tai.van_toc || 0).toFixed(0) }} km/h</span>
          </div>
          <div class="lt-info-item">
            <span class="lt-info-label">Hướng đi</span>
            <span class="lt-info-value">{{ Number(liveData.hien_tai.huong_di || 0).toFixed(0) }}°</span>
          </div>
          <div class="lt-info-item">
            <span class="lt-info-label">Trạng thái</span>
            <span class="lt-info-value" :class="liveData.meta?.is_live ? 'live-text' : 'offline-text'">
              {{ liveData.meta?.is_live ? "🟢 Live" : "🔴 Offline" }}
            </span>
          </div>
          <div class="lt-info-item">
            <span class="lt-info-label">Cập nhật</span>
            <span class="lt-info-value">{{ formatDateTime(liveData.hien_tai.thoi_diem_ghi) }}</span>
          </div>
        </div>

        <div class="lt-info-bar empty" v-else-if="!selectedTripId">
          <span class="lt-info-hint">👈 Chọn chuyến xe bên trái để xem live tracking</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ===== WRAPPER ===== */
.live-tracking-wrapper {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 80px);
  font-family: "Inter", "Segoe UI", system-ui, sans-serif;
  gap: 1rem;
}

/* ===== HEADER ===== */
.lt-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-radius: 16px;
  background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #3730a3 100%);
  color: white;
  box-shadow: 0 6px 24px rgba(30, 27, 75, 0.25);
  flex-shrink: 0;
}
.lt-header-left { display: flex; align-items: center; gap: 14px; }
.lt-header-icon { font-size: 28px; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.12); border-radius: 14px; backdrop-filter: blur(8px); }
.lt-title { margin: 0; font-size: 1.35rem; font-weight: 800; letter-spacing: -0.03em; }
.lt-subtitle { margin: 3px 0 0; color: #c4b5fd; font-size: 0.82rem; }
.lt-header-right { display: flex; align-items: center; }
.lt-live-badge {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 16px; border-radius: 999px;
  background: rgba(255,255,255,0.1); backdrop-filter: blur(8px);
  font-size: 0.82rem; font-weight: 700; color: #e0e7ff;
  border: 1px solid rgba(255,255,255,0.15);
}
.lt-live-badge.active { border-color: rgba(34,197,94,0.4); }
.lt-pulse {
  width: 8px; height: 8px; border-radius: 50%; background: #94a3b8;
}
.lt-live-badge.active .lt-pulse {
  background: #22c55e;
  box-shadow: 0 0 8px rgba(34,197,94,0.6);
  animation: pulse-anim 2s infinite;
}
@keyframes pulse-anim {
  0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.4); }
  50% { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
}

/* ===== MAIN LAYOUT ===== */
.lt-main { display: flex; flex: 1; gap: 1rem; min-height: 0; }

/* ===== SIDEBAR ===== */
.lt-sidebar {
  width: 340px; min-width: 290px; flex-shrink: 0;
  background: white; border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px -4px rgba(0,0,0,0.06);
  display: flex; flex-direction: column;
  overflow: hidden;
}
.lt-search-box {
  position: relative; padding: 14px 14px 10px;
  border-bottom: 1px solid #f1f5f9;
}
.lt-search-icon { position: absolute; left: 26px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
.lt-search-input {
  width: 100%; padding: 10px 12px 10px 36px;
  border: 1.5px solid #e2e8f0; border-radius: 12px;
  font-size: 0.85rem; color: #1e293b; background: #f8fafc;
  outline: none; transition: all 0.2s;
}
.lt-search-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); background: white; }

.lt-trip-list { flex: 1; overflow-y: auto; padding: 8px; display: flex; flex-direction: column; gap: 6px; }

/* Trip Card */
.lt-trip-card {
  padding: 12px 14px; border-radius: 12px;
  border: 1.5px solid #e2e8f0;
  cursor: pointer; transition: all 0.2s ease;
  background: white; position: relative;
}
.lt-trip-card:hover { border-color: #c7d2fe; background: #fafaff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.08); }
.lt-trip-card.selected { border-color: #6366f1; background: linear-gradient(135deg, #f5f3ff, #ede9fe); box-shadow: 0 4px 16px rgba(99,102,241,0.15); }
.lt-trip-card.offline { opacity: 0.7; }

.lt-trip-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.lt-trip-status { display: flex; align-items: center; gap: 6px; }
.lt-status-dot { width: 7px; height: 7px; border-radius: 50%; }
.lt-status-dot.live { background: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,0.5); }
.lt-status-dot.offline { background: #94a3b8; }
.lt-status-text { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
.lt-trip-id { font-size: 0.72rem; font-weight: 700; color: #94a3b8; }

.lt-trip-route { font-size: 0.88rem; font-weight: 700; color: #1e293b; margin-bottom: 4px; line-height: 1.35; }
.lt-trip-meta { display: flex; gap: 12px; font-size: 0.75rem; color: #64748b; }
.lt-trip-speed {
  position: absolute; right: 14px; bottom: 12px;
  display: flex; align-items: baseline; gap: 2px;
}
.speed-value { font-size: 1.3rem; font-weight: 800; color: #6366f1; font-variant-numeric: tabular-nums; }
.speed-unit { font-size: 0.65rem; font-weight: 600; color: #94a3b8; }

.lt-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 8px; padding: 40px 20px; color: #94a3b8; font-size: 0.85rem; text-align: center;
}
.lt-loading-trips { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; padding: 40px; color: #64748b; font-size: 0.85rem; }
.lt-spinner {
  width: 28px; height: 28px; border: 3px solid #e2e8f0; border-top-color: #6366f1;
  border-radius: 50%; animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ===== MAP AREA ===== */
.lt-map-area {
  flex: 1; display: flex; flex-direction: column;
  border-radius: 16px; overflow: hidden;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px -4px rgba(0,0,0,0.06);
  background: white; min-width: 0;
}
.lt-map { flex: 1; width: 100%; min-height: 400px; }

/* Info Bar */
.lt-info-bar {
  display: flex; flex-wrap: wrap; gap: 0;
  border-top: 1px solid #f1f5f9;
  background: white; flex-shrink: 0;
}
.lt-info-bar.empty {
  justify-content: center; padding: 16px;
}
.lt-info-hint { color: #94a3b8; font-size: 0.85rem; font-weight: 500; }
.lt-info-item {
  flex: 1; min-width: 130px; padding: 12px 16px;
  border-right: 1px solid #f1f5f9;
  display: flex; flex-direction: column; gap: 2px;
}
.lt-info-item:last-child { border-right: none; }
.lt-info-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; font-weight: 700; }
.lt-info-value { font-size: 0.85rem; color: #1e293b; font-weight: 600; font-variant-numeric: tabular-nums; }
.lt-info-value.accent { color: #6366f1; font-weight: 700; }
.lt-info-value.live-text { color: #059669; }
.lt-info-value.offline-text { color: #dc2626; }

/* MapLibre overrides */
:deep(.maplibregl-popup-content) { border-radius: 12px; padding: 10px 14px; font-size: 0.82rem; box-shadow: 0 8px 24px rgba(0,0,0,0.12); font-family: "Inter", system-ui, sans-serif; }
:deep(.maplibregl-ctrl-group) { border-radius: 12px !important; box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important; }

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
  .lt-main { flex-direction: column; }
  .lt-sidebar { width: 100%; min-width: 0; max-height: 240px; }
  .lt-trip-list { flex-direction: row; overflow-x: auto; overflow-y: hidden; flex-wrap: nowrap; }
  .lt-trip-card { min-width: 220px; flex-shrink: 0; }
}
@media (max-width: 640px) {
  .lt-header { flex-direction: column; text-align: center; gap: 10px; padding: 14px; }
  .lt-title { font-size: 1.15rem; }
  .lt-info-bar { flex-direction: column; }
  .lt-info-item { border-right: none; border-bottom: 1px solid #f1f5f9; }
  .lt-info-item:last-child { border-bottom: none; }
}
</style>
