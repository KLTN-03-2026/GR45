<script setup>
import { inject, ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAdminStore } from '@/stores/adminStore.js'
import { 
  LayoutDashboard, 
  Users, 
  ShieldCheck, 
  Map, 
  Ticket, 
  LifeBuoy, 
  TrendingUp,
  ChevronDown,
  Bus,
  BusFront,
  User,
  UserCog,
  Settings,
  Database,
  Gift,
  Star
} from 'lucide-vue-next'

// Inject từ AdminLayout
const isSidebarOpen = inject('isSidebarOpen')
const closeSidebar = inject('closeSidebar')
const route = useRoute()
const adminStore = useAdminStore()

// Lịch sử state mở menu (chỉ mở 1 cái 1 lúc)
const activeDropdown = ref(null)

const toggleDropdown = (menuName) => {
  if (activeDropdown.value === menuName) {
    activeDropdown.value = null
  } else {
    activeDropdown.value = menuName
  }
}

// Kiểm tra route hien tai co nam trong menu cha dropdown hay ko
const isChildActive = (paths) => {
  return paths.some(p => route.path.includes(p))
}

const hasMenuPermission = (item) => {
  if (adminStore.isMaster === 1) return true

  if (Array.isArray(item.permissionSlugs) && item.permissionSlugs.length > 0) {
    return adminStore.hasAnyPermission(item.permissionSlugs)
  }

  if (Array.isArray(item.permissionHints) && item.permissionHints.length > 0) {
    return item.permissionHints.some((hint) =>
      adminStore.permissions.some((slug) => slug.includes(hint))
    )
  }

  return true
}

const closeSidebarSafely = () => {
  if (typeof closeSidebar === 'function') {
    closeSidebar()
  }
}

const menuList = [
  { id: 'dashboard', name: 'Dashboard', path: '/admin/dashboard', icon: LayoutDashboard },
  {
    id: 'tuyen-duong',
    name: 'Quản lý tuyến đường',
    path: '/admin/tuyen-duong',
    icon: Map,
    permissionSlugs: ['xem-tuyen-duong'],
    permissionHints: ['tuyen-duong'],
  },
  {
    id: 'chuyen-xe',
    name: 'Quản lý chuyến xe',
    path: '/admin/chuyen-xe',
    icon: BusFront,
    permissionSlugs: ['xem-chuyen-xe'],
    permissionHints: ['chuyen-xe'],
  },
  {
    id: 'tracking',
    name: 'Live tracking',
    path: '/admin/tracking',
    icon: Map,
    permissionSlugs: ['xem-tracking-chuyen-xe', 'xem-chuyen-xe'],
    permissionHints: ['tracking'],
  },
  {
    id: 've',
    name: 'Quản lý vé',
    path: '/admin/ve',
    icon: Ticket,
    permissionSlugs: ['xem-ve'],
    permissionHints: ['ve'],
  },
  {
    id: 'voucher',
    name: 'Quản lý voucher',
    path: '/admin/voucher',
    icon: Gift,
    permissionSlugs: ['xem-voucher'],
    permissionHints: ['voucher'],
  },
  {
    id: 'danh-gia',
    name: 'Đánh giá chuyến xe',
    path: '/admin/danh-gia',
    icon: Star,
  },
  
  // Dropdown quản lý user
  { 
    id: 'quan-ly', 
    name: 'Quản lý', 
    icon: Users,
    children: [
      {
        name: 'Nhân viên',
        path: '/admin/nhan-vien',
        icon: UserCog,
        permissionSlugs: ['xem-nhan-vien'],
        permissionHints: ['nhan-vien'],
      },
      {
        name: 'Nhà xe',
        path: '/admin/nha-xe',
        icon: Bus,
        permissionSlugs: ['xem-nha-xe'],
        permissionHints: ['nha-xe'],
      },
      {
        name: 'Tài xế',
        path: '/admin/tai-xe',
        icon: User,
        permissionSlugs: ['xem-tai-xe'],
        permissionHints: ['tai-xe'],
      },
      {
        name: 'Phương tiện',
        path: '/admin/phuong-tien',
        icon: BusFront,
        permissionSlugs: ['xem-xe'],
        permissionHints: ['xe', 'phuong-tien'],
      },
      {
        name: 'Khách hàng',
        path: '/admin/khach-hang',
        icon: Users,
        permissionSlugs: ['xem-khach-hang'],
        permissionHints: ['khach-hang'],
      },
    ],
    paths: ['/admin/nhan-vien', '/admin/nha-xe', '/admin/tai-xe', '/admin/phuong-tien', '/admin/khach-hang']
  },

  // Dropdown Hệ thống
  { 
    id: 'he-thong', 
    name: 'Hệ thống', 
    icon: Settings,
    children: [
      {
        name: 'Phân quyền',
        path: '/admin/phan-quyen',
        icon: ShieldCheck,
        permissionSlugs: ['xem-phan-quyen'],
        permissionHints: ['phan-quyen', 'chuc-nang'],
      },
      {
        name: 'Cấu hình chung',
        path: '/admin/cau-hinh',
        icon: Database,
        permissionSlugs: ['xem-cau-hinh'],
        permissionHints: ['cau-hinh'],
      },
    ],
    paths: ['/admin/phan-quyen', '/admin/cau-hinh']
  },

  {
    id: 'ho-tro',
    name: 'Quản lý hỗ trợ',
    path: '/admin/ho-tro',
    icon: LifeBuoy,
    permissionSlugs: ['xem-ho-tro'],
    permissionHints: ['ho-tro'],
  },
  {
    id: 'thong-ke',
    name: 'Báo cáo doanh thu',
    path: '/admin/thong-ke',
    icon: TrendingUp,
    permissionSlugs: ['xem-thong-ke'],
    permissionHints: ['thong-ke', 'doanh-thu'],
  },
]

