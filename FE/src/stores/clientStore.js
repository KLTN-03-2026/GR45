import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import authApi from '@/api/authApi.js';

// Key localStorage của Khách Hàng
const TOKEN_KEY = 'auth.client.token';
const USER_KEY  = 'auth.client.user';

// Hàm đọc user an toàn từ localStorage
function readUser() {
  try {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export const useClientStore = defineStore('client', () => {
  // ─── State ───────────────────────────────────────────────
  const token   = ref(localStorage.getItem(TOKEN_KEY) || null);
  const user    = ref(readUser());
  const loading = ref(false);
  const error   = ref(null);
  const isTokenVerified = ref(false);
  
  // Auth Modal global state
  const isAuthModalOpen = ref(false);
  const authMode = ref('login'); // 'login' or 'register'

  // ─── Getters ─────────────────────────────────────────────
  const isLoggedIn = computed(() => !!token.value);

  // ─── Actions ─────────────────────────────────────────────

  // Đăng nhập Khách Hàng
  async function login(credentials) {
    loading.value = true;
    error.value   = null;
    try {
      const res     = await authApi.clientLogin(credentials);
      const payload = res.data || res;
      const t       = payload.token || payload.access_token || null;
      const u       = payload.khach_hang || payload.user || null;

      if (!t) {
        error.value = res.message || 'Đăng nhập thất bại.';
        return false;
      }

      token.value = t;
      user.value  = u;
      isTokenVerified.value = true;
      localStorage.setItem(TOKEN_KEY, t);
      localStorage.setItem(USER_KEY, JSON.stringify(u));
      localStorage.setItem('auth.active_role', 'client');
      return true;
    } catch (err) {
      error.value = err.response?.data?.errors
        ? Object.values(err.response.data.errors).flat()[0]
        : (err.response?.data?.message || err.message || 'Lỗi kết nối máy chủ');
      return false;
    } finally {
      loading.value = false;
    }
  }

  function openAuthModal(initialMode = 'login') {
    authMode.value = initialMode;
    isAuthModalOpen.value = true;
  }

  function closeAuthModal() {
    isAuthModalOpen.value = false;
  }

  // Đăng xuất Khách Hàng
  function logout() {
    token.value = null;
    user.value  = null;
    isTokenVerified.value = false;
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  }

  // Lấy thông tin cá nhân hiện tại
  async function fetchProfile() {
    if (!token.value) return false;
    loading.value = true;
    try {
      // Giả sử có endpoint /me hoặc profile trong authApi
      const res = await authApi.getClientProfile(); 
      user.value = res.data || res;
      localStorage.setItem(USER_KEY, JSON.stringify(user.value));
      isTokenVerified.value = true;
      return true;
    } catch (err) {
      console.error('Fetch profile failed:', err);
      // Nếu token hết hạn hoặc lỗi, logout luôn
      logout();
      return false;
    } finally {
      loading.value = false;
    }
  }

  // Cập nhật thông tin user locally
  function updateUser(newData) {
    if (user.value) {
      user.value = { ...user.value, ...newData };
      localStorage.setItem(USER_KEY, JSON.stringify(user.value));
    }
  }

  return { 
    token, user, loading, error, isTokenVerified, isLoggedIn, 
    isAuthModalOpen, authMode,
    login, logout, updateUser, openAuthModal, closeAuthModal,
    fetchProfile 
  };
});
