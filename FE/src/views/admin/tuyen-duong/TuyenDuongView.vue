<script setup>
import { ref, reactive, onMounted } from 'vue';
import adminApi from '@/api/adminApi';
import BaseTable from '@/components/common/BaseTable.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import BaseInput from '@/components/common/BaseInput.vue';
import BaseModal from '@/components/common/BaseModal.vue';
import BaseToast from '@/components/common/BaseToast.vue'; // <-- Import Toast
import mapApi from '@/api/mapApi';
import { formatCurrency } from '@/utils/format';
import { getRouteStatus as getStatusLabel } from '@/utils/status';

// --- TOAST STATE ---
const toast = reactive({
  visible: false,
  message: '',
  type: 'success'
});

const showToast = (message, type = 'success') => {
  toast.message = message;
  toast.type = type;
  toast.visible = true;
  setTimeout(() => {
    toast.visible = false;
  }, 3000);
};

// --- TRẠNG THÁI VÀ DỮ LIỆU ---
const routes = ref([]);
const loading = ref(false);
const pagination = reactive({
  currentPage: 1,
  perPage: 15,
  total: 0,
});
const searchQuery = ref('');

// Cấu hình cột cho BaseTable
const tableColumns = [
  { key: 'id', label: 'ID' },
  { key: 'ma_nha_xe', label: 'Mã Nhà Xe' },
  { key: 'ten_tuyen_duong', label: 'Tên Tuyến' },
  { key: 'lo_trinh', label: 'Lộ Trình' },
  { key: 'quang_duong', label: 'Quãng Đường (km)' },
  { key: 'gia_ve_co_ban', label: 'Giá Vé' },
  { key: 'tinh_trang', label: 'Trạng Thái' },
  { key: 'actions', label: 'Hành Động' },
];

// --- MODAL ---
const isShowModal = ref(false);
const isEditMode = ref(false);
const modalLoading = ref(false);

const initialForm = {
  ma_nha_xe: '',
  ten_tuyen_duong: '',
  diem_bat_dau: '',
  diem_ket_thuc: '',
  quang_duong: 0,
  cac_ngay_trong_tuan: [0, 1, 2, 3, 4, 5, 6],
  gio_khoi_hanh: '06:00',
  gio_ket_thuc: '10:00',
  gia_ve_co_ban: 0,
  id_xe: 1, // field lúc gửi lên API là xe
  tinh_trang: 'cho_duyet', // Thêm trường tinh_trang
  tram_dungs: [] // mảng các trạm
};

const formData = reactive({ ...initialForm });
const currentId = ref(null);
const geoLoadingStationIndex = ref(null);

const daysOfWeek = [
  { value: 0, label: 'CN' },
  { value: 1, label: 'T2' },
  { value: 2, label: 'T3' },
  { value: 3, label: 'T4' },
  { value: 4, label: 'T5' },
  { value: 5, label: 'T6' },
  { value: 6, label: 'T7' },
];

// Trạm dừng template
const getEmptyStation = (loai = 'don', thu_tu = 1) => ({
  ten_tram: '',
  dia_chi: '',
  id_phuong_xa: 1,
  loai_tram: loai,
  thu_tu: thu_tu,
  toa_do_x: 0,
  toa_do_y: 0
});

// Thêm trạm dừng mới
const addStation = () => {
  formData.tram_dungs.push(getEmptyStation('don', formData.tram_dungs.length + 1));
};

// Khởi tạo trạm mặc định
const initDefaultStations = () => {
  if (formData.tram_dungs.length === 0) {
    formData.tram_dungs.push(getEmptyStation('don', 1));
    formData.tram_dungs.push(getEmptyStation('tra', 2));
  }
};

// Loại bỏ trạm dừng
const removeStation = (index) => {
  formData.tram_dungs.splice(index, 1);
};

// Toggle ngày chạy
const toggleDay = (dayValue) => {
  const index = formData.cac_ngay_trong_tuan.indexOf(dayValue);
  if (index === -1) {
    formData.cac_ngay_trong_tuan.push(dayValue);
  } else {
    formData.cac_ngay_trong_tuan.splice(index, 1);
  }
};

