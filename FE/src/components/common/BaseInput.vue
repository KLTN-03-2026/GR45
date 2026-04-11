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
  type: {
    type: String,
    default: 'text'
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

const onInput = (event) => {
  emit('update:modelValue', event.target.value);
};
</script>

<template>
  <div class="base-input-wrapper">
    <label v-if="label" class="base-input-label">
      {{ label }}
    </label>
    <div class="input-container">
      <input
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :class="['base-input', { 'has-error': !!error }]"
        @input="onInput"
      />
    </div>
    <Transition name="fade">
      <span v-if="error" class="error-text">{{ error }}</span>
    </Transition>
  </div>
</template>

<style scoped>
.base-input-wrapper {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  width: 100%;
  font-family: 'Inter', system-ui, sans-serif;
  margin-bottom: 1rem;
}

.base-input-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
}

.input-container {
  position: relative;
  width: 100%;
}

.base-input {
  width: 100%;
  padding: 0.625rem 0.875rem;
  font-size: 1rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background-color: #ffffff;
  color: #1f2937;
  transition: all 0.2s ease-in-out;
  box-sizing: border-box;
}

.base-input::placeholder {
  color: #9ca3af;
}

.base-input:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

.base-input:disabled {
  background-color: #f3f4f6;
  color: #9ca3af;
  cursor: not-allowed;
}

.base-input.has-error {
  border-color: #ef4444;
}

.base-input.has-error:focus {
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
