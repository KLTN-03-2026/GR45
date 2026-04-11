<script setup>
import { ref, provide } from "vue";
import DriverHeader from "@/components/layout/DriverHeader.vue";
import DriverSidebar from "@/components/layout/DriverSidebar.vue";

// Trạng thái mở/đóng Sidebar trên Mobile
const isSidebarOpen = ref(false);

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

const closeSidebar = () => {
  isSidebarOpen.value = false;
};

provide("isSidebarOpen", isSidebarOpen);
provide("toggleSidebar", toggleSidebar);
provide("closeSidebar", closeSidebar);
</script>

<template>
  <div class="driver-layout-wrapper">
    <DriverSidebar />

    <div class="driver-main-container">
      <DriverHeader />

      <main class="driver-content-area">
        <div class="content-wrapper">
          <RouterView />
        </div>
      </main>
    </div>

    <!-- Mobile Overlay -->
    <div
      class="mobile-overlay"
      :class="{ show: isSidebarOpen }"
      @click="closeSidebar"
    ></div>
  </div>
</template>

<style scoped>
.driver-layout-wrapper {
  display: flex;
  height: 100vh;
  width: 100vw;
  overflow: hidden;
  background-color: #f0fdf4; /* Xanh lá nhạt cho Driver */
  font-family: "Inter", sans-serif;
  color: #064e3b;
}

.driver-main-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100vh;
  min-width: 0;
  position: relative;
  transition: all 0.3s ease;
}

.driver-content-area {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 0; /* Remove default layout padding to fix View layout */
}

.content-wrapper {
  max-width: 100%;
  margin: 0 auto;
  min-height: 100%;
  display: block; /* Fix flex-box stretch children */
}

.mobile-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(6, 78, 59, 0.5);
  backdrop-filter: blur(4px);
  z-index: 40;
  opacity: 0;
  visibility: hidden;
  transition:
    opacity 0.3s ease,
    visibility 0.3s ease;
}

.mobile-overlay.show {
  opacity: 1;
  visibility: visible;
}

@media (max-width: 1024px) {
  .driver-content-area {
    padding: 16px;
  }
}
</style>
