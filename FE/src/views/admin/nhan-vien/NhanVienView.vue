<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import { 
  Plus, Search, Filter, Edit, Trash2, 
  Lock, Unlock, UserCheck, Mail, Phone, MapPin, 
  ShieldCheck, MoreHorizontal, X, Calendar
} from 'lucide-vue-next';
import adminApi from '@/api/adminApi';
import { getStaffStatus } from '@/utils/status';
import { formatDateOnly } from '@/utils/format';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseSelect from '@/components/common/BaseSelect.vue';
import BaseTable from '@/components/common/BaseTable.vue';
import BaseModal from '@/components/common/BaseModal.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseToast from '@/components/common/BaseToast.vue';
import { useAdminStore } from '@/stores/adminStore';

const adminStore = useAdminStore();

// --- STATE ---
const staffs = ref([]);
const roles = ref([]);
const loading = ref(false);
const totalStaffs = ref(0);

// Toast
const toast = reactive({ visible: false, message: '', type: 'success' });
const showToast = (message, type = 'success') => {
  toast.message = message;
  toast.type = type;
  toast.visible = true;
  setTimeout(() => { toast.visible = false; }, 3000);
};

// Filters
const filters = reactive({
  search: '',
  tinh_trang: '',
  id_chuc_vu: '',
});

// Stats Computed
const stats = computed(() => {
  return {
    total: staffs.value.length,
    active: staffs.value.filter(s => s.tinh_trang === 'hoat_dong').length,
    blocked: staffs.value.filter(s => s.tinh_trang === 'khoa').length
  };
});

// Modal State
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const isEditing = ref(false);
const currentStaffId = ref(null);
const staffToDelete = ref(null);

// Form State
const staffForm = reactive({
  ho_va_ten: '',
  email: '',
  so_dien_thoai: '',
  dia_chi: '',
  ngay_sinh: '',
  id_chuc_vu: '',
  password: '',
  tinh_trang: 'hoat_dong',
  is_master: 0
});

const errors = ref({});

// Table Columns
const columns = [
  { key: 'id', label: 'ID' },
  { key: 'ho_va_ten', label: 'Nhân viên' },
  { key: 'ngay_sinh', label: 'Ngày sinh' },
  { key: 'liên_hệ', label: 'Liên hệ' },
  { key: 'dia_chi', label: 'Địa chỉ' },
  { key: 'chuc_vu', label: 'Chức vụ' },
  { key: 'tinh_trang', label: 'Trạng thái' },
  { key: 'actions', label: 'Thao tác' },
];

// --- METHODS ---

const fetchRoles = async () => {
  try {
    const res = await adminApi.getRoles();
    roles.value = res.data.map(role => ({
      value: role.id,
      label: role.ten_chuc_vu
    }));
  } catch (err) {
    console.error('Lỗi lấy danh sách chức vụ:', err);
  }
};

const fetchStaffs = async () => {
  loading.value = true;
  try {
    const params = {
      search: filters.search,
      tinh_trang: filters.tinh_trang,
      id_chuc_vu: filters.id_chuc_vu,
    };
    const res = await adminApi.getStaffs(params);
    
    let listData = [];
    if (res.data?.data && Array.isArray(res.data.data)) {
      listData = res.data.data;
    } else if (Array.isArray(res.data)) {
      listData = res.data;
    }
    
    staffs.value = listData;
    totalStaffs.value = listData.length;
  } catch (err) {
    console.error('Lỗi lấy danh sách nhân viên:', err);
    // Không showToast ở đây để tránh hiện lặp thông báo lỗi khi các hàm khác gọi fetchStaffs
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  filters.search = '';
  filters.tinh_trang = '';
  filters.id_chuc_vu = '';
  fetchStaffs();
};

// Debounce search
let searchTimeout;
watch(() => filters.search, () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(fetchStaffs, 500);
});

watch([() => filters.tinh_trang, () => filters.id_chuc_vu], fetchStaffs);

