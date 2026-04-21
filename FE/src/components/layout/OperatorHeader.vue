<script setup>
import { inject, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useOperatorStore } from '@/stores/operatorStore'
import {
  Menu,
  Search,
  Bell,
  UserCircle,
  LogOut,
  Settings,
  ChevronDown,
  AlertTriangle,
  CheckCircle,
  Info,
  Zap
} from 'lucide-vue-next'

// Inject từ OperatorLayout
const toggleSidebar = inject('toggleSidebar')

const router = useRouter()
const operatorStore = useOperatorStore()

// Thông tin nhà xe
const operatorUser = computed(() => operatorStore.user || { ten_nha_xe: 'Nhà Xe', role: 'Quản lý' })

// --- Profile dropdown ---
const isProfileMenuOpen = ref(false)
const toggleProfileMenu = () => {
  isProfileMenuOpen.value = !isProfileMenuOpen.value
  if (isProfileMenuOpen.value) isNotifyMenuOpen.value = false
}

const handleLogout = () => {
  operatorStore.logout()
  router.push('/auth/operator-login')
}

// --- Notification dropdown ---
const isNotifyMenuOpen = ref(false)
const toggleNotifyMenu = () => {
  isNotifyMenuOpen.value = !isNotifyMenuOpen.value
  if (isNotifyMenuOpen.value) isProfileMenuOpen.value = false
}

// Lấy thông báo từ store và kết hợp với thông báo mẫu
const notifications = computed(() => {
  const storeNotes = operatorStore.notifications;
  return [...storeNotes, ...sampleNotifications.value];
});

const sampleNotifications = ref([
  {
    id: 1,
    type: 'alert',
    title: 'Hệ thống AI',
    message: 'Hệ thống giám sát tài xế đang hoạt động.',
    time: 'Bắt đầu phiên',
    read: true,
    icon: 'alert'
  },
  {
    id: 2,
    type: 'info',
    title: 'Hỗ trợ',
    message: 'Chào mừng bạn đến với bảng điều khiển Nhà Xe.',
    time: 'Hôm nay',
    read: true,
    icon: 'info'
  }
])

const unreadCount = computed(() => notifications.value.filter(n => !n.read).length)

// Đánh dấu tất cả đã đọc
const markAllRead = () => {
  notifications.value.forEach(n => n.read = true)
}

// Đóng dropdown khi click ngoài
const closeAll = () => {
  isProfileMenuOpen.value = false
  isNotifyMenuOpen.value = false
}
</script>

