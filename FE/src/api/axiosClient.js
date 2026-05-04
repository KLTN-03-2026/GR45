import axios from 'axios';

/**
 * Base cho mọi request dạng `/v1/...` (prefix Laravel là `/api`).
 * Nếu `VITE_API_URL` kết thúc bằng `/api/v1` (file .env.exemple), bỏ `/v1` để không thành `/api/v1/v1/...`.
 */
function resolveAxiosBaseUrl() {
  let u = String(import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/').trim();
  u = u.replace(/\/+$/, '');
  if (/\/v1$/i.test(u)) {
    u = u.replace(/\/v1$/i, '');
  }
  return u.endsWith('/') ? u : `${u}/`;
}

// Mapping từ role → token key trong localStorage
const ROLE_TOKEN_MAP = {
  admin: 'auth.admin.token',
  client: 'auth.client.token',
  operator: 'auth.operator.token',
  driver: 'auth.driver.token',
};

/** Fallback khi chưa set auth.active_role (vd: ngrok / gọi URL trực tiếp). */
function getRoleFromUrl(url) {
  if (!url) return 'client';
  const u = String(url);
  if (u.includes('/admin/')) return 'admin';
  if (u.includes('/nha-xe/')) return 'operator';
  if (u.includes('/tai-xe/')) return 'driver';
  return 'client';
}

// Giữ sàn tối thiểu 60s như bản cũ; env có thể tăng (vd: 180000).
const apiTimeoutMs = (() => {
  const raw = String(import.meta.env.VITE_API_TIMEOUT_MS || '').trim();
  const n = Number.parseInt(raw, 10);
  if (Number.isFinite(n) && n >= 5000) {
    return Math.max(n, 60000);
  }
  return 60000;
})();

const axiosClient = axios.create({
  baseURL: resolveAxiosBaseUrl(),
  headers: {
    'Content-Type': 'application/json',
    'ngrok-skip-browser-warning': 'true',
  },
  timeout: apiTimeoutMs,
});

// --- REQUEST INTERCEPTOR ---
// Tự động đính Bearer: ưu tiên auth.active_role; không có thì suy ra từ URL (hành vi cũ, hợp ngrok).
axiosClient.interceptors.request.use(
  (config) => {
    const storedRole = localStorage.getItem('auth.active_role');
    const activeRole = storedRole || getRoleFromUrl(config.url);
    const tokenKey = ROLE_TOKEN_MAP[activeRole];
    const token = tokenKey ? localStorage.getItem(tokenKey) : null;

    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }

    // Tự xoá Content-Type nếu dữ liệu là form data để cho phép browser tự gen config Multipart
    if (config.data instanceof FormData) {
      delete config.headers['Content-Type'];
    }

    // Gắn role vào config để response interceptor 401 xóa đúng token
    config._role = activeRole;

    return config;
  },
  (error) => Promise.reject(error)
);

// --- RESPONSE INTERCEPTOR ---
axiosClient.interceptors.response.use(
  (response) => response.data,
  (error) => {
    // Nếu 401 → xóa token của đúng role gọi API (bản cũ không xóa auth.active_role)
    if (error.response?.status === 401) {
      const activeRole =
        error.config?._role ||
        localStorage.getItem('auth.active_role') ||
        getRoleFromUrl(error.config?.url);
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
