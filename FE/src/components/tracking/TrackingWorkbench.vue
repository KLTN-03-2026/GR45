<script setup>
import {
  computed, nextTick, onMounted, onUnmounted, reactive, ref, watch,
} from "vue";
import maplibregl from "@openmapvn/openmapvn-gl";
import "@openmapvn/openmapvn-gl/dist/maplibre-gl.css";
import axios from "axios";
import openmapApi from "@/api/openmap";
import BaseButton from "@/components/common/BaseButton.vue";
import BaseCard from "@/components/common/BaseCard.vue";
import BaseInput from "@/components/common/BaseInput.vue";
import BaseTable from "@/components/common/BaseTable.vue";
import { formatDateTime } from "@/utils/format";
import driverApi from "@/api/driverApi";
import adminApi from "@/api/adminApi";
import operatorApi from "@/api/operatorApi";

const props = defineProps({
  role: { type: String, required: true, validator: (v) => ["admin", "operator", "driver"].includes(v) },
  title: { type: String, default: "Tracking hành trình" },
  subtitle: { type: String, default: "" },
});

const roleTextMap = { admin: "Admin", operator: "Nhà xe", driver: "Tài xế" };
const roleIconMap = { admin: "🛡️", operator: "🏢", driver: "🚌" };

const query = reactive({ tripId: "", from: "", to: "", sample_seconds: "120", limit: "1000" });
const liveConfig = reactive({ autoRefresh: true, refreshSeconds: 15 });
const trackingForm = reactive({
  vi_do: "", kinh_do: "", van_toc: "", huong_di: "",
  do_chinh_xac_gps: "", trang_thai_tai_xe: "binh_thuong", thoi_diem_ghi: "",
});
const loading = reactive({ history: false, live: false, post: false, gps: false });
const historyData = reactive({ hien_tai: null, lich_su: [], meta: {} });
const liveData = reactive({ chuyen_xe: null, hien_tai: null, duong_di_gan_nhat: [], meta: {} });
const responseMessage = reactive({ type: "", text: "" });

const canPostTracking = computed(() => props.role === "driver");
const hasLiveEndpoint = computed(() => props.role === "admin" || props.role === "operator");

const mapContainer = ref(null);
let mapInstance = null;
let mapMarker = null;
let popupInstance = null;
let liveTimer = null;

const historyColumns = [
  { key: "id", label: "ID" },
  { key: "thoi_diem_ghi", label: "Thời điểm ghi" },
  { key: "vi_tri", label: "Vị trí" },
  { key: "van_toc", label: "Vận tốc" },
  { key: "trang_thai_tai_xe", label: "Trạng thái tài xế" },
];
const livePathColumns = [
  { key: "id", label: "ID điểm" },
  { key: "thoi_diem_ghi", label: "Thời điểm" },
  { key: "vi_tri", label: "Vị trí" },
];

const currentPoint = computed(() => liveData.hien_tai || historyData.hien_tai || null);
const historyRows = computed(() => historyData.lich_su.map((item) => ({
  ...item,
  thoi_diem_ghi: formatDateTime(item.thoi_diem_ghi),
  vi_tri: `${item.vi_do}, ${item.kinh_do}`,
  van_toc: `${Number(item.van_toc || 0).toFixed(1)} km/h`,
})));
const livePathRows = computed(() => liveData.duong_di_gan_nhat.map((item) => ({
  ...item,
  thoi_diem_ghi: formatDateTime(item.thoi_diem_ghi),
  vi_tri: `${item.vi_do}, ${item.kinh_do}`,
})));
const sampleInfoText = computed(() => {
  const returned = historyData.meta?.returned ?? historyRows.value.length;
  const ss = historyData.meta?.sample_seconds ?? query.sample_seconds;
  return `${returned} điểm, lấy mẫu mỗi ${ss || 0}s`;
});
const normalizedLastUpdateSeconds = computed(() => {
  const raw = Number(liveData.meta?.last_update_seconds);
  if (!Number.isFinite(raw)) return "--";
  return Math.max(0, Math.round(raw));
});

const parseCoordinateMapLibre = (latValue, lngValue) => {
  const lat = Number(latValue), lng = Number(lngValue);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
  return [lng, lat];
};

const buildPathPoints = () => {
  if (Array.isArray(liveData.duong_di_gan_nhat) && liveData.duong_di_gan_nhat.length)
    return liveData.duong_di_gan_nhat.map((i) => parseCoordinateMapLibre(i.vi_do, i.kinh_do)).filter(Boolean);
  if (Array.isArray(historyData.lich_su) && historyData.lich_su.length)
    return historyData.lich_su.map((i) => parseCoordinateMapLibre(i.vi_do, i.kinh_do)).filter(Boolean);
  return [];
};

