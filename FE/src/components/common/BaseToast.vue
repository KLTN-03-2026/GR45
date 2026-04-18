<script setup>
import { ref } from 'vue';

const props = defineProps({
  message: String,
  type: {
    type: String,
    default: 'success'
  },
  visible: Boolean,
  showIcon: {
    type: Boolean,
    default: true
  }
});
</script>

<template>
  <div v-if="visible" class="custom-toast" :class="type">
    <div class="toast-content">
      <template v-if="showIcon">
        <span class="toast-icon" v-if="type !== 'success'">!</span>
      </template>
      <span>{{ message }}</span>
    </div>
  </div>
</template>

<style scoped>
.custom-toast {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 12px 24px;
  border-radius: 8px;
  color: white;
  font-weight: 500;
  z-index: 9999;
  display: flex;
  align-items: center;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s;
}
.success { background-color: #10b981; }
.error { background-color: #ef4444; }
.toast-content {
  display: flex;
  align-items: center;
  gap: 12px;
}
.toast-icon {
  font-weight: bold;
  font-size: 1.2rem;
}
@keyframes slideIn {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}
@keyframes fadeOut {
  from { opacity: 1; }
  to { opacity: 0; }
}
</style>
