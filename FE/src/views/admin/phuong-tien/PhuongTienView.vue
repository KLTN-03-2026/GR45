<script setup>
import { ref, reactive, onMounted } from 'vue'
import adminApi from '@/api/adminApi'
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
const vehicles = ref([])
const searchQuery = ref('')
const filterStatus = ref('')
const pagination = reactive({ currentPage: 1, perPage: 15, total: 0, lastPage: 1 })

const tableColumns = [
  { key: 'id', label: 'ID' },
  { key: 'bien_so', label: 'Biển Số' },
  { key: 'ten_xe', label: 'Tên Xe' },
  { key: 'nha_xe', label: 'Nhà Xe' },
  { key: 'loai_xe', label: 'Loại Xe' },
  { key: 'so_ghe_thuc_te', label: 'Số Ghế' },
  { key: 'trang_thai', label: 'Trạng Thái' },
  { key: 'actions', label: 'Hành Động' },
]

const getVehicleStatus = (status) => {
  if (status === 'hoat_dong') return { text: 'Hoạt động', class: 'status-approved' }
  if (status === 'bao_tri') return { text: 'Bảo trì', class: 'status-info' }
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

const fetchVehicles = async (page = 1) => {
  try {
    loading.value = true
    const response = await adminApi.getVehicles({
      page,
      per_page: pagination.perPage,
      search: searchQuery.value || undefined,
      trang_thai: filterStatus.value || undefined,
    })

    const { listData, pageData } = extractListAndPage(response)

    vehicles.value = listData
    pagination.currentPage = pageData.current_page || page
    pagination.perPage = pageData.per_page || pagination.perPage
    pagination.total = pageData.total || listData.length
    pagination.lastPage = pageData.last_page || 1
  } catch (error) {
    console.error('Lỗi tải danh sách xe:', error)
    showToast('Không thể tải danh sách xe!', 'error')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  fetchVehicles(1)
}

const resetFilter = () => {
  searchQuery.value = ''
  filterStatus.value = ''
  fetchVehicles(1)
}

const isFormModal = ref(false)
const isEditMode = ref(false)
const currentVehicleId = ref(null)
const formLoading = ref(false)

const initialFormData = () => ({
  bien_so: '',
  ten_xe: '',
  ma_nha_xe: '',
  id_loai_xe: '',
  id_tai_xe_chinh: '',
  bien_nhan_dang: '',
  so_ghe_thuc_te: '',
})

const formData = reactive(initialFormData())

const openCreateModal = () => {
  isEditMode.value = false
  currentVehicleId.value = null
  Object.assign(formData, initialFormData())
  isFormModal.value = true
}

const openEditModal = (vehicle) => {
  isEditMode.value = true
  currentVehicleId.value = vehicle.id
  Object.assign(formData, {
    bien_so: vehicle.bien_so || '',
    ten_xe: vehicle.ten_xe || '',
    ma_nha_xe: vehicle.ma_nha_xe || vehicle.nha_xe?.ma_nha_xe || '',
    id_loai_xe: vehicle.id_loai_xe || vehicle.loai_xe?.id || '',
    id_tai_xe_chinh: vehicle.id_tai_xe_chinh || '',
    bien_nhan_dang: vehicle.bien_nhan_dang || '',
    so_ghe_thuc_te: vehicle.so_ghe_thuc_te || '',
  })
  isFormModal.value = true
}

const buildPayload = () => {
  const payload = {
    bien_so: String(formData.bien_so || '').trim(),
    ten_xe: String(formData.ten_xe || '').trim(),
    ma_nha_xe: String(formData.ma_nha_xe || '').trim(),
    id_loai_xe: Number(formData.id_loai_xe),
    so_ghe_thuc_te: Number(formData.so_ghe_thuc_te),
  }

  if (formData.id_tai_xe_chinh !== '' && formData.id_tai_xe_chinh !== null) {
    payload.id_tai_xe_chinh = Number(formData.id_tai_xe_chinh)
  }

  if (String(formData.bien_nhan_dang || '').trim()) {
    payload.bien_nhan_dang = String(formData.bien_nhan_dang).trim()
  }

  return payload
}

const submitForm = async () => {
  try {
    formLoading.value = true
    const payload = buildPayload()

    if (isEditMode.value) {
      await adminApi.updateVehicle(currentVehicleId.value, payload)
      showToast('Cập nhật xe thành công!', 'success')
    } else {
      await adminApi.createVehicle(payload)
      showToast('Thêm xe mới thành công!', 'success')
    }

    isFormModal.value = false
    fetchVehicles(isEditMode.value ? pagination.currentPage : 1)
  } catch (error) {
    console.error('Lỗi lưu xe:', error)
    const message = error.response?.data?.errors
      ? Object.values(error.response.data.errors).flat()[0]
      : (error.response?.data?.message || 'Lưu xe thất bại!')
    showToast(message, 'error')
  } finally {
    formLoading.value = false
  }
}

const statusModal = reactive({
  show: false,
  id: null,
  bienSo: '',
  trangThai: 'cho_duyet',
  loading: false,
})

const openStatusModal = (vehicle) => {
  statusModal.id = vehicle.id
  statusModal.bienSo = vehicle.bien_so
  statusModal.trangThai = vehicle.trang_thai || 'cho_duyet'
  statusModal.show = true
}

const submitStatus = async () => {
  try {
    statusModal.loading = true
    await adminApi.updateVehicleStatus(statusModal.id, { trang_thai: statusModal.trangThai })
    showToast('Cập nhật trạng thái xe thành công!', 'success')
    statusModal.show = false
    fetchVehicles(pagination.currentPage)
  } catch (error) {
    console.error('Lỗi cập nhật trạng thái xe:', error)
    showToast(error.response?.data?.message || 'Không thể cập nhật trạng thái xe!', 'error')
  } finally {
    statusModal.loading = false
  }
}

const deleteModal = reactive({ show: false, id: null, bienSo: '', loading: false })

const openDeleteModal = (vehicle) => {
  deleteModal.show = true
  deleteModal.id = vehicle.id
  deleteModal.bienSo = vehicle.bien_so
}

const confirmDelete = async () => {
  try {
    deleteModal.loading = true
    await adminApi.deleteVehicle(deleteModal.id)
    showToast('Xóa xe thành công!', 'success')
    deleteModal.show = false
    fetchVehicles(1)
  } catch (error) {
    console.error('Lỗi xóa xe:', error)
    showToast(error.response?.data?.message || 'Không thể xóa xe!', 'error')
  } finally {
    deleteModal.loading = false
  }
}

const detailModal = reactive({ show: false, loading: false, data: null })

const openDetailModal = async (vehicle) => {
  detailModal.show = true
  detailModal.loading = true
  detailModal.data = null

  try {
    const response = await adminApi.getVehicleDetails(vehicle.id)
    detailModal.data = response?.data || response
  } catch (error) {
    console.error('Lỗi tải chi tiết xe:', error)
    showToast('Không thể tải chi tiết xe!', 'error')
    detailModal.show = false
  } finally {
    detailModal.loading = false
  }
}

onMounted(() => {
  fetchVehicles()
})
</script>

<template>
  <div class="admin-page">
    <BaseToast :visible="toast.visible" :message="toast.message" :type="toast.type" />

    <div class="page-header">
      <div>
        <h1 class="page-title">Quản Lý Phương Tiện</h1>
        <p class="page-sub">Quản trị toàn bộ xe trên hệ thống, duyệt trạng thái và kiểm soát chất lượng vận hành.</p>
      </div>
      <BaseButton variant="primary" @click="openCreateModal">+ Thêm Xe Mới</BaseButton>
    </div>

    <div class="filter-card">
      <div class="filter-row">
        <div class="search-box">
          <BaseInput
            v-model="searchQuery"
            placeholder="Tìm biển số, tên xe..."
            @keyup.enter="handleSearch"
          />
          <BaseButton variant="secondary" @click="handleSearch">Tìm</BaseButton>
        </div>

        <div class="filter-group">
          <label class="filter-label">Trạng thái</label>
          <select v-model="filterStatus" class="custom-select" @change="handleSearch">
            <option value="">Tất cả</option>
            <option value="hoat_dong">Hoạt động</option>
            <option value="bao_tri">Bảo trì</option>
            <option value="cho_duyet">Chờ duyệt</option>
          </select>
        </div>

        <BaseButton variant="outline" @click="resetFilter">Đặt lại</BaseButton>
      </div>
    </div>

    <div class="table-card">
      <BaseTable :columns="tableColumns" :data="vehicles" :loading="loading">
        <template #cell(bien_so)="{ value }">
          <span class="code-chip">{{ value }}</span>
        </template>

        <template #cell(ten_xe)="{ item }">
          <div class="name-block">
            <strong>{{ item.ten_xe }}</strong>
            <span v-if="item.bien_nhan_dang" class="name-sub">{{ item.bien_nhan_dang }}</span>
          </div>
        </template>

        <template #cell(nha_xe)="{ item }">
          <span>{{ item.nha_xe?.ten_nha_xe || item.ma_nha_xe || '—' }}</span>
        </template>

        <template #cell(loai_xe)="{ item }">
          <span>{{ item.loai_xe?.ten_loai_xe || `ID: ${item.id_loai_xe || '—'}` }}</span>
        </template>

        <template #cell(so_ghe_thuc_te)="{ value }">
          <span class="seat-badge">{{ value || 0 }} ghế</span>
        </template>

        <template #cell(trang_thai)="{ value }">
          <span :class="['status-badge', getVehicleStatus(value).class]">
            {{ getVehicleStatus(value).text }}
          </span>
        </template>

        <template #cell(actions)="{ item }">
          <div class="action-buttons">
            <BaseButton size="sm" variant="outline" @click="openDetailModal(item)">Chi tiết</BaseButton>
            <BaseButton size="sm" variant="primary" @click="openEditModal(item)">Sửa</BaseButton>
            <BaseButton size="sm" variant="secondary" @click="openStatusModal(item)">Duyệt/TT</BaseButton>
            <BaseButton size="sm" variant="danger" @click="openDeleteModal(item)">Xóa</BaseButton>
          </div>
        </template>
      </BaseTable>

      <div class="pagination-container">
        <div class="page-info-left">
          <span>Hiển thị:</span>
          <select v-model="pagination.perPage" @change="fetchVehicles(1)" class="custom-select per-page-select">
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
          </select>
          <span>dòng / trang</span>
          <span v-if="pagination.total > 0" class="total-label">(Tổng: {{ pagination.total }} xe)</span>
        </div>

        <div class="pagination-controls">
          <BaseButton
            size="sm"
            variant="outline"
            :disabled="pagination.currentPage <= 1"
            @click="fetchVehicles(pagination.currentPage - 1)"
          >← Trước</BaseButton>

          <span class="page-number">Trang {{ pagination.currentPage }} / {{ pagination.lastPage }}</span>

          <BaseButton
            size="sm"
            variant="outline"
            :disabled="pagination.currentPage >= pagination.lastPage"
            @click="fetchVehicles(pagination.currentPage + 1)"
          >Sau →</BaseButton>
        </div>
      </div>
    </div>

    <BaseModal
      v-model="isFormModal"
      :title="isEditMode ? 'Cập Nhật Xe' : 'Thêm Xe Mới'"
      maxWidth="760px"
    >
      <form @submit.prevent="submitForm" class="vehicle-form">
        <div class="form-grid">
          <BaseInput v-model="formData.bien_so" label="Biển số *" placeholder="VD: 51G-12345" required />
          <BaseInput v-model="formData.ten_xe" label="Tên xe *" placeholder="VD: Xe giường nằm VIP" required />

          <BaseInput v-model="formData.ma_nha_xe" label="Mã nhà xe *" placeholder="VD: NX001" required />

          <div class="form-group">
            <label class="base-input-label">ID Loại xe *</label>
            <input v-model="formData.id_loai_xe" class="custom-input" type="number" min="1" required />
          </div>

          <div class="form-group">
            <label class="base-input-label">ID Tài xế chính</label>
            <input v-model="formData.id_tai_xe_chinh" class="custom-input" type="number" min="1" placeholder="Tùy chọn" />
          </div>

          <div class="form-group">
            <label class="base-input-label">Số ghế thực tế *</label>
            <input v-model="formData.so_ghe_thuc_te" class="custom-input" type="number" min="1" required />
          </div>

          <div class="form-group full-width">
            <label class="base-input-label">Biển nhận dạng</label>
            <textarea
              v-model="formData.bien_nhan_dang"
              class="custom-input custom-textarea"
              placeholder="VD: Màu xanh, logo lớn phía hông xe"
            ></textarea>
          </div>
        </div>
      </form>

      <template #footer>
        <BaseButton variant="secondary" @click="isFormModal = false">Hủy</BaseButton>
        <BaseButton variant="primary" :loading="formLoading" @click="submitForm">
          {{ isEditMode ? 'Lưu thay đổi' : 'Thêm xe' }}
        </BaseButton>
      </template>
    </BaseModal>

    <BaseModal v-model="statusModal.show" title="Cập nhật trạng thái xe" maxWidth="520px">
      <div class="status-body">
        <p>Biển số: <strong>{{ statusModal.bienSo }}</strong></p>
        <div class="form-group">
          <label class="base-input-label">Trạng thái mới</label>
          <select v-model="statusModal.trangThai" class="custom-select">
            <option value="cho_duyet">Chờ duyệt</option>
            <option value="hoat_dong">Hoạt động</option>
            <option value="bao_tri">Bảo trì</option>
          </select>
        </div>
      </div>

      <template #footer>
        <BaseButton variant="secondary" @click="statusModal.show = false">Hủy</BaseButton>
        <BaseButton variant="primary" :loading="statusModal.loading" @click="submitStatus">Lưu trạng thái</BaseButton>
      </template>
    </BaseModal>

    <BaseModal v-model="deleteModal.show" title="Xác nhận xóa xe" maxWidth="520px">
      <div class="delete-body">
        <p>Bạn có chắc muốn xóa xe <strong>{{ deleteModal.bienSo }}</strong>?</p>
        <p class="warn-text">Hành động này không thể hoàn tác.</p>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="deleteModal.show = false">Hủy</BaseButton>
        <BaseButton variant="danger" :loading="deleteModal.loading" @click="confirmDelete">Xóa xe</BaseButton>
      </template>
    </BaseModal>

    <BaseModal v-model="detailModal.show" title="Chi tiết xe" maxWidth="650px">
      <div v-if="detailModal.loading" class="detail-loading">Đang tải...</div>
      <div v-else-if="detailModal.data" class="detail-grid">
        <div class="detail-item"><span class="detail-label">Biển số</span><span class="detail-value">{{ detailModal.data.bien_so }}</span></div>
        <div class="detail-item"><span class="detail-label">Tên xe</span><span class="detail-value">{{ detailModal.data.ten_xe }}</span></div>
        <div class="detail-item"><span class="detail-label">Mã nhà xe</span><span class="detail-value">{{ detailModal.data.ma_nha_xe || '—' }}</span></div>
        <div class="detail-item"><span class="detail-label">ID Loại xe</span><span class="detail-value">{{ detailModal.data.id_loai_xe || '—' }}</span></div>
        <div class="detail-item"><span class="detail-label">ID Tài xế chính</span><span class="detail-value">{{ detailModal.data.id_tai_xe_chinh || '—' }}</span></div>
        <div class="detail-item"><span class="detail-label">Số ghế</span><span class="detail-value">{{ detailModal.data.so_ghe_thuc_te || 0 }}</span></div>
        <div class="detail-item full-width"><span class="detail-label">Biển nhận dạng</span><span class="detail-value">{{ detailModal.data.bien_nhan_dang || '—' }}</span></div>
        <div class="detail-item full-width"><span class="detail-label">Trạng thái</span><span class="detail-value"><span :class="['status-badge', getVehicleStatus(detailModal.data.trang_thai).class]">{{ getVehicleStatus(detailModal.data.trang_thai).text }}</span></span></div>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="detailModal.show = false">Đóng</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.admin-page {
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
  color: #1e3a8a;
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
  border: 1px solid #dbeafe;
  box-shadow: 0 8px 20px rgba(30, 58, 138, 0.06);
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
.search-box > :first-child {
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
  font-weight: 500;
  color: #334155;
}

.table-card {
  background: #fff;
  border: 1px solid #dbeafe;
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 8px 24px rgba(30, 58, 138, 0.06);
}

.code-chip {
  display: inline-block;
  background: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
  padding: 3px 10px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
}
.name-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.name-sub {
  font-size: 12px;
  color: #64748b;
}
.seat-badge {
  font-weight: 700;
  color: #334155;
}

.status-badge {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  border-radius: 999px;
  padding: 4px 10px;
}
.status-approved { background: #dcfce7; color: #166534; }
.status-info { background: #dbeafe; color: #1d4ed8; }
.status-pending { background: #fef3c7; color: #b45309; }

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
.full-width {
  grid-column: 1 / -1;
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
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}
.custom-textarea {
  resize: vertical;
  min-height: 80px;
}

.status-body,
.delete-body {
  color: #334155;
}
.warn-text {
  color: #dc2626;
  margin-bottom: 0;
}

.detail-loading {
  text-align: center;
  padding: 28px;
  color: #64748b;
}
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.detail-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.detail-label {
  font-size: 12px;
  text-transform: uppercase;
  color: #64748b;
  letter-spacing: 0.5px;
  font-weight: 700;
}
.detail-value {
  font-size: 14px;
  color: #0f172a;
  font-weight: 600;
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
  .form-grid,
  .detail-grid {
    grid-template-columns: 1fr;
  }
  .pagination-container {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
