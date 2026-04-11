<script setup>
import { ref, onMounted } from 'vue'
import adminApi from '@/api/adminApi'
import {
  Building2, Plus, Edit2, Trash2, CheckCircle, XCircle,
  Eye, Save, RefreshCw, AlertTriangle, ShieldAlert, BadgePercent,
  Search, Info, CreditCard, MapPin, Phone, Mail, User
} from 'lucide-vue-next'

// --- State Danh sách & Phân trang ---
const nhaXes = ref([])
const loading = ref(false)
const searchKeyword = ref('')
const selectedStatus = ref('')
const meta = ref({ current_page: 1, last_page: 1, total: 0 })

// --- State Modal Thêm/Sửa ---
const showModal = ref(false)
const modalMode = ref('add') // 'add' | 'edit'
const submitLoading = ref(false)
const formError = ref(null)

const defaultForm = {
  id: null,
  ten_nha_xe: '',
  giay_phep_kinh_doanh: '',
  nguoi_dai_dien: '',
  so_dien_thoai: '',
  email: '',
  dia_chi: '',
  tai_khoan_ngan_hang: '',
  chiet_khau: 0
}
const formData = ref({ ...defaultForm })

// --- State Modal Xác nhận (Xoá / Duyệt / Khoá) ---
const showConfirm = ref(false)
const confirmAction = ref('') // 'delete' | 'toggleStatus'
const confirmTarget = ref(null)
const confirmLoading = ref(false)

// --- Lifecycle & Fetch ---
const fetchNhaXes = async (page = 1) => {
  loading.value = true
  try {
    const params = {
      page,
      search: searchKeyword.value || undefined,
      tinh_trang: selectedStatus.value || undefined,
      per_page: 10
    }
    const res = await adminApi.getOperators(params)
    const payload = res?.data ?? res
    nhaXes.value = payload?.data ?? []
    meta.value = {
      current_page: payload?.current_page ?? 1,
      last_page: payload?.last_page ?? 1,
      total: payload?.total ?? 0
    }
  } catch (error) {
    console.error('Lỗi lấy danh sách nhà xe:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchNhaXes()
})

// --- Hàm xử lý Modal Thêm/Sửa ---
const openAddModal = () => {
  modalMode.value = 'add'
  formData.value = { ...defaultForm }
  formError.value = null
  showModal.value = true
}

const openEditModal = (item) => {
  modalMode.value = 'edit'
  formData.value = {
    id: item.id,
    ten_nha_xe: item.ten_nha_xe || '',
    giay_phep_kinh_doanh: item.giay_phep_kinh_doanh || '',
    nguoi_dai_dien: item.nguoi_dai_dien || '',
    so_dien_thoai: item.so_dien_thoai || '',
    email: item.email || '',
    dia_chi: item.dia_chi || '',
    tai_khoan_ngan_hang: item.tai_khoan_ngan_hang || '',
    chiet_khau: item.chiet_khau || 0
  }
  formError.value = null
  showModal.value = true
}

const handleSave = async () => {
  submitLoading.value = true
  formError.value = null
  try {
    if (modalMode.value === 'add') {
      await adminApi.createOperator(formData.value)
    } else {
      await adminApi.updateOperator(formData.value.id, formData.value)
    }
    showModal.value = false
    fetchNhaXes(meta.value.current_page)
  } catch (error) {
    if (error.response?.data?.errors) {
      formError.value = Object.values(error.response.data.errors).flat().join('\n')
    } else {
      formError.value = error.response?.data?.message || 'Có lỗi xảy ra khi lưu.'
    }
  } finally {
    submitLoading.value = false
  }
}

// --- Hàm xử lý Modal Xác nhận (Xoá / Đổi trạng thái) ---
const openToggleStatus = (item) => {
  confirmAction.value = 'toggleStatus'
  confirmTarget.value = item
  showConfirm.value = true
}

const openDelete = (item) => {
  confirmAction.value = 'delete'
  confirmTarget.value = item
  showConfirm.value = true
}

const executeConfirm = async () => {
  if (!confirmTarget.value) return
  confirmLoading.value = true
  try {
    if (confirmAction.value === 'toggleStatus') {
      await adminApi.toggleOperatorStatus(confirmTarget.value.id)
    } else if (confirmAction.value === 'delete') {
      await adminApi.deleteOperator(confirmTarget.value.id)
    }
    showConfirm.value = false
    fetchNhaXes(meta.value.current_page)
  } catch (error) {
    console.error('Lỗi thao tác:', error)
    alert(error.response?.data?.message || 'Có lỗi xảy ra!')
  } finally {
    confirmLoading.value = false
  }
}

// --- Tiện ích hiển thị ---
const getStatusLabel = (status) => {
  if (status === 'hoat_dong' || status === 1) return { text: 'Hoạt động', cls: 'badge-green', icon: CheckCircle }
  if (status === 'cho_duyet') return { text: 'Chờ duyệt', cls: 'badge-yellow', icon: Eye }
  if (status === 'khoa' || status === 0) return { text: 'Đã khoá', cls: 'badge-red', icon: XCircle }
  return { text: status ?? 'N/A', cls: 'badge-gray', icon: Info }
}

const filteredStatusOptions = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'hoat_dong', label: 'Hoạt động' },
  { value: 'cho_duyet', label: 'Chờ duyệt' },
  { value: 'khoa', label: 'Đã khoá' }
]
</script>

