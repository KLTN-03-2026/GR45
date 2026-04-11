<script setup>
defineProps({
  noPadding: {
    type: Boolean,
    default: false
  }
});
</script>

<template>
  <div class="base-card" :class="{ 'no-padding': noPadding }">
    <div v-if="$slots.header" class="card-header">
      <slot name="header"></slot>
    </div>
    
    <div class="card-body">
      <slot></slot>
    </div>

    <div v-if="$slots.footer" class="card-footer">
      <slot name="footer"></slot>
    </div>
  </div>
</template>

<style scoped>
.base-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: 12px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.025);
  border: 1px solid rgba(226, 232, 240, 0.8);
  overflow: hidden;
  transition: box-shadow 0.3s ease;
  font-family: 'Inter', system-ui, sans-serif;
  margin-bottom: 1.5rem;
}

.base-card:hover {
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.04);
}

.card-header {
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  font-weight: 600;
  font-size: 1.125rem;
  color: #1e293b;
}

.card-body {
  padding: 1.5rem;
}

.base-card.no-padding .card-body {
  padding: 0;
}

.card-footer {
  padding: 1rem 1.5rem;
  background-color: #f8fafc;
  border-top: 1px solid #f1f5f9;
}
</style>
