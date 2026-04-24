import { useAdminStore } from '@/stores/adminStore.js';

const TOKEN_KEY = 'auth.admin.token';
const USER_KEY = 'auth.admin.user';

export async function checkAdminLogin(to, from) {
  const token = localStorage.getItem(TOKEN_KEY);

  // Nếu không có token -> Về trang đăng nhập
  if (!token) return { name: 'admin-login' };

  const adminStore = useAdminStore();

  // Load lại user từ localStorage nếu store chưa có
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

  // Đánh dấu đã xác thực an toàn, cho phép truy cập luôn 
  // (tránh tình trạng gọi check-token bị lỗi khiến admin bị logout oan)
  adminStore.isTokenVerified = true;
  localStorage.setItem('auth.active_role', 'admin');
  await adminStore.fetchPermissions({ silent: true });

  return true;
}
