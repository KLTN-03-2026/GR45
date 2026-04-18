<script setup>
import { ref, reactive, onMounted } from "vue";
import { Eye, CheckCircle, Clock } from "lucide-vue-next";
import operatorApi from "@/api/operatorApi";
import BaseTable from "@/components/common/BaseTable.vue";
import BaseButton from "@/components/common/BaseButton.vue";
import BaseModal from "@/components/common/BaseModal.vue";
import BaseToast from "@/components/common/BaseToast.vue";
import { formatDateTime } from "@/utils/format";

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

    // Tùy theo base setup, getAlarms có thể nằm ở operatorApi.getAlarms
    const res = await operatorApi.getAlarms(params);
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

const openDetailModal = (item) => {
  detailData.value = JSON.parse(JSON.stringify(item));
  isDetailModal.value = true;
};

// Đổi trạng thái xử lý
const markAsReadOrHandled = async (item, status) => {
  try {
    // Nếu API có endpoint toggle trạng thái cụ thể, ví dụ đổi sang da_xem hoặc da_xu_ly
    // Giả sử có operatorApi.toggleAlarmStatus để toggle,
    // hoặc có thể call API truyền status nếu backend hỗ trợ patch trạng thái.
    // Tạm thời ở đây sử dụng toggle (có thể cần backend support truyền trạng thái cụ thể)
    await operatorApi.toggleAlarmStatus(item.id, { trang_thai: status });
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
  <div class="operator-page">
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
          Quản lý Cảnh Báo AI
        </h1>
        <p class="page-sub text-gray-500">
          Giám sát vi phạm AI, buồn ngủ, quá tốc độ của tài xế...
        </p>
      </div>
      <div>
        <!-- Nút dự phòng nếu cần tính năng tải báo cáo -->
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
          <div v-if="item.tai_xe?.cccd" class="text-xs text-gray-500">
            CCCD: {{ item.tai_xe.cccd }}
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
      title="Chi Tiết Cảnh Báo"
      maxWidth="600px"
    >
      <div v-if="detailData" class="space-y-4">
        <!-- Badge Container -->
        <div class="flex items-center justify-between mb-2">
          <div class="flex items-center gap-2">
            <span
              :class="[
                'px-3 py-1 rounded text-sm font-bold',
                getMucDo(detailData.muc_do).cls,
              ]"
            >
              🚨 Mức độ: {{ getMucDo(detailData.muc_do).text }}
            </span>
            <span
              :class="[
                'px-3 py-1 rounded text-sm font-bold',
                getTrangThai(detailData.trang_thai).cls,
              ]"
            >
              {{ getTrangThai(detailData.trang_thai).text }}
            </span>
          </div>
          <div
            class="text-gray-500 font-medium text-sm flex items-center gap-1"
          >
            <Clock size="16" /> {{ formatDateTime(detailData.created_at) }}
          </div>
        </div>

        <!-- Ảnh Vi Phạm Khung Nhìn -->
        <div
          v-if="detailData.anh_url"
          class="rounded-lg overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center p-2 mb-4 h-64"
        >
          <img
            :src="detailData.anh_url"
            alt="Ảnh vi phạm"
            class="max-h-full max-w-full object-contain rounded"
          />
        </div>
        <div
          v-else-if="detailData.du_lieu_phat_hien?.anh_url"
          class="rounded-lg overflow-hidden border border-gray-200 bg-gray-100 flex items-center justify-center p-2 mb-4 h-64"
        >
          <img
            :src="detailData.du_lieu_phat_hien.anh_url"
            alt="Ảnh vi phạm"
            class="max-h-full max-w-full object-contain rounded"
          />
        </div>
        <div
          v-else
          class="rounded-lg border-2 border-dashed border-gray-200 bg-gray-50 flex items-center justify-center p-6 text-gray-400 mb-4 h-40"
        >
          Không có ảnh đính kèm
        </div>

        <div class="bg-gray-50 p-4 rounded-lg border border-gray-100 space-y-3">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-xs text-gray-500 font-medium uppercase">
                Loại vi phạm
              </p>
              <p class="font-semibold text-gray-800 text-lg">
                {{
                  loaiBaoDongMap[detailData.loai_bao_dong] ||
                  detailData.loai_bao_dong
                }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 font-medium uppercase">
                Xe Vi Phạm
              </p>
              <p class="font-semibold text-gray-800 text-lg">
                {{ detailData.xe?.bien_so || "N/A" }}
              </p>
            </div>
          </div>

          <div class="border-t border-gray-200 pt-3 grid grid-cols-2 gap-4">
            <div>
              <p class="text-xs text-gray-500 font-medium uppercase">Tài Xế</p>
              <p class="font-medium text-gray-800">
                {{
                  detailData.tai_xe?.ho_va_ten ||
                  detailData.tai_xe?.email ||
                  "N/A"
                }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 font-medium uppercase">
                Chuyến Xe
              </p>
              <p class="font-medium text-blue-600">
                #{{ detailData.id_chuyen_xe }}
              </p>
            </div>
          </div>
        </div>

        <div
          class="bg-blue-50 p-4 rounded-lg border border-blue-100"
          v-if="detailData.du_lieu_phat_hien"
        >
          <h4 class="text-sm font-bold text-blue-900 mb-2">
            Dữ Liệu Sensor AI:
          </h4>
          <ul
            class="text-sm text-blue-800 space-y-1 font-mono bg-white p-3 rounded border border-blue-50"
          >
            <li v-for="(val, key) in detailData.du_lieu_phat_hien" :key="key">
              <span v-if="key !== 'anh_url'">
                <span class="font-bold text-blue-700">{{ key }}:</span>
                {{ val }}
              </span>
            </li>
          </ul>
        </div>

        <div
          class="flex gap-4 mt-4"
          v-if="
            detailData.trang_thai === 'moi' ||
            detailData.trang_thai === 'da_xem'
          "
        >
          <BaseButton
            class="flex-1"
            variant="outline"
            @click="markAsReadOrHandled(detailData, 'bo_qua')"
          >
            Bỏ Qua
          </BaseButton>
          <BaseButton
            class="flex-1"
            variant="primary"
            @click="markAsReadOrHandled(detailData, 'da_xu_ly')"
          >
            Đánh Dấu: Đã Xử Lý
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
