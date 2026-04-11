<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ShieldCheck, Crown, RefreshCw, Pencil, Trash2, Plus, Search } from 'lucide-vue-next'
import adminApi from '@/api/adminApi.js'
import { useAdminStore } from '@/stores/adminStore.js'
import BaseCard from '@/components/common/BaseCard.vue'
import BaseButton from '@/components/common/BaseButton.vue'
import BaseInput from '@/components/common/BaseInput.vue'
import BaseModal from '@/components/common/BaseModal.vue'
import BaseTable from '@/components/common/BaseTable.vue'
import BaseSelect from '@/components/common/BaseSelect.vue'

const adminStore = useAdminStore()

const pageLoading = ref(false)
const functionLoading = ref(false)
const actionLoading = ref(false)
const roleLoading = ref(false)
const roleSaving = ref(false)
const errorText = ref('')

const functionList = ref([])
const roles = ref([])
const selectedRoleId = ref('')
const selectedRolePermissionIds = ref([])
const selectedRoleName = ref('')
const keyword = ref('')
const statusFilter = ref('')

const isModalOpen = ref(false)
const editingId = ref(null)

const formData = reactive({
  ten_chuc_nang: '',
  mota: '',
  tinh_trang: 'hoat_dong',
})

const tableColumns = [
  { key: 'ten_chuc_nang', label: 'Tên chức năng' },
  { key: 'slug', label: 'Slug' },
  { key: 'tinh_trang', label: 'Tình trạng' },
  { key: 'actions', label: 'Thao tác' },
]

const statusOptions = [
  { value: 'hoat_dong', label: 'Hoạt động' },
  { value: 'tam_khoa', label: 'Tạm khoá' },
]

const statusFilterOptions = [
  { value: '', label: 'Tất cả trạng thái' },
  ...statusOptions,
]

const isSuperAdmin = computed(() => adminStore.isMaster === 1)
const canViewRbacConfig = computed(() => isSuperAdmin.value || adminStore.hasPermission('xem-phan-quyen'))

const roleOptions = computed(() => {
  const options = [{ value: '', label: '-- Chọn chức vụ --' }]

  for (const role of roles.value) {
    options.push({
      value: String(role.id),
      label: role.ten_chuc_vu || role.slug || `Chức vụ #${role.id}`,
    })
  }

  return options
})

const staticPermissionLabelMap = {
  'xem-nhan-vien': 'Xem nhân viên',
  'them-nhan-vien': 'Thêm nhân viên',
  'sua-nhan-vien': 'Sửa nhân viên',
  'xoa-nhan-vien': 'Xoá nhân viên',
  'cap-nhat-trang-thai-nhan-vien': 'Cập nhật trạng thái nhân viên',
  'xem-khach-hang': 'Xem khách hàng',
  'xoa-khach-hang': 'Xoá khách hàng',
  'cap-nhat-trang-thai-khach-hang': 'Cập nhật trạng thái khách hàng',
  'xem-tai-xe': 'Xem tài xế',
  'them-tai-xe': 'Thêm tài xế',
  'xoa-tai-xe': 'Xoá tài xế',
  'cap-nhat-trang-thai-tai-xe': 'Cập nhật trạng thái tài xế',
  'xem-nha-xe': 'Xem nhà xe',
  'them-nha-xe': 'Thêm nhà xe',
  'xoa-nha-xe': 'Xoá nhà xe',
  'cap-nhat-trang-thai-nha-xe': 'Cập nhật trạng thái nhà xe',
  'xem-tuyen-duong': 'Xem tuyến đường',
  'them-tuyen-duong': 'Thêm tuyến đường',
  'sua-tuyen-duong': 'Sửa tuyến đường',
  'xoa-tuyen-duong': 'Xoá tuyến đường',
  'duyet-tuyen-duong': 'Duyệt tuyến đường',
  'xem-chuyen-xe': 'Xem chuyến xe',
  'them-chuyen-xe': 'Thêm chuyến xe',
  'sua-chuyen-xe': 'Sửa chuyến xe',
  'xoa-chuyen-xe': 'Xoá chuyến xe',
  'cap-nhat-trang-thai-chuyen-xe': 'Cập nhật trạng thái chuyến xe',
  'doi-xe': 'Đổi xe',
  'xem-tracking-chuyen-xe': 'Xem tracking chuyến xe',
  'auto-generate-chuyen-xe': 'Tiện ích auto generate chuyến xe',
  'xem-xe': 'Xem xe',
  'them-xe': 'Thêm xe',
  'sua-xe': 'Sửa xe',
  'xoa-xe': 'Xoá xe',
  'cap-nhat-trang-thai-xe': 'Cập nhật trạng thái xe',
  'auto-generate-ghe-xe': 'Auto generate ghế xe',
  'xem-ve': 'Xem vé',
  'dat-ve-admin': 'Đặt vé admin',
  'cap-nhat-trang-thai-ve': 'Cập nhật trạng thái vé',
  'huy-ve': 'Huỷ vé',
  'xem-voucher': 'Xem voucher',
  'duyet-voucher': 'Duyệt voucher',
  'xem-dashboard': 'Xem dashboard',
  'xem-phan-quyen': 'Xem phân quyền',
  'xem-cau-hinh': 'Xem cấu hình chung',
  'xem-ho-tro': 'Xem hỗ trợ',
  'xem-thong-ke': 'Xem thống kê',
}