const visibleMenuList = computed(() => {
  return menuList
    .map((item) => {
      if (!item.children) {
        return hasMenuPermission(item) ? item : null
      }

      const children = item.children.filter((child) => hasMenuPermission(child))
      if (children.length === 0) return null

      return {
        ...item,
        children,
        paths: children.map((child) => child.path),
      }
    })
    .filter(Boolean)
})

</script>

<template>
  <aside 
    class="sidebar scrollable-custom"
    :class="{ 'mobile-open': isSidebarOpen }"
  >
    <!-- Logo & Brand -->
    <div class="sidebar-header">
      <div class="brand-logo">
        <div class="logo-icon">Hệ Thống</div>
        <div class="brand-copy">
          <span class="brand-text">Admin Panel</span>
          <span class="brand-role">
            {{ adminStore.isMaster === 1 ? 'Super Admin' : (adminStore.chucVu || 'Nhân viên') }}
          </span>
        </div>
      </div>
    </div>

    <!-- Menu List -->
    <nav class="sidebar-menu">
      <template v-for="item in visibleMenuList" :key="item.id">
        <!-- Nếu là link bình thường -->
        <RouterLink 
          v-if="!item.children" 
          :to="item.path" 
          class="menu-item"
          active-class="active"
          @click="closeSidebarSafely"
        >
          <component :is="item.icon" class="menu-icon" />
          <span class="menu-text">{{ item.name }}</span>
        </RouterLink>

        <!-- Nếu là Dropdown -->
        <div v-else class="menu-dropdown">
          <button 
            class="menu-item" 
            :class="{ 'dropdown-active': activeDropdown === item.id || isChildActive(item.paths) }"
            @click="toggleDropdown(item.id)"
          >
            <component :is="item.icon" class="menu-icon" />
            <span class="menu-text">{{ item.name }}</span>
            <ChevronDown 
              class="arrow-icon" 
              :class="{ 'rotate-180': activeDropdown === item.id }" 
            />
          </button>
          
          <!-- Lưới dropdown (transition scale y smoothly) -->
          <div class="submenu" :class="{ 'submenu-open': activeDropdown === item.id }">
            <RouterLink 
              v-for="sub in item.children" 
              :key="sub.path"
              :to="sub.path"
              class="submenu-item"
              active-class="sub-active"
              @click="closeSidebarSafely"
            >
              <span class="submenu-dot"></span>
              <span class="submenu-text">{{ sub.name }}</span>
            </RouterLink>
          </div>
        </div>
      </template>

      <p v-if="visibleMenuList.length === 0" class="empty-menu-text">
        Tài khoản hiện tại chưa được cấp quyền chức năng.
      </p>

      <div class="divider"></div>
      
      <!-- Khác -->
      <div class="menu-title">KHÁC</div>
      <RouterLink to="/admin/cai-dat" class="menu-item" active-class="active" @click="closeSidebarSafely">
        <Settings class="menu-icon" />
        <span class="menu-text">Cài đặt cá nhân</span>
      </RouterLink>

    </nav>
    
    <!-- Upgrade block (Aesthetic touch) -->
    <div class="sidebar-footer">
      <div class="upgrade-card gradient-glass">
        <div class="upgrade-icon">🚀</div>
        <h4>Smart Bus AI</h4>
        <p>Phiên bản x.1.0 (Pro)</p>
      </div>
    </div>
  </aside>
