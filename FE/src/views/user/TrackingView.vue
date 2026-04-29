<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, nextTick } from 'vue';
import maplibregl from "@openmapvn/openmapvn-gl";
import "@openmapvn/openmapvn-gl/dist/maplibre-gl.css";
import clientApi from '@/api/clientApi.js';

// --- STATE ---
const phone = ref('');
const trips = ref([]);
const selectedTrip = ref(null);
const liveData = ref(null);
const uiState = ref('idle'); // idle | loading | results | tracking | not-found | error
const errorMsg = ref('');
const mapContainer = ref(null);
let map = null;
let busMarker = null;
let pollingId = null;

// --- LOOKUP ---
const lookupTrips = async () => {
  if (!phone.value || phone.value.trim().length < 8) {
    errorMsg.value = 'Vui lòng nhập số điện thoại hợp lệ.';
    uiState.value = 'error';
    return;
  }
  uiState.value = 'loading';
  errorMsg.value = '';
  try {
    const res = await clientApi.lookupTripsByPhone({ so_dien_thoai: phone.value.trim() });
    const data = res.data?.data || res.data || [];
    trips.value = Array.isArray(data) ? data : [];
    if (trips.value.length === 0) {
      uiState.value = 'not-found';
    } else {
      uiState.value = 'results';
    }
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Có lỗi xảy ra khi tra cứu.';
    uiState.value = 'error';
  }
};

// --- SELECT TRIP & INIT MAP ---
const selectTrip = async (trip) => {
  selectedTrip.value = trip;
  uiState.value = 'tracking';
  await nextTick();
  initMap();
  await fetchLive();
  startPolling();
};

const goBack = () => {
  stopPolling();
  selectedTrip.value = null;
  liveData.value = null;
  if (busMarker) { busMarker.remove(); busMarker = null; }
  if (map) { map.remove(); map = null; }
  uiState.value = 'results';
};

const goHome = () => {
  stopPolling();
  selectedTrip.value = null;
  liveData.value = null;
  trips.value = [];
  if (busMarker) { busMarker.remove(); busMarker = null; }
  if (map) { map.remove(); map = null; }
  uiState.value = 'idle';
};

// --- MAP ---
const initMap = () => {
  if (!mapContainer.value || map) return;
  const apiKey = import.meta.env.VITE_OPENMAP_API_KEY;
  map = new maplibregl.Map({
    container: mapContainer.value,
    style: `https://maptiles.openmap.vn/styles/day-v1/style.json?apikey=${apiKey}`,
    center: [108.206, 16.047], // [lng, lat]
    zoom: 6,
  });
  
  // Navigation control (zoom and compass)
  map.addControl(new maplibregl.NavigationControl(), 'top-right');

  map.on("load", () => {
    map.addSource("live-route", {
      type: "geojson",
      data: { type: "Feature", properties: {}, geometry: { type: "LineString", coordinates: [] } },
    });
    map.addLayer({
      id: "live-route",
      type: "line",
      source: "live-route",
      layout: { "line-join": "round", "line-cap": "round" },
      paint: { "line-color": "#3b82f6", "line-width": 4, "line-opacity": 0.8 },
    });
  });
};

// --- LIVE DATA ---
const fetchLive = async () => {
  if (!selectedTrip.value) return;
  try {
    const res = await clientApi.getLiveTrackingPublic(selectedTrip.value.id, {
      so_dien_thoai: phone.value.trim(),
    });
    liveData.value = res.data?.data || res.data || null;
    updateMap();
  } catch (e) {
    console.error('Lỗi tải live tracking:', e);
  }
};

const createBusMarkerElement = (isLive = true) => {
  const el = document.createElement("div");
  el.className = "live-bus-marker";
  const color = isLive ? "#3b82f6" : "#94a3b8";
  const shadow = isLive ? "rgba(59,130,246,0.5)" : "rgba(148,163,184,0.3)";
  el.innerHTML = `<div style="background:linear-gradient(135deg,${color} 0%,${isLive ? '#1e40af' : '#64748b'} 100%);width:40px;height:40px;border-radius:50%;border:3px solid rgba(255,255,255,0.95);box-shadow:0 0 18px ${shadow},0 4px 10px rgba(0,0,0,0.25);display:flex;align-items:center;justify-content:center;color:white;font-size:22px;transition:all 0.3s"><span class="material-symbols-outlined">directions_bus</span></div>`;
  return el;
};

