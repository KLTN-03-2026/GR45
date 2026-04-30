<script setup>
import { onMounted, ref, computed, onUnmounted, watch } from "vue";
import { useRoute } from "vue-router";
import {
  AlertTriangle,
  Video,
  MapPin,
  Truck,
  Bell,
  Route,
  Clock,
  Flag,
  Navigation,
  ChevronRight,
  Crosshair,
  Compass,
  Wifi,
  WifiOff,
  RefreshCw,
  CircleDot,
  Zap,
  Eye,
  EyeOff,
  Camera,
  ShieldAlert,
  ScanLine,
  Swords,
  Cigarette,
} from "lucide-vue-next";
import maplibregl from "@openmapvn/openmapvn-gl";
import "@openmapvn/openmapvn-gl/dist/maplibre-gl.css";
import driverApi from "@/api/driverApi.js";
import openmapApi from "@/api/openmap";
import { DrowsinessAgent } from "@/utils/drowsinessAgent.js";
import { ViolationDetectionAgent } from "@/utils/violationDetectionAgent.js";

const route = useRoute();

// --- State ---
const currentTrip = ref({
  id: null,
  ten_tuyen_duong: "Đang tải...",
  bien_so: "---",
  trang_thai: "loading",
  van_toc: 0,
  quang_duong: "0",
  diem_bat_dau: "---",
  diem_ket_thuc: "---",
  gio_khoi_hanh: "--:--",
  tram_dungs: [],
});

const alerts = ref([]);
const isFetchingTrip = ref(true);
const isStartingTrip = ref(false);
const isSendingSOS = ref(false);

const sendSOS = async () => {
  if (!currentTrip.value.id || isSendingSOS.value) return;

  if (
    !confirm(
      "XÁC NHẬN GỬI TÍN HIỆU SOS KHẨN CẤP?\nTín hiệu sẽ được gửi ngay lập tức về trung tâm điều hành nhà xe."
    )
  ) {
    return;
  }

  isSendingSOS.value = true;
  try {
    const res = await driverApi.postSOS({
      id_chuyen_xe: currentTrip.value.id,
      vi_do_luc_bao: currentPosition.value.lat,
      kinh_do_luc_bao: currentPosition.value.lng,
    });
    if (res.success || res.data) {
      alerts.value.unshift({
        id: Date.now(),
        type: "danger",
        message: "🆘 TÍN HIỆU SOS ĐÃ ĐƯỢC GỬI! Trung tâm đang tiếp nhận xử lý.",
        time: new Date().toLocaleTimeString("vi-VN"),
      });
      // Tạo hiệu ứng rung nếu thiết bị hỗ trợ
      if ("vibrate" in navigator) {
        navigator.vibrate([500, 200, 500, 200, 500]);
      }
    }
  } catch (error) {
    console.error("Lỗi khi gửi SOS:", error);
    alerts.value.unshift({
      id: Date.now(),
      type: "danger",
      message: "Lỗi khi gửi SOS: " + (error.response?.data?.message || error.message),
      time: new Date().toLocaleTimeString("vi-VN"),
    });
  } finally {
    isSendingSOS.value = false;
  }
};

const batDauDiChuyen = async () => {
  if (!currentTrip.value.id) return;
  isStartingTrip.value = true;
  try {
    const res = await driverApi.updateTrangThaiChuyenXe(currentTrip.value.id, {
      trang_thai: "dang_di_chuyen",
    });
    if (res.success || res.data) {
      currentTrip.value.trang_thai = "dang_di_chuyen";
      alerts.value.unshift({
        id: Date.now(),
        type: "warning",
        message: "Chuyến xe đã bắt đầu di chuyển!",
        time: new Date().toLocaleTimeString("vi-VN"),
      });
      setTimeout(() => {
        if (mapInstance) mapInstance.resize();
      }, 300);
    }
  } catch (error) {
    console.error("Lỗi khi cập nhật trạng thái:", error);
    alerts.value.unshift({
      id: Date.now(),
      type: "danger",
      message: "Không thể bắt đầu chuyến xe: " + error.message,
      time: new Date().toLocaleTimeString("vi-VN"),
    });
  } finally {
    isStartingTrip.value = false;
  }
};

// --- AI Camera State (Ngủ gật) ---
const aiVideoRef = ref(null);
const aiCanvasRef = ref(null);
const aiStatus = ref("init"); // init | loading | normal | warning | danger | no_face | error
const aiEar = ref(0);
const aiFps = ref(0);
const aiCameraOn = ref(false);
const aiViolationCount = ref(0);
let drowsinessAgent = null;

// --- YOLO Camera State (Phát hiện vật thể vi phạm) ---
const yoloVideoRef = ref(null);
const yoloCanvasRef = ref(null);
const yoloStatus = ref("init"); // init | loading | detecting | violation | error
const yoloCameraOn = ref(false);
const yoloFps = ref(0);
const yoloViolationCount = ref(0);
const yoloDetections = ref([]); // Danh sách detections realtime
let violationAgent = null;

const aiStatusLabel = computed(() => {
  const map = {
    init: "Chưa khởi động",
    loading: "Đang nạp AI...",
    normal: "Tỉnh táo",
    warning: "Cảnh báo buồn ngủ",
    danger: "⚠️ NGỦ GẬT",
    no_face: "Không nhận diện khuôn mặt",
    error: "Lỗi camera",
  };
  return map[aiStatus.value] || aiStatus.value;
});

const aiStatusColor = computed(() => {
  const map = {
    normal: "#4ade80",
    warning: "#fbbf24",
    danger: "#ef4444",
    no_face: "#94a3b8",
    loading: "#818cf8",
    init: "#64748b",
    error: "#ef4444",
  };
  return map[aiStatus.value] || "#64748b";
});

const earPercent = computed(() =>
  Math.min(100, Math.max(0, (aiEar.value / 0.35) * 100))
);

// --- YOLO Computed ---
const yoloStatusLabel = computed(() => {
  const map = {
    init: "Chưa khởi động",
    loading: "Đang nạp YOLO...",
    detecting: "Đang giám sát",
    violation: "⚠️ PHÁT HIỆN VI PHẠM",
    error: "Lỗi camera",
  };
  return map[yoloStatus.value] || yoloStatus.value;
});

const yoloStatusColor = computed(() => {
  const map = {
    init: "#64748b",
    loading: "#818cf8",
    detecting: "#4ade80",
    violation: "#ef4444",
    error: "#ef4444",
  };
  return map[yoloStatus.value] || "#64748b";
});

// Start AI Camera (Ngủ gật)
const toggleAiCamera = async () => {
  if (aiCameraOn.value) {
    drowsinessAgent?.stop();
    aiCameraOn.value = false;
    aiStatus.value = "init";
    return;
  }

  try {
    aiStatus.value = "loading";
    if (!drowsinessAgent) {
      drowsinessAgent = new DrowsinessAgent();
      await drowsinessAgent.init();
    }
    drowsinessAgent.attachElements(aiVideoRef.value, aiCanvasRef.value);
    drowsinessAgent.setTripId(currentTrip.value.id);
    drowsinessAgent.setPosition(
      currentPosition.value.lat,
      currentPosition.value.lng
    );

    drowsinessAgent.onStatusChange = (status, ear) => {
      aiStatus.value = status;
      aiEar.value = ear;
    };
    drowsinessAgent.onFpsUpdate = (fps) => {
      aiFps.value = fps;
    };
    drowsinessAgent.onViolation = (data) => {
      aiViolationCount.value++;
      alerts.value.unshift({
        id: Date.now(),
        type: "danger",
        message: `Phát hiện ngủ gật! EAR: ${data.ear.toFixed(3)}`,
        time: new Date().toLocaleTimeString("vi-VN"),
      });
      if (alerts.value.length > 20) alerts.value.pop();
    };

    await drowsinessAgent.start();
    aiCameraOn.value = true;
    aiStatus.value = "normal";

    // Push info alert
    alerts.value.unshift({
      id: Date.now(),
      type: "warning",
      message: "Camera AI đã bật — đang giám sát tài xế",
      time: new Date().toLocaleTimeString("vi-VN"),
    });
  } catch (e) {
    console.error("Lỗi khởi động AI Camera:", e);
    aiStatus.value = "error";
    alerts.value.unshift({
      id: Date.now(),
      type: "danger",
      message: `Lỗi camera: ${e.message}`,
      time: new Date().toLocaleTimeString("vi-VN"),
    });
  }
};

