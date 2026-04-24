<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { useRouter } from "vue-router";
import clientApi from "@/api/clientApi";

const router = useRouter();

const allTickets = ref([]);
const isLoading = ref(false);
const listMessage = ref({ type: "", text: "" });

const statusFilter = ref("all");
const currentPage = ref(1);
const pageSize = 10;

const detailOpen = ref(false);
const detailLoading = ref(false);
const detailTicket = ref(null);
const detailError = ref("");

/** Phân loại vé cho lọc & nhãn (API: dang_cho / da_thanh_toan / huy; có thể có da_hoan_thanh) */
function isPastTrip(ticket) {
  const cx = ticket?.chuyen_xe;
  if (!cx?.ngay_khoi_hanh) return false;
  const gio = (cx.gio_khoi_hanh || "00:00").toString().substring(0, 5);
  const dep = new Date(`${cx.ngay_khoi_hanh}T${gio}:00`);
  return dep.getTime() < Date.now();
}

function categoryOf(ticket) {
  const tt = ticket?.tinh_trang;
  if (tt === "dang_cho") return "dang_cho";
  if (tt === "huy" || tt === "da_huy") return "huy";
  if (tt === "da_hoan_thanh") return "hoan_thanh";
  if (tt === "da_thanh_toan") {
    return isPastTrip(ticket) ? "hoan_thanh" : "da_thanh_toan";
  }
  return "unknown";
}

function sortKey(ticket) {
  const raw = ticket?.thoi_gian_dat || ticket?.created_at;
  if (raw) return new Date(raw).getTime();
  const cx = ticket?.chuyen_xe;
  if (cx?.ngay_khoi_hanh) {
    const gio = (cx.gio_khoi_hanh || "00:00").toString().substring(0, 5);
    return new Date(`${cx.ngay_khoi_hanh}T${gio}:00`).getTime();
  }
  return 0;
}

const statusLabel = (ticket) => {
  const map = {
    dang_cho: "Chờ thanh toán",
    da_thanh_toan: "Đã thanh toán",
    hoan_thanh: "Đã hoàn thành",
    huy: "Đã hủy",
    unknown: "Không xác định",
  };
  return map[categoryOf(ticket)] || map.unknown;
};

const statusBadgeClass = (ticket) => {
  const c = categoryOf(ticket);
  const map = {
    dang_cho: "bg-amber-100 text-amber-800 border-amber-200",
    da_thanh_toan: "bg-emerald-100 text-emerald-800 border-emerald-200",
    hoan_thanh: "bg-sky-100 text-sky-800 border-sky-200",
    huy: "bg-rose-100 text-rose-800 border-rose-200",
    unknown: "bg-slate-100 text-slate-700 border-slate-200",
  };
  return map[c] || map.unknown;
};

const filterOptions = [
  { value: "all", label: "Tất cả" },
  { value: "dang_cho", label: "Chờ thanh toán" },
  { value: "da_thanh_toan", label: "Đã thanh toán" },
  { value: "hoan_thanh", label: "Đã hoàn thành" },
  { value: "huy", label: "Đã hủy" },
];

const filteredTickets = computed(() => {
  const list = allTickets.value;
  if (statusFilter.value === "all") return list;
  return list.filter((t) => categoryOf(t) === statusFilter.value);
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredTickets.value.length / pageSize)));

const pagedTickets = computed(() => {
  const start = (currentPage.value - 1) * pageSize;
  return filteredTickets.value.slice(start, start + pageSize);
});

watch(statusFilter, () => {
  currentPage.value = 1;
});

watch(filteredTickets, () => {
  if (currentPage.value > totalPages.value) currentPage.value = totalPages.value;
});

const formatCurrency = (value) => {
  if (value == null || value === "") return "0đ";
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(value);
};

