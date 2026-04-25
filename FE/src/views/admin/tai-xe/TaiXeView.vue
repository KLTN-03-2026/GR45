<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import adminApi from '@/api/adminApi'
import BaseTable from '@/components/common/BaseTable.vue'
import BaseButton from '@/components/common/BaseButton.vue'
import BaseInput from '@/components/common/BaseInput.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import BaseToast from '@/components/common/BaseToast.vue'
import BaseSelect from '@/components/common/BaseSelect.vue'

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
const operators = ref([]) // Để làm select
const searchQuery = ref('')
const filterStatus = ref('')
const filterNhaXe = ref('')
const pagination = reactive({ currentPage: 1, perPage: 15, total: 0, lastPage: 1 })
const isSuperAdmin = computed(() => {
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  // SuperAdmin cao nhất (is_master=1 hoặc email cụ thể)
  return user.is_master === 1 || user.email === 'superadmin@xekhachu.vn'
})

const tableColumns = [
  { key: 'avatar', label: 'Ảnh' },
  { key: 'thong_tin', label: 'Tài xế' },
  { key: 'nha_xe', label: 'Nhà Xe' },
  { key: 'lien_he', label: 'Liên hệ' },
  { key: 'giay_to', label: 'Giấy tờ' },
  { key: 'trang_thai', label: 'Trạng Thái' },
  { key: 'actions', label: 'Hành Động' },
]

