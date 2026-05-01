<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import adminApi from '@/api/adminApi.js'
import { useAdminStore } from '@/stores/adminStore'
import BaseToast from '@/components/common/BaseToast.vue'

const PREFS_KEY = 'admin.personal.settings'
const personalInfo = ref({})
const isLoadingProfile = ref(false)
const profileError = ref('')

const defaultPrefs = {
  theme: 'light',
  language: 'vi',
  notifyCriticalOnly: false,
}

const settings = ref(loadPrefs())
const router = useRouter()
const adminStore = useAdminStore()

const i18n = {
  vi: {
    title: 'Cài đặt cá nhân',
    subtitle: 'Quản lý thông tin tài khoản, giao diện và tùy chọn theo sở thích cá nhân.',
    profile: 'Thông tin cá nhân',
    fullName: 'Họ tên',
    email: 'Email',
    phone: 'Số điện thoại',
    role: 'Chức vụ',
    accountSettings: 'Tùy chọn tài khoản',
    interfaceTheme: 'Giao diện',
    language: 'Ngôn ngữ',
    notifyMode: 'Chế độ nhận cảnh báo',
    criticalOnly: 'Chỉ nhận mức Critical',
    allLevels: 'Nhận cả Warning + Critical',
    loginHistory: 'Lịch sử đăng nhập gần đây',
    historyNote: 'Dữ liệu demo cục bộ, có thể map API backend.',
    save: 'Lưu cài đặt',
    saved: 'Đã lưu cài đặt cá nhân.',
    light: 'Sáng (Light)',
    dark: 'Tối (Dark)',
    vietnamese: 'Tiếng Việt',
    english: 'English',
    loading: 'Đang tải thông tin...',
  },
  en: {
    title: 'Personal Settings',
    subtitle: 'Manage profile information, interface preferences, and custom options.',
    profile: 'Profile Information',
    fullName: 'Full name',
    email: 'Email',
    phone: 'Phone',
    role: 'Role',
    accountSettings: 'Account Preferences',
    interfaceTheme: 'Theme',
    language: 'Language',
    notifyMode: 'Alert Mode',
    criticalOnly: 'Critical only',
    allLevels: 'Warning + Critical',
    loginHistory: 'Recent Login History',
    historyNote: 'Local demo data, ready for backend API mapping.',
    save: 'Save settings',
    saved: 'Personal settings saved.',
    light: 'Light',
    dark: 'Dark',
    vietnamese: 'Vietnamese',
    english: 'English',
    loading: 'Loading profile...',
  },
}

const t = computed(() => i18n[settings.value.language] || i18n.vi)
const saveStatus = ref('')
const toastVisible = ref(false)
const toastMessage = ref('')
const toastType = ref('success')
const isChangingPassword = ref(false)
const passwordErrors = ref({})
const passwordForm = ref({
  mat_khau_cu: '',
  mat_khau_moi: '',
  mat_khau_moi_confirmation: '',
})
const showPwCu = ref(false)
const showPwMoi = ref(false)
const showPwConf = ref(false)
let toastTimer = null

const loginHistory = ref([
  {
    id: 1,
    thoiGian: '2026-04-15 09:14:22',
    ip: '14.161.xx.xx',
    thietBi: 'Chrome - Windows',
    trangThai: 'Thành công',
  },
  {
    id: 2,
    thoiGian: '2026-04-14 21:05:38',
    ip: '14.161.xx.xx',
    thietBi: 'Edge - Windows',
    trangThai: 'Thành công',
  },
  {
    id: 3,
    thoiGian: '2026-04-13 08:44:03',
    ip: '27.78.xx.xx',
    thietBi: 'Chrome - Android',
    trangThai: 'Bị từ chối (Sai mật khẩu)',
  },
])

function loadPrefs() {
  try {
    const raw = localStorage.getItem(PREFS_KEY)
    if (!raw) return { ...defaultPrefs }
    return { ...defaultPrefs, ...JSON.parse(raw) }
  } catch {
    return { ...defaultPrefs }
  }
}

const applyTheme = (theme) => {
  document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'dark' : 'light')
}

const normalizeRole = (payload = {}) => {
  const roleSource = payload.chuc_vu ?? payload.role ?? payload.chucVu ?? null

  if (!roleSource) return 'Admin'

  if (typeof roleSource === 'object') {
    return roleSource.ten_chuc_vu || roleSource.ten || roleSource.name || roleSource.slug || 'Admin'
  }

  if (typeof roleSource === 'string') {
    const trimmedRole = roleSource.trim()
    if (!trimmedRole) return 'Admin'

    // Trường hợp backend trả role dưới dạng JSON string.
    if (trimmedRole.startsWith('{') && trimmedRole.endsWith('}')) {
      try {
        const parsedRole = JSON.parse(trimmedRole)
        return parsedRole.ten_chuc_vu || parsedRole.ten || parsedRole.name || parsedRole.slug || 'Admin'
      } catch {
        return 'Admin'
      }
    }

    return trimmedRole
  }

  return 'Admin'
}