<template>
  <div class="admin-page w-full">
    
    <!-- HEADER -->
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
      <div class="header-left d-flex align-items-center" style="gap: 16px;">
        <div class="header-icon-wrap">
          <Building2 class="header-icon" />
        </div>
        <div>
          <h1 class="page-title mb-0">Quản lý nhà xe</h1>
          <p class="text-muted mb-0" style="font-size: 14px;">Xem, duyệt và quản lý hồ sơ đối tác vận tải</p>
        </div>
      </div>
      <div class="header-actions" style="display: flex; gap: 0.75rem;">
        <button class="btn-refresh" :class="{ spinning: loading }" @click="fetchNhaXes()">
          <RefreshCw class="btn-icon" />
        </button>
        <button class="btn btn-primary" @click="openAddModal" style="display: flex; align-items: center; gap: 4px;">
          <Plus class="btn-icon" size="18" /> Thêm nhà xe
        </button>
      </div>
    </div>

    <!-- TÌM KIẾM & LỌC -->
    <div class="filter-card">
      <div class="filter-grid">
        <div class="filter-item">
          <span class="filter-label">Tìm kiếm nhanh</span>
          <div class="position-relative">
            <Search class="input-icon" />
            <input 
              type="text" 
              v-model="searchKeyword" 
              class="custom-input pl-10" 
              placeholder="VD: tên doanh nghiệp..."
              @keyup.enter="fetchNhaXes(1)"
            >
          </div>
        </div>

        <div class="filter-item">
          <span class="filter-label">Trạng thái</span>
          <select v-model="selectedStatus" class="custom-select" @change="fetchNhaXes(1)">
            <option v-for="opt in filteredStatusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <div class="filter-item filter-btn-wrapper self-end">
          <button class="btn btn-secondary w-100" @click="fetchNhaXes(1)">Tìm & Lọc</button>
        </div>
      </div>
    </div>

    <!-- BẢNG DỮ LIỆU -->
    <div class="table-card">
      <div class="table-responsive">
        <table class="data-table mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>DOANH NGHIỆP</th>
              <th>ĐẠI DIỆN</th>
              <th>CHIẾT KHẤU</th>
              <th>CẢNH BÁO AI</th>
              <th>TRẠNG THÁI</th>
              <th class="text-center">HÀNH ĐỘNG</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="text-center py-5 text-muted">
                <RefreshCw class="inline-block animate-spin mr-2" size="20" /> Đang tải dữ liệu...
              </td>
            </tr>
            <tr v-else-if="nhaXes.length === 0">
              <td colspan="7" class="text-center py-5 text-muted">
                Không tìm thấy thông tin nhà xe nào hợp lệ.
              </td>
            </tr>
            <tr v-else v-for="item in nhaXes" :key="item.id">
              <td class="font-weight-medium text-dark">#{{ item.id }}</td>
              <td>
                <div class="d-flex align-items-center" style="gap: 12px;">
                  <div class="avatar-bg">
                    <Building2 size="18" style="color: #4f46e5;" />
                  </div>
                  <div>
                    <div class="fw-bold text-dark">{{ item.ten_nha_xe }}</div>
                    <div class="text-xs text-muted truncate-text" :title="item.email">
                      {{ item.email }}
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <div class="text-sm fw-medium text-dark">{{ item.nguoi_dai_dien }}</div>
                <div class="text-xs text-muted">{{ item.so_dien_thoai }}</div>
              </td>
              <td>
                <div class="discount-badge">
                  <BadgePercent size="14" class="mr-1" />
                  {{ item.chiet_khau || 0 }}%
                </div>
              </td>
              <td>
                <div class="d-flex" style="gap: 8px;">
                  <div class="ai-stat stat-danger" title="Cảnh báo SOS">
                    <ShieldAlert size="14" />
                    <span>{{ item.tong_sos || 0 }}</span>
                  </div>
                  <div class="ai-stat stat-warning" title="Cảnh báo ngủ gật">
                    <AlertTriangle size="14" />
                    <span>{{ item.tong_ngu_gat || 0 }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span class="status-badge" :class="getStatusLabel(item.tinh_trang).cls">
                  {{ getStatusLabel(item.tinh_trang).text }}
                </span>
              </td>
              <td class="text-center">
                <div class="d-flex justify-content-center align-items-center" style="gap: 8px;">
                  <button class="btn btn-sm btn-outline-secondary d-flex justify-content-center align-items-center px-2" title="Trạng thái" @click="openToggleStatus(item)">
                    <component :is="item.tinh_trang === 'hoat_dong' ? XCircle : CheckCircle" size="14" />
                  </button>
                  <button class="btn btn-sm btn-outline-primary d-flex justify-content-center align-items-center px-2" title="Sửa" @click="openEditModal(item)">
                    <Edit2 size="14" />
                  </button>
                  <button class="btn btn-sm btn-danger d-flex justify-content-center align-items-center px-2" title="Xóa" @click="openDelete(item)">
                    <Trash2 size="14" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Phân trang -->
      <div v-if="meta.last_page > 1" class="pagination-container mt-4 d-flex justify-content-between align-items-center">
        <span class="text-muted" style="font-size: 14px;">Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
        <div class="d-flex" style="gap: 8px;">
          <button class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page <= 1" @click="fetchNhaXes(meta.current_page - 1)">Trước</button>
          <button class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="fetchNhaXes(meta.current_page + 1)">Sau</button>
        </div>
      </div>
    </div>

    <!-- MODAL THÊM / SỬA -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal-content modal-lg">
        <div class="modal-header d-flex justify-content-between align-items-center">
          <h5 class="modal-title m-0 fw-bold d-flex align-items-center" style="font-size: 1.25rem;">
            <Building2 class="text-primary mr-2" size="24" style="margin-right: 8px;" />
            {{ modalMode === 'add' ? 'Thêm Nhà Xe Mới' : 'Cập Nhật Hồ Sơ' }}
          </h5>
          <button class="btn-close-custom" @click="showModal = false"><XCircle size="24" /></button>
        </div>
        
        <div class="modal-body p-4">
          <div v-if="formError" class="alert alert-danger" style="white-space: pre-wrap;">{{ formError }}</div>

          <div class="form-grid">
            <div class="form-column">
              <h6 class="column-title"><Info class="mr-2" size="18" style="margin-right: 6px;"/> Thông tin doanh nghiệp</h6>
              
              <div class="form-group mb-3">
                <label class="base-input-label">Tên nhà xe / Tên DN <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input type="text" v-model="formData.ten_nha_xe" class="custom-input" required>
                </div>
              </div>

              <div class="form-group mb-3">
                <label class="base-input-label">Giấy phép kinh doanh <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input type="text" v-model="formData.giay_phep_kinh_doanh" class="custom-input" required>
                </div>
              </div>

              <div class="form-group mb-3">
                <label class="base-input-label">Địa chỉ văn phòng</label>
                <div class="position-relative">
                  <input type="text" v-model="formData.dia_chi" class="custom-input">
                </div>
              </div>
              
              <div class="form-group mb-3">
                <label class="base-input-label">Tỷ lệ chiết khấu (%) <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input type="number" step="0.1" v-model="formData.chiet_khau" class="custom-input" required>
                </div>
                <small class="text-muted mt-1 d-block">Mức phí áp dụng cho mỗi giao dịch qua hệ thống.</small>
              </div>
            </div>

            <div class="form-column">
              <h6 class="column-title"><User class="mr-2" size="18" style="margin-right: 6px;"/> Liên hệ & Thanh toán</h6>
              
              <div class="form-group mb-3">
                <label class="base-input-label">Người đại diện <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input type="text" v-model="formData.nguoi_dai_dien" class="custom-input" required>
                </div>
              </div>

              <div class="form-group mb-3">
                <label class="base-input-label">Số điện thoại <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input type="text" v-model="formData.so_dien_thoai" class="custom-input" required>
                </div>
              </div>

              <div class="form-group mb-3">
                <label class="base-input-label">Email tài khoản <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input type="email" v-model="formData.email" class="custom-input" required>
                </div>
              </div>

              <div class="form-group mb-3">
                <label class="base-input-label">Tài khoản nhận tiền (Stk, Ngân hàng)</label>
                <textarea v-model="formData.tai_khoan_ngan_hang" class="custom-input h-auto" rows="3" placeholder="VD: 19033... Techcombank HCM"></textarea>
              </div>
            </div>
          </div>
        </div>
        
        <div class="modal-footer p-4 bg-light border-top-0" style="border-radius: 0 0 20px 20px;">
          <button class="btn btn-secondary mr-2" @click="showModal = false" :disabled="submitLoading">Hủy</button>
          <button class="btn btn-primary d-flex align-items-center" @click="handleSave" :disabled="submitLoading">
            <RefreshCw v-if="submitLoading" class="animate-spin mr-2" size="18" style="margin-right: 8px;" />
            <Save v-else class="mr-2" size="18" style="margin-right: 8px;" />
            Lưu Thông Tin
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL XÁC NHẬN CHUNG -->
    <div v-if="showConfirm" class="modal-overlay" @click.self="showConfirm = false">
      <div class="modal-content modal-sm">
        <div class="modal-body text-center p-4">
          <div class="confirm-icon mx-auto mb-3 d-flex align-items-center justify-content-center" :class="confirmAction === 'delete' ? 'icon-danger' : 'icon-warning'">
            <AlertTriangle v-if="confirmAction === 'delete'" size="36" />
            <Info v-else size="36" />
          </div>
          
          <h5 class="mb-3 fw-bold">Xác nhận</h5>
          <p class="text-muted mb-4">
            <template v-if="confirmAction === 'delete'">
              Bạn chắc chắn muốn xoá nhà xe <strong>{{ confirmTarget?.ten_nha_xe }}</strong>?
            </template>
            <template v-else>
              Bạn muốn {{ confirmTarget?.tinh_trang === 'hoat_dong' ? 'khoá' : 'kích hoạt' }} nhà xe <strong>{{ confirmTarget?.ten_nha_xe }}</strong>?
            </template>
          </p>
          
          <div class="d-flex justify-content-center" style="gap: 12px;">
            <button class="btn btn-secondary px-4" @click="showConfirm = false" :disabled="confirmLoading">Hủy</button>
            <button 
              class="btn px-4 text-white d-flex align-items-center" 
              :class="confirmAction === 'delete' ? 'btn-danger' : 'btn-primary'"
              @click="executeConfirm" 
              :disabled="confirmLoading"
            >
              <RefreshCw v-if="confirmLoading" class="animate-spin mr-2" size="18" style="margin-right: 6px;" />
              Đồng ý
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
/* Xoá max-width để fill width hoàn toàn giống ChuyenXeView */
.admin-page {
  padding: 1.5rem;
  width: 100%;
}

/* Header */
.header-icon-wrap {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #bfdbfe;
}
.header-icon {
  color: #2563eb;
  width: 24px;
  height: 24px;
}
.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
}

