import axios from 'axios';

// Mapping từ role → token key trong localStorage
const ROLE_TOKEN_MAP = {
  admin    : 'auth.admin.token',
  client   : 'auth.client.token',
  operator : 'auth.operator.token',
  driver   : 'auth.driver.token',
};

const apiRoot = (import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');

const axiosClient = axios.create({
  baseURL: `${apiRoot}/api/`,
  headers: { 'Content-Type': 'application/json' },
  timeout: 10000,
});

// --- REQUEST INTERCEPTOR ---
// Tự động đính Bearer Token của role đang active vào mỗi request
axiosClient.interceptors.request.use(
  (config) => {
    const activeRole = localStorage.getItem('auth.active_role');
    const tokenKey   = ROLE_TOKEN_MAP[activeRole];
    const token      = tokenKey ? localStorage.getItem(tokenKey) : null;

    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`;
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
      const tokenKey   = ROLE_TOKEN_MAP[activeRole];
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
