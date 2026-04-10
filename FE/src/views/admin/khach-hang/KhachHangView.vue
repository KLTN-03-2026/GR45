<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import { 
  Search, Trash2, 
  Lock, Unlock, Mail, Phone, MapPin, 
  Calendar, Eye, ShieldCheck, Ticket
} from 'lucide-vue-next';
import adminApi from '@/api/adminApi';
import { getStaffStatus } from '@/utils/status'; // Dùng chung logic hiển thị trạng thái
import { formatDateOnly, formatCurrency } from '@/utils/format';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseSelect from '@/components/common/BaseSelect.vue';
import BaseTable from '@/components/common/BaseTable.vue';
import BaseModal from '@/components/common/BaseModal.vue';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseToast from '@/components/common/BaseToast.vue';

// --- STATE ---
const clients = ref([]);
const loading = ref(false);
const detailsLoading = ref(false);
const actionLoading = ref(false); // Dành cho các thao tác nhanh
const totalClients = ref(0);

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
  hang_thanh_vien: '',
});

// Stats Computed
const stats = computed(() => {
  return {
    total: clients.value.length,
    active: clients.value.filter(c => c.tinh_trang === 'hoat_dong').length,
    blocked: clients.value.filter(c => c.tinh_trang === 'khoa').length
  };
});

const isDeleteModalOpen = ref(false);
const isDetailsModalOpen = ref(false);
const currentClientId = ref(null);
const clientToDelete = ref(null);
const clientDetails = ref(null);

const errors = ref({});

// Table Columns
const columns = [
  { key: 'id', label: 'ID' },
  { key: 'ho_va_ten', label: 'Khách hàng' },
  { key: 'hang_thanh_vien', label: 'Hạng' },
  { key: 'ngay_sinh', label: 'Ngày sinh' },
  { key: 'liên_hệ', label: 'Liên hệ' },
  { key: 'dia_chi', label: 'Địa chỉ' },
  { key: 'tinh_trang', label: 'Trạng thái' },
  { key: 'actions', label: 'Thao tác' },
];

// --- METHODS ---

const fetchClients = async () => {
  loading.value = true;
  try {
    const params = {
      search: filters.search,
      tinh_trang: filters.tinh_trang,
      hang_thanh_vien: filters.hang_thanh_vien,
      per_page: 50 // Lấy danh sách rộng để phục vụ hiển thị
    };
    const res = await adminApi.getClients(params);
    
    let listData = [];
    if (res.data?.data && Array.isArray(res.data.data)) {
      listData = res.data.data;
    } else if (Array.isArray(res.data)) {
      listData = res.data;
    }
    
    clients.value = listData;
    totalClients.value = listData.length;
  } catch (err) {
    console.error('Lỗi lấy danh sách khách hàng:', err);
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  filters.search = '';
  filters.tinh_trang = '';
  filters.hang_thanh_vien = '';
  fetchClients();
};

// Search handling with debounce
let searchTimeout;
watch(() => filters.search, () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(fetchClients, 500);
});

watch(() => filters.tinh_trang, fetchClients);
watch(() => filters.hang_thanh_vien, fetchClients);

const openDetailsModal = async (client) => {
  detailsLoading.value = true;
  try {
    const res = await adminApi.getClientDetails(client.id);
    clientDetails.value = res.data;
    isDetailsModalOpen.value = true;
  } catch (err) {
    showToast('Không thể lấy thông tin chi tiết!', 'error');
    console.error(err);
  } finally {
    detailsLoading.value = false;
  }
};

const toggleStatus = async (client) => {
  if (actionLoading.value) return;
  actionLoading.value = true;
  try {
    await adminApi.toggleClientStatus(client.id);
    // Cập nhật trực tiếp tại local thay vì fetch lại toàn bộ
    client.tinh_trang = client.tinh_trang === 'hoat_dong' ? 'khoa' : 'hoat_dong';
    showToast('Đã thay đổi trạng thái khách hàng!');
  } catch (err) {
    showToast('Lỗi thay đổi trạng thái!', 'error');
  } finally {
    actionLoading.value = false;
  }
};

