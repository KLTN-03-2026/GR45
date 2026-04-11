<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: ''
  },
  label: {
    type: String,
    default: ''
  },
  options: {
    type: Array,
    default: () => []
    // Format: [{ value: 'abc', label: 'ABC' }] hoặc [{ value: '', label: '-- Tất cả --' }]
  },
  placeholder: {
    type: String,
    default: ''
  },
  error: {
    type: String,
    default: ''
  },
  disabled: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue']);

const onChange = (event) => {
  emit('update:modelValue', event.target.value);
};
</script>

<template>
  <div class="base-select-wrapper">
    <label v-if="label" class="base-select-label">
      {{ label }}
    </label>
    <div class="select-container">
      <select
        :value="modelValue"
        :disabled="disabled"
        :class="['base-select', { 'has-error': !!error }]"
        @change="onChange"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option
          v-for="opt in options"
          :key="opt.value"
          :value="opt.value"
        >
          {{ opt.label }}
        </option>
      </select>
    </div>
    <Transition name="fade">
      <span v-if="error" class="error-text">{{ error }}</span>
    </Transition>
  </div>
</template>

<style scoped>
.base-select-wrapper {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  width: 100%;
  font-family: 'Inter', system-ui, sans-serif;
  margin-bottom: 1rem;
}

.base-select-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
}

.select-container {
  position: relative;
  width: 100%;
}

.base-select {
  width: 100%;
  padding: 0.625rem 0.875rem;
  font-size: 0.95rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background-color: #ffffff;
  color: #1f2937;
  transition: all 0.2s ease-in-out;
  box-sizing: border-box;
  cursor: pointer;
}

.base-select:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

.base-select:disabled {
  background-color: #f3f4f6;
  color: #9ca3af;
  cursor: not-allowed;
}

.base-select.has-error {
  border-color: #ef4444;
}

.base-select.has-error:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
}

.error-text {
  font-size: 0.75rem;
  color: #ef4444;
  margin-top: 0.25rem;
  display: block;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