const getStationGeoQueries = (station) => {
  const queries = [
    station?.dia_chi,
    [station?.ten_tram, station?.dia_chi].filter(Boolean).join(', '),
    [station?.ten_tram, formData.diem_bat_dau].filter(Boolean).join(', '),
    [formData.diem_bat_dau, formData.diem_ket_thuc].filter(Boolean).join(' - '),
    station?.ten_tram,
  ]
    .map((item) => item?.trim())
    .filter(Boolean);

  return [...new Set(queries)];
};

const fetchStationCoordinates = async (station, index) => {
  const queries = getStationGeoQueries(station);

  if (!queries.length) {
    showToast('Vui lòng nhập địa chỉ hoặc tên trạm trước khi lấy tọa độ.', 'error');
    return;
  }

  try {
    geoLoadingStationIndex.value = index;
    let result = null;

    for (const query of queries) {
      const response = await mapApi.searchCoordinatesByAddress(query);
      if (response?.data?.length) {
        result = response.data[0];
        break;
      }
    }

    if (!result) {
      showToast('Không tìm thấy tọa độ phù hợp từ bản đồ Leaflet.', 'error');
      return;
    }

    station.toa_do_x = Number(result.lon);
    station.toa_do_y = Number(result.lat);
    showToast('Đã lấy tọa độ thành công từ map Leaflet.', 'success');
  } catch (error) {
    console.error('Lỗi lấy tọa độ map:', error);
    showToast('Không thể lấy tọa độ từ map Leaflet.', 'error');
  } finally {
    geoLoadingStationIndex.value = null;
  }
};

// --- HÀM GỌI API ---
const fetchRoutes = async (page = 1) => {
  try {
    loading.value = true;
    const res = await adminApi.getRoutes({ 
      per_page: pagination.perPage, 
      search: searchQuery.value,
      page: page
    });
    
    // Linh hoạt bóc tách mảng dữ liệu vì API đôi khi lồng nhiều cấp
    let listData = [];
    let pageInfo = {};
    
    if (res.data?.data?.data?.data) {
      listData = res.data.data.data.data;
      pageInfo = res.data.data.data;
    } else if (res.data?.data?.data) {
      listData = res.data.data.data;
      pageInfo = res.data.data;
    } else if (res.data?.data) {
      listData = res.data.data;
      pageInfo = res.data;
    } else if (Array.isArray(res.data)) {
      listData = res.data;
    }
    
    routes.value = Array.isArray(listData) ? listData : [];
    pagination.currentPage = pageInfo.current_page || 1;
    pagination.total = pageInfo.total || 0;
  } catch (error) {
    console.error('Lỗi khi tải danh sách tuyến đường:', error);
  } finally {
    loading.value = false;
  }
};

// --- XỬ LÝ SỰ KIỆN ---
const handleSearch = () => {
  fetchRoutes(1);
};

const openCreateModal = () => {
  isEditMode.value = false;
  Object.assign(formData, initialForm);
  initDefaultStations();
  isShowModal.value = true;
};

const openEditModal = (route) => {
  isEditMode.value = true;
  currentId.value = route.id;
  Object.assign(formData, {
    ma_nha_xe: route.ma_nha_xe || '',
    ten_tuyen_duong: route.ten_tuyen_duong || '',
    diem_bat_dau: route.diem_bat_dau || '',
    diem_ket_thuc: route.diem_ket_thuc || '',
    quang_duong: Number(route.quang_duong) || 0,
    cac_ngay_trong_tuan: route.cac_ngay_trong_tuan || [],
    gio_khoi_hanh: route.gio_khoi_hanh || '',
    gio_ket_thuc: route.gio_ket_thuc || '',
    gia_ve_co_ban: Number(route.gia_ve_co_ban) || 0,
    id_xe: route.id_xe || 1,
    tinh_trang: route.tinh_trang || 'cho_duyet', // Map tình trạng về
    tram_dungs: route.tram_dungs || []
  });
  isShowModal.value = true;
};