<template>
  <header class="operator-header glass-header" @click.self="closeAll">

    <!-- Phần trái: hamburger + tìm kiếm -->
    <div class="header-left">
      <button class="hamburger-btn" @click="toggleSidebar">
        <Menu class="icon-menu" />
      </button>

      <div class="search-box">
        <Search class="search-icon" />
        <input type="text" placeholder="Tìm kiếm chuyến xe, vé, tài xế..." class="search-input" />
      </div>
    </div>

    <!-- Phần phải: Notification + Profile -->
    <div class="header-right">

      <!-- Nút thông báo -->
      <div class="notify-container">
        <button class="action-btn notify-btn" @click.stop="toggleNotifyMenu">
          <Bell class="action-icon" />
          <span v-if="unreadCount > 0" class="notify-badge">{{ unreadCount }}</span>
        </button>

        <!-- Dropdown Thông Báo -->
        <transition name="fade-scale">
          <div v-if="isNotifyMenuOpen" class="notify-dropdown" @click.stop>
            <!-- Header dropdown -->
            <div class="dropdown-header flex-between">
              <div class="notify-header-left">
                <span class="dropdown-title">Thông báo</span>
                <span v-if="unreadCount > 0" class="unread-chip">{{ unreadCount }} mới</span>
              </div>
              <button class="mark-read-btn" @click="markAllRead">Đánh dấu đã đọc</button>
            </div>

            <!-- Danh sách thông báo -->
            <div class="notify-list">
              <div
                v-for="note in notifications"
                :key="note.id"
                class="notify-item"
                :class="{ 'unread': !note.read, [`type-${note.type}`]: true }"
              >
                <!-- Icon loại thông báo -->
                <div class="notify-icon-wrap" :class="`icon-${note.type}`">
                  <AlertTriangle v-if="note.icon === 'alert'" class="n-icon" />
                  <CheckCircle v-else-if="note.icon === 'success'" class="n-icon" />
                  <Info v-else class="n-icon" />
                </div>
                <div class="notify-content">
                  <p class="notify-title">{{ note.title }}</p>
                  <p class="notify-desc">{{ note.message }}</p>
                  <span class="notify-time">{{ note.time }}</span>
                </div>
                <div v-if="!note.read" class="unread-dot"></div>
              </div>
            </div>

            <!-- Footer -->
            <div class="dropdown-footer">
              <button class="view-all-btn" @click="router.push('/nha-xe/ho-tro'); isNotifyMenuOpen = false">
                Xem tất cả thông báo
              </button>
            </div>
          </div>
        </transition>
      </div>

      <!-- Divider -->
      <div class="divider-vertical"></div>

      <!-- Profile -->
      <div class="profile-container">
        <div class="profile-trigger" @click.stop="toggleProfileMenu">
          <div class="avatar">
            <UserCircle class="avatar-icon" />
          </div>
          <div class="user-info">
            <span class="user-name">{{ operatorUser.ten_nha_xe || operatorUser.name || 'Nhà Xe' }}</span>
            <span class="user-role">Quản lý nhà xe</span>
          </div>
          <ChevronDown class="arrow-down" :class="{ 'rotate': isProfileMenuOpen }" />
        </div>

        <!-- Dropdown Profile -->
        <transition name="fade-scale">
          <div v-if="isProfileMenuOpen" class="profile-dropdown" @click.stop>
            <div class="dropdown-header">
              <div class="profile-info-mini">
                <div class="avatar-lg">
                  <UserCircle class="avatar-icon-lg" />
                </div>
                <div>
                  <p class="profile-name">{{ operatorUser.ten_nha_xe || operatorUser.name || 'Nhà Xe' }}</p>
                  <p class="profile-email">{{ operatorUser.email || 'operator@smartbus.vn' }}</p>
                </div>
              </div>
            </div>
            <div class="dropdown-body">
              <button class="dropdown-item" @click="router.push('/nha-xe/cai-dat'); isProfileMenuOpen = false">
                <Settings class="drop-icon" />
                Cài đặt hệ thống
              </button>
              <button class="dropdown-item text-danger" @click="handleLogout">
                <LogOut class="drop-icon" />
                Đăng xuất
              </button>
            </div>
          </div>
        </transition>
      </div>

    </div>
  </header>
</template>

<style scoped>
/* ============ Header Layout ============ */
.operator-header {
  height: 72px;
  min-height: 72px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  margin: 16px 24px 0 24px;
  border-radius: 16px;
  position: relative;
  z-index: 30;
}

.glass-header {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow: 0 4px 20px rgba(0, 80, 40, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.6);
}

/* Trái */
.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
}

.hamburger-btn {
  display: none;
  background: none;
  border: none;
  cursor: pointer;
  color: #0d4f35;
  padding: 8px;
  border-radius: 8px;
  transition: background 0.2s;
}
.hamburger-btn:hover { background: rgba(0,0,0,0.05); }
.icon-menu { width: 24px; height: 24px; }

.search-box {
  display: flex;
  align-items: center;
  background-color: #f0fdf4;
  border-radius: 30px;
  padding: 10px 16px;
  min-width: 280px;
  transition: box-shadow 0.3s, background 0.3s;
  border: 1px solid #dcfce7;
}
.search-box:focus-within {
  box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.25);
  background-color: #fff;
}
.search-icon { width: 18px; height: 18px; color: #16a34a; margin-right: 12px; }
.search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  color: #0d4f35;
  width: 100%;
}
.search-input::placeholder { color: #86efac; }

/* Phải */
.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.action-btn {
  background: white;
  border: none;
  border-radius: 50%;
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  position: relative;
  color: #16a34a;
  box-shadow: 0 2px 10px rgba(0,0,0,0.04);
  transition: all 0.2s ease;
  border: 1px solid #dcfce7;
}
.action-btn:hover {
  color: #0d4f35;
  transform: translateY(-2px);
  box-shadow: 0 4px 14px rgba(0,0,0,0.08);
}
.action-icon { width: 20px; height: 20px; }

.notify-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
  font-size: 10px;
  font-weight: 700;
  min-width: 16px;
  height: 16px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid white;
  padding: 0 2px;
}

/* ======= Notification Dropdown ======= */
.notify-container { position: relative; }

.notify-dropdown {
  position: absolute;
  top: calc(100% + 12px);
  right: -20px;
  width: 360px;
  background: white;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
  border: 1px solid #f0fdf4;
  z-index: 100;
  overflow: hidden;
}

