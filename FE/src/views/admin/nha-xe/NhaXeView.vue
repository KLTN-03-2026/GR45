<script setup>
import { computed, onMounted, reactive, ref, nextTick, watch } from 'vue'
import adminApi from '@/api/adminApi'
import { Modal } from 'bootstrap'
import {
  AlertTriangle,
  BadgePercent,
  Building2,
  CheckCircle,
  Eye,
  ImageIcon,
  Info,
  Lock,
  Mail,
  MapPin,
  Phone,
  Plus,
  RefreshCw,
  Save,
  Search,
  ShieldAlert,
  Trash2,
  Unlock,
  User
} from 'lucide-vue-next'

const operatorList = ref([])
const loading = ref(false)
const filters = reactive({
  keyword: '',
  status: '',
  page: 1,
  perPage: 10
})
const meta = reactive({
  current_page: 1,
  last_page: 1,
  total: 0
})

const activeTab = ref('all')

const formModalRef = ref(null)
const confirmModalRef = ref(null)
const formMode = ref('add')
const submitLoading = ref(false)
const formError = ref('')

const confirmLoading = ref(false)
const confirmAction = ref('')
const confirmTitle = ref('')
const confirmMessage = ref('')
const confirmTarget = ref(null)

let formModalInstance = null
let confirmModalInstance = null

const toast = reactive({
  visible: false,
  type: 'success',
  message: ''
})
let toastTimer = null

const form = reactive({
  id: null,
  ten_nha_xe: '',
  email: '',
  password: '',
  so_dien_thoai: '',
  nguoi_dai_dien: '',
  giay_phep_kinh_doanh: '',
  ma_so_thue: '',
  dia_chi_van_phong: '',
  tai_khoan_ngan_hang: '',
  ty_le_chiet_khau: 0,
  anh_logo: '',
  anh_tru_so: '',
  dia_chi_nha_xe_label: ''
})

// Tự động đồng bộ ma_so_thue từ giay_phep_kinh_doanh
watch(() => form.giay_phep_kinh_doanh, (val) => {
  form.ma_so_thue = val
})

// File objects cho upload
const fileAnhLogo = ref(null)
const fileAnhTruSo = ref(null)
const fileGiayPhep = ref(null)
const fileCccd = ref(null)
// Preview / info URLs
const previewAnhLogo = ref('')
const previewAnhTruSo = ref('')
const previewGiayPhep = ref('')   // tên file PDF hiện tại
const previewCccd = ref('')       // URL ảnh CCCD hiện tại
const pdfViewUrl = ref('')        // URL thực để mở xem PDF (objectURL hoặc Cloudinary URL)

const onFileChange = (field, event) => {
  const file = event.target.files[0]
  if (!file) return
  if (field === 'anh_logo') {
    fileAnhLogo.value = file
    previewAnhLogo.value = URL.createObjectURL(file)
  } else if (field === 'anh_tru_so') {
    fileAnhTruSo.value = file
    previewAnhTruSo.value = URL.createObjectURL(file)
  } else if (field === 'file_giay_phep_kinh_doanh') {
    fileGiayPhep.value = file
    previewGiayPhep.value = file.name
    pdfViewUrl.value = URL.createObjectURL(file)
  } else if (field === 'file_cccd_dai_dien') {
    fileCccd.value = file
    previewCccd.value = URL.createObjectURL(file)
  }
}

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'hoat_dong', label: 'Hoạt động' },
  { value: 'cho_duyet', label: 'Chờ duyệt' },
  { value: 'khoa', label: 'Đã khóa' }
]

/** Chuyển đổi giá trị sang số an toàn — trả về 0 nếu không hợp lệ */
const safeNumber = (value) => {
  const n = Number(value)
  return Number.isFinite(n) ? n : 0
}


const showToast = (message, type = 'success') => {
  toast.message = message
  toast.type = type
  toast.visible = true
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
    toast.visible = false
  }, 3000)
}


const extractResponseData = (res) => {
  const data = res?.data ?? res
  return data?.data?.data ? data.data : (Array.isArray(data?.data) ? data : { data: Array.isArray(data) ? data : [] })
}


const normalizeOperator = (item) => {
  const hoSo = item.ho_so || {}
  return {
    ...item,
    giay_phep_kinh_doanh: hoSo.so_dang_ky_kinh_doanh || item.giay_phep_kinh_doanh || '',
    dia_chi_van_phong: (Array.isArray(item.dia_chi_nha_xe) ? item.dia_chi_nha_xe[0]?.dia_chi : null) || hoSo.dia_chi || hoSo.dia_chi_chi_tiet || item.dia_chi_van_phong || '',
    nguoi_dai_dien: hoSo.nguoi_dai_dien || item.nguoi_dai_dien || '',
    tai_khoan_ngan_hang: item.tai_khoan_ngan_hang || item.thong_tin_tai_khoan_nhan_tien || item.tai_khoan_nhan_tien || '',
    ty_le_chiet_khau: safeNumber(item.ty_le_chiet_khau ?? item.chiet_khau ?? item.hoa_hong),
    tong_sos: safeNumber(item.tong_sos),
    tong_ngu_gat: safeNumber(item.tong_ngu_gat)
  }
}


