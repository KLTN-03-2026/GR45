import axios from 'axios';

// Mapping từ role → token key trong localStorage
const ROLE_TOKEN_MAP = {
  admin: 'auth.admin.token',
  client: 'auth.client.token',
  operator: 'auth.operator.token',
  driver: 'auth.driver.token',
};

// Hàm xác định role từ URL
function getRoleFromUrl(url) {
  if (!url) return 'client';
  if (url.includes('/admin/')) return 'admin';
  if (url.includes('/nha-xe/')) return 'operator';
  if (url.includes('/tai-xe/')) return 'driver';
  return 'client';
}

const axiosClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/',
  headers: { 
    'Content-Type': 'application/json',
    'ngrok-skip-browser-warning': 'true'
  },
  timeout: 10000,
});

// --- REQUEST INTERCEPTOR ---
// Tự động đính Bearer Token của role phù hợp vào mỗi request
axiosClient.interceptors.request.use(
  (config) => {
    // Xác định role từ URL thay vì dùng 'auth.active_role' chung
    const activeRole = getRoleFromUrl(config.url);
    const tokenKey = ROLE_TOKEN_MAP[activeRole];
    const token = tokenKey ? localStorage.getItem(tokenKey) : null;

    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }

    // Tự xoá Content-Type nếu dữ liệu là form data để cho phép browser tự gen config Multipart
    if (config.data instanceof FormData) {
      delete config.headers['Content-Type'];
    }

    // Gắn role vào config để dùng ở response interceptor
    config._role = activeRole;

    return config;
  },
  (error) => Promise.reject(error)
);

// --- RESPONSE INTERCEPTOR ---
axiosClient.interceptors.response.use(
  (response) => response.data,
  (error) => {
    // Nếu 401 → xóa token của đúng role gọi API
    if (error.response?.status === 401) {
      const activeRole = error.config?._role || getRoleFromUrl(error.config?.url);
      const tokenKey = ROLE_TOKEN_MAP[activeRole];
      if (tokenKey) {
        localStorage.removeItem(tokenKey);
        localStorage.removeItem(tokenKey.replace('.token', '.user'));
      }
    }
    return Promise.reject(error);
  }
);

export default axiosClient;