/* Các nút header */
.btn-refresh {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: white;
  color: #64748b;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  transition: all 0.2s;
}
.btn-refresh:hover {
  background: #f1f5f9;
  color: #2563eb;
}
.spinning .btn-icon {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  100% { transform: rotate(360deg); }
}

/* Filter Card */
.filter-card {
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(10px);
  border: 1px solid #e2e8f0;
  padding: 1rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.filter-grid {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
  align-items: flex-end;
}
.filter-item {
  flex: 1;
  min-width: 200px;
}
.filter-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #475569;
  margin-bottom: 0.4rem;
}
.input-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  width: 16px;
  height: 16px;
}
.pl-10 {
  padding-left: 36px !important;
}

/* Inputs & Form */
.custom-input,
.custom-select {
  width: 100%;
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background-color: #ffffff;
  color: #1e293b;
  transition: all 0.2s;
}
.custom-input:focus,
.custom-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
.base-input-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  color: #334155;
  margin-bottom: 0.4rem;
}

/* Bảng */
.table-card {
  background: white;
  border-radius: 12px;
  padding: 1rem;
  box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.025);
  border: 1px solid rgba(226, 232, 240, 0.8);
}
.data-table {
  width: 100%;
  border-collapse: collapse;
}
.data-table th {
  padding: 12px 16px;
  font-size: 0.75rem;
  font-weight: 700;
  color: #64748b;
  border-bottom: 1px solid #e2e8f0;
  background-color: #f8fafc;
}
.data-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.data-table tbody tr:hover {
  background-color: #f8fafc;
}