const initializeMap = () => {
  if (!mapContainer.value || mapInstance) return;
  const apiKey = import.meta.env.VITE_OPENMAP_API_KEY;
  mapInstance = new maplibregl.Map({
    container: mapContainer.value,
    style: `https://maptiles.openmap.vn/styles/day-v1/style.json?apikey=${apiKey}`,
    center: [106.660172, 10.762622], zoom: 12,
  });
  mapInstance.addControl(new maplibregl.NavigationControl(), "top-right");
  mapInstance.on("load", () => {
    mapInstance.addSource("route", {
      type: "geojson",
      data: { type: "Feature", properties: {}, geometry: { type: "LineString", coordinates: [] } },
    });
    mapInstance.addLayer({
      id: "route", type: "line", source: "route",
      layout: { "line-join": "round", "line-cap": "round" },
      paint: { "line-color": "#6366f1", "line-width": 5, "line-opacity": 0.9 },
    });
  });
  const el = document.createElement("div");
  el.className = "bus-marker";
  el.innerHTML = `<div class="bus-icon-wrap"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16c0 .88.39 1.67 1 2.22V20a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-1h4v1a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-1.78A2.994 2.994 0 0 0 20 16V6c0-1.92-1.71-3.41-4-3.83V2h-2v.17C13.38 2.06 12.7 2 12 2s-1.38.06-2 .17V2H8v.17C5.71 2.59 4 4.08 4 6v10z"/><path d="M4 11h16"/><path d="M8 15h.01"/><path d="M16 15h.01"/><path d="M6 6h12"/></svg></div>`;
  mapMarker = new maplibregl.Marker({ element: el }).setLngLat([106.660172, 10.762622]).addTo(mapInstance);
  popupInstance = new maplibregl.Popup({ closeButton: false, closeOnClick: false, offset: 25 });
};

const renderTrackingMap = () => {
  if (!mapInstance || !mapMarker) return;
  const points = buildPathPoints();
  const current = currentPoint.value ? parseCoordinateMapLibre(currentPoint.value.vi_do, currentPoint.value.kinh_do) : null;
  const updateRoute = () => {
    const src = mapInstance.getSource("route");
    if (src) src.setData({ type: "Feature", properties: {}, geometry: { type: "LineString", coordinates: points } });
  };
  if (mapInstance.isStyleLoaded()) updateRoute(); else mapInstance.once("load", updateRoute);
  if (current) {
    mapMarker.setLngLat(current);
    if (currentPoint.value?.huong_di !== undefined && currentPoint.value?.huong_di !== null)
      mapMarker.setRotation(Number(currentPoint.value.huong_di));
    popupInstance.setLngLat(current).setHTML(`<strong>Vị trí hiện tại:</strong><br/>${current[1].toFixed(6)}, ${current[0].toFixed(6)}`).addTo(mapInstance);
  } else { popupInstance.remove(); }
  if (points.length > 1) {
    const bounds = new maplibregl.LngLatBounds(points[0], points[0]);
    for (const coord of points) bounds.extend(coord);
    mapInstance.fitBounds(bounds, { padding: 32, maxZoom: 16 });
    return;
  }
  if (current) mapInstance.flyTo({ center: current, zoom: 15 });
};

// Autocomplete
const searchState = reactive({ query: "", suggestions: [], isOpen: false, loading: false });
let searchTimeout = null;

const fetchAutocomplete = async (text) => {
  if (!text) { searchState.suggestions = []; return; }
  searchState.loading = true;
  try {
    const res = await openmapApi.autocomplete(text);
    if (res.data?.predictions) searchState.suggestions = res.data.predictions;
    else if (res.data?.features) searchState.suggestions = res.data.features.map((f) => ({
      description: f.properties.label || f.properties.name || f.properties.short_address,
      place_id: f.properties.id || f.properties.place_id,
    }));
  } catch (e) { console.error("Autocomplete error:", e); }
  finally { searchState.loading = false; }
};

const handleSearchInput = (e) => {
  searchState.query = e.target.value; searchState.isOpen = true;
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => fetchAutocomplete(searchState.query), 300);
};

