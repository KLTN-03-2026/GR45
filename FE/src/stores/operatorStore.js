import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import authApi from '@/api/authApi.js';

// Keys localStorage — dùng chung cho cả nhà xe lẫn nhân viên nhà xe
const TOKEN_KEY = 'auth.operator.token';
const USER_KEY  = 'auth.operator.user';
const ROLE_KEY  = 'auth.operator.role'; // 'nha_xe' | 'nhan_vien'

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
  const user  = ref(readUser());
  // 'nha_xe' = chủ nhà xe, 'nhan_vien' = nhân viên nội bộ nhà xe
  const role  = ref(localStorage.getItem(ROLE_KEY) || 'nha_xe');
  const loading = ref(false);
  const error   = ref(null);
  const isTokenVerified = ref(false);
  const notifications   = ref([]);

  // ─── Getters ─────────────────────────────────────────────
  const isLoggedIn  = computed(() => !!token.value);
  const isOwner     = computed(() => role.value === 'nha_xe');
  const isEmployee  = computed(() => role.value === 'nhan_vien');

  /**
   * Danh sách slug quyền của user hiện tại.
   * - Nhà xe (chủ): lấy từ user.permissions (mảng slug từ /phan-quyen)
   * - Nhân viên: lấy từ user.permissions (mảng slug trả về khi login)
   */
  const permissions = computed(() => {
    if (!user.value) return [];
    // Cả 2 trường hợp đều lưu permissions là mảng slug string
    return Array.isArray(user.value.permissions) ? user.value.permissions : [];
  });

  /**
   * Kiểm tra user có quyền cụ thể không.
   * Nhà xe (chủ) luôn có toàn quyền.
   */
  function hasPermission(slug) {
    if (isOwner.value) return true; // Chủ nhà xe có tất cả quyền
    return permissions.value.includes(slug);
  }

  // ─── Actions ─────────────────────────────────────────────

  /**
   * Xử lý sau khi lấy được token (lưu store, fetch permissions nếu cần)
   */
  async function processLoginSuccess(res, loginRole) {
    const payload = res.data || res;
    const t = payload.token || payload.access_token || null;
    let u = payload.nha_xe || payload.nhan_vien || payload.user || null;

    if (!t) {
      error.value = res.message || 'Đăng nhập thất bại.';
      return false;
    }

    // Với nhà xe, tải thêm permissions từ /phan-quyen
    if (loginRole === 'nha_xe') {
      try {
        localStorage.setItem(TOKEN_KEY, t);
        const pqRes = await authApi.operatorMe();
        const pqData = pqRes.data?.data || pqRes.data || {};
        u = {
          ...u,
          permissions: pqData.permissions || [],
          chuc_vu: pqData.chuc_vu || null,
        };
      } catch {
        // Lỗi gọi phan-quyen vẫn cho qua (chủ nhà xe có toàn quyền)
        u = { ...u, permissions: [] };
      }
    }

    token.value = t;
    user.value  = u;
    role.value  = loginRole;
    isTokenVerified.value = true;

    localStorage.setItem(TOKEN_KEY, t);
    localStorage.setItem(USER_KEY, JSON.stringify(u));
    localStorage.setItem(ROLE_KEY, loginRole);

    return true;
  }

  
  async function login(credentials) {
    loading.value = true;
    error.value   = null;

    try {
      // 1. Thử đăng nhập Chủ nhà xe
      const resOwner = await authApi.operatorLogin(credentials);
      const success = await processLoginSuccess(resOwner, 'nha_xe');
      return success;
    } catch (err1) {
      // Nếu lỗi 5xx hoặc không phải 401/422 thì quăng lỗi luôn (VD server down)
      const status1 = err1.response?.status;
      if (status1 !== 401 && status1 !== 422) {
        error.value = err1.response?.data?.message || err1.message || 'Lỗi kết nối máy chủ';
        loading.value = false;
        return false;
      }

      // 2. Thử đăng nhập Nhân viên
      try {
        const resEmp = await authApi.employeeLogin(credentials);
        const success = await processLoginSuccess(resEmp, 'nhan_vien');
        return success;
      } catch (err2) {
        // Lỗi cuối cùng (sai pass cả 2 bên) -> hiện lỗi
        error.value = err2.response?.data?.errors
          ? Object.values(err2.response.data.errors).flat()[0]
          : (err2.response?.data?.message || 'Email hoặc mật khẩu không chính xác.');
        return false;
      }
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
    if (notifications.value.length > 20) {
      notifications.value.pop();
    }
  }

  function markNotificationRead(id) {
    const note = notifications.value.find(n => n.id === id);
    if (note) note.read = true;
  }

  function logout() {
    // Gọi API đăng xuất phù hợp (fire-and-forget)
    if (token.value) {
      if (role.value === 'nhan_vien') {
        authApi.employeeLogout().catch(() => {});
      } else {
        authApi.operatorLogout().catch(() => {});
      }
    }
    token.value = null;
    user.value  = null;
    role.value  = 'nha_xe';
    isTokenVerified.value = false;
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    localStorage.removeItem(ROLE_KEY);
  }

  return {
    token, user, role, loading, error,
    isTokenVerified, isLoggedIn, isOwner, isEmployee,
    permissions,
    hasPermission,
    notifications,
    login, logout,
    addNotification, markNotificationRead,
  };
});
