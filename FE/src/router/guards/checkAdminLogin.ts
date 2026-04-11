// @ts-nocheck
import type { NavigationGuard } from 'vue-router';
import { useAdminStore } from '@/stores/adminStore.js';

const TOKEN_KEY = 'auth.admin.token';
const USER_KEY = 'auth.admin.user';

export const checkAdminLogin: NavigationGuard = async () => {
  const token = localStorage.getItem(TOKEN_KEY);

  if (!token) return { name: 'admin-login' };

  const adminStore = useAdminStore();

  if (!adminStore.user) {
    try {
      const savedUser = localStorage.getItem(USER_KEY);
      if (savedUser) {
        adminStore.user = JSON.parse(savedUser);
      }
    } catch (e) {
      console.error('Lỗi parse user từ localStorage:', e);
    }
  }

  adminStore.isTokenVerified = true;
  localStorage.setItem('auth.active_role', 'admin');
  await adminStore.fetchPermissions({ silent: true });

  return true;
};
