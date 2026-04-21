<script setup>
import { computed, onMounted, reactive, ref, nextTick } from 'vue'
import adminApi from '@/api/adminApi'
import { Modal } from 'bootstrap'
import {
  AlertTriangle,
  BadgePercent,
  Building2,
  CheckCircle,
  Eye,
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
  dia_chi_van_phong: '',
  tai_khoan_ngan_hang: '',
  ty_le_chiet_khau: 0,
  dia_chi_nha_xe_label: ''
})

const statusOptions = [
  { value: '', label: 'Tất cả trạng thái' },
  { value: 'hoat_dong', label: 'Hoạt động' },
  { value: 'cho_duyet', label: 'Chờ duyệt' },
  { value: 'khoa', label: 'Đã khóa' }
]

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

const extractResponseData = (response) => {
  if (!response) return null
  const firstLayer = response.data ?? response
  if (firstLayer?.data && Array.isArray(firstLayer.data.data)) return firstLayer.data
  if (Array.isArray(firstLayer?.data)) return firstLayer
  if (Array.isArray(firstLayer)) return { data: firstLayer }
  return firstLayer?.data ?? firstLayer
}

const normalizeOperator = (item) => {
  const firstOffice = Array.isArray(item.dia_chi_nha_xe) ? item.dia_chi_nha_xe[0] : null
  return {
    ...item,
    giay_phep_kinh_doanh: item.ho_so?.so_dang_ky_kinh_doanh || item.giay_phep_kinh_doanh || '',
    dia_chi_van_phong: firstOffice?.dia_chi || item.ho_so?.dia_chi || item.ho_so?.dia_chi_chi_tiet || item.dia_chi_van_phong || '',
    nguoi_dai_dien: item.ho_so?.nguoi_dai_dien || item.nguoi_dai_dien || '',
    tai_khoan_ngan_hang:
      item.tai_khoan_ngan_hang ||
      item.thong_tin_tai_khoan_nhan_tien ||
      item.tai_khoan_nhan_tien ||
      '',
    ty_le_chiet_khau: safeNumber(item.ty_le_chiet_khau ?? item.chiet_khau ?? item.hoa_hong),
    tong_sos: safeNumber(item.tong_sos),
    tong_ngu_gat: safeNumber(item.tong_ngu_gat)
  }
}

const getStatusKey = (status) => {
  if (status === 'hoat_dong' || status === 1) return 'hoat_dong'
  if (status === 'cho_duyet') return 'cho_duyet'
  if (status === 'khoa' || status === 0) return 'khoa'
  return 'unknown'
}

const getStatusMeta = (status) => {
  const key = getStatusKey(status)
  if (key === 'hoat_dong') return { text: 'Hoạt động', cls: 'badge-green' }
  if (key === 'cho_duyet') return { text: 'Chờ duyệt', cls: 'badge-yellow' }
  if (key === 'khoa') return { text: 'Đã khóa', cls: 'badge-red' }
  return { text: 'Không rõ', cls: 'badge-gray' }
}

const canApprove = (item) => getStatusKey(item.tinh_trang) === 'cho_duyet'

const displayedOperators = computed(() => {
  if (activeTab.value === 'all') return operatorList.value
  if (activeTab.value === 'approval') return operatorList.value.filter((item) => canApprove(item))
  if (activeTab.value === 'safety') return operatorList.value
  return operatorList.value
})

const dashboardStats = computed(() => {
  const total = meta.total || operatorList.value.length
  const pending = operatorList.value.filter((item) => canApprove(item)).length
  const active = operatorList.value.filter((item) => getStatusKey(item.tinh_trang) === 'hoat_dong').length
  const totalSos = operatorList.value.reduce((sum, item) => sum + safeNumber(item.tong_sos), 0)
  const totalDrowsy = operatorList.value.reduce((sum, item) => sum + safeNumber(item.tong_ngu_gat), 0)
  return { total, pending, active, totalSos, totalDrowsy }
})

