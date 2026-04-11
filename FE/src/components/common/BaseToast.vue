<script setup>
defineProps({
  visible: { type: Boolean, default: false },
  message: { type: String, default: '' },
  type: { type: String, default: 'success' },
  showIcon: { type: Boolean, default: true },
});

defineEmits(['update:visible']);
</script>

<template>
  <Teleport to="body">
    <Transition name="toast-fade">
      <div
        v-if="visible"
        class="base-toast"
        :class="type"
        role="status"
      >
        <span v-if="showIcon" class="base-toast-icon" aria-hidden="true">
          {{ type === 'error' ? '!' : '✓' }}
        </span>
        <span class="base-toast-msg">{{ message }}</span>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.base-toast {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 2000;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
  max-width: min(90vw, 420px);
}

.base-toast.success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.base-toast.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.base-toast-icon {
  flex-shrink: 0;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 800;
}

.success .base-toast-icon {
  background: #34d399;
  color: #fff;
}

.error .base-toast-icon {
  background: #f87171;
  color: #fff;
}

.base-toast-msg {
  line-height: 1.35;
}

.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.toast-fade-enter-from,
.toast-fade-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(8px);
}
</style>