const formatStatusLabel = (status) => {
  if (status === 'hoat_dong') return 'Hoạt động'
  if (status === 'tam_khoa') return 'Tạm khoá'
  return status || 'Không rõ'
}

const permissionLabelMap = computed(() => {
  const map = { ...staticPermissionLabelMap }

  for (const item of functionList.value) {
    if (item?.slug && item?.ten_chuc_nang) {
      map[item.slug] = item.ten_chuc_nang
    }
  }

  return map
})

const formatPermissionLabel = (slug) => {
  if (!slug) return ''

  if (permissionLabelMap.value[slug]) {
    return permissionLabelMap.value[slug]
  }

  return slug
    .replace(/-/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

const permissionBadges = computed(() =>
  (adminStore.permissions || []).map((slug) => ({
    slug,
    label: formatPermissionLabel(slug),
  }))
)

const filteredFunctions = computed(() => {
  const normalizedKeyword = keyword.value.trim().toLowerCase()

  return functionList.value.filter((item) => {
    const matchKeyword =
      !normalizedKeyword ||
      item.ten_chuc_nang?.toLowerCase().includes(normalizedKeyword) ||
      item.slug?.toLowerCase().includes(normalizedKeyword) ||
      item.mota?.toLowerCase().includes(normalizedKeyword)

    const matchStatus = !statusFilter.value || item.tinh_trang === statusFilter.value

    return matchKeyword && matchStatus
  })
})

const modalTitle = computed(() =>
  editingId.value ? 'Cập nhật chức năng' : 'Thêm chức năng mới'
)

const extractList = (response) => {
  if (Array.isArray(response)) return response
  if (Array.isArray(response?.data)) return response.data
  return []
}

const extractObject = (response) => {
  if (!response || typeof response !== 'object' || Array.isArray(response)) return {}
  if (response.data && typeof response.data === 'object' && !Array.isArray(response.data)) {
    return response.data
  }
  return response
}

const resetForm = () => {
  formData.ten_chuc_nang = ''
  formData.mota = ''
  formData.tinh_trang = 'hoat_dong'
  editingId.value = null
}

const fetchFunctions = async () => {
  if (!canViewRbacConfig.value) return

  functionLoading.value = true

  try {
    const res = await adminApi.getFunctions()
    functionList.value = extractList(res)
  } catch (err) {
    errorText.value = err.response?.data?.message || err.message || 'Không tải được danh sách chức năng'
  } finally {
    functionLoading.value = false
  }
}

const fetchRoles = async () => {
  if (!canViewRbacConfig.value) return

  roleLoading.value = true

  try {
    const res = await adminApi.getRoles()
    roles.value = extractList(res)

    if (!selectedRoleId.value && roles.value.length > 0) {
      selectedRoleId.value = String(roles.value[0].id)
    }
  } catch (err) {
    errorText.value = err.response?.data?.message || err.message || 'Không tải được danh sách chức vụ'
  } finally {
    roleLoading.value = false
  }
}

const fetchRolePermissions = async (roleId) => {
  if (!roleId) {
    selectedRolePermissionIds.value = []
    selectedRoleName.value = ''
    return
  }

  roleLoading.value = true

  try {
    const res = await adminApi.getRolePermissions(roleId)
    const payload = extractObject(res)

    selectedRolePermissionIds.value = Array.isArray(payload.quyen_ids)
      ? payload.quyen_ids.map((id) => Number(id))
      : []

    selectedRoleName.value = payload.chuc_vu?.ten_chuc_vu || ''
  } catch (err) {
    errorText.value = err.response?.data?.message || err.message || 'Không tải được phân quyền theo chức vụ'
  } finally {
    roleLoading.value = false
  }
}

const toggleRolePermission = (permissionId) => {
  const id = Number(permissionId)
  const current = new Set(selectedRolePermissionIds.value)

  if (current.has(id)) {
    current.delete(id)
  } else {
    current.add(id)
  }

  selectedRolePermissionIds.value = Array.from(current)
}

const saveRolePermissions = async () => {
  if (!selectedRoleId.value) {
    errorText.value = 'Vui lòng chọn chức vụ trước khi lưu phân quyền.'
    return
  }

  roleSaving.value = true
  errorText.value = ''

  try {
    await adminApi.syncRolePermissions(selectedRoleId.value, {
      chuc_nang_ids: selectedRolePermissionIds.value,
    })
    await fetchRolePermissions(selectedRoleId.value)
  } catch (err) {
    errorText.value = err.response?.data?.message || err.message || 'Không cập nhật được quyền cho chức vụ'
  } finally {
    roleSaving.value = false
  }
}

const refreshAll = async () => {
  pageLoading.value = true
  errorText.value = ''

  await adminStore.fetchPermissions()

  if (canViewRbacConfig.value) {
    await Promise.all([fetchFunctions(), fetchRoles()])

    if (selectedRoleId.value) {
      await fetchRolePermissions(selectedRoleId.value)
    }
  }

  pageLoading.value = false
}

const openCreateModal = () => {
  resetForm()
  isModalOpen.value = true
}

const openEditModal = (item) => {
  editingId.value = item.id
  formData.ten_chuc_nang = item.ten_chuc_nang || ''
  formData.mota = item.mota || ''
  formData.tinh_trang = item.tinh_trang || 'hoat_dong'
  isModalOpen.value = true
}

const closeModal = () => {
  isModalOpen.value = false
  resetForm()
}

const saveFunction = async () => {
  if (!formData.ten_chuc_nang.trim()) {
    errorText.value = 'Tên chức năng là bắt buộc.'
    return
  }

  actionLoading.value = true
  errorText.value = ''

  const payload = {
    ten_chuc_nang: formData.ten_chuc_nang.trim(),
    mota: formData.mota.trim(),
    tinh_trang: formData.tinh_trang,
  }

  try {
    if (editingId.value) {
      await adminApi.updateFunction(editingId.value, payload)
    } else {
      await adminApi.createFunction(payload)
    }

    closeModal()
    await fetchFunctions()
  } catch (err) {
    errorText.value = err.response?.data?.message || err.message || 'Không lưu được chức năng'
  } finally {
    actionLoading.value = false
  }
}

const removeFunction = async (item) => {
  const confirmed = window.confirm(`Bạn có chắc muốn xoá chức năng "${item.ten_chuc_nang}"?`)
  if (!confirmed) return

  actionLoading.value = true
  errorText.value = ''

  try {
    await adminApi.deleteFunction(item.id)
    await fetchFunctions()
  } catch (err) {
    errorText.value = err.response?.data?.message || err.message || 'Xoá chức năng thất bại'
  } finally {
    actionLoading.value = false
  }
}

onMounted(async () => {
  await refreshAll()
})

watch(selectedRoleId, async (nextRoleId) => {
  if (!canViewRbacConfig.value) return
  await fetchRolePermissions(nextRoleId)
})
</script>

<template>
  <div class="permission-page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Phân quyền & Chức năng</h1>
        <p class="page-subtitle">Hiển thị quyền theo dữ liệu thực tế từ API `/phan-quyen`.</p>
      </div>
      <BaseButton variant="secondary" :loading="pageLoading" @click="refreshAll">
        <RefreshCw class="btn-icon" />
        Làm mới
      </BaseButton>
    </div>

    <BaseCard>
      <template #header>
        <div class="card-header-inline">
          <div class="header-left">
            <ShieldCheck class="header-icon" />
            <span>Quyền tài khoản hiện tại</span>
          </div>
          <span class="role-chip" :class="{ 'master-chip': isSuperAdmin }">
            <Crown v-if="isSuperAdmin" class="chip-icon" />
            {{ isSuperAdmin ? 'Super Admin' : (adminStore.chucVu || 'Nhân viên') }}
          </span>
        </div>
      </template>

      <div class="permission-grid">
        <div class="permission-box">
          <h3>Vai trò</h3>
          <p>{{ adminStore.chucVu || 'Chưa có thông tin chức vụ' }}</p>
        </div>

        <div class="permission-box">
          <h3>Danh sách quyền</h3>
          <div class="permission-tags">
            <span v-if="permissionBadges.length === 0 && !isSuperAdmin" class="empty-tag">
              Tài khoản chưa được gán quyền cụ thể
            </span>
            <span v-if="isSuperAdmin" class="tag tag-master">Toàn quyền hệ thống</span>
            <span v-for="permission in permissionBadges" :key="permission.slug" class="tag">
              {{ permission.label }}
            </span>
          </div>
        </div>
      </div>
    </BaseCard>

    <BaseCard v-if="canViewRbacConfig">
      <template #header>
        <div class="card-header-inline">
          <span>Phân quyền theo chức vụ</span>
          <BaseButton variant="secondary" size="sm" :loading="roleSaving" @click="saveRolePermissions">
            Lưu phân quyền
          </BaseButton>
        </div>
      </template>

      <div class="role-toolbar">
        <BaseSelect
          v-model="selectedRoleId"
          label="Chọn chức vụ"
          :options="roleOptions"
          :disabled="roleLoading || roleSaving"
        />
        <p class="role-note">
          {{ selectedRoleName ? `Đang cấu hình cho: ${selectedRoleName}` : 'Chọn chức vụ để cấu hình quyền.' }}
        </p>
      </div>

      <div class="permission-checkbox-grid" v-if="functionList.length > 0">
        <label
          v-for="permission in functionList"
          :key="permission.id"
          class="permission-checkbox-item"
        >
          <input
            type="checkbox"
            :checked="selectedRolePermissionIds.includes(Number(permission.id))"
            :disabled="!selectedRoleId || roleLoading || roleSaving"
            @change="toggleRolePermission(permission.id)"
          />
          <div>
            <p class="permission-name">{{ permission.ten_chuc_nang || formatPermissionLabel(permission.slug) }}</p>
            <span class="permission-slug">{{ permission.slug }}</span>
          </div>
        </label>
      </div>

      <p v-else class="non-master-note">Không có dữ liệu chức năng để phân quyền.</p>
    </BaseCard>

    <BaseCard v-if="canViewRbacConfig" no-padding>
      <template #header>
        <div class="card-header-inline">
          <span>Quản lý danh sách chức năng</span>
          <BaseButton size="sm" @click="openCreateModal">
            <Plus class="btn-icon" />
            Thêm chức năng
          </BaseButton>
        </div>
      </template>

      <div class="toolbar">
        <div class="toolbar-input">
          <Search class="search-icon" />
          <BaseInput v-model="keyword" placeholder="Tìm theo tên, slug, mô tả" />
        </div>
        <BaseSelect v-model="statusFilter" :options="statusFilterOptions" />
      </div>

      <BaseTable :columns="tableColumns" :data="filteredFunctions" :loading="functionLoading || pageLoading">
        <template #cell(tinh_trang)="{ value }">
          <span class="status-chip" :class="value === 'hoat_dong' ? 'status-active' : 'status-inactive'">
            {{ formatStatusLabel(value) }}
          </span>
        </template>

        <template #cell(actions)="{ item }">
          <div class="action-row">
            <BaseButton size="sm" variant="outline" @click="openEditModal(item)">
              <Pencil class="btn-icon" />
              Sửa
            </BaseButton>
            <BaseButton
              size="sm"
              variant="danger"
              :loading="actionLoading"
              @click="removeFunction(item)"
            >
              <Trash2 class="btn-icon" />
              Xoá
            </BaseButton>
          </div>
        </template>
      </BaseTable>
    </BaseCard>

    <BaseCard v-else>
      <template #header>Quản lý chức năng hệ thống</template>
      <p class="non-master-note">
        Tài khoản hiện tại chưa có quyền cấu hình phân quyền/chức năng.
      </p>
    </BaseCard>

    <p v-if="errorText" class="error-text">{{ errorText }}</p>

    <BaseModal v-model="isModalOpen" :title="modalTitle" max-width="680px" @close="closeModal">
      <BaseInput v-model="formData.ten_chuc_nang" label="Tên chức năng" placeholder="Ví dụ: Xem khách hàng" />
      <BaseInput v-model="formData.mota" label="Mô tả" placeholder="Mô tả ngắn cho quyền này" />
      <BaseSelect v-model="formData.tinh_trang" label="Tình trạng" :options="statusOptions" />

      <template #footer>
        <BaseButton variant="secondary" @click="closeModal">Huỷ</BaseButton>
        <BaseButton :loading="actionLoading" @click="saveFunction">Lưu</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.permission-page {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.page-title {
  font-size: 28px;
  font-weight: 800;
  color: #0f172a;
}

.page-subtitle {
  margin-top: 6px;
  color: #64748b;
  font-size: 14px;
}

.card-header-inline {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-icon {
  width: 18px;
  height: 18px;
  color: #4f46e5;
}

.permission-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.permission-box {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 14px;
  background: #f8fafc;
}

.permission-box h3 {
  font-size: 14px;
  margin-bottom: 8px;
  color: #334155;
}

.permission-box p {
  color: #0f172a;
  font-weight: 600;
}

.permission-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag,
.empty-tag,
.role-chip {
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.tag {
  background: #e0e7ff;
  color: #4338ca;
}

.tag-master,
.master-chip {
  background: #dcfce7;
  color: #166534;
}

.empty-tag {
  background: #f1f5f9;
  color: #64748b;
}

.role-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #eef2ff;
  color: #3730a3;
}

.chip-icon,
.btn-icon {
  width: 14px;
  height: 14px;
}

.toolbar {
  display: grid;
  grid-template-columns: 1fr 240px;
  gap: 10px;
  padding: 14px 14px 0 14px;
}

.role-toolbar {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 12px;
  align-items: end;
  margin-bottom: 12px;
}

.role-note {
  color: #334155;
  font-size: 14px;
  margin-bottom: 12px;
}

.permission-checkbox-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.permission-checkbox-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
}

.permission-checkbox-item input {
  margin-top: 4px;
}

.permission-name {
  font-size: 14px;
  font-weight: 600;
  color: #0f172a;
}

.permission-slug {
  display: block;
  margin-top: 2px;
  font-size: 12px;
  color: #64748b;
}

.toolbar-input {
  position: relative;
}

.search-icon {
  position: absolute;
  top: 13px;
  left: 12px;
  width: 16px;
  height: 16px;
  color: #64748b;
  z-index: 2;
}

.toolbar-input :deep(.base-input) {
  padding-left: 36px;
}

.toolbar :deep(.base-input-wrapper),
.toolbar :deep(.base-select-wrapper) {
  margin-bottom: 0;
}

.desc-cell {
  color: #475569;
}

.status-chip {
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 700;
}

.status-active {
  color: #166534;
  background: #dcfce7;
}

.status-inactive {
  color: #991b1b;
  background: #fee2e2;
}

.action-row {
  display: flex;
  gap: 8px;
}

.non-master-note {
  color: #64748b;
  font-size: 14px;
}

.error-text {
  color: #b91c1c;
  font-weight: 600;
}

@media (max-width: 1024px) {
  .permission-grid {
    grid-template-columns: 1fr;
  }

  .toolbar {
    grid-template-columns: 1fr;
  }

  .role-toolbar {
    grid-template-columns: 1fr;
  }

  .permission-checkbox-grid {
    grid-template-columns: 1fr;
  }

  .page-header {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