const fetchOperators = async (page = 1) => {
  loading.value = true
  try {
    const params = {
      page,
      per_page: filters.perPage,
      search: filters.keyword || undefined,
      tinh_trang: filters.status || undefined
    }
    const res = await adminApi.getOperators(params)
    const payload = extractResponseData(res) || {}
    operatorList.value = Array.isArray(payload.data) ? payload.data.map(normalizeOperator) : []
    meta.current_page = payload.current_page || 1
    meta.last_page = payload.last_page || 1
    meta.total = payload.total || operatorList.value.length
    filters.page = meta.current_page
  } catch (error) {
    console.error('Lỗi tải danh sách nhà xe:', error)
    showToast('Không thể tải danh sách nhà xe.', 'error')
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  form.id = null
  form.ten_nha_xe = ''
  form.email = ''
  form.password = ''
  form.so_dien_thoai = ''
  form.nguoi_dai_dien = ''
  form.giay_phep_kinh_doanh = ''
  form.dia_chi_van_phong = ''
  form.tai_khoan_ngan_hang = ''
  form.ty_le_chiet_khau = 0
  form.dia_chi_nha_xe_label = ''
  formError.value = ''
}

const openAddModal = async () => {
  formMode.value = 'add'
  resetForm()
  await nextTick()
  formModalInstance?.show()
}

const openEditModal = async (item) => {
  formMode.value = 'edit'
  resetForm()
  form.id = item.id
  form.ten_nha_xe = item.ten_nha_xe || ''
  form.email = item.email || ''
  form.so_dien_thoai = item.so_dien_thoai || ''
  form.nguoi_dai_dien = item.nguoi_dai_dien || ''
  form.giay_phep_kinh_doanh = item.giay_phep_kinh_doanh || ''
  form.dia_chi_van_phong = item.dia_chi_van_phong || ''
  form.tai_khoan_ngan_hang = item.tai_khoan_ngan_hang || ''
  form.ty_le_chiet_khau = safeNumber(item.ty_le_chiet_khau)
  form.dia_chi_nha_xe_label = item.dia_chi_van_phong || item.ho_so?.dia_chi || ''
  await nextTick()
  formModalInstance?.show()
}

const buildFormPayload = () => {
  const payload = {
    ten_nha_xe: form.ten_nha_xe,
    email: form.email,
    so_dien_thoai: form.so_dien_thoai,
    nguoi_dai_dien: form.nguoi_dai_dien,
    so_dang_ky_kinh_doanh: form.giay_phep_kinh_doanh,
    dia_chi_chi_tiet: form.dia_chi_van_phong,
    tai_khoan_nhan_tien: form.tai_khoan_ngan_hang,
    ty_le_chiet_khau: safeNumber(form.ty_le_chiet_khau)
  }
  if (formMode.value === 'add') payload.password = form.password
  return payload
}

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
  if (!Number.isFinite(Number(form.ty_le_chiet_khau)) || Number(form.ty_le_chiet_khau) < 0 || Number(form.ty_le_chiet_khau) > 100) {
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
    const message = detailedErrors || error?.response?.data?.message || 'Không thể lưu dữ liệu nhà xe.'
    formError.value = message
  } finally {
    submitLoading.value = false
  }
}

const extractListFromAnyResponse = (response) => {
  const payload = extractResponseData(response)
  return Array.isArray(payload?.data) ? payload.data : []
}

const hasAtLeastOneApprovedRoute = async (operator) => {
  const searchKey = operator.ma_nha_xe || operator.ten_nha_xe
  if (!searchKey) return false
  const res = await adminApi.getRoutes({ per_page: 100, search: searchKey })
  const routes = extractListFromAnyResponse(res)
  const approvedSet = new Set(['hoat_dong', 'da_duyet'])
  return routes.some((route) => {
    const sameOperator =
      (route.ma_nha_xe && operator.ma_nha_xe && route.ma_nha_xe === operator.ma_nha_xe) ||
      (route.ten_nha_xe && route.ten_nha_xe === operator.ten_nha_xe) ||
      (!operator.ma_nha_xe && route.ma_nha_xe === searchKey)
    if (!sameOperator) return false
    return approvedSet.has(route.tinh_trang) || approvedSet.has(route.trang_thai)
  })
}

const parseDateFromItem = (item) => {
  const raw = item?.ngay_khoi_hanh || item?.ngay_di || item?.ngay_xuat_ben || item?.ngay
  if (!raw) return null
  const date = new Date(raw)
  return Number.isNaN(date.getTime()) ? null : date
}

