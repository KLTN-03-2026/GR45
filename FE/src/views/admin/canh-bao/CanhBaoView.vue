<script setup>
import { ref, reactive, onMounted } from "vue";
import { Eye, CheckCircle, Clock, MapPin, ExternalLink, User, AlertTriangle } from "lucide-vue-next";
import adminApi from "@/api/adminApi";
import BaseTable from "@/components/common/BaseTable.vue";
import BaseButton from "@/components/common/BaseButton.vue";
import BaseModal from "@/components/common/BaseModal.vue";
import BaseToast from "@/components/common/BaseToast.vue";
import { formatDateTime } from "@/utils/format";
 
// --- HELPERS ---
const formatDate = (str) => {
  if (!str) return "N/A";
  try {
    return new Date(str).toLocaleDateString('vi-VN');
  } catch (e) {
    return "N/A";
  }
};
 
const hasKeys = (obj) => {
  if (!obj || typeof obj !== 'object') return false;
  return Object.keys(obj).length > 0;
};

// --- TOAST ---
const toast = reactive({ visible: false, message: "", type: "success" });
const showToast = (msg, type = "success") => {
  toast.message = msg;
  toast.type = type;
  toast.visible = true;
  setTimeout(() => {
    toast.visible = false;
  }, 3500);
};

// --- MAPPING TỪ ĐIỂN ---
const loaiBaoDongMap = {
  ngu_gat: "Ngủ gật",
  qua_toc_do: "Quá tốc độ",
  phanh_gap: "Phanh gấp",
  lac_lan: "Lạc làn",
  roi_khoi_hanh_trinh: "Rời khỏi hành trình",
  khong_phan_hoi: "Không phản hồi",
  thiet_bi_loi: "Thiết bị lỗi",
  phat_hien_dao: "Phát hiện dao",
  su_dung_dien_thoai: "Sử dụng điện thoại",
  hut_thuoc: "Hút thuốc",
  mang_vu_khi: "Mang vũ khí",
  khong_quan_sat: "Không quan sát",
  bao_dong_khan_cap: "Khẩn cấp (SOS)",
  vi_pham_khac: "Vi phạm khác",
};

const mucDoMap = {
  thong_tin: { text: "Thông tin", cls: "badge-blue" },
  canh_bao: { text: "Cảnh báo", cls: "badge-yellow" },
  nguy_hiem: { text: "Nguy hiểm", cls: "badge-red" },
  khan_cap: { text: "Khẩn cấp", cls: "badge-red" },
};

const trangThaiMap = {
  moi: { text: "Mới", cls: "badge-red" },
  da_xem: { text: "Đã xem", cls: "badge-yellow" },
  da_xu_ly: { text: "Đã xử lý", cls: "badge-green" },
  bo_qua: { text: "Bỏ qua", cls: "badge-secondary" },
};

const getMucDo = (s) => mucDoMap[s] || { text: s || "—", cls: "" };
const getTrangThai = (s) => trangThaiMap[s] || { text: s || "—", cls: "" };

// --- DANH SÁCH BÁO ĐỘNG ---
const alarms = ref([]);
const loading = ref(false);
const pagination = reactive({
  currentPage: 1,
  perPage: 15,
  total: 0,
  lastPage: 1,
});

const filter = reactive({
  trang_thai: "",
  muc_do: "",
  loai_bao_dong: "",
});

const tableColumns = [
  { key: "id", label: "ID" },
  { key: "thoi_gian", label: "Thời Gian" },
  { key: "nha_xe", label: "Nhà Xe" },
  { key: "xe_chuyen", label: "Xe / Chuyến" },
  { key: "tai_xe", label: "Tài Xế" },
  { key: "loai_vi_pham", label: "Loại Vi Phạm" },
  { key: "muc_do", label: "Mức Độ" },
  { key: "trang_thai", label: "Trạng Thái" },
  { key: "actions", label: "Hành Động" },
];

const fetchAlarms = async (page = 1) => {
  try {
    loading.value = true;
    const params = {
      page,
      limit: pagination.perPage,
      trang_thai: filter.trang_thai || undefined,
      muc_do: filter.muc_do || undefined,
      loai_bao_dong: filter.loai_bao_dong || undefined,
    };

    const res = await adminApi.getAlerts(params);
    let list = [],
      info = {};

    if (res.data?.data?.data) {
      list = res.data.data.data;
      info = res.data.data;
    } else if (res.data?.data) {
      list = res.data.data;
      info = res.data;
    }

    alarms.value = Array.isArray(list) ? list : [];
    pagination.currentPage = info.current_page || 1;
    pagination.total = info.total || 0;
    pagination.lastPage = info.last_page || 1;
  } catch (e) {
    console.error(e);
    showToast("Không thể tải danh sách báo động!", "error");
  } finally {
    loading.value = false;
  }
};