// Start YOLO Camera (Phát hiện vật thể vi phạm)
const toggleYoloCamera = async () => {
  if (yoloCameraOn.value) {
    violationAgent?.stop();
    yoloCameraOn.value = false;
    yoloStatus.value = "init";
    yoloDetections.value = [];
    return;
  }

  try {
    yoloStatus.value = "loading";

    // Khởi tạo agent nếu chưa có hoặc chưa load model thành công
    if (!violationAgent || !violationAgent.session) {
      violationAgent = new ViolationDetectionAgent();
      await violationAgent.init();
    }

    // Đảm bảo ref video/canvas đã sẵn sàng
    if (!yoloVideoRef.value || !yoloCanvasRef.value) {
      throw new Error(
        "Video hoặc Canvas element chưa sẵn sàng. Vui lòng thử lại."
      );
    }

    violationAgent.attachElements(yoloVideoRef.value, yoloCanvasRef.value);
    violationAgent.setTripId(currentTrip.value.id);
    violationAgent.setPosition(
      currentPosition.value.lat,
      currentPosition.value.lng
    );

    // Callbacks
    violationAgent.onStatusChange = (status) => {
      yoloStatus.value = status;
    };
    violationAgent.onFpsUpdate = (fps) => {
      yoloFps.value = fps;
    };
    violationAgent.onDetection = (detections) => {
      yoloDetections.value = detections;
    };
    violationAgent.onViolation = (data) => {
      yoloViolationCount.value++;
      alerts.value.unshift({
        id: Date.now(),
        type: "danger",
        message: `${data.label} — độ tin cậy: ${(data.confidence * 100).toFixed(
          0
        )}%`,
        time: new Date().toLocaleTimeString("vi-VN"),
      });
      if (alerts.value.length > 20) alerts.value.pop();
    };

    await violationAgent.start();
    yoloCameraOn.value = true;
    yoloStatus.value = "detecting";

    alerts.value.unshift({
      id: Date.now(),
      type: "warning",
      message: "Camera YOLO đã bật — đang giám sát vật thể vi phạm",
      time: new Date().toLocaleTimeString("vi-VN"),
    });
  } catch (e) {
    console.error("Lỗi khởi động YOLO Camera:", e);
    yoloStatus.value = "error";
    // Reset agent nếu init fail để lần sau thử lại
    violationAgent = null;
    alerts.value.unshift({
      id: Date.now(),
      type: "danger",
      message: `Lỗi YOLO camera: ${e.message}`,
      time: new Date().toLocaleTimeString("vi-VN"),
    });
  }
};

const mapContainer = ref(null);
let mapInstance = null;
let busMarker = null;
let currentPopup = null;
let plannedRouteGeoJSON = null;
let actualRouteGeoJSON = {
  type: "Feature",
  properties: {},
  geometry: { type: "LineString", coordinates: [] },
};
let watchId = null;
let trackingInterval = null;
const currentPosition = ref({ lat: 10.762622, lng: 106.660172 });
const hasRealGps = ref(false);
const gpsError = ref(null);
const mapBearing = ref(0);
const isLoadingRoute = ref(false);
const activeTargetStop = ref(null);

// Sorted stops by thu_tu
const sortedStops = computed(() => {
  if (!currentTrip.value.tram_dungs) return [];
  return [...currentTrip.value.tram_dungs].sort((a, b) => {
    const orderA = a.thu_tu ?? a.pivot?.thu_tu ?? 999;
    const orderB = b.thu_tu ?? b.pivot?.thu_tu ?? 999;
    return orderA - orderB;
  });
});

// Custom markers
const createBusElement = () => {
  const el = document.createElement("div");
  el.className = "custom-bus-marker";
  el.innerHTML = `<div style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); width: 40px; height: 40px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.9); box-shadow: 0 0 20px rgba(99,102,241,0.5), 0 4px 12px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">🚌</div>`;
  return el;
};

const createStopElement = (type, num) => {
  const colors =
    type === "don"
      ? {
          bg: "linear-gradient(135deg, #10b981, #059669)",
          shadow: "rgba(16,185,129,0.4)",
        }
      : {
          bg: "linear-gradient(135deg, #f43f5e, #e11d48)",
          shadow: "rgba(244,63,94,0.4)",
        };
  const el = document.createElement("div");
  el.innerHTML = `<div style="background: ${colors.bg}; width: 28px; height: 28px; border-radius: 50%; border: 2.5px solid white; box-shadow: 0 0 12px ${colors.shadow}, 0 2px 6px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 12px; cursor: pointer;">${num}</div>`;
  return el;
};

// 1. Tracking
const postLiveTracking = async () => {
  if (!currentTrip.value.id) return;
  try {
    await driverApi.postLiveTrackingLocation(currentTrip.value.id, {
      vi_do: currentPosition.value.lat,
      kinh_do: currentPosition.value.lng,
      van_toc: currentTrip.value.van_toc,
      huong_di: currentPosition.value.heading || 0,
      do_chinh_xac_gps: currentPosition.value.accuracy || 0,
    });
  } catch (e) {
    console.error("Lỗi gửi tracking", e);
  }
};

// 2. Fetch trip
/** Ưu tiên chuyến từ ?chuyen=id (từ Lịch trình → Vào điều khiển), không thì auto chọn chuyến hiện tại. */
const fetchTripByIdOrFallback = async () => {
  console.log("fetchTripByIdOrFallback started");
  isFetchingTrip.value = true;
  try {
    const raw = route.query.chuyen;
    const id = raw != null && String(raw).trim() !== "" ? Number(raw) : NaN;
    if (Number.isFinite(id) && id > 0) {
      console.log("Fetching specific trip by ID:", id);
      const res = await driverApi.getChuyenXeDetail(id);
      if (res?.success && res.data) {
        let trip = res.data;
        try {
          console.log("Fetching stops for trip ID:", id);
          const lt = await driverApi.getLichTrinhChuyen(id);
          if (
            lt?.success &&
            Array.isArray(lt.data?.lich_trinh) &&
            lt.data.lich_trinh.length
          ) {
            trip = {
              ...trip,
              tuyen_duong: {
                ...(trip.tuyen_duong || {}),
                tram_dungs: lt.data.lich_trinh,
              },
            };
          }
        } catch (ltErr) {
          console.warn("Không lấy được lịch trình trạm dừng:", ltErr);
        }
        applyTripData(trip);
        return;
      }
    }
    console.log("No valid trip ID in query, falling back to fetchCurrentTrip");
    await fetchCurrentTrip();
  } catch (e) {
    console.error("Lỗi trong fetchTripByIdOrFallback:", e);
    await fetchCurrentTrip();
  } finally {
    isFetchingTrip.value = false;
    console.log("fetchTripByIdOrFallback finished");
  }
};

const fetchCurrentTrip = async () => {
  console.log("fetchCurrentTrip started");
  isFetchingTrip.value = true;
  try {
    const today = new Date().toISOString().split("T")[0];

    // 1. Tìm chuyến xe đang chạy
    console.log("Checking for active trips (dang_di_chuyen)...");
    let response = await driverApi.getChuyenXeList({
      trang_thai: "dang_di_chuyen",
    });
    let trips = response?.data?.data || response?.data || [];
    if (!Array.isArray(trips) && response?.data) trips = [response.data];

    if (Array.isArray(trips) && trips.length > 0) {
      console.log("Found active trip:", trips[0].id);
      const activeTrip = trips.find(t => t.trang_thai === "dang_di_chuyen") || trips[0];
      applyTripData(activeTrip);
      return;
    }

    // 2. Nếu không có chuyến nào đang chạy, tìm chuyến sắp tới trong hôm nay
    console.log("No active trip. Checking today's schedule for upcoming trips...");
    response = await driverApi.getLichTrinhCaNhan({
      ngay_bat_dau: today,
      ngay_ket_thuc: today,
    });

    const todayTrips = response?.data?.data || response?.data || [];
    console.log(`Found ${Array.isArray(todayTrips) ? todayTrips.length : 0} total trips today.`);
    
    if (Array.isArray(todayTrips)) {
      todayTrips.forEach(t => {
        console.log(`- Trip ID ${t.id}: status=${t.trang_thai}, date=${t.ngay_khoi_hanh} (Today is ${today})`);
      });
    }

    // Lọc ra các chuyến hoạt động
    let upcomingTrips = Array.isArray(todayTrips) ? todayTrips.filter(
      (t) => t.trang_thai === "hoat_dong" && t.ngay_khoi_hanh?.startsWith(today)
    ) : [];

    if (upcomingTrips.length > 0) {
      console.log(`Found ${upcomingTrips.length} upcoming 'hoat_dong' trips.`);
      const now = new Date();
      const currentMinutes = now.getHours() * 60 + now.getMinutes();

      upcomingTrips.sort((a, b) => {
        const timeAStr = a.gio_khoi_hanh || "00:00";
        const timeBStr = b.gio_khoi_hanh || "00:00";
        const [hA, mA] = timeAStr.split(":").map(Number);
        const timeA = (hA || 0) * 60 + (mA || 0);
        const [hB, mB] = timeBStr.split(":").map(Number);
        const timeB = (hB || 0) * 60 + (mB || 0);
        return (
          Math.abs(timeA - currentMinutes) - Math.abs(timeB - currentMinutes)
        );
      });

      console.log("Selecting closest trip:", upcomingTrips[0].id);
      applyTripData(upcomingTrips[0]);
    } else {
      currentTrip.value.id = null;
      console.warn("Hôm nay không có lịch trình di chuyển.");
    }
  } catch (error) {
    console.warn("Lỗi khi load dữ liệu chuyến xe:", error.message);
    currentTrip.value.id = null;
  } finally {
    isFetchingTrip.value = false;
    console.log("fetchCurrentTrip finished");
  }
};

