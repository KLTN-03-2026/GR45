<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import operatorApi from '@/api/operatorApi'
import BaseTable from '@/components/common/BaseTable.vue'
import BaseButton from '@/components/common/BaseButton.vue'
import BaseInput from '@/components/common/BaseInput.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import BaseToast from '@/components/common/BaseToast.vue'

// ─── Toast ────────────────────────────────────────────────────────────────────
const toast = reactive({ visible: false, message: '', type: 'success' })
const showToast = (message, type = 'success') => {
  toast.message = message
  toast.type = type
  toast.visible = true
  setTimeout(() => { toast.visible = false }, 3500)
}

// ─── Danh sách xe ─────────────────────────────────────────────────────────────
const loading = ref(false)
const vehicles = ref([])
const searchQuery = ref('')
const filterStatus = ref('')
const pagination = reactive({ currentPage: 1, perPage: 15, total: 0, lastPage: 1 })

const tableColumns = [
  { key: 'id', label: 'ID' },
  { key: 'bien_so', label: 'Biển số' },
  { key: 'ten_xe', label: 'Tên xe' },
  { key: 'loai_xe', label: 'Loại xe' },
  { key: 'so_ghe', label: 'Số ghế / Tầng' },
  { key: 'trang_thai', label: 'Trạng thái' },
  { key: 'actions', label: 'Hành động' },
]

const getStatus = (status) => {
  if (status === 'hoat_dong') return { text: 'Hoạt động', cls: 'status-active' }
  if (status === 'bao_tri') return { text: 'Bảo trì', cls: 'status-info' }
  if (status === 'cho_duyet') return { text: 'Chờ duyệt', cls: 'status-pending' }
  return { text: 'Không rõ', cls: '' }
}

const getSeatStatus = (status) => {
  if (status === 'hoat_dong') return { text: 'Hoạt động', cls: 'seat-ok' }
  if (status === 'bao_tri_hoac_khoa') return { text: 'Bảo trì', cls: 'seat-broken' }
  return { text: '', cls: '' }
}

const extractListAndPage = (response) => {
  const d = response?.data
  if (Array.isArray(d?.data?.data)) return { listData: d.data.data, pageData: d.data }
  if (Array.isArray(d?.data)) return { listData: d.data, pageData: d }
  if (Array.isArray(d)) return { listData: d, pageData: {} }
  return { listData: [], pageData: {} }
}

const fetchVehicles = async (page = 1) => {
  try {
    loading.value = true
    const res = await operatorApi.getVehicles({
      page,
      per_page: pagination.perPage,
      search: searchQuery.value || undefined,
      trang_thai: filterStatus.value || undefined,
    })
    const { listData, pageData } = extractListAndPage(res)
    vehicles.value = listData
    pagination.currentPage = pageData.current_page || page
    pagination.perPage = pageData.per_page || pagination.perPage
    pagination.total = pageData.total || listData.length
    pagination.lastPage = pageData.last_page || 1
  } catch {
    showToast('Không thể tải danh sách xe!', 'error')
  } finally {
    loading.value = false
  }
}

// ─── Danh mục ─────────────────────────────────────────────────────────────────
const loaiXeList = ref([])
const loaiGheList = ref([])

const fetchMeta = async () => {
  try {
    const [lx, lg] = await Promise.all([
      operatorApi.getLoaiXe(),
      operatorApi.getLoaiGhe(),
    ])
    loaiXeList.value = lx?.data?.data ?? lx?.data ?? []
    loaiGheList.value = lg?.data?.data ?? lg?.data ?? []
  } catch {
    showToast('Không thể tải danh mục. Vui lòng tải lại trang.', 'error')
  }
}

// ─── Modal Tạo xe (2 bước) ───────────────────────────────────────────────────
const createModal = ref(false)
const createStep = ref(1) // 1 = thông tin, 2 = sơ đồ ghế
const createLoading = ref(false)

const selectedLoaiXe = computed(() =>
  loaiXeList.value.find(lx => lx.id === Number(createForm.id_loai_xe)) || null
)

const createForm = reactive({
  bien_so: '',
  ten_xe: '',
  id_loai_xe: '',
  so_ghe_thuc_te: '',
  so_tang: 1,
  tien_nghi: '',
  bien_nhan_dang: '',
  id_tai_xe_chinh: '',
})

// Khi chọn loại xe → auto-fill so_tang và so_ghe_thuc_te
watch(() => createForm.id_loai_xe, (val) => {
  const lx = loaiXeList.value.find(x => x.id === Number(val))
  if (lx) {
    createForm.so_tang = lx.so_tang ?? 1
    createForm.so_ghe_thuc_te = lx.so_ghe_mac_dinh ?? ''
    createForm.tien_nghi = lx.tien_nghi ?? ''
    // Reset và generate ghế
    generateGhes()
  }
})

watch(() => createForm.so_tang, () => generateGhes())
watch(() => createForm.so_ghe_thuc_te, () => generateGhes())

// ─── Sơ đồ ghế tạm thời ──────────────────────────────────────────────────────
const ghes = ref([]) // [{ ma_ghe, tang, id_loai_ghe }]

const defaultLoaiGheId = computed(() => loaiGheList.value[0]?.id ?? '')

/**
 * Sinh danh sách ghế tự động theo số tầng & số ghế.
 * Nếu 1 tầng: A01→A{n}
 * Nếu 2 tầng: chia đều, tầng 1 = A01…, tầng 2 = B01…
 */
const generateGhes = () => {
  const total = parseInt(createForm.so_ghe_thuc_te) || 0
  const tang = parseInt(createForm.so_tang) || 1

  if (total === 0) { ghes.value = []; return }

  const result = []
  const perFloor = Math.ceil(total / tang)

  for (let t = 1; t <= tang; t++) {
    const prefix = String.fromCharCode(64 + t) // A, B
    const count = t < tang ? perFloor : total - perFloor * (tang - 1)
    for (let i = 1; i <= count; i++) {
      result.push({
        ma_ghe: `${prefix}${String(i).padStart(2, '0')}`,
        tang: t,
        id_loai_ghe: defaultLoaiGheId.value || '',
      })
    }
  }
  ghes.value = result
}

const ghesByFloor = computed(() => {
  const map = {}
  for (const g of ghes.value) {
    if (!map[g.tang]) map[g.tang] = []
    map[g.tang].push(g)
  }
  return map
})