// --- CHI TIẾT BÁO ĐỘNG ---
const isDetailModal = ref(false);
const detailData = ref(null);

const openDetailModal = async (item) => {
  try {
    const res = await adminApi.getAlertDetail(item.id);
    
    // Axios interceptor trả về root JSON { success, message, data }
    // Nếu payload bọc trong key 'data' thì lấy, nếu không thì dùng trực tiếp object res
    if (res && res.data) {
      detailData.value = res.data;
    } else {
      detailData.value = res;
    }
    
    isDetailModal.value = true;
  } catch (err) {
    console.error("Error loading alert detail:", err);
    // Fallback: sử dụng trực tiếp thông tin từ item của dòng được click
    detailData.value = JSON.parse(JSON.stringify(item));
    isDetailModal.value = true;
  }
};

// Đổi trạng thái xử lý
const markAsReadOrHandled = async (item, status) => {
  try {
    await adminApi.toggleAlertStatus(item.id, { trang_thai: status });
    showToast(`Đã thay đổi trạng thái thành công!`);

    if (isDetailModal.value && detailData.value?.id === item.id) {
      isDetailModal.value = false;
    }
    fetchAlarms(pagination.currentPage);
  } catch (e) {
    showToast("Lỗi khi xử lý báo động!", "error");
  }
};

onMounted(() => {
  fetchAlarms();
});
</script>

