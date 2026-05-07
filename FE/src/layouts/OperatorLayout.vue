<script setup>
import { ref, provide, onMounted, onUnmounted } from "vue";
import OperatorHeader from "@/components/layout/OperatorHeader.vue";
import OperatorSidebar from "@/components/layout/OperatorSidebar.vue";
import BaseToast from "@/components/common/BaseToast.vue";
import { useOperatorStore } from "@/stores/operatorStore";
import Echo from "laravel-echo";
import Pusher from "pusher-js";

// Store state
const operatorStore = useOperatorStore();

// Trạng thái mở/đóng Sidebar trên Mobile
const isSidebarOpen = ref(false);

// Hàm toggle sidebar
const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value;
};

// Hàm đóng sidebar
const closeSidebar = () => {
  isSidebarOpen.value = false;
};

// Provide cho các component con sử dụng
provide("isSidebarOpen", isSidebarOpen);
provide("toggleSidebar", toggleSidebar);
provide("closeSidebar", closeSidebar);

// ─── Logic Toast ────────────────────────────────────────────────────────────
const toastVisible = ref(false);
const toastMessage = ref("");
const toastType = ref("success");

const showToast = (message, type = "success") => {
  toastMessage.value = message;
  toastType.value = type;
  toastVisible.value = true;
  setTimeout(() => {
    toastVisible.value = false;
  }, 3000);
};

// ─── Lắng nghe sự kiện Pusher (WebSocket) ──────────────────────────────────
let echoInstance = null;

onMounted(() => {
  if (operatorStore.user && operatorStore.token) {
    // Gán biến global để Echo dùng
    window.Pusher = Pusher;

    // Lấy config từ môi trường (hoặc fallback mặc định)
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
    const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER;
    let apiUrl = import.meta.env.VITE_API_URL || "https://api.bussafe.io.vn/api/";
    if (!apiUrl.endsWith("/")) apiUrl += "/";

    echoInstance = new Echo({
      broadcaster: "pusher",
      key: pusherKey,
      cluster: pusherCluster,
      forceTLS: true,
      authEndpoint: `${apiUrl}v1/nha-xe/broadcasting/auth`, // Router API để verify Channel
      auth: {
        headers: {
          Authorization: `Bearer ${operatorStore.token}`,
          Accept: "application/json",
          "ngrok-skip-browser-warning": "true",
        },
      },
    });

    const channelName = `nha-xe.${operatorStore.user.ma_nha_xe}`;
    console.log(`Đang subscribe kênh: ${channelName}`);

    echoInstance
      .private(channelName)
      .listen(".ve.moi_dat", (eventVeMoi) => {
        console.log("Nhan event moi_dat:", eventVeMoi);
        const msg = eventVeMoi.message || `Bạn có 1 vé mới (${eventVeMoi.ma_ve})!`;
        showToast(msg, "success");
        operatorStore.addNotification({
          type: 'ticket',
          title: 'Vé mới được đặt',
          message: msg,
          icon: 'info'
        });
      })
      .listen(".ve.da_thanh_toan", (eventTiendo) => {
        console.log("Nhan event da_thanh_toan:", eventTiendo);
        const msg = eventTiendo.message || `Vé ${eventTiendo.ma_ve} mới được thanh toán.`;
        showToast(msg, "success");
        operatorStore.addNotification({
          type: 'success',
          title: 'Thanh toán thành công',
          message: msg,
          icon: 'success'
        });
      })
      .listen(".ve.huy_tu_dong", (eventHuy) => {
        console.log("Nhan event huy_tu_dong:", eventHuy);
        const msg = eventHuy.message || `Vé ${eventHuy.ma_ve} vừa bị huỷ do hết thời gian thanh toán`;
        showToast(msg, "error");
        operatorStore.addNotification({
          type: 'alert',
          title: 'Vé bị hủy tự động',
          message: msg,
          icon: 'alert'
        });
      })
      .listen(".bao-dong.vi-pham", (event) => {
        console.log("🚨 Nhận cảnh báo vi phạm:", event);
        const msg = event.message || `⚠️ Phát hiện tài xế vi phạm trên chuyến #${event.id_chuyen_xe}`;
        showToast(msg, "error");
        operatorStore.addNotification({
          type: 'alert',
          title: 'Cảnh báo vi phạm',
          message: msg,
          icon: 'alert'
        });
      })
      .listen(".yeu_cau_rut_tien.approved", (event) => {
        console.log("💰 Nhận thông báo duyệt rút tiền:", event);
        const msg = event.message || `Yêu cầu rút tiền ${event.transaction_code} đã được duyệt.`;
        showToast(msg, "success");
        operatorStore.addNotification({
          type: 'wallet',
          title: 'Rút tiền thành công',
          message: msg,
          icon: 'success'
        });
      })
      .listen(".yeu_cau_rut_tien.rejected", (event) => {
        console.log("❌ Nhận thông báo từ chối rút tiền:", event);
        const msg = event.message || `Yêu cầu rút tiền ${event.transaction_code} đã bị từ chối.`;
        showToast(msg, "error");
        operatorStore.addNotification({
          type: 'wallet',
          title: 'Rút tiền bị từ chối',
          message: msg,
          icon: 'alert'
        });
      })
      .listen(".nha_xe.topup_success", (event) => {
        console.log("💎 Nhận thông báo nạp tiền thành công:", event);
        const msg = event.message || `Nạp tiền thành công ${event.amount} VNĐ.`;
        showToast(msg, "success");
        operatorStore.addNotification({
          type: 'wallet',
          title: 'Nạp tiền thành công',
          message: msg,
          icon: 'success'
        });
      });
  }
});

onUnmounted(() => {
  if (echoInstance && operatorStore.user) {
    echoInstance.leave(`nha-xe.${operatorStore.user.ma_nha_xe}`);
  }
});
</script>

<template>
  <div class="operator-layout-wrapper">
    <BaseToast
      :visible="toastVisible"
      :message="toastMessage"
      :type="toastType"
    />

    <!-- Sidebar -->
    <OperatorSidebar />

    <!-- Khu vực Main Content -->
    <div class="operator-main-container">
      <OperatorHeader />

      <!-- Vùng hiển thị nội dung chính -->
      <main class="operator-content-area">
        <div class="content-wrapper">
          <RouterView />
        </div>
      </main>
    </div>

    <!-- Overlay che nền khi mở sidebar trên Mobile -->
    <div
      class="mobile-overlay"
      :class="{ show: isSidebarOpen }"
      @click="closeSidebar"
    ></div>
  </div>
</template>

<style scoped>
.operator-layout-wrapper {
  display: flex;
  height: 100vh;
  width: 100vw;
  overflow: hidden;
  background-color: #f0fdf4;
  font-family: "Inter", sans-serif;
  color: #1e3a2f;
}

.operator-main-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100vh;
  min-width: 0;
  position: relative;
  transition: all 0.3s ease;
}

.operator-content-area {
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

/* Overlay che nền khi mở sidebar trên Mobile */
.mobile-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(11, 55, 30, 0.5);
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
  .operator-content-area {
    padding: 16px;
  }
}
</style>
