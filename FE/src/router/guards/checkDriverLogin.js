import { useDriverStore } from '@/stores/driverStore.js';

const TOKEN_KEY = 'auth.driver.token';
const USER_KEY  = 'auth.driver.user';

export async function checkDriverLogin(to, from) {
  const token = localStorage.getItem(TOKEN_KEY);

  // Nếu không có token -> Về trang đăng nhập tài xế
  if (!token) return { name: 'driver-login' };

  const driverStore = useDriverStore();

  // Load lại user từ localStorage nếu store chưa có
  if (!driverStore.user) {
    try {
      const savedUser = localStorage.getItem(USER_KEY);
      if (savedUser) {
        driverStore.user = JSON.parse(savedUser);
      }
    } catch (e) {
      console.error('Lỗi parse user từ localStorage:', e);
    }
  }

  // Đánh dấu đã xác thực, cho phép truy cập
  driverStore.isTokenVerified = true;
  localStorage.setItem('auth.active_role', 'driver');

  return true;
}