const getStatusKey = (status) => ({ 1: 'hoat_dong', 0: 'khoa', hoat_dong: 'hoat_dong', cho_duyet: 'cho_duyet', khoa: 'khoa' }[status] || 'unknown')


const getStatusMeta = (status) => ({
  hoat_dong: { text: 'Hoạt động', cls: 'badge-green' },
  cho_duyet: { text: 'Chờ duyệt', cls: 'badge-yellow' },
  khoa: { text: 'Đã khóa', cls: 'badge-red' }
}[getStatusKey(status)] || { text: 'Không rõ', cls: 'badge-gray' })

/** Kiểm tra nhà xe có đang chờ phê duyệt không */
const canApprove = (item) => getStatusKey(item.tinh_trang) === 'cho_duyet'

/** Danh sách nhà xe hiển thị theo tab đang chọn */
const displayedOperators = computed(() => activeTab.value === 'approval' ? operatorList.value.filter(canApprove) : operatorList.value)

/** Thống kê tổng hợp cho dashboard: tổng, chờ duyệt, hoạt động, cảnh báo AI */
const dashboardStats = computed(() => {
  const stats = { total: meta.total, pending: 0, active: 0, totalSos: 0, totalDrowsy: 0 }
  operatorList.value.forEach(item => {
    const key = getStatusKey(item.tinh_trang)
    if (key === 'cho_duyet') stats.pending++
    if (key === 'hoat_dong') stats.active++
    stats.totalSos += item.tong_sos
    stats.totalDrowsy += item.tong_ngu_gat
  })
  return stats
})


const fetchOperators = async (page = 1) => {
  loading.value = true
  try {
    const res = await adminApi.getOperators({ page, per_page: filters.perPage, search: filters.keyword || undefined, tinh_trang: filters.status || undefined })
    const payload = extractResponseData(res)
    operatorList.value = payload.data.map(normalizeOperator)
    meta.current_page = payload.current_page || 1
    meta.last_page = payload.last_page || 1
    meta.total = payload.total || operatorList.value.length
    filters.page = page
  } catch (e) {
    showToast('Không thể tải danh sách.', 'error')
  } finally {
    loading.value = false
  }
}

/** Đặt lại toàn bộ dữ liệu form về trạng thái trống ban đầu */
const resetForm = () => {
  Object.assign(form, { id: null, ten_nha_xe: '', email: '', password: '', so_dien_thoai: '', nguoi_dai_dien: '', giay_phep_kinh_doanh: '', ma_so_thue: '', dia_chi_van_phong: '', tai_khoan_ngan_hang: '', ty_le_chiet_khau: 0, anh_logo: '', anh_tru_so: '', dia_chi_nha_xe_label: '' })
  fileAnhLogo.value = null
  fileAnhTruSo.value = null
  fileGiayPhep.value = null
  fileCccd.value = null
  previewAnhLogo.value = ''
  previewAnhTruSo.value = ''
  previewGiayPhep.value = ''
  previewCccd.value = ''
  pdfViewUrl.value = ''
  formError.value = ''
}

/** Mở modal thêm mới nhà xe (reset form, chuyển mode sang 'add') */
const openAddModal = async () => { formMode.value = 'add'; resetForm(); await nextTick(); formModalInstance?.show() }


const openEditModal = async (item) => {
  formMode.value = 'edit'
  resetForm()
  Object.assign(form, item, { dia_chi_nha_xe_label: item.dia_chi_van_phong || item.ho_so?.dia_chi || '' })
  // Điền giấy phép và mã số thuế từ ho_so
  form.giay_phep_kinh_doanh = item.ho_so?.so_dang_ky_kinh_doanh || item.giay_phep_kinh_doanh || ''
  form.ma_so_thue = item.ho_so?.ma_so_thue || form.giay_phep_kinh_doanh
  // Điền sẵn ảnh hiện tại nếu có
  previewAnhLogo.value = item.ho_so?.anh_logo || item.anh_logo || ''
  previewAnhTruSo.value = item.ho_so?.anh_tru_so || item.anh_tru_so || ''
  // Điền tên file giấy tờ hiện tại
  const gpkdUrl = item.ho_so?.file_giay_phep_kinh_doanh || ''
  previewGiayPhep.value = gpkdUrl ? gpkdUrl.split('/').pop() : ''
  pdfViewUrl.value = gpkdUrl
  previewCccd.value = item.ho_so?.file_cccd_dai_dien || ''
  await nextTick()
  formModalInstance?.show()
}


