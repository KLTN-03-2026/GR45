<script setup>
import { ref, provide, onMounted } from 'vue'
import AdminHeader from '@/components/layout/AdminHeader.vue'
import AdminSidebar from '@/components/layout/AdminSidebar.vue'
import { useAdminStore } from '@/stores/adminStore.js'

// Trạng thái mở/đóng Sidebar trên Mobile
const isSidebarOpen = ref(false)

// Function toggle sidebar
const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value
}

// Hàm đóng sidebar (dùng cho mobile overlay)
const closeSidebar = () => {
  isSidebarOpen.value = false
}

const adminStore = useAdminStore()

onMounted(async () => {
  if (adminStore.isLoggedIn) {
    await adminStore.fetchPermissions({ silent: true })
  }
})

// Provide để các component con có thể tuỳ ý gọi nếu cần
provide('isSidebarOpen', isSidebarOpen)
provide('toggleSidebar', toggleSidebar)
provide('closeSidebar', closeSidebar)
</script>

<template>
  <div class="admin-layout-wrapper">
    <!-- Sidebar -->
    <AdminSidebar />

    <!-- Khu vực Main Content -->
    <div class="admin-main-container">
      <AdminHeader />
      
      <!-- Vùng hiển thị nội dung chính -->
      <main class="admin-content-area">
        <div class="content-wrapper">
          <RouterView />
        </div>
      </main>
    </div>

    <!-- Lớp phủ Overlay (Chỉ hiện trên Mobile khi mở Sidebar) -->
    <div 
      class="mobile-overlay"
      :class="{ 'show': isSidebarOpen }"
      @click="closeSidebar"
    ></div>
  </div>
</template>

<style scoped>
.admin-layout-wrapper {
  display: flex;
  height: 100vh;
  width: 100vw;
  overflow: hidden;
  background-color: #f4f7fe; /* Nền xám xanh nhạt hiện đại */
  font-family: 'Inter', sans-serif;
  color: #2b3674;
}

.admin-main-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100vh;
  min-width: 0; /* Tránh tràn layout flex */
  position: relative;
  transition: all 0.3s ease;
}

.admin-content-area {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 24px;
}

.content-wrapper {
  max-width: 100%;
  margin: 0 auto;
  min-height: 100%;
}

/* Overlay che nền khi mở sidebar trên thiết bị di động */
.mobile-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(11, 20, 55, 0.5);
  backdrop-filter: blur(4px);
  z-index: 40;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.3s ease, visibility 0.3s ease;
}

.mobile-overlay.show {
  opacity: 1;
  visibility: visible;
}

/* Reponsive */
@media (max-width: 1024px) {
  .admin-content-area {
    padding: 16px;
  }
}
</style>