<template>
  <div class="admin-page p-6">
    <BaseToast
      :visible="toast.visible"
      :message="toast.message"
      :type="toast.type"
    />

    <!-- Tiêu đề -->
    <div
      class="page-header gap-3 flex flex-wrap justify-between items-center mb-6"
    >
      <div>
        <h1 class="page-title text-2xl font-bold text-gray-800">
          Quản lý Cảnh Báo Hệ Thống
        </h1>
        <p class="page-sub text-gray-500">
          Giám sát toàn bộ các báo động an toàn, vi phạm AI từ tài xế trên hệ thống.
        </p>
      </div>
      <div>
        <BaseButton variant="outline" @click="fetchAlarms(1)">
          🔄 Làm mới
        </BaseButton>
      </div>
    </div>

    <!-- Bộ lọc -->
    <div
      class="filter-card bg-white p-4 rounded-xl shadow-sm mb-6 flex flex-wrap gap-4 items-end border border-gray-100"
    >
      <div class="filter-group flex flex-col min-w-[150px]">
        <label class="text-sm text-gray-500 mb-1">Loại Báo Động</label>
        <select
          v-model="filter.loai_bao_dong"
          @change="fetchAlarms(1)"
          class="custom-select p-2 border border-gray-300 rounded-lg outline-none focus:border-primary"
        >
          <option value="">Tất cả</option>
          <option v-for="(val, key) in loaiBaoDongMap" :key="key" :value="key">
            {{ val }}
          </option>
        </select>
      </div>

      <div class="filter-group flex flex-col min-w-[150px]">
        <label class="text-sm text-gray-500 mb-1">Mức Độ</label>
        <select
          v-model="filter.muc_do"
          @change="fetchAlarms(1)"
          class="custom-select p-2 border border-gray-300 rounded-lg outline-none focus:border-primary"
        >
          <option value="">Tất cả</option>
          <option value="thong_tin">Thông tin</option>
          <option value="canh_bao">Cảnh báo</option>
          <option value="nguy_hiem">Nguy hiểm</option>
          <option value="khan_cap">Khẩn cấp</option>
        </select>
      </div>

      <div class="filter-group flex flex-col min-w-[150px]">
        <label class="text-sm text-gray-500 mb-1">Trạng Thái</label>
        <select
          v-model="filter.trang_thai"
          @change="fetchAlarms(1)"
          class="custom-select p-2 border border-gray-300 rounded-lg outline-none focus:border-primary"
        >
          <option value="">Tất cả</option>
          <option value="moi">Mới</option>
          <option value="da_xem">Đã xem</option>
          <option value="da_xu_ly">Đã xử lý</option>
        </select>
      </div>

      <BaseButton
        @click="
          filter.trang_thai = '';
          filter.muc_do = '';
          filter.loai_bao_dong = '';
          fetchAlarms(1);
        "
        variant="secondary"
        class="h-[42px]"
      >
        Lọc Lại
      </BaseButton>
    </div>

    <!-- Bảng Dữ Liệu -->
    <div
      class="table-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
    >
      <BaseTable
        :columns="tableColumns"
        :data="alarms"
        :loading="loading"
        @row-click="openDetailModal"
      >
        <template #cell(thoi_gian)="{ item }">
          <div class="text-sm text-gray-600 font-medium">
            {{ formatDateTime(item.created_at) }}
          </div>
        </template>

        <template #cell(nha_xe)="{ item }">
          <div class="font-medium text-blue-700">
            {{ item.tai_xe?.ma_nha_xe || "N/A" }}
          </div>
        </template>

        <template #cell(xe_chuyen)="{ item }">
          <div
            class="font-medium text-gray-900 border border-gray-200 inline-block px-2 py-0.5 rounded text-sm bg-gray-50"
          >
            {{ item.xe?.bien_so || "Xe #" + item.id_xe }}
          </div>
          <div class="text-xs text-gray-500 mt-1">
            Chuyến: <span class="font-medium">#{{ item.id_chuyen_xe }}</span>
          </div>
        </template>

        <template #cell(tai_xe)="{ item }">
          <div class="text-sm font-medium text-gray-800">
            {{
              item.tai_xe?.ho_va_ten ||
              item.tai_xe?.email ||
              "TX #" + item.id_tai_xe
            }}
          </div>
        </template>

        <template #cell(loai_vi_pham)="{ item }">
          <span class="font-semibold text-gray-700">
            {{ loaiBaoDongMap[item.loai_bao_dong] || item.loai_bao_dong }}
          </span>
        </template>

        <template #cell(muc_do)="{ value }">
          <span
            :class="[
              'px-2 py-1 text-xs font-semibold rounded-full',
              getMucDo(value).cls,
            ]"
          >
            {{ getMucDo(value).text }}
          </span>
        </template>

        <template #cell(trang_thai)="{ value }">
          <span
            :class="[
              'px-2 py-1 text-xs font-semibold rounded-full',
              getTrangThai(value).cls,
            ]"
          >
            {{ getTrangThai(value).text }}
          </span>
        </template>

        <template #cell(actions)="{ item }">
          <div class="flex items-center gap-2">
            <BaseButton
              size="sm"
              variant="outline"
              title="Xem Chi Tiết"
              class="w-8 h-8 !p-0 flex items-center justify-center rounded border-gray-200 text-info hover:bg-blue-50"
              @click.stop="openDetailModal(item)"
            >
              <Eye size="16" />
            </BaseButton>

            <BaseButton
              v-if="item.trang_thai === 'moi' || item.trang_thai === 'da_xem'"
              size="sm"
              variant="outline"
              title="Đánh dấu đã xử lý"
              class="w-8 h-8 !p-0 flex items-center justify-center rounded border-gray-200 text-success hover:bg-green-50"
              @click.stop="markAsReadOrHandled(item, 'da_xu_ly')"
            >
              <CheckCircle size="16" />
            </BaseButton>
          </div>
        </template>
      </BaseTable>

      <!-- Phân trang -->
      <div
        class="flex items-center justify-between p-4 border-t border-gray-100 bg-gray-50"
      >
        <div class="flex items-center gap-2 text-sm text-gray-600">
          <span>Hiển thị:</span>
          <select
            v-model="pagination.perPage"
            @change="fetchAlarms(1)"
            class="border border-gray-300 rounded px-2 py-1 outline-none"
          >
            <option :value="10">10</option>
            <option :value="15">15</option>
            <option :value="30">30</option>
            <option :value="50">50</option>
          </select>
          <span>/ trang</span>
          <span v-if="pagination.total > 0" class="ml-2 font-medium"
            >(Tổng: {{ pagination.total }})</span
          >
        </div>
        <div class="flex gap-2 items-center">
          <BaseButton
            size="sm"
            variant="outline"
            :disabled="pagination.currentPage <= 1"
            class="text-xs"
            @click="fetchAlarms(pagination.currentPage - 1)"
          >
            ← Trước
          </BaseButton>
          <span class="text-sm font-medium text-gray-600 px-2">
            Trang {{ pagination.currentPage }} / {{ pagination.lastPage }}
          </span>
          <BaseButton
            size="sm"
            variant="outline"
            class="text-xs"
            :disabled="pagination.currentPage >= pagination.lastPage"
            @click="fetchAlarms(pagination.currentPage + 1)"
          >
            Sau →
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- MODAL CHI TIẾT BÁO ĐỘNG -->
    <BaseModal
      v-model="isDetailModal"
      title="Chi Tiết Cảnh Báo Vi Phạm"
      maxWidth="700px"
    >
      <div v-if="detailData" class="space-y-5">
        <!-- Tình trạng & Thời gian -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3">
          <div class="flex items-center gap-2">
            <span
              :class="[
                'px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider',
                getMucDo(detailData.muc_do).cls,
              ]"
            >
              Mức độ: {{ getMucDo(detailData.muc_do).text }}
            </span>
            <span
              :class="[
                'px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider',
                getTrangThai(detailData.trang_thai).cls,
              ]"
            >
              {{ getTrangThai(detailData.trang_thai).text }}
            </span>
          </div>
          <div class="text-gray-500 font-medium text-sm flex items-center gap-1.5">
            <Clock size="15" class="text-gray-400" />
            {{ detailData.created_at ? formatDateTime(detailData.created_at) : "—" }}
          </div>
        </div>

        <!-- Ảnh Vi Phạm Vi Phạm -->
        <div>
          <div
            v-if="detailData.anh_url"
            class="rounded-xl overflow-hidden border border-gray-200 bg-black shadow-sm flex items-center justify-center h-64"
          >
            <img
              :src="detailData.anh_url"
              alt="Ảnh vi phạm"
              class="max-h-full w-auto object-contain"
            />
          </div>
          <div
            v-else-if="detailData.du_lieu_phat_hien?.anh_url"
            class="rounded-xl overflow-hidden border border-gray-200 bg-black shadow-sm flex items-center justify-center h-64"
          >
            <img
              :src="detailData.du_lieu_phat_hien.anh_url"
              alt="Ảnh vi phạm"
              class="max-h-full w-auto object-contain"
            />
          </div>
          <div
            v-else
            class="rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 flex flex-col items-center justify-center p-6 text-gray-400 h-40 shadow-inner"
          >
            <Eye size="32" class="mb-2 opacity-50" />
            <p class="text-sm font-medium">Không có hình ảnh bằng chứng</p>
          </div>
        </div>

        <!-- Section chi tiết vi phạm chính -->
        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex items-start gap-4">
          <div class="bg-blue-100 p-3 rounded-full text-blue-600">
            <AlertTriangle size="24" />
          </div>
          <div class="flex-1">
            <p class="text-xs font-bold text-blue-500 uppercase tracking-wide">Nội dung cảnh báo</p>
            <h3 class="text-xl font-bold text-blue-900 mt-0.5">
              {{ loaiBaoDongMap[detailData.loai_bao_dong] || detailData.loai_bao_dong }}
            </h3>
            <div v-if="detailData.vi_do_luc_bao && detailData.kinh_do_luc_bao" class="mt-2 inline-flex items-center gap-2 bg-white px-3 py-1.5 rounded-lg border border-blue-200 text-sm font-medium text-blue-600 hover:bg-blue-100 cursor-pointer">
              <MapPin size="15" />
              <a
                :href="`https://www.google.com/maps?q=${detailData.vi_do_luc_bao},${detailData.kinh_do_luc_bao}`"
                target="_blank"
                class="flex items-center gap-1"
              >
                Xem vị trí trên bản đồ
                <ExternalLink size="12" />
              </a>
            </div>
          </div>
        </div>

        <!-- GRID THÔNG TIN -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          
          <!-- Cụm Nhà Xe & Xử lý -->
          <div class="space-y-6">
            <!-- Thông tin Nhà Xe -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
              <div class="bg-gradient-to-r from-blue-700 to-indigo-600 px-4 py-2.5 flex justify-between items-center">
                <span class="text-white font-bold text-sm uppercase tracking-wide">Thông tin Nhà Xe</span>
                <span class="bg-white/20 text-white text-xs px-2 py-0.5 rounded font-medium">{{ detailData.tai_xe?.ma_nha_xe || 'NX' }}</span>
              </div>
              <div class="p-4 space-y-3">
                <div>
                  <span class="text-gray-400 text-xs block font-medium uppercase">Tên Nhà Xe</span>
                  <span class="font-bold text-gray-800 text-base leading-tight">
                    {{ detailData.nha_xe_xu_ly?.ten_nha_xe || detailData.tai_xe?.ma_nha_xe || "Chưa xác định" }}
                  </span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm pt-2 border-t border-gray-50">
                  <div>
                    <span class="text-gray-400 text-xs block">Số điện thoại:</span>
                    <span class="font-semibold text-gray-700">{{ detailData.nha_xe_xu_ly?.so_dien_thoai || "N/A" }}</span>
                  </div>
                  <div>
                    <span class="text-gray-400 text-xs block">Email:</span>
                    <span class="font-medium text-gray-600 truncate block" :title="detailData.nha_xe_xu_ly?.email">{{ detailData.nha_xe_xu_ly?.email || "N/A" }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Thông tin Chuyến Xe -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
              <div class="bg-gray-100 px-4 py-2.5 border-b border-gray-200">
                <span class="text-gray-700 font-bold text-sm uppercase tracking-wide">Lịch trình Chuyến Xe</span>
              </div>
              <div class="p-4 space-y-3 text-sm">
                <div class="flex justify-between items-center">
                  <div>
                    <span class="text-gray-400 text-xs block uppercase">Mã Chuyến</span>
                    <span class="font-extrabold text-indigo-600 text-lg">#{{ detailData.id_chuyen_xe }}</span>
                  </div>
                  <div class="text-right">
                    <span class="text-gray-400 text-xs block uppercase">Trạng thái</span>
                    <span class="inline-block bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs font-bold capitalize">
                      {{ detailData.chuyen_xe?.trang_thai || "N/A" }}
                    </span>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-2 border-t border-gray-50 pt-2">
                  <div>
                    <span class="text-gray-400 text-xs block">Ngày khởi hành:</span>
                    <span class="font-semibold text-gray-800">{{ formatDate(detailData.chuyen_xe?.ngay_khoi_hanh) }}</span>
                  </div>
                  <div>
                    <span class="text-gray-400 text-xs block">Giờ đi:</span>
                    <span class="font-semibold text-gray-800">{{ detailData.chuyen_xe?.gio_khoi_hanh || "N/A" }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Cụm Phương Tiện & Tài xế -->
          <div class="space-y-6">
            <!-- Thông tin Phương tiện -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
               <div class="bg-gray-100 px-4 py-2.5 border-b border-gray-200 flex items-center gap-2">
                <span class="text-gray-700 font-bold text-sm uppercase tracking-wide">Phương tiện</span>
              </div>
              <div class="p-4 flex gap-4">
                <div class="w-20 h-20 rounded-lg bg-gray-100 flex-shrink-0 overflow-hidden border border-gray-200 shadow-inner">
                  <img v-if="detailData.xe?.hinh_anh" :src="detailData.xe.hinh_anh" class="w-full h-full object-cover" />
                  <div v-else class="w-full h-full flex items-center justify-center text-gray-400 font-bold">BUS</div>
                </div>
                <div class="flex-1 min-w-0 text-sm space-y-1">
                  <div>
                    <span class="font-bold text-gray-800 text-base block truncate" :title="detailData.xe?.ten_xe">{{ detailData.xe?.ten_xe || "Chưa rõ tên xe" }}</span>
                  </div>
                  <div class="bg-amber-50 border border-amber-200 text-amber-800 inline-block px-2 py-0.5 rounded font-bold tracking-wide text-sm">
                    {{ detailData.xe?.bien_so || "BIỂN SỐ N/A" }}
                  </div>
                  <div class="text-xs text-gray-500 pt-1 font-medium">
                     Sức chứa: {{ detailData.xe?.so_ghe_thuc_te || '—' }} ghế 
                  </div>
                </div>
              </div>
            </div>

            <!-- Thông tin Tài xế -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
              <div class="bg-gray-100 px-4 py-2.5 border-b border-gray-200">
                <span class="text-gray-700 font-bold text-sm uppercase tracking-wide">Tài xế điều khiển</span>
              </div>
              <div class="p-4 flex gap-4">
                <div class="w-16 h-16 rounded-full bg-gray-100 flex-shrink-0 border-2 border-white shadow-md overflow-hidden">
                  <img v-if="detailData.tai_xe?.avatar" :src="detailData.tai_xe.avatar" class="w-full h-full object-cover" />
                  <div v-else class="w-full h-full flex items-center justify-center text-gray-400"><User size="24" /></div>
                </div>
                <div class="flex-1 text-sm">
                  <span class="font-bold text-gray-800 text-base block">{{ detailData.tai_xe?.ho_va_ten || "N/A" }}</span>
                  <div class="grid grid-cols-1 gap-0.5 mt-1 text-gray-600 text-xs">
                    <div class="flex items-center gap-1">
                      <span class="font-bold text-gray-400 w-12">SĐT:</span>
                      <span>{{ detailData.tai_xe?.so_dien_thoai || "N/A" }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                      <span class="font-bold text-gray-400 w-12">CCCD:</span>
                      <span>{{ detailData.tai_xe?.cccd || "N/A" }}</span>
                    </div>
                    <div class="flex items-center gap-1 truncate">
                      <span class="font-bold text-gray-400 w-12">Email:</span>
                      <span class="truncate">{{ detailData.tai_xe?.email || "N/A" }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Dữ liệu cảm biến / Thông số phát hiện -->
        <div v-if="hasKeys(detailData.du_lieu_phat_hien)" class="border border-gray-200 rounded-xl overflow-hidden bg-gray-50">
          <div class="bg-gray-800 text-white px-4 py-2 text-xs font-bold tracking-wider uppercase">
            Thông số phân tích từ AI Camera & Sensors
          </div>
          <div class="p-4 grid grid-cols-2 gap-3">
            <template v-for="(val, key) in detailData.du_lieu_phat_hien" :key="key">
              <div v-if="key !== 'anh_url' && val !== null" class="bg-white p-2 border border-gray-100 rounded shadow-sm">
                <span class="text-xs font-medium text-gray-500 uppercase block">{{ key.replace(/_/g, ' ') }}</span>
                <span class="font-mono font-bold text-blue-600 text-sm">
                   {{ typeof val === 'object' ? JSON.stringify(val) : val }}
                </span>
              </div>
            </template>
          </div>
        </div>

        <!-- Phần xử lý ghi chú nếu đã hoàn thành -->
        <div v-if="detailData.admin_id || detailData.ghi_chu_xu_ly || detailData.thoi_gian_xu_ly" class="bg-green-50 border border-green-100 rounded-xl p-4 shadow-sm">
          <h4 class="text-sm font-bold text-green-800 flex items-center gap-2 mb-2.5">
            <CheckCircle size="16" /> Kết quả xử lý từ hệ thống
          </h4>
          <div class="grid grid-cols-2 text-sm gap-4 bg-white p-3 rounded-lg border border-green-100">
            <div>
              <span class="text-gray-400 block text-xs font-medium">Người xử lý</span>
              <span class="font-bold text-gray-800 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                {{ detailData.admin_xu_ly?.ho_va_ten || 'Hệ thống tự động' }}
              </span>
            </div>
            <div>
              <span class="text-gray-400 block text-xs font-medium">Thời gian ghi nhận</span>
              <span class="font-semibold text-gray-700">{{ detailData.thoi_gian_xu_ly ? formatDateTime(detailData.thoi_gian_xu_ly) : 'N/A' }}</span>
            </div>
            <div class="col-span-2 pt-2 border-t border-gray-50" v-if="detailData.ghi_chu_xu_ly">
              <span class="text-gray-400 block text-xs font-medium mb-1">Ghi chú nghiệp vụ:</span>
              <p class="text-gray-700 font-medium">{{ detailData.ghi_chu_xu_ly }}</p>
            </div>
          </div>
        </div>

        <!-- Hành động -->
        <div
          class="flex gap-4 pt-4 border-t border-gray-100 mt-2"
          v-if="
            detailData.trang_thai === 'moi' ||
            detailData.trang_thai === 'da_xem'
          "
        >
          <BaseButton
            class="flex-1 font-bold"
            variant="outline"
            size="lg"
            @click="markAsReadOrHandled(detailData, 'bo_qua')"
          >
            Bỏ Qua
          </BaseButton>
          <BaseButton
            class="flex-1 font-bold shadow-md"
            variant="primary"
            size="lg"
            @click="markAsReadOrHandled(detailData, 'da_xu_ly')"
          >
            Xác Nhận Đã Xử Lý
          </BaseButton>
        </div>
      </div>
    </BaseModal>
  </div>
</template>

<style scoped>
.badge-red {
  background-color: #fee2e2;
  color: #b91c1c;
}
.badge-yellow {
  background-color: #fef3c7;
  color: #b45309;
}
.badge-green {
  background-color: #d1fae5;
  color: #047857;
}
.badge-blue {
  background-color: #dbeafe;
  color: #1d4ed8;
}
.badge-secondary {
  background-color: #f3f4f6;
  color: #4b5563;
}
</style>
