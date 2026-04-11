<script setup>
import { inject, ref } from 'vue'
import { useRoute } from 'vue-router'
import { 
  LayoutDashboard, 
  Map, 
  LifeBuoy, 
  Settings,
  ChevronDown
} from 'lucide-vue-next'

// Inject từ DriverLayout
const isSidebarOpen = inject('isSidebarOpen')
const closeSidebar = inject('closeSidebar')
const route = useRoute()

const activeDropdown = ref(null)

const toggleDropdown = (menuName) => {
  if (activeDropdown.value === menuName) {
    activeDropdown.value = null
  } else {
    activeDropdown.value = menuName
  }
}

const isChildActive = (paths) => {
  return paths.some(p => route.path.includes(p))
}

const menuList = [
  { id: 'dashboard', name: 'Dashboard', path: '/tai-xe/dashboard', icon: LayoutDashboard },
  { id: 'lich-trinh', name: 'Lịch trình chuyến xe', path: '/tai-xe/lich-trinh', icon: Map },
  { id: 'ho-tro', name: 'Hỗ trợ khẩn cấp', path: '/tai-xe/ho-tro', icon: LifeBuoy }
]
</script>

<template>
  <aside 
    class="sidebar scrollable-custom"
    :class="{ 'mobile-open': isSidebarOpen }"
  >
    <!-- Logo & Brand -->
    <div class="sidebar-header">
      <div class="brand-logo">
        <div class="logo-icon">Tài Xế</div>
        <span class="brand-text">Driver App</span>
      </div>
    </div>

    <!-- Menu List -->
    <nav class="sidebar-menu">
      <template v-for="item in menuList" :key="item.id">
        <!-- Link bình thường -->
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

        <!-- Dropdown nếu có -->
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
          
          <div class="submenu" :class="{ 'submenu-open': activeDropdown === item.id }">
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

      <div class="divider"></div>
      
      <!-- Khác -->
      <div class="menu-title">CÁ NHÂN</div>
      <RouterLink to="/tai-xe/cai-dat" class="menu-item" active-class="active" @click="closeSidebar">
        <Settings class="menu-icon" />
        <span class="menu-text">Cài đặt cá nhân</span>
      </RouterLink>

    </nav>
    
    <div class="sidebar-footer">
      <div class="upgrade-card gradient-glass">
        <div class="upgrade-icon">🚌</div>
        <h4>Chuyến xe an toàn</h4>
        <p>Hệ thống hỗ trợ lái xe AI</p>
      </div>
    </div>
  </aside>
</template>

<style scoped>
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
.logo-icon {
  background: linear-gradient(135deg, #059669 0%, #34d399 100%);
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
  box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3);
}
.brand-text {
  font-size: 20px;
  font-weight: 700;
  color: #0F172A;
  letter-spacing: -0.5px;
}

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
.divider {
  height: 1px;
  background-color: #f1f5f9;
  margin: 16px 8px;
}

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
  color: #059669;
}
.menu-item.active, .menu-item.dropdown-active {
  background-color: #ecfdf5;
  color: #047857;
  font-weight: 700;
  border-left: 4px solid #059669;
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
  color: #059669;
  background-color: #F8FAFC;
}
.submenu-item.sub-active {
  color: #047857;
  font-weight: 700;
  background-color: #ecfdf5;
}
.submenu-item.sub-active .submenu-dot {
  opacity: 1;
  box-shadow: 0 0 8px rgba(5, 150, 105, 0.5);
}

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
  background: linear-gradient(135deg, #059669 0%, #34d399 100%);
  box-shadow: 0 10px 20px rgba(5, 150, 105, 0.25);
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