const buildFormPayload = () => {
  const fd = new FormData()
  fd.append('ten_nha_xe', form.ten_nha_xe)
  fd.append('email', form.email)
  fd.append('so_dien_thoai', form.so_dien_thoai || '')
  fd.append('nguoi_dai_dien', form.nguoi_dai_dien || '')
  fd.append('so_dang_ky_kinh_doanh', form.giay_phep_kinh_doanh || '')
  fd.append('ma_so_thue', form.giay_phep_kinh_doanh || '')  // luôn bằng so_dang_ky_kinh_doanh
  fd.append('dia_chi_chi_tiet', form.dia_chi_van_phong || '')
  fd.append('tai_khoan_nhan_tien', form.tai_khoan_ngan_hang || '')
  fd.append('ty_le_chiet_khau', safeNumber(form.ty_le_chiet_khau))
  if (formMode.value === 'add') fd.append('password', form.password)
  if (fileAnhLogo.value) fd.append('anh_logo', fileAnhLogo.value)
  if (fileAnhTruSo.value) fd.append('anh_tru_so', fileAnhTruSo.value)
  if (fileGiayPhep.value) fd.append('file_giay_phep_kinh_doanh', fileGiayPhep.value)
  if (fileCccd.value) fd.append('file_cccd_dai_dien', fileCccd.value)
  return fd
}

/**
 * Validate và lưu form nhà xe (thêm mới hoặc cập nhật)
 * — kiểm tra các trường bắt buộc, validate chiết khấu, gọi API tương ứng
 */
const saveForm = async () => {
  if (!form.ten_nha_xe || !form.email) {
    formError.value = 'Vui lòng nhập đầy đủ tên doanh nghiệp và email.'
    return
  }
  if (formMode.value === 'add' && !form.password) {
    formError.value = 'Vui lòng nhập mật khẩu cho nhà xe mới.'
    return
  }
  if (formMode.value === 'add' && String(form.password).length < 6) {
    formError.value = 'Mật khẩu phải có ít nhất 6 ký tự.'
    return
  }
  const ck = Number(form.ty_le_chiet_khau)
  if (!Number.isFinite(ck) || ck < 0 || ck > 100) {
    formError.value = 'Tỷ lệ chiết khấu/hoa hồng phải là số trong khoảng 0 đến 100.'
    return
  }
  submitLoading.value = true
  formError.value = ''
  try {
    const payload = buildFormPayload()
    if (formMode.value === 'add') {
      await adminApi.createOperator(payload)
      showToast('Đã thêm hồ sơ nhà xe mới.', 'success')
    } else {
      await adminApi.updateOperator(form.id, payload)
      showToast('Đã cập nhật hồ sơ nhà xe.', 'success')
    }
    formModalInstance?.hide()
    await fetchOperators(filters.page)
  } catch (error) {
    console.error('Lỗi lưu nhà xe:', error)
    const detailedErrors = Object.values(error?.response?.data?.errors || {}).flat().join('\n')
    formError.value = detailedErrors || error?.response?.data?.message || 'Không thể lưu dữ liệu nhà xe.'
  } finally {
    submitLoading.value = false
  }
}


const checkRoutes = async (operator) => {
  const res = await adminApi.getRoutes({ per_page: 100, search: operator.ma_nha_xe || operator.ten_nha_xe })
  return extractResponseData(res).data.some(r => ['hoat_dong', 'da_duyet'].includes(r.tinh_trang || r.trang_thai))
}


const openConfirm = (action, item) => {
  confirmAction.value = action; confirmTarget.value = item;
  confirmTitle.value = { approve: 'Duyệt tham gia', toggle: 'Đổi trạng thái', delete: 'Xóa nhà xe' }[action]
  confirmMessage.value = `Thực hiện ${confirmTitle.value} cho "${item.ten_nha_xe}"?`
  confirmModalInstance?.show()
}

/**
 * Thực thi hành động sau khi người dùng xác nhận trong modal:
 * - approve: duyệt nhà xe (yêu cầu có tuyến được duyệt)
 * - toggle: kích hoạt/khóa nhà xe (kích hoạt cũng yêu cầu có tuyến)
 * - delete: xóa nhà xe
 */
