<script setup>
import { ref, reactive, onMounted } from 'vue'
import operatorApi from '@/api/operatorApi'
import { compressImage } from '@/utils/imageCompression'
import BaseTable from '@/components/common/BaseTable.vue'
import BaseButton from '@/components/common/BaseButton.vue'
import BaseInput from '@/components/common/BaseInput.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import BaseToast from '@/components/common/BaseToast.vue'

const toast = reactive({ visible: false, message: '', type: 'success' })
const showToast = (message, type = 'success') => {
  toast.message = message
  toast.type = type
  toast.visible = true
  setTimeout(() => {
    toast.visible = false
  }, 3500)
}

const loading = ref(false)
const confirmModal = reactive({ visible: false, title: '', message: '', onConfirm: null, variant: 'primary' })
const drivers = ref([])
const searchQuery = ref('')
const filterStatus = ref('')
const pagination = reactive({ currentPage: 1, perPage: 15, total: 0, lastPage: 1 })

const tableColumns = [
  { key: 'avatar', label: 'Ảnh' },
  { key: 'thong_tin', label: 'Tài xế' },
  { key: 'lien_he', label: 'Liên hệ' },
  { key: 'giay_to', label: 'Giấy tờ' },
  { key: 'trang_thai', label: 'Trạng Thái' },
  { key: 'actions', label: 'Hành Động' },
]

const getStatus = (status) => {
  if (status === 'hoat_dong') return { text: 'Hoạt Động', class: 'status-approved' }
  if (status === 'khoa') return { text: 'Bị Khóa', class: 'status-locked' }
  if (status === 'cho_duyet') return { text: 'Chờ duyệt', class: 'status-pending' }
  return { text: 'Không rõ', class: '' }
}

const extractListAndPage = (response) => {
  let listData = []
  let pageData = {}

  if (Array.isArray(response?.data?.data?.data)) {
    listData = response.data.data.data
    pageData = response.data
  } else if (Array.isArray(response?.data?.data)) {
    listData = response.data.data
    pageData = response.data
  } else if (Array.isArray(response?.data)) {
    listData = response.data
    pageData = response
  } else if (Array.isArray(response)) {
    listData = response
    pageData = {}
  }

  return { listData, pageData }
}