const addGhe = (tang) => {
  const floor = ghes.value.filter(g => g.tang === tang)
  const prefix = String.fromCharCode(64 + tang)
  const idx = floor.length + 1
  ghes.value.push({
    ma_ghe: `${prefix}${String(idx).padStart(2, '0')}`,
    tang,
    id_loai_ghe: defaultLoaiGheId.value || '',
  })
}

const removeGhe = (index) => {
  ghes.value.splice(index, 1)
}

const openCreateModal = () => {
  createStep.value = 1
  Object.assign(createForm, {
    bien_so: '', ten_xe: '', id_loai_xe: '', so_ghe_thuc_te: '',
    so_tang: 1, tien_nghi: '', bien_nhan_dang: '', id_tai_xe_chinh: '',
  })
  ghes.value = []
  createModal.value = true
}

const goToStep2 = () => {
  if (!createForm.bien_so.trim()) { showToast('Vui lòng nhập biển số xe.', 'error'); return }
  if (!createForm.ten_xe.trim()) { showToast('Vui lòng nhập tên xe.', 'error'); return }
  if (!createForm.id_loai_xe) { showToast('Vui lòng chọn loại xe.', 'error'); return }
  if (!createForm.so_ghe_thuc_te || Number(createForm.so_ghe_thuc_te) < 1) {
    showToast('Vui lòng nhập số ghế hợp lệ (ít nhất 1).', 'error'); return
  }
  if (ghes.value.length === 0) generateGhes()
  createStep.value = 2
}

const submitCreate = async () => {
  if (createLoading.value) return
  createLoading.value = true

  try {
    // Validate bước 2
    if (ghes.value.length === 0) {
      showToast('Sơ đồ ghế không được để trống.', 'error');
      createLoading.value = false;
      return
    }
    const maGhes = ghes.value.map(g => g.ma_ghe.trim().toUpperCase())
    if (new Set(maGhes).size !== maGhes.length) {
      showToast('Có mã ghế bị trùng. Vui lòng kiểm tra lại.', 'error');
      createLoading.value = false;
      return
    }
    if (ghes.value.some(g => !g.id_loai_ghe)) {
      showToast('Vui lòng chọn loại ghế cho tất cả ghế.', 'error');
      createLoading.value = false;
      return
    }
    if (ghes.value.length !== Number(createForm.so_ghe_thuc_te)) {
      showToast(`Số ghế trong sơ đồ (${ghes.value.length}) không khớp với số ghế thực tế (${createForm.so_ghe_thuc_te}).`, 'error');
      createLoading.value = false;
      return
    }

    const payload = {
      bien_so: createForm.bien_so.trim(),
      ten_xe: createForm.ten_xe.trim(),
      id_loai_xe: Number(createForm.id_loai_xe),
      so_ghe_thuc_te: Number(createForm.so_ghe_thuc_te),
      so_tang: Number(createForm.so_tang),
      tien_nghi: createForm.tien_nghi || undefined,
      bien_nhan_dang: createForm.bien_nhan_dang || undefined,
      id_tai_xe_chinh: createForm.id_tai_xe_chinh ? Number(createForm.id_tai_xe_chinh) : undefined,
      ghes: ghes.value.map(g => ({
        ma_ghe: g.ma_ghe.trim().toUpperCase(),
        tang: Number(g.tang),
        id_loai_ghe: Number(g.id_loai_ghe),
      })),
    }
    await operatorApi.createVehicle(payload)
    showToast('Thêm xe thành công! Đang chờ Admin duyệt.', 'success')
    createModal.value = false
    fetchVehicles(1)
  } catch (err) {
    const errors = err.response?.data?.errors
    const msg = errors
      ? Object.values(errors).flat()[0]
      : (err.response?.data?.message || 'Thêm xe thất bại!')
    showToast(msg, 'error')
  } finally {
    createLoading.value = false
  }
}

// ─── Modal Sửa xe ─────────────────────────────────────────────────────────────
const editModal = ref(false)
const editLoading = ref(false)
const currentEditId = ref(null)
const editTab = ref('basic') // 'basic', 'images', 'docs'

const editForm = reactive({
  bien_so: '',
  ten_xe: '',
  bien_nhan_dang: '',
  tien_nghi: '',
  id_tai_xe_chinh: '',
  // Hồ sơ xe
  so_dang_kiem: '',
  ngay_dang_kiem: '',
  ngay_het_han_dang_kiem: '',
  so_bao_hiem: '',
  ngay_hieu_luc_bao_hiem: '',
  ngay_het_han_bao_hiem: '',
  ghi_chu: '',
})

// File được chọn để upload
const editFiles = reactive({
  hinh_xe_truoc: null,
  hinh_xe_sau: null,
  hinh_bien_so: null,
  hinh_dang_kiem: null,
  hinh_bao_hiem: null,
})

// Preview URL (ảnh đã có hoặc ảnh vừa chọn)
const editPreviews = reactive({
  hinh_xe_truoc: '',
  hinh_xe_sau: '',
  hinh_bien_so: '',
  hinh_dang_kiem: '',
  hinh_bao_hiem: '',
})

const openEditModal = (vehicle) => {
  currentEditId.value = vehicle.id
  editTab.value = 'basic'

  Object.assign(editForm, {
    bien_so: vehicle.bien_so || '',
    ten_xe: vehicle.ten_xe || '',
    bien_nhan_dang: vehicle.bien_nhan_dang || '',
    tien_nghi: vehicle.thong_tin_cai_dat?.tien_nghi || '',
    id_tai_xe_chinh: vehicle.id_tai_xe_chinh || '',

    so_dang_kiem: vehicle.ho_so_xe?.so_dang_kiem || '',
    ngay_dang_kiem: vehicle.ho_so_xe?.ngay_dang_kiem || '',
    ngay_het_han_dang_kiem: vehicle.ho_so_xe?.ngay_het_han_dang_kiem || '',
    so_bao_hiem: vehicle.ho_so_xe?.so_bao_hiem || '',
    ngay_hieu_luc_bao_hiem: vehicle.ho_so_xe?.ngay_hieu_luc_bao_hiem || '',
    ngay_het_han_bao_hiem: vehicle.ho_so_xe?.ngay_het_han_bao_hiem || '',
    ghi_chu: vehicle.ho_so_xe?.ghi_chu || '',
  })

  // Reset files
  Object.keys(editFiles).forEach(key => editFiles[key] = null)

  // Gán preview từ dữ liệu hiên có
  Object.assign(editPreviews, {
    hinh_xe_truoc: vehicle.ho_so_xe?.hinh_xe_truoc || '',
    hinh_xe_sau: vehicle.ho_so_xe?.hinh_xe_sau || '',
    hinh_bien_so: vehicle.ho_so_xe?.hinh_bien_so || '',
    hinh_dang_kiem: vehicle.ho_so_xe?.hinh_dang_kiem || '',
    hinh_bao_hiem: vehicle.ho_so_xe?.hinh_bao_hiem || '',
  })
  
  editModal.value = true
}