</template>

<style scoped>
/* =========== Layout Sidebar Cơ bản =========== */
.sidebar {
  width: 280px;
  background-color: #ffffff;
  height: 100vh;
  position: relative;
  display: flex;
  flex-direction: column;
  z-index: 50;
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.04);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  overflow-y: auto;
}

/* Custom Scrollbar */
.scrollable-custom::-webkit-scrollbar {
  width: 5px;
}
.scrollable-custom::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}
.scrollable-custom:hover::-webkit-scrollbar-thumb {
  background: #94a3b8;
}

/* Header & Logo */
.sidebar-header {
  padding: 32px 24px 20px 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-bottom: 1px solid #f1f5f9;
}
.brand-logo {
  display: flex;
  align-items: center;
  gap: 12px;
}
.brand-copy {
  display: flex;
  flex-direction: column;
}
.logo-icon {
  background: linear-gradient(135deg, #4f46e5 0%, #818cf8 100%);
  color: white;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 10px;
  text-transform: uppercase;
  box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
}
.brand-text {
  font-size: 20px;
  font-weight: 700;
  color: #0F172A;
  letter-spacing: -0.5px;
}
.brand-role {
  font-size: 12px;
  color: #64748B;
  font-weight: 600;
}

/* Menu Chính */
.sidebar-menu {
  flex: 1;
  padding: 24px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.menu-title {
  font-size: 12px;
  font-weight: 700;
  color: #64748B;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin: 16px 0 8px 16px;
}
.empty-menu-text {
  padding: 12px 16px;
  font-size: 13px;
  color: #64748B;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  background: #f8fafc;
}
.divider {
  height: 1px;
  background-color: #f1f5f9;
  margin: 16px 8px;
}

/* Nút & Link Menu */
.menu-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 16px;
  border-radius: 12px;
  border: none;
  background: transparent;
  color: #475569;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.25s ease;
  width: 100%;
  text-align: left;
  border-left: 4px solid transparent;
}
.menu-item:hover {
  background-color: #F8FAFC;
  color: #4F46E5;
}
.menu-item.active, .menu-item.dropdown-active {
  background-color: #EEF2FF;
  color: #4338CA;
  font-weight: 700;
  border-left: 4px solid #4F46E5;
  border-radius: 0 12px 12px 0;
}
.menu-icon {
  width: 22px;
  height: 22px;
  color: inherit;
  transition: color 0.2s ease;
}
.arrow-icon {
  width: 18px;
  height: 18px;
  margin-left: auto;
  transition: transform 0.3s ease;
}
.rotate-180 {
  transform: rotate(180deg);
}

/* Menu Con (Dropdown) */
.submenu {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.4s ease-in-out, opacity 0.3s ease;
  opacity: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-left: 42px;
}
.submenu-open {
  max-height: 300px;
  opacity: 1;
  margin-top: 4px;
}
.submenu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  color: #64748B;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.2s;
}
.submenu-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: currentColor;
  opacity: 0.5;
}
.submenu-item:hover {
  color: #4F46E5;
  background-color: #F8FAFC;
}
.submenu-item.sub-active {
  color: #4338CA;
  font-weight: 700;
  background-color: #EEF2FF;
}
.submenu-item.sub-active .submenu-dot {
  opacity: 1;
  box-shadow: 0 0 8px rgba(79, 70, 229, 0.5);
}

/* Sidebar Footer (Aesthetic Card) */
.sidebar-footer {
  padding: 24px;
}
.upgrade-card {
  padding: 20px;
  border-radius: 16px;
  text-align: center;
  color: white;
  position: relative;
  overflow: hidden;
}
.gradient-glass {
  background: linear-gradient(135deg, #4F46E5 0%, #818cf8 100%);
  box-shadow: 0 10px 20px rgba(79, 70, 229, 0.25);
}
.upgrade-icon {
  font-size: 28px;
  margin-bottom: 8px;
}
.upgrade-card h4 {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 4px;
}
.upgrade-card p {
  font-size: 12px;
  opacity: 0.8;
}

/* =========== Mobile Responsive =========== */
@media (max-width: 1024px) {
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    transform: translateX(-100%);
  }
  .sidebar.mobile-open {
    transform: translateX(0);
  }
}
</style>
