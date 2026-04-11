<script setup>
import { inject, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAdminStore } from '@/stores/adminStore'
import { 
  Menu, 
  Search, 
  Bell, 
  UserCircle, 
  LogOut, 
  Settings,
  ChevronDown 
} from 'lucide-vue-next'

// Inject the toggler from Layout
const toggleSidebar = inject('toggleSidebar')

const router = useRouter()
const adminStore = useAdminStore()

// Lấy thông tin admin
const adminUser = computed(() => adminStore.user || { name: 'Admin', role: 'Super Admin' })

// State dropdown profile
const isProfileMenuOpen = ref(false)
const toggleProfileMenu = () => {
  isProfileMenuOpen.value = !isProfileMenuOpen.value
}

const handleLogout = () => {
  adminStore.logout()
  router.push('/auth/login') // Điều hướng về login (tuỳ theo cấu hình route)
}

// State dropdown thông báo & Dữ liệu mẫu
const isNotifyMenuOpen = ref(false)
const notifications = ref([
  { id: 1, title: 'Vé mới được đặt', message: 'Hành khách Nguyễn Văn A vừa đặt 2 vé đi Đà Lạt.', time: '5 phút trước', read: false },
  { id: 2, title: 'Cảnh báo tài xế', message: 'Tài xế Trần B (CX-102) có dấu hiệu buồn ngủ.', time: '12 phút trước', read: false },
  { id: 3, title: 'Cập nhật hệ thống', message: 'Hệ thống AI nhận diện vừa được nâng cấp model.', time: '1 giờ trước', read: true },
])
const unreadCount = computed(() => notifications.value.filter(n => !n.read).length)
</script>

<template>
  <header class="admin-header glass-header">
    
    <!-- Mobile Hamburger & Breadcrumb / Search -->
    <div class="header-left">
      <button class="hamburger-btn" @click="toggleSidebar">
        <Menu class="icon-menu" />
      </button>

      <!-- Thanh tìm kiếm mở rộng (Ẩn trên mobile nhỏ) -->
      <div class="search-box">
        <Search class="search-icon" />
        <input type="text" placeholder="Tìm kiếm nhanh..." class="search-input" />
      </div>
    </div>
    
    <!-- Header Right Actions -->
    <div class="header-right">
      
      <!-- Nút thông báo -->
      <div class="notify-container" @click="isNotifyMenuOpen = !isNotifyMenuOpen">
        <button class="action-btn notify-btn">
          <Bell class="action-icon" />
          <span v-if="unreadCount > 0" class="notify-badge">{{ unreadCount }}</span>
        </button>

        <!-- Dropdown Thông Báo -->
        <transition name="fade-scale">
          <div v-if="isNotifyMenuOpen" class="profile-dropdown notify-dropdown" @click.stop>
            <div class="dropdown-header flex-between">
              <span class="dropdown-title">Thông báo mới</span>
              <button class="mark-read-text" @click="isNotifyMenuOpen = false">Đóng</button>
            </div>
            <div class="dropdown-body notify-list">
              <div 
                v-for="note in notifications" 
                :key="note.id" 
                class="notify-item" 
                :class="{ 'unread': !note.read }"
              >
                <div class="notify-dot" v-if="!note.read"></div>
                <div class="notify-content">
                  <p class="notify-text-title">{{ note.title }}</p>
                  <p class="notify-text-desc">{{ note.message }}</p>
                  <span class="notify-time">{{ note.time }}</span>
                </div>
              </div>
            </div>
            <div class="dropdown-footer">
              <button class="view-all-btn">Xem tất cả thông báo</button>
            </div>
          </div>
        </transition>
      </div>

      <!-- Phân cách -->
      <div class="divider-vertical"></div>

      <!-- User Profile Dropdown -->
      <div class="profile-container" @click="toggleProfileMenu">
        <!-- Avatar/Info -->
        <div class="profile-trigger">
          <div class="avatar">
            <UserCircle class="avatar-icon" />
          </div>
          <div class="user-info">
            <span class="user-name">{{ adminUser.name }}</span>
            <span class="user-role">{{ adminUser.role }}</span>
          </div>
          <ChevronDown class="arrow-down" :class="{ 'rotate': isProfileMenuOpen }" />
        </div>

        <!-- Dropdown Menu -->
        <transition name="fade-scale">
          <div v-if="isProfileMenuOpen" class="profile-dropdown">
            <div class="dropdown-header">
              <span class="dropdown-title">Tài khoản của bạn</span>
            </div>
            <div class="dropdown-body">
              <button class="dropdown-item" @click="router.push('/admin/cai-dat')">
                <Settings class="drop-icon" /> Cài đặt cá nhân
              </button>
              <button class="dropdown-item text-danger" @click="handleLogout">
                <LogOut class="drop-icon" /> Đăng xuất
              </button>
            </div>
          </div>
        </transition>
      </div>

    </div>
  </header>
</template>

<style scoped>
/* =========== Layout Header =========== */
.admin-header {
  height: 80px;
  min-height: 80px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  margin: 16px 24px 0 24px; /* Lề ngoài tạo cảm giác nổi */
  border-radius: 16px;
  position: relative;
  z-index: 30;
  transition: all 0.3s ease;
}

/* Glassmorphism Effect */
.glass-header {
  background: rgba(255, 255, 255, 0.75);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  box-shadow: 0px 4px 14px rgba(0, 0, 0, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.5);
}

/* Nửa trái Header */
.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
}