const applyTripData = (tripData) => {
  currentTrip.value = {
    id: tripData.id,
    ten_tuyen_duong: tripData.tuyen_duong?.ten_tuyen_duong || "Chưa xác định",
    bien_so: tripData.xe?.bien_so || "---",
    trang_thai: tripData.trang_thai,
    van_toc: 0,
    quang_duong: tripData.tuyen_duong?.quang_duong || "0",
    diem_bat_dau: tripData.tuyen_duong?.diem_bat_dau || "",
    diem_ket_thuc: tripData.tuyen_duong?.diem_ket_thuc || "",
    gio_khoi_hanh: tripData.gio_khoi_hanh,
    tram_dungs: tripData.tuyen_duong?.tram_dungs || [],
  };
  drawAllStops();
};

watch(
  () => route.query.chuyen,
  () => {
    fetchTripByIdOrFallback();
  }
);

// Polyline decoder
const decodePolyline = (str, precision = 5) => {
  let index = 0,
    lat = 0,
    lng = 0,
    coordinates = [],
    shift,
    result,
    byte,
    factor = Math.pow(10, precision);
  while (index < str.length) {
    byte = null;
    shift = 0;
    result = 0;
    do {
      byte = str.charCodeAt(index++) - 63;
      result |= (byte & 0x1f) << shift;
      shift += 5;
    } while (byte >= 0x20);
    lat += result & 1 ? ~(result >> 1) : result >> 1;
    shift = result = 0;
    do {
      byte = str.charCodeAt(index++) - 63;
      result |= (byte & 0x1f) << shift;
      shift += 5;
    } while (byte >= 0x20);
    lng += result & 1 ? ~(result >> 1) : result >> 1;
    coordinates.push([lng / factor, lat / factor]);
  }
  return coordinates;
};

// Route to stop
const routeToStop = async (tram) => {
  activeTargetStop.value = tram;
  tram.routingDistance = null;
  tram.routingDuration = null;
  isLoadingRoute.value = true;

  let originLat = currentPosition.value.lat;
  let originLng = currentPosition.value.lng;

  // Fallback: nearest previous stop if no GPS
  if (!hasRealGps.value && currentTrip.value?.tram_dungs) {
    const sorted = sortedStops.value;
    const tramIndex = sorted.findIndex((t) => t.id === tram.id);
    if (tramIndex > 0) {
      const prevTram = sorted[tramIndex - 1];
      originLat = prevTram.toa_do_x;
      originLng = prevTram.toa_do_y;
    }
  }

  const originCoord = `${originLat},${originLng}`;
  const destCoord = `${tram.toa_do_x},${tram.toa_do_y}`;

  try {
    const data = await openmapApi.direction(originCoord, destCoord);
    let pathCoordinates = [];
    if (data.routes && data.routes.length > 0) {
      const route = data.routes[0];
      if (route.legs && route.legs.length > 0) {
        const leg = route.legs[0];
        if (leg.distance?.text) tram.routingDistance = leg.distance.text;
        if (leg.duration?.text) tram.routingDuration = leg.duration.text;
      }
      if (route.geometry && Array.isArray(route.geometry.coordinates))
        pathCoordinates = route.geometry.coordinates;
      else if (route.overview_polyline?.points)
        pathCoordinates = decodePolyline(route.overview_polyline.points);
      else if (typeof route.geometry === "string")
        pathCoordinates = decodePolyline(route.geometry);
    } else if (data.paths && data.paths.length > 0) {
      const path = data.paths[0];
      if (path.distance)
        tram.routingDistance = (path.distance / 1000).toFixed(1) + " km";
      if (path.time)
        tram.routingDuration = Math.round(path.time / 1000 / 60) + " phút";
      if (path.points?.coordinates) pathCoordinates = path.points.coordinates;
      else if (typeof path.points === "string")
        pathCoordinates = decodePolyline(path.points);
    }
    if (pathCoordinates.length > 0) drawPath(pathCoordinates);
  } catch (error) {
    console.error("Fallback OSRM:", error);
    try {
      const oLng = hasRealGps.value ? currentPosition.value.lng : originLng;
      const oLat = hasRealGps.value ? currentPosition.value.lat : originLat;
      const osrmData = await openmapApi.getDrivingRoute(
        `${oLng},${oLat};${tram.toa_do_y},${tram.toa_do_x}`
      );
      if (osrmData?.code === "Ok" && osrmData.routes?.length > 0) {
        const route = osrmData.routes[0];
        if (route.distance)
          tram.routingDistance = (route.distance / 1000).toFixed(1) + " km";
        if (route.duration)
          tram.routingDuration = Math.round(route.duration / 60) + " phút";
        if (route.geometry.coordinates.length > 0)
          drawPath(route.geometry.coordinates);
      }
    } catch (e2) {
      console.error("OSRM failed:", e2);
    }
  } finally {
    isLoadingRoute.value = false;
  }
};

const drawPath = (pathCoordinates) => {
  plannedRouteGeoJSON = {
    type: "Feature",
    geometry: { type: "LineString", coordinates: pathCoordinates },
  };
  if (mapInstance.getSource("plannedRoute")) {
    mapInstance.getSource("plannedRoute").setData(plannedRouteGeoJSON);
  } else {
    mapInstance.addSource("plannedRoute", {
      type: "geojson",
      data: plannedRouteGeoJSON,
    });
    mapInstance.addLayer({
      id: "plannedRoute",
      type: "line",
      source: "plannedRoute",
      layout: { "line-join": "round", "line-cap": "round" },
      paint: {
        "line-color": "#818cf8",
        "line-width": 5,
        "line-dasharray": [2, 2],
      },
    });
  }
  const bounds = new maplibregl.LngLatBounds(
    pathCoordinates[0],
    pathCoordinates[0]
  );
  for (const coord of pathCoordinates) bounds.extend(coord);
  mapInstance.fitBounds(bounds, { padding: 60 });
};