const executeConfirm = async () => {
  confirmLoading.value = true
  try {
    const { action, target } = { action: confirmAction.value, target: confirmTarget.value }
    if (action === 'approve' || (action === 'toggle' && getStatusKey(target.tinh_trang) === 'khoa')) {
      if (!(await checkRoutes(target))) return showToast('Nhà xe chưa có tuyến đường được duyệt.', 'error')
    }
    action === 'delete' ? await adminApi.deleteOperator(target.id) : await adminApi.toggleOperatorStatus(target.id)
    showToast('Thao tác thành công.')
    confirmModalInstance?.hide(); fetchOperators(filters.page)
  } catch (e) { showToast('Thao tác thất bại.', 'error') } finally { confirmLoading.value = false }
}

/** Áp dụng bộ lọc và tải lại từ trang 1 */
const submitFilter = () => fetchOperators(1)

/** Mở file PDF ở tab mới */
const openPdf = (url) => { if (url) window.open(url, '_blank') }

/** Khởi tạo Bootstrap Modal instances và tải dữ liệu nhà xe lần đầu */
onMounted(async () => {
  await nextTick()
  formModalInstance = new Modal(formModalRef.value, { backdrop: 'static' })
  confirmModalInstance = new Modal(confirmModalRef.value, { backdrop: 'static' })
  fetchOperators()
})
</script>

