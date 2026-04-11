// @ts-nocheck
import type { NavigationGuard } from 'vue-router';
import { useClientStore } from '@/stores/clientStore.js';

const TOKEN_KEY = 'auth.client.token';
const USER_KEY = 'auth.client.user';

export const checkClientLogin: NavigationGuard = async () => {
  const token = localStorage.getItem(TOKEN_KEY);

  if (!token) return { name: 'client-login' };

  const clientStore = useClientStore();

  if (!clientStore.user) {
    try {
      const savedUser = localStorage.getItem(USER_KEY);
      if (savedUser) {
        clientStore.user = JSON.parse(savedUser);
      }
    } catch (e) {
      console.error('Lỗi parse user từ localStorage:', e);
    }
  }

  clientStore.isTokenVerified = true;
  localStorage.setItem('auth.active_role', 'client');

  return true;
};