.hamburger-btn {
  display: none; /* Mặc định ẩn trên Desktop */
  background: none;
  border: none;
  cursor: pointer;
  color: #0F172A;
  padding: 8px;
  border-radius: 8px;
  transition: background 0.2s;
}
.hamburger-btn:hover {
  background: rgba(0, 0, 0, 0.05);
}
.icon-menu {
  width: 24px;
  height: 24px;
}

/* Thanh tìm kiếm */
.search-box {
  display: flex;
  align-items: center;
  background-color: #F1F5F9;
  border-radius: 30px;
  padding: 10px 16px;
  min-width: 280px;
  transition: box-shadow 0.3s ease;
}
.search-box:focus-within {
  box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
  background-color: #fff;
}
.search-icon {
  width: 18px;
  height: 18px;
  color: #64748B;
  margin-right: 12px;
}
.search-input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  color: #0F172A;
  width: 100%;
}
.search-input::placeholder {
  color: #94A3B8;
}

/* Nửa phải Header */
.header-right {
  display: flex;
  align-items: center;
  gap: 20px;
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
  color: #64748B;
  box-shadow: 0 2px 10px rgba(0,0,0,0.02);
  transition: all 0.2s ease;
}
.action-btn:hover {
  color: #4F46E5;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.action-icon {
  width: 20px;
  height: 20px;
}
.notify-badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background-color: #FF5B5B;
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
}

/* Container notify */
.notify-container {
  position: relative;
}
.notify-dropdown {
  width: 320px !important;
  right: -20px !important; /* Center with button roughly */
}
.flex-between {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.mark-read-text {
  font-size: 12px;
  color: #4F46E5;
  background: none;
  border: none;
  cursor: pointer;
  font-weight: 600;
}
.mark-read-text:hover {
  text-decoration: underline;
}
.notify-list {
  max-height: 350px;
  overflow-y: auto;
  padding: 0 !important;
}
.notify-item {
  display: flex;
  padding: 16px;
  gap: 12px;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  transition: background 0.2s;
  position: relative;
}
.notify-item:hover {
  background: #F8FAFC;
}
.notify-item.unread {
  background: #EEF2FF;
}
.notify-item.unread:hover {
  background: #E0E7FF;
}
.notify-dot {
  width: 8px;
  height: 8px;
  background: #4F46E5;
  border-radius: 50%;
  margin-top: 6px;
  flex-shrink: 0;
}
.notify-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.notify-text-title {
  font-size: 13px;
  font-weight: 700;
  color: #0F172A;
  margin: 0;
}
.notify-text-desc {
  font-size: 12px;
  color: #64748B;
  margin: 0;
  line-height: 1.4;
}
.notify-time {
  font-size: 11px;
  color: #94A3B8;
}
.dropdown-footer {
  padding: 12px;
  text-align: center;
  border-top: 1px solid #f1f5f9;
}
.view-all-btn {
  background: none;
  border: none;
  color: #4F46E5;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.view-all-btn:hover {
  text-decoration: underline;
}

.divider-vertical {
  width: 1px;
  height: 24px;
  background-color: #e2e8f0;
}

/* User Profile */
.profile-container {
  position: relative;
  cursor: pointer;
}
.profile-trigger {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 12px 6px 6px;
  border-radius: 30px;
  transition: background 0.2s ease;
}
.profile-trigger:hover {
  background: rgba(0,0,0,0.02);
}
.avatar {
  background: linear-gradient(135deg, #00B4DB 0%, #0083B0 100%);
  color: white;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.avatar-icon {
  width: 24px;
  height: 24px;
}
.user-info {
  display: flex;
  flex-direction: column;
}
.user-name {
  font-size: 14px;
  font-weight: 700;
  color: #0F172A;
}
.user-role {
  font-size: 12px;
  color: #64748B;
}
.arrow-down {
  width: 16px;
  height: 16px;
  color: #64748B;
  transition: transform 0.3s ease;
}
.arrow-down.rotate {
  transform: rotate(180deg);
}

/* Profile Dropdown */
.profile-dropdown {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 220px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
  border: 1px solid #f1f5f9;
  z-index: 100;
  overflow: hidden;
}
.dropdown-header {
  padding: 16px;
  border-bottom: 1px solid #f1f5f9;
}
.dropdown-title {
  font-size: 13px;
  font-weight: 700;
  color: #64748B;
  text-transform: uppercase;
}
.dropdown-body {
  padding: 8px 0;
}
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
  color: #1E293B;
  cursor: pointer;
  transition: background 0.2s;
}
.dropdown-item:hover {
  background-color: #f8fafc;
  color: #4F46E5;
}
.text-danger {
  color: #FF5B5B;
}
.text-danger:hover {
  background-color: #FEF2F2;
  color: #DC2626;
}
.drop-icon {
  width: 18px;
  height: 18px;
}

/* Animations */
.fade-scale-enter-active,
.fade-scale-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-scale-enter-from,
.fade-scale-leave-to {
  opacity: 0;
  transform: scale(0.95) translateY(-10px);
}

/* =========== Mobile Responsive =========== */
@media (max-width: 1024px) {
  .admin-header {
    margin: 0;
    border-radius: 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  }
  .hamburger-btn {
    display: block;
  }
  .search-box {
    display: none; /* Ẩn search trên mobile cho gọn, bật icon search riêng nếu muốn */
  }
  .user-info {
    display: none;
  }
  .profile-trigger {
    padding: 0;
  }
  .arrow-down {
    display: none;
  }
}
</style>
