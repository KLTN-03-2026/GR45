<script setup>
defineProps({
  tag: { type: String, default: 'div' },
});
</script>

<template>
  <component :is="tag" class="base-card">
    <slot />
  </component>
</template>

<style scoped>
.base-card {
  width: 100%;
}
</style>
