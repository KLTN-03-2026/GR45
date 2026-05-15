<script setup>
import { inject, ref, computed } from "vue";
import { useRoute } from "vue-router";
import { useOperatorStore } from "@/stores/operatorStore";
import {
  LayoutDashboard,
  Ticket,
  LifeBuoy,
  Map,
  BusFront,
  ShieldCheck,
  AlertTriangle,
  TrendingUp,
  Star,
  Settings,
  ChevronDown,
  Bus,
  Bell,
  Car,
  Users,
  FileText,
  Clock,
  BarChart2,
  Gift,
  Wallet,
} from "lucide-vue-next";

// Inject từ OperatorLayout
const isSidebarOpen = inject("isSidebarOpen");
const closeSidebar = inject("closeSidebar");
const route = useRoute();
const operatorStore = useOperatorStore();

// State mở dropdown menu
const activeDropdown = ref(null);

const toggleDropdown = (menuName) => {
  activeDropdown.value = activeDropdown.value === menuName ? null : menuName;
};

// Kiểm tra route con có nằm trong dropdown không
const isChildActive = (paths) => {
  return paths.some((p) => route.path.includes(p));
};

// Danh sách menu đầy đủ cho Nhà Xe
// permission: slug cần có để hiển thị mục menu này
// Nếu không có permission → luôn hiển thị (dashboard, ...)
const menuList = [
  {
    id: "dashboard",
    name: "Tổng quan",
    path: "/nha-xe/dashboard",
    icon: LayoutDashboard,
    // Không có permission → luôn hiển thị
  },

  // --- Vận hành ---
  {
    id: "tuyen-duong",
    name: "Tuyến đường",
    path: "/nha-xe/tuyen-duong",
    icon: Map,
    permission: "op-xem-tuyen-duong",
  },
  {
    id: "chuyen-xe",
    name: "Chuyến xe",
    path: "/nha-xe/chuyen-xe",
    icon: BusFront,
    permission: "op-xem-chuyen-xe",
  },
  {
    id: "tracking",
    name: "Giám sát xe",
    icon: Map,
    permission: "op-xem-tracking",
    children: [
      { name: "📡 Live Tracking", path: "/nha-xe/live-tracking", icon: Map },
      {
        name: "📋 Lịch sử hành trình",
        path: "/nha-xe/lich-su-hanh-trinh",
        icon: Map,
      },
    ],
    paths: ["/nha-xe/live-tracking", "/nha-xe/lich-su-hanh-trinh"],
  },
  {
    id: "ve",
    name: "Quản lý vé",
    path: "/nha-xe/ve",
    icon: Ticket,
    permission: "op-xem-ve",
  },
  {
    id: "voucher",
    name: "Voucher",
    path: "/nha-xe/voucher",
    icon: Gift,
    permission: "op-xem-voucher",
  },
  {
    id: "danh-gia",
    name: "Đánh giá chuyến xe",
    path: "/nha-xe/danh-gia",
    icon: Star,
    // Nhà xe luôn thấy; nhân viên nếu chưa có quyền riêng thì không hiển
  },

  // --- Nhân sự ---
  {
    id: "nhan-su",
    name: "Nhân sự",
    icon: Users,
    permission: "op-xem-nhan-vien", // Chỉ hiển khi có quyền
    children: [
      { name: "Nhân viên nhà xe", path: "/nha-xe/nhan-vien", icon: Users },
      { name: "Tài xế", path: "/nha-xe/tai-xe", icon: Car },
      { name: "Xe & Phương tiện", path: "/nha-xe/phuong-tien", icon: Bus },
    ],
    paths: ["/nha-xe/nhan-vien", "/nha-xe/tai-xe", "/nha-xe/phuong-tien"],
  },

  // --- Giám sát ---
  {
    id: "canh-bao",
    name: "Cảnh báo AI",
    path: "/nha-xe/canh-bao",
    icon: AlertTriangle,
    permission: "op-xem-bao-dong",
  },

  // --- Dịch vụ ---
  {
    id: "ho-tro",
    name: "Yêu cầu hỗ trợ",
    path: "/nha-xe/ho-tro",
    icon: LifeBuoy,
    // Nhà xe luôn thấy
  },

  // --- Báo cáo ---
  {
    id: "thong-ke",
    name: "Thống kê & Báo cáo",
    path: "/nha-xe/thong-ke",
    icon: TrendingUp,
    permission: "op-xem-thong-ke",
  },

  // --- Quản trị (chỉ chủ nhà xe) ---
  {
    id: "he-thong",
    name: "Hệ thống",
    icon: Settings,
    ownerOnly: true, // Chỉ chủ nhà xe mới thấy
    children: [
      { name: "Phân quyền", path: "/nha-xe/phan-quyen", icon: ShieldCheck },
      { name: "Cài đặt hệ thống", path: "/nha-xe/cai-dat", icon: Settings },
      { name: "Ví nhà xe", path: "/nha-xe/vi-nha-xe", icon: Wallet },
    ],
    paths: ["/nha-xe/phan-quyen", "/nha-xe/cai-dat", "/nha-xe/vi-nha-xe"],
  },
];