const handleFileChange = (e, field) => {
  const file = e.target.files[0]
  if (!file) {
    editFiles[field] = null
    return
  }
  editFiles[field] = file
  // Preview
  const reader = new FileReader()
  reader.onload = (ev) => {
    editPreviews[field] = ev.target.result
  }
  reader.readAsDataURL(file)
}

const submitEdit = async () => {
  if (editLoading.value) return
  if (!editForm.bien_so.trim()) { 
    showToast('Biển số không được để trống.', 'error'); 
    editTab.value = 'basic';
    return;
  }
  
  try {
    editLoading.value = true

    // 1. Cập nhật thông tin cơ bản
    const basicPayload = {
      bien_so: editForm.bien_so.trim(),
      ten_xe: editForm.ten_xe.trim() || undefined,
      bien_nhan_dang: editForm.bien_nhan_dang || undefined,
      tien_nghi: editForm.tien_nghi || undefined,
      id_tai_xe_chinh: editForm.id_tai_xe_chinh ? Number(editForm.id_tai_xe_chinh) : undefined,
    }
    await operatorApi.updateVehicle(currentEditId.value, basicPayload)

    // 2. Cập nhật Hồ sơ xe + Hình ảnh
    const formData = new FormData()
    const textFields = ['so_dang_kiem', 'ngay_dang_kiem', 'ngay_het_han_dang_kiem', 'so_bao_hiem', 'ngay_hieu_luc_bao_hiem', 'ngay_het_han_bao_hiem', 'ghi_chu']
    textFields.forEach(f => {
      if (editForm[f]) formData.append(f, editForm[f])
    })
    const imageFields = ['hinh_xe_truoc', 'hinh_xe_sau', 'hinh_bien_so', 'hinh_dang_kiem', 'hinh_bao_hiem']
    let hasHoSoUpdate = textFields.some(f => editForm[f]) || imageFields.some(f => editFiles[f])
    
    let docRes = null
    if (hasHoSoUpdate) {
      imageFields.forEach(f => {
        if (editFiles[f]) formData.append(f, editFiles[f])
      })
      docRes = await operatorApi.updateVehicleDocument(currentEditId.value, formData)
    }

    const docMsg = docRes?.message ? ' ' + docRes.message : ''
    showToast('Cập nhật xe thành công!' + docMsg, 'success')
    editModal.value = false
    fetchVehicles(pagination.currentPage)
  } catch (err) {
    const errors = err.response?.data?.errors
    const msg = errors
      ? Object.values(errors).flat()[0]
      : (err.response?.data?.message || 'Cập nhật thất bại!')
    showToast(msg, 'error')
  } finally {
    editLoading.value = false
  }
}

// ─── Modal sơ đồ ghế ──────────────────────────────────────────────────────────
const seatModal = reactive({ show: false, loading: false, data: null, updatingId: null })

const openSeatModal = async (vehicle) => {
  seatModal.show = true
  seatModal.loading = true
  seatModal.data = null
  try {
    const res = await operatorApi.getVehicleSeats(vehicle.id)
    seatModal.data = res?.data?.data ?? res?.data ?? null
  } catch {
    showToast('Không thể tải sơ đồ ghế!', 'error')
    seatModal.show = false
  } finally {
    seatModal.loading = false
  }
}

const toggleSeatStatus = async (vehicleId, ghe) => {
  const newStatus = ghe.trang_thai === 'hoat_dong' ? 'bao_tri_hoac_khoa' : 'hoat_dong'
  seatModal.updatingId = ghe.id
  try {
    await operatorApi.updateSeatStatus(vehicleId, ghe.id, { trang_thai: newStatus })
    ghe.trang_thai = newStatus
    const label = newStatus === 'bao_tri_hoac_khoa' ? 'Bảo trì' : 'Hoạt động'
    showToast(`Ghế ${ghe.ma_ghe} → ${label}`, 'success')
  } catch (err) {
    showToast(err.response?.data?.message || 'Cập nhật thất bại!', 'error')
  } finally {
    seatModal.updatingId = null
  }
}

// ─── Chi tiết xe ──────────────────────────────────────────────────────────────
const detailModal = reactive({ show: false, loading: false, data: null })

const openDetailModal = async (vehicle) => {
  detailModal.show = true
  detailModal.loading = true
  detailModal.data = null
  try {
    const res = await operatorApi.getVehicleDetails(vehicle.id)
    detailModal.data = res?.data?.data ?? res?.data ?? null
  } catch {
    showToast('Không thể tải chi tiết xe!', 'error')
    detailModal.show = false
  } finally {
    detailModal.loading = false
  }
}

onMounted(async () => {
  await fetchMeta()
  fetchVehicles()
})
</script>

