import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import authApi from '@/api/authApi.js';
import adminApi from '@/api/adminApi.js';

// Key localStorage của Admin
const TOKEN_KEY = 'auth.admin.token';
const USER_KEY  = 'auth.admin.user';
const IS_MASTER_KEY = 'auth.admin.is_master';
const CHUC_VU_KEY = 'auth.admin.chuc_vu';
const PERMISSIONS_KEY = 'auth.admin.permissions';

// Hàm đọc user an toàn từ localStorage
function readUser() {
  try {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function readPermissions() {
  try {
    const raw = localStorage.getItem(PERMISSIONS_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

export const useAdminStore = defineStore('admin', () => {
  // ─── State ───────────────────────────────────────────────
  const token   = ref(localStorage.getItem(TOKEN_KEY) || null);
  const user    = ref(readUser());
  const loading = ref(false);
  const error   = ref(null);
  const isTokenVerified = ref(false); // Đánh dấu token đã được verify trong phiên này chưa
  const isMaster = ref(Number(localStorage.getItem(IS_MASTER_KEY) || 0));
  const chucVu = ref(localStorage.getItem(CHUC_VU_KEY) || '');
  const permissions = ref(readPermissions());
  const permissionsLoading = ref(false);
  const permissionsError = ref('');

  // ─── Getters ─────────────────────────────────────────────
  const isLoggedIn = computed(() => !!token.value);
  const permissionSet = computed(() => new Set(permissions.value));

  const hasPermission = (slug) => {
    if (!slug) return true;
    if (isMaster.value === 1) return true;
    return permissionSet.value.has(slug);
  };

  const hasAnyPermission = (slugs = []) => {
    if (isMaster.value === 1) return true;
    if (!Array.isArray(slugs) || slugs.length === 0) return true;
    return slugs.some((slug) => permissionSet.value.has(slug));
  };

  function setPermissionData(payload = {}) {
    const source = payload?.data && typeof payload.data === 'object' ? payload.data : payload;
    const nextIsMaster = Number(Boolean(source.is_master));
    const nextChucVu = source.chuc_vu || '';
    const nextPermissions = Array.isArray(source.permissions) ? source.permissions : [];

    isMaster.value = nextIsMaster;
    chucVu.value = nextChucVu;
    permissions.value = nextPermissions;

    localStorage.setItem(IS_MASTER_KEY, String(nextIsMaster));
    localStorage.setItem(CHUC_VU_KEY, nextChucVu);
    localStorage.setItem(PERMISSIONS_KEY, JSON.stringify(nextPermissions));
  }

  async function fetchPermissions(options = {}) {
    const { silent = false } = options;

    if (!token.value) return false;

    if (!silent) {
      permissionsLoading.value = true;
    }
    permissionsError.value = '';

    try {
      const res = await adminApi.getMyPermissions();
      const payload = res.data || res;
      setPermissionData(payload);
      return true;
    } catch (err) {
      permissionsError.value = err.response?.data?.message || err.message || 'Không tải được quyền admin';
      return false;
    } finally {
      if (!silent) {
        permissionsLoading.value = false;
      }
    }
  }

  // ─── Actions ─────────────────────────────────────────────

  // Đăng nhập Admin
  async function login(credentials) {
    loading.value = true;
    error.value   = null;
    try {
      const res     = await authApi.adminLogin(credentials);
      const payload = res.data || res;
      const t       = payload.token || payload.access_token || null;
      const u       = payload.admin || payload.user || null;

      if (!t) {
        error.value = res.message || 'Đăng nhập thất bại.';
        return false;
      }

      token.value = t;
      user.value  = u;
      isTokenVerified.value = true;
      localStorage.setItem(TOKEN_KEY, t);
      localStorage.setItem(USER_KEY, JSON.stringify(u));
      localStorage.setItem('auth.active_role', 'admin');
      await fetchPermissions({ silent: true });
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

  // Đăng xuất Admin
  function logout() {
    token.value = null;
    user.value  = null;
    isTokenVerified.value = false;
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    localStorage.removeItem('auth.active_role');
    localStorage.removeItem(IS_MASTER_KEY);
    localStorage.removeItem(CHUC_VU_KEY);
    localStorage.removeItem(PERMISSIONS_KEY);
    isMaster.value = 0;
    chucVu.value = '';
    permissions.value = [];
    permissionsError.value = '';
  }

  return {
    token,
    user,
    loading,
    error,
    isTokenVerified,
    isMaster,
    chucVu,
    permissions,
    permissionsLoading,
    permissionsError,
    isLoggedIn,
    hasPermission,
    hasAnyPermission,
    login,
    logout,
    fetchPermissions,
  };
});