/**
 * Menu hiển thị sau khi lọc theo quyền.
 * - Nếu mục có ownerOnly: chỉ chủ nhà xe mới thấy.
 * - Nếu mục có permission: kiểm tra hasPermission.
 * - Không có cả hai: luôn hiển thị.
 */
const visibleMenu = computed(() =>
  menuList.filter((item) => {
    if (item.ownerOnly) return operatorStore.isOwner;
    if (item.permission) return operatorStore.hasPermission(item.permission);
    return true;
  })
);

// Thông tin hiển thị ở header sidebar
const sidebarUser = computed(() => {
  const u = operatorStore.user;
  if (!u) return { name: '--', sub: '', badge: '' };
  if (operatorStore.isEmployee) {
    return {
      name: u.ho_va_ten || u.email,
      sub: u.chuc_vu?.ten_chuc_vu || 'Nhân viên',
      badge: u.ten_nha_xe || u.ma_nha_xe,
    };
  }
  return {
    name: u.ten_nha_xe || u.email,
    sub: 'Chủ nhà xe',
    badge: u.ma_nha_xe,
  };
});
</script>

<template>
  <aside
    class="operator-sidebar scrollable-custom"
    :class="{ 'mobile-open': isSidebarOpen }"
  >
    <div class="sidebar-header">
      <div class="brand-logo">
        <div class="logo-icon">
          <Bus class="logo-svg" />
        </div>
        <div class="brand-text-group">
          <span class="brand-text">{{ sidebarUser.name }}</span>
          <span class="brand-sub">
            {{ sidebarUser.sub }}
            <span v-if="sidebarUser.badge" class="role-badge">{{ sidebarUser.badge }}</span>
          </span>
        </div>
      </div>
    </div>

    <!-- Label phân nhóm -->
    <div class="menu-section-label">VẬN HÀNH</div>

    <!-- Menu List -->
    <nav class="sidebar-menu">
      <template v-for="item in visibleMenu" :key="item.id">
        <!-- Phân nhóm label theo vị trí -->
        <div v-if="item.id === 'nhan-su'" class="menu-section-divider">
          <span>NHÂN SỰ</span>
        </div>
        <div v-if="item.id === 'canh-bao'" class="menu-section-divider">
          <span>GIÁM SÁT</span>
        </div>
        <div v-if="item.id === 'ho-tro'" class="menu-section-divider">
          <span>DỊCH VỤ</span>
        </div>
        <div v-if="item.id === 'thong-ke'" class="menu-section-divider">
          <span>BÁO CÁO</span>
        </div>
        <div v-if="item.id === 'he-thong'" class="menu-section-divider">
          <span>QUẢN TRỊ</span>
        </div>

        <!-- Link thường -->
        <RouterLink
          v-if="!item.children"
          :to="item.path"
          class="menu-item"
          active-class="active"
          @click="closeSidebar"
        >
          <component :is="item.icon" class="menu-icon" />
          <span class="menu-text">{{ item.name }}</span>
        </RouterLink>

        <!-- Dropdown -->
        <div v-else class="menu-dropdown">
          <button
            class="menu-item"
            :class="{
              'dropdown-active':
                activeDropdown === item.id || isChildActive(item.paths),
            }"
            @click="toggleDropdown(item.id)"
          >
            <component :is="item.icon" class="menu-icon" />
            <span class="menu-text">{{ item.name }}</span>
            <ChevronDown
              class="arrow-icon"
              :class="{ 'rotate-180': activeDropdown === item.id }"
            />
          </button>

          <div
            class="submenu"
            :class="{ 'submenu-open': activeDropdown === item.id }"
          >
            <RouterLink
              v-for="sub in item.children"
              :key="sub.path"
              :to="sub.path"
              class="submenu-item"
              active-class="sub-active"
              @click="closeSidebar"
            >
              <span class="submenu-dot"></span>
              <span class="submenu-text">{{ sub.name }}</span>
            </RouterLink>
          </div>
        </div>
      </template>
    </nav>

    <!-- Footer card -->
    <div class="sidebar-footer">
      <div class="footer-card">
        <div class="footer-icon">🚌</div>
        <h4>Smart Bus AI</h4>
        <p>Hệ thống nhà xe v1.0</p>
      </div>
    </div>
  </aside>
</template>

