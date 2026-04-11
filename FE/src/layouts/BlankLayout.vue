<script setup>
import { RouterView } from 'vue-router';
</script>

<template>
  <div class="blank-layout">
    <RouterView />
  </div>
</template>

<style scoped>
.blank-layout {
  min-height: 100vh;
  width: 100%;
}
</style>