const drawAllStops = () => {
  if (!mapInstance || sortedStops.value.length === 0) return;
  const waypoints = [],
    lngLats = [];
  sortedStops.value.forEach((tram) => {
    const lat = parseFloat(tram.toa_do_x),
      lng = parseFloat(tram.toa_do_y);
    if (!isNaN(lat) && !isNaN(lng)) {
      waypoints.push({ lat, lng });
      lngLats.push([lng, lat]);
      const popup = new maplibregl.Popup({ offset: 25 }).setHTML(
        `<b>${tram.ten_tram}</b><br>Loại: ${
          tram.loai_tram === "don" ? "Đón khách" : "Trả khách"
        }`
      );
      new maplibregl.Marker({
        element: createStopElement(tram.loai_tram, waypoints.length),
      })
        .setLngLat([lng, lat])
        .setPopup(popup)
        .addTo(mapInstance);
    }
  });
  if (waypoints.length > 0) {
    currentPosition.value = { lat: waypoints[0].lat, lng: waypoints[0].lng };
    if (busMarker) busMarker.setLngLat([waypoints[0].lng, waypoints[0].lat]);
    const bounds = new maplibregl.LngLatBounds(lngLats[0], lngLats[0]);
    for (const coord of lngLats) bounds.extend(coord);
    if (waypoints.length > 1) {
      const originCoord = `${waypoints[0].lat},${waypoints[0].lng}`;
      const destCoordsArr = waypoints
        .slice(1)
        .map((wp) => `${wp.lat},${wp.lng}`);
      openmapApi
        .direction(originCoord, destCoordsArr.join(";"))
        .then((data) => {
          let pCoords = [];
          if (data.routes?.length > 0) {
            const r = data.routes[0];
            if (r.geometry && Array.isArray(r.geometry.coordinates))
              pCoords = r.geometry.coordinates;
            else if (r.overview_polyline?.points)
              pCoords = decodePolyline(r.overview_polyline.points);
            else if (typeof r.geometry === "string")
              pCoords = decodePolyline(r.geometry);
          }
          if (pCoords.length > 0) drawPath(pCoords);
        })
        .catch((err) => {
          console.warn("Fallback OSRM toàn tuyến:", err);
          const coordsString = waypoints
            .map((wp) => `${wp.lng},${wp.lat}`)
            .join(";");
          openmapApi
            .getDrivingRoute(coordsString)
            .then((osrmData) => {
              if (osrmData?.code === "Ok" && osrmData.routes?.length > 0)
                drawPath(osrmData.routes[0].geometry.coordinates);
            })
            .catch((e) => console.error("OSRM toàn tuyến failed", e));
        });
    }
    mapInstance.fitBounds(bounds, { padding: 60 });
  }
};

const centerToCurrentLocation = () => {
  if (mapInstance && currentPosition.value.lat && currentPosition.value.lng) {
    if (busMarker)
      busMarker.setLngLat([
        currentPosition.value.lng,
        currentPosition.value.lat,
      ]);
    mapInstance.flyTo({
      center: [currentPosition.value.lng, currentPosition.value.lat],
      zoom: 16,
      speed: 1.5,
    });
  }
};

const rotateMap = () => {
  if (!mapInstance) return;
  mapBearing.value = (mapBearing.value + 90) % 360;
  mapInstance.easeTo({ bearing: mapBearing.value, duration: 600 });
};

const initMap = () => {
  if (mapInstance || !mapContainer.value) return;

  console.log("Initializing map...");
  const apiKey = import.meta.env.VITE_OPENMAP_API_KEY;
  if (!apiKey) {
    console.warn("VITE_OPENMAP_API_KEY is missing!");
  }

  mapInstance = new maplibregl.Map({
    container: mapContainer.value,
    style: `https://maptiles.openmap.vn/styles/day-v1/style.json?apikey=${apiKey}`,
    center: [currentPosition.value.lng, currentPosition.value.lat],
    zoom: 13,
  });

  mapInstance.on("load", () => {
    mapInstance.addSource("actualRoute", {
      type: "geojson",
      data: actualRouteGeoJSON,
    });
    mapInstance.addLayer({
      id: "actualRoute",
      type: "line",
      source: "actualRoute",
      layout: { "line-join": "round", "line-cap": "round" },
      paint: { "line-color": "#f43f5e", "line-width": 5, "line-opacity": 0.85 },
    });
    if (plannedRouteGeoJSON) {
      mapInstance.addSource("plannedRoute", {
        type: "geojson",
        data: plannedRouteGeoJSON,
      });
      mapInstance.addLayer(
        {
          id: "plannedRoute",
          type: "line",
          source: "plannedRoute",
          layout: { "line-join": "round", "line-cap": "round" },
          paint: {
            "line-color": "#818cf8",
            "line-width": 5,
            "line-dasharray": [2, 2],
          },
        },
        "actualRoute"
      );
    }
    
    currentPopup = new maplibregl.Popup({ offset: 25 }).setHTML(
      "Vị trí xe hiện tại"
    );
    busMarker = new maplibregl.Marker({ element: createBusElement() })
      .setLngLat([currentPosition.value.lng, currentPosition.value.lat])
      .setPopup(currentPopup)
      .addTo(mapInstance);
      
    if (sortedStops.value.length > 0) {
      drawAllStops();
    }
  });
};

watch(
  () => currentTrip.value.trang_thai,
  (newStatus) => {
    if (newStatus === "dang_di_chuyen") {
      setTimeout(() => {
        initMap();
      }, 100);
    }
  },
  { immediate: true }
);

onMounted(() => {
  console.log("DashboardView Mounted - Bắt đầu tải dữ liệu...");
  fetchTripByIdOrFallback();

  if ("geolocation" in navigator) {
    let gpsRetryCount = 0;
    const MAX_GPS_RETRIES = 5;

    const startGpsWatch = (highAccuracy = true) => {
      watchId = navigator.geolocation.watchPosition(
        (position) => {
          hasRealGps.value = true;
          gpsError.value = null;
          gpsRetryCount = 0; // reset khi thành công
          const { latitude, longitude, speed, heading, accuracy } =
            position.coords;
          currentPosition.value = {
            lat: latitude,
            lng: longitude,
            heading: heading || 0,
            accuracy: accuracy || 0,
          };
          currentTrip.value.van_toc = speed ? Math.round(speed * 3.6) : 0;
          drowsinessAgent?.setPosition(latitude, longitude);
          if (busMarker) busMarker.setLngLat([longitude, latitude]);
          if (mapInstance) {
            mapInstance.panTo([longitude, latitude]);
            actualRouteGeoJSON.geometry.coordinates.push([longitude, latitude]);
            const src = mapInstance.getSource("actualRoute");
            if (src) src.setData(actualRouteGeoJSON);
          }
        },
        (error) => {
          gpsError.value = error.message;
          console.warn("GPS Error code:", error.code, "-", error.message);

          if (error.code === 1) {
            gpsError.value =
              "Trình duyệt bị từ chối quyền truy cập vị trí. Vui lòng cấp quyền trong cài đặt.";
            return;
          }

          if (gpsRetryCount >= MAX_GPS_RETRIES) {
            console.warn("GPS: đã thử lại tối đa, dừng retry.");
            gpsError.value =
              "Không thể lấy vị trí GPS. Đang dùng vị trí trạm dừng làm fallback.";
            return;
          }

          gpsRetryCount++;
          if (watchId) navigator.geolocation.clearWatch(watchId);

          if (error.code === 2) {
            console.info(
              `GPS unavailable, thử lại (lần ${gpsRetryCount}) với lowAccuracy...`
            );
            setTimeout(() => startGpsWatch(false), 5000);
          } else if (error.code === 3) {
            console.info(`GPS timeout, thử lại (lần ${gpsRetryCount})...`);
            setTimeout(() => startGpsWatch(highAccuracy), 3000);
          }
        },
        {
          enableHighAccuracy: highAccuracy,
          maximumAge: 0,
          timeout: highAccuracy ? 15000 : 30000,
        }
      );
    };
    startGpsWatch();
  } else {
    gpsError.value = "Trình duyệt không hỗ trợ GPS";
  }
  trackingInterval = setInterval(postLiveTracking, 30000); // Gửi tracking mỗi 30 giây
  postLiveTracking();
});

onUnmounted(() => {
  if (mapInstance) mapInstance.remove();
  if (watchId) navigator.geolocation.clearWatch(watchId);
  if (trackingInterval) clearInterval(trackingInterval);
  drowsinessAgent?.stop();
  violationAgent?.stop();
});
</script>

