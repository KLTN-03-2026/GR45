// @ts-nocheck
import type { NavigationGuard } from 'vue-router';
import { useOperatorStore } from '@/stores/operatorStore.js';

const TOKEN_KEY = 'auth.operator.token';
const USER_KEY = 'auth.operator.user';

export const checkOperatorLogin: NavigationGuard = async () => {
  const token = localStorage.getItem(TOKEN_KEY);

  if (!token) return { name: 'operator-login' };

  const operatorStore = useOperatorStore();

  if (!operatorStore.user) {
    try {
      const savedUser = localStorage.getItem(USER_KEY);
      if (savedUser) {
        operatorStore.user = JSON.parse(savedUser);
      }
    } catch (e) {
      console.error('Lỗi parse user từ localStorage:', e);
    }
  }

  operatorStore.isTokenVerified = true;
  localStorage.setItem('auth.active_role', 'operator');

  return true;
};