const confirmDelete = (client) => {
  clientToDelete.value = client;
  isDeleteModalOpen.value = true;
};

const handleDelete = async () => {
  if (!clientToDelete.value || loading.value) return;
  loading.value = true;
  try {
    await adminApi.deleteClient(clientToDelete.value.id);
    isDeleteModalOpen.value = false;
    await fetchClients();
    showToast('Xóa khách hàng thành công!');
  } catch (err) {
    showToast(err.response?.data?.message || 'Có lỗi xảy ra khi xóa!', 'error');
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchClients();
});
</script>

<template>
  <div class="admin-khach-hang">
    <BaseToast v-model:visible="toast.visible" :message="toast.message" :type="toast.type" :show-icon="false" />
    
    <!-- Header Section -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý Khách Hàng</h1>
        <p class="page-sub">Xem và quản lý thông tin khách hàng, điểm thưởng và trạng thái tài khoản.</p>
      </div>
      <div class="header-right-side">
        <div class="header-stats" v-if="clients.length > 0">
          <div class="stat-chip stat-total">
            <span class="stat-number">{{ stats.total }}</span>
            <span class="stat-label">TỔNG</span>
          </div>
          <div class="stat-chip stat-active">
            <span class="stat-number">{{ stats.active }}</span>
            <span class="stat-label">HOẠT ĐỘNG</span>
          </div>
          <div class="stat-chip stat-blocked">
            <span class="stat-number">{{ stats.blocked }}</span>
            <span class="stat-label">BỊ KHÓA</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="filter-card">
      <div class="filter-row">
        <div class="filter-item search-box">
          <label class="filter-label">Tìm kiếm</label>
          <BaseInput v-model="filters.search" placeholder="Tìm theo tên, email, SĐT...">
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
            <label class="filter-label">Hạng thành viên</label>
            <BaseSelect
              v-model="filters.hang_thanh_vien"
              :options="[
                { value: '', label: 'Tất cả hạng' },
                { value: 'dong', label: 'Đồng' },
                { value: 'bac', label: 'Bạc' },
                { value: 'vang', label: 'Vàng' },
                { value: 'kim_cuong', label: 'Kim cương' }
              ]"
            />
          </div>

          <div class="filter-item btn-reset-wrapper">
            <label class="filter-label" style="visibility: hidden">.</label>
            <BaseButton variant="outline" @click="resetFilters" class="btn-reset">Đặt lại</BaseButton>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Section -->
    <BaseCard class="table-card p-0 overflow-hidden">
      <BaseTable :columns="columns" :data="clients" :loading="loading">
        <!-- Họ tên & Avatar -->
        <template #cell(ho_va_ten)="{ item }">
          <div class="user-cell">
            <div :class="['user-avatar', item.diem_thanh_vien?.hang_thanh_vien || 'moi']">
              {{ item.ho_va_ten?.charAt(0).toUpperCase() }}
            </div>
            <div class="user-info">
              <div class="user-name">{{ item.ho_va_ten }}</div>
              <div class="user-id">ID: {{ item.id }}</div>
            </div>
          </div>
        </template>

        <!-- Liên hệ -->
        <template #cell(liên_hệ)="{ item }">
          <div class="contact-stack">
            <div class="contact-item"><Mail :size="14" /> {{ item.email }}</div>
            <div class="contact-item"><Phone :size="14" /> {{ item.so_dien_thoai }}</div>
          </div>
        </template>

        <!-- Địa chỉ -->
        <template #cell(dia_chi)="{ value }">
          <div class="address-cell" :title="value">
            <MapPin :size="14" class="text-muted me-1" />
            <span class="text-truncate">{{ value || '---' }}</span>
          </div>
        </template>

        <!-- Hạng thành viên -->
        <template #cell(hang_thanh_vien)="{ item }">
          <span :class="['tier-badge', item.diem_thanh_vien?.hang_thanh_vien || 'moi']">
            {{ (item.diem_thanh_vien?.hang_thanh_vien || 'Mới').toUpperCase() }}
          </span>
        </template>

        <!-- Ngày sinh -->
        <template #cell(ngay_sinh)="{ value }">
          <div class="birth-date-cell">
            <Calendar :size="14" class="text-muted me-1" />
            <span>{{ formatDateOnly(value) }}</span>
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
            <button class="btn-action view" title="Xem chi tiết" @click.stop="openDetailsModal(item)">
              <Eye :size="18" />
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

    <!-- Modal Xem chi tiết -->
    <BaseModal v-model="isDetailsModalOpen" title="Chi tiết khách hàng" maxWidth="750px">
      <div v-if="detailsLoading" class="details-loading">
        <div class="loader"></div>
        <p>Đang tải thông tin...</p>
      </div>
      <div v-else-if="clientDetails" class="details-content">
        <div class="details-header">
          <div class="big-avatar">{{ clientDetails.ho_va_ten?.charAt(0).toUpperCase() }}</div>
          <div class="details-main-info">
            <h2>{{ clientDetails.ho_va_ten }}</h2>
            <div class="details-badges">
              <span class="id-tag">ID: {{ clientDetails.id }}</span>
              <span :class="['status-badge', getStaffStatus(clientDetails.tinh_trang).class]">
                {{ getStaffStatus(clientDetails.tinh_trang || 'hoat_dong').text }}
              </span>
            </div>
          </div>
          <div class="points-box">
            <div class="points-val">{{ clientDetails.diem_thanh_vien?.diem_kha_dung || 0 }}</div>
            <div class="points-label">Điểm tích lũy</div>
          </div>
        </div>

        <div class="details-grid">
          <div class="detail-group">
            <label><Mail :size="16" /> Email</label>
            <p>{{ clientDetails.email }}</p>
          </div>
          <div class="detail-group">
            <label><Phone :size="16" /> Số điện thoại</label>
            <p>{{ clientDetails.so_dien_thoai }}</p>
          </div>
          <div class="detail-group">
            <label><Calendar :size="16" /> Ngày sinh</label>
            <p>{{ formatDateOnly(clientDetails.ngay_sinh) }}</p>
          </div>
          <div class="detail-group">
            <label><ShieldCheck :size="16" /> Hạng thành viên</label>
            <p class="tier-text">{{ clientDetails.diem_thanh_vien?.hang_thanh_vien?.toUpperCase() || 'MỚI' }}</p>
          </div>
          <div class="detail-group full-width">
            <label><MapPin :size="16" /> Địa chỉ</label>
            <p>{{ clientDetails.dia_chi || 'Chưa cập nhật' }}</p>
          </div>
        </div>

        <div class="tickets-section">
          <h3><Ticket :size="18" /> Lịch sử đặt vé (Gần nhất)</h3>
          <div v-if="clientDetails.ves && clientDetails.ves.length > 0" class="tickets-list">
             <!-- Giả lập list vé -->
             <div v-for="ve in clientDetails.ves.slice(0, 5)" :key="ve.id" class="ticket-mini-card">
                <div class="t-route">{{ ve.chuyen_xe?.tuyen_duong?.ten_tuyen || 'Chuyến xe #' + ve.id }}</div>
                <div class="t-meta">{{ formatDateOnly(ve.ngay_di) }} - {{ formatCurrency(ve.tong_tien) }}</div>
             </div>
          </div>
          <div v-else class="empty-tickets">Chưa có lịch sử đặt vé nào.</div>
        </div>
      </div>
    </BaseModal>

    <!-- Modal Xác nhận xóa -->
    <BaseModal v-model="isDeleteModalOpen" title="Xác nhận xóa" maxWidth="400px">
      <div class="delete-confirm">
        <div class="warning-icon"><Trash2 :size="48" /></div>
        <h3>Xóa khách hàng?</h3>
        <p>Bạn có chắc chắn muốn xóa <strong>{{ clientToDelete?.ho_va_ten }}</strong>? Toàn bộ lịch sử điểm và vé sẽ bị ảnh hưởng.</p>
        <div class="confirm-actions">
          <BaseButton variant="secondary" @click="isDeleteModalOpen = false">Hủy</BaseButton>
          <BaseButton variant="danger" @click="handleDelete" :loading="loading">Xác nhận xóa</BaseButton>
        </div>
      </div>
    </BaseModal>
  </div>
