import axios from 'axios';

// Mapping từ role → token key trong localStorage
const ROLE_TOKEN_MAP = {
  admin: 'auth.admin.token',
  client: 'auth.client.token',
  operator: 'auth.operator.token',
  driver: 'auth.driver.token',
};

const axiosClient = axios.create({
  baseURL: 'http://127.0.0.1:8000/api/',
  headers: { 'Content-Type': 'application/json' },
  timeout: 10000,
});

// --- REQUEST INTERCEPTOR ---
// Tự động đính Bearer Token của role đang active vào mỗi request
axiosClient.interceptors.request.use(
  (config) => {
    const activeRole = localStorage.getItem('auth.active_role');
    const tokenKey = ROLE_TOKEN_MAP[activeRole];
    const token = tokenKey ? localStorage.getItem(tokenKey) : null;

    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
    }

    // Tự xoá Content-Type nếu dữ liệu là form data để cho phép browser tự gen config Multipart
    if (config.data instanceof FormData) {
      delete config.headers['Content-Type'];
    }

    return config;
  },
  (error) => Promise.reject(error)
);

// --- RESPONSE INTERCEPTOR ---
axiosClient.interceptors.response.use(
  (response) => response.data,
  (error) => {
    // Nếu 401 → xóa token của role đang active
    if (error.response?.status === 401) {
      const activeRole = localStorage.getItem('auth.active_role');
      const tokenKey = ROLE_TOKEN_MAP[activeRole];
      if (tokenKey) {
        localStorage.removeItem(tokenKey);
        localStorage.removeItem(tokenKey.replace('.token', '.user'));
      }
      localStorage.removeItem('auth.active_role');
    }
    return Promise.reject(error);
  }
);

export default axiosClient;
