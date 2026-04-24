import { useOperatorStore } from '@/stores/operatorStore.js';

const TOKEN_KEY = 'auth.operator.token';
const USER_KEY  = 'auth.operator.user';

export async function checkOperatorLogin(to, from) {
  const token = localStorage.getItem(TOKEN_KEY);

  // Nếu không có token -> Về trang đăng nhập nhà xe
  if (!token) return { name: 'operator-login' };

  const operatorStore = useOperatorStore();

  // Load lại user từ localStorage nếu store chưa có
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

  // Đánh dấu đã xác thực, cho phép truy cập
  operatorStore.isTokenVerified = true;
  localStorage.setItem('auth.active_role', 'operator');

  return true;
}