const openCreateModal = () => {
  isEditing.value = false;
  currentStaffId.value = null;
  Object.assign(staffForm, {
    ho_va_ten: '',
    email: '',
    so_dien_thoai: '',
    dia_chi: '',
    ngay_sinh: '',
    id_chuc_vu: '',
    password: '',
    tinh_trang: 'hoat_dong',
    is_master: 0
  });
  errors.value = {};
  isModalOpen.value = true;
};

const openEditModal = (staff) => {
  isEditing.value = true;
  currentStaffId.value = staff.id;
  Object.assign(staffForm, {
    ho_va_ten: staff.ho_va_ten,
    email: staff.email,
    so_dien_thoai: staff.so_dien_thoai,
    dia_chi: staff.dia_chi,
    ngay_sinh: staff.ngay_sinh,
    id_chuc_vu: staff.id_chuc_vu,
    password: '', // Không hiển thị mật khẩu cũ
    tinh_trang: staff.tinh_trang,
    is_master: staff.is_master
  });
  errors.value = {};
  isModalOpen.value = true;
};

const handleSave = async () => {
  errors.value = {};
  loading.value = true;
  try {
    const payload = { ...staffForm };
    
    // Khi cập nhật, nếu mật khẩu để trống thì không gửi lên API
    if (isEditing.value && !payload.password) {
      delete payload.password;
    }

    if (isEditing.value) {
      await adminApi.updateStaff(currentStaffId.value, payload);
      isModalOpen.value = false;
      await fetchStaffs();
      showToast('Cập nhật nhân viên thành công!');
    } else {
      await adminApi.createStaff(payload);
      isModalOpen.value = false;
      await fetchStaffs();
      showToast('Thêm nhân viên mới thành công!');
    }
  } catch (err) {
    if (err.response && err.response.data.errors) {
      errors.value = err.response.data.errors;
    } else {
      showToast(err.response?.data?.message || 'Có lỗi xảy ra!', 'error');
    }
  } finally {
    loading.value = false;
  }
};

const toggleStatus = async (staff) => {
  if (loading.value) return;
  loading.value = true;
  try {
    await adminApi.toggleStaffStatus(staff.id);
    await fetchStaffs();
    showToast('Đã thay đổi trạng thái nhân viên!');
  } catch (err) {
    showToast('Lỗi thay đổi trạng thái!', 'error');
    console.error('Lỗi thay đổi trạng thái:', err);
  } finally {
    loading.value = false;
  }
};

const confirmDelete = (staff) => {
  staffToDelete.value = staff;
  isDeleteModalOpen.value = true;
};