const updateMap = () => {
  if (!map || !liveData.value) return;
  const cur = liveData.value.hien_tai;
  const isLive = liveData.value.meta?.is_live ?? true;
  
  if (cur && cur.vi_do && cur.kinh_do) {
    const lat = parseFloat(cur.vi_do);
    const lng = parseFloat(cur.kinh_do);
    if (!busMarker) {
      busMarker = new maplibregl.Marker({ element: createBusMarkerElement(isLive) })
        .setLngLat([lng, lat])
        .addTo(map);
      map.flyTo({ center: [lng, lat], zoom: 14 });
    } else {
      busMarker.setLngLat([lng, lat]);
      const newEl = createBusMarkerElement(isLive);
      busMarker.getElement().innerHTML = newEl.innerHTML;
    }
  }
  
  // Trail line
  const trail = liveData.value.duong_di_gan_nhat;
  if (Array.isArray(trail) && trail.length > 1) {
    const coords = trail.map(p => {
      const pLng = parseFloat(p.kinh_do);
      const pLat = parseFloat(p.vi_do);
      return [pLng, pLat];
    });
    const src = map.getSource("live-route");
    if (src) src.setData({ type: "Feature", properties: {}, geometry: { type: "LineString", coordinates: coords } });
  }
};

const startPolling = () => { stopPolling(); pollingId = setInterval(fetchLive, 15000); };
const stopPolling = () => { if (pollingId) { clearInterval(pollingId); pollingId = null; } };

const formatTime = (seconds) => {
  if (!seconds && seconds !== 0) return '—';
  if (seconds < 60) return `${Math.round(seconds)}s trước`;
  if (seconds < 3600) return `${Math.round(seconds / 60)} phút trước`;
  return `${Math.round(seconds / 3600)}h trước`;
};

onMounted(() => {
  // No need to inject leaflet anymore
});

onBeforeUnmount(() => {
  stopPolling();
  if (map) { map.remove(); map = null; }
});
</script>