const submitForm = async () => {
  try {
    modalLoading.value = true;
    
    // Mapping format cho API
    const payload = {
        ...formData,
        xe: formData.id_xe // API requires "xe" instead of id_xe parameter for creation
    };

    if (isEditMode.value) {
      await adminApi.updateRoute(currentId.value, payload);
      showToast('Cập nhật tuyến đường thành công!', 'success');
    } else {
      await adminApi.createRoute(payload);
      showToast('Thêm tuyến đường mới thành công!', 'success');
    }
    
    isShowModal.value = false;
    fetchRoutes(pagination.currentPage);
  } catch (error) {
    console.error('Lỗi lưu tuyến đường:', error);
    showToast(error.response?.data?.message || 'Có lỗi xảy ra khi lưu tuyến đường!', 'error');
  } finally {
    modalLoading.value = false;
  }
};

const confirmModal = reactive({
  show: false,
  title: '',
  message: '',
  action: null,
  id: null,
  loading: false
});

const openConfirmModal = (action, id) => {
  confirmModal.action = action;
  confirmModal.id = id;
  if (action === 'approve') {
    confirmModal.title = 'Xác nhận duyệt';
    confirmModal.message = 'Bạn có chắc chắn muốn duyệt tuyến đường này?';
  } else if (action === 'reject') {
    confirmModal.title = 'Xác nhận từ chối';
    confirmModal.message = 'Bạn có chắc chắn muốn từ chối/hủy tuyến đường này?';
  } else if (action === 'delete') {
    confirmModal.title = 'Xác nhận xóa';
    confirmModal.message = 'Bạn có chắc muốn xóa tuyến đường này không? Hành động này không thể hoàn tác!';
  }
  confirmModal.show = true;
};

const executeConfirmAction = async () => {
  const { action, id } = confirmModal;
  try {
    confirmModal.loading = true;
    if (action === 'approve') {
      await adminApi.approveRoute(id);
      showToast('Đã duyệt tuyến đường thành công.', 'success');
    } else if (action === 'reject') {
      await adminApi.rejectRoute(id);
      showToast('Đã từ chối tuyến đường.', 'success');
    } else if (action === 'delete') {
      await adminApi.deleteRoute(id);
      showToast('Đã xóa tuyến đường thành công.', 'success');
    }
    fetchRoutes(pagination.currentPage);
    confirmModal.show = false;
  } catch (error) {
    console.error(`Lỗi khi thực hiện ${action}:`, error);
    showToast(error.response?.data?.message || `Có lỗi khi thực hiện ${action}!`, 'error');
  } finally {
    confirmModal.loading = false;
  }
};

// Lifecycle
onMounted(() => {
  fetchRoutes();
});
</script>