const fetchDrivers = async (page = 1) => {
  try {
    loading.value = true
    const response = await operatorApi.getDrivers({
      page,
      per_page: pagination.perPage,
      search: searchQuery.value || undefined,
      tinh_trang: filterStatus.value || undefined,
    })

    const { listData, pageData } = extractListAndPage(response)

    drivers.value = listData
    pagination.currentPage = pageData.current_page || page
    pagination.perPage = pageData.per_page || pagination.perPage
    pagination.total = pageData.total || listData.length
    pagination.lastPage = pageData.last_page || 1
  } catch (error) {
    console.error('Lỗi tải danh sách tài xế:', error)
    showToast('Không thể tải danh sách tài xế!', 'error')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  fetchDrivers(1)
}

const resetFilter = () => {
  searchQuery.value = ''
  filterStatus.value = ''
  fetchDrivers(1)
}

const isFormModal = ref(false)
const isEditMode = ref(false)
const currentDriverId = ref(null)
const formLoading = ref(false)

const licenseClasses = [
  { value: 'B1', label: 'Hạng B1' },
  { value: 'B2', label: 'Hạng B2' },
  { value: 'C', label: 'Hạng C' },
  { value: 'D', label: 'Hạng D' },
  { value: 'E', label: 'Hạng E' },
  { value: 'F', label: 'Hạng F' },
]

const initialFormData = () => ({
  ho_va_ten: '',
  email: '',
  password: '',
  cccd: '',
  so_dien_thoai: '',
  ngay_sinh: '',
  dia_chi: '',
  so_gplx: '',
  hang_bang_lai: '',
  ngay_cap_gplx: '',
  ngay_het_han_gplx: '',
  avatar: null,
  anh_cccd_mat_truoc: null,
  anh_cccd_mat_sau: null,
  anh_gplx: null,
  anh_gplx_mat_sau: null,
})

const formData = reactive(initialFormData())
const filePreviews = reactive({
  avatar: null,
  anh_cccd_mat_truoc: null,
  anh_cccd_mat_sau: null,
  anh_gplx: null,
  anh_gplx_mat_sau: null,
})

const handleFileUpload = (event, field) => {
  const file = event.target.files[0]
  if (file) {
    formData[field] = file
    filePreviews[field] = URL.createObjectURL(file)
  }
}

const openCreateModal = () => {
  isEditMode.value = false
  currentDriverId.value = null
  Object.assign(formData, initialFormData())
  for (let key in filePreviews) filePreviews[key] = null
  isFormModal.value = true
}

const openEditModal = (driver) => {
  isEditMode.value = true
  currentDriverId.value = driver.id
  Object.assign(formData, initialFormData())
  formData.ho_va_ten = driver.ho_va_ten || driver.hoSo?.ho_va_ten || ''
  formData.email = driver.email
  formData.cccd = driver.cccd
  formData.so_dien_thoai = driver.so_dien_thoai || driver.hoSo?.so_dien_thoai || ''

  if (driver.hoSo) {
    formData.ngay_sinh = driver.hoSo.ngay_sinh || ''
    formData.dia_chi = driver.hoSo.dia_chi || ''
    formData.so_gplx = driver.hoSo.so_gplx || ''
    formData.hang_bang_lai = driver.hoSo.hang_bang_lai || ''
    formData.ngay_cap_gplx = driver.hoSo.ngay_cap_gplx || ''
    formData.ngay_het_han_gplx = driver.hoSo.ngay_het_han_gplx || ''
  }

  for (let key in filePreviews) {
    filePreviews[key] = driver[key] || driver.hoSo?.[key] || null
  }

  isFormModal.value = true
}

const submitForm = async () => {
  try {
    formLoading.value = true
    const payload = new FormData()

    payload.append('ho_va_ten', formData.ho_va_ten)
    payload.append('email', formData.email)
    payload.append('cccd', formData.cccd)
    payload.append('so_dien_thoai', formData.so_dien_thoai)

    payload.append('ngay_sinh', formData.ngay_sinh || '')
    payload.append('dia_chi', formData.dia_chi || '')
    payload.append('so_gplx', formData.so_gplx || '')
    payload.append('hang_bang_lai', formData.hang_bang_lai || '')
    payload.append('ngay_cap_gplx', formData.ngay_cap_gplx || '')
    payload.append('ngay_het_han_gplx', formData.ngay_het_han_gplx || '')

    if (formData.password) {
      payload.append('password', formData.password)
    }

    const fileFields = ['avatar', 'anh_cccd_mat_truoc', 'anh_cccd_mat_sau', 'anh_gplx', 'anh_gplx_mat_sau']
    for (const field of fileFields) {
      if (formData[field] instanceof File) {
        const compressed = await compressImage(formData[field], { quality: 0.6 })
        payload.append(field, compressed)
      }
    }

    if (isEditMode.value) {
      await operatorApi.updateDriver(currentDriverId.value, payload)
      showToast('Cập nhật tài xế thành công! Trạng thái đã chuyển về Chờ duyệt.', 'success')
    } else {
      await operatorApi.createDriver(payload)
      showToast('Thêm tài xế mới thành công! Đang chờ Admin duyệt.', 'success')
    }

    isFormModal.value = false
    fetchDrivers(isEditMode.value ? pagination.currentPage : 1)
  } catch (error) {
    console.error('Lỗi khi lưu tài xế:', error)
    const message = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat()[0]
      : (error.response?.data?.message || 'Lưu thông tin thất bại!')
    showToast(message, 'error')
  } finally {
    formLoading.value = false
  }
}

const openConfirm = (title, message, onConfirm, variant = 'primary') => {
  confirmModal.title = title
  confirmModal.message = message
  confirmModal.onConfirm = onConfirm
  confirmModal.variant = variant
  confirmModal.visible = true
}

const handleConfirmAction = async () => {
  const callback = confirmModal.onConfirm
  confirmModal.visible = false
  if (callback) await callback()
}

const requestDelete = (id) => {
  openConfirm(
    'Xác nhận xóa',
    'Bạn có chắc muốn xóa tài khoản này không? Hệ thống sẽ chuyển trạng thái tài khoản về "Chờ duyệt" để tiến hành xóa.',
    async () => {
      try {
        await operatorApi.deleteDriver(id)
        showToast('Đã gửi yêu cầu xóa thành công!', 'success')
        fetchDrivers(pagination.currentPage)
      } catch (error) {
        showToast(error.response?.data?.message || 'Lỗi khi yêu cầu xóa.', 'error')
      }
    },
    'danger'
  )
}

onMounted(() => {
  fetchDrivers()
})
</script>

<template>
  <div class="operator-page">
    <BaseToast :visible="toast.visible" :message="toast.message" :type="toast.type" />

    <div class="page-header">
      <div>
        <h1 class="page-title">Quản Lý Tài Xế</h1>
        <p class="page-sub">Quản lý hồ sơ tài xế thuộc nhà xe của bạn. Thêm hoặc Sửa sẽ yêu cầu duyệt lại.</p>
      </div>
      <BaseButton variant="primary" @click="openCreateModal">+ Thêm Tài Xế</BaseButton>
    </div>

    <div class="filter-card">
      <div class="filter-row">
        <div class="search-box">
          <BaseInput v-model="searchQuery" placeholder="Tìm Email, CCCD, Tên..." @keyup.enter="handleSearch" />
          <BaseButton variant="secondary" @click="handleSearch">Tìm</BaseButton>
        </div>

        <div class="filter-group">
          <label class="filter-label">Trạng thái</label>
          <select v-model="filterStatus" class="custom-select" @change="handleSearch">
            <option value="">Tất cả</option>
            <option value="hoat_dong">Hoạt Động</option>
            <option value="khoa">Bị Khóa</option>
            <option value="cho_duyet">Chờ duyệt</option>
          </select>
        </div>

        <BaseButton variant="outline" @click="resetFilter">Đặt lại</BaseButton>
      </div>
    </div>

    <div class="table-card">
      <BaseTable :columns="tableColumns" :data="drivers" :loading="loading">
        <template #cell(avatar)="{ item }">
          <div class="avatar-cell">
            <img v-if="item.avatar" :src="item.avatar" alt="Avatar" class="driver-avatar" />
            <div v-else class="avatar-placeholder">?</div>
          </div>
        </template>

        <template #cell(thong_tin)="{ item }">
          <div class="info-block">
            <strong>{{ item.hoSo?.ho_va_ten || 'Đang cập nhật' }}</strong>
            <span class="sub-text">{{ item.email }}</span>
          </div>
        </template>

        <template #cell(lien_he)="{ item }">
          <div class="info-block">
            <span>{{ item.so_dien_thoai || 'N/A' }}</span>
          </div>
        </template>

        <template #cell(giay_to)="{ item }">
          <div class="info-block">
            <span class="sub-text">Bằng lái:</span>
            <strong style="color: #0d4f35; font-size: 14px;">{{ item.hoSo?.so_gplx || 'N/A' }}</strong>
            <span class="sub-text">Hạng: {{ item.hoSo?.hang_bang_lai || 'N/A' }}</span>
          </div>
        </template>

        <template #cell(trang_thai)="{ item }">
          <span :class="['status-badge', getStatus(item.tinh_trang).class]">
            {{ getStatus(item.tinh_trang).text }}
          </span>
        </template>

        <template #cell(actions)="{ item }">
          <div class="action-buttons">
            <BaseButton size="sm" variant="outline" @click="openEditModal(item)">Sửa</BaseButton>
            <BaseButton size="sm" style="color: red; border-color: red" variant="outline"
              @click="requestDelete(item.id)">Xóa</BaseButton>
          </div>
        </template>
      </BaseTable>

      <div class="pagination-container">
        <div class="page-info-left">
          <span>Hiển thị:</span>
          <select v-model="pagination.perPage" @change="fetchDrivers(1)" class="custom-select per-page-select">
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
          </select>
          <span>dòng / trang</span>
          <span v-if="pagination.total > 0" class="total-label">(Tổng: {{ pagination.total }} tài xế)</span>
        </div>

        <div class="pagination-controls">
          <BaseButton size="sm" variant="outline" :disabled="pagination.currentPage <= 1"
            @click="fetchDrivers(pagination.currentPage - 1)">Trước</BaseButton>

          <span class="page-number">Trang {{ pagination.currentPage }} / {{ pagination.lastPage }}</span>

          <BaseButton size="sm" variant="outline" :disabled="pagination.currentPage >= pagination.lastPage"
            @click="fetchDrivers(pagination.currentPage + 1)">Sau</BaseButton>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <BaseModal v-model="isFormModal" :title="isEditMode ? 'Cập Nhật Tài Xế' : 'Thêm Tài Xế Mới'" maxWidth="800px">
      <div class="info-banner">
        <span class="info-icon">ℹ️</span>
        <span v-if="isEditMode">Sau khi cập nhật, hệ thống sẽ chuyển tài xế về trạng thái <strong>Chờ
            duyệt</strong>.</span>
        <span v-else>Tài xế mới được tạo sẽ ở trạng thái <strong>Chờ duyệt</strong> cho tới khi Admin phê duyệt. Vui
          lòng
          thêm các ảnh nhận dạng chân thực nhất.</span>
      </div>

      <!-- Loading Overlay -->
      <div v-if="formLoading" class="upload-overlay">
        <div class="upload-spinner-box">
          <svg class="spinner-main" viewBox="0 0 50 50">
            <circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle>
          </svg>
          <p>Đang tải ảnh và lưu hồ sơ tài xế...</p>
          <span class="sub-tip">Vui lòng không đóng trình duyệt lúc này</span>
        </div>
      </div>

      <form @submit.prevent="submitForm" class="driver-form">
        <!-- Thông tin cơ bản -->
        <h3 class="section-title">Thông tin xác thực & Liên hệ</h3>
        <div class="form-grid">
          <BaseInput v-model="formData.ho_va_ten" label="Họ và tên *" placeholder="Nguyễn Văn A" required />
          <BaseInput v-model="formData.email" type="email" label="Email đăng nhập *" placeholder="nhanvien@email.com"
            required />
          <BaseInput v-model="formData.cccd" label="Số CCCD *" placeholder="012345678912" required />
          <BaseInput v-model="formData.so_dien_thoai" label="Số điện thoại *" placeholder="0987654321" required />
          <BaseInput v-if="!isEditMode" v-model="formData.password" type="password" label="Mật khẩu *" required />
        </div>

        <!-- Thông tin bằng lái -->
        <h3 class="section-title mt-4">Hồ sơ & Giấy phép lái xe</h3>
        <div class="form-grid">
          <BaseInput v-model="formData.ngay_sinh" type="date" label="Ngày sinh" />
          <BaseInput v-model="formData.dia_chi" label="Địa chỉ" placeholder="123 Đường ABC, Quận X" />
          <BaseInput v-model="formData.so_gplx" label="Số GPLX *" placeholder="123456789012" required />

          <div class="form-group">
            <label class="base-input-label">Hạng bằng lái *</label>
            <select v-model="formData.hang_bang_lai" class="custom-select" required>
              <option value="" disabled>-- Chọn Hạng Bằng --</option>
              <option v-for="item in licenseClasses" :key="item.value" :value="item.value">
                {{ item.label }}
              </option>
            </select>
          </div>

          <BaseInput v-model="formData.ngay_cap_gplx" type="date" label="Ngày cấp GPLX *" required />
          <BaseInput v-model="formData.ngay_het_han_gplx" type="date" label="Ngày hết hạn GPLX *" required />
        </div>

        <h3 class="section-title mt-4">Hình ảnh hồ sơ tài liệu</h3>
        <div class="file-grid">
          <div class="file-group">
            <label class="base-input-label">Ảnh Avatar <span v-if="!isEditMode">*</span></label>
            <div class="file-upload-box" @click="$refs.avatarInput.click()">
              <img v-if="filePreviews.avatar" :src="filePreviews.avatar" alt="Preview" class="file-preview" />
              <div v-else class="upload-placeholder">
                <span>Tải ảnh lên</span>
              </div>
            </div>
            <input type="file" ref="avatarInput" hidden accept="image/*" @change="handleFileUpload($event, 'avatar')" />
          </div>

          <div class="file-group">
            <label class="base-input-label">CCCD Mặt trước <span v-if="!isEditMode">*</span></label>
            <div class="file-upload-box" @click="$refs.cccd1Input.click()">
              <img v-if="filePreviews.anh_cccd_mat_truoc" :src="filePreviews.anh_cccd_mat_truoc" alt="Preview"
                class="file-preview" />
              <div v-else class="upload-placeholder">
                <span>Tải ảnh lên</span>
              </div>
            </div>
            <input type="file" ref="cccd1Input" hidden accept="image/*"
              @change="handleFileUpload($event, 'anh_cccd_mat_truoc')" />
          </div>

          <div class="file-group">
            <label class="base-input-label">CCCD Mặt sau <span v-if="!isEditMode">*</span></label>
            <div class="file-upload-box" @click="$refs.cccd2Input.click()">
              <img v-if="filePreviews.anh_cccd_mat_sau" :src="filePreviews.anh_cccd_mat_sau" alt="Preview"
                class="file-preview" />
              <div v-else class="upload-placeholder">
                <span>Tải ảnh lên</span>
              </div>
            </div>
            <input type="file" ref="cccd2Input" hidden accept="image/*"
              @change="handleFileUpload($event, 'anh_cccd_mat_sau')" />
          </div>

          <div class="file-group">
            <label class="base-input-label">Ảnh GPLX Mặt trước <span v-if="!isEditMode">*</span></label>
            <div class="file-upload-box" @click="$refs.gplx1Input.click()">
              <img v-if="filePreviews.anh_gplx" :src="filePreviews.anh_gplx" alt="Preview" class="file-preview" />
              <div v-else class="upload-placeholder">
                <span>Tải ảnh lên</span>
              </div>
            </div>
            <input type="file" ref="gplx1Input" hidden accept="image/*"
              @change="handleFileUpload($event, 'anh_gplx')" />
          </div>

          <div class="file-group">
            <label class="base-input-label">Ảnh GPLX Mặt sau <span v-if="!isEditMode">*</span></label>
            <div class="file-upload-box" @click="$refs.gplx2Input.click()">
              <img v-if="filePreviews.anh_gplx_mat_sau" :src="filePreviews.anh_gplx_mat_sau" alt="Preview"
                class="file-preview" />
              <div v-else class="upload-placeholder">
                <span>Tải ảnh lên</span>
              </div>
            </div>
            <input type="file" ref="gplx2Input" hidden accept="image/*"
              @change="handleFileUpload($event, 'anh_gplx_mat_sau')" />
          </div>
        </div>
      </form>

      <template #footer>
        <BaseButton variant="secondary" @click="isFormModal = false">Hủy</BaseButton>
        <BaseButton variant="primary" :loading="formLoading" @click="submitForm">
          {{ isEditMode ? 'Lưu yêu cầu sửa' : 'Thêm tài xế' }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- Custom Confirmation Modal -->
    <BaseModal v-model="confirmModal.visible" :title="confirmModal.title" maxWidth="450px">
      <div class="confirm-modal-content">
        <div class="confirm-icon" :class="confirmModal.variant">
          <span v-if="confirmModal.variant === 'danger'">⚠️</span>
          <span v-else>❓</span>
        </div>
        <p class="confirm-message">{{ confirmModal.message }}</p>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="confirmModal.visible = false">Hủy</BaseButton>
        <BaseButton :variant="confirmModal.variant" @click="handleConfirmAction">Xác nhận</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.operator-page {
  padding: 0;
  font-family: 'Inter', system-ui, sans-serif;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  gap: 12px;
  flex-wrap: wrap;
}

.page-title {
  font-size: 22px;
  font-weight: 800;
  color: #0d4f35;
  margin: 0 0 4px 0;
}

.page-sub {
  margin: 0;
  color: #64748b;
  font-size: 13px;
}

.filter-card {
  background: rgba(255, 255, 255, 0.86);
  backdrop-filter: blur(10px);
  border: 1px solid #dcfce7;
  box-shadow: 0 8px 20px rgba(13, 79, 53, 0.06);
  border-radius: 14px;
  padding: 16px;
  margin-bottom: 18px;
}

.filter-row {
  display: flex;
  gap: 14px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.search-box {
  display: flex;
  gap: 10px;
  align-items: flex-end;
  flex: 1;
  min-width: 280px;
}

.search-box> :first-child {
  flex: 1;
  margin-bottom: 0;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 180px;
}

.filter-label,
.base-input-label {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.table-card {
  background: #fff;
  border: 1px solid #dcfce7;
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 8px 24px rgba(13, 79, 53, 0.06);
}

.avatar-cell {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  overflow: hidden;
  background-color: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
}

.driver-avatar {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.avatar-placeholder {
  font-size: 20px;
  font-weight: 700;
  color: #94a3b8;
}

.info-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.sub-text {
  font-size: 12px;
  color: #64748b;
}

.status-badge {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  border-radius: 999px;
  padding: 4px 10px;
}

.status-approved {
  background: #dcfce7;
  color: #166534;
}

.status-locked {
  background: #fee2e2;
  color: #b91c1c;
}

.status-pending {
  background: #fef3c7;
  color: #b45309;
}

.action-buttons {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.pagination-container {
  margin-top: 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.page-info-left {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #64748b;
}

.total-label {
  color: #94a3b8;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-number {
  color: #334155;
  font-size: 14px;
  font-weight: 600;
}

.per-page-select {
  width: 72px !important;
}

.info-banner {
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 10px;
  padding: 10px 12px;
  font-size: 13px;
  color: #92400e;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.section-title {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 12px 0;
  padding-bottom: 8px;
  border-bottom: 1px solid #e2e8f0;
}

.mt-4 {
  margin-top: 24px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.file-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 16px;
}

.file-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.file-upload-box {
  width: 100%;
  aspect-ratio: 1;
  border: 2px dashed #cbd5e1;
  border-radius: 12px;
  background-color: #f8fafc;
  cursor: pointer;
  overflow: hidden;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.file-upload-box:hover {
  border-color: #10b981;
  background-color: #ecfdf5;
}

.upload-placeholder {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-align: center;
  padding: 10px;
}

.file-preview {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.custom-input,
.custom-select {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 14px;
  color: #1f2937;
  background: white;
  transition: all 0.2s ease;
}

.custom-input:focus,
.custom-select:focus {
  outline: none;
  border-color: #16a34a;
  box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
}

@media (max-width: 768px) {
  .filter-row {
    flex-direction: column;
    align-items: stretch;
  }

  .search-box {
    flex-direction: column;
    align-items: stretch;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .file-grid {
    grid-template-columns: 1fr 1fr;
  }
}

.upload-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(2px);
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: inherit;
}

.upload-spinner-box {
  background: white;
  padding: 30px 40px;
  border-radius: 20px;
  color: #0f172a;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.upload-spinner-box p {
  margin: 0;
  font-weight: 700;
  font-size: 16px;
}

.sub-tip {
  font-size: 12px;
  color: #64748b;
}

.spinner-main {
  width: 40px;
  height: 40px;
  animation: rotate 2s linear infinite;
}

.spinner-main .path {
  stroke: #10b981;
  stroke-linecap: round;
  animation: dash 1.5s ease-in-out infinite;
}

@keyframes rotate {
  100% {
    transform: rotate(360deg);
  }
}

@keyframes dash {
  0% {
    stroke-dasharray: 1, 150;
    stroke-dashoffset: 0;
  }

  50% {
    stroke-dasharray: 90, 150;
    stroke-dashoffset: -35;
  }

  100% {
    stroke-dasharray: 90, 150;
    stroke-dashoffset: -124;
  }
}
</style>
