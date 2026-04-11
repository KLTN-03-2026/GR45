<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'primary', // primary, secondary, danger, outline, text
  },
  size: {
    type: String,
    default: 'md', // sm, md, lg
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  block: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String,
    default: 'button',
  }
});

const buttonClasses = computed(() => {
  return [
    'base-btn',
    `base-btn--${props.variant}`,
    `base-btn--${props.size}`,
    { 'base-btn--block': props.block },
    { 'base-btn--loading': props.loading }
  ];
});
</script>

<template>
  <button
    :type="type"
    :class="buttonClasses"
    :disabled="disabled || loading"
    @click="$emit('click', $event)"
  >
    <svg v-if="loading" class="spinner" viewBox="0 0 50 50">
      <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
    </svg>
    <span :class="{ 'opacity-0': loading }">
      <slot></slot>
    </span>
  </button>
</template>

<style scoped>
.base-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  font-family: 'Inter', system-ui, sans-serif;
  font-weight: 500;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1px solid transparent;
  position: relative;
  overflow: hidden;
}

.base-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.base-btn:active:not(:disabled) {
  transform: scale(0.97);
}

/* Sizing */
.base-btn--sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
}
.base-btn--md {
  padding: 0.5rem 1rem;
  font-size: 1rem;
}
.base-btn--lg {
  padding: 0.75rem 1.5rem;
  font-size: 1.125rem;
}
.base-btn--block {
  width: 100%;
}

/* Variants */
.base-btn--primary {
  background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
  color: white;
  box-shadow: 0 4px 14px 0 rgba(79, 70, 229, 0.39);
}
.base-btn--primary:hover:not(:disabled) {
  box-shadow: 0 6px 20px rgba(79, 70, 229, 0.23);
  transform: translateY(-1px);
}

.base-btn--danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  box-shadow: 0 4px 14px 0 rgba(239, 68, 68, 0.39);
}
.base-btn--danger:hover:not(:disabled) {
  box-shadow: 0 6px 20px rgba(239, 68, 68, 0.23);
  transform: translateY(-1px);
}

.base-btn--outline {
  background: transparent;
  color: #4f46e5;
  border-color: #4f46e5;
}
.base-btn--outline:hover:not(:disabled) {
  background: #e0e7ff;
}

.base-btn--secondary {
  background: #f1f5f9;
  color: #334155;
}
.base-btn--secondary:hover:not(:disabled) {
  background: #e2e8f0;
}

/* Spinner Animation */
.spinner {
  animation: rotate 2s linear infinite;
  width: 1em;
  height: 1em;
  position: absolute;
}
.spinner .path {
  stroke: currentColor;
  stroke-linecap: round;
  animation: dash 1.5s ease-in-out infinite;
}
@keyframes rotate {
  100% {
    transform: rotate(360deg);
  }
}
@keyframes dash {
  0% {
    stroke-dasharray: 1, 150;
    stroke-dashoffset: 0;
  }
  50% {
    stroke-dasharray: 90, 150;
    stroke-dashoffset: -35;
  }
  100% {
    stroke-dasharray: 90, 150;
    stroke-dashoffset: -124;
  }
}
.opacity-0 {
  opacity: 0;
}
</style>