<template>
  <div class="admin-page">
    <BaseToast :visible="toast.visible" :message="toast.message" :type="toast.type" />
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
      <h1 class="page-title">Quản lý Tuyến Đường</h1>
      <BaseButton @click="openCreateModal" variant="primary">
        + Thêm Tuyến Đường
      </BaseButton>
    </div>

    <!-- Thanh tìm kiếm & Lọc -->
    <div class="filter-card">
      <div class="search-box">
        <BaseInput 
          v-model="searchQuery" 
          placeholder="Tìm kiếm theo tên tuyến, mã nhà xe..." 
          @keyup.enter="handleSearch"
        />
        <BaseButton @click="handleSearch" variant="secondary">Tìm</BaseButton>
      </div>
    </div>

    <!-- Bảng Dữ Liệu -->
    <div class="table-card">
      <BaseTable 
        :columns="tableColumns" 
        :data="routes" 
        :loading="loading"
      >
        <!-- Custom cell cho Lộ Trình -->
        <template #cell(lo_trinh)="{ item }">
          <div class="route-path">
            <span class="fw-bold">{{ item.diem_bat_dau }}</span>
            <span class="route-arrow">→</span>
            <span class="fw-bold">{{ item.diem_ket_thuc }}</span>
          </div>
        </template>

        <!-- Custom cell cho Quãng Đường -->
        <template #cell(quang_duong)="{ value }">
          {{ Number(value) }} km
        </template>

        <!-- Custom cell cho Giá Vé -->
        <template #cell(gia_ve_co_ban)="{ value }">
          <span class="text-primary fw-bold">{{ formatCurrency(value) }}</span>
        </template>

        <!-- Custom cell cho Trạng Thái -->
        <template #cell(tinh_trang)="{ value }">
          <span :class="['status-badge', getStatusLabel(value).class]">
            {{ getStatusLabel(value).text }}
          </span>
        </template>

        <!-- Custom cell Hành Động -->
        <template #cell(actions)="{ item }">
          <div class="action-buttons">
            <BaseButton size="sm" variant="outline" @click="openEditModal(item)">Sửa</BaseButton>
            
            <BaseButton 
              v-if="item.tinh_trang === 'cho_duyet'" 
              size="sm" 
              variant="primary" 
              @click="openConfirmModal('approve', item.id)"
            >Duyệt</BaseButton>
            
            <BaseButton 
              v-if="item.tinh_trang === 'cho_duyet'" 
              size="sm" 
              variant="secondary" 
              @click="openConfirmModal('reject', item.id)"
            >Từ chối</BaseButton>
            
            <BaseButton size="sm" variant="danger" @click="openConfirmModal('delete', item.id)">Xóa</BaseButton>
          </div>
        </template>
      </BaseTable>

      <!-- Phân trang -->
      <div class="pagination-container mt-4 d-flex justify-content-between align-items-center">
        <div class="per-page-selector">
          <span style="color: #64748b; font-size: 0.875rem;">Hiển thị: </span>
          <select 
            v-model="pagination.perPage" 
            @change="handleSearch" 
            class="custom-select" 
            style="width: auto; display: inline-block; padding: 0.25rem 0.75rem; margin: 0 0.5rem; min-width: 70px;"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="20">20</option>
            <option :value="30">30</option>
            <option :value="50">50</option>
          </select>
          <span style="color: #64748b; font-size: 0.875rem;"> dòng / trang</span>
          <span style="color: #64748b; font-size: 0.875rem; margin-left: 1rem;" v-if="pagination.total > 0">
            (Tổng: {{ pagination.total }})
          </span>
        </div>
        
        <div class="pagination-controls d-flex align-items-center" style="gap: 0.5rem;">
          <BaseButton 
            size="sm" 
            variant="outline" 
            :disabled="pagination.currentPage <= 1"
            @click="fetchRoutes(pagination.currentPage - 1)"
          >
            Trước
          </BaseButton>
          <span class="page-info fw-bold" style="padding: 0 0.75rem;">Trang {{ pagination.currentPage }}</span>
          <BaseButton 
            size="sm" 
            variant="outline" 
            :disabled="routes.length < pagination.perPage"
            @click="fetchRoutes(pagination.currentPage + 1)"
          >
            Sau
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- Modal Thêm/Sửa -->
    <BaseModal 
      v-model="isShowModal" 
      :title="isEditMode ? 'Cập Nhật Tuyến Đường' : 'Thêm Tuyến Đường Mới'"
      maxWidth="800px"
    >
      <form @submit.prevent="submitForm" class="route-form">
        <!-- Thông tin chung -->
        <h4 class="form-section-title">Thông Tin Cơ Bản</h4>
        <div class="form-grid">
          <BaseInput v-model="formData.ma_nha_xe" label="Mã Nhà Xe" placeholder="Nhập mã nhà xe (VD: NX001)" required />
          <BaseInput v-model="formData.ten_tuyen_duong" label="Tên Tuyến Đường" placeholder="VD: Hà Nội - Hải Phòng" required />
          <BaseInput v-model="formData.diem_bat_dau" label="Điểm Bắt Đầu" placeholder="Thành phố xuất phát" required />
          <BaseInput v-model="formData.diem_ket_thuc" label="Điểm Kết Thúc" placeholder="Thành phố đến" required />
          
          <div class="form-group">
            <label class="base-input-label">Quãng Đường (km)</label>
            <input type="number" v-model="formData.quang_duong" class="custom-input" min="1" required />
          </div>
          
          <div class="form-group">
            <label class="base-input-label">Giá Vé Cơ Bản (VNĐ)</label>
            <input type="number" v-model="formData.gia_ve_co_ban" class="custom-input" min="0" required />
          </div>

          <div class="form-group">
            <label class="base-input-label">Giờ Khởi Hành</label>
            <input type="time" v-model="formData.gio_khoi_hanh" class="custom-input" required />
          </div>

          <div class="form-group">
            <label class="base-input-label">Giờ Kết Thúc</label>
            <input type="time" v-model="formData.gio_ket_thuc" class="custom-input" required />
          </div>

          <div class="form-group full-width" v-if="isEditMode">
            <label class="base-input-label mb-2">Trạng Thái Tuyến Đường</label>
            <div class="btn-group w-100" role="group" aria-label="Trạng thái tuyến đường">
              <input type="radio" class="btn-check" name="statusToggle" id="statusActive" autocomplete="off" value="hoat_dong" v-model="formData.tinh_trang">
              <label class="btn btn-outline-success" for="statusActive">Đã Phê Duyệt / Hoạt Động</label>

              <input type="radio" class="btn-check" name="statusToggle" id="statusInactive" autocomplete="off" value="khong_hoat_dong" v-model="formData.tinh_trang">
              <label class="btn btn-outline-danger" for="statusInactive">Từ Chối / Không Hoạt Động</label>

              <input type="radio" class="btn-check" name="statusToggle" id="statusPending" autocomplete="off" value="cho_duyet" v-model="formData.tinh_trang">
              <label class="btn btn-outline-warning" for="statusPending">Chờ Duyệt (Pending)</label>
            </div>
          </div>
        </div>

        <!-- Các ngày chạy -->
        <h4 class="form-section-title mt-4">Lịch Trình (Ngày trong tuần)</h4>
        <div class="days-grid">
          <label v-for="day in daysOfWeek" :key="day.value" class="day-checkbox">
            <input 
              type="checkbox" 
              :checked="formData.cac_ngay_trong_tuan.includes(day.value)"
              @change="toggleDay(day.value)"
            />
            <span class="day-label">{{ day.label }}</span>
          </label>
        </div>

        <!-- Trạm dừng -->
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h4 class="form-section-title m-0">Danh Sách Trạm Dừng</h4>
            <BaseButton size="sm" variant="outline" type="button" @click="addStation">+ Thêm Trạm</BaseButton>
        </div>
        
        <div class="stations-list">
          <div v-for="(station, index) in formData.tram_dungs" :key="index" class="station-item">
            <div class="station-header">
                <strong>Trạm thứ {{ index + 1 }}</strong>
                <BaseButton size="sm" variant="text" class="text-danger" type="button" @click="removeStation(index)">Xóa</BaseButton>
            </div>
            <div class="form-grid">
                <BaseInput v-model="station.ten_tram" label="Tên Trạm" placeholder="VD: Bến xe Nước Ngầm" required />
                <div class="form-group">
                    <label class="base-input-label">Loại Trạm</label>
                    <select v-model="station.loai_tram" class="custom-select">
                        <option value="don">Trạm Đón</option>
                        <option value="tra">Trạm Trả</option>
                    </select>
                </div>
                <div class="form-group full-width">
                    <BaseInput v-model="station.dia_chi" label="Địa Chỉ Trạm" placeholder="Địa chỉ chi tiết..." required />
                </div>
                <div class="form-group">
                    <label class="base-input-label">Tọa Độ X</label>
                    <input type="number" step="0.000001" v-model="station.toa_do_x" class="custom-input" />
                </div>
                <div class="form-group">
                    <label class="base-input-label">Tọa Độ Y</label>
                    <input type="number" step="0.000001" v-model="station.toa_do_y" class="custom-input" />
                </div>
                <div class="form-group full-width station-coordinate-action">
                    <BaseButton
                      size="sm"
                      variant="outline"
                      type="button"
                      :loading="geoLoadingStationIndex === index"
                      @click="fetchStationCoordinates(station, index)"
                    >
                      Lấy tọa độ từ map Leaflet
                    </BaseButton>
                </div>
            </div>
          </div>
        </div>
      </form>

      <template #footer>
        <BaseButton variant="secondary" @click="isShowModal = false">Hủy</BaseButton>
        <BaseButton variant="primary" :loading="modalLoading" @click="submitForm">
          {{ isEditMode ? 'Cập Nhật' : 'Thêm Tuyến Đường' }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- Modal Xác nhận (Confirm) -->
    <BaseModal 
      v-model="confirmModal.show" 
      :title="confirmModal.title"
      maxWidth="450px"
    >
      <div style="padding: 1rem 0; text-align: center;">
        <p style="font-size: 1.05rem; color: #334155; margin: 0;">{{ confirmModal.message }}</p>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="confirmModal.show = false">Hủy</BaseButton>
        <BaseButton 
          :variant="confirmModal.action === 'delete' ? 'danger' : 'primary'" 
          :loading="confirmModal.loading"
          @click="executeConfirmAction"
        >
          Xác nhận
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<style scoped>
.admin-page {
  padding: 1.5rem;
  font-family: 'Inter', system-ui, sans-serif;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.d-flex { display: flex; }
.justify-content-between { justify-content: space-between; }
.align-items-center { align-items: center; }
.mb-4 { margin-bottom: 1.5rem; }
.mt-4 { margin-top: 1.5rem; }
.mb-2 { margin-bottom: 0.5rem; }
.m-0 { margin: 0; }
.fw-bold { font-weight: 600; }
.text-primary { color: #4f46e5; }
.text-danger { color: #ef4444; }

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

.search-box {
  display: flex;
  gap: 1rem;
  max-width: 500px;
  align-items: flex-end;
}
.search-box > :first-child { flex: 1; margin-bottom: 0;} /* Fix BaseInput margin */

/* Table Card */
.table-card {
  background: white;
  border-radius: 12px;
  padding: 1rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
  border: 1px solid rgba(226, 232, 240, 0.8);
}

.route-path {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #334155;
}

.route-arrow {
  color: #94a3b8;
  font-weight: bold;
}

/* Status Badges */
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-block;
}

.status-pending { background: #fef3c7; color: #d97706; }
.status-approved { background: #dcfce3; color: #16a34a; }
.status-rejected { background: #fee2e2; color: #dc2626; }

/* Actions */
.action-buttons {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

/* Form Styles */
.form-section-title {
  font-size: 1.125rem;
  color: #1e293b;
  border-bottom: 2px solid #f1f5f9;
  padding-bottom: 0.5rem;
  margin-bottom: 1rem;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

.full-width {
    grid-column: 1 / -1;
}

.base-input-label {
  display: block;
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.35rem;
}

.custom-input, .custom-select {
  width: 100%;
  padding: 0.625rem 0.875rem;
  font-size: 1rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background-color: #ffffff;
  color: #1f2937;
  transition: all 0.2s ease-in-out;
  box-sizing: border-box;
}

.custom-input:focus, .custom-select:focus {
  outline: none;
  border-color: #4f46e5;
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

.days-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}

.day-checkbox {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  background: #f8fafc;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  transition: all 0.2s;
}

.day-checkbox:hover {
  background: #f1f5f9;
  border-color: #cbd5e1;
}

.day-checkbox input:checked + .day-label {
  color: #4f46e5;
  font-weight: 600;
}

/* Trạm Dừng */
.stations-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  max-height: 400px;
  overflow-y: auto;
  padding-right: 0.5rem;
}

.station-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1rem;
}

.station-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px dashed #cbd5e1;
}

.station-coordinate-action {
  display: flex;
  justify-content: flex-end;
}

/* Responsive */
@media (max-width: 768px) {
  .form-grid { grid-template-columns: 1fr; }
  .search-box { flex-direction: column; align-items: stretch; }
  .search-box > :first-child { margin-bottom: 0.5rem; }
  .action-buttons { flex-direction: column; }
}
</style>