<template>
  <div class="admin-page">
    <div v-if="toast.visible" class="custom-toast" :class="toast.type">{{ toast.message }}</div>

    <!-- header -->
    <div class="page-header">
      <div class="header-left">
        <div class="header-icon-wrap">
          <Building2 class="header-icon" />
        </div>
        <div>
          <h1 class="page-title">Quản lý Nhà xe</h1>
          <p class="sub-title">Quản lý hồ sơ, phê duyệt tham gia, an toàn AI và hợp đồng chiết khấu.</p>
        </div>
      </div>
      <div class="header-actions">
        <button class="btn btn-refresh" @click="fetchOperators(filters.page)">
          <RefreshCw size="18" :class="{ 'spin-icon': loading }" />
        </button>
        <button class="btn btn-primary" @click="openAddModal">
          <Plus size="16" />
          Thêm nhà xe
        </button>
      </div>
    </div>

    <!-- grid stats nhà xe -->
    <div class="stats-grid">
      <div class="stat-card">
        <p class="label">Tổng nhà xe</p>
        <p class="value">{{ dashboardStats.total }}</p>
      </div>
      <div class="stat-card">
        <p class="label">Chờ phê duyệt</p>
        <p class="value text-warning">{{ dashboardStats.pending }}</p>
      </div>
      <div class="stat-card">
        <p class="label">Đang hoạt động</p>
        <p class="value text-success">{{ dashboardStats.active }}</p>
      </div>
      <div class="stat-card">
        <p class="label">Cảnh báo AI</p>
        <p class="value text-danger">{{ dashboardStats.totalSos + dashboardStats.totalDrowsy }}</p>
        <p class="hint">SOS: {{ dashboardStats.totalSos }} | Ngủ gật: {{ dashboardStats.totalDrowsy }}</p>
      </div>
    </div>

    <!-- filter card -->
    <div class="filter-card">
      <div class="tab-row">
        <button class="tab-btn" :class="{ active: activeTab === 'all' }" @click="activeTab = 'all'">Tất cả</button>
        <button class="tab-btn" :class="{ active: activeTab === 'approval' }" @click="activeTab = 'approval'">Phê duyệt tham gia</button>
        <button class="tab-btn" :class="{ active: activeTab === 'safety' }" @click="activeTab = 'safety'">Giám sát an toàn AI</button>
      </div>

      <div class="filter-grid">
        <div class="filter-item">
          <label>Tìm kiếm</label>
          <div class="input-wrap">
            <Search class="input-icon" size="16" />
            <input
              v-model="filters.keyword"
              class="custom-input with-icon"
              placeholder="Tên nhà xe, mã nhà xe, email, số điện thoại"
              @keyup.enter="submitFilter"
            />
          </div>
        </div>
        <div class="filter-item">
          <label>Trạng thái</label>
          <select v-model="filters.status" class="custom-select">
            <option v-for="item in statusOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
          </select>
        </div>
        <div class="filter-item btn-wrap">
          <button class="btn btn-secondary" @click="submitFilter">Lọc dữ liệu</button>
        </div>
      </div>
    </div>

    <!-- table list nha xe -->
    <div class="table-card">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Doanh nghiệp</th>
              <th>Hồ sơ pháp lý</th>
              <th>Đại diện</th>
              <th>Hợp đồng</th>
              <th>An toàn AI</th>
              <th>Trạng thái</th>
              <th class="text-center">Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="center muted">
                <RefreshCw class="spin-inline" size="16" />
                Đang tải dữ liệu...
              </td>
            </tr>
            <tr v-else-if="displayedOperators.length === 0">
              <td colspan="7" class="center muted">Không có dữ liệu nhà xe phù hợp bộ lọc.</td>
            </tr>
            <tr v-for="item in displayedOperators" :key="item.id">
              <td>
                <p class="strong">{{ item.ten_nha_xe }}</p>
                <p class="muted">{{ item.ma_nha_xe || 'Chưa có mã' }}</p>
                <p class="muted">{{ item.email }}</p>
              </td>
              <td>
                <p class="line-item">
                  <Info size="14" />
                  {{ item.giay_phep_kinh_doanh || 'Chưa cập nhật GPKD' }}
                </p>
                <p class="line-item">
                  <MapPin size="14" />
                  {{ item.dia_chi_van_phong || item.ho_so?.dia_chi || item.ho_so?.dia_chi_chi_tiet || 'Chưa cập nhật địa chỉ' }}
                </p>
              </td>
              <td>
                <p class="line-item">
                  <User size="14" />
                  {{ item.nguoi_dai_dien || 'Chưa cập nhật' }}
                </p>
                <p class="line-item">
                  <Phone size="14" />
                  {{ item.so_dien_thoai || 'Chưa cập nhật' }}
                </p>
                <p class="line-item">
                  <Mail size="14" />
                  {{ item.tai_khoan_ngan_hang || 'Chưa cập nhật tài khoản nhận tiền' }}
                </p>
              </td>
              <td>
                <span class="discount-badge">
                  <BadgePercent size="14" />
                  {{ item.ty_le_chiet_khau }}%
                </span>
              </td>
              <td>
                <div class="safety-wrap">
                  <span class="ai-stat stat-danger"><ShieldAlert size="14" /> SOS: {{ item.tong_sos }}</span>
                  <span class="ai-stat stat-warning"><AlertTriangle size="14" /> Ngủ gật: {{ item.tong_ngu_gat }}</span>
                </div>
              </td>
              <td>
                <span class="status-badge" :class="getStatusMeta(item.tinh_trang).cls">
                  {{ getStatusMeta(item.tinh_trang).text }}
                </span>
              </td>
              <td>
                <div class="action-group">
                  <button v-if="canApprove(item)" class="btn btn-sm btn-success" @click="openConfirm('approve', item)">
                    <CheckCircle size="14" />
                    Duyệt
                  </button>
                  <button class="btn btn-sm btn-outline-primary" @click="openEditModal(item)">
                    <Save size="14" />
                    Sửa
                  </button>
                  <button class="btn btn-sm btn-outline-secondary" @click="openConfirm('toggle', item)">
                    <Lock v-if="getStatusKey(item.tinh_trang) === 'hoat_dong'" size="14" />
                    <Unlock v-else size="14" />
                    {{ getStatusKey(item.tinh_trang) === 'hoat_dong' ? 'Khóa' : 'Kích hoạt' }}
                  </button>
                  <button class="btn btn-sm btn-danger" @click="openConfirm('delete', item)">
                    <Trash2 size="14" />
                    Xóa
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta.last_page > 1" class="pagination">
        <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
        <div class="pager-btns">
          <button class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page <= 1" @click="fetchOperators(meta.current_page - 1)">
            Trước
          </button>
          <button class="btn btn-sm btn-outline-secondary" :disabled="meta.current_page >= meta.last_page" @click="fetchOperators(meta.current_page + 1)">
            Sau
          </button>
        </div>
      </div>
    </div>

    <!-- modal form add/edit nha xe -->
    <div ref="formModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h3 class="modal-title">{{ formMode === 'add' ? 'Thêm hồ sơ nhà xe' : 'Cập nhật hồ sơ nhà xe' }}</h3>
            <button type="button" class="btn-close" aria-label="Close" @click="formModalInstance?.hide()"></button>
          </div>
          <div class="modal-body">
            <div v-if="formError" class="alert alert-danger">{{ formError }}</div>
            <div class="form-grid">
              <div>
                <label>Tên doanh nghiệp *</label>
                <input v-model="form.ten_nha_xe" class="custom-input" />
              </div>
              <div>
                <label>Email đăng nhập *</label>
                <input v-model="form.email" type="email" class="custom-input" />
              </div>
              <div v-if="formMode === 'add'">
                <label>Mật khẩu *</label>
                <input v-model="form.password" type="password" class="custom-input" />
              </div>
              <div>
                <label>Số điện thoại</label>
                <input v-model="form.so_dien_thoai" class="custom-input" />
              </div>
              <div>
                <label>Số đăng ký kinh doanh / giấy phép</label>
                <input v-model="form.giay_phep_kinh_doanh" class="custom-input" />
              </div>
              <div>
                <label>Người đại diện</label>
                <input v-model="form.nguoi_dai_dien" class="custom-input" />
              </div>
              <div>
                <label>Địa chỉ văn phòng</label>
                <input v-model="form.dia_chi_van_phong" class="custom-input" />
              </div>
              <div>
                <label>Tỷ lệ chiết khấu/hoa hồng (%)</label>
                <input v-model="form.ty_le_chiet_khau" type="number" step="0.1" min="0" class="custom-input" />
              </div>
              <div class="full-width">
                <label>Tài khoản nhận tiền</label>
                <textarea v-model="form.tai_khoan_ngan_hang" rows="3" class="custom-input"></textarea>
              </div>

              <!-- Nhóm 4 ô upload: 2 cột x 2 hàng -->
              <div class="full-width">
                <div class="upload-grid">

                  <!-- Ảnh logo -->
                  <div class="upload-cell">
                    <label class="img-field-label"><ImageIcon size="13" /> Ảnh Logo</label>
                    <div class="img-upload-wrap">
                      <label class="img-upload-btn">
                        <ImageIcon size="14" />
                        Chọn ảnh logo
                        <input type="file" accept="image/*" class="hidden-input" @change="onFileChange('anh_logo', $event)" />
                      </label>
                      <div v-if="previewAnhLogo" class="img-preview-wrap">
                        <img :src="previewAnhLogo" class="img-preview" alt="Logo preview" />
                        <span class="img-preview-label">{{ fileAnhLogo ? fileAnhLogo.name : 'Ảnh hiện tại' }}</span>
                      </div>
                      <span v-else class="img-placeholder">Chưa có ảnh logo</span>
                    </div>
                  </div>

                  <!-- Ảnh trụ sở -->
                  <div class="upload-cell">
                    <label class="img-field-label"><ImageIcon size="13" /> Ảnh Trụ sở</label>
                    <div class="img-upload-wrap">
                      <label class="img-upload-btn">
                        <ImageIcon size="14" />
                        Chọn ảnh trụ sở
                        <input type="file" accept="image/*" class="hidden-input" @change="onFileChange('anh_tru_so', $event)" />
                      </label>
                      <div v-if="previewAnhTruSo" class="img-preview-wrap">
                        <img :src="previewAnhTruSo" class="img-preview" alt="Trụ sở preview" />
                        <span class="img-preview-label">{{ fileAnhTruSo ? fileAnhTruSo.name : 'Ảnh hiện tại' }}</span>
                      </div>
                      <span v-else class="img-placeholder">Chưa có ảnh trụ sở</span>
                    </div>
                  </div>

                  <!-- Giấy phép kinh doanh (PDF) -->
                  <div class="upload-cell">
                    <label class="img-field-label">
                      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                      Giấy phép KD (PDF)
                    </label>
                    <div class="img-upload-wrap">
                      <label class="img-upload-btn pdf-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Chọn file PDF
                        <input type="file" accept="application/pdf" class="hidden-input" @change="onFileChange('file_giay_phep_kinh_doanh', $event)" />
                      </label>
                      <div v-if="previewGiayPhep" class="pdf-info-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                        <span class="pdf-name">{{ previewGiayPhep }}</span>
                        <button v-if="pdfViewUrl" type="button" class="btn-view-pdf" @click="openPdf(pdfViewUrl)">
                          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                          Xem PDF
                        </button>
                      </div>
                      <span v-else class="img-placeholder">Chưa có file PDF</span>
                    </div>
                  </div>

                  <!-- CCCD đại diện -->
                  <div class="upload-cell">
                    <label class="img-field-label"><ImageIcon size="13" /> Ảnh CCCD Đại diện</label>
                    <div class="img-upload-wrap">
                      <label class="img-upload-btn">
                        <ImageIcon size="14" />
                        Chọn ảnh CCCD
                        <input type="file" accept="image/*" class="hidden-input" @change="onFileChange('file_cccd_dai_dien', $event)" />
                      </label>
                      <div v-if="previewCccd" class="img-preview-wrap">
                        <img :src="previewCccd" class="img-preview" alt="CCCD preview" />
                        <span class="img-preview-label">{{ fileCccd ? fileCccd.name : 'Ảnh hiện tại' }}</span>
                      </div>
                      <span v-else class="img-placeholder">Chưa có ảnh CCCD</span>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" :disabled="submitLoading" @click="formModalInstance?.hide()">Hủy</button>
            <button class="btn btn-primary" :disabled="submitLoading" @click="saveForm">
              <RefreshCw v-if="submitLoading" size="14" class="spin-inline" />
              Lưu thông tin
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- modal confirm -->
    <div ref="confirmModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
          <div class="modal-body text-center">
            <div class="confirm-icon">
              <Eye size="28" />
            </div>
            <h4 class="modal-title">{{ confirmTitle }}</h4>
            <p>{{ confirmMessage }}</p>
          </div>
          <div class="modal-footer justify-content-center">
            <button class="btn btn-secondary" :disabled="confirmLoading" @click="confirmModalInstance?.hide()">Hủy</button>
            <button class="btn btn-primary" :disabled="confirmLoading" @click="executeConfirm">
              <RefreshCw v-if="confirmLoading" size="14" class="spin-inline" />
              Xác nhận
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-page {
  padding: 1.5rem;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.header-icon-wrap {
  width: 46px;
  height: 46px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  background: linear-gradient(135deg, #eef2ff 0%, #dbeafe 100%);
  border: 1px solid #c7d2fe;
}

.header-icon {
  color: #4f46e5;
}

.page-title {
  margin: 0;
  font-size: 1.45rem;
  font-weight: 700;
  color: #1e293b;
}

.sub-title {
  margin: 2px 0 0;
  font-size: 0.88rem;
  color: #64748b;
}

.header-actions {
  display: flex;
  gap: 0.65rem;
}

.btn-refresh {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  border: 1px solid #dbe3ef;
  background: #fff;
  color: #475569;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.85rem;
  margin-bottom: 1rem;
}

.stat-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 0.85rem 1rem;
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
}

.label {
  margin: 0;
  color: #64748b;
  font-size: 0.8rem;
}

.value {
  margin: 2px 0;
  font-size: 1.3rem;
  font-weight: 700;
  color: #1e293b;
}

.hint {
  margin: 0;
  font-size: 0.75rem;
  color: #64748b;
}

.text-warning {
  color: #d97706;
}

.text-success {
  color: #16a34a;
}

.text-danger {
  color: #dc2626;
}

.filter-card,
.table-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1rem;
  box-shadow: 0 8px 18px rgba(15, 23, 42, 0.03);
}