const selectLocation = async (item) => {
  searchState.query = item.description; searchState.isOpen = false;
  try {
    const res = await openmapApi.getPlaceDetail(item.place_id);
    if (res.data?.result?.geometry) {
      const loc = res.data.result.geometry.location;
      const lng = Number(loc.lng), lat = Number(loc.lat);
      if (canPostTracking.value) { trackingForm.vi_do = String(lat); trackingForm.kinh_do = String(lng); }
      if (mapInstance && Number.isFinite(lng) && Number.isFinite(lat)) {
        mapInstance.flyTo({ center: [lng, lat], zoom: 16 });
        mapMarker.setLngLat([lng, lat]);
        popupInstance.setLngLat([lng, lat]).setHTML(`<strong>Đã chọn:</strong><br/>${lat.toFixed(6)}, ${lng.toFixed(6)}`).addTo(mapInstance);
      }
    } else { setMessage("Không lấy được tọa độ chi tiết.", "error"); }
  } catch (e) { setMessage("Chưa lấy được tọa độ chi tiết.", "error"); }
};

const closeSearchDropdown = () => { setTimeout(() => { searchState.isOpen = false; }, 200); };

const parseApiResponse = (rawResponse) => {
  const envelope = rawResponse?.data?.success !== undefined ? rawResponse.data : rawResponse;
  const businessData = envelope?.data && typeof envelope.data === "object" ? envelope.data : {};
  return { message: envelope?.message || rawResponse?.message || "", data: businessData };
};

const setMessage = (text, type = "success") => { responseMessage.text = text; responseMessage.type = type; };
const clearMessage = () => { responseMessage.text = ""; responseMessage.type = ""; };
const getTripId = () => Number(query.tripId || 0);
const getHistoryParams = () => {
  const params = {};
  if (query.from) params.from = query.from;
  if (query.to) params.to = query.to;
  if (query.sample_seconds !== "") params.sample_seconds = Number(query.sample_seconds);
  if (query.limit !== "") params.limit = Number(query.limit);
  return params;
};

const fetchTrackingHistory = async () => {
  const tripId = getTripId();
  if (!tripId) { setMessage("Vui lòng nhập ID chuyến xe hợp lệ.", "error"); return; }
  clearMessage(); loading.history = true;
  try {
    let raw;
    const params = getHistoryParams();
    if (props.role === "driver") raw = await driverApi.getTrackingHistory(tripId, params);
    else if (props.role === "admin") raw = await adminApi.getTripTrackingHistory(tripId, params);
    else raw = await operatorApi.getTripTrackingHistory(tripId, params);
    const parsed = parseApiResponse(raw);
    const data = parsed.data || {};
    historyData.hien_tai = data.hien_tai || null;
    historyData.lich_su = Array.isArray(data.lich_su) ? data.lich_su : [];
    historyData.meta = data.meta || {};
    setMessage(parsed.message || "Đã tải lịch sử tracking.");
  } catch (e) { setMessage(e?.response?.data?.message || "Không thể tải lịch sử.", "error"); }
  finally { loading.history = false; }
};

const fetchLiveTracking = async () => {
  if (!hasLiveEndpoint.value) return;
  const tripId = getTripId();
  if (!tripId) { setMessage("Vui lòng nhập ID chuyến xe.", "error"); return; }
  loading.live = true;
  try {
    const raw = props.role === "admin" ? await adminApi.getTripTrackingLive(tripId) : await operatorApi.getTripTrackingLive(tripId);
    const parsed = parseApiResponse(raw);
    const data = parsed.data || {};
    liveData.chuyen_xe = data.chuyen_xe || null;
    liveData.hien_tai = data.hien_tai || null;
    liveData.duong_di_gan_nhat = Array.isArray(data.duong_di_gan_nhat) ? data.duong_di_gan_nhat : [];
    liveData.meta = data.meta || {};
    if (!responseMessage.text || responseMessage.type === "error") setMessage(parsed.message || "Đã cập nhật live tracking.");
  } catch (e) { setMessage(e?.response?.data?.message || "Không thể tải live tracking.", "error"); }
  finally { loading.live = false; }
};

const getCurrentLocation = async () => {
  if (!navigator.geolocation) { setMessage("Trình duyệt không hỗ trợ GPS.", "error"); return; }
  loading.gps = true;
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      trackingForm.vi_do = String(pos.coords.latitude);
      trackingForm.kinh_do = String(pos.coords.longitude);
      trackingForm.do_chinh_xac_gps = String(pos.coords.accuracy || "0");
      if (pos.coords.speed != null) trackingForm.van_toc = String((pos.coords.speed * 3.6).toFixed(1));
      trackingForm.thoi_diem_ghi = new Date().toISOString();
      loading.gps = false; setMessage("Đã lấy vị trí GPS.");
    },
    () => { loading.gps = false; setMessage("Không thể lấy GPS.", "error"); },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
  );
};