<style scoped>
/* Sidebar chính */
.operator-sidebar {
  width: 280px;
  background: linear-gradient(180deg, #0d4f35 0%, #0a3d28 100%);
  height: 100vh;
  position: relative;
  display: flex;
  flex-direction: column;
  z-index: 50;
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.08);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  overflow-y: auto;
}

/* Scrollbar tuỳ chỉnh */
.scrollable-custom::-webkit-scrollbar {
  width: 5px;
}
.scrollable-custom::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 10px;
}
.scrollable-custom:hover::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.4);
}

/* Header sidebar */
.sidebar-header {
  padding: 28px 24px 20px 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.brand-logo {
  display: flex;
  align-items: center;
  gap: 12px;
}
.logo-icon {
  background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 14px rgba(34, 197, 94, 0.4);
  flex-shrink: 0;
}
.logo-svg {
  width: 24px;
  height: 24px;
  color: white;
}
.brand-text-group {
  display: flex;
  flex-direction: column;
}
.brand-text {
  font-size: 18px;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -0.5px;
  line-height: 1.1;
}
.brand-sub {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.5);
  font-weight: 400;
  letter-spacing: 0.3px;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px;
}

.role-badge {
  display: inline-block;
  background: rgba(74, 222, 128, 0.2);
  color: #4ade80;
  border: 1px solid rgba(74, 222, 128, 0.3);
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
  padding: 1px 5px;
  letter-spacing: 0;
}

/* Label phân nhóm */
.menu-section-label {
  font-size: 10px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.35);
  letter-spacing: 1.5px;
  padding: 16px 24px 8px 24px;
}

/* Phân cách nhóm trong menu */
.menu-section-divider {
  font-size: 10px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.35);
  letter-spacing: 1.5px;
  padding: 16px 24px 6px 20px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.menu-section-divider::after {
  content: "";
  flex: 1;
  height: 1px;
  background: rgba(255, 255, 255, 0.08);
}

/* Nav menu */
.sidebar-menu {
  flex: 1;
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

/* Menu item */
.menu-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: rgba(255, 255, 255, 0.65);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  width: 100%;
  text-align: left;
  text-decoration: none;
}
.menu-item:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
}
.menu-item.active,
.menu-item.dropdown-active {
  background: linear-gradient(
    135deg,
    rgba(34, 197, 94, 0.25) 0%,
    rgba(22, 163, 74, 0.15) 100%
  );
  color: #4ade80;
  font-weight: 700;
  box-shadow: inset 0 0 0 1px rgba(74, 222, 128, 0.2);
}
.menu-icon {
  width: 20px;
  height: 20px;
  color: inherit;
  flex-shrink: 0;
}
.arrow-icon {
  width: 16px;
  height: 16px;
  margin-left: auto;
  transition: transform 0.3s ease;
}
.rotate-180 {
  transform: rotate(180deg);
}

/* Dropdown submenu */
.submenu {
  max-height: 0;
  overflow: hidden;
  transition:
    max-height 0.4s ease,
    opacity 0.3s ease;
  opacity: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-left: 36px;
}
.submenu-open {
  max-height: 300px;
  opacity: 1;
  margin-top: 2px;
}
.submenu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  color: rgba(255, 255, 255, 0.5);
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.2s;
}
.submenu-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: currentColor;
  opacity: 0.4;
  flex-shrink: 0;
}
.submenu-item:hover {
  color: rgba(255, 255, 255, 0.9);
  background: rgba(255, 255, 255, 0.06);
}
.submenu-item.sub-active {
  color: #4ade80;
  font-weight: 700;
  background: rgba(74, 222, 128, 0.1);
}
.submenu-item.sub-active .submenu-dot {
  opacity: 1;
  box-shadow: 0 0 6px rgba(74, 222, 128, 0.6);
}

/* Footer sidebar */
.sidebar-footer {
  padding: 20px 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}
.footer-card {
  background: linear-gradient(
    135deg,
    rgba(34, 197, 94, 0.2) 0%,
    rgba(16, 185, 129, 0.1) 100%
  );
  border: 1px solid rgba(74, 222, 128, 0.15);
  border-radius: 14px;
  padding: 16px;
  text-align: center;
  color: rgba(255, 255, 255, 0.85);
}
.footer-icon {
  font-size: 28px;
  margin-bottom: 8px;
}
.footer-card h4 {
  font-size: 14px;
  font-weight: 700;
  margin: 0 0 4px 0;
  color: #ffffff;
}
.footer-card p {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.4);
  margin: 0;
}

/* Mobile */
@media (max-width: 1024px) {
  .operator-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    transform: translateX(-100%);
  }
  .operator-sidebar.mobile-open {
    transform: translateX(0);
  }
}
</style>