<template>
  <div class="tracking-page">
    <!-- HERO -->
    <div class="tracking-hero">
      <div class="tracking-hero__inner">
        <span class="material-symbols-outlined tracking-hero__icon">share_location</span>
        <h1 class="tracking-hero__title">Theo dõi chuyến xe</h1>
        <p class="tracking-hero__desc">Nhập số điện thoại khách hàng để theo dõi chuyến xe theo thời gian thực</p>
      </div>
    </div>

    <div class="tracking-container">
      <!-- SEARCH FORM -->
      <div v-if="uiState !== 'tracking'" class="tracking-search-card">
        <div class="tracking-search-form">
          <div class="tracking-input-group">
            <span class="material-symbols-outlined tracking-input-icon">phone</span>
            <input
              v-model="phone"
              type="tel"
              placeholder="Nhập số điện thoại khách hàng..."
              class="tracking-input"
              @keyup.enter="lookupTrips"
            />
          </div>
          <button @click="lookupTrips" class="tracking-search-btn" :disabled="uiState === 'loading'">
            <span class="material-symbols-outlined">{{ uiState === 'loading' ? 'hourglass_empty' : 'search' }}</span>
            {{ uiState === 'loading' ? 'Đang tra cứu...' : 'Tra cứu' }}
          </button>
        </div>

        <!-- ERROR -->
        <div v-if="uiState === 'error'" class="tracking-alert tracking-alert--error">
          <span class="material-symbols-outlined">error</span>
          <span>{{ errorMsg }}</span>
        </div>

        <!-- NOT FOUND -->
        <div v-if="uiState === 'not-found'" class="tracking-alert tracking-alert--warning">
          <span class="material-symbols-outlined">info</span>
          <div>
            <strong>Không tìm thấy chuyến xe đang hoạt động</strong>
            <p>Không có chuyến xe nào đang di chuyển với số điện thoại <strong>{{ phone }}</strong>. Vui lòng kiểm tra lại SĐT hoặc chuyến xe có thể chưa khởi hành.</p>
          </div>
        </div>

        <!-- RESULTS -->
        <div v-if="uiState === 'results' && trips.length" class="tracking-results">
          <h3 class="tracking-results__title">
            <span class="material-symbols-outlined">directions_bus</span>
            Tìm thấy {{ trips.length }} chuyến xe đang di chuyển
          </h3>
          <div class="tracking-trip-list">
            <button v-for="trip in trips" :key="trip.id" class="tracking-trip-card" @click="selectTrip(trip)">
              <div class="tracking-trip-card__header">
                <span class="tracking-trip-card__route">
                  {{ trip.tuyen_duong?.diem_bat_dau || '?' }} → {{ trip.tuyen_duong?.diem_ket_thuc || '?' }}
                </span>
                <span class="tracking-trip-card__live" :class="{'tracking-trip-card__live--on': trip.is_live}">
                  <span class="tracking-trip-card__dot"></span>
                  {{ trip.is_live ? 'LIVE' : 'Chờ tín hiệu' }}
                </span>
              </div>
              <div class="tracking-trip-card__body">
                <div v-if="trip.tuyen_duong?.ten_tuyen_duong" class="tracking-trip-card__info">
                  <span class="material-symbols-outlined">route</span>
                  {{ trip.tuyen_duong.ten_tuyen_duong }}
                </div>
                <div v-if="trip.nha_xe?.ten_nha_xe" class="tracking-trip-card__info">
                  <span class="material-symbols-outlined">business</span>
                  {{ trip.nha_xe.ten_nha_xe }}
                </div>
                <div v-if="trip.xe?.bien_so" class="tracking-trip-card__info">
                  <span class="material-symbols-outlined">directions_bus</span>
                  {{ trip.xe.ten_xe || '' }} — {{ trip.xe.bien_so }}
                </div>
                <div class="tracking-trip-card__info">
                  <span class="material-symbols-outlined">schedule</span>
                  Khởi hành: {{ trip.gio_khoi_hanh }}
                </div>
              </div>
              <div class="tracking-trip-card__action">
                <span class="material-symbols-outlined">arrow_forward</span>
                Xem trực tiếp
              </div>
            </button>
          </div>
        </div>

        <!-- IDLE INFO -->
        <div v-if="uiState === 'idle'" class="tracking-info-cards">
          <div class="tracking-info-card">
            <span class="material-symbols-outlined">phone_android</span>
            <h4>Nhập SĐT</h4>
            <p>Số điện thoại đã dùng để đặt vé xe</p>
          </div>
          <div class="tracking-info-card">
            <span class="material-symbols-outlined">search</span>
            <h4>Tra cứu</h4>
            <p>Hệ thống sẽ tìm chuyến xe đang di chuyển</p>
          </div>
          <div class="tracking-info-card">
            <span class="material-symbols-outlined">map</span>
            <h4>Theo dõi</h4>
            <p>Xem vị trí xe trực tiếp trên bản đồ</p>
          </div>
        </div>
      </div>

      <!-- TRACKING MAP VIEW -->
      <div v-if="uiState === 'tracking'" class="tracking-live-section">
        <div class="tracking-live-header">
          <button @click="goBack" class="tracking-back-btn">
            <span class="material-symbols-outlined">arrow_back</span>
            Quay lại
          </button>
          <div class="tracking-live-title">
            <h2>{{ selectedTrip?.tuyen_duong?.diem_bat_dau }} → {{ selectedTrip?.tuyen_duong?.diem_ket_thuc }}</h2>
            <span class="tracking-live-badge" :class="{'tracking-live-badge--on': liveData?.meta?.is_live}">
              <span class="tracking-live-badge__dot"></span>
              {{ liveData?.meta?.is_live ? 'LIVE' : 'Chờ tín hiệu' }}
            </span>
          </div>
          <button @click="goHome" class="tracking-home-btn">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="tracking-live-body">
          <!-- Info Panel -->
          <div class="tracking-info-panel">
            <div class="tracking-info-panel__item">
              <span class="material-symbols-outlined">route</span>
              <div>
                <label>Tuyến đường</label>
                <span>{{ selectedTrip?.tuyen_duong?.ten_tuyen_duong || '—' }}</span>
              </div>
            </div>
            <div class="tracking-info-panel__item">
              <span class="material-symbols-outlined">business</span>
              <div>
                <label>Nhà xe</label>
                <span>{{ selectedTrip?.nha_xe?.ten_nha_xe || '—' }}</span>
              </div>
            </div>
            <div class="tracking-info-panel__item">
              <span class="material-symbols-outlined">directions_bus</span>
              <div>
                <label>Xe</label>
                <span>{{ selectedTrip?.xe?.bien_so || '—' }}</span>
              </div>
            </div>
            <div class="tracking-info-panel__item">
              <span class="material-symbols-outlined">speed</span>
              <div>
                <label>Vận tốc</label>
                <span>{{ liveData?.hien_tai?.van_toc ? Math.round(liveData.hien_tai.van_toc) + ' km/h' : '—' }}</span>
              </div>
            </div>
            <div class="tracking-info-panel__item">
              <span class="material-symbols-outlined">update</span>
              <div>
                <label>Cập nhật</label>
                <span>{{ formatTime(liveData?.meta?.last_update_seconds) }}</span>
              </div>
            </div>
          </div>

          <!-- Map -->
          <div ref="mapContainer" class="tracking-map"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tracking-page { font-family: 'Manrope', 'Inter', system-ui, sans-serif; padding-bottom: 3rem; }

