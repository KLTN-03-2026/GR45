<script setup>
import { ref } from "vue";
import { Activity, ShieldAlert, Route, Users, MapPin } from "lucide-vue-next";
import TrackingWorkbench from "@/components/tracking/TrackingWorkbench.vue";

const quickStats = ref([
  {
    id: 1,
    label: "Xe đang online",
    value: "24",
    icon: Activity,
    colorClass: "text-emerald-500",
    bgClass: "bg-emerald-50",
  },
  {
    id: 2,
    label: "Tuyến hoạt động",
    value: "12",
    icon: Route,
    colorClass: "text-blue-500",
    bgClass: "bg-blue-50",
  },
  {
    id: 3,
    label: "Tài xế sẵn sàng",
    value: "38",
    icon: Users,
    colorClass: "text-indigo-500",
    bgClass: "bg-indigo-50",
  },
  {
    id: 4,
    label: "Cảnh báo rủi ro",
    value: "0",
    icon: ShieldAlert,
    colorClass: "text-rose-500",
    bgClass: "bg-rose-50",
  },
]);
</script>

<template>
  <div class="admin-tracking-dashboard">
    <!-- Header Giao diện Hiện đại -->
    <header class="dashboard-head">
      <div class="head-left">
        <div class="icon-wrap">
          <MapPin class="head-icon" />
        </div>
        <div>
          <h1 class="main-title">Trạm Điều Khiển (Command Center) 📍</h1>
          <p class="sub-title">
            Giám sát tổng thể hoạt động Di chuyển & Cảnh báo an toàn phương tiện
            toàn mạng lưới.
          </p>
        </div>
      </div>
      <div class="head-right">
        <div class="live-status">
          <span class="pulse-dot"></span>
          <span>System Online</span>
        </div>
      </div>
    </header>

    <!-- Thẻ Thống kê nhanh gọn -->
    <div class="stats-grid">
      <div v-for="stat in quickStats" :key="stat.id" class="stat-card">
        <div class="stat-icon-wrapper" :class="stat.bgClass">
          <component
            :is="stat.icon"
            class="stat-icon"
            :class="stat.colorClass"
          />
        </div>
        <div class="stat-info">
          <span class="stat-label">{{ stat.label }}</span>
          <span class="stat-value">{{ stat.value }}</span>
        </div>
      </div>
    </div>

    <!-- Trình điều khiển Tracking (Map & Data) -->
    <div class="workbench-container glass-panel">
      <TrackingWorkbench
        role="admin"
        title="Truy vết Radar Hành trình 🗺️"
        subtitle="Nhập ID Chuyến xe để lấy định vị GPS trực tiếp trên bản đồ MapLibre (OpenMapVN)."
      />
    </div>
  </div>
</template>

<style scoped>
.admin-tracking-dashboard {
  padding: 1.5rem;
  min-height: 100vh;
  background: #f8fafc; /* Nền xám xanh nhẹ hiện đại */
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* === Header === */
.dashboard-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}
.head-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.icon-wrap {
  width: 50px;
  height: 50px;
  border-radius: 14px;
  background: linear-gradient(135deg, #0ea5e9, #2563eb);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
}
.head-icon {
  width: 24px;
  height: 24px;
}
.main-title {
  margin: 0 0 0.25rem 0;
  font-size: 1.7rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.02em;
}
.sub-title {
  margin: 0;
  color: #64748b;
  font-size: 0.95rem;
  font-weight: 500;
}
.live-status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #0f172a;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}
.pulse-dot {
  width: 8px;
  height: 8px;
  background: #22c55e;
  border-radius: 50%;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2);
  animation: pulsing 2s infinite;
}
@keyframes pulsing {
  0% {
    box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4);
  }
  70% {
    box-shadow: 0 0 0 6px rgba(34, 197, 94, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
  }
}

/* === Stats Grid === */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}
.stat-card {
  background: white;
  border: 1px solid rgba(226, 232, 240, 0.8);
  border-radius: 16px;
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
  transition:
    transform 0.2s,
    box-shadow 0.2s;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.06);
}
.stat-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.stat-icon {
  width: 24px;
  height: 24px;
}
.stat-info {
  display: flex;
  flex-direction: column;
}
.stat-label {
  font-size: 0.8rem;
  text-transform: uppercase;
  font-weight: 600;
  color: #64748b;
  letter-spacing: 0.05em;
  margin-bottom: 0.2rem;
}
.stat-value {
  font-size: 1.6rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1;
}

/* === Workbench Container === */
.workbench-container {
  background: white;
  border-radius: 20px;
  padding: 1.5rem;
  box-shadow:
    0 4px 6px -1px rgba(0, 0, 0, 0.02),
    0 10px 15px -3px rgba(0, 0, 0, 0.04);
  border: 1px solid rgba(226, 232, 240, 0.8);
  flex: 1;
}

/* Responsive */
@media (max-width: 768px) {
  .admin-tracking-dashboard {
    padding: 1rem;
  }
  .workbench-container {
    padding: 1rem;
  }
  .main-title {
    font-size: 1.3rem;
  }
}
</style>