.filter-card {
  margin-bottom: 1rem;
}

.tab-row {
  display: flex;
  gap: 0.6rem;
  margin-bottom: 0.85rem;
}

.tab-btn {
  border: 1px solid #dbe3ef;
  background: #f8fafc;
  color: #475569;
  border-radius: 8px;
  padding: 0.4rem 0.7rem;
  font-size: 0.82rem;
  font-weight: 600;
}

.tab-btn.active {
  background: #e0e7ff;
  color: #4338ca;
  border-color: #c7d2fe;
}

.filter-grid {
  display: grid;
  grid-template-columns: 2fr 1fr auto;
  gap: 0.75rem;
  align-items: end;
}

.filter-item label {
  display: block;
  font-size: 0.8rem;
  color: #475569;
  margin-bottom: 0.4rem;
  font-weight: 600;
}

.input-wrap {
  position: relative;
}

.input-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
}

.custom-input,
.custom-select {
  width: 100%;
  border: 1px solid #dbe3ef;
  border-radius: 8px;
  padding: 0.55rem 0.75rem;
  font-size: 0.9rem;
}

.with-icon {
  padding-left: 30px;
}

.custom-input:focus,
.custom-select:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.btn-wrap {
  display: flex;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  border-bottom: 1px solid #eef2f7;
  padding: 0.7rem;
  vertical-align: top;
}

