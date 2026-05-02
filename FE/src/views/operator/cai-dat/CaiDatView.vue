<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import operatorApi from '@/api/operatorApi.js'
import { useOperatorStore } from '@/stores/operatorStore'
import BaseToast from '@/components/common/BaseToast.vue'

const profile = ref({
  ten_nha_xe: 'Nhà xe',
  email: '---',
  so_dien_thoai: '---',
  ma_nha_xe: '---',
  ten_cong_ty: '---',
  ma_so_thue: '---',
  nguoi_dai_dien: '---',
  so_du_vi: '---',
})
const router = useRouter()
const operatorStore = useOperatorStore()
const isLoading = ref(false)
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

const toastVisible = ref(false)
const toastMessage = ref('')
const toastType = ref('success')
let toastTimer = null

const showToast = (message, type = 'success') => {
  toastMessage.value = message
  toastType.value = type
  toastVisible.value = true
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toastVisible.value = false
  }, 3000)
}

const resetPasswordForm = () => {
  passwordForm.value = {
    mat_khau_cu: '',
    mat_khau_moi: '',
    mat_khau_moi_confirmation: '',
  }
  passwordErrors.value = {}
}

const fetchProfile = async () => {
  isLoading.value = true
  try {
    const res = await operatorApi.getProfile()
    // axiosClient đã unwrap response => res thường là { success, data }
    // nhưng vẫn fallback các dạng cũ để tương thích.
    const payload =
      res?.data?.nha_xe ||
      res?.data ||
      res?.nha_xe ||
      res?.data?.data?.nha_xe ||
      res?.data?.data ||
      res?.data?.nha_xe ||
      {}
    const hoSo = payload.ho_so || {}
    const viTopup = payload.vi_top_up || {}
    profile.value = {
      ten_nha_xe: payload.ten_nha_xe || payload.ten || payload.name || 'Nhà xe',
      email: payload.email || '---',
      so_dien_thoai: payload.so_dien_thoai || payload.phone || '---',
      ma_nha_xe: payload.ma_nha_xe || '---',
      ten_cong_ty: hoSo.ten_cong_ty || '---',
      ma_so_thue: hoSo.ma_so_thue || '---',
      nguoi_dai_dien: hoSo.nguoi_dai_dien || '---',
      so_du_vi: viTopup.so_du || '---',
    }
  } catch (error) {
    const fallback = operatorStore.user || {}
    profile.value = {
      ten_nha_xe: fallback.ten_nha_xe || 'Nhà xe',
      email: fallback.email || '---',
      so_dien_thoai: fallback.so_dien_thoai || '---',
      ma_nha_xe: fallback.ma_nha_xe || '---',
      ten_cong_ty: '---',
      ma_so_thue: '---',
      nguoi_dai_dien: '---',
      so_du_vi: '---',
    }
    showToast(error?.response?.data?.message || 'Không tải được thông tin nhà xe.', 'error')
  } finally {
    isLoading.value = false
  }
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
    const res = await operatorApi.changePassword(payload)
    showToast(res?.data?.message || 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.', 'success')
    resetPasswordForm()
    setTimeout(() => {
      operatorStore.logout()
      router.replace({ name: 'operator-login' })
    }, 700)
  } catch (error) {
    passwordErrors.value = error?.response?.data?.errors || {}
    showToast(error?.response?.data?.message || 'Đổi mật khẩu thất bại.', 'error')
  } finally {
    isChangingPassword.value = false
  }
}

onMounted(fetchProfile)
</script>

<template>
  <section class="operator-page">
    <BaseToast :visible="toastVisible" :message="toastMessage" :type="toastType" />
    <h1 class="page-title">Cài đặt tài khoản nhà xe</h1>

    <article class="card">
      <h2>Thông tin tài khoản</h2>
      <p v-if="isLoading" class="muted">Đang tải thông tin...</p>
      <div v-else class="profile-grid">
        <div class="profile-item">
          <span>Mã nhà xe</span>
          <strong>{{ profile.ma_nha_xe }}</strong>
        </div>
        <div class="profile-item">
          <span>Tên nhà xe</span>
          <strong>{{ profile.ten_nha_xe }}</strong>
        </div>
        <div class="profile-item">
          <span>Email</span>
          <strong>{{ profile.email }}</strong>
        </div>
        <div class="profile-item">
          <span>Số điện thoại</span>
          <strong>{{ profile.so_dien_thoai }}</strong>
        </div>
        <div class="profile-item">
          <span>Công ty</span>
          <strong>{{ profile.ten_cong_ty }}</strong>
        </div>
        <div class="profile-item">
          <span>Mã số thuế</span>
          <strong>{{ profile.ma_so_thue }}</strong>
        </div>
        <div class="profile-item">
          <span>Người đại diện</span>
          <strong>{{ profile.nguoi_dai_dien }}</strong>
        </div>
        <div class="profile-item">
          <span>Số dư ví</span>
          <strong>{{ profile.so_du_vi }}</strong>
        </div>
      </div>
    </article>

    <article class="card">
      <h2>Đổi mật khẩu</h2>
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

      <button class="btn-save" :disabled="isChangingPassword" @click="handleChangePassword">
        {{ isChangingPassword ? 'Đang cập nhật...' : 'Cập nhật mật khẩu' }}
      </button>
    </article>
  </section>
</template>

<style scoped>
.operator-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.page-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  color: #0d4f35;
}

.card {
  background: white;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 6px 20px rgba(2, 6, 23, 0.04);
}

.card h2 {
  margin: 0 0 12px 0;
  font-size: 16px;
}

.profile-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
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
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.field label {
  font-size: 13px;
  font-weight: 600;
  color: #475569;
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
  background: linear-gradient(135deg, #0d4f35, #15803d);
  cursor: pointer;
}

.btn-save:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.muted {
  color: #64748b;
  font-size: 13px;
}

.error-text {
  font-size: 12px;
  color: #dc2626;
}

@media (max-width: 1024px) {
  .profile-grid {
    grid-template-columns: 1fr;
  }
}
</style>