<template>
  <div class="driver-dashboard-wrapper">
    <!-- Loading state -->
    <div v-if="isFetchingTrip" class="loading-full">
      <div class="spinner"></div>
      <p>Đang tải lịch trình...</p>
    </div>

    <!-- No trip state -->
    <div v-else-if="!currentTrip.id" class="no-trip-container">
      <div class="no-trip-content glass-card">
        <Truck class="no-trip-icon" :size="64" />
        <h2>Hôm nay không có lịch trình di chuyển</h2>
        <p>Bạn có thể nghỉ ngơi hoặc liên hệ điều hành nếu có thắc mắc.</p>
        <button class="action-btn refresh-btn" @click="fetchCurrentTrip">
          <RefreshCw /> Tải lại
        </button>
      </div>
    </div>

    <template v-else>
      <!-- GPS Warning Banner -->
      <div
        class="gps-warning-banner"
        v-if="!hasRealGps && currentTrip.trang_thai === 'dang_di_chuyen'"
      >
        <WifiOff class="gps-warn-icon" />
        <div class="gps-warn-text">
          <strong>Tín hiệu GPS yếu hoặc chưa kết nối</strong>
          <span>{{
            gpsError ||
            "Đang tìm kiếm vệ tinh... Chỉ đường sẽ dựa trên trạm dừng gần nhất."
          }}</span>
        </div>
        <div class="gps-signal-anim">
          <span></span><span></span><span></span>
        </div>
      </div>

      <!-- Header -->
      <header class="dashboard-header">
        <div class="header-left">
          <div class="header-icon-wrap"><Truck class="header-icon" /></div>
          <div>
            <h1 class="page-title">Dashboard Tài Xế</h1>
            <p class="subtitle">
              <span class="route-name">{{ currentTrip.ten_tuyen_duong }}</span>
              <span class="divider">•</span>
              <span class="plate-badge">{{ currentTrip.bien_so }}</span>
            </p>
          </div>
        </div>
        <div class="header-right">
          <div class="speed-gauge">
            <Zap class="speed-icon" />
            <span class="speed-value">{{ currentTrip.van_toc }}</span>
            <span class="speed-unit">km/h</span>
          </div>
          <div class="status-badge" :class="currentTrip.trang_thai">
            <span
              class="pulsing-dot"
              v-if="currentTrip.trang_thai === 'dang_di_chuyen'"
            ></span>
            <span>{{
              currentTrip.trang_thai === "dang_di_chuyen"
                ? "Đang di chuyển"
                : "Sẵn sàng"
            }}</span>
          </div>
        </div>
      </header>

      <!-- Info Cards -->
      <div class="trip-info-row">
        <div class="info-card">
          <div class="info-icon-wrap icon-blue">
            <Route class="info-icon" />
          </div>
          <div class="info-data">
            <span class="info-label">Lộ trình</span>
            <span class="info-val"
              >{{ currentTrip.diem_bat_dau }} →
              {{ currentTrip.diem_ket_thuc }}</span
            >
          </div>
        </div>
        <div class="info-card">
          <div class="info-icon-wrap icon-green">
            <MapPin class="info-icon" />
          </div>
          <div class="info-data">
            <span class="info-label">Quãng đường</span>
            <span class="info-val">{{ currentTrip.quang_duong }} km</span>
          </div>
        </div>
        <div class="info-card">
          <div class="info-icon-wrap icon-amber">
            <Clock class="info-icon" />
          </div>
          <div class="info-data">
            <span class="info-label">Giờ khởi hành</span>
            <span class="info-val">{{ currentTrip.gio_khoi_hanh }}</span>
          </div>
        </div>
        <div class="info-card">
          <div class="info-icon-wrap icon-purple">
            <CircleDot class="info-icon" />
          </div>
          <div class="info-data">
            <span class="info-label">Số trạm dừng</span>
            <span class="info-val">{{ sortedStops.length }} trạm</span>
          </div>
        </div>
      </div>

      <!-- Main Grid (dang_di_chuyen) -->
      <div
        class="dashboard-grid"
        v-if="currentTrip.trang_thai === 'dang_di_chuyen'"
      >
        <!-- Map Column -->
        <div class="col-left">
          <div class="glass-card map-card">
            <div class="card-header flex-between">
              <h3 class="card-title">
                <MapPin class="icon" /> Bản đồ & Tracking
                <span class="legend-route"
                  ><span class="line-blue"></span> Kế hoạch</span
                >
                <span class="legend-route"
                  ><span class="line-red"></span> Đã đi</span
                >
              </h3>
            </div>
            <div class="map-container-wrapper">
              <div class="map-container" ref="mapContainer"></div>
              <!-- Map Control Buttons -->
              <div class="map-controls">
                <button
                  class="map-ctrl-btn"
                  @click="centerToCurrentLocation"
                  title="Về vị trí hiện tại"
                >
                  <Crosshair class="ctrl-icon" />
                </button>
                <button
                  class="map-ctrl-btn"
                  @click="rotateMap"
                  title="Xoay bản đồ"
                >
                  <Compass
                    class="ctrl-icon"
                    :style="{ transform: `rotate(${mapBearing}deg)` }"
                  />
                </button>
              </div>
              <!-- GPS indicator on map -->
              <div
                class="map-gps-indicator"
                :class="{ 'gps-ok': hasRealGps, 'gps-no': !hasRealGps }"
              >
                <Wifi v-if="hasRealGps" class="gps-ind-icon" />
                <WifiOff v-else class="gps-ind-icon" />
                <span>{{ hasRealGps ? "GPS OK" : "Không có GPS" }}</span>
              </div>
            </div>
            <div class="map-footer">
              <button
                class="action-btn sos-btn"
                @click="sendSOS"
                :disabled="isSendingSOS"
              >
                <div class="spinner-sm" v-if="isSendingSOS"></div>
                <AlertTriangle v-else class="icon" /> S.O.S KHẨN CẤP
              </button>
              <button class="action-btn report-btn">
                <Truck class="icon" /> Báo cáo kẹt xe
              </button>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div class="col-right">
          <!-- Stops List -->
          <div class="glass-card stops-card">
            <div class="card-header">
              <h3 class="card-title">
                <Navigation class="icon" /> Danh sách trạm dừng
              </h3>
              <span class="stop-count-badge">{{ sortedStops.length }}</span>
            </div>
            <div class="stops-scroll-area">
              <div
                v-for="(tram, index) in sortedStops"
                :key="tram.id"
                class="stop-item"
                :class="{ 'active-stop': activeTargetStop?.id === tram.id }"
                @click="routeToStop(tram)"
              >
                <div class="stop-order-num">{{ index + 1 }}</div>
                <div class="stop-icon-wrap" :class="`icon-${tram.loai_tram}`">
                  <MapPin v-if="tram.loai_tram === 'don'" />
                  <Flag v-else />
                </div>
                <div class="stop-info-txt">
                  <span class="stop-name">{{ tram.ten_tram }}</span>
                  <span class="stop-type">{{
                    tram.loai_tram === "don" ? "Đón khách" : "Trả khách"
                  }}</span>
                  <div
                    v-if="
                      activeTargetStop?.id === tram.id && tram.routingDistance
                    "
                    class="stop-routing-info"
                  >
                    <span class="routing-chip route-dist"
                      ><Route class="inline-icon" :size="13" />
                      {{ tram.routingDistance }}</span
                    >
                    <span class="routing-chip route-time"
                      ><Clock class="inline-icon" :size="13" />
                      {{ tram.routingDuration }}</span
                    >
                  </div>
                  <div
                    v-if="
                      activeTargetStop?.id === tram.id &&
                      !hasRealGps &&
                      !tram.routingDistance &&
                      !isLoadingRoute
                    "
                    class="fallback-note"
                  >
                    <WifiOff :size="12" /> Chỉ đường từ trạm trước đó
                  </div>
                </div>
                <div class="stop-action">
                  <div
                    v-if="activeTargetStop?.id === tram.id && isLoadingRoute"
                    class="loading-spinner"
                  ></div>
                  <Navigation
                    v-else-if="activeTargetStop?.id === tram.id"
                    class="anim-pulse icon-nav"
                  />
                  <ChevronRight v-else class="icon-nav-inactive" />
                </div>
                <!-- Connector line -->
                <div
                  v-if="index < sortedStops.length - 1"
                  class="stop-connector"
                ></div>
              </div>
            </div>
          </div>

          <!-- AI Camera -->
          <div class="glass-card camera-card">
            <div class="card-header flex-between">
              <h3 class="card-title">
                <Video class="icon" /> Camera AI Giám sát
              </h3>
              <div class="camera-controls">
                <span v-if="aiCameraOn" class="live-tag">● LIVE</span>
                <span class="fps-badge" v-if="aiCameraOn">{{ aiFps }} FPS</span>
                <button
                  class="cam-toggle-btn"
                  :class="{ active: aiCameraOn }"
                  @click="toggleAiCamera"
                >
                  <Camera :size="16" />
                  {{ aiCameraOn ? "Tắt" : "Bật" }}
                </button>
              </div>
            </div>
            <div class="camera-stream-container">
              <!-- Video + Canvas overlay -->
              <video
                ref="aiVideoRef"
                class="ai-video"
                autoplay
                playsinline
                muted
              ></video>
              <canvas ref="aiCanvasRef" class="ai-canvas-overlay"></canvas>

              <!-- Placeholder khi chưa bật -->
              <div v-if="!aiCameraOn" class="camera-placeholder">
                <div class="cam-placeholder-content">
                  <ShieldAlert :size="32" />
                  <span>Nhấn <strong>Bật</strong> để khởi động Camera AI</span>
                  <span class="cam-hint"
                    >Hệ thống sẽ giám sát ngủ gật bằng AI</span
                  >
                </div>
                <div class="scanning-line" v-if="aiStatus === 'loading'"></div>
              </div>

              <!-- EAR Gauge -->
              <div v-if="aiCameraOn" class="ear-gauge">
                <div class="ear-gauge-label">EAR</div>
                <div class="ear-gauge-bar">
                  <div
                    class="ear-gauge-fill"
                    :style="{
                      width: earPercent + '%',
                      background: aiStatusColor,
                    }"
                  ></div>
                </div>
                <div class="ear-gauge-value" :style="{ color: aiStatusColor }">
                  {{ aiEar.toFixed(3) }}
                </div>
              </div>

              <!-- Status overlay -->
              <div class="camera-status" :class="`cam-status-${aiStatus}`">
                <div class="cam-status-left">
                  <Eye v-if="aiStatus === 'normal'" :size="16" />
                  <EyeOff
                    v-else-if="aiStatus === 'danger' || aiStatus === 'warning'"
                    :size="16"
                  />
                  <ShieldAlert v-else :size="16" />
                  <span class="cam-status-text">{{ aiStatusLabel }}</span>
                </div>
                <div class="cam-status-right" v-if="aiCameraOn">
                  <span v-if="aiViolationCount > 0" class="violation-count"
                    >{{ aiViolationCount }} vi phạm</span
                  >
                </div>
              </div>
            </div>
          </div>

          <!-- YOLO Camera — Phát hiện vật thể vi phạm -->
          <div class="glass-card yolo-camera-card">
            <div class="card-header flex-between">
              <h3 class="card-title">
                <ScanLine class="icon" /> Camera Giám sát Cabin
              </h3>
              <div class="camera-controls">
                <span v-if="yoloCameraOn" class="live-tag yolo-live"
                  >● DETECT</span
                >
                <span class="fps-badge" v-if="yoloCameraOn"
                  >{{ yoloFps }} FPS</span
                >
                <button
                  class="cam-toggle-btn"
                  :class="{ active: yoloCameraOn }"
                  @click="toggleYoloCamera"
                >
                  <ScanLine :size="16" />
                  {{ yoloCameraOn ? "Tắt" : "Bật" }}
                </button>
              </div>
            </div>
            <div class="camera-stream-container">
              <!-- Video + Canvas overlay cho bounding box -->
              <video
                ref="yoloVideoRef"
                class="ai-video yolo-video"
                autoplay
                playsinline
                muted
              ></video>
              <canvas
                ref="yoloCanvasRef"
                class="ai-canvas-overlay yolo-canvas"
              ></canvas>

              <!-- Placeholder khi chưa bật -->
              <div
                v-if="!yoloCameraOn"
                class="camera-placeholder yolo-placeholder"
              >
                <div class="cam-placeholder-content">
                  <Swords :size="32" />
                  <span
                    >Nhấn <strong>Bật</strong> để khởi động YOLO Detection</span
                  >
                  <span class="cam-hint"
                    >Phát hiện dao, hút thuốc, vi phạm trên xe</span
                  >
                </div>
                <div
                  class="scanning-line yolo-scan"
                  v-if="yoloStatus === 'loading'"
                ></div>
              </div>

              <!-- Danh sách detections realtime -->
              <div
                v-if="yoloCameraOn && yoloDetections.length > 0"
                class="yolo-detections-overlay"
              >
                <div
                  v-for="(det, idx) in yoloDetections"
                  :key="idx"
                  class="yolo-det-chip"
                  :class="`det-${det.className.replace(' ', '_')}`"
                >
                  <Swords v-if="det.className === 'knife'" :size="13" />
                  <Cigarette
                    v-else-if="det.className === 'cell phone'"
                    :size="13"
                  />
                  <AlertTriangle v-else :size="13" />
                  <span>{{
                    det.className === "knife"
                      ? "Dao"
                      : det.className === "cell phone"
                      ? "Hút thuốc"
                      : "Vi phạm"
                  }}</span>
                  <span class="det-conf"
                    >{{ (det.confidence * 100).toFixed(0) }}%</span
                  >
                </div>
              </div>

              <!-- Status overlay -->
              <div
                class="camera-status"
                :class="`cam-status-${
                  yoloStatus === 'violation'
                    ? 'danger'
                    : yoloStatus === 'detecting'
                    ? 'normal'
                    : yoloStatus
                }`"
              >
                <div class="cam-status-left">
                  <ScanLine v-if="yoloStatus === 'detecting'" :size="16" />
                  <AlertTriangle
                    v-else-if="yoloStatus === 'violation'"
                    :size="16"
                  />
                  <ShieldAlert v-else :size="16" />
                  <span class="cam-status-text">{{ yoloStatusLabel }}</span>
                </div>
                <div class="cam-status-right" v-if="yoloCameraOn">
                  <span v-if="yoloViolationCount > 0" class="violation-count"
                    >{{ yoloViolationCount }} vi phạm</span
                  >
                </div>
              </div>
            </div>
          </div>

          <!-- Alerts -->
          <div class="glass-card alerts-card">
            <div class="card-header warning-header">
              <h3 class="card-title text-danger">
                <AlertTriangle class="icon" /> Cảnh báo
              </h3>
            </div>
            <div class="alerts-list">
              <div
                v-for="alert in alerts"
                :key="alert.id"
                class="alert-item"
                :class="`alert-${alert.type}`"
              >
                <div class="alert-icon">
                  <Bell v-if="alert.type === 'warning'" /><AlertTriangle
                    v-else
                  />
                </div>
                <div class="alert-content">
                  <p class="alert-msg">{{ alert.message }}</p>
                  <span class="alert-time">{{ alert.time }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Ready to Start Screen (Trạng thái hoạt động) -->
      <div
        class="ready-to-start-container"
        v-else-if="currentTrip.trang_thai === 'hoat_dong'"
      >
        <div class="ready-content glass-card">
          <div class="ready-icon-wrap">
            <Truck class="ready-icon" :size="64" />
          </div>
          <h2>Chuyến xe đã sẵn sàng</h2>
          <p>
            Vui lòng kiểm tra lại thông tin chuyến xe, số lượng hành khách trước
            khi khởi hành.
          </p>
          <button
            class="btn-start-trip"
            @click="batDauDiChuyen"
            :disabled="isStartingTrip"
          >
            <div class="spinner" v-if="isStartingTrip"></div>
            <span v-else>BẮT ĐẦU DI CHUYỂN <ChevronRight /></span>
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
<style scoped>
/* ===== BASE ===== */
.driver-dashboard-wrapper {
  padding: 12px 16px;
  min-height: calc(100vh - 80px);
  background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 50%, #f0fdf4 100%);
  font-family: "Inter", "Segoe UI", sans-serif;
}

/* ===== GPS WARNING ===== */
.gps-warning-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  margin-bottom: 16px;
  background: linear-gradient(135deg, #fef3c7, #fde68a);
  border: 1px solid #f59e0b;
  border-radius: 14px;
  box-shadow: 0 4px 16px rgba(245, 158, 11, 0.15);
  animation: slideDown 0.4s ease;
}
@keyframes slideDown {
  from {
    transform: translateY(-20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}
.gps-warn-icon {
  width: 28px;
  height: 28px;
  color: #d97706;
  flex-shrink: 0;
}
.gps-warn-text {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.gps-warn-text strong {
  font-size: 14px;
  color: #92400e;
}
.gps-warn-text span {
  font-size: 12px;
  color: #a16207;
  margin-top: 2px;
}
.gps-signal-anim {
  display: flex;
  gap: 3px;
  align-items: flex-end;
}
.gps-signal-anim span {
  display: block;
  width: 4px;
  background: #d97706;
  border-radius: 2px;
  animation: gpsPulse 1.2s infinite;
}
.gps-signal-anim span:nth-child(1) {
  height: 8px;
  animation-delay: 0s;
}
.gps-signal-anim span:nth-child(2) {
  height: 14px;
  animation-delay: 0.2s;
}
.gps-signal-anim span:nth-child(3) {
  height: 20px;
  animation-delay: 0.4s;
}
@keyframes gpsPulse {
  0%,
  100% {
    opacity: 0.3;
  }
  50% {
    opacity: 1;
  }
}

/* ===== HEADER ===== */
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding: 16px 24px;
  background: linear-gradient(135deg, #1e1b4b, #312e81);
  border-radius: 18px;
  color: white;
  box-shadow: 0 8px 32px rgba(30, 27, 75, 0.3);
}
.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}
.header-icon-wrap {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
}
.header-icon {
  width: 24px;
  height: 24px;
  color: #c4b5fd;
}
.page-title {
  font-size: 22px;
  font-weight: 800;
  margin: 0 0 4px 0;
  letter-spacing: -0.5px;
}
.subtitle {
  font-size: 13px;
  color: #c4b5fd;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.route-name {
  font-weight: 600;
}
.divider {
  opacity: 0.4;
}
.plate-badge {
  background: rgba(255, 255, 255, 0.15);
  padding: 2px 10px;
  border-radius: 6px;
  font-weight: 700;
  font-size: 12px;
}
.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}
.speed-gauge {
  display: flex;
  align-items: center;
  gap: 4px;
  background: rgba(0, 0, 0, 0.3);
  padding: 8px 16px;
  border-radius: 30px;
  border: 1.5px solid rgba(129, 140, 248, 0.4);
}
.speed-icon {
  width: 16px;
  height: 16px;
  color: #a5b4fc;
}
.speed-value {
  font-size: 22px;
  font-weight: 900;
  font-family: "JetBrains Mono", monospace;
  color: #818cf8;
}
.speed-unit {
  font-size: 11px;
  color: #94a3b8;
}
.status-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 18px;
  border-radius: 30px;
  font-weight: 700;
  font-size: 13px;
}
.status-badge.dang_di_chuyen {
  background: rgba(16, 185, 129, 0.2);
  color: #4ade80;
  border: 1px solid rgba(16, 185, 129, 0.3);
}
.status-badge.loading {
  background: rgba(100, 116, 139, 0.2);
  color: #94a3b8;
}
.pulsing-dot {
  width: 9px;
  height: 9px;
  background: #4ade80;
  border-radius: 50%;
  animation: pulse 1.5s infinite;
}
@keyframes pulse {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.6);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 8px rgba(74, 222, 128, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(74, 222, 128, 0);
  }
}

/* ===== INFO CARDS ===== */
.trip-info-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 20px;
}
.info-card {
  background: white;
  padding: 16px 18px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
  border: 1px solid #e2e8f0;
  transition: transform 0.2s, box-shadow 0.2s;
}
.info-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}
.info-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.info-icon {
  width: 22px;
  height: 22px;
}
.icon-blue {
  background: #eff6ff;
  color: #3b82f6;
}
.icon-green {
  background: #ecfdf5;
  color: #10b981;
}
.icon-amber {
  background: #fffbeb;
  color: #f59e0b;
}
.icon-purple {
  background: #f5f3ff;
  color: #8b5cf6;
}
.info-data {
  display: flex;
  flex-direction: column;
}
.info-label {
  font-size: 11px;
  color: #94a3b8;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.info-val {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin-top: 3px;
}

/* ===== GRID ===== */
.dashboard-grid {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 20px;
  align-items: start;
}

/* ===== GLASS CARD ===== */
.glass-card {
  background: white;
  border-radius: 18px;
  box-shadow: 0 4px 24px -6px rgba(0, 0, 0, 0.08);
  border: 1px solid #e2e8f0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.card-header {
  padding: 14px 20px;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  gap: 8px;
}
.warning-header {
  background: linear-gradient(135deg, #fef2f2, #fff1f2);
  border-bottom-color: #fecaca;
}
.flex-between {
  justify-content: space-between;
}
.card-title {
  font-size: 15px;
  font-weight: 700;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #1e293b;
}
.text-danger {
  color: #ef4444;
}
.icon {
  width: 18px;
  height: 18px;
}

/* ===== MAP ===== */
.map-card {
  height: calc(100vh - 290px);
  min-height: 480px;
}
.map-container-wrapper {
  position: relative;
  flex: 1;
  display: flex;
  flex-direction: column;
}
.map-container {
  flex: 1;
  background: #e2e8f0;
  z-index: 10;
}
:deep(.maplibregl-canvas-container) {
  height: 100%;
  width: 100%;
  z-index: 10;
}
.legend-route {
  font-size: 11px;
  font-weight: 600;
  color: #94a3b8;
  margin-left: 10px;
  display: flex;
  align-items: center;
  gap: 4px;
}
.line-blue {
  width: 16px;
  height: 3px;
  background: #818cf8;
  display: inline-block;
  border-radius: 2px;
}
.line-red {
  width: 16px;
  height: 3px;
  background: #f43f5e;
  display: inline-block;
  border-radius: 2px;
}
.map-controls {
  position: absolute;
  bottom: 20px;
  right: 16px;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.map-ctrl-btn {
  width: 44px;
  height: 44px;
  border-radius: 13px;
  background: white;
  border: none;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.25s ease;
  color: #4f46e5;
}
.map-ctrl-btn:hover {
  background: #4f46e5;
  color: white;
  transform: scale(1.05);
}
.ctrl-icon {
  width: 22px;
  height: 22px;
  transition: transform 0.5s ease;
}
.map-gps-indicator {
  position: absolute;
  top: 14px;
  left: 14px;
  z-index: 1000;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  backdrop-filter: blur(10px);
}
.map-gps-indicator.gps-ok {
  background: rgba(16, 185, 129, 0.15);
  color: #059669;
  border: 1px solid rgba(16, 185, 129, 0.3);
}
.map-gps-indicator.gps-no {
  background: rgba(239, 68, 68, 0.12);
  color: #dc2626;
  border: 1px solid rgba(239, 68, 68, 0.25);
}
.gps-ind-icon {
  width: 14px;
  height: 14px;
}
.map-footer {
  padding: 14px 16px;
  background: white;
  border-top: 1px solid #f1f5f9;
  display: flex;
  gap: 10px;
}
.action-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 11px;
  border: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
}
.sos-btn {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
  box-shadow: 0 4px 16px rgba(239, 68, 68, 0.3);
}
.sos-btn:hover {
  box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
  transform: translateY(-1px);
}
.report-btn {
  background: #f1f5f9;
  color: #475569;
}
.report-btn:hover {
  background: #e2e8f0;
}

/* ===== STOPS ===== */
.stops-card {
  max-height: calc(100vh - 300px);
}
.stop-count-badge {
  margin-left: auto;
  background: #4f46e5;
  color: white;
  width: 26px;
  height: 26px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 800;
}
.stops-scroll-area {
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px 14px;
}
.stop-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  cursor: pointer;
  transition: all 0.25s ease;
}
.stop-item:hover {
  background: #eef2ff;
  border-color: #c7d2fe;
  transform: translateX(3px);
}
.stop-item.active-stop {
  background: linear-gradient(135deg, #eef2ff, #e0e7ff);
  border-color: #818cf8;
  box-shadow: 0 4px 16px rgba(79, 70, 229, 0.12);
}
.stop-order-num {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 800;
  color: #475569;
  flex-shrink: 0;
}
.active-stop .stop-order-num {
  background: #4f46e5;
  color: white;
}
.stop-icon-wrap {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stop-icon-wrap.icon-don {
  color: #10b981;
  background: #ecfdf5;
}
.stop-icon-wrap.icon-tra {
  color: #f43f5e;
  background: #fff1f2;
}
.stop-info-txt {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.stop-name {
  font-weight: 600;
  font-size: 14px;
  color: #1e293b;
}
.stop-type {
  font-size: 12px;
  color: #94a3b8;
}
.stop-routing-info {
  display: flex;
  gap: 8px;
  margin-top: 4px;
}
.routing-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}
.route-dist {
  background: #eff6ff;
  color: #3b82f6;
}
.route-time {
  background: #ecfdf5;
  color: #059669;
}
.inline-icon {
  flex-shrink: 0;
}
.fallback-note {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: #d97706;
  margin-top: 3px;
  font-style: italic;
}
.stop-action {
  flex-shrink: 0;
}
.anim-pulse {
  animation: navPulse 2s infinite;
  color: #4f46e5;
  width: 20px;
  height: 20px;
}
@keyframes navPulse {
  0%,
  100% {
    transform: scale(1);
    opacity: 1;
  }
  50% {
    transform: scale(1.15);
    opacity: 0.7;
  }
}
.icon-nav-inactive {
  opacity: 0.25;
  width: 18px;
  height: 18px;
  color: #94a3b8;
}
.loading-spinner {
  width: 20px;
  height: 20px;
  border: 2.5px solid #e2e8f0;
  border-top-color: #4f46e5;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.stop-connector {
  position: absolute;
  left: 27px;
  bottom: -8px;
  width: 2px;
  height: 8px;
  background: #cbd5e1;
  z-index: 0;
}

/* ===== AI CAMERA ===== */
.camera-card {
  margin-top: 14px;
}
.camera-controls {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
}
.live-tag {
  color: #ef4444;
  font-size: 11px;
  font-weight: 800;
  animation: blink 2s infinite;
}
.fps-badge {
  font-size: 11px;
  font-weight: 700;
  color: #818cf8;
  background: rgba(99, 102, 241, 0.1);
  padding: 2px 8px;
  border-radius: 6px;
}
.cam-toggle-btn {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 5px 12px;
  border-radius: 8px;
  border: 1.5px solid #e2e8f0;
  background: white;
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}
.cam-toggle-btn:hover {
  border-color: #6366f1;
  color: #4f46e5;
}
.cam-toggle-btn.active {
  background: #ef4444;
  color: white;
  border-color: #ef4444;
}
.cam-toggle-btn.active:hover {
  background: #dc2626;
}
@keyframes blink {
  50% {
    opacity: 0.5;
  }
}
.camera-stream-container {
  aspect-ratio: 4/3;
  background: #0f172a;
  position: relative;
  overflow: hidden;
}
.ai-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scaleX(-1); /* Mirror mode */
}
.ai-canvas-overlay {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transform: scaleX(-1);
  pointer-events: none;
  z-index: 2;
}
.camera-placeholder {
  position: absolute;
  inset: 0;
  z-index: 3;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
}
.cam-placeholder-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: #94a3b8;
  font-size: 13px;
  text-align: center;
}
.cam-placeholder-content strong {
  color: #818cf8;
}
.cam-hint {
  font-size: 11px;
  opacity: 0.6;
}
.scanning-line {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 3px;
  background: linear-gradient(90deg, transparent, #818cf8, transparent);
  box-shadow: 0 0 16px rgba(99, 102, 241, 0.6);
  animation: scanAnim 2s ease-in-out infinite;
}
@keyframes scanAnim {
  0% {
    transform: translateY(0);
  }
  100% {
    transform: translateY(300px);
  }
}
/* EAR Gauge */
.ear-gauge {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 5;
  display: flex;
  align-items: center;
  gap: 6px;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(8px);
  padding: 5px 12px;
  border-radius: 20px;
}
.ear-gauge-label {
  font-size: 10px;
  font-weight: 800;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.ear-gauge-bar {
  width: 60px;
  height: 6px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 3px;
  overflow: hidden;
}
.ear-gauge-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.15s, background 0.3s;
}
.ear-gauge-value {
  font-size: 12px;
  font-weight: 800;
  font-family: "JetBrains Mono", monospace;
}
/* Camera Status Bar */
.camera-status {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 8px 14px;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  font-size: 12px;
  color: white;
  display: flex;
  align-items: center;
  justify-content: space-between;
  z-index: 5;
  transition: background 0.3s;
}
.cam-status-danger {
  background: rgba(220, 38, 38, 0.85);
}
.cam-status-warning {
  background: rgba(217, 119, 6, 0.75);
}
.cam-status-left {
  display: flex;
  align-items: center;
  gap: 8px;
}
.cam-status-text {
  font-weight: 700;
}
.violation-count {
  background: rgba(239, 68, 68, 0.3);
  padding: 2px 10px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 11px;
  color: #fca5a5;
}

/* ===== ALERTS ===== */
.alerts-card {
  margin-top: 14px;
}
.alerts-list {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 200px;
  overflow-y: auto;
}
.alert-item {
  display: flex;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  align-items: flex-start;
}
.alert-warning {
  background: #fffbeb;
  border-left: 3px solid #f59e0b;
}
.alert-warning .alert-icon {
  color: #f59e0b;
}
.alert-danger {
  background: #fef2f2;
  border-left: 3px solid #ef4444;
}
.alert-danger .alert-icon {
  color: #ef4444;
}
.alert-icon {
  margin-top: 2px;
}
.alert-content {
  flex: 1;
}
.alert-msg {
  margin: 0 0 2px 0;
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
}
.alert-time {
  font-size: 11px;
  color: #94a3b8;
}

/* ===== YOLO CAMERA ===== */
.yolo-camera-card {
  margin-top: 14px;
}
.yolo-live {
  color: #818cf8 !important;
}
.yolo-video {
  transform: none; /* Camera sau không cần mirror */
}
.yolo-canvas {
  transform: none;
}
.yolo-placeholder {
  background: linear-gradient(135deg, #0c0a1a 0%, #1a1145 100%) !important;
}
.yolo-scan {
  background: linear-gradient(
    90deg,
    transparent,
    #6366f1,
    transparent
  ) !important;
  box-shadow: 0 0 16px rgba(99, 102, 241, 0.6) !important;
}

/* Danh sách phát hiện realtime */
.yolo-detections-overlay {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 5;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.yolo-det-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  backdrop-filter: blur(8px);
  animation: detChipIn 0.3s ease;
}
@keyframes detChipIn {
  from {
    transform: translateX(20px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}
.det-knife {
  background: rgba(239, 68, 68, 0.85);
  color: white;
  box-shadow: 0 2px 12px rgba(239, 68, 68, 0.4);
}
.det-cell_phone {
  background: rgba(245, 158, 11, 0.85);
  color: white;
  box-shadow: 0 2px 12px rgba(245, 158, 11, 0.4);
}
.det-vi_pham {
  background: rgba(139, 92, 246, 0.85);
  color: white;
  box-shadow: 0 2px 12px rgba(139, 92, 246, 0.4);
}
.det-conf {
  font-family: "JetBrains Mono", monospace;
  font-size: 11px;
  opacity: 0.9;
  background: rgba(255, 255, 255, 0.2);
  padding: 1px 6px;
  border-radius: 10px;
}

/* ===== ADDITIONAL STATES ===== */
.loading-full {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 60vh;
  gap: 16px;
  color: #64748b;
  font-weight: 500;
}
.spinner {
  width: 32px;
  height: 32px;
  border: 4px solid #e2e8f0;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}
.spinner-sm {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.no-trip-container {
  display: flex;
  align-items: center;
  justify-content: center;
  height: calc(100vh - 200px);
}
.no-trip-content {
  text-align: center;
  padding: 48px;
  max-width: 480px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  background: white;
}
.no-trip-icon {
  color: #94a3b8;
  margin-bottom: 8px;
}
.no-trip-content h2 {
  font-size: 24px;
  color: #1e293b;
  margin: 0;
}
.no-trip-content p {
  color: #64748b;
  margin: 0 0 16px 0;
  line-height: 1.5;
}
.refresh-btn {
  padding: 10px 20px;
  background: white;
  border: 1px solid #cbd5e1;
  color: #475569;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.refresh-btn:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.ready-to-start-container {
  display: flex;
  justify-content: center;
  padding: 40px 20px;
}
.ready-content {
  text-align: center;
  padding: 48px;
  max-width: 500px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
  background: white;
}
.ready-icon-wrap {
  width: 96px;
  height: 96px;
  background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #4f46e5;
  margin-bottom: 8px;
  box-shadow: 0 10px 25px rgba(79, 70, 229, 0.2);
}
.ready-content h2 {
  font-size: 28px;
  color: #1e293b;
  margin: 0;
}
.ready-content p {
  color: #64748b;
  font-size: 16px;
  line-height: 1.6;
  margin: 0;
}
.btn-start-trip {
  margin-top: 12px;
  padding: 16px 32px;
  background: linear-gradient(135deg, #4f46e5, #4338ca);
  color: white;
  border: none;
  border-radius: 12px;
  font-size: 18px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
}
.btn-start-trip:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 28px rgba(79, 70, 229, 0.4);
}
.btn-start-trip:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none;
}
.btn-start-trip span {
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-start-trip .spinner {
  width: 24px;
  height: 24px;
  border-width: 3px;
  border-top-color: white;
  border-right-color: rgba(255, 255, 255, 0.3);
  border-bottom-color: rgba(255, 255, 255, 0.3);
  border-left-color: rgba(255, 255, 255, 0.3);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1100px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
  }
  .trip-info-row {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 640px) {
  .trip-info-row {
    grid-template-columns: 1fr;
  }
  .dashboard-header {
    flex-direction: column;
    gap: 12px;
    text-align: center;
  }
  .header-right {
    justify-content: center;
  }
}
</style>