<template>
  <div class="pt-page">
    <BaseToast :visible="toast.visible" :message="toast.message" :type="toast.type" />

    <!-- Header -->
    <div class="pt-header">
      <div>
        <h1 class="pt-title">Quản Lý Phương Tiện</h1>
        <p class="pt-sub">Thêm xe với sơ đồ ghế. Mọi thay đổi sẽ về trạng thái <strong>Chờ Admin duyệt</strong>.</p>
      </div>
      <BaseButton variant="primary" @click="openCreateModal">+ Thêm Xe Mới</BaseButton>
    </div>

    <!-- Filter -->
    <div class="pt-filter">
      <div class="filter-inner">
        <div class="search-wrap">
          <BaseInput v-model="searchQuery" placeholder="Tìm biển số, tên xe..." @keyup.enter="fetchVehicles(1)" />
          <BaseButton variant="secondary" @click="fetchVehicles(1)">Tìm</BaseButton>
        </div>
        <div class="filter-group">
          <label class="flabel">Trạng thái</label>
          <select v-model="filterStatus" class="fselect" @change="fetchVehicles(1)">
            <option value="">Tất cả</option>
            <option value="hoat_dong">Hoạt động</option>
            <option value="bao_tri">Bảo trì</option>
            <option value="cho_duyet">Chờ duyệt</option>
          </select>
        </div>
        <BaseButton variant="outline" @click="() => { searchQuery = ''; filterStatus = ''; fetchVehicles(1) }">Đặt lại</BaseButton>
      </div>
    </div>

    <!-- Table -->
    <div class="pt-table-card">
      <BaseTable :columns="tableColumns" :data="vehicles" :loading="loading">
        <template #cell(bien_so)="{ value }">
          <span class="chip-code">{{ value }}</span>
        </template>

        <template #cell(ten_xe)="{ item }">
          <div class="name-col">
            <strong>{{ item.ten_xe }}</strong>
            <span v-if="item.bien_nhan_dang" class="name-sub">{{ item.bien_nhan_dang }}</span>
          </div>
        </template>

        <template #cell(loai_xe)="{ item }">
          <span>{{ item.loai_xe?.ten_loai_xe || '—' }}</span>
        </template>

        <template #cell(so_ghe)="{ item }">
          <div class="ghe-col">
            <span class="ghe-badge">{{ item.so_ghe_thuc_te || 0 }} ghế</span>
            <span class="tang-badge">{{ item.thong_tin_cai_dat?.so_tang ?? 1 }} tầng</span>
          </div>
        </template>

        <template #cell(trang_thai)="{ value }">
          <span :class="['status-badge', getStatus(value).cls]">{{ getStatus(value).text }}</span>
        </template>

        <template #cell(actions)="{ item }">
          <div class="action-row">
            <BaseButton size="sm" variant="outline" @click="openDetailModal(item)">Chi tiết</BaseButton>
            <BaseButton size="sm" variant="secondary" @click="openSeatModal(item)">🪑 Sơ đồ ghế</BaseButton>
            <BaseButton size="sm" variant="primary" @click="openEditModal(item)">Sửa</BaseButton>
          </div>
        </template>
      </BaseTable>

      <!-- Phân trang -->
      <div class="pagination">
        <div class="page-left">
          <span>Hiển thị:</span>
          <select v-model="pagination.perPage" class="fselect per-sel" @change="fetchVehicles(1)">
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
          </select>
          <span>dòng/trang</span>
          <span v-if="pagination.total > 0" class="total-txt">(Tổng: {{ pagination.total }} xe)</span>
        </div>
        <div class="page-ctrl">
          <BaseButton size="sm" variant="outline" :disabled="pagination.currentPage <= 1" @click="fetchVehicles(pagination.currentPage - 1)">← Trước</BaseButton>
          <span class="page-num">Trang {{ pagination.currentPage }} / {{ pagination.lastPage }}</span>
          <BaseButton size="sm" variant="outline" :disabled="pagination.currentPage >= pagination.lastPage" @click="fetchVehicles(pagination.currentPage + 1)">Sau →</BaseButton>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         MODAL: TẠO XE MỚI (2 bước)
    ═══════════════════════════════════════════════════════ -->
    <BaseModal v-model="createModal" title="Thêm Xe Mới" maxWidth="820px">
      <!-- Step indicator -->
      <div class="step-bar">
        <div :class="['step', createStep === 1 ? 'step-active' : 'step-done']">
          <span class="step-dot">{{ createStep > 1 ? '✓' : '1' }}</span>
          <span>Thông tin xe</span>
        </div>
        <div class="step-line" />
        <div :class="['step', createStep === 2 ? 'step-active' : '']">
          <span class="step-dot">2</span>
          <span>Sơ đồ ghế</span>
        </div>
      </div>

      <!-- Bước 1 -->
      <div v-if="createStep === 1" class="form-grid-2">
        <div class="form-group">
          <label class="flabel">Biển số xe *</label>
          <input v-model="createForm.bien_so" class="finput" placeholder="VD: 51G-12345" required />
        </div>
        <div class="form-group">
          <label class="flabel">Tên xe *</label>
          <input v-model="createForm.ten_xe" class="finput" placeholder="VD: Xe giường nằm VIP" required />
        </div>

        <div class="form-group full-w">
          <label class="flabel">Loại xe *</label>
          <select v-model="createForm.id_loai_xe" class="finput">
            <option value="">-- Chọn loại xe --</option>
            <option v-for="lx in loaiXeList" :key="lx.id" :value="lx.id">
              {{ lx.ten_loai_xe }} ({{ lx.so_tang }} tầng, {{ lx.so_ghe_mac_dinh }} ghế)
            </option>
          </select>
          <span v-if="selectedLoaiXe" class="hint">
            Đã auto-fill: {{ selectedLoaiXe.so_tang }} tầng, {{ selectedLoaiXe.so_ghe_mac_dinh }} ghế mặc định
          </span>
        </div>

        <div class="form-group">
          <label class="flabel">Số tầng *</label>
          <select v-model="createForm.so_tang" class="finput">
            <option :value="1">1 tầng</option>
            <option :value="2">2 tầng</option>
          </select>
        </div>
        <div class="form-group">
          <label class="flabel">Số ghế thực tế *</label>
          <input v-model="createForm.so_ghe_thuc_te" class="finput" type="number" min="1" max="100" placeholder="VD: 40" required />
        </div>

        <div class="form-group full-w">
          <label class="flabel">Tiện nghi trên xe</label>
          <input v-model="createForm.tien_nghi" class="finput" placeholder="VD: Wifi, máy lạnh, TV, cổng sạc USB" />
        </div>
        <div class="form-group full-w">
          <label class="flabel">Biển nhận dạng xe</label>
          <textarea v-model="createForm.bien_nhan_dang" class="finput ftextarea" placeholder="VD: Màu xanh lá, logo lớn ở hai bên hông xe" />
        </div>
        <div class="form-group">
          <label class="flabel">ID Tài xế chính</label>
          <input v-model="createForm.id_tai_xe_chinh" class="finput" type="number" min="1" placeholder="Tùy chọn" />
        </div>
      </div>

      <!-- Bước 2 -->
      <div v-else class="step2-wrap">
        <div class="seat-info-bar">
          <span>🚌 <strong>{{ createForm.ten_xe }}</strong></span>
          <span class="seat-count-badge">{{ ghes.length }} / {{ createForm.so_ghe_thuc_te }} ghế</span>
          <span :class="ghes.length === Number(createForm.so_ghe_thuc_te) ? 'count-ok' : 'count-err'">
            {{ ghes.length === Number(createForm.so_ghe_thuc_te) ? '✓ Đúng số ghế' : '⚠ Chưa khớp số ghế' }}
          </span>
        </div>

        <div v-for="t in Number(createForm.so_tang)" :key="t" class="floor-block">
          <div class="floor-header">
            <span class="floor-label">Tầng {{ t }}</span>
            <span class="floor-count">{{ (ghesByFloor[t] || []).length }} ghế</span>
            <button class="btn-add-seat" @click="addGhe(t)">+ Thêm ghế</button>
          </div>

          <div class="seat-grid">
            <div
              v-for="(ghe, idx) in ghes.filter(g => g.tang === t)"
              :key="idx"
              class="seat-card"
            >
              <div class="seat-preview" :class="ghe.id_loai_ghe == loaiGheList[0]?.id ? 'seat-norm' : 'seat-vip'">
                {{ ghe.ma_ghe }}
              </div>
              <div class="seat-fields">
                <input
                  v-model="ghe.ma_ghe"
                  class="seat-input"
                  placeholder="Mã ghế"
                  @input="ghe.ma_ghe = ghe.ma_ghe.toUpperCase()"
                />
                <select v-model="ghe.id_loai_ghe" class="seat-input">
                  <option value="">-- Loại ghế --</option>
                  <option v-for="lg in loaiGheList" :key="lg.id" :value="lg.id">
                    {{ lg.ten_loai_ghe }} (x{{ lg.he_so_gia }})
                  </option>
                </select>
              </div>
              <button
                class="seat-remove"
                @click="removeGhe(ghes.findIndex(g => g === ghe))"
                title="Xóa ghế"
              >✕</button>
            </div>
          </div>
        </div>

        <div class="seat-tip">
          💡 Mã ghế phải duy nhất (VD: A01, B12). Sau khi tạo xe, sơ đồ ghế <strong>không thể thay đổi</strong>.
        </div>
      </div>

      <template #footer>
        <template v-if="createStep === 1">
          <BaseButton variant="secondary" @click="createModal = false">Hủy</BaseButton>
          <BaseButton variant="primary" @click="goToStep2">Tiếp theo → Cấu hình ghế</BaseButton>
        </template>
        <template v-else>
          <BaseButton variant="secondary" @click="createStep = 1">← Quay lại</BaseButton>
          <BaseButton variant="primary" :loading="createLoading" @click="submitCreate">
            🚌 Tạo xe & sơ đồ ghế
          </BaseButton>
        </template>
      </template>
    </BaseModal>

    <!-- ═══════════════════════════════════════════════════════
         MODAL: SỬA XE (chỉ ngoại hình)
    ═══════════════════════════════════════════════════════ -->
    <BaseModal v-model="editModal" title="Cập Nhật Thông Tin & Hồ Sơ Xe" maxWidth="750px">
      <!-- Tabs header -->
      <div class="edit-tabs">
        <button :class="['etab', editTab === 'basic' ? 'etab-active' : '']" @click="editTab = 'basic'">1. Thông tin chung</button>
        <button :class="['etab', editTab === 'images' ? 'etab-active' : '']" @click="editTab = 'images'">2. Hình ảnh xe</button>
        <button :class="['etab', editTab === 'docs' ? 'etab-active' : '']" @click="editTab = 'docs'">3. Giấy tờ</button>
      </div>

      <!-- Tab 1: Thông tin cơ bản -->
      <div v-show="editTab === 'basic'" class="tab-pane">
        <div class="info-banner mb-3">
          ℹ️ Sơ đồ ghế <strong>không thể thay đổi</strong> sau khi xe được tạo. Bạn chỉ có thể cập nhật thông tin và hồ sơ xe.
        </div>
        <div class="form-grid-2">
          <div class="form-group">
            <label class="flabel">Biển số xe *</label>
            <input v-model="editForm.bien_so" class="finput" required />
          </div>
          <div class="form-group">
            <label class="flabel">Tên xe</label>
            <input v-model="editForm.ten_xe" class="finput" />
          </div>
          <div class="form-group full-w">
            <label class="flabel">Tiện nghi trên xe</label>
            <input v-model="editForm.tien_nghi" class="finput" placeholder="VD: Wifi, máy lạnh, cổng sạc" />
          </div>
          <div class="form-group full-w">
            <label class="flabel">Biển nhận dạng xe</label>
            <textarea v-model="editForm.bien_nhan_dang" class="finput ftextarea" placeholder="VD: Màu xanh lá, logo lớn ở hai bên hông xe" />
          </div>
          <div class="form-group">
            <label class="flabel">ID Tài xế chính</label>
            <input v-model="editForm.id_tai_xe_chinh" class="finput" type="number" min="1" placeholder="Tùy chọn" />
          </div>
        </div>
      </div>

      <!-- Tab 2: Hình ảnh xe -->
      <div v-show="editTab === 'images'" class="tab-pane">
        <div class="form-grid-2">
          <!-- Ảnh trước -->
          <div class="form-group">
            <label class="flabel">Ảnh xe phía trước</label>
            <div class="upload-box" @click="$refs.hxTruoc.click()">
              <img v-if="editPreviews.hinh_xe_truoc" :src="editPreviews.hinh_xe_truoc" class="up-img" />
              <div v-else class="up-placeholder">+ Chọn ảnh</div>
            </div>
            <input type="file" ref="hxTruoc" class="hidden-input" accept="image/png, image/jpeg, image/webp" @change="(e) => handleFileChange(e, 'hinh_xe_truoc')" />
          </div>
          
          <!-- Ảnh sau -->
          <div class="form-group">
            <label class="flabel">Ảnh xe phía sau</label>
            <div class="upload-box" @click="$refs.hxSau.click()">
              <img v-if="editPreviews.hinh_xe_sau" :src="editPreviews.hinh_xe_sau" class="up-img" />
              <div v-else class="up-placeholder">+ Chọn ảnh</div>
            </div>
            <input type="file" ref="hxSau" class="hidden-input" accept="image/png, image/jpeg, image/webp" @change="(e) => handleFileChange(e, 'hinh_xe_sau')" />
          </div>

          <!-- Ảnh biển số -->
          <div class="form-group">
            <label class="flabel">Ảnh chụp rõ biển số</label>
            <div class="upload-box" @click="$refs.hBien.click()">
              <img v-if="editPreviews.hinh_bien_so" :src="editPreviews.hinh_bien_so" class="up-img" />
              <div v-else class="up-placeholder">+ Chọn ảnh</div>
            </div>
            <input type="file" ref="hBien" class="hidden-input" accept="image/png, image/jpeg, image/webp" @change="(e) => handleFileChange(e, 'hinh_bien_so')" />
          </div>
        </div>
      </div>

      <!-- Tab 3: Giấy tờ -->
      <div v-show="editTab === 'docs'" class="tab-pane">
        <div class="form-grid-2">
          <div class="form-group full-w">
            <label class="flabel">Ghi chú hồ sơ</label>
            <input v-model="editForm.ghi_chu" class="finput" placeholder="Ghi chú thêm về tình trạng giấy tờ" />
          </div>

          <!-- Đăng kiểm -->
          <div class="doc-panel">
            <h4 class="dp-title">Đăng kiểm</h4>
            <div class="form-group">
              <label class="flabel">Số đăng kiểm</label>
              <input v-model="editForm.so_dang_kiem" class="finput" />
            </div>
            <div class="form-group">
              <label class="flabel">Ngày đăng kiểm</label>
              <input type="date" v-model="editForm.ngay_dang_kiem" class="finput" />
            </div>
            <div class="form-group">
              <label class="flabel">Ngày hết hạn</label>
              <input type="date" v-model="editForm.ngay_het_han_dang_kiem" class="finput" />
            </div>
            <div class="form-group mt-2">
              <label class="flabel">Ảnh giấy đăng kiểm</label>
              <div class="upload-box u-small" @click="$refs.hDK.click()">
                <img v-if="editPreviews.hinh_dang_kiem" :src="editPreviews.hinh_dang_kiem" class="up-img" />
                <div v-else class="up-placeholder">Tải ảnh...</div>
              </div>
              <input type="file" ref="hDK" class="hidden-input" accept="image/png, image/jpeg, image/webp" @change="(e) => handleFileChange(e, 'hinh_dang_kiem')" />
            </div>
          </div>

          <!-- Bảo hiểm -->
          <div class="doc-panel">
            <h4 class="dp-title">Bảo hiểm</h4>
            <div class="form-group">
              <label class="flabel">Số bảo hiểm</label>
              <input v-model="editForm.so_bao_hiem" class="finput" />
            </div>
            <div class="form-group">
              <label class="flabel">Ngày hiệu lực</label>
              <input type="date" v-model="editForm.ngay_hieu_luc_bao_hiem" class="finput" />
            </div>
            <div class="form-group">
              <label class="flabel">Ngày hết hạn</label>
              <input type="date" v-model="editForm.ngay_het_han_bao_hiem" class="finput" />
            </div>
            <div class="form-group mt-2">
              <label class="flabel">Ảnh giấy bảo hiểm</label>
              <div class="upload-box u-small" @click="$refs.hBH.click()">
                <img v-if="editPreviews.hinh_bao_hiem" :src="editPreviews.hinh_bao_hiem" class="up-img" />
                <div v-else class="up-placeholder">Tải ảnh...</div>
              </div>
              <input type="file" ref="hBH" class="hidden-input" accept="image/png, image/jpeg, image/webp" @change="(e) => handleFileChange(e, 'hinh_bao_hiem')" />
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <BaseButton variant="secondary" @click="editModal = false">Hủy</BaseButton>
        <BaseButton variant="primary" :loading="editLoading" @click="submitEdit">Thay đổi & Upload ảnh</BaseButton>
      </template>
    </BaseModal>

    <!-- ═══════════════════════════════════════════════════════
         MODAL: SƠ ĐỒ GHẾ
    ═══════════════════════════════════════════════════════ -->
    <BaseModal v-model="seatModal.show" title="Sơ Đồ Ghế Xe" maxWidth="860px">
      <div v-if="seatModal.loading" class="seat-loading">⏳ Đang tải sơ đồ ghế...</div>

      <div v-else-if="seatModal.data">
        <!-- Thông tin xe -->
        <div class="seat-xe-info">
          <span class="chip-code">{{ seatModal.data.bien_so }}</span>
          <span>{{ seatModal.data.ten_xe }}</span>
          <span class="ghe-badge">{{ seatModal.data.tong_ghe }} ghế</span>
          <span class="tang-badge">{{ seatModal.data.so_tang }} tầng</span>
        </div>

        <!-- Chú thích -->
        <div class="seat-legend">
          <span class="legend-item"><span class="lbox seat-ok-box"></span> Hoạt động</span>
          <span class="legend-item"><span class="lbox seat-broken-box"></span> Bảo trì / Khóa</span>
          <span class="legend-item"><span class="lbox seat-vip-box"></span> VIP / Hệ số giá cao</span>
        </div>

        <!-- Sơ đồ từng tầng -->
        <div v-for="(seats, floorKey) in seatModal.data.so_do_ghe" :key="floorKey" class="view-floor">
          <div class="floor-header">
            <span class="floor-label">{{ floorKey.replace('tang_', 'Tầng ') }}</span>
            <span class="floor-count">{{ seats.length }} ghế</span>
          </div>

          <div class="seat-map-grid">
            <div
              v-for="ghe in seats"
              :key="ghe.id"
              :class="[
                'seat-map-item',
                ghe.trang_thai === 'hoat_dong' ? 'smok' : 'smbrk',
                ghe.loai_ghe?.he_so_gia > 1 ? 'smvip' : '',
                seatModal.updatingId === ghe.id ? 'sm-updating' : '',
              ]"
              :title="ghe.ma_ghe + ' — ' + (ghe.loai_ghe?.ten_loai_ghe || '') + ' — ' + getSeatStatus(ghe.trang_thai).text"
              @click="toggleSeatStatus(seatModal.data.xe_id, ghe)"
            >
              <span class="sm-code">{{ ghe.ma_ghe }}</span>
              <span class="sm-type">{{ ghe.loai_ghe?.ten_loai_ghe || '' }}</span>
              <span v-if="seatModal.updatingId === ghe.id" class="sm-spin">⏳</span>
            </div>
          </div>
        </div>

        <div class="seat-tip">💡 Click vào ghế để chuyển trạng thái Hoạt động ↔ Bảo trì</div>
      </div>

      <template #footer>
        <BaseButton variant="secondary" @click="seatModal.show = false">Đóng</BaseButton>
      </template>
    </BaseModal>

    <!-- ═══════════════════════════════════════════════════════
         MODAL: CHI TIẾT XE
    ═══════════════════════════════════════════════════════ -->
    <BaseModal v-model="detailModal.show" title="Chi Tiết Xe" maxWidth="640px">
      <div v-if="detailModal.loading" class="seat-loading">Đang tải...</div>
      <div v-else-if="detailModal.data" class="detail-grid">
        <div class="detail-item">
          <span class="dlabel">Biển số</span>
          <span class="dvalue"><span class="chip-code">{{ detailModal.data.bien_so }}</span></span>
        </div>
        <div class="detail-item">
          <span class="dlabel">Tên xe</span>
          <span class="dvalue">{{ detailModal.data.ten_xe }}</span>
        </div>
        <div class="detail-item">
          <span class="dlabel">Loại xe</span>
          <span class="dvalue">{{ detailModal.data.loai_xe?.ten_loai_xe || '—' }}</span>
        </div>
        <div class="detail-item">
          <span class="dlabel">Số ghế / Tầng</span>
          <span class="dvalue">{{ detailModal.data.so_ghe_thuc_te }} ghế / {{ detailModal.data.thong_tin_cai_dat?.so_tang ?? 1 }} tầng</span>
        </div>
        <div class="detail-item">
          <span class="dlabel">Tiện nghi</span>
          <span class="dvalue">{{ detailModal.data.thong_tin_cai_dat?.tien_nghi || '—' }}</span>
        </div>
        <div class="detail-item">
          <span class="dlabel">Tài xế chính</span>
          <span class="dvalue">{{ detailModal.data.tai_xe_chinh?.ho_ten || '—' }}</span>
        </div>
        <div class="detail-item full-w">
          <span class="dlabel">Biển nhận dạng</span>
          <span class="dvalue">{{ detailModal.data.bien_nhan_dang || '—' }}</span>
        </div>
        <div class="detail-item full-w">
          <span class="dlabel">Trạng thái</span>
          <span class="dvalue">
            <span :class="['status-badge', getStatus(detailModal.data.trang_thai).cls]">
              {{ getStatus(detailModal.data.trang_thai).text }}
            </span>
          </span>
        </div>
        <div v-if="detailModal.data.ho_so_xe" class="detail-item full-w">
          <span class="dlabel">Trạng thái hồ sơ</span>
          <span class="dvalue">{{ detailModal.data.ho_so_xe.tinh_trang }}</span>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="() => { detailModal.show = false; openSeatModal(detailModal.data) }">🪑 Xem sơ đồ ghế</BaseButton>
        <BaseButton variant="outline" @click="detailModal.show = false">Đóng</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.pt-page { padding: 0; font-family: 'Inter', system-ui, sans-serif; }