const submitDriverTracking = async () => {
  const tripId = getTripId();
  if (!tripId) { setMessage("Vui lòng nhập ID chuyến xe.", "error"); return; }
  if (!trackingForm.vi_do || !trackingForm.kinh_do) { setMessage("Nhập đầy đủ vĩ độ và kinh độ.", "error"); return; }
  loading.post = true;
  try {
    const payload = { vi_do: Number(trackingForm.vi_do), kinh_do: Number(trackingForm.kinh_do) };
    if (trackingForm.van_toc !== "") payload.van_toc = Number(trackingForm.van_toc);
    if (trackingForm.huong_di !== "") payload.huong_di = Number(trackingForm.huong_di);
    if (trackingForm.do_chinh_xac_gps !== "") payload.do_chinh_xac_gps = Number(trackingForm.do_chinh_xac_gps);
    if (trackingForm.trang_thai_tai_xe) payload.trang_thai_tai_xe = trackingForm.trang_thai_tai_xe;
    if (trackingForm.thoi_diem_ghi) payload.thoi_diem_ghi = trackingForm.thoi_diem_ghi;
    const raw = await driverApi.postLiveTrackingLocation(tripId, payload);
    const parsed = parseApiResponse(raw);
    setMessage(parsed.message || "Đã gửi điểm tracking.");
    await fetchTrackingHistory();
  } catch (e) { setMessage(e?.response?.data?.message || "Không thể lưu tracking.", "error"); }
  finally { loading.post = false; }
};

const resetLiveTimer = () => {
  if (liveTimer) { clearInterval(liveTimer); liveTimer = null; }
  if (!hasLiveEndpoint.value || !liveConfig.autoRefresh || !getTripId()) return;
  liveTimer = setInterval(() => fetchLiveTracking(), Number(liveConfig.refreshSeconds) * 1000);
};

watch(() => [query.tripId, liveConfig.autoRefresh, liveConfig.refreshSeconds], () => resetLiveTimer());
watch(() => [liveData.hien_tai, liveData.duong_di_gan_nhat, historyData.hien_tai, historyData.lich_su],
  () => renderTrackingMap(), { deep: true });

onMounted(async () => { await nextTick(); initializeMap(); renderTrackingMap(); });
onUnmounted(() => {
  if (liveTimer) clearInterval(liveTimer);
  if (mapInstance) { mapInstance.remove(); mapInstance = null; mapMarker = null; popupInstance = null; }
});
</script>

