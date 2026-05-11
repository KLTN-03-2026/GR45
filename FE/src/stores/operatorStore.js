import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import authApi from '@/api/authApi.js';

// Key localStorage của Nhà Xe
const TOKEN_KEY = 'auth.operator.token';
const USER_KEY = 'auth.operator.user';

// Hàm đọc user an toàn từ localStorage
function readUser() {
  try {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

export const useOperatorStore = defineStore('operator', () => {
  // ─── State ───────────────────────────────────────────────
  const token = ref(localStorage.getItem(TOKEN_KEY) || null);
  const user = ref(readUser());
  const loading = ref(false);
  const error = ref(null);
  const isTokenVerified = ref(false);
  const notifications = ref([]);

  // ─── Getters ─────────────────────────────────────────────
  const isLoggedIn = computed(() => !!token.value);

  // ─── Actions ─────────────────────────────────────────────

  // Đăng nhập Nhà Xe
  async function login(credentials) {
    loading.value = true;
    error.value = null;
    try {
      const res = await authApi.operatorLogin(credentials);
      const payload = res.data || res;
      const t = payload.token || payload.access_token || null;
      const u = payload.nha_xe || payload.user || null;

      if (!t) {
        error.value = res.message || 'Đăng nhập thất bại.';
        return false;
      }

      token.value = t;
      user.value = u;
      isTokenVerified.value = true;
      localStorage.setItem(TOKEN_KEY, t);
      localStorage.setItem(USER_KEY, JSON.stringify(u));
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

  // Thêm thông báo mới vào danh sách
  function addNotification(note) {
    notifications.value.unshift({
      id: Date.now(),
      read: false,
      time: 'Vừa xong',
      ...note
    });
    // Giới hạn 20 thông báo gần nhất
    if (notifications.value.length > 20) {
      notifications.value.pop();
    }
  }

  function markNotificationRead(id) {
    const note = notifications.value.find(n => n.id === id);
    if (note) {
      note.read = true;
    }
  }

  function logout() {
    token.value = null;
    user.value = null;
    isTokenVerified.value = false;
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  }

  return {
    token, user, loading, error, isTokenVerified, isLoggedIn,
    notifications, login, logout, addNotification, markNotificationRead
  };
});