</template>

<style scoped>
.admin-khach-hang {
  animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(8px); }
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
.stat-blocked { background: #fef3c7; color: #92400e; }

.page-title {
  font-size: 24px;
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

.btn-reset-wrapper {
  min-width: auto;
  flex: none;
}

.filter-label {
  font-size: 13px;
  font-weight: 600;
  color: #475569;
}

.user-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar {
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
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.user-cell:hover .user-avatar {
  transform: rotate(6deg) scale(1.1);
  box-shadow: 0 8px 16px rgba(99, 102, 241, 0.3);
}

.user-avatar.vang {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.user-avatar.kim_cuong {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  color: #1e293b;
  font-size: 0.925rem;
}

.user-id {
  font-size: 0.75rem;
  color: #94a3b8;
}

.contact-stack {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
  color: #64748b;
}

.address-cell {
  max-width: 220px;
  display: flex;
  align-items: center;
  font-size: 0.85rem;
}

.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.birth-date-cell {
  display: flex;
  align-items: center;
  font-size: 0.85rem;
  background: #f8fafc;
  padding: 4px 8px;
  border-radius: 6px;
  width: fit-content;
}

.me-1 { margin-right: 4px; }

.status-badge {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
}

.status-badge.status-approved { background: #dcfce7; color: #166534; }
.status-badge.status-rejected { background: #fee2e2; color: #991b1b; }

.action-buttons {
  display: flex;
  gap: 8px;
}

.btn-action {
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: white;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-action:hover {
  background: #f8fafc;
  color: #1e293b;
  transform: translateY(-2px);
}

.btn-action.view:hover { border-color: #3b82f6; color: #3b82f6; }
.btn-action.edit:hover { border-color: #4f46e5; color: #4f46e5; }
.btn-action.status:hover { border-color: #f59e0b; color: #f59e0b; }
.btn-action.delete:hover { border-color: #ef4444; color: #ef4444; }

/* Modal Form Styles */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.full-width {
  grid-column: span 2;
}

.form-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 32px;
  padding-top: 20px;
  border-top: 1px solid #f1f5f9;
}

/* Details Modal Styles */
.details-content {
  padding: 8px;
}

.details-header {
  display: flex;
  align-items: center;
  gap: 24px;
  margin-bottom: 32px;
  background: linear-gradient(to right, #f8fafc, #ffffff);
  padding: 24px;
  border-radius: 20px;
  border: 1px solid #e2e8f0;
}

.big-avatar {
  width: 80px;
  height: 80px;
  border-radius: 24px;
  background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  font-weight: 800;
  box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2);
}

.details-main-info h2 {
  margin: 0 0 8px 0;
  font-size: 24px;
  color: #1e293b;
}

.details-badges {
  display: flex;
  gap: 12px;
  align-items: center;
}

.id-tag {
  font-size: 13px;
  color: #64748b;
  background: #f1f5f9;
  padding: 2px 10px;
  border-radius: 6px;
}

.points-box {
  margin-left: auto;
  text-align: center;
  background: #fff;
  padding: 12px 24px;
  border-radius: 16px;
  border: 2px dashed #4f46e5;
}

.points-val {
  font-size: 28px;
  font-weight: 800;
  color: #4f46e5;
}

.points-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: #64748b;
  margin-top: 2px;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
  margin-bottom: 40px;
}

.detail-group label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #94a3b8;
  margin-bottom: 8px;
}

.detail-group p {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #334155;
}

.tier-text {
  color: #4f46e5 !important;
}

.tickets-section h3 {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 18px;
  color: #1e293b;
  margin-bottom: 16px;
}

.tickets-list {
  display: grid;
  gap: 12px;
}

.ticket-mini-card {
  padding: 14px 20px;
  background: #f8fafc;
  border-radius: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-left: 4px solid #4f46e5;
}

.t-route { font-weight: 600; color: #1e293b; }
.t-meta { font-size: 13px; color: #64748b; }

.empty-tickets {
  padding: 40px;
  text-align: center;
  background: #f8fafc;
  border-radius: 16px;
  color: #94a3b8;
  font-style: italic;
}

.delete-confirm {
  text-align: center;
  padding: 20px 10px;
}

.warning-icon {
  color: #ef4444;
  margin-bottom: 20px;
  animation: shake 0.5s ease-in-out;
}

@keyframes shake {
  0%, 100% { transform: rotate(0); }
  25% { transform: rotate(-10deg); }
  75% { transform: rotate(10deg); }
}

.delete-confirm h3 { margin-bottom: 12px; font-size: 20px; }
.delete-confirm p { color: #64748b; margin-bottom: 30px; }

.confirm-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

/* Loader cho modal chi tiết */
.details-loading {
  padding: 60px;
  text-align: center;
  color: #64748b;
}

.loader {
  width: 40px;
  height: 40px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid #4f46e5;
  border-radius: 50%;
  margin: 0 auto 16px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.tier-badge {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 800;
  color: white;
  display: inline-block;
}

.tier-badge.dong { background: #cd7f32; }
.tier-badge.bac { background: #94a3b8; }
.tier-badge.vang { background: #f59e0b; }
.tier-badge.kim_cuong { background: #3b82f6; }
.tier-badge.moi { background: #64748b; }

@media (max-width: 768px) {
  .details-header { flex-direction: column; text-align: center; }
  .points-box { margin: 0 auto; }
  .details-grid { grid-template-columns: 1fr; }
  .form-grid { grid-template-columns: 1fr; }
  .full-width { grid-column: span 1; }
}

/* Badges */
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-block;
  white-space: nowrap;
}
.badge-green { background: #dcfce7; color: #16a34a; }
.badge-red { background: #fee2e2; color: #dc2626; }
.badge-gray { background: #f1f5f9; color: #64748b; }

.membership-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.75rem;
  text-transform: uppercase;
}
.badge-silver { background: #f1f5f9; border: 1px solid #cbd5e1; color: #64748b; }
.badge-gold { background: #fef3c7; border: 1px solid #fde68a; color: #d97706; }
.badge-diamond { background: #ecfeff; border: 1px solid #a5f3fc; color: #0891b2; }

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
  max-height: 94vh;
  box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
  display: flex;
  flex-direction: column;
  animation: slideUp 0.3s ease;
  border: none;
  overflow: hidden;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.modal-xl { max-width: 900px; }
.modal-sm { max-width: 420px; }

.btn-close-custom {
  background: transparent;
  border: none;
  color: #94a3b8;
  padding: 0;
  cursor: pointer;
  transition: 0.2s;
}
.btn-close-custom:hover {
  color: #ef4444;
}

/* Nav Tabs trong Modal */
.nav-tabs {
  list-style: none;
  display: flex;
  margin: 0;
}

.nav-tabs .nav-link {
  color: #64748b;
  border-bottom: 2px solid transparent !important;
  transition: all 0.2s;
  display: flex;
  align-items: center;
}
.nav-tabs .nav-link:hover {
  color: #3b82f6;
}
.active-tab {
  color: #2563eb !important;
  border-bottom: 2px solid #2563eb !important;
}

/* Icons confirm */
.confirm-icon {
  width: 70px;
  height: 70px;
  border-radius: 50%;
}
.icon-danger { background: #fee2e2; color: #dc2626; }
.icon-warning { background: #fef3c7; color: #d97706; }

/* Overrides Buttons */
.btn-primary { background-color: #3b82f6; border-color: #3b82f6; box-shadow: 0 4px 6px -1px rgba(59,130,246,0.5); border-radius: 8px; padding: 8px 16px; }
.btn-primary:hover { background-color: #2563eb; }
.btn-secondary { background-color: #f1f5f9; border-color: #e2e8f0; color: #475569; border-radius: 8px; }
.btn-secondary:hover { background-color: #e2e8f0; color: #1e293b; }
.btn-danger { background-color: #ef4444; border-color: #ef4444; box-shadow: 0 4px 6px -1px rgba(239,68,68,0.3); border-radius: 8px; }
.btn-danger:hover { background-color: #dc2626; }

.text-xs { font-size: 0.75rem; }
.text-sm { font-size: 0.875rem; }
</style>
