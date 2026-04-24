<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue'
import adminApi from '@/api/adminApi'
import BaseTable from '@/components/common/BaseTable.vue'
import BaseButton from '@/components/common/BaseButton.vue'
import BaseInput from '@/components/common/BaseInput.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import BaseToast from '@/components/common/BaseToast.vue'

const toast = reactive({ visible: false, message: '', type: 'success' })
let toastTimer = null
let lastToast = { message: '', type: '', at: 0 }
const showToast = (message, type = 'success') => {
  const now = Date.now()
  if (toast.visible && toast.message === message && toast.type === type) return
  const globalLastToast = window.__gobusLastToast || { message: '', type: '', at: 0 }
  if (
    globalLastToast.message === message &&
    globalLastToast.type === type &&
    now - globalLastToast.at < 4000
  ) return
  if (lastToast.message === message && lastToast.type === type && now - lastToast.at < 4000) return
  lastToast = { message, type, at: now }
  window.__gobusLastToast = { message, type, at: now }
  toast.message = message
  toast.type = type
  toast.visible = true
  if (toastTimer) clearTimeout(toastTimer)
  toastTimer = setTimeout(() => {
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
const loaiXeList = ref([])
const loaiXeLoading = ref(false)
const driverList = ref([])
const driverLoading = ref(false)

const ensureLoaiXe = async (force = false) => {
  if (!force && loaiXeList.value.length) return
  try {
    loaiXeLoading.value = true
    const res = await adminApi.getLoaiXe()
    loaiXeList.value = Array.isArray(res?.data) ? res.data : []
  } catch (error) {
    console.error('Lỗi tải loại xe:', error)
    showToast(error.response?.data?.message || 'Không tải được danh sách loại xe.', 'error')
  } finally {
    loaiXeLoading.value = false
  }
}

const ensureDrivers = async (force = false) => {
  if (!force && driverList.value.length) return
  try {
    driverLoading.value = true
    const params = {
      per_page: 200,
      tinh_trang: 'hoat_dong',
      ma_nha_xe: formData.ma_nha_xe || undefined,
    }
    const res = await adminApi.getDrivers(params)
    const raw = res?.data
    if (Array.isArray(raw?.data)) {
      driverList.value = raw.data
    } else if (Array.isArray(raw)) {
      driverList.value = raw
    } else {
      driverList.value = []
    }
  } catch (error) {
    console.error('Lỗi tải tài xế:', error)
    showToast(error.response?.data?.message || 'Không tải được danh sách tài xế.', 'error')
  } finally {
    driverLoading.value = false
  }
}

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
const formErrors = ref({})
const seatErrors = ref({})

/** Chuẩn hóa lỗi field (API có thể trả string hoặc mảng) */
const seatFieldError = (key) => {
  const raw = seatErrors.value?.[key]
  if (raw == null || raw === '') return ''
  if (Array.isArray(raw)) return raw[0] != null ? String(raw[0]) : ''
  return String(raw)
}

const clearSeatError = (key) => {
  if (!seatErrors.value[key]) return
  const next = { ...seatErrors.value }
  delete next[key]
  seatErrors.value = next
}

const onSeatCodeInput = (value) => {
  if (String(value ?? '').length > 0) {
    clearSeatError('ma_ghe')
  }
}

const openCreateModal = async () => {
  await ensureLoaiXe()
  await ensureDrivers(true)
  isEditMode.value = false
  currentVehicleId.value = null
  Object.assign(formData, initialFormData())
  formErrors.value = {}
  isFormModal.value = true
}

const openEditModal = async (vehicle) => {
  await ensureLoaiXe()
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
  formErrors.value = {}
  await ensureDrivers(true)
  isFormModal.value = true
}

const buildPayload = () => {
  const selectedLoaiXe = loaiXeList.value.find((lx) => Number(lx.id) === Number(formData.id_loai_xe))
  const payload = {
    bien_so: String(formData.bien_so || '').trim(),
    ten_xe: String(formData.ten_xe || '').trim(),
    ma_nha_xe: String(formData.ma_nha_xe || '').trim(),
    id_loai_xe: Number(formData.id_loai_xe),
    so_ghe_thuc_te: Number(selectedLoaiXe?.so_ghe_mac_dinh || 0),
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
  if (formLoading.value) return
  try {
    formLoading.value = true
    formErrors.value = {}
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
    const backendErrors = error.response?.data?.errors
    if (backendErrors) {
      formErrors.value = backendErrors
    }
    const message = backendErrors
      ? Object.values(backendErrors).flat()[0]
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
  canhBao: null,
  canhBaoLoading: false,
})

const fetchStatusCanhBao = async () => {
  if (!statusModal.show || !statusModal.id) return
  statusModal.canhBaoLoading = true
  statusModal.canhBao = null
  try {
    const res = await adminApi.getVehicleStatusChangeWarning(statusModal.id, {
      trang_thai: statusModal.trangThai,
    })
    const payload = res?.data?.data ?? res?.data
    statusModal.canhBao = payload && typeof payload === 'object' ? payload : null
  } catch (e) {
    console.error('Lỗi tải cảnh báo trạng thái xe:', e)
    statusModal.canhBao = null
  } finally {
    statusModal.canhBaoLoading = false
  }
}

watch(
  () => [statusModal.show, statusModal.trangThai, statusModal.id],
  () => {
    if (statusModal.show) fetchStatusCanhBao()
  },
)

const openStatusModal = (vehicle) => {
  statusModal.id = vehicle.id
  statusModal.bienSo = vehicle.bien_so
  statusModal.trangThai = vehicle.trang_thai || 'cho_duyet'
  statusModal.canhBao = null
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
  if (deleteModal.loading) return
  try {
    deleteModal.loading = true
    await adminApi.deleteVehicle(deleteModal.id)
    showToast('Xóa xe thành công!', 'success')
    deleteModal.show = false
    await fetchVehicles(pagination.currentPage)
  } catch (error) {
    console.error('Lỗi xóa xe:', error)
    showToast(error.response?.data?.message || 'Không thể xóa xe!', 'error')
  } finally {
    deleteModal.loading = false
  }
}

const seatModal = reactive({
  show: false,
  vehicleId: null,
  driverId: null,
  vehicleName: '',
  loading: false,
  saving: false,
  seats: [],
  editingId: null,
  form: { id_loai_ghe: '', ma_ghe: '', tang: 1, trang_thai: 'hoat_dong' },
})
const seatDeleteModal = reactive({ show: false, seat: null, loading: false })
const seatTypeList = ref([])
const seatTypeLoading = ref(false)

const ensureSeatTypes = async (force = false) => {
  if (!force && seatTypeList.value.length) return
  seatTypeLoading.value = true
  try {
    const res = await adminApi.getSeatTypes()
    const raw = res?.data
    if (Array.isArray(raw?.data)) {
      seatTypeList.value = raw.data
    } else if (Array.isArray(raw)) {
      seatTypeList.value = raw
    } else {
      seatTypeList.value = []
    }
  } catch (error) {
    showToast(error.response?.data?.message || 'Không tải được loại ghế.', 'error')
  } finally {
    seatTypeLoading.value = false
  }
}

const resetSeatForm = () => {
  seatModal.editingId = null
  seatModal.form = { id_loai_ghe: '', ma_ghe: '', tang: 1, trang_thai: 'hoat_dong' }
  seatDeleteModal.show = false
  seatDeleteModal.seat = null
  seatErrors.value = {}
}

const loadSeats = async () => {
  seatModal.loading = true
  try {
    const res = await adminApi.getVehicleSeats(seatModal.vehicleId)
    seatModal.seats = res?.data || []
  } catch (error) {
    showToast(error.response?.data?.message || 'Không tải được danh sách ghế!', 'error')
  } finally {
    seatModal.loading = false
  }
}

const openSeatModal = async (vehicle) => {
  seatModal.vehicleId = vehicle.id
  seatModal.driverId = vehicle.id_tai_xe_chinh || null
  seatModal.vehicleName = vehicle.ten_xe || vehicle.bien_so
  applySeatDirectionFromDriver(seatModal.driverId)
  seatModal.show = true
  resetSeatForm()
  await ensureSeatTypes()
  await loadSeats()
}


const getSeatMapByFloor = () => {
  const visibleSeats = seatModal.seats.filter((seat) => seat.trang_thai !== 'an_ghe')

  const grouped = visibleSeats.reduce((acc, seat) => {
    const floor = Number(seat.tang || 1)
    if (!acc[floor]) acc[floor] = []
    acc[floor].push(seat)
    return acc
  }, {})

  return Object.entries(grouped)
    .sort((a, b) => Number(a[0]) - Number(b[0]))
    .map(([floor, seats]) => ({
      floor: Number(floor),
      seats,
    }))
}

const seatStats = computed(() => {
  const visibleSeats = seatModal.seats.filter((seat) => seat.trang_thai !== 'an_ghe')
  const total = visibleSeats.length
  const active = visibleSeats.filter((seat) => seat.trang_thai === 'hoat_dong').length
  const locked = visibleSeats.filter((seat) => seat.trang_thai === 'bao_tri_hoac_khoa').length
  const booked = visibleSeats.filter((seat) => seat.dang_co_ve).length
  return { total, active, locked, booked }
})

const editingSeatBooked = computed(() => {
  if (!seatModal.editingId) return false
  const s = seatModal.seats.find((x) => x.id === seatModal.editingId)
  return !!s?.dang_co_ve
})

/** Số hàng ghế trên mỗi tầng (1–8), lưu localStorage để giữ khi mở lại */
const SEAT_ROWS_LS_KEY = 'gobus_seat_map_rows_per_floor'
const seatRowsPerFloor = ref(2)
const seatDirection = ref('driver_left')
const SEAT_DIRECTION_LS_PREFIX = 'gobus_seat_direction_driver_'

const getSeatDirectionStorageKey = (driverId) => `${SEAT_DIRECTION_LS_PREFIX}${driverId || 'none'}`

const applySeatDirectionFromDriver = (driverId) => {
  const stored = localStorage.getItem(getSeatDirectionStorageKey(driverId))
  seatDirection.value = stored === 'driver_right' ? 'driver_right' : 'driver_left'
}

const handleSeatDirectionChange = () => {
  if (seatModal.seats.some((s) => s.dang_co_ve)) {
    applySeatDirectionFromDriver(seatModal.driverId)
    showToast('Đã có vé, không thể đổi vị trí tài xế.', 'error')
    return
  }
  localStorage.setItem(getSeatDirectionStorageKey(seatModal.driverId), seatDirection.value)
}

const splitSeatsIntoRows = (seats, numRows) => {
  const list = Array.isArray(seats) ? [...seats] : []
  if (!list.length) return []
  const rows = Math.min(8, Math.max(1, Number(numRows) || 1))
  const perRow = Math.ceil(list.length / rows)
  const out = []
  for (let i = 0; i < list.length; i += perRow) {
    out.push(list.slice(i, i + perRow))
  }
  return out
}

/** Thứ tự mã ghế cố định; trái/phải chỉ đổi nhãn (TX) qua isDriverSeat */
const getDisplayedRow = (row) => {
  if (!Array.isArray(row)) return []
  return [...row]
}

const isDriverSeat = (seat, floorNumber, row, rowIndex) => {
  if (!seat || floorNumber !== 1 || rowIndex !== 0 || !Array.isArray(row) || !row.length) return false
  const driverSeat = seatDirection.value === 'driver_right' ? row[row.length - 1] : row[0]
  return Number(driverSeat?.id) === Number(seat.id)
}

const seatTooltipText = (seat, floorNumber, row, rowIndex) => {
  const roleText = isDriverSeat(seat, floorNumber, row, rowIndex) ? ' · Ghế tài xế' : ''
  const statusText = seat.trang_thai !== 'hoat_dong'
    ? 'Khóa/Bảo trì'
    : seat.dang_co_ve
      ? 'Đã đặt (có vé)'
      : 'Hoạt động'
  return `Click để chọn/sửa · Double-click để sửa nhanh — ${seat.ma_ghe}${roleText} (${statusText})`
}


watch(seatRowsPerFloor, (v) => {
  const n = Math.min(8, Math.max(1, Number(v) || 2))
  if (n !== Number(v)) seatRowsPerFloor.value = n
  localStorage.setItem(SEAT_ROWS_LS_KEY, String(n))
})

const editSeat = (seat) => {
  if (seat?.dang_co_ve) {
    showToast('Ghế đã có vé đặt, không thể sửa hoặc xóa.', 'error')
    return
  }
  seatModal.editingId = seat.id
  seatModal.form = {
    id_loai_ghe: seat.id_loai_ghe || '',
    ma_ghe: seat.ma_ghe || '',
    tang: seat.tang || 1,
    trang_thai: seat.trang_thai || 'hoat_dong',
  }
}

const handleSeatTileClick = (seat) => {
  if (seatModal.saving) return
  editSeat(seat)
}

const submitSeat = async () => {
  if (seatModal.editingId && editingSeatBooked.value) {
    showToast('Ghế đã có vé đặt, không thể cập nhật.', 'error')
    return
  }
  seatErrors.value = {}
  const maGhe = String(seatModal.form.ma_ghe || '').trim()
  if (!maGhe) {
    seatErrors.value = { ma_ghe: ['Vui lòng nhập mã ghế.'] }
    showToast('Vui lòng nhập mã ghế.', 'error')
    return
  }
  if (!seatModal.form.id_loai_ghe) {
    seatErrors.value = { id_loai_ghe: ['Vui lòng chọn loại ghế.'] }
    showToast('Vui lòng chọn loại ghế.', 'error')
    return
  }
  try {
    seatModal.saving = true
    const payload = {
      id_loai_ghe: Number(seatModal.form.id_loai_ghe),
      ma_ghe: String(seatModal.form.ma_ghe || '').trim(),
      tang: Number(seatModal.form.tang),
      trang_thai: String(seatModal.form.trang_thai || 'hoat_dong'),
    }
    if (seatModal.editingId) {
      await adminApi.updateVehicleSeat(seatModal.vehicleId, seatModal.editingId, payload)
      showToast('Cập nhật ghế thành công!', 'success')
    } else {
      await adminApi.createVehicleSeat(seatModal.vehicleId, payload)
      showToast('Thêm ghế thành công!', 'success')
    }
    resetSeatForm()
    await loadSeats()
  } catch (error) {
    const backendErrors = error.response?.data?.errors
    if (backendErrors) {
      seatErrors.value = backendErrors
    }
    const message = backendErrors
      ? Object.values(backendErrors).flat()[0]
      : (error.response?.data?.message || 'Lưu ghế thất bại!')
    showToast(message, 'error')
  } finally {
    seatModal.saving = false
  }
}

const openDeleteSeatModal = () => {
  if (!seatModal.editingId) return
  const seat = seatModal.seats.find((item) => item.id === seatModal.editingId) || null
  if (!seat) {
    showToast('Không tìm thấy ghế để xóa.', 'error')
    return
  }
  if (seat.dang_co_ve) {
    showToast('Ghế đã có vé đặt, không thể xóa.', 'error')
    return
  }
  seatDeleteModal.seat = seat
  seatDeleteModal.show = true
}

const deleteSeat = async (seat) => {
  if (!seat?.id) return
  try {
    await adminApi.deleteVehicleSeat(seatModal.vehicleId, seat.id)
    if (seatModal.editingId === seat.id) resetSeatForm()
    seatDeleteModal.show = false
    seatDeleteModal.seat = null
    showToast('Xóa ghế thành công!', 'success')
    await loadSeats()
  } catch (error) {
    showToast(error.response?.data?.message || 'Không thể xóa ghế!', 'error')
  }
}

const confirmDeleteSeat = async () => {
  if (seatDeleteModal.loading || !seatDeleteModal.seat) return
  try {
    seatDeleteModal.loading = true
    await deleteSeat(seatDeleteModal.seat)
  } finally {
    seatDeleteModal.loading = false
  }
}

watch(
  () => formData.ma_nha_xe,
  async (value, oldValue) => {
    if (!isFormModal.value || value === oldValue) return
    formData.id_tai_xe_chinh = ''
    await ensureDrivers(true)
  }
)

onMounted(() => {
  const raw = localStorage.getItem(SEAT_ROWS_LS_KEY)
  if (raw != null) {
    const n = parseInt(raw, 10)
    if (!Number.isNaN(n)) seatRowsPerFloor.value = Math.min(8, Math.max(1, n))
  }
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
          <BaseInput v-model="searchQuery" placeholder="Tìm biển số, tên xe..." @keyup.enter="handleSearch" />
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
            <BaseButton size="sm" variant="primary" @click="openEditModal(item)">Sửa</BaseButton>
            <BaseButton size="sm" variant="secondary" @click="openSeatModal(item)">Ghế</BaseButton>
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
          <BaseButton size="sm" variant="outline" :disabled="pagination.currentPage <= 1"
            @click="fetchVehicles(pagination.currentPage - 1)">← Trước</BaseButton>

          <span class="page-number">Trang {{ pagination.currentPage }} / {{ pagination.lastPage }}</span>

          <BaseButton size="sm" variant="outline" :disabled="pagination.currentPage >= pagination.lastPage"
            @click="fetchVehicles(pagination.currentPage + 1)">Sau →</BaseButton>
        </div>
      </div>
    </div>

    <BaseModal v-model="isFormModal" :title="isEditMode ? 'Cập Nhật Xe' : 'Thêm Xe Mới'" maxWidth="760px">
      <div class="info-banner">
        <span class="info-icon">ℹ️</span>
        <span v-if="isEditMode">Sau khi cập nhật, kiểm tra lại thông tin. Có thể đổi trạng thái xe bằng nút
          <strong>Duyệt/TT</strong> trên bảng nếu cần.</span>
        <span v-else>Nhập đúng <strong>mã nhà xe</strong> và <strong>loại xe</strong>. Xe mới có thể được tạo ở trạng
          thái
          hoạt động; có thể chỉnh trạng thái sau tại <strong>Duyệt/TT</strong>.</span>
      </div>
      <form id="gobus-admin-vehicle-form" @submit.prevent="submitForm" class="vehicle-form">
        <div class="form-grid">
          <div class="form-group">
            <BaseInput v-model="formData.bien_so" label="Biển số *" placeholder="VD: 51G-12345" required />
            <p v-if="formErrors.bien_so?.[0]" class="field-error">{{ formErrors.bien_so[0] }}</p>
          </div>
          <div class="form-group">
            <BaseInput v-model="formData.ten_xe" label="Tên xe *" placeholder="VD: Xe giường nằm VIP" required />
            <p v-if="formErrors.ten_xe?.[0]" class="field-error">{{ formErrors.ten_xe[0] }}</p>
          </div>

          <div class="form-group">
            <BaseInput v-model="formData.ma_nha_xe" label="Mã nhà xe *" placeholder="VD: NX001" required />
            <p v-if="formErrors.ma_nha_xe?.[0]" class="field-error">{{ formErrors.ma_nha_xe[0] }}</p>
          </div>

          <div class="form-group">
            <label class="base-input-label">Loại xe *</label>
            <select v-model="formData.id_loai_xe" class="custom-input custom-select" required :disabled="loaiXeLoading">
              <option disabled value="">-- Chọn loại xe --</option>
              <option v-for="lx in loaiXeList" :key="lx.id" :value="lx.id">
                {{ lx.ten_loai_xe }} (mặc định {{ lx.so_ghe_mac_dinh }} ghế{{ lx.so_tang > 1 ? ', ' + lx.so_tang + ' tầng' : '' }})
              </option>
            </select>
            <p v-if="formErrors.id_loai_xe?.[0]" class="field-error">{{ formErrors.id_loai_xe[0] }}</p>
          </div>

          <div class="form-group">
            <label class="base-input-label">Tài xế chính</label>
            <select v-model="formData.id_tai_xe_chinh" class="custom-input custom-select" :disabled="driverLoading">
              <option value="">-- Chưa chọn --</option>
              <option v-for="d in driverList" :key="d.id" :value="d.id">
                {{ d.ho_so?.ho_va_ten || d.email }} (ID: {{ d.id }})
              </option>
            </select>
            <p v-if="formErrors.id_tai_xe_chinh?.[0]" class="field-error">{{ formErrors.id_tai_xe_chinh[0] }}</p>
          </div>

          <div class="form-group full-width">
            <label class="base-input-label">Biển nhận dạng</label>
            <textarea v-model="formData.bien_nhan_dang" class="custom-input custom-textarea"
              placeholder="VD: Màu xanh, logo lớn phía hông xe"></textarea>
            <p v-if="formErrors.bien_nhan_dang?.[0]" class="field-error">{{ formErrors.bien_nhan_dang[0] }}</p>
          </div>
        </div>
      </form>

      <template #footer>
        <BaseButton variant="secondary" @click="isFormModal = false">Hủy</BaseButton>
        <BaseButton type="submit" form="gobus-admin-vehicle-form" variant="primary" :loading="formLoading">
          {{ isEditMode ? 'Lưu thay đổi' : 'Thêm xe' }}
        </BaseButton>
      </template>
    </BaseModal>

    <BaseModal v-model="statusModal.show" title="Cập nhật trạng thái xe" maxWidth="560px">
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

        <div v-if="statusModal.canhBaoLoading" class="status-warning-panel muted">Đang tải cảnh báo…</div>
        <div v-else-if="statusModal.canhBao?.cac_dong?.length" class="status-warning-panel" role="alert">
          <p class="status-warning-title">Cảnh báo</p>
          <ul class="status-warning-list">
            <li v-for="(dong, idx) in statusModal.canhBao.cac_dong" :key="idx">{{ dong }}</li>
          </ul>
        </div>
      </div>

      <template #footer>
        <BaseButton variant="secondary" @click="statusModal.show = false">Hủy</BaseButton>
        <BaseButton variant="primary" :loading="statusModal.loading" @click="submitStatus">Lưu trạng thái</BaseButton>
      </template>
    </BaseModal>

    <BaseModal v-model="deleteModal.show" title="Xác nhận xóa xe" maxWidth="520px">
      <div class="delete-body">
        <p>Bạn có chắc muốn xóa xe <strong>{{ deleteModal.bienSo }}</strong>? Hành động này không thể hoàn tác.</p>
        <p class="delete-hint">Nếu xe đang được gán cho chuyến hoặc còn dữ liệu liên quan, hệ thống có thể từ chối xóa.
        </p>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="deleteModal.show = false">Hủy</BaseButton>
        <BaseButton variant="danger" :loading="deleteModal.loading" @click="confirmDelete">Xóa xe</BaseButton>
      </template>
    </BaseModal>

    <BaseModal v-model="seatModal.show" title="Quản lý ghế xe" maxWidth="860px" bodyOverflow="visible">
      <p class="seat-title">Xe: <strong>{{ seatModal.vehicleName }}</strong></p>
      <div class="seat-stats">
        <div class="stat-item">
          <span class="stat-num">{{ seatStats.total }}</span>
          <span class="stat-lbl">Tổng ghế</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">{{ seatStats.active }}</span>
          <span class="stat-lbl">Hoạt động</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">{{ seatStats.booked }}</span>
          <span class="stat-lbl">Đã đặt</span>
        </div>
        <div class="stat-item">
          <span class="stat-num">{{ seatStats.locked }}</span>
          <span class="stat-lbl">Khóa / bảo trì</span>
        </div>
      </div>

      <form class="seat-form" @submit.prevent="submitSeat">
        <BaseInput v-model="seatModal.form.ma_ghe" label="Mã ghế *" placeholder="VD: A01"
          :error="seatFieldError('ma_ghe')" @update:modelValue="onSeatCodeInput" />
        <div class="seat-select-field">
          <label class="base-input-label">Loại ghế *</label>
          <select v-model="seatModal.form.id_loai_ghe" class="custom-select"
            :class="{ 'has-error': !!seatFieldError('id_loai_ghe') }" :disabled="seatTypeLoading"
            @change="clearSeatError('id_loai_ghe')">
            <option disabled value="">-- Chọn loại ghế --</option>
            <option v-for="lg in seatTypeList" :key="lg.id" :value="lg.id">
              {{ lg.ten_loai_ghe }} (ID: {{ lg.id }})
            </option>
          </select>
          <span v-if="seatFieldError('id_loai_ghe')" class="field-error">{{ seatFieldError('id_loai_ghe') }}</span>
        </div>
        <div class="seat-select-field">
          <label class="base-input-label">Tầng</label>
          <select v-model="seatModal.form.tang" class="custom-select" :class="{ 'has-error': !!seatFieldError('tang') }"
            @change="clearSeatError('tang')">
            <option :value="1">Tầng 1</option>
            <option :value="2">Tầng 2</option>
          </select>
          <span v-if="seatFieldError('tang')" class="field-error">{{ seatFieldError('tang') }}</span>
        </div>
        <div class="seat-select-field">
          <label class="base-input-label">Trạng thái ghế</label>
          <select v-model="seatModal.form.trang_thai" class="custom-select"
            :class="{ 'has-error': !!seatFieldError('trang_thai') }" @change="clearSeatError('trang_thai')">
            <option value="hoat_dong">Hoạt động</option>
            <option value="bao_tri_hoac_khoa">Khóa/Bảo trì</option>
          </select>
          <span v-if="seatFieldError('trang_thai')" class="field-error">{{ seatFieldError('trang_thai') }}</span>
        </div>
        <div class="seat-form-actions">
          <BaseButton type="submit" variant="primary" :loading="seatModal.saving" :disabled="editingSeatBooked">
            Lưu
          </BaseButton>
          <BaseButton v-if="seatModal.editingId" type="button" variant="danger" :disabled="editingSeatBooked"
            @click="openDeleteSeatModal">Xóa</BaseButton>
          <BaseButton v-if="seatModal.editingId" type="button" variant="outline" @click="resetSeatForm">Hủy sửa
          </BaseButton>
        </div>
      </form>

      <div v-if="seatModal.loading" class="detail-loading">Đang tải danh sách ghế...</div>
      <template v-else>
        <div v-if="seatModal.seats.length" class="seat-map-wrap">
          <div class="seat-legend-row">
            <div class="seat-legend">
              <span class="legend-item"><span class="seat-dot dot-active"></span> Hoạt động</span>
              <span class="legend-item"><span class="seat-dot dot-booked"></span> Đã đặt</span>
              <span class="legend-item"><span class="seat-dot dot-locked"></span> Khóa / bảo trì</span>
              <span class="legend-item"><span class="seat-dot dot-editing"></span> Ghế đang chỉnh sửa</span>
              <span class="legend-item"><span class="seat-dot dot-driver"></span> Ghế tài xế</span>
            </div>
            <div class="seat-layout-toolbar">
              <span class="seat-layout-label">Số hàng ghế / tầng</span>
              <select v-model.number="seatRowsPerFloor" class="custom-select seat-rows-select">
                <option v-for="n in 8" :key="n" :value="n">{{ n }} hàng</option>
              </select>
              <span class="seat-layout-label">Vị trí ghế tài xế</span>
              <select v-model="seatDirection" class="custom-select seat-dir-select" @change="handleSeatDirectionChange">
                <option value="driver_left">Tài xế bên trái</option>
                <option value="driver_right">Tài xế bên phải</option>
              </select>
            </div>
          </div>

          <div v-for="floor in getSeatMapByFloor()" :key="floor.floor" class="seat-floor-block">
            <h4 class="seat-floor-title">Tầng {{ floor.floor }}</h4>
            <div v-for="(row, ri) in splitSeatsIntoRows(floor.seats, seatRowsPerFloor)" :key="ri" class="seat-row"
              :style="{ '--seat-cols': Math.max(row.length, 1) }">
              <button v-for="seat in getDisplayedRow(row)" :key="seat.id" type="button" class="seat-tile" :class="{
                blocked: seat.trang_thai !== 'hoat_dong',
                booked: seat.trang_thai === 'hoat_dong' && seat.dang_co_ve,
                editing: seatModal.editingId === seat.id,
                driver:
                  isDriverSeat(seat, floor.floor, row, ri) &&
                  seat.trang_thai === 'hoat_dong' &&
                  !seat.dang_co_ve,
              }"
                @click="handleSeatTileClick(seat)" @dblclick.stop="editSeat(seat)">
                {{ seat.ma_ghe }}<span v-if="isDriverSeat(seat, floor.floor, row, ri)"> (TX)</span>
                <span class="seat-tooltip">{{ seatTooltipText(seat, floor.floor, row, ri) }}</span>
              </button>
            </div>
          </div>
        </div>
      </template>
    </BaseModal>
    <BaseModal v-model="seatDeleteModal.show" title="Xác nhận xóa ghế" maxWidth="420px">
      <div class="delete-body">
        <p>Bạn có chắc muốn xóa ghế <strong>{{ seatDeleteModal.seat?.ma_ghe }}</strong> không?</p>
      </div>
      <template #footer>
        <BaseButton variant="secondary" :disabled="seatDeleteModal.loading" @click="seatDeleteModal.show = false">Hủy
        </BaseButton>
        <BaseButton variant="danger" :loading="seatDeleteModal.loading" @click="confirmDeleteSeat">Xóa</BaseButton>
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

.status-approved {
  background: #dcfce7;
  color: #166534;
}

.status-info {
  background: #dbeafe;
  color: #1d4ed8;
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
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  padding: 10px 12px;
  font-size: 13px;
  color: #1e40af;
  margin-bottom: 16px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
  line-height: 1.45;
}

.info-banner .info-icon {
  flex-shrink: 0;
  line-height: 1.2;
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

.delete-hint {
  margin-top: 10px;
  font-size: 13px;
  color: #64748b;
  line-height: 1.45;
}

.status-warning-panel {
  margin-top: 14px;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid #fecaca;
  background: linear-gradient(135deg, #fff7ed 0%, #fff1f2 100%);
  font-size: 13px;
  line-height: 1.5;
  color: #7f1d1d;
}

.status-warning-panel.muted {
  border-color: #e2e8f0;
  background: #f8fafc;
  color: #64748b;
}

.status-warning-title {
  margin: 0 0 8px;
  font-weight: 700;
  font-size: 0.875rem;
  color: #991b1b;
}

.status-warning-list {
  margin: 0;
  padding-left: 1.15rem;
}

.status-warning-list li {
  margin-bottom: 4px;
}

.field-error {
  display: block;
  color: #dc2626;
  font-size: 0.8125rem;
  font-weight: 500;
  margin-top: 4px;
  line-height: 1.35;
}

.seat-form {
  display: grid;
  grid-template-columns: 1.2fr 1fr 0.8fr 1fr auto;
  gap: 10px;
  align-items: start;
  margin-bottom: 16px;
}

.seat-form :deep(.base-input-wrapper) {
  margin-bottom: 0;
}

.seat-select-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 0;
}

.seat-form-actions {
  display: flex;
  flex-wrap: nowrap;
  gap: 8px;
  margin-top: 0;
  padding-top: 1.35rem;
}

.custom-select.has-error {
  border-color: #ef4444 !important;
}

.custom-select.has-error:focus {
  outline: none;
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
}

.detail-loading {
  text-align: center;
  padding: 28px;
  color: #64748b;
}

.seat-title {
  margin: 0 0 10px;
  color: #334155;
}

.seat-map-wrap {
  margin: 12px 0 14px;
  padding: 10px;
}

.seat-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 12px;
}

.stat-item {
  text-align: center;
  padding: 8px 6px;
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-num {
  font-size: 20px;
  font-weight: 800;
  color: #334155;
  line-height: 1;
}

.stat-lbl {
  font-size: 12px;
  color: #64748b;
}

.seat-legend-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.seat-legend {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 6px 12px;
  font-size: 12px;
  color: #334155;
  flex: 1;
  min-width: 0;
}

.legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.seat-dot {
  width: 13px;
  height: 13px;
  border-radius: 4px;
  display: inline-block;
}

.dot-active {
  background: #dcfce7;
  border: 1px solid #86efac;
}

/* Đã đặt: cam — tách biệt khóa (xám) */
.dot-booked {
  background: #ffedd5;
  border: 1px solid #ea580c;
}

.dot-locked {
  background: #e2e8f0;
  border: 1px solid #475569;
}

.dot-editing {
  background: #dbeafe;
  border: 1px solid #93c5fd;
}

.dot-driver {
  background: #fef3c7;
  border: 1px solid #fcd34d;
}

.seat-layout-toolbar {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  margin-bottom: 0;
}

.seat-layout-toolbar .seat-layout-label {
  font-size: 12px;
  font-weight: 600;
  color: #334155;
  line-height: 1.2;
  white-space: nowrap;
}

.seat-layout-toolbar .custom-select {
  width: auto;
  height: 30px;
  min-height: 30px;
  padding: 2px 8px;
  font-size: 12px;
  line-height: 1.2;
  border-radius: 6px;
}

.seat-rows-select {
  width: auto;
  min-width: 100px;
  max-width: 160px;
}

.seat-dir-select {
  width: auto;
  min-width: 128px;
  max-width: 190px;
}

.seat-floor-block {
  margin-bottom: 14px;
}

.seat-floor-title {
  font-size: 12px;
  color: #64748b;
  margin: 0 0 8px;
  font-weight: 600;
}

.seat-row {
  display: grid;
  grid-template-columns: repeat(var(--seat-cols), minmax(0, 1fr));
  gap: 7px;
  margin-bottom: 10px;
}

.seat-floor-block .seat-row:last-child {
  margin-bottom: 0;
}

.seat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(58px, 1fr));
  gap: 7px;
}

.seat-tile {
  position: relative;
  overflow: visible;
  width: 100%;
  border: 1px solid #86efac;
  background: #dcfce7;
  color: #166534;
  border-radius: 9px;
  padding: 8px 4px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.seat-tile:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 10px rgba(22, 163, 74, 0.2);
}

.seat-tooltip {
  position: absolute;
  left: 50%;
  bottom: calc(100% + 8px);
  transform: translateX(-50%) translateY(4px);
  z-index: 12050;
  min-width: 220px;
  max-width: 320px;
  padding: 6px 8px;
  border-radius: 8px;
  background: #0f172a;
  color: #f8fafc;
  font-size: 11px;
  line-height: 1.4;
  white-space: normal;
  text-align: left;
  pointer-events: none;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.15s ease, transform 0.15s ease;
  box-shadow: 0 8px 24px rgba(2, 6, 23, 0.32);
}

.seat-tooltip::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border-width: 6px;
  border-style: solid;
  border-color: #0f172a transparent transparent transparent;
}

.seat-tile:hover .seat-tooltip {
  opacity: 1;
  visibility: visible;
  transform: translateX(-50%) translateY(0);
}

.seat-tile.blocked {
  border-color: #64748b;
  background: #f1f5f9;
  color: #1e293b;
}

.seat-tile.blocked:hover {
  box-shadow: 0 4px 10px rgba(71, 85, 105, 0.25);
}

.seat-tile.booked {
  border-color: #fb923c;
  background: #fff7ed;
  color: #c2410c;
  cursor: not-allowed;
}

.seat-tile.booked:hover {
  box-shadow: 0 4px 10px rgba(234, 88, 12, 0.2);
  transform: none;
}

.seat-tile.editing {
  border-color: #60a5fa;
  background: #dbeafe;
  color: #1d4ed8;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}

.seat-tile.driver {
  border-color: #f59e0b;
  background: #fef3c7;
  color: #92400e;
  box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.25);
}

.seat-tile.dragging {
  opacity: 0.55;
  transform: scale(0.98);
}

.seat-driver-tile {
  border-color: #f59e0b;
  background: #fef3c7;
  color: #92400e;
  cursor: default;
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

  .pagination-container {
    flex-direction: column;
    align-items: flex-start;
  }

  .seat-form {
    grid-template-columns: 1fr;
    align-items: stretch;
  }

  .seat-form-actions {
    flex-wrap: wrap;
    padding-top: 0;
  }

  .seat-stats {
    grid-template-columns: 1fr;
  }
}
</style>