const showToast = (message, type = 'success') => {
  toastMessage.value = message
  toastType.value = type
  toastVisible.value = true
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastVisible.value = false
  }, 3000)
}

const fetchProfile = async () => {
  isLoadingProfile.value = true
  profileError.value = ''
  try {
    const res = await adminApi.getMe()
    const payload = res?.data?.data || res?.data || {}
    personalInfo.value = {
      fullName: payload.ho_va_ten || payload.ho_ten || payload.ten || payload.name || 'Admin',
      email: payload.email || '---',
      phone: payload.so_dien_thoai || payload.phone || '---',
      role: normalizeRole(payload),
    }
    showToast('Tải thông tin cá nhân thành công.', 'success')
  } catch (error) {
    profileError.value = error?.response?.data?.message || error?.message || 'Không tải được thông tin cá nhân.'
    personalInfo.value = {
      fullName: 'Admin',
      email: '---',
      phone: '---',
      role: 'Admin',
    }
    showToast('Tải thông tin cá nhân thất bại.', 'error')
  } finally {
    isLoadingProfile.value = false
  }
}

const handleSave = () => {
  localStorage.setItem(PREFS_KEY, JSON.stringify(settings.value))
  saveStatus.value = t.value.saved
}

const resetPasswordForm = () => {
  passwordForm.value = {
    mat_khau_cu: '',
    mat_khau_moi: '',
    mat_khau_moi_confirmation: '',
  }
  passwordErrors.value = {}
}