<template>
  <div class="tracking-workbench">
    <!-- Header -->
    <header class="tracking-header">
      <div class="header-left">
        <div class="header-icon-badge">{{ roleIconMap[role] }}</div>
        <div>
          <h1 class="page-title">{{ title }}</h1>
          <p v-if="subtitle" class="page-subtitle">{{ subtitle }}</p>
        </div>
      </div>
      <span class="role-chip">
        <span class="role-dot"></span>
        {{ roleTextMap[role] }}
      </span>
    </header>

    <!-- Feedback -->
    <Transition name="fade-slide">
      <div v-if="responseMessage.text" :class="['feedback', responseMessage.type]">
        <span class="feedback-icon">{{ responseMessage.type === 'error' ? '⚠️' : '✅' }}</span>
        {{ responseMessage.text }}
        <button class="feedback-close" @click="clearMessage">×</button>
      </div>
    </Transition>

    <!-- Main Layout -->
    <div class="main-layout">
      <!-- Map Column -->
      <div class="map-column">
        <div class="map-card">
          <div class="map-card-header">
            <div class="map-title-group">
              <span class="map-live-dot"></span>
              <span class="map-card-title">Bản đồ Tracking</span>
            </div>
            <div class="map-search-container">
              <div class="search-icon-wrap">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
              </div>
              <input type="text" class="map-search-input" placeholder="Tìm địa điểm..." :value="searchState.query" @input="handleSearchInput" @focus="searchState.isOpen = true" @blur="closeSearchDropdown" />
              <ul v-if="searchState.isOpen && searchState.suggestions.length" class="map-search-dropdown">
                <li v-for="(item, idx) in searchState.suggestions" :key="idx" @mousedown.prevent="selectLocation(item)" class="map-search-item">
                  <svg class="search-item-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <span>{{ item.description }}</span>
                </li>
              </ul>
            </div>
          </div>
          <div class="map-wrapper">
            <div ref="mapContainer" class="tracking-map"></div>
          </div>
        </div>

        <!-- Current Position Card below map -->
        <div class="position-strip">
          <div class="pos-card" v-if="currentPoint">
            <div class="pos-item"><span class="pos-label">Vĩ độ</span><span class="pos-val">{{ currentPoint.vi_do }}</span></div>
            <div class="pos-item"><span class="pos-label">Kinh độ</span><span class="pos-val">{{ currentPoint.kinh_do }}</span></div>
            <div class="pos-item"><span class="pos-label">Vận tốc</span><span class="pos-val accent">{{ Number(currentPoint.van_toc || 0).toFixed(1) }} km/h</span></div>
            <div class="pos-item"><span class="pos-label">Hướng</span><span class="pos-val">{{ Number(currentPoint.huong_di || 0).toFixed(0) }}°</span></div>
            <div class="pos-item"><span class="pos-label">Cập nhật</span><span class="pos-val">{{ formatDateTime(currentPoint.thoi_diem_ghi) }}</span></div>
          </div>
          <div class="pos-card empty" v-else>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity="0.4"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span>Chưa có dữ liệu vị trí</span>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="sidebar-column">
        <!-- Query Card -->
        <div class="sidebar-card">
          <div class="sidebar-card-header">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            Truy vấn chuyến xe
          </div>
          <div class="sidebar-card-body">
            <div class="search-form-grid">
              <BaseInput v-model="query.tripId" label="ID Chuyến Xe" type="number" placeholder="VD: 88" class="full-width" />
              <BaseInput v-model="query.from" label="Từ ngày" type="datetime-local" />
              <BaseInput v-model="query.to" label="Đến ngày" type="datetime-local" />
              <BaseInput v-model="query.sample_seconds" label="Lấy mẫu (s)" type="number" placeholder="120" />
              <BaseInput v-model="query.limit" label="Giới hạn" type="number" placeholder="1000" />
            </div>
            <div class="tracking-action-bar">
              <div class="btn-group">
                <BaseButton class="action-btn history-btn" variant="secondary" :loading="loading.history" @click="fetchTrackingHistory">
                  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                  Lịch sử
                </BaseButton>
                <BaseButton v-if="hasLiveEndpoint" class="action-btn live-btn" variant="primary" :loading="loading.live" @click="fetchLiveTracking">
                  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48 0a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                  Live
                </BaseButton>
              </div>
              <div v-if="hasLiveEndpoint" class="live-refresh-toggle">
                <label class="modern-switch">
                  <input v-model="liveConfig.autoRefresh" type="checkbox" />
                  <span class="slider"></span>
                </label>
                <span class="switch-label-text">Tự động {{ liveConfig.refreshSeconds }}s</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Live Info -->
        <div v-if="hasLiveEndpoint" class="sidebar-card">
          <div class="sidebar-card-header">
            <span class="live-dot" :class="{ active: liveData.meta?.is_live }"></span>
            Thông tin Live
          </div>
          <div class="sidebar-card-body">
            <div v-if="liveData.meta && Object.keys(liveData.meta).length" class="info-grid">
              <div class="info-item">
                <span class="info-label">Truy cập</span>
                <span class="info-value">{{ liveData.meta.access_mode || "internal" }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Trạng thái</span>
                <span class="info-value" :class="liveData.meta.is_live ? 'highlight-green' : ''">
                  {{ liveData.meta.is_live ? "Đang live" : "Offline" }}
                </span>
              </div>
              <div class="info-item">
                <span class="info-label">Cập nhật</span>
                <span class="info-value">{{ normalizedLastUpdateSeconds }}s trước</span>
              </div>
              <div class="info-item">
                <span class="info-label">Chuyến</span>
                <span class="info-value">{{ liveData.chuyen_xe?.trang_thai || "--" }}</span>
              </div>
            </div>
            <div v-else class="empty-state"><span>Chưa tải live tracking.</span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Driver Tracking Form -->
    <div v-if="canPostTracking" class="driver-section">
      <div class="sidebar-card driver-card">
        <div class="sidebar-card-header driver-header">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
          Ghi điểm tracking
        </div>
        <div class="sidebar-card-body">
          <div class="driver-grid">
            <BaseInput v-model="trackingForm.vi_do" label="Vĩ độ" placeholder="10.762622" />
            <BaseInput v-model="trackingForm.kinh_do" label="Kinh độ" placeholder="106.660172" />
            <BaseInput v-model="trackingForm.van_toc" label="Vận tốc (km/h)" type="number" />
            <BaseInput v-model="trackingForm.huong_di" label="Hướng đi (°)" type="number" />
            <BaseInput v-model="trackingForm.do_chinh_xac_gps" label="Độ chính xác GPS (m)" type="number" />
            <div class="select-wrap">
              <label class="select-label">Trạng thái tài xế</label>
              <select v-model="trackingForm.trang_thai_tai_xe" class="status-select">
                <option value="binh_thuong">Bình thường</option>
                <option value="buon_ngu">Buồn ngủ</option>
                <option value="mat_tap_trung">Mất tập trung</option>
              </select>
            </div>
            <BaseInput v-model="trackingForm.thoi_diem_ghi" label="Thời điểm" type="datetime-local" />
          </div>
          <div class="actions-row">
            <BaseButton variant="outline" :loading="loading.gps" @click="getCurrentLocation">📡 Lấy GPS</BaseButton>
            <BaseButton variant="primary" :loading="loading.post" @click="submitDriverTracking">💾 Lưu tracking</BaseButton>
          </div>
        </div>
      </div>
    </div>

    <!-- Data Tables -->
    <div class="data-tables-section">
      <BaseCard class="modern-data-card" v-if="hasLiveEndpoint">
        <template #header>
          <div class="card-header-icon live-icon">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Quỹ đạo gần nhất
          </div>
        </template>
        <BaseTable :columns="livePathColumns" :data="livePathRows" :loading="loading.live" />
      </BaseCard>
      <BaseCard class="modern-data-card">
        <template #header>
          <div class="card-header-icon history-icon">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
            Nhật ký lịch sử
          </div>
        </template>
        <div class="table-toolbar"><span class="meta-badge">{{ sampleInfoText }}</span></div>
        <BaseTable :columns="historyColumns" :data="historyRows" :loading="loading.history" />
      </BaseCard>
    </div>
  </div>
</template>

<style scoped>
/* ===== BASE ===== */
.tracking-workbench {
  display: flex; flex-direction: column; gap: 1.25rem;
  font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
}

/* ===== HEADER ===== */
.tracking-header {
  display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;
  padding: 18px 24px; border-radius: 18px;
  background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #3730a3 100%);
  color: white; box-shadow: 0 8px 32px rgba(30, 27, 75, 0.25);
}
.header-left { display: flex; align-items: center; gap: 14px; }
.header-icon-badge {
  width: 46px; height: 46px; border-radius: 14px;
  background: rgba(255,255,255,0.12); backdrop-filter: blur(10px);
  display: flex; align-items: center; justify-content: center; font-size: 22px;
}
.page-title { margin: 0; font-size: 1.4rem; font-weight: 800; letter-spacing: -0.03em; }
.page-subtitle { margin: 3px 0 0; color: #c4b5fd; font-size: 0.85rem; }
.role-chip {
  display: flex; align-items: center; gap: 8px;
  background: rgba(255,255,255,0.12); backdrop-filter: blur(8px);
  color: #e0e7ff; border-radius: 999px; padding: 7px 16px;
  font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  border: 1px solid rgba(255,255,255,0.15);
}
.role-dot {
  width: 7px; height: 7px; border-radius: 50%; background: #4ade80;
  box-shadow: 0 0 6px rgba(74,222,128,0.6);
}

/* ===== FEEDBACK ===== */
.feedback {
  display: flex; align-items: center; gap: 10px;
  border-radius: 14px; padding: 12px 18px;
  font-weight: 600; font-size: 0.875rem;
  animation: fadeSlideIn 0.3s ease;
}
.feedback.success {
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
  color: #065f46; border: 1px solid #a7f3d0;
}
.feedback.error {
  background: linear-gradient(135deg, #fff1f2, #ffe4e6);
  color: #9f1239; border: 1px solid #fecdd3;
}
.feedback-icon { font-size: 16px; }
.feedback-close {
  margin-left: auto; background: none; border: none; font-size: 18px;
  cursor: pointer; opacity: 0.5; color: inherit; padding: 0 4px;
}
.feedback-close:hover { opacity: 1; }
.fade-slide-enter-active, .fade-slide-leave-active { transition: all 0.3s ease; }
.fade-slide-enter-from, .fade-slide-leave-to { opacity: 0; transform: translateY(-8px); }
@keyframes fadeSlideIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

/* ===== MAIN LAYOUT ===== */
.main-layout { display: grid; grid-template-columns: 1fr 380px; gap: 1.25rem; align-items: start; }
.map-column { min-width: 0; display: flex; flex-direction: column; gap: 12px; }

/* ===== MAP CARD ===== */
.map-card {
  background: white; border-radius: 18px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px -4px rgba(0,0,0,0.06);
  overflow: hidden; transition: box-shadow 0.3s;
}
.map-card:hover { box-shadow: 0 8px 32px -6px rgba(0,0,0,0.1); }
.map-card-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 18px; border-bottom: 1px solid #f1f5f9; gap: 12px;
}
.map-title-group { display: flex; align-items: center; gap: 10px; }
.map-live-dot {
  width: 8px; height: 8px; border-radius: 50%; background: #22c55e;
  box-shadow: 0 0 8px rgba(34,197,94,0.4);
  animation: pulse-dot 2s infinite;
}
.map-card-title { font-weight: 700; font-size: 0.95rem; color: #1e293b; }
.map-wrapper { position: relative; background: #e2e8f0; }
.tracking-map { height: 520px; width: 100%; }

/* Position strip below map */
.position-strip { width: 100%; }
.pos-card {
  display: flex; flex-wrap: wrap; gap: 0; padding: 0;
  background: white; border-radius: 16px;
  border: 1px solid #e2e8f0; box-shadow: 0 2px 10px rgba(0,0,0,0.04);
  overflow: hidden;
}
.pos-card.empty {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 16px; color: #94a3b8; font-size: 0.85rem;
}
.pos-item {
  flex: 1; min-width: 120px; padding: 12px 16px;
  border-right: 1px solid #f1f5f9; display: flex; flex-direction: column; gap: 3px;
}
.pos-item:last-child { border-right: none; }
.pos-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; font-weight: 700; }
.pos-val { font-size: 0.88rem; color: #1e293b; font-weight: 600; font-variant-numeric: tabular-nums; }
.pos-val.accent { color: #6366f1; font-weight: 700; }

/* ===== SIDEBAR ===== */
.sidebar-column { display: flex; flex-direction: column; gap: 12px; min-width: 0; }
.sidebar-card {
  background: white; border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 10px rgba(0,0,0,0.04);
  overflow: hidden; transition: box-shadow 0.3s, transform 0.2s;
}
.sidebar-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.07); }
.sidebar-card-header {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 16px; font-weight: 700; font-size: 0.85rem; color: #334155;
  border-bottom: 1px solid #f1f5f9;
  background: linear-gradient(to bottom, #fafbfc, #ffffff);
}
.sidebar-card-body { padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; }

/* Search form */
.search-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.full-width { grid-column: 1 / -1; }
.tracking-action-bar { margin-top: 8px; display: flex; flex-direction: column; gap: 10px; }
.btn-group { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.action-btn {
  display: flex; align-items: center; justify-content: center; gap: 6px;
  padding: 9px; border-radius: 10px; font-weight: 700; font-size: 0.82rem;
  transition: all 0.2s ease;
}
.history-btn { background: #f8fafc; color: #334155; border: 1px solid #e2e8f0; }
.history-btn:hover { background: #f1f5f9; border-color: #cbd5e1; }
.live-btn {
  background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; border: none;
  box-shadow: 0 4px 14px rgba(99,102,241,0.3);
}
.live-btn:hover {
  background: linear-gradient(135deg, #4f46e5, #4338ca);
  box-shadow: 0 6px 18px rgba(99,102,241,0.4); transform: translateY(-1px);
}
.live-refresh-toggle {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 8px 12px; background: #f8fafc; border-radius: 10px; border: 1px dashed #e2e8f0;
}
.modern-switch { position: relative; display: inline-block; width: 34px; height: 18px; }
.modern-switch input { opacity: 0; width: 0; height: 0; }
.slider {
  position: absolute; cursor: pointer; inset: 0;
  background: #cbd5e1; transition: 0.35s; border-radius: 34px;
}
.slider:before {
  position: absolute; content: ""; height: 14px; width: 14px;
  left: 2px; bottom: 2px; background: white; transition: 0.35s; border-radius: 50%;
  box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.modern-switch input:checked + .slider { background: linear-gradient(135deg, #22c55e, #16a34a); }
.modern-switch input:checked + .slider:before { transform: translateX(16px); }
.switch-label-text { font-size: 0.78rem; color: #64748b; font-weight: 600; }

/* ===== INFO GRID ===== */
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.info-item { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; background: #f8fafc; border-radius: 10px; }
.info-item.full-width { grid-column: 1 / -1; }
.info-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; font-weight: 700; }
.info-value { font-size: 0.85rem; color: #1e293b; font-weight: 600; font-variant-numeric: tabular-nums; }
.info-value.highlight { color: #6366f1; font-weight: 700; }
.info-value.highlight-green { color: #059669; font-weight: 700; }

/* Live dot */
.live-dot { width: 8px; height: 8px; border-radius: 50%; background: #94a3b8; flex-shrink: 0; }
.live-dot.active { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,0.25); animation: pulse-dot 2s infinite; }
@keyframes pulse-dot { 0%,100% { box-shadow: 0 0 0 3px rgba(34,197,94,0.25); } 50% { box-shadow: 0 0 0 6px rgba(34,197,94,0.1); } }

/* Empty state */
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 20px 0; color: #94a3b8; font-size: 0.82rem; }

/* ===== DRIVER SECTION ===== */
.driver-section { width: 100%; }
.driver-card { border-left: 4px solid #6366f1; }
.driver-header { background: linear-gradient(to right, #f5f3ff, #ffffff) !important; color: #4f46e5 !important; }
.driver-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; }
.actions-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
.select-wrap { display: flex; flex-direction: column; gap: 4px; }
.select-label { font-size: 0.82rem; font-weight: 600; color: #475569; }
.status-select {
  width: 100%; border: 1px solid #d1d5db; border-radius: 10px; min-height: 40px;
  padding: 8px 12px; color: #1f2937; font-size: 0.875rem; transition: all 0.2s;
  background: white;
}
.status-select:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }

/* ===== TABLES ===== */
.data-tables-section { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.modern-data-card {
  border-radius: 16px !important; border: 1px solid #e2e8f0 !important;
  box-shadow: 0 2px 10px rgba(0,0,0,0.04) !important; overflow: hidden;
  background: white !important;
}
.card-header-icon {
  display: flex; align-items: center; gap: 8px;
  font-weight: 700; font-size: 0.95rem;
}
.card-header-icon.live-icon { color: #6366f1; }
.card-header-icon.history-icon { color: #8b5cf6; }
.table-toolbar { padding: 0 18px 10px 18px; border-bottom: 1px solid #f1f5f9; }
.meta-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 12px; background: linear-gradient(135deg, #f5f3ff, #ede9fe);
  color: #6366f1; border-radius: 20px; font-size: 0.75rem; font-weight: 700;
}

/* ===== SEARCH ===== */
.map-search-container { position: relative; flex: 1; max-width: 320px; min-width: 160px; }
.search-icon-wrap {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  color: #94a3b8; pointer-events: none; z-index: 1;
}
.map-search-input {
  width: 100%; padding: 8px 12px 8px 34px;
  border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 0.82rem;
  outline: none; transition: all 0.2s; color: #1e293b; background: #f8fafc;
}
.map-search-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); background: white; }
.map-search-dropdown {
  position: absolute; top: calc(100% + 6px); left: 0; right: 0;
  background: white; border: 1px solid #e2e8f0; border-radius: 14px;
  box-shadow: 0 12px 40px -8px rgba(0,0,0,0.15); max-height: 260px;
  overflow-y: auto; z-index: 1000; padding: 6px; list-style: none; margin: 0;
}
.map-search-item {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 10px 12px; cursor: pointer; font-size: 0.82rem;
  color: #475569; border-radius: 10px; transition: all 0.15s; line-height: 1.4;
}
.map-search-item:hover { background: #f5f3ff; color: #4f46e5; }
.search-item-icon { flex-shrink: 0; margin-top: 2px; color: #94a3b8; }
.map-search-item:hover .search-item-icon { color: #6366f1; }

/* ===== BUS MARKER ===== */
.bus-marker {
  display: flex; align-items: center; justify-content: center;
  width: 40px; height: 40px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  border-radius: 50%; border: 3px solid white;
  box-shadow: 0 0 16px rgba(99,102,241,0.4), 0 4px 10px rgba(0,0,0,0.2);
  cursor: pointer; transition: transform 0.2s, background 0.3s;
}
.bus-marker:hover { background: linear-gradient(135deg, #4f46e5, #4338ca); transform: scale(1.1); }
.bus-icon-wrap { display: flex; align-items: center; justify-content: center; color: white; }

/* MapLibre overrides */
:deep(.maplibregl-canvas-container) { font-family: 'Inter', system-ui, sans-serif; }
:deep(.maplibregl-popup-content) { border-radius: 12px; padding: 10px 14px; font-size: 0.82rem; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
:deep(.maplibregl-ctrl-group) { border-radius: 12px !important; box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important; }
:deep(.maplibregl-ctrl-group button) { border-radius: 0 !important; }

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
  .main-layout { grid-template-columns: 1fr; }
  .data-tables-section { grid-template-columns: 1fr; }
  .tracking-map { height: 400px; }
}
@media (max-width: 768px) {
  .tracking-header { padding: 14px 18px; }
  .page-title { font-size: 1.2rem; }
  .tracking-map { height: 300px; }
  .info-grid { grid-template-columns: 1fr; }
  .driver-grid { grid-template-columns: 1fr 1fr; }
  .map-search-container { max-width: 200px; min-width: 130px; }
  .pos-card { flex-direction: column; }
  .pos-item { border-right: none; border-bottom: 1px solid #f1f5f9; }
  .pos-item:last-child { border-bottom: none; }
}
@media (max-width: 480px) {
  .driver-grid { grid-template-columns: 1fr; }
  .map-card-header { flex-direction: column; align-items: flex-start; }
  .map-search-container { max-width: 100%; width: 100%; }
  .tracking-header { flex-direction: column; text-align: center; align-items: center; }
}
</style>