const getStatus = (status) => {
  if (status === 'hoat_dong') return { text: 'Hoạt động', class: 'status-approved' }
  if (status === 'khoa') return { text: 'Bị khóa', class: 'status-locked' }
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
    const response = await adminApi.getDrivers({
      page,
      per_page: pagination.perPage,
      search: searchQuery.value || undefined,
      tinh_trang: filterStatus.value || undefined,
      ma_nha_xe: filterNhaXe.value || undefined,
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

const fetchOperators = async () => {
    try {
        const response = await adminApi.getOperators({ per_page: 100 })
        const { listData } = extractListAndPage(response)
        operators.value = listData
    } catch(error) {
        console.error('Không thể lấy sách sách nhà xe', error)
    }
}

const handleSearch = () => {
  fetchDrivers(1)
}

const resetFilter = () => {
  searchQuery.value = ''
  filterStatus.value = ''
  filterNhaXe.value = ''
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
  { value: 'FB2', label: 'Hạng FB2' },
  { value: 'FC', label: 'Hạng FC' },
  { value: 'FD', label: 'Hạng FD' },
  { value: 'FE', label: 'Hạng FE' },
]

const initialFormData = () => ({
  ho_va_ten: '',
  email: '',
  password: '',
  cccd: '',
  so_dien_thoai: '',
  ma_nha_xe: '',
  tinh_trang: 'cho_duyet',
  ngay_sinh: '',
  dia_chi: '',
  so_gplx: '',
  hang_bang_lai: '',
  ngay_cap_gplx: '',
  ngay_het_han_gplx: '',
  avatar: null,
  anh_cccd_mat_truoc: null,
  anh_cccd_mat_sau: null,
})

const formData = reactive(initialFormData())
const filePreviews = reactive({
  avatar: null,
  anh_cccd_mat_truoc: null,
  anh_cccd_mat_sau: null,
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
  for(let key in filePreviews) filePreviews[key] = null
  isFormModal.value = true
}

const openEditModal = (driver) => {
  isEditMode.value = true
  currentDriverId.value = driver.id
  Object.assign(formData, initialFormData())
  
  // Load basic auth data
  formData.ho_va_ten = driver.ho_va_ten || ''
  formData.email = driver.email
  formData.cccd = driver.cccd
  formData.ma_nha_xe = driver.ma_nha_xe
  formData.tinh_trang = driver.tinh_trang || 'cho_duyet'

  // Load profile data (Priority to hoSo if available)
  if (driver.hoSo) {
    formData.ho_va_ten = driver.hoSo.ho_va_ten || formData.ho_va_ten
    formData.so_dien_thoai = driver.hoSo.so_dien_thoai || ''
    formData.ngay_sinh = driver.hoSo.ngay_sinh || ''
    formData.dia_chi = driver.hoSo.dia_chi || ''
    formData.so_gplx = driver.hoSo.so_gplx || ''
    formData.hang_bang_lai = driver.hoSo.hang_bang_lai || ''
    formData.ngay_cap_gplx = driver.hoSo.ngay_cap_gplx || ''
    formData.ngay_het_han_gplx = driver.hoSo.ngay_het_han_gplx || ''
  } else if (driver.so_dien_thoai) {
    formData.so_dien_thoai = driver.so_dien_thoai
  }
  
  // Load image previews
  filePreviews.avatar = driver.avatar || driver.hoSo?.avatar || null
  filePreviews.anh_cccd_mat_truoc = driver.anh_cccd_mat_truoc || driver.hoSo?.anh_cccd_mat_truoc || null
  filePreviews.anh_cccd_mat_sau = driver.anh_cccd_mat_sau || driver.hoSo?.anh_cccd_mat_sau || null
  
  isFormModal.value = true
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

const submitForm = async () => {
  try {
    formLoading.value = true
    const payload = new FormData()

    payload.append('ho_va_ten', formData.ho_va_ten)
    payload.append('email', formData.email)
    payload.append('cccd', formData.cccd)
    payload.append('so_dien_thoai', formData.so_dien_thoai)
    payload.append('ma_nha_xe', formData.ma_nha_xe)
    payload.append('tinh_trang', formData.tinh_trang)

    // Add profile fields
    payload.append('ngay_sinh', formData.ngay_sinh || '')
    payload.append('dia_chi', formData.dia_chi || '')
    payload.append('so_gplx', formData.so_gplx || '')
    payload.append('hang_bang_lai', formData.hang_bang_lai || '')
    payload.append('ngay_cap_gplx', formData.ngay_cap_gplx || '')
    payload.append('ngay_het_han_gplx', formData.ngay_het_han_gplx || '')

    if (formData.password) {
      payload.append('password', formData.password)
    }

    const fileFields = ['avatar', 'anh_cccd_mat_truoc', 'anh_cccd_mat_sau']
    fileFields.forEach(field => {
      if (formData[field] instanceof File) {
        payload.append(field, formData[field])
      }
    })

    if (isEditMode.value) {
      await adminApi.updateDriver(currentDriverId.value, payload)
      showToast('Cập nhật tài xế thành công!', 'success')
    } else {
      await adminApi.createDriver(payload)
      showToast('Thêm tài xế mới thành công!', 'success')
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

const toggleStatus = (id) => {
  openConfirm(
    'Xác nhận thay đổi',
    'Bạn có chắc muốn chuyển đổi trạng thái tài khoản này không?',
    async () => {
      try {
        await adminApi.toggleDriverStatus(id)
        showToast('Cập nhật trạng thái thành công!', 'success')
        fetchDrivers(pagination.currentPage)
      } catch (error) {
        showToast(error.response?.data?.message || 'Lỗi hệ thống.', 'error')
      }
    }
  )
}

const approveDriver = (id) => {
  openConfirm(
    'Duyệt tài xế',
    'Duyệt tài xế này và cho phép hoạt động?',
    async () => {
      try {
        await adminApi.approveDriver(id)
        showToast('Đã duyệt tài xế thành công! Tài xế có thể đăng nhập.', 'success')
        fetchDrivers(pagination.currentPage)
      } catch (error) {
        showToast(error.response?.data?.message || 'Lỗi khi duyệt tài xế.', 'error')
      }
    },
    'primary'
  )
}

const requestDelete = (id) => {
  openConfirm(
    'Xác nhận xoá',
    'LƯU Ý: Đây là hành động xoá VĨNH VIỄN khỏi cơ sở dữ liệu. Bạn có chắc chắn?',
    async () => {
      try {
        await adminApi.deleteDriver(id)
        showToast('Đã xoá thành công!', 'success')
        fetchDrivers(pagination.currentPage)
      } catch (error) {
        showToast(error.response?.data?.message || 'Lỗi khi yêu cầu xoá.', 'error')
      }
    },
    'danger'
  )
}

onMounted(() => {
  fetchOperators()
  fetchDrivers()
})
</script>

<template>
  <div class="admin-page">
    <BaseToast :visible="toast.visible" :message="toast.message" :type="toast.type" />

    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Tài xế</h1>
        <p class="page-sub">Quản trị danh sách tài xế toàn hệ thống (Admin Master)</p>
      </div>
      <div class="header-stats" v-if="drivers.length > 0">
        <div class="stat-chip stat-total">
          <span class="stat-number">{{ pagination.total }}</span>
          <span class="stat-label">Tổng</span>
        </div>
        <div class="stat-chip stat-pending">
          <span class="stat-number">{{ drivers.filter(d => d.tinh_trang === 'cho_duyet').length }}</span>
          <span class="stat-label">Chờ duyệt</span>
        </div>
        <div class="stat-chip stat-active">
          <span class="stat-number">{{ drivers.filter(d => d.tinh_trang === 'hoat_dong').length }}</span>
          <span class="stat-label">Hoạt động</span>
        </div>
      </div>
      <BaseButton variant="primary" @click="openCreateModal">+ Thêm Tài Xế</BaseButton>
    </div>

    <!-- Filter Section -->
    <div class="filter-card">
      <div class="filter-row">
        <div class="search-box flex-2">
          <label class="filter-label">Tìm kiếm</label>
          <BaseInput v-model="searchQuery" placeholder="Tìm theo tên, email, cccd..." @keyup.enter="fetchDrivers(1)" />
        </div>
        
        <div class="filter-group flex-1">
          <label class="filter-label">Trạng thái</label>
          <select v-model="filterStatus" class="custom-select" @change="fetchDrivers(1)">
            <option value="">Tất cả trạng thái</option>
            <option value="cho_duyet">Chờ duyệt</option>
            <option value="hoat_dong">Hoạt động</option>
            <option value="khoa">Bị khóa</option>
          </select>
        </div>

        <div class="filter-group flex-1">
          <label class="filter-label">Nhà xe</label>
          <select v-model="filterNhaXe" class="custom-select" @change="fetchDrivers(1)">
            <option value="">Tất cả nhà xe</option>
            <option v-for="nx in operators" :key="nx.id" :value="nx.ma_nha_xe">{{ nx.ma_nha_xe }} - {{ nx.ten_nha_xe }}</option>
          </select>
        </div>

        <div class="filter-actions">
          <BaseButton variant="primary" @click="fetchDrivers(1)">Lọc</BaseButton>
          <BaseButton variant="outline" @click="resetFilter">Đặt lại</BaseButton>
        </div>
      </div>
    </div>

    <div class="table-card">
      <BaseTable :columns="tableColumns" :data="drivers" :loading="loading">
        <template #cell(avatar)="{ item }">
          <div class="avatar-cell">
             <img v-if="item.avatar" :src="item.avatar" alt="Avatar" class="driver-avatar"/>
             <div v-else class="avatar-placeholder">
                <span class="placeholder-icon">👤</span>
             </div>
          </div>
        </template>

        <template #cell(thong_tin)="{ item }">
          <div class="info-block">
            <div class="driver-name">{{ item.hoSo?.ho_va_ten || item.ho_va_ten || 'Chưa cập nhật tên' }}</div>
            <div class="driver-email">{{ item.email }}</div>
          </div>
        </template>

        <template #cell(nha_xe)="{ item }">
           <span class="nha-xe-badge">{{ item.ma_nha_xe }}</span>
           <div class="nha-xe-name" v-if="item.nhaXe?.ten_nha_xe">{{ item.nhaXe.ten_nha_xe }}</div>
        </template>

        <template #cell(lien_he)="{ item }">
           <div class="contact-info">
              <div class="contact-item">
                <span class="icon">📞</span> {{ item.hoSo?.so_dien_thoai || '—' }}
              </div>
           </div>
        </template>

        <template #cell(giay_to)="{ item }">
           <div class="giay-to-block">
            <div class="cccd-row">
                <span class="label">CCCD:</span>
                <span v-if="isSuperAdmin" class="value">{{ item.cccd }}</span>
                <span v-else class="value-masked">********</span>
            </div>
            <div class="gplx-row">
                <span class="label">GPLX:</span>
                <strong class="gplx-code">{{ item.hoSo?.so_gplx || '—' }}</strong>
                <span class="gplx-class" v-if="item.hoSo?.hang_bang_lai">({{ item.hoSo.hang_bang_lai }})</span>
            </div>
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

            <!-- Nút Duyệt: chỉ hiện khi tài xế đang CHờ DUYỆT -->
            <BaseButton
              v-if="item.tinh_trang === 'cho_duyet'"
              size="sm"
              style="color: #ffffff; border-color: #16a34a; background: #16a34a; font-weight: 700;"
              variant="outline"
              @click="approveDriver(item.id)"
            >✓ Duyệt</BaseButton>

            <!-- Nút Khoá / Mở khoá: chỉ hiện khi KHÔNG phải chờ duyệt -->
            <BaseButton
              v-if="item.tinh_trang !== 'cho_duyet'"
              size="sm"
              :style="item.tinh_trang === 'hoat_dong'
                ? 'color: #b45309; border-color: #d97706; background: #fffbeb;'
                : 'color: #166534; border-color: #16a34a; background: #f0fdf4;'"
              variant="outline"
              @click="toggleStatus(item.id)"
            >
              {{ item.tinh_trang === 'hoat_dong' ? '🔒 Khoá' : '🔓 Mở khoá' }}
            </BaseButton>

            <BaseButton size="sm" style="color: red; border-color: red" variant="outline" @click="requestDelete(item.id)">Xoá</BaseButton>
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
          <BaseButton
            size="sm"
            variant="outline"
            :disabled="pagination.currentPage <= 1"
            @click="fetchDrivers(pagination.currentPage - 1)"
          >← Trước</BaseButton>

          <span class="page-number">Trang {{ pagination.currentPage }} / {{ pagination.lastPage }}</span>

          <BaseButton
            size="sm"
            variant="outline"
            :disabled="pagination.currentPage >= pagination.lastPage"
            @click="fetchDrivers(pagination.currentPage + 1)"
          >Sau →</BaseButton>
        </div>
      </div>
    </div>

    <BaseModal
      v-model="isFormModal"
      :title="isEditMode ? 'Cập Nhật Tài Xế (Admin)' : 'Thêm Tài Xế Mới'"
      maxWidth="800px"
    >
      <!-- Loading Overlay cho quá trình Upload ảnh (Đưa vào trong Modal) -->
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
        <h3 class="section-title">Thông tin xác thực & Quản lý</h3>
        <div class="form-grid">
          <BaseInput v-model="formData.ho_va_ten" label="Họ và tên *" placeholder="Nguyễn Văn A" required />
          <BaseInput v-model="formData.email" type="email" label="Email đăng nhập *" placeholder="nhanvien@email.com" required />
          <BaseInput v-model="formData.cccd" label="Số CCCD *" placeholder="012345678912" required />
          <BaseInput v-model="formData.so_dien_thoai" label="Số điện thoại *" placeholder="0909123456" required />
          <BaseInput v-model="formData.password" type="password" :label="isEditMode ? 'Mật khẩu mới (Tùy chọn)' : 'Mật khẩu *'" :required="!isEditMode" />
          <BaseInput v-model="formData.ngay_sinh" type="date" label="Ngày sinh" />
          <BaseInput v-model="formData.dia_chi" label="Địa chỉ" placeholder="Số 1, Đường X, Quận Y..." />
        </div>

        <h3 class="section-title mt-4">Thông tin giấy phép lái xe</h3>
        <div class="form-grid">
          <BaseInput v-model="formData.so_gplx" label="Số GPLX *" placeholder="123456789012" required />
          <BaseSelect 
            v-model="formData.hang_bang_lai" 
            label="Hạng bằng lái *" 
            :options="licenseClasses"
            required 
          />
          <BaseInput v-model="formData.ngay_cap_gplx" type="date" label="Ngày cấp GPLX *" required />
          <BaseInput v-model="formData.ngay_het_han_gplx" type="date" label="Ngày hết hạn GPLX *" required />
        </div>

        <div class="form-grid mt-4">
          <div class="form-group">
            <label class="base-input-label">Mã nhà xe *</label>
            <select v-model="formData.ma_nha_xe" class="custom-select" required>
                <option value="" disabled>-- Chọn Nhà Xe --</option>
                <option v-for="nx in operators" :key="nx.id" :value="nx.ma_nha_xe">
                    {{ nx.ma_nha_xe }} - {{ nx.ten_nha_xe }}
                </option>
            </select>
          </div>

          <div class="form-group">
            <label class="base-input-label">Tình trạng (Dành cho Admin)</label>
            <select v-model="formData.tinh_trang" class="custom-select" required>
                <option value="hoat_dong">Cho phép hoạt động luôn</option>
                <option value="khoa">Khoá không cho đăng nhập</option>
                <option value="cho_duyet">Chờ duyệt hồ sơ</option>
            </select>
          </div>
        </div>

        <h3 class="section-title mt-4">Hình ảnh hồ sơ</h3>
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
            <label class="base-input-label">CCCD Trước <span v-if="!isEditMode">*</span></label>
            <div class="file-upload-box" @click="$refs.cccd1Input.click()">
              <img v-if="filePreviews.anh_cccd_mat_truoc" :src="filePreviews.anh_cccd_mat_truoc" alt="Preview" class="file-preview" />
              <div v-else class="upload-placeholder">
                 <span>Tải ảnh lên</span>
              </div>
            </div>
            <input type="file" ref="cccd1Input" hidden accept="image/*" @change="handleFileUpload($event, 'anh_cccd_mat_truoc')" />
          </div>

          <div class="file-group">
            <label class="base-input-label">CCCD Sau <span v-if="!isEditMode">*</span></label>
            <div class="file-upload-box" @click="$refs.cccd2Input.click()">
              <img v-if="filePreviews.anh_cccd_mat_sau" :src="filePreviews.anh_cccd_mat_sau" alt="Preview" class="file-preview" />
              <div v-else class="upload-placeholder">
                 <span>Tải ảnh lên</span>
              </div>
            </div>
            <input type="file" ref="cccd2Input" hidden accept="image/*" @change="handleFileUpload($event, 'anh_cccd_mat_sau')" />
          </div>
        </div>
      </form>

      <template #footer>
        <BaseButton variant="secondary" @click="isFormModal = false">Hủy</BaseButton>
        <BaseButton variant="primary" :loading="formLoading" @click="submitForm">
          {{ isEditMode ? 'Cập nhật tài xế' : 'Thêm tài xế' }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- Custom Confirmation Modal -->
    <BaseModal
      v-model="confirmModal.visible"
      :title="confirmModal.title"
      maxWidth="450px"
    >
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
.admin-page {
  padding: 1.5rem;
  font-family: 'Inter', system-ui, sans-serif;
  background-color: #f8fafc;
  min-height: 100vh;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.35rem 0;
}

.page-sub {
  color: #64748b;
  font-size: 0.925rem;
  margin: 0;
}

.header-stats {
  display: flex;
  gap: 0.75rem;
}

.stat-chip {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.5rem 1rem;
  border-radius: 12px;
  min-width: 80px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.stat-number {
  font-size: 1.25rem;
  font-weight: 700;
}

.stat-label {
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.stat-total { background: #f1f5f9; color: #475569; }
.stat-pending { background: #fef3c7; color: #92400e; }
.stat-active { background: #dcfce7; color: #166534; }

.filter-card {
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(10px);
  border: 1px solid #e2e8f0;
  padding: 1rem;
  border-radius: 14px;
  margin-bottom: 1.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.filter-row {
  display: flex;
  gap: 1rem;
  align-items: flex-end;
  flex-wrap: nowrap; /* Ép lên cùng 1 hàng */
}

.search-box { min-width: 200px; }
.flex-1 { flex: 1; }
.flex-2 { flex: 2; }

.filter-group { 
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.filter-actions {
  display: flex;
  gap: 0.5rem;
  padding-bottom: 2px; /* Căn chỉnh nhẹ để khớp với chiều cao input */
}

.filter-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: #475569;
}

.btn-group {
  display: flex;
  gap: 0.5rem;
}

.table-card {
  background: white;
  border-radius: 14px;
  padding: 1rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  border: 1px solid #e2e8f0;
}

.avatar-cell {
  width: 44px;
  height: 44px;
}

.driver-avatar {
  width: 100%;
  height: 100%;
  border-radius: 10px;
  object-fit: cover;
  border: 1px solid #e2e8f0;
}

.avatar-placeholder {
  width: 100%;
  height: 100%;
  background: #f1f5f9;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  border: 1px dashed #cbd5e1;
}

.driver-name {
  font-weight: 600;
  color: #1e293b;
  font-size: 0.95rem;
}

.driver-email {
  font-size: 0.825rem;
  color: #64748b;
}

.nha-xe-badge {
  background: #eef2ff;
  color: #4338ca;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.75rem;
  display: inline-block;
}

.nha-xe-name {
  font-size: 0.8rem;
  color: #64748b;
  margin-top: 4px;
}

.contact-info {
  font-size: 0.9rem;
  color: #475569;
}

.giay-to-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.cccd-row, .gplx-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
}

.label { color: #64748b; font-weight: 500; }
.value { color: #1e293b; font-weight: 600; }
.value-masked { color: #94a3b8; letter-spacing: 2px; }

.gplx-code {
  color: #0d4f35;
  font-weight: 700;
  font-family: 'Fira Code', monospace;
}

.gplx-class {
  color: #059669;
  font-weight: 600;
}

.status-badge {
  padding: 0.35rem 0.85rem;
  border-radius: 99px;
  font-size: 0.75rem;
  font-weight: 700;
}

.status-approved { background: #dcfce7; color: #166534; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-locked { background: #fee2e2; color: #991b1b; }

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
.form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
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
  border-color: #3b82f6;
  background-color: #eff6ff;
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
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
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

/* Custom Confirmation Modal Styles */
.confirm-modal-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 1rem 0;
}
.confirm-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  margin-bottom: 1rem;
}
.confirm-icon.primary { background: #eff6ff; color: #3b82f6; }
.confirm-icon.danger { background: #fef2f2; color: #ef4444; }
.confirm-message {
  font-size: 1rem;
  color: #1e293b;
  font-weight: 500;
  line-height: 1.5;
}

/* Upload Overlay Styles */
.upload-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.75);
  backdrop-filter: blur(4px);
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: inherit;
}
.upload-spinner-box {
  background: white;
  padding: 2rem 2.5rem;
  border-radius: 20px;
  color: #0f172a;
  box-shadow: 0 20px 50px rgba(0,0,0,0.15);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  text-align: center;
}
.upload-spinner-box p {
  margin: 0;
  font-weight: 700;
  font-size: 1.1rem;
}
.sub-tip {
  font-size: 0.85rem;
  color: #64748b;
}
.spinner-main {
  animation: rotate 2s linear infinite;
  width: 60px;
  height: 60px;
}
.spinner-main .path {
  stroke: #3b82f6;
  stroke-linecap: round;
  animation: dash 1.5s ease-in-out infinite;
}

@keyframes rotate {
  100% { transform: rotate(360deg); }
}
@keyframes dash {
  0% { stroke-dasharray: 1, 150; stroke-dashoffset: 0; }
  50% { stroke-dasharray: 90, 150; stroke-dashoffset: -35; }
  100% { stroke-dasharray: 90, 150; stroke-dashoffset: -124; }
}
</style>
