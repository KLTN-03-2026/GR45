<script setup>
import { computed } from 'vue';
import { RouterView, useRoute, useRouter, RouterLink } from 'vue-router';
import { useAdminStore } from '@/stores/adminStore.js';
import { useOperatorStore } from '@/stores/operatorStore.js';

const route = useRoute();
const router = useRouter();
const adminStore = useAdminStore();
const operatorStore = useOperatorStore();

const isAdmin = computed(() => route.path.startsWith('/admin'));

const shellTitle = computed(() => route.meta.title || 'Trang quản lý');

function logout() {
  if (isAdmin.value) {
    adminStore.logout();
    router.push({ name: 'admin-login' });
  } else {
    operatorStore.logout();
    router.push({ name: 'operator-login' });
  }
}
</script>

<template>
  <div class="dashboard-shell">
    <header class="shell-header">
      <div class="shell-brand">
        <span class="shell-badge">{{ isAdmin ? 'Admin' : 'Nhà xe' }}</span>
        <h1 class="shell-title">{{ shellTitle }}</h1>
      </div>
      <nav class="shell-nav">
        <RouterLink
          v-if="isAdmin"
          :to="{ name: 'admin-xe' }"
          class="shell-link"
          active-class="shell-link-active"
        >
          Xe &amp; ghế
        </RouterLink>
        <RouterLink
          v-else
          :to="{ name: 'operator-xe' }"
          class="shell-link"
          active-class="shell-link-active"
        >
          Xe &amp; ghế
        </RouterLink>
        <button type="button" class="shell-logout" @click="logout">Đăng xuất</button>
      </nav>
    </header>
    <main class="shell-main">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.dashboard-shell {
  min-height: 100vh;
  background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%);
}

.shell-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 14px 20px;
  background: rgba(255, 255, 255, 0.92);
  border-bottom: 1px solid #e2e8f0;
  position: sticky;
  top: 0;
  z-index: 50;
  backdrop-filter: blur(8px);
}

.shell-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.shell-badge {
  flex-shrink: 0;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 4px 10px;
  border-radius: 999px;
  background: #1e3a8a;
  color: #fff;
}

.shell-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.shell-nav {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.shell-link {
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #475569;
  text-decoration: none;
  border: 1px solid transparent;
}

.shell-link:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.shell-link-active {
  background: #eff6ff;
  color: #1d4ed8;
  border-color: #bfdbfe;
}

.shell-logout {
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
}

.shell-logout:hover {
  border-color: #cbd5e1;
  color: #0f172a;
}

.shell-main {
  max-width: 1280px;
  margin: 0 auto;
  padding: 20px 16px 40px;
}
</style>