/* ── Header ── */
.pt-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  margin-bottom: 20px; gap: 12px; flex-wrap: wrap;
}
.pt-title { font-size: 22px; font-weight: 800; color: #0d4f35; margin: 0 0 4px; }
.pt-sub { margin: 0; color: #64748b; font-size: 13px; }

/* ── Filter ── */
.pt-filter {
  background: rgba(255,255,255,.88); backdrop-filter: blur(10px);
  border: 1px solid #dcfce7; border-radius: 14px;
  box-shadow: 0 8px 20px rgba(13,79,53,.06);
  padding: 14px 16px; margin-bottom: 18px;
}
.filter-inner { display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap; }
.search-wrap { display: flex; gap: 10px; align-items: flex-end; flex: 1; min-width: 260px; }
.search-wrap > :first-child { flex: 1; margin-bottom: 0; }
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.flabel { font-size: 13px; font-weight: 600; color: #334155; }
.fselect, .finput {
  border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 9px 12px;
  font-size: 14px; color: #1f2937; background: #fff;
  transition: border-color .2s, box-shadow .2s; width: 100%; box-sizing: border-box;
}
.fselect:focus, .finput:focus {
  outline: none; border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,.14);
}
.ftextarea { resize: vertical; min-height: 70px; }

/* ── Table ── */
.pt-table-card {
  background: #fff; border: 1px solid #dcfce7; border-radius: 16px;
  padding: 16px; box-shadow: 0 8px 24px rgba(13,79,53,.06);
}
.chip-code {
  display: inline-block; background: #f0fdf4; color: #15803d;
  border: 1px solid #bbf7d0; padding: 3px 10px;
  border-radius: 10px; font-size: 12px; font-weight: 700;
}
.name-col { display: flex; flex-direction: column; gap: 2px; }
.name-sub { font-size: 12px; color: #64748b; }
.ghe-col { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.ghe-badge { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 2px 8px; border-radius: 8px; font-size: 12px; font-weight: 700; }
.tang-badge { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 2px 8px; border-radius: 8px; font-size: 12px; }
.status-badge { display: inline-block; font-size: 12px; font-weight: 700; border-radius: 999px; padding: 4px 12px; }
.status-active { background: #dcfce7; color: #166534; }
.status-info { background: #dbeafe; color: #1d4ed8; }
.status-pending { background: #fef3c7; color: #b45309; }
.action-row { display: flex; gap: 6px; flex-wrap: wrap; }

/* ── Pagination ── */
.pagination {
  margin-top: 16px; display: flex; justify-content: space-between;
  align-items: center; gap: 12px; flex-wrap: wrap;
}
.page-left { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #64748b; }
.per-sel { width: 72px !important; }
.total-txt { color: #94a3b8; }
.page-ctrl { display: flex; align-items: center; gap: 10px; }
.page-num { color: #334155; font-size: 14px; font-weight: 600; }

/* ── Step bar ── */
.step-bar {
  display: flex; align-items: center; gap: 0; margin-bottom: 20px;
  padding: 12px 16px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;
}
.step { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #94a3b8; }
.step-active { color: #15803d; }
.step-done { color: #0f766e; }
.step-dot {
  width: 26px; height: 26px; border-radius: 50%; background: #e2e8f0;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 800;
}
.step-active .step-dot { background: #16a34a; color: #fff; }
.step-done .step-dot { background: #0d9488; color: #fff; }
.step-line { flex: 1; height: 2px; background: #e2e8f0; margin: 0 12px; }

/* ── Form grid ── */
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.full-w { grid-column: 1 / -1; }
.hint { font-size: 12px; color: #16a34a; font-style: italic; margin-top: 2px; }

/* ── Info banner ── */
.info-banner {
  background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px;
  padding: 10px 14px; font-size: 13px; color: #92400e; margin-bottom: 16px;
}

/* ── Step 2: Seat builder ── */
.step2-wrap { display: flex; flex-direction: column; gap: 16px; }
.seat-info-bar {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  padding: 10px 14px; background: #f0fdf4; border-radius: 10px;
  font-size: 13px; color: #166534; border: 1px solid #bbf7d0;
}
.seat-count-badge { background: #166534; color: #fff; border-radius: 999px; padding: 2px 10px; font-weight: 800; font-size: 12px; }
.count-ok { color: #15803d; font-weight: 700; }
.count-err { color: #b45309; font-weight: 700; }
.floor-block { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
.floor-header {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
}
.floor-label { font-weight: 800; color: #0f172a; font-size: 14px; }
.floor-count { color: #64748b; font-size: 12px; }
.btn-add-seat {
  margin-left: auto; background: #f0fdf4; color: #15803d;
  border: 1.5px solid #bbf7d0; border-radius: 7px; padding: 4px 12px;
  font-size: 12px; font-weight: 700; cursor: pointer; transition: all .2s;
}
.btn-add-seat:hover { background: #dcfce7; }
.seat-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 10px; padding: 14px;
}
.seat-card {
  display: flex; flex-direction: column; gap: 6px;
  border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 10px;
  position: relative; background: #fff; transition: box-shadow .2s;
}
.seat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.seat-preview {
  height: 36px; border-radius: 8px; display: flex; align-items: center;
  justify-content: center; font-weight: 800; font-size: 13px;
}
.seat-norm { background: #f0fdf4; color: #166534; border: 1.5px solid #bbf7d0; }
.seat-vip { background: #fef3c7; color: #92400e; border: 1.5px solid #fde68a; }
.seat-input {
  border: 1px solid #cbd5e1; border-radius: 7px; padding: 5px 8px;
  font-size: 12px; color: #1f2937; background: #fff; width: 100%; box-sizing: border-box;
}
.seat-input:focus { outline: none; border-color: #16a34a; }
.seat-remove {
  position: absolute; top: 6px; right: 6px; width: 20px; height: 20px;
  border-radius: 50%; border: none; background: #fee2e2; color: #dc2626;
  font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: all .15s;
}
.seat-remove:hover { background: #dc2626; color: #fff; }
.seat-tip {
  font-size: 12px; color: #64748b; background: #f8fafc;
  border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px;
}

/* ── Seat map viewer ── */
.seat-loading { text-align: center; padding: 32px; color: #64748b; font-size: 15px; }
.seat-xe-info {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 12px;
}
.seat-legend {
  display: flex; gap: 16px; align-items: center; flex-wrap: wrap;
  font-size: 12px; color: #64748b; margin-bottom: 14px;
}
.legend-item { display: flex; align-items: center; gap: 5px; }
.lbox { width: 14px; height: 14px; border-radius: 4px; display: inline-block; }
.seat-ok-box { background: #dcfce7; border: 1px solid #86efac; }
.seat-broken-box { background: #fee2e2; border: 1px solid #fca5a5; }
.seat-vip-box { background: #fef3c7; border: 1px solid #fde68a; }
.view-floor { margin-bottom: 16px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
.seat-map-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
  gap: 8px; padding: 14px;
}
.seat-map-item {
  display: flex; flex-direction: column; align-items: center; gap: 2px;
  border-radius: 10px; padding: 8px 4px; cursor: pointer;
  border: 2px solid transparent; transition: all .18s; user-select: none;
}
.seat-map-item:hover { transform: scale(1.07); z-index: 2; }
.smok { background: #f0fdf4; border-color: #86efac; }
.smbrk { background: #fee2e2; border-color: #fca5a5; }
.smvip { border-style: dashed; border-color: #fbbf24; }
.smok.smvip { background: #fef9c3; }
.sm-updating { opacity: .5; pointer-events: none; }
.sm-code { font-size: 13px; font-weight: 800; color: #0f172a; }
.sm-type { font-size: 9px; color: #64748b; text-align: center; }
.sm-spin { font-size: 12px; }

/* ── Detail grid ── */
.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.detail-item { display: flex; flex-direction: column; gap: 4px; }
.full-w { grid-column: 1 / -1; }
.dlabel { font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #64748b; font-weight: 700; }
.dvalue { font-size: 14px; color: #0f172a; font-weight: 600; }

/* ── Edit Modal Tabs & Upload ── */
.edit-tabs { display: flex; gap: 4px; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; padding: 0 4px; }
.etab { 
  background: none; border: none; padding: 10px 16px; font-size: 14px; font-weight: 600; 
  color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px;
  transition: all 0.2s;
}
.etab:hover { color: #15803d; }
.etab-active { color: #15803d; border-bottom-color: #16a34a; }
.mb-3 { margin-bottom: 12px; }
.mt-2 { margin-top: 8px; }
.doc-panel { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; display: flex; flex-direction: column; gap: 10px; background: #fafafa; }
.dp-title { margin: 0; font-size: 14px; font-weight: 700; color: #334155; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px; }

.hidden-input { display: none; }
.upload-box { 
  width: 100%; height: 140px; border: 2px dashed #cbd5e1; border-radius: 12px; 
  display: flex; align-items: center; justify-content: center; cursor: pointer; 
  background: #f8fafc; overflow: hidden; transition: all 0.2s; position: relative;
}
.upload-box:hover { border-color: #16a34a; background: #f0fdf4; }
.up-small { height: 100px; }
.up-placeholder { font-size: 13px; font-weight: 600; color: #94a3b8; }
.up-img { width: 100%; height: 100%; object-fit: cover; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .filter-inner { flex-direction: column; align-items: stretch; }
  .form-grid-2, .detail-grid { grid-template-columns: 1fr; }
  .seat-map-grid { grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); }
  .step-bar { flex-direction: column; gap: 8px; }
}
</style>