/* Hero */
.tracking-hero {
  background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 50%, #3b82f6 100%);
  padding: 3rem 1.5rem 4rem;
  text-align: center;
  color: #fff;
}
.tracking-hero__inner { max-width: 600px; margin: 0 auto; }
.tracking-hero__icon { font-size: 48px; opacity: 0.85; margin-bottom: 0.5rem; display: block; }
.tracking-hero__title { font-size: 2rem; font-weight: 800; margin: 0 0 0.5rem; }
.tracking-hero__desc { font-size: 1.05rem; opacity: 0.85; margin: 0; line-height: 1.5; }

/* Container */
.tracking-container { max-width: 900px; margin: -2rem auto 0; padding: 0 1rem; position: relative; z-index: 10; }

/* Search Card */
.tracking-search-card {
  background: #fff; border-radius: 20px; padding: 1.5rem;
  box-shadow: 0 10px 40px rgba(15,23,42,0.1), 0 0 0 1px rgba(148,163,184,0.08);
}

.tracking-search-form { display: flex; gap: 0.75rem; }
.tracking-input-group {
  flex: 1; display: flex; align-items: center; gap: 0.5rem;
  background: #f1f5f9; border: 2px solid #e2e8f0; border-radius: 14px; padding: 0 1rem;
  transition: all 0.2s;
}
.tracking-input-group:focus-within { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
.tracking-input-icon { color: #64748b; font-size: 22px; }
.tracking-input {
  flex: 1; border: none; background: none; outline: none; font-size: 1rem; font-weight: 600;
  padding: 0.85rem 0; color: #1e293b; font-family: inherit;
}
.tracking-input::placeholder { color: #94a3b8; font-weight: 500; }

.tracking-search-btn {
  display: flex; align-items: center; gap: 0.5rem; padding: 0 1.75rem;
  background: linear-gradient(135deg, #3b82f6, #1e40af); color: #fff; border: none;
  border-radius: 14px; font-size: 1rem; font-weight: 700; cursor: pointer;
  transition: all 0.2s; white-space: nowrap; font-family: inherit;
  box-shadow: 0 4px 14px rgba(59,130,246,0.3);
}
.tracking-search-btn:hover { box-shadow: 0 6px 20px rgba(59,130,246,0.4); transform: translateY(-1px); }
.tracking-search-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* Alerts */
.tracking-alert {
  display: flex; align-items: flex-start; gap: 0.75rem; margin-top: 1rem;
  padding: 1rem 1.25rem; border-radius: 14px; font-size: 0.9rem; line-height: 1.5;
}
.tracking-alert p { margin: 0.25rem 0 0; font-weight: 500; }
.tracking-alert--error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
.tracking-alert--warning { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }

/* Results */
.tracking-results { margin-top: 1.5rem; }
.tracking-results__title {
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0 0 1rem;
}

.tracking-trip-list { display: flex; flex-direction: column; gap: 0.75rem; }

.tracking-trip-card {
  display: block; width: 100%; text-align: left; background: #fff; border: 2px solid #e2e8f0;
  border-radius: 16px; padding: 1rem 1.25rem; cursor: pointer; transition: all 0.2s;
  font-family: inherit;
}
.tracking-trip-card:hover { border-color: #3b82f6; box-shadow: 0 4px 20px rgba(59,130,246,0.12); transform: translateY(-2px); }

.tracking-trip-card__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
.tracking-trip-card__route { font-size: 1.1rem; font-weight: 800; color: #1e40af; }

.tracking-trip-card__live {
  display: flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.75rem;
  border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
  background: #f1f5f9; color: #64748b; letter-spacing: 0.05em;
}
.tracking-trip-card__live--on { background: #dcfce7; color: #16a34a; }
.tracking-trip-card__dot {
  width: 6px; height: 6px; border-radius: 50%; background: currentColor;
}
.tracking-trip-card__live--on .tracking-trip-card__dot { animation: pulse-dot 1.5s infinite; }

.tracking-trip-card__body { display: grid; grid-template-columns: 1fr 1fr; gap: 0.35rem 1rem; }
.tracking-trip-card__info {
  display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 500; color: #475569;
}
.tracking-trip-card__info .material-symbols-outlined { font-size: 16px; color: #94a3b8; }

.tracking-trip-card__action {
  display: flex; align-items: center; justify-content: flex-end; gap: 0.3rem;
  margin-top: 0.75rem; font-size: 0.85rem; font-weight: 700; color: #3b82f6;
}

/* Info cards (idle) */
.tracking-info-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1.5rem; }
.tracking-info-card {
  text-align: center; padding: 1.5rem 1rem; border-radius: 16px;
  background: #f8fafc; border: 1px solid #e2e8f0;
}
.tracking-info-card .material-symbols-outlined { font-size: 32px; color: #3b82f6; margin-bottom: 0.5rem; }
.tracking-info-card h4 { margin: 0 0 0.25rem; font-size: 0.95rem; font-weight: 700; color: #1e293b; }
.tracking-info-card p { margin: 0; font-size: 0.8rem; color: #64748b; line-height: 1.4; }

/* LIVE SECTION */
.tracking-live-section { background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 40px rgba(15,23,42,0.1); }

.tracking-live-header {
  display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem;
  border-bottom: 1px solid #e2e8f0; background: #f8fafc;
}
.tracking-back-btn, .tracking-home-btn {
  display: flex; align-items: center; gap: 0.3rem; padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0;
  background: #fff; border-radius: 10px; font-size: 0.85rem; font-weight: 600; color: #475569;
  cursor: pointer; transition: all 0.15s; font-family: inherit;
}
.tracking-back-btn:hover, .tracking-home-btn:hover { background: #f1f5f9; color: #1e293b; }
.tracking-live-title { flex: 1; }
.tracking-live-title h2 { margin: 0; font-size: 1.1rem; font-weight: 800; color: #1e293b; }

.tracking-live-badge {
  display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.15rem 0.6rem;
  border-radius: 20px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
  background: #f1f5f9; color: #64748b; margin-top: 0.2rem; letter-spacing: 0.05em;
}
.tracking-live-badge--on { background: #dcfce7; color: #16a34a; }
.tracking-live-badge__dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.tracking-live-badge--on .tracking-live-badge__dot { animation: pulse-dot 1.5s infinite; }

.tracking-live-body { display: flex; flex-direction: column; }

.tracking-info-panel {
  display: flex; flex-wrap: wrap; gap: 0.5rem; padding: 1rem 1.25rem;
  border-bottom: 1px solid #e2e8f0; background: #fff;
}
.tracking-info-panel__item {
  display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem;
  background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; min-width: 140px;
}
.tracking-info-panel__item .material-symbols-outlined { font-size: 20px; color: #3b82f6; }
.tracking-info-panel__item label { display: block; font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; }
.tracking-info-panel__item span:last-child { font-size: 0.85rem; font-weight: 600; color: #1e293b; }

.tracking-map { width: 100%; height: 500px; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; }

@keyframes pulse-dot { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }

/* MapLibre overrides */
:deep(.maplibregl-ctrl-group) { border-radius: 12px !important; box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important; }
:deep(.live-bus-marker) { background: transparent; border: none; }
:deep(.live-bus-marker > div) {
  animation: pulse-bus 2s infinite;
}
@keyframes pulse-bus { 0%,100% { box-shadow: 0 0 0 0 rgba(59,130,246,0.4); } 50% { box-shadow: 0 0 0 12px rgba(59,130,246,0); } }

@media (max-width: 640px) {
  .tracking-search-form { flex-direction: column; }
  .tracking-search-btn { justify-content: center; padding: 0.85rem; }
  .tracking-info-cards { grid-template-columns: 1fr; }
  .tracking-trip-card__body { grid-template-columns: 1fr; }
  .tracking-map { height: 350px; }
  .tracking-hero__title { font-size: 1.5rem; }
}
</style>