const handleDelete = async () => {
  if (!staffToDelete.value || loading.value) return;
  loading.value = true;
  try {
    await adminApi.deleteStaff(staffToDelete.value.id);
    isDeleteModalOpen.value = false;
    await fetchStaffs();
    showToast('Xóa nhân viên thành công!');
  } catch (err) {
    showToast(err.response?.data?.message || 'Có lỗi xảy ra khi xóa!', 'error');
    console.error('Lỗi xóa nhân viên:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchRoles();
  fetchStaffs();
});
</script>

<template>
  <div class="admin-nhan-vien">
    <BaseToast v-model:visible="toast.visible" :message="toast.message" :type="toast.type" :show-icon="false" />
    <!-- Header Section -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Nhân Viên</h1>
        <p class="page-sub">
          Quản lý toàn bộ đội ngũ nhân viên trên hệ thống. Cấp quyền hoặc 
          thay đổi trạng thái nhân viên để đảm bảo an ninh vận hành.
        </p>
      </div>
      <div class="header-right-side">
        <div class="header-stats" v-if="staffs.length > 0">
          <div class="stat-chip stat-total">
            <span class="stat-number">{{ stats.total }}</span>
            <span class="stat-label">TỔNG</span>
          </div>
          <div class="stat-chip stat-blocked">
            <span class="stat-number">{{ stats.blocked }}</span>
            <span class="stat-label">BỊ KHÓA</span>
          </div>
          <div class="stat-chip stat-active">
            <span class="stat-number">{{ stats.active }}</span>
            <span class="stat-label">HOẠT ĐỘNG</span>
          </div>
        </div>
        <div class="header-actions">
          <BaseButton variant="primary" @click="openCreateModal" class="btn-add-staff">
            <template #icon><Plus :size="18" /></template>
            Thêm nhân viên
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="filter-card">
      <div class="filter-row">
        <div class="filter-item search-box">
          <label class="filter-label">Tìm kiếm</label>
          <BaseInput 
            v-model="filters.search" 
            placeholder="Tìm theo tên, email, SĐT..."
          >
            <template #prefix><Search :size="18" class="text-muted" /></template>
          </BaseInput>
        </div>
        
        <div class="filter-group-items">
          <div class="filter-item">
            <label class="filter-label">Trạng thái</label>
            <BaseSelect
              v-model="filters.tinh_trang"
              :options="[
                { value: '', label: 'Tất cả trạng thái' },
                { value: 'hoat_dong', label: 'Hoạt động' },
                { value: 'khoa', label: 'Bị khóa' }
              ]"
            />
          </div>
          <div class="filter-item">
            <label class="filter-label">Chức vụ</label>
            <BaseSelect
              v-model="filters.id_chuc_vu"
              :options="[{ value: '', label: 'Tất cả chức vụ' }, ...roles]"
            />
          </div>
          <div class="filter-item btn-reset-wrapper">
            <label class="filter-label" style="visibility: hidden">.</label>
            <BaseButton variant="outline" @click="resetFilters" class="btn-reset">
              Đặt lại
            </BaseButton>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <BaseCard class="table-card p-0 overflow-hidden">
      <BaseTable :columns="columns" :data="staffs" :loading="loading">
        <!-- Tên và ID -->
        <template #cell(ho_va_ten)="{ item }">
          <div class="staff-info">
            <div class="staff-avatar" :class="item.is_master ? 'master' : ''">
              {{ item.ho_va_ten?.charAt(0).toUpperCase() }}
            </div>
            <div class="staff-meta">
              <div class="staff-name-wrapper">
                <span class="staff-name">{{ item.ho_va_ten }}</span>
                <span v-if="item.is_master" class="badge-master">Master</span>
              </div>
              <div class="staff-role-text">
                 <ShieldCheck :size="12" class="me-1" />
                 {{ item.chuc_vu?.ten_chuc_vu || 'Nhân viên' }}
              </div>
            </div>
          </div>
        </template>

        <!-- Ngày sinh -->
        <template #cell(ngay_sinh)="{ value }">
          <div class="birth-date-cell">
            <Calendar :size="14" class="text-muted me-1" />
            <span>{{ formatDateOnly(value) }}</span>
          </div>
        </template>

        <!-- Liên hệ -->
        <template #cell(liên_hệ)="{ item }">
          <div class="contact-info">
            <div class="contact-item" :title="item.email">
              <Mail :size="14" /> <span>{{ item.email }}</span>
            </div>
            <div class="contact-item">
              <Phone :size="14" /> <span>{{ item.so_dien_thoai }}</span>
            </div>
          </div>
        </template>

        <!-- Địa chỉ -->
        <template #cell(dia_chi)="{ value }">
          <div class="address-cell" :title="value">
            <MapPin :size="14" class="text-muted me-1" />
            <span class="text-truncate">{{ value }}</span>
          </div>
        </template>

        <!-- Chức vụ -->
        <template #cell(chuc_vu)="{ item }">
          <div class="role-badge">
            <ShieldCheck :size="14" />
            <span>{{ item.chuc_vu?.ten_chuc_vu || 'N/A' }}</span>
          </div>
        </template>

        <!-- Trạng thái -->
        <template #cell(tinh_trang)="{ value }">
          <span :class="['status-badge', getStaffStatus(value).class]">
            {{ getStaffStatus(value).text }}
          </span>
        </template>

        <!-- Thao tác -->
        <template #cell(actions)="{ item }">
          <div class="action-buttons">
            <button class="btn-action edit" title="Chỉnh sửa" @click.stop="openEditModal(item)">
              <Edit :size="18" />
            </button>
            <button 
              class="btn-action status" 
              :title="item.tinh_trang === 'hoat_dong' ? 'Khóa tài khoản' : 'Mở khóa'"
              @click.stop="toggleStatus(item)"
            >
              <Lock v-if="item.tinh_trang === 'hoat_dong'" :size="18" />
              <Unlock v-else :size="18" />
            </button>
            <button class="btn-action delete" title="Xóa" @click.stop="confirmDelete(item)">
              <Trash2 :size="18" />
            </button>
          </div>
        </template>
      </BaseTable>
    </BaseCard>

    <!-- Modal Thêm/Sửa -->
    <BaseModal 
      v-model="isModalOpen" 
      :title="isEditing ? 'Chỉnh sửa nhân viên' : 'Thêm nhân viên mới'"
      maxWidth="650px"
    >
      <form @submit.prevent="handleSave" class="staff-form">
        <div class="form-grid">
          <BaseInput 
            v-model="staffForm.ho_va_ten" 
            label="Họ và tên" 
            placeholder="Nhập họ và tên đầy đủ"
            :error="errors.ho_va_ten?.[0]"
          />
          <BaseInput 
            v-model="staffForm.email" 
            label="Email" 
            type="email"
            placeholder="example@gmail.com"
            :error="errors.email?.[0]"
            :disabled="isEditing"
          />
          <BaseInput 
            v-model="staffForm.so_dien_thoai" 
            label="Số điện thoại" 
            placeholder="0xxxxxxxxx"
            :error="errors.so_dien_thoai?.[0]"
          />
          <BaseSelect 
            v-model="staffForm.id_chuc_vu" 
            label="Chức vụ" 
            :options="roles"
            placeholder="Chọn chức vụ"
            :error="errors.id_chuc_vu?.[0]"
            :disabled="!adminStore.isMaster"
          />
          <BaseInput 
            v-model="staffForm.ngay_sinh" 
            label="Ngày sinh" 
            type="date"
            :error="errors.ngay_sinh?.[0]"
          />
          <div class="full-width">
            <BaseInput 
              v-model="staffForm.dia_chi" 
              label="Địa chỉ" 
              placeholder="Nhập địa chỉ cư trú"
              :error="errors.dia_chi?.[0]"
            />
          </div>
          <BaseInput 
            v-model="staffForm.password" 
            label="Mật khẩu" 
            type="password"
            :placeholder="isEditing ? 'Để trống nếu không muốn đổi' : 'Nhập mật khẩu'"
            :error="errors.password?.[0]"
          />
          <BaseSelect 
            v-model="staffForm.tinh_trang" 
            label="Trạng thái" 
            :options="[
              { value: 'hoat_dong', label: 'Hoạt động' },
              { value: 'khoa', label: 'Bị khóa' }
            ]"
            :error="errors.tinh_trang?.[0]"
          />
        </div>
        
        <div class="form-footer">
          <BaseButton variant="secondary" @click="isModalOpen = false">Hủy</BaseButton>
          <BaseButton variant="primary" type="submit" :loading="loading">
            {{ isEditing ? 'Cập nhật' : 'Thêm mới' }}
          </BaseButton>
        </div>
      </form>
    </BaseModal>

    <!-- Modal Xác nhận xóa -->
    <BaseModal v-model="isDeleteModalOpen" title="Xác nhận xóa" maxWidth="400px">
      <div class="delete-confirm">
        <div class="warning-icon">
          <Trash2 :size="48" />
        </div>
        <h3>Xóa nhân viên?</h3>
        <p>Bạn có chắc chắn muốn xóa nhân viên <strong>{{ staffToDelete?.ho_va_ten }}</strong>? Hành động này không thể hoàn tác.</p>
        <div class="confirm-actions">
          <BaseButton variant="secondary" @click="isDeleteModalOpen = false">Hủy</BaseButton>
          <BaseButton variant="danger" @click="handleDelete" :loading="loading">Xác nhận xóa</BaseButton>
        </div>
      </div>
    </BaseModal>
  </div>