const hasFutureIncompleteRecords = async (operator) => {
  const maNhaXe = operator.ma_nha_xe
  if (!maNhaXe) return false

  const [tripRes, ticketRes] = await Promise.all([
    adminApi.getTrips({ per_page: 100, ma_nha_xe: maNhaXe }),
    adminApi.getTickets({ per_page: 100, ma_nha_xe: maNhaXe })
  ])

  const trips = extractListFromAnyResponse(tripRes)
  const tickets = extractListFromAnyResponse(ticketRes)
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  const isRelatedToOperator = (item) => {
    const itemMaNhaXe = item?.ma_nha_xe || item?.nha_xe?.ma_nha_xe || item?.chuyen_xe?.ma_nha_xe
    return itemMaNhaXe === maNhaXe
  }

  const isFutureAndNotDone = (item, statusField) => {
    if (!isRelatedToOperator(item)) return false
    const status = item?.[statusField]
    const doneStatuses = new Set(['hoan_thanh', 'da_hoan_thanh', 'huy'])
    if (doneStatuses.has(status)) return false
    const date = parseDateFromItem(item)
    if (!date) return true
    return date >= today
  }

  const hasTrip = trips.some((trip) => isFutureAndNotDone(trip, 'trang_thai'))
  const hasTicket = tickets.some((ticket) => isFutureAndNotDone(ticket, 'tinh_trang'))
  return hasTrip || hasTicket
}

const openConfirm = (action, item) => {
  confirmAction.value = action
  confirmTarget.value = item
  confirmModalInstance?.show()
  if (action === 'approve') {
    confirmTitle.value = 'Duyệt tham gia hệ thống'
    confirmMessage.value = `Duyệt nhà xe "${item.ten_nha_xe}" tham gia hệ thống?`
    return
  }
  if (action === 'toggle') {
    const isActive = getStatusKey(item.tinh_trang) === 'hoat_dong'
    confirmTitle.value = isActive ? 'Khóa nhà xe' : 'Kích hoạt nhà xe'
    confirmMessage.value = isActive
      ? `Bạn muốn khóa nhà xe "${item.ten_nha_xe}"?`
      : `Bạn muốn kích hoạt nhà xe "${item.ten_nha_xe}"?`
    return
  }
  confirmTitle.value = 'Xóa nhà xe'
  confirmMessage.value = `Bạn chắc chắn muốn xóa nhà xe "${item.ten_nha_xe}"?`
}

const executeConfirm = async () => {
  if (!confirmTarget.value) return
  confirmLoading.value = true
  try {
    const item = confirmTarget.value
    if (confirmAction.value === 'approve') {
      const hasApprovedRoute = await hasAtLeastOneApprovedRoute(item)
      if (!hasApprovedRoute) {
        showToast('Không thể duyệt kích hoạt: nhà xe chưa có tuyến đường được duyệt.', 'error')
        return
      }
      await adminApi.toggleOperatorStatus(item.id)
      showToast('Đã duyệt nhà xe thành công.', 'success')
    } else if (confirmAction.value === 'toggle') {
      const targetStatus = getStatusKey(item.tinh_trang) === 'hoat_dong' ? 'khoa' : 'hoat_dong'
      if (targetStatus === 'hoat_dong') {
        const hasApprovedRoute = await hasAtLeastOneApprovedRoute(item)
        if (!hasApprovedRoute) {
          showToast('Không thể kích hoạt: nhà xe chưa có tuyến đường được duyệt.', 'error')
          return
        }
      }
      await adminApi.toggleOperatorStatus(item.id)
      showToast('Cập nhật trạng thái nhà xe thành công.', 'success')
    } else if (confirmAction.value === 'delete') {
      const blocked = await hasFutureIncompleteRecords(item)
      if (blocked) {
        showToast('Không thể xóa: nhà xe còn chuyến xe/vé chưa hoàn thành trong tương lai.', 'error')
        return
      }
      await adminApi.deleteOperator(item.id)
      showToast('Đã xóa nhà xe.', 'success')
    }
    confirmModalInstance?.hide()
    await fetchOperators(filters.page)
  } catch (error) {
    console.error('Lỗi thao tác nhà xe:', error)
    showToast(error?.response?.data?.message || 'Thao tác thất bại.', 'error')
  } finally {
    confirmLoading.value = false
  }
}

const submitFilter = () => fetchOperators(1)

onMounted(async () => {
  await nextTick()
  formModalInstance = new Modal(formModalRef.value, { backdrop: 'static', keyboard: false })
  confirmModalInstance = new Modal(confirmModalRef.value, { backdrop: 'static', keyboard: false })
  fetchOperators()
})
</script>

<template>
  <div class="admin-page">
    <div v-if="toast.visible" class="custom-toast" :class="toast.type">{{ toast.message }}</div>

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
        <button class="btn btn-refresh" :class="{ spinning: loading }" @click="fetchOperators(filters.page)">
          <RefreshCw size="18" />
        </button>
        <button class="btn btn-primary" @click="openAddModal">
          <Plus size="16" />
          Thêm nhà xe
        </button>
      </div>
    </div>

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
</style>