.dropdown-header {
  padding: 16px 20px;
  border-bottom: 1px solid #f0fdf4;
}
.flex-between {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.notify-header-left {
  display: flex;
  align-items: center;
  gap: 8px;
}
.dropdown-title {
  font-size: 15px;
  font-weight: 700;
  color: #0d4f35;
}
.unread-chip {
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: white;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 10px;
}
.mark-read-btn {
  font-size: 12px;
  color: #16a34a;
  background: none;
  border: none;
  cursor: pointer;
  font-weight: 600;
}
.mark-read-btn:hover { text-decoration: underline; }

/* Danh sách notify */
.notify-list {
  max-height: 360px;
  overflow-y: auto;
}
.notify-item {
  display: flex;
  align-items: flex-start;
  padding: 14px 20px;
  gap: 12px;
  border-bottom: 1px solid #f8fafc;
  cursor: pointer;
  transition: background 0.2s;
  position: relative;
}
.notify-item:hover { background: #f8fafc; }
.notify-item.unread { background: #f0fdf4; }
.notify-item.unread:hover { background: #dcfce7; }

.notify-icon-wrap {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.icon-alert { background: #fef2f2; color: #ef4444; }
.icon-success { background: #f0fdf4; color: #16a34a; }
.icon-warning { background: #fffbeb; color: #f59e0b; }
.icon-ticket, .icon-info { background: #eff6ff; color: #3b82f6; }

.n-icon { width: 18px; height: 18px; color: inherit; }

.notify-content { flex: 1; }
.notify-title {
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 3px 0;
  line-height: 1.3;
}
.notify-desc {
  font-size: 12px;
  color: #64748b;
  margin: 0 0 4px 0;
  line-height: 1.4;
}
.notify-time { font-size: 11px; color: #94a3b8; }

.unread-dot {
  width: 8px;
  height: 8px;
  background: #22c55e;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 6px;
}

.dropdown-footer {
  padding: 12px 20px;
  text-align: center;
  border-top: 1px solid #f0fdf4;
}
.view-all-btn {
  background: none;
  border: none;
  color: #16a34a;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}
.view-all-btn:hover { text-decoration: underline; }

/* ======= Divider ======= */
.divider-vertical {
  width: 1px;
  height: 24px;
  background: #dcfce7;
}

/* ======= Profile ======= */
.profile-container { position: relative; cursor: pointer; }

.profile-trigger {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 12px 6px 6px;
  border-radius: 40px;
  transition: background 0.2s;
}
.profile-trigger:hover { background: #f0fdf4; }

.avatar {
  background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.avatar-icon { width: 24px; height: 24px; color: white; }

.user-info { display: flex; flex-direction: column; }
.user-name { font-size: 14px; font-weight: 700; color: #0d4f35; }
.user-role { font-size: 11px; color: #16a34a; }

.arrow-down {
  width: 16px;
  height: 16px;
  color: #16a34a;
  transition: transform 0.3s;
}
.arrow-down.rotate { transform: rotate(180deg); }

/* Profile Dropdown */
.profile-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 260px;
  background: white;
  border-radius: 18px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
  border: 1px solid #f0fdf4;
  z-index: 100;
  overflow: hidden;
}

.profile-info-mini {
  display: flex;
  align-items: center;
  gap: 12px;
}
.avatar-lg {
  background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
  width: 46px;
  height: 46px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.avatar-icon-lg { width: 28px; height: 28px; color: white; }
.profile-name { font-size: 14px; font-weight: 700; color: #0d4f35; margin: 0 0 2px 0; }
.profile-email { font-size: 11px; color: #64748b; margin: 0; }

.dropdown-body { padding: 8px 0; }
.dropdown-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  background: transparent;
  border: none;
  font-size: 14px;
  font-weight: 500;
  color: #1e3a2f;
  cursor: pointer;
  transition: background 0.2s;
}
.dropdown-item:hover { background: #f0fdf4; color: #16a34a; }
.text-danger { color: #ef4444 !important; }
.text-danger:hover { background: #fef2f2 !important; color: #dc2626 !important; }
.drop-icon { width: 18px; height: 18px; }

/* Animations */
.fade-scale-enter-active, .fade-scale-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-scale-enter-from, .fade-scale-leave-to {
  opacity: 0;
  transform: scale(0.95) translateY(-8px);
}

/* Mobile */
@media (max-width: 1024px) {
  .operator-header {
    margin: 0;
    border-radius: 0;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
  }
  .hamburger-btn { display: block; }
  .search-box { display: none; }
  .user-info { display: none; }
  .profile-trigger { padding: 0; }
  .arrow-down { display: none; }
  .notify-dropdown { width: 300px; right: -60px; }
}
</style>