.data-table th {
  color: #64748b;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.strong {
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.muted {
  color: #64748b;
  margin: 2px 0;
  font-size: 0.8rem;
}

.center {
  text-align: center;
  padding: 1.4rem;
}

.line-item {
  margin: 0 0 4px;
  display: flex;
  align-items: center;
  gap: 5px;
  color: #334155;
  font-size: 0.82rem;
}

.discount-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0.28rem 0.58rem;
  border-radius: 999px;
  background: #eef2ff;
  color: #4338ca;
  font-weight: 700;
}

.safety-wrap {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.ai-stat {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  border-radius: 999px;
  padding: 0.2rem 0.5rem;
  font-size: 0.76rem;
  font-weight: 700;
}

.stat-danger {
  color: #b91c1c;
  background: #fee2e2;
}

.stat-warning {
  color: #b45309;
  background: #ffedd5;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.6rem;
  border-radius: 999px;
  font-size: 0.76rem;
  font-weight: 700;
}

.badge-green {
  background: #dcfce7;
  color: #166534;
}

.badge-yellow {
  background: #fef3c7;
  color: #92400e;
}

.badge-red {
  background: #fee2e2;
  color: #991b1b;
}

.badge-gray {
  background: #f1f5f9;
  color: #475569;
}

.action-group {
  display: flex;
  flex-wrap: wrap;
  gap: 0.42rem;
  justify-content: center;
}

.pagination {
  margin-top: 0.9rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: #64748b;
  font-size: 0.85rem;
}

.pager-btns {
  display: flex;
  gap: 0.42rem;
}

.modal-content {
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
}

.modal-lg {
  max-width: 840px;
}

.modal-sm {
  max-width: 430px;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border-bottom: 1px solid #eef2f7;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.05rem;
  color: #1e293b;
}

.modal-body {
  padding: 1rem;
}

.modal-footer {
  padding: 1rem;
  border-top: 1px solid #eef2f7;
  display: flex;
  justify-content: flex-end;
  gap: 0.6rem;
}

.btn {
  border: 1px solid transparent;
  border-radius: 8px;
  padding: 0.5rem 0.75rem;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-weight: 600;
  font-size: 0.84rem;
}

.btn-sm {
  padding: 0.32rem 0.55rem;
}

.btn-primary {
  background: #4f46e5;
  color: #fff;
  border-color: #4f46e5;
}

.btn-secondary {
  background: #f1f5f9;
  color: #334155;
  border-color: #dbe3ef;
}

.btn-outline-primary {
  background: #fff;
  color: #4f46e5;
  border-color: #c7d2fe;
}

.btn-outline-secondary {
  background: #fff;
  color: #334155;
  border-color: #dbe3ef;
}

.btn-success {
  background: #16a34a;
  border-color: #16a34a;
  color: #fff;
}

.btn-danger {
  background: #ef4444;
  border-color: #ef4444;
  color: #fff;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.form-grid label {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: #475569;
}

.full-width {
  grid-column: 1 / -1;
}

.alert-error {
  margin-bottom: 0.7rem;
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 0.6rem;
  white-space: pre-wrap;
}

.confirm-icon {
  width: 56px;
  height: 56px;
  border-radius: 999px;
  background: #e0e7ff;
  color: #4338ca;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 0.6rem;
}

.modal-sm .modal-body {
  text-align: center;
}

.modal-sm h4 {
  margin: 0 0 0.35rem;
  color: #1e293b;
}

.modal-sm p {
  margin: 0;
  color: #64748b;
}

.custom-toast {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 1500;
  color: #fff;
  border-radius: 8px;
  padding: 0.65rem 1rem;
  font-size: 0.85rem;
  box-shadow: 0 10px 20px rgba(15, 23, 42, 0.2);
}

.custom-toast.success {
  background: #16a34a;
}

.custom-toast.error {
  background: #dc2626;
}

.spinning,
.spin-inline {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1200px) {
  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }

  .filter-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    justify-content: flex-end;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }
}

