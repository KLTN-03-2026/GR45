import { useOperatorStore } from '@/stores/operatorStore.js';

const TOKEN_KEY = 'auth.operator.token';
const USER_KEY  = 'auth.operator.user';
const ROLE_KEY  = 'auth.operator.role';

export async function checkOperatorLogin(to, from) {
  const token = localStorage.getItem(TOKEN_KEY);

  // Không có token → về trang đăng nhập
  if (!token) return { name: 'operator-login' };

  const operatorStore = useOperatorStore();

  // Khôi phục state từ localStorage nếu store chưa có
  if (!operatorStore.user) {
    try {
      const savedUser = localStorage.getItem(USER_KEY);
      const savedRole = localStorage.getItem(ROLE_KEY) || 'nha_xe';
      if (savedUser) {
        operatorStore.user  = JSON.parse(savedUser);
        operatorStore.role  = savedRole;
        operatorStore.token = token;
      }
    } catch (e) {
      console.error('Lỗi parse user từ localStorage:', e);
    }
  }

  operatorStore.isTokenVerified = true;
  return true;
}