const handleChangePassword = async () => {
  isChangingPassword.value = true
  passwordErrors.value = {}
  try {
    const payload = {
      mat_khau_cu: passwordForm.value.mat_khau_cu,
      mat_khau_moi: passwordForm.value.mat_khau_moi,
      mat_khau_moi_confirmation: passwordForm.value.mat_khau_moi_confirmation,
    }
    const res = await adminApi.changePassword(payload)
    showToast(res?.data?.message || 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.', 'success')
    resetPasswordForm()
    setTimeout(() => {
      adminStore.logout()
      router.replace({ name: 'admin-login' })
    }, 700)
  } catch (error) {
    passwordErrors.value = error?.response?.data?.errors || {}
    showToast(error?.response?.data?.message || 'Đổi mật khẩu thất bại.', 'error')
  } finally {
    isChangingPassword.value = false
  }
}

watch(
  () => settings.value.theme,
  (newTheme) => applyTheme(newTheme),
  { immediate: true },
)

watch(
  () => settings.value.language,
  () => {
    saveStatus.value = ''
  },
)

onMounted(() => {
  fetchProfile()
})
</script>

<template>
  <section class="settings-page">
    <BaseToast :visible="toastVisible" :message="toastMessage" :type="toastType" />

    <header>
      <h1 class="page-title">{{ t.title }}</h1>
      <p class="page-sub">{{ t.subtitle }}</p>
    </header>

    <div class="settings-grid">
      <article class="glass-card">
        <h2 class="card-title">{{ t.profile }}</h2>
        <p v-if="isLoadingProfile" class="muted">{{ t.loading }}</p>
        <p v-if="profileError" class="error-text">{{ profileError }}</p>
        <div class="profile-grid">
          <div class="profile-item">
            <span>{{ t.fullName }}</span>
            <strong>{{ personalInfo.fullName }}</strong>
          </div>
          <div class="profile-item">
            <span>{{ t.email }}</span>
            <strong>{{ personalInfo.email }}</strong>
          </div>
          <div class="profile-item">
            <span>{{ t.phone }}</span>
            <strong>{{ personalInfo.phone }}</strong>
          </div>
          <div class="profile-item">
            <span>{{ t.role }}</span>
            <strong>{{ personalInfo.role }}</strong>
          </div>
        </div>
      </article>

      <article class="glass-card">
        <h2 class="card-title">{{ t.accountSettings }}</h2>
        <div class="field">
          <label>{{ t.interfaceTheme }}</label>
          <select v-model="settings.theme">
            <option value="light">{{ t.light }}</option>
            <option value="dark">{{ t.dark }}</option>
          </select>
        </div>

        <div class="field">
          <label>{{ t.language }}</label>
          <select v-model="settings.language">
            <option value="vi">{{ t.vietnamese }}</option>
            <option value="en">{{ t.english }}</option>
          </select>
        </div>

        <div class="field">
          <label>{{ t.notifyMode }}</label>
          <select v-model="settings.notifyCriticalOnly">
            <option :value="false">{{ t.allLevels }}</option>
            <option :value="true">{{ t.criticalOnly }}</option>
          </select>
        </div>

        <button class="btn-save" @click="handleSave">{{ t.save }}</button>
        <p v-if="saveStatus" class="saved-text">{{ saveStatus }}</p>

        <div class="divider"></div>

        <h3 class="sub-title">Đổi mật khẩu</h3>
        <div class="field">
          <label>Mật khẩu hiện tại</label>
          <div class="pw-field">
            <input
              v-model="passwordForm.mat_khau_cu"
              :type="showPwCu ? 'text' : 'password'"
              placeholder="Nhập mật khẩu hiện tại"
              autocomplete="current-password"
            />
            <button type="button" class="pw-toggle" :aria-label="showPwCu ? 'Ẩn' : 'Hiện'" @click="showPwCu = !showPwCu">
              <span class="material-symbols-outlined">{{ showPwCu ? 'visibility_off' : 'visibility' }}</span>
            </button>
          </div>
          <small v-if="passwordErrors.mat_khau_cu" class="error-text">{{ passwordErrors.mat_khau_cu[0] }}</small>
        </div>

        <div class="field">
          <label>Mật khẩu mới</label>
          <div class="pw-field">
            <input
              v-model="passwordForm.mat_khau_moi"
              :type="showPwMoi ? 'text' : 'password'"
              placeholder="Ít nhất 6 ký tự"
              autocomplete="new-password"
            />
            <button type="button" class="pw-toggle" :aria-label="showPwMoi ? 'Ẩn' : 'Hiện'" @click="showPwMoi = !showPwMoi">
              <span class="material-symbols-outlined">{{ showPwMoi ? 'visibility_off' : 'visibility' }}</span>
            </button>
          </div>
          <small v-if="passwordErrors.mat_khau_moi" class="error-text">{{ passwordErrors.mat_khau_moi[0] }}</small>
        </div>

        <div class="field">
          <label>Xác nhận mật khẩu mới</label>
          <div class="pw-field">
            <input
              v-model="passwordForm.mat_khau_moi_confirmation"
              :type="showPwConf ? 'text' : 'password'"
              placeholder="Nhập lại mật khẩu mới"
              autocomplete="new-password"
            />
            <button type="button" class="pw-toggle" :aria-label="showPwConf ? 'Ẩn' : 'Hiện'" @click="showPwConf = !showPwConf">
              <span class="material-symbols-outlined">{{ showPwConf ? 'visibility_off' : 'visibility' }}</span>
            </button>
          </div>
        </div>

        <button class="btn-save btn-password" :disabled="isChangingPassword" @click="handleChangePassword">
          {{ isChangingPassword ? 'Đang cập nhật...' : 'Cập nhật mật khẩu' }}
        </button>
      </article>
    </div>

    <article class="glass-card">
      <h2 class="card-title">{{ t.loginHistory }}</h2>
      <p class="muted">{{ t.historyNote }}</p>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Thời gian</th>
              <th>IP</th>
              <th>Thiết bị</th>
              <th>Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in loginHistory" :key="row.id">
              <td>{{ row.thoiGian }}</td>
              <td>{{ row.ip }}</td>
              <td>{{ row.thietBi }}</td>
              <td>
                <span class="status-pill" :class="{ danger: row.trangThai.includes('Bị từ chối') }">
                  {{ row.trangThai }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </article>
  </section>
</template>

<style scoped>
.settings-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.page-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
}

.page-sub {
  margin-top: 6px;
  color: #64748b;
  font-size: 14px;
}

.settings-grid {
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  gap: 16px;
}

.glass-card {
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
  padding: 16px;
}

.card-title {
  margin: 0 0 12px 0;
  font-size: 16px;
}

.profile-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.profile-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.profile-item span {
  font-size: 12px;
  color: #64748b;
}

.profile-item strong {
  font-size: 14px;
  color: #0f172a;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.field label {
  font-size: 13px;
  color: #475569;
  font-weight: 600;
}

.field select {
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  padding: 10px;
  font-size: 14px;
}

.field input {
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  padding: 10px;
  font-size: 14px;
}

.pw-field {
  position: relative;
  display: flex;
  align-items: center;
}

.pw-field input {
  width: 100%;
  padding-right: 44px;
}

.pw-toggle {
  position: absolute;
  right: 6px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  padding: 6px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pw-toggle:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.pw-toggle .material-symbols-outlined {
  font-size: 22px;
}

.btn-save {
  width: 100%;
  border: none;
  border-radius: 10px;
  padding: 10px 12px;
  font-weight: 700;
  color: white;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  cursor: pointer;
}

.btn-save:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.btn-password {
  margin-top: 4px;
}

.divider {
  margin: 14px 0;
  border-top: 1px solid #e2e8f0;
}

.sub-title {
  margin: 0 0 10px 0;
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}

.saved-text {
  margin-top: 10px;
  font-size: 13px;
  color: #0369a1;
}

.muted {
  color: #64748b;
  font-size: 13px;
}

.error-text {
  margin-bottom: 10px;
  font-size: 13px;
  color: #dc2626;
}

.table-wrap {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

th,
td {
  text-align: left;
  padding: 11px 8px;
  border-bottom: 1px solid #e2e8f0;
}

th {
  font-size: 12px;
  text-transform: uppercase;
  color: #64748b;
}

.status-pill {
  display: inline-flex;
  padding: 3px 9px;
  border-radius: 999px;
  background: #dcfce7;
  color: #166534;
  font-size: 12px;
  font-weight: 700;
}

.status-pill.danger {
  background: #fee2e2;
  color: #b91c1c;
}

@media (max-width: 1024px) {
  .settings-grid,
  .profile-grid {
    grid-template-columns: 1fr;
  }
}
</style>
