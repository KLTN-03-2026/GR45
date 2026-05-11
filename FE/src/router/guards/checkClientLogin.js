import { useClientStore } from '@/stores/clientStore.js';

const TOKEN_KEY = 'auth.client.token';
const USER_KEY  = 'auth.client.user';

export async function checkClientLogin(to, from) {
  const token = localStorage.getItem(TOKEN_KEY);

  // Nếu không có token -> Về trang đăng nhập khách hàng
  if (!token) return { name: 'client-login' };

  const clientStore = useClientStore();

  // Load lại user từ localStorage nếu store chưa có
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

  // Đánh dấu đã xác thực, cho phép truy cập
  clientStore.isTokenVerified = true;

  return true;
}