/* ── Image upload ─────────────────────────────────────────────── */
.img-field-label {
  display: flex;
  align-items: center;
  gap: 5px;
  margin-bottom: 0.4rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: #475569;
}

.img-upload-wrap {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.img-upload-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0.45rem 0.9rem;
  background: #eef2ff;
  color: #4338ca;
  border: 1.5px dashed #a5b4fc;
  border-radius: 8px;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}

.img-upload-btn:hover {
  background: #e0e7ff;
  border-color: #6366f1;
}

.hidden-input {
  display: none;
}

.img-preview-wrap {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.img-preview {
  width: 72px;
  height: 72px;
  object-fit: cover;
  border-radius: 8px;
  border: 1.5px solid #c7d2fe;
  box-shadow: 0 2px 6px rgba(99,102,241,0.12);
}

.img-preview-label {
  font-size: 0.76rem;
  color: #64748b;
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.img-placeholder {
  font-size: 0.78rem;
  color: #94a3b8;
  font-style: italic;
}

/* PDF upload specific */
.pdf-btn {
  background: #fff5f5;
  color: #dc2626;
  border-color: #fca5a5;
}

.pdf-btn:hover {
  background: #fee2e2;
  border-color: #ef4444;
}

.pdf-info-wrap {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.pdf-name {
  font-size: 0.78rem;
  color: #334155;
  font-weight: 600;
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.btn-view-pdf {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 9px;
  border-radius: 6px;
  border: 1px solid #fca5a5;
  background: #fff5f5;
  color: #dc2626;
  font-size: 0.72rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.18s, border-color 0.18s, transform 0.12s;
  white-space: nowrap;
}

.btn-view-pdf:hover {
  background: #fee2e2;
  border-color: #ef4444;
  transform: translateY(-1px);
}

/* Spin chỉ icon, không xoay cả nút */
.spin-icon {
  animation: icon-spin 0.8s linear infinite;
  display: inline-block;
}

@keyframes icon-spin {
  from { transform: rotate(0deg); }
  to   { transform: rotate(360deg); }
}

/* 2×2 upload grid */
.upload-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.upload-cell {
  background: #f8fafc;
  border: 1.5px dashed #cbd5e1;
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.upload-cell:hover {
  border-color: #818cf8;
  background: #f5f3ff;
}
</style>