const formatDate = (dateStr) => {
  if (!dateStr) return "—";
  return new Date(dateStr).toLocaleDateString("vi-VN", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};

const formatTime = (timeStr) => {
  if (!timeStr) return "—";
  const s = String(timeStr);
  return s.length >= 5 ? s.substring(0, 5) : s;
};

const formatDateTime = (iso) => {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "—";
  return d.toLocaleString("vi-VN", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const paymentLabel = (code) => {
  const map = {
    tien_mat: "Tiền mặt",
    chuyen_khoan: "Chuyển khoản",
    vi_dien_tu: "Ví điện tử",
  };
  return map[code] || code || "—";
};

async function loadAllTickets() {
  isLoading.value = true;
  listMessage.value = { type: "", text: "" };
  try {
    const acc = [];
    let page = 1;
    let lastPage = 1;
    do {
      const res = await clientApi.getTickets({ page, per_page: 50 });
      if (!res?.success) break;
      const payload = res.data;
      acc.push(...(payload.data || []));
      lastPage = payload.last_page || 1;
      page += 1;
      if (page > 40) break;
    } while (page <= lastPage);

    acc.sort((a, b) => sortKey(b) - sortKey(a));
    allTickets.value = acc;
  } catch {
    listMessage.value = { type: "error", text: "Không thể tải lịch sử đặt vé." };
    allTickets.value = [];
  } finally {
    isLoading.value = false;
  }
}

async function openDetail(ticket) {
  detailOpen.value = true;
  detailError.value = "";
  detailTicket.value = { ...ticket };
  detailLoading.value = true;
  try {
    const res = await clientApi.getTicket(ticket.id);
    if (res?.success && res.data) {
      detailTicket.value = res.data;
    }
  } catch {
    detailTicket.value = ticket;
    detailError.value = "Không tải được chi tiết đầy đủ từ máy chủ — hiển thị theo dữ liệu danh sách.";
  } finally {
    detailLoading.value = false;
  }
}

function closeDetail() {
  detailOpen.value = false;
  detailTicket.value = null;
  detailError.value = "";
}

onMounted(() => loadAllTickets());
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900 font-sans py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <button
            type="button"
            class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-blue-600 mb-2"
            @click="router.push('/profile')"
          >
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            Quay lại hồ sơ
          </button>
          <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">Lịch sử đặt vé</h1>
          <p class="text-slate-500 text-sm mt-1">Danh sách vé theo thời gian mới nhất.</p>
        </div>
      </div>

      <div v-if="listMessage.text" :class="listMessage.type === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-amber-50 text-amber-800 border-amber-200'" class="mb-6 p-4 rounded-2xl text-sm font-medium border">
        {{ listMessage.text }}
      </div>

      <!-- Lọc trạng thái -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5 mb-6">
        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Lọc theo trạng thái</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="opt in filterOptions"
            :key="opt.value"
            type="button"
            @click="statusFilter = opt.value"
            :class="[
              'px-4 py-2 rounded-xl text-sm font-semibold border transition-all',
              statusFilter === opt.value
                ? 'bg-blue-600 text-white border-blue-600 shadow-md'
                : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100',
            ]"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <!-- Danh sách -->
      <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div v-if="isLoading" class="py-24 text-center">
          <span class="material-symbols-outlined animate-spin text-4xl text-blue-600">refresh</span>
          <p class="mt-4 text-slate-500 font-medium">Đang tải vé...</p>
        </div>

        <div v-else-if="filteredTickets.length === 0" class="py-20 text-center px-4">
          <span class="material-symbols-outlined text-6xl text-slate-200">history</span>
          <p class="mt-4 text-slate-600 font-medium">Không có vé phù hợp.</p>
          <button type="button" @click="router.push('/')" class="mt-6 text-blue-600 font-bold hover:underline">
            Đặt vé ngay
          </button>
        </div>

        <ul v-else class="divide-y divide-slate-100">
          <li
            v-for="ticket in pagedTickets"
            :key="ticket.id"
            class="p-5 sm:p-6 hover:bg-slate-50/80 cursor-pointer transition-colors"
            @click="openDetail(ticket)"
          >
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
              <div class="min-w-0 flex-1 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                  <span :class="statusBadgeClass(ticket)" class="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wide border">
                    {{ statusLabel(ticket) }}
                  </span>
                  <span class="text-slate-400 text-xs font-mono">Mã vé: {{ ticket.ma_ve }}</span>
                </div>
                <div class="flex items-start gap-3 text-slate-900">
                  <span class="material-symbols-outlined text-blue-600 shrink-0">route</span>
                  <div>
                    <p class="font-bold text-base leading-snug">
                      {{ ticket.chuyen_xe?.tuyen_duong?.diem_bat_dau || "—" }}
                      <span class="text-slate-400 font-normal px-1">→</span>
                      {{ ticket.chuyen_xe?.tuyen_duong?.diem_ket_thuc || "—" }}
                    </p>
                    <p v-if="ticket.chuyen_xe?.tuyen_duong?.ten_tuyen_duong" class="text-sm text-slate-500 mt-0.5 truncate">
                      {{ ticket.chuyen_xe.tuyen_duong.ten_tuyen_duong }}
                    </p>
                  </div>
                </div>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-600">
                  <span class="inline-flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px] text-slate-400">calendar_today</span>
                    {{ formatDate(ticket.chuyen_xe?.ngay_khoi_hanh) }}
                  </span>
                  <span class="inline-flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px] text-slate-400">schedule</span>
                    {{ formatTime(ticket.chuyen_xe?.gio_khoi_hanh) }}
                  </span>
                </div>
              </div>
              <div class="text-right shrink-0">
                <p class="text-lg font-bold text-blue-700">{{ formatCurrency(ticket.tong_tien) }}</p>
                <p class="text-xs text-blue-600 font-semibold mt-2">Xem chi tiết →</p>
              </div>
            </div>
          </li>
        </ul>

        <!-- Phân trang (client) -->
        <div v-if="!isLoading && filteredTickets.length > pageSize" class="flex flex-col sm:flex-row items-center justify-between gap-4 px-5 py-4 bg-slate-50/80 border-t border-slate-100">
          <p class="text-sm text-slate-500">
            Trang <span class="font-bold text-slate-800">{{ currentPage }}</span> / {{ totalPages }}
            <span class="text-slate-400">({{ filteredTickets.length }} vé)</span>
          </p>
          <div class="flex gap-2">
            <button
              type="button"
              class="p-2 rounded-xl border border-slate-200 hover:bg-white disabled:opacity-30"
              :disabled="currentPage <= 1"
              @click="currentPage--"
            >
              <span class="material-symbols-outlined">chevron_left</span>
            </button>
            <button
              type="button"
              class="p-2 rounded-xl border border-slate-200 hover:bg-white disabled:opacity-30"
              :disabled="currentPage >= totalPages"
              @click="currentPage++"
            >
              <span class="material-symbols-outlined">chevron_right</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal chi tiết -->
    <Teleport to="body">
      <div
        v-if="detailOpen"
        class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-0 sm:p-4"
        aria-modal="true"
        role="dialog"
      >
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeDetail" />
        <div
          class="relative w-full sm:max-w-lg max-h-[90vh] overflow-y-auto bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl border border-slate-200"
          @click.stop
        >
          <div class="sticky top-0 z-10 flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-white rounded-t-3xl">
            <h2 class="text-lg font-bold text-slate-900">Chi tiết vé</h2>
            <button type="button" class="p-2 rounded-xl hover:bg-slate-100 text-slate-500" @click="closeDetail" aria-label="Đóng">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div v-if="detailLoading" class="p-12 text-center">
            <span class="material-symbols-outlined animate-spin text-3xl text-blue-600">refresh</span>
          </div>

          <div v-else-if="detailTicket" class="p-5 sm:p-6 space-y-6 pb-8">
            <p v-if="detailError" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-xl p-3">
              {{ detailError }}
            </p>

            <div class="flex flex-wrap gap-2 items-center">
              <span :class="statusBadgeClass(detailTicket)" class="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wide border">
                {{ statusLabel(detailTicket) }}
              </span>
              <span class="text-sm font-mono font-bold text-slate-700">Mã vé: {{ detailTicket.ma_ve }}</span>
            </div>

            <section class="space-y-2">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tuyến &amp; thời gian</h3>
              <p class="font-bold text-slate-900">
                {{ detailTicket.chuyen_xe?.tuyen_duong?.diem_bat_dau || "—" }}
                →
                {{ detailTicket.chuyen_xe?.tuyen_duong?.diem_ket_thuc || "—" }}
              </p>
              <p v-if="detailTicket.chuyen_xe?.tuyen_duong?.ten_tuyen_duong" class="text-sm text-slate-600">
                {{ detailTicket.chuyen_xe.tuyen_duong.ten_tuyen_duong }}
              </p>
              <p v-if="detailTicket.chuyen_xe?.tuyen_duong?.nha_xe?.ten_nha_xe" class="text-sm text-slate-500">
                Nhà xe: {{ detailTicket.chuyen_xe.tuyen_duong.nha_xe.ten_nha_xe }}
              </p>
              <div class="flex flex-wrap gap-4 text-sm pt-2">
                <span>Ngày khởi hành: <strong>{{ formatDate(detailTicket.chuyen_xe?.ngay_khoi_hanh) }}</strong></span>
                <span>Giờ: <strong>{{ formatTime(detailTicket.chuyen_xe?.gio_khoi_hanh) }}</strong></span>
              </div>
            </section>

            <section class="space-y-2">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Khách &amp; thanh toán</h3>
              <p class="text-sm">
                Người đặt: <strong>{{ detailTicket.khach_hang?.ho_va_ten || "—" }}</strong>
                <span v-if="detailTicket.khach_hang?.so_dien_thoai" class="text-slate-600"> — {{ detailTicket.khach_hang.so_dien_thoai }}</span>
              </p>
              <p class="text-sm">Phương thức: {{ paymentLabel(detailTicket.phuong_thuc_thanh_toan) }}</p>
              <p class="text-sm">Thời gian đặt: {{ formatDateTime(detailTicket.thoi_gian_dat) }}</p>
              <p v-if="detailTicket.thoi_gian_thanh_toan" class="text-sm">Thanh toán lúc: {{ formatDateTime(detailTicket.thoi_gian_thanh_toan) }}</p>
              <div class="flex flex-wrap gap-6 pt-2 border-t border-slate-100 mt-2">
                <div>
                  <p class="text-xs text-slate-500">Tổng tiền</p>
                  <p class="text-xl font-bold text-blue-700">{{ formatCurrency(detailTicket.tong_tien) }}</p>
                </div>
                <div v-if="detailTicket.tien_khuyen_mai > 0">
                  <p class="text-xs text-slate-500">Giảm giá</p>
                  <p class="text-lg font-semibold text-emerald-700">-{{ formatCurrency(detailTicket.tien_khuyen_mai) }}</p>
                </div>
              </div>
            </section>

            <section v-if="detailTicket.chi_tiet_ves?.length" class="space-y-2">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ghế &amp; trạm</h3>
              <ul class="space-y-2">
                <li
                  v-for="(ct, idx) in detailTicket.chi_tiet_ves"
                  :key="ct.id || idx"
                  class="text-sm bg-slate-50 rounded-xl px-4 py-3 border border-slate-100"
                >
                  <span class="font-bold">Ghế {{ ct.ghe?.ma_ghe || ct.ma_ghe || "—" }}</span>
                  <span v-if="ct.tram_don?.ten_tram" class="text-slate-600"> — Đón: {{ ct.tram_don.ten_tram }}</span>
                  <span v-if="ct.tram_tra?.ten_tram" class="text-slate-600"> — Trả: {{ ct.tram_tra.ten_tram }}</span>
                  <span v-if="ct.gia_ve != null" class="block text-slate-500 mt-1">{{ formatCurrency(ct.gia_ve) }}</span>
                </li>
              </ul>
            </section>

            <section v-if="detailTicket.ghi_chu" class="space-y-1">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ghi chú</h3>
              <p class="text-sm text-slate-700">{{ detailTicket.ghi_chu }}</p>
            </section>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap");

.font-sans {
  font-family: "Inter", sans-serif;
}
</style>