</template>

<style scoped>
.admin-nhan-vien {
  animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  gap: 20px;
  flex-wrap: wrap;
}

.header-right-side {
  display: flex;
  align-items: center;
  gap: 24px;
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
  min-width: 64px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.stat-number {
  font-size: 1.25rem;
  font-weight: 700;
}

.stat-label {
  font-size: 0.7rem;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.stat-total { background: #f1f5f9; color: #475569; }
.stat-active { background: #dcfce7; color: #166534; }
.stat-blocked { background: #fef3c7; color: #92400e; } /* Màu vàng như "Chờ duyệt" của Voucher */

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

.filter-card {
  margin-bottom: 24px;
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(12px);
  border: 1px solid #e2e8f0;
  padding: 20px;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

.filter-row {
  display: flex;
  gap: 20px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.filter-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 160px;
}

.filter-item :deep(.base-select-wrapper),
.filter-item :deep(.base-input-wrapper) {
  margin-bottom: 0 !important;
}

.search-box {
  flex: 1.5;
  min-width: 250px;
}

.filter-group-items {
  display: flex;
  gap: 16px;
  flex: 3;
}

.filter-group-items .filter-item {
  flex: 1;
}

.btn-reset-wrapper,
.btn-add-wrapper {
  min-width: auto;
}

.filter-label {
  font-size: 13px;
  font-weight: 600;
  color: #475569;
}

.btn-reset {
  white-space: nowrap;
}

.table-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.04);
}

/* Staff Info Styled */
.staff-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.staff-avatar {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 20px;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
  transition: transform 0.3s ease;
}

.staff-info:hover .staff-avatar {
  transform: rotate(5deg) scale(1.05);
}

.staff-avatar.master {
  background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.staff-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.staff-name-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
}

.staff-name {
  font-weight: 600;
  color: #1e293b;
  font-size: 14px;
}

.staff-role-text {
  font-size: 12px;
  color: #64748b;
  display: flex;
  align-items: center;
  font-weight: 500;
}

.birth-date-cell {
  font-size: 13px;
  color: #475569;
  font-weight: 500;
  display: flex;
  align-items: center;
  background: #f8fafc;
  padding: 4px 10px;
  border-radius: 8px;
  width: fit-content;
}

.address-cell {
  font-size: 13px;
  color: #475569;
  display: flex;
  align-items: center;
  max-width: 200px;
}

.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.me-1 { margin-right: 4px; }

.badge-master {
  font-size: 10px;
  background: #fef3c7;
  color: #92400e;
  padding: 1px 6px;
  border-radius: 4px;
  width: fit-content;
  font-weight: 700;
  text-transform: uppercase;
}

/* Contact Info */
.contact-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #64748b;
}

.contact-item svg {
  color: #94a3b8;
}

/* Role Badge */
.role-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #f1f5f9;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
}

.role-badge svg {
  color: #6366f1;
}

/* Status Badges */
.status-badge {
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.status-badge.status-approved {
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
}

.status-badge.status-rejected {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.birth-date {
  font-size: 14px;
  color: #334155;
  font-weight: 500;
  background: #f8fafc;
  padding: 4px 10px;
  border-radius: 8px;
  display: inline-block;
}

/* Actions */
.action-buttons {
  display: flex;
  gap: 8px;
}

.btn-action {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: white;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-action:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.btn-action.edit:hover {
  color: #4f46e5;
  background: #eef2ff;
  border-color: #c7d2fe;
}

.btn-action.status:hover {
  color: #f59e0b;
  background: #fffbeb;
  border-color: #fef3c7;
}

.btn-action.delete:hover {
  color: #ef4444;
  background: #fef2f2;
  border-color: #fee2e2;
}

/* Form Styling */
.staff-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.full-width {
  grid-column: span 2;
}

.form-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 10px;
}

/* Delete confirmation */
.delete-confirm {
  text-align: center;
  padding: 10px 0;
}

.warning-icon {
  width: 80px;
  height: 80px;
  background: #fee2e2;
  color: #ef4444;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
}

.delete-confirm h3 {
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 8px;
}

.delete-confirm p {
  color: #64748b;
  font-size: 15px;
  line-height: 1.5;
  margin-bottom: 24px;
}

.confirm-actions {
  display: flex;
  justify-content: center;
  gap: 12px;
}

/* Responsive */
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
  
  .filter-grid {
    flex-direction: column;
    align-items: stretch;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .full-width {
    grid-column: span 1;
  }
}
</style>