.avatar-bg {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #e0e7ff;
  display: flex;
  align-items: center;
  justify-content: center;
}
.truncate-text {
  max-width: 160px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Badge */
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-block;
}
.badge-green { background: #dcfce7; color: #16a34a; }
.badge-yellow { background: #fef3c7; color: #d97706; }
.badge-red { background: #fee2e2; color: #dc2626; }
.badge-gray { background: #f1f5f9; color: #64748b; }

.discount-badge {
  display: inline-flex;
  align-items: center;
  background: #fdf2f8;
  color: #db2777;
  padding: 4px 8px;
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.8rem;
}

.ai-stat {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.75rem;
}
.stat-danger { background: #fee2e2; color: #dc2626; }
.stat-warning { background: #fef3c7; color: #d97706; }

/* Modals */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1050;
}
.modal-content {
  background: white;
  border-radius: 16px;
  width: 100%;
  max-height: 90vh;
  box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
  display: flex;
  flex-direction: column;
  animation: slideUp 0.3s ease;
  border: none;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.modal-lg { max-width: 800px; }
.modal-sm { max-width: 400px; }
.btn-close-custom {
  background: transparent;
  border: none;
  color: #94a3b8;
  padding: 0;
  cursor: pointer;
}
.btn-close-custom:hover {
  color: #ef4444;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}
@media (max-width: 768px) {
  .form-grid { grid-template-columns: 1fr; gap: 1rem; }
}
.column-title {
  color: #475569;
  font-weight: 700;
  border-bottom: 2px solid #f1f5f9;
  padding-bottom: 8px;
  margin-bottom: 1rem;
}

/* Icons */
.confirm-icon {
  width: 70px;
  height: 70px;
  border-radius: 50%;
}
.icon-danger { background: #fee2e2; color: #dc2626; }
.icon-warning { background: #fef3c7; color: #d97706; }

/* Buttons Overrides */
.btn-primary { background-color: #3b82f6; border-color: #3b82f6; box-shadow: 0 4px 6px -1px rgba(59,130,246,0.5); border-radius: 8px; padding: 8px 16px; }
.btn-primary:hover { background-color: #2563eb; }
.btn-secondary { background-color: #f1f5f9; border-color: #e2e8f0; color: #475569; }
.btn-secondary:hover { background-color: #e2e8f0; color: #1e293b; }
.btn-danger { background-color: #ef4444; border-color: #ef4444; box-shadow: 0 4px 6px -1px rgba(239,68,68,0.3); border-radius: 8px; }
.btn-danger:hover { background-color: #dc2626; }
</style>
