<script setup>
import { useClientStore } from "@/stores/clientStore";
import { ref, reactive, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import clientApi from "@/api/clientApi";

const clientStore = useClientStore();
const router = useRouter();

const expandedMenus = ref({
  account: true,
  offers: false,
});

const activeMenu = ref({
  main: "account",
  sub: "profile",
});

// Profile state
const isLoadingProfile = ref(false);
const isSavingProfile = ref(false);
const profileForm = reactive({
  ho_va_ten: "",
  so_dien_thoai: "",
  email: "",
  ngay_sinh: "",
  dia_chi: "",
});
const profileMessage = ref({ type: "", text: "" });

// Tickets state
const tickets = ref([]);
const isLoadingTickets = ref(false);
const ticketsMessage = ref({ type: "", text: "" });
const ticketsPagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
});

// Password state
const isSavingPassword = ref(false);
const passwordForm = reactive({
  mat_khau_cu: "",
  mat_khau_moi: "",
  mat_khau_moi_confirmation: "",
});
const passwordMessage = ref({ type: "", text: "" });

// Computed
const avatarLetter = computed(() => {
  const name =
    clientStore.user?.ho_va_ten ||
    clientStore.user?.ten_khach_hang ||
    clientStore.user?.name ||
    "K";
  return name.charAt(0).toUpperCase();
});

const userName = computed(() => clientStore.user?.ho_va_ten || "Khách hàng");
const userEmail = computed(() => profileForm.email || clientStore.user?.email || "");

// Helper formats
const formatCurrency = (value) => {
  if (!value) return "0đ";
  return new Intl.NumberFormat("vi-VN", {
    style: "currency",
    currency: "VND",
  }).format(value);
};

const formatDate = (dateStr) => {
  if (!dateStr) return "";
  return new Date(dateStr).toLocaleDateString("vi-VN", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};

const getStatusInfo = (status) => {
  const map = {
    dang_cho: { label: "Chờ thanh toán", class: "bg-amber-100 text-amber-700 border-amber-200" },
    da_thanh_toan: { label: "Đã thanh toán", class: "bg-emerald-100 text-emerald-700 border-emerald-200" },
    da_huy: { label: "Đã hủy", class: "bg-rose-100 text-rose-700 border-rose-200" },
  };
  return map[status] || { label: status, class: "bg-slate-100 text-slate-700 border-slate-200" };
};

// Actions
const fetchProfile = async () => {
  isLoadingProfile.value = true;
  try {
    const res = await clientApi.getProfile();
    if (res.success) {
      const data = res.data;
      Object.assign(profileForm, {
        ho_va_ten: data.ho_va_ten || "",
        so_dien_thoai: data.so_dien_thoai || "",
        email: data.email || "",
        ngay_sinh: data.ngay_sinh ? data.ngay_sinh.split("T")[0] : "",
        dia_chi: data.dia_chi || "",
      });
      clientStore.updateUser(data);
    }
  } catch (error) {
    profileMessage.value = { type: "error", text: "Không thể tải thông tin cá nhân." };
  } finally {
    isLoadingProfile.value = false;
  }
};

const handleUpdateProfile = async () => {
  isSavingProfile.value = true;
  profileMessage.value = { type: "", text: "" };
  try {
    const res = await clientApi.updateProfile(profileForm);
    if (res.success) {
      profileMessage.value = { type: "success", text: "Cập nhật thông tin thành công!" };
      clientStore.updateUser(res.data);
    }
  } catch (error) {
    const msg =
      error.response?.data?.errors
        ? Object.values(error.response.data.errors).flat()[0]
        : error.response?.data?.message;
    profileMessage.value = { type: "error", text: msg || "Cập nhật thông tin thất bại. Vui lòng thử lại." };
  } finally {
    isSavingProfile.value = false;
  }
};

const handleChangePassword = async () => {
  if (passwordForm.mat_khau_moi !== passwordForm.mat_khau_moi_confirmation) {
    passwordMessage.value = { type: "error", text: "Xác nhận mật khẩu không khớp." };
    return;
  }
  isSavingPassword.value = true;
  passwordMessage.value = { type: "", text: "" };
  try {
    const res = await clientApi.changePassword(passwordForm);
    if (res.success) {
      passwordMessage.value = { type: "success", text: "Đổi mật khẩu thành công. Đang chuyển hướng..." };
      setTimeout(() => {
        clientStore.logout();
        router.push("/auth/login");
      }, 2000);
    }
  } catch (error) {
    const msg =
      error.response?.data?.errors
        ? Object.values(error.response.data.errors).flat()[0]
        : error.response?.data?.message;
    passwordMessage.value = { type: "error", text: msg || "Đổi mật khẩu thất bại. Vui lòng thử lại." };
  } finally {
    isSavingPassword.value = false;
  }
};

const fetchTickets = async (page = 1) => {
  isLoadingTickets.value = true;
  try {
    const res = await clientApi.getTickets({ page });
    if (res.success) {
      tickets.value = res.data.data || [];
      ticketsPagination.value = {
        current_page: res.data.current_page || 1,
        last_page: res.data.last_page || 1,
        total: res.data.total || 0,
      };
    }
  } catch (error) {
    ticketsMessage.value = { type: "error", text: "Không thể tải danh sách vé." };
  } finally {
    isLoadingTickets.value = false;
  }
};

const selectMenu = (main, sub) => {
  activeMenu.value = { main, sub };
  if (main === "account") expandedMenus.value.account = true;
  if (main === "offers") expandedMenus.value.offers = true;
};

onMounted(() => fetchProfile());
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900 font-sans py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col lg:flex-row gap-8">
        <!-- Sidebar -->
        <aside class="w-full lg:w-72 flex-shrink-0 lg:sticky lg:top-8 h-fit">
          <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 bg-blue-600 border-b border-blue-700">
              <button @click="$router.push('/')" class="w-full py-3 px-4 bg-white text-blue-600 hover:bg-blue-50 font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 active:scale-95">
                <span class="material-symbols-outlined">directions_bus</span>
                Đặt vé Smart Bus
              </button>
            </div>
            <nav class="p-4 flex flex-col gap-1">
              <div class="mb-1">
                <button @click="expandedMenus.account = !expandedMenus.account" class="w-full flex items-center justify-between px-4 py-3 rounded-2xl text-slate-700 hover:bg-slate-50 transition-all duration-200 font-semibold">
                  <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-slate-500">account_circle</span>
                    <span>Tài khoản</span>
                  </div>
                  <span class="material-symbols-outlined text-slate-400 text-base">
                    {{ expandedMenus.account ? "expand_less" : "expand_more" }}
                  </span>
                </button>
                <div v-show="expandedMenus.account" class="ml-4 mt-1 space-y-1">
                  <button @click="selectMenu('account', 'profile')" :class="['w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all', activeMenu.main === 'account' && activeMenu.sub === 'profile' ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:bg-slate-50']">
                    <span class="material-symbols-outlined text-[18px]">manage_accounts</span>
                    Hồ sơ cá nhân
                  </button>
                  <button @click="selectMenu('account', 'security')" :class="['w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all', activeMenu.main === 'account' && activeMenu.sub === 'security' ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:bg-slate-50']">
                    <span class="material-symbols-outlined text-[18px]">lock</span>
                    Bảo mật
                  </button>
                </div>
              </div>
              <button @click="selectMenu('tickets', null), fetchTickets()" :class="['flex items-center gap-3 px-4 py-3 rounded-2xl font-semibold transition-all duration-200', activeMenu.main === 'tickets' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
                <span class="material-symbols-outlined">confirmation_number</span>
                <span>Vé của tôi</span>
              </button>
              <div class="mt-1">
                <button @click="expandedMenus.offers = !expandedMenus.offers" class="w-full flex items-center justify-between px-4 py-3 rounded-2xl text-slate-700 hover:bg-slate-50 transition-all duration-200 font-semibold">
                  <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-slate-500">card_giftcard</span>
                    <span>Ưu đãi</span>
                  </div>
                  <span class="material-symbols-outlined text-slate-400 text-base">
                    {{ expandedMenus.offers ? "expand_less" : "expand_more" }}
                  </span>
                </button>
                <div v-show="expandedMenus.offers" class="ml-4 mt-1 space-y-1">
                  <button @click="selectMenu('offers', 'points')" :class="['w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all', activeMenu.main === 'offers' && activeMenu.sub === 'points' ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:bg-slate-50']">
                    <span class="material-symbols-outlined text-[18px]">hotel_class</span>
                    Điểm thành viên
                  </button>
                  <button @click="selectMenu('offers', 'vouchers')" :class="['w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition-all', activeMenu.main === 'offers' && activeMenu.sub === 'vouchers' ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-slate-600 hover:bg-slate-50']">
                    <span class="material-symbols-outlined text-[18px]">local_activity</span>
                    Kho Voucher
                    <span class="ml-auto bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full">Mới</span>
                  </button>
                </div>
              </div>
              <div class="h-px bg-slate-100 my-2"></div>
              <button @click="clientStore.logout()" class="flex items-center gap-3 px-4 py-3 rounded-2xl font-semibold text-red-600 hover:bg-red-50 transition-all duration-200">
                <span class="material-symbols-outlined">logout</span>
                <span>Đăng xuất</span>
              </button>
            </nav>
          </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 min-w-0 space-y-8">
          <!-- Header Profile Card -->
          <section class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden relative">
            <div class="h-32 bg-gradient-to-r from-blue-600 to-indigo-600"></div>
            <div class="px-6 sm:px-10 pb-8">
              <div class="relative flex justify-between items-end -mt-12 mb-6">
                <div class="w-24 h-24 bg-white rounded-full p-1.5 shadow-md">
                  <div class="w-full h-full bg-blue-100 rounded-full flex items-center justify-center text-3xl font-black text-blue-700">{{ avatarLetter }}</div>
                </div>
                <div class="mb-2">
                  <span class="px-4 py-1.5 bg-yellow-100 text-yellow-800 font-bold text-xs rounded-full border border-yellow-200">Thành viên</span>
                </div>
              </div>
              <div>
                <h1 class="text-3xl font-bold text-slate-900">{{ userName }}</h1>
                <div class="flex flex-wrap items-center gap-4 mt-3 text-slate-500 text-sm font-medium">
                  <span class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">mail</span>
                    {{ userEmail }}
                  </span>
                  <span v-if="profileForm.so_dien_thoai" class="flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">phone</span>
                    +84 {{ profileForm.so_dien_thoai }}
                  </span>
                </div>
              </div>
            </div>
          </section>

          <!-- Profile Content -->
          <div v-if="activeMenu.main === 'account' && activeMenu.sub === 'profile'" class="space-y-8 animate-fade-in">
            <section class="bg-white p-6 sm:p-10 rounded-3xl shadow-sm border border-slate-200">
              <div class="mb-8">
                <h2 class="text-xl font-bold text-slate-900">Hồ sơ cá nhân</h2>
                <p class="text-sm text-slate-500 mt-1">Quản lý thông tin liên hệ và tùy chỉnh tài khoản.</p>
              </div>
              <form @submit.prevent="handleUpdateProfile" class="space-y-6">
                <div v-if="profileMessage.text" :class="profileMessage.type === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-green-50 text-green-700 border-green-200'" class="p-4 rounded-xl text-sm font-medium border flex gap-3">
                  <span class="material-symbols-outlined">{{ profileMessage.type === 'error' ? 'error' : 'check_circle' }}</span>
                  {{ profileMessage.text }}
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">Họ và tên <span class="text-red-500">*</span></label>
                    <input type="text" v-model="profileForm.ho_va_ten" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 transition-colors" />
                  </div>
                  <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">Số điện thoại</label>
                    <input type="tel" v-model="profileForm.so_dien_thoai" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="0901 234 567" />
                  </div>
                  <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700 flex justify-between">Email <span class="text-[10px] bg-slate-200 text-slate-600 px-2 py-0.5 rounded uppercase">Cố định</span></label>
                    <input type="email" :value="profileForm.email" disabled class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-slate-500 cursor-not-allowed" />
                  </div>
                  <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">Ngày sinh</label>
                    <input type="date" v-model="profileForm.ngay_sinh" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 transition-colors h-[48px]" />
                  </div>
                  <div class="space-y-2 md:col-span-2">
                    <label class="block text-sm font-semibold text-slate-700">Địa chỉ liên hệ</label>
                    <input type="text" v-model="profileForm.dia_chi" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="Số nhà, đường, thành phố..." />
                  </div>
                </div>
                <div class="pt-4 flex justify-end">
                  <button type="submit" :disabled="isSavingProfile" class="w-full sm:w-auto px-8 py-3 bg-slate-900 text-white font-semibold rounded-xl hover:bg-slate-800 transition-all flex items-center justify-center gap-2 disabled:opacity-70">
                    <span v-if="isSavingProfile" class="material-symbols-outlined animate-spin text-sm">refresh</span>
                    Lưu thông tin
                  </button>
                </div>
              </form>
            </section>
          </div>

          <!-- Security Content -->
          <div v-if="activeMenu.main === 'account' && activeMenu.sub === 'security'" class="space-y-8 animate-fade-in">
            <section class="bg-white p-6 sm:p-10 rounded-3xl shadow-sm border border-slate-200">
              <div class="mb-8">
                <h2 class="text-xl font-bold text-slate-900">Bảo mật tài khoản</h2>
                <p class="text-sm text-slate-500 mt-1">Cập nhật mật khẩu để bảo vệ dữ liệu của bạn.</p>
              </div>
              <form @submit.prevent="handleChangePassword" class="space-y-6 max-w-2xl">
                <div v-if="passwordMessage.text" :class="passwordMessage.type === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-green-50 text-green-700 border-green-200'" class="p-4 rounded-xl text-sm font-medium border flex gap-3">
                  <span class="material-symbols-outlined">{{ passwordMessage.type === 'error' ? 'error' : 'check_circle' }}</span>
                  {{ passwordMessage.text }}
                </div>
                <div class="space-y-2">
                  <label class="block text-sm font-semibold text-slate-700">Mật khẩu hiện tại</label>
                  <input type="password" v-model="passwordForm.mat_khau_cu" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-colors font-mono" placeholder="••••••••" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">Mật khẩu mới</label>
                    <input type="password" v-model="passwordForm.mat_khau_moi" required minlength="6" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-colors font-mono" placeholder="••••••••" />
                  </div>
                  <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">Xác nhận mật khẩu</label>
                    <input type="password" v-model="passwordForm.mat_khau_moi_confirmation" required minlength="6" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-colors font-mono" placeholder="••••••••" />
                  </div>
                </div>
                <div class="pt-4">
                  <button type="submit" :disabled="isSavingPassword" class="w-full sm:w-auto px-8 py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition-all flex items-center justify-center gap-2 disabled:opacity-70">
                    <span v-if="isSavingPassword" class="material-symbols-outlined animate-spin text-sm">refresh</span>
                    Đổi mật khẩu
                  </button>
                </div>
              </form>
            </section>
          </div>

          <!-- Tickets Content -->
          <div v-if="activeMenu.main === 'tickets'" class="bg-white p-6 sm:p-10 rounded-3xl shadow-sm border border-slate-200 animate-fade-in">
            <div class="flex items-center justify-between mb-8">
              <h2 class="text-xl font-bold text-slate-900">Chuyến đi của tôi</h2>
              <button class="text-blue-600 font-semibold text-sm hover:underline">Lịch sử đặt vé</button>
            </div>
            <div v-if="isLoadingTickets" class="py-20 text-center">
              <span class="material-symbols-outlined animate-spin text-4xl text-blue-600">refresh</span>
              <p class="mt-4 text-slate-500 font-medium">Đang tải danh sách vé...</p>
            </div>
            <div v-else-if="tickets.length === 0" class="py-20 text-center border-2 border-dashed border-slate-100 rounded-3xl">
              <span class="material-symbols-outlined text-6xl text-slate-200">confirmation_number</span>
              <p class="mt-4 text-slate-500 font-medium">Bạn chưa có vé nào.</p>
              <button @click="$router.push('/')" class="mt-6 text-blue-600 font-bold hover:underline">Đặt vé ngay</button>
            </div>
            <div v-else class="space-y-6">
              <div class="grid grid-cols-1 gap-6">
                <div v-for="ticket in tickets" :key="ticket.id" class="border border-slate-200 rounded-2xl p-6 flex flex-col md:flex-row items-center justify-between hover:shadow-md transition-shadow cursor-pointer bg-slate-50 group">
                  <div class="flex-1 w-full">
                    <div class="flex justify-between items-center mb-4">
                      <span :class="getStatusInfo(ticket.tinh_trang).class" class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border">{{ getStatusInfo(ticket.tinh_trang).label }}</span>
                      <span class="text-slate-500 text-sm font-mono font-bold">Mã vé: {{ ticket.ma_ve }}</span>
                    </div>
                    <div class="flex items-center gap-6 text-slate-900">
                      <div class="text-center min-w-[60px]">
                        <div class="text-lg font-black">{{ ticket.chuyen_xe?.tuyen_duong?.diem_bat_dau || 'N/A' }}</div>
                        <div class="text-xs font-semibold text-slate-500">{{ ticket.chuyen_xe?.gio_khoi_hanh?.substring(0, 5) }}</div>
                      </div>
                      <div class="flex-1 flex flex-col items-center">
                        <div class="text-[10px] font-bold text-blue-600 mb-1">{{ formatDate(ticket.chuyen_xe?.ngay_khoi_hanh) }}</div>
                        <div class="h-px bg-slate-300 w-full relative border-t border-dashed border-slate-400">
                          <span class="material-symbols-outlined absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-slate-400 bg-slate-50 px-2 group-hover:text-blue-600 transition-colors">directions_bus</span>
                        </div>
                        <div class="mt-1 text-[10px] text-slate-400 truncate max-w-[150px]">{{ ticket.chuyen_xe?.tuyen_duong?.ten_tuyen_duong }}</div>
                      </div>
                      <div class="text-center min-w-[60px]">
                        <div class="text-lg font-black">{{ ticket.chuyen_xe?.tuyen_duong?.diem_ket_thuc || 'N/A' }}</div>
                        <div class="text-xs font-semibold text-slate-500">Đến nơi</div>
                      </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-200/50 flex justify-between items-center">
                      <div class="text-xs text-slate-500">Người đặt: <span class="font-bold text-slate-700">{{ ticket.khach_hang?.ho_va_ten }}</span></div>
                      <div class="text-sm font-bold text-blue-700">{{ formatCurrency(ticket.tong_tien) }}</div>
                    </div>
                  </div>
                  <div class="w-full md:w-auto mt-6 md:mt-0 md:ml-8 flex justify-center">
                    <div class="bg-white p-2 rounded-xl shadow-sm border border-slate-200">
                      <span class="material-symbols-outlined text-5xl" :class="ticket.tinh_trang === 'da_thanh_toan' ? 'text-blue-600' : 'text-slate-300'">qr_code_2</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Pagination UI -->
              <div v-if="ticketsPagination.last_page > 1" class="flex items-center justify-between pt-6 border-t border-slate-100">
                <div class="text-sm text-slate-500">
                  Hiển thị trang <span class="font-bold text-slate-900">{{ ticketsPagination.current_page }}</span> / {{ ticketsPagination.last_page }}
                </div>
                <div class="flex gap-2">
                  <button 
                    @click="fetchTickets(ticketsPagination.current_page - 1)"
                    :disabled="ticketsPagination.current_page === 1"
                    class="p-2 rounded-xl border border-slate-200 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                  >
                    <span class="material-symbols-outlined">chevron_left</span>
                  </button>
                  
                  <div class="flex gap-1">
                    <button 
                      v-for="page in ticketsPagination.last_page" 
                      :key="page"
                      @click="fetchTickets(page)"
                      :class="[
                        'w-10 h-10 rounded-xl font-bold text-sm transition-all',
                        ticketsPagination.current_page === page 
                          ? 'bg-blue-600 text-white shadow-md' 
                          : 'hover:bg-slate-100 text-slate-600'
                      ]"
                    >
                      {{ page }}
                    </button>
                  </div>

                  <button 
                    @click="fetchTickets(ticketsPagination.current_page + 1)"
                    :disabled="ticketsPagination.current_page === ticketsPagination.last_page"
                    class="p-2 rounded-xl border border-slate-200 hover:bg-slate-50 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                  >
                    <span class="material-symbols-outlined">chevron_right</span>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Points Content -->
          <div v-if="activeMenu.main === 'offers' && activeMenu.sub === 'points'" class="bg-white p-6 sm:p-10 rounded-3xl shadow-sm border border-slate-200 animate-fade-in">
            <div class="text-center py-8">
              <div class="w-24 h-24 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                <span class="material-symbols-outlined text-5xl text-white">hotel_class</span>
              </div>
              <h2 class="text-3xl font-bold text-slate-900 mb-2">{{ clientStore.user?.diem_thanh_vien ?? 0 }} điểm</h2>
              <p class="text-slate-500 max-w-md mx-auto">Tích điểm khi đặt vé và nhận ưu đãi độc quyền.</p>
              <div class="mt-8 bg-slate-50 rounded-2xl p-6 text-left max-w-lg mx-auto">
                <h3 class="font-bold text-slate-900 mb-3">Cách tích điểm</h3>
                <ul class="space-y-2 text-sm text-slate-600">
                  <li class="flex items-center gap-2"><span class="material-symbols-outlined text-blue-500 text-sm">check_circle</span> Mỗi 10.000đ = 1 điểm</li>
                  <li class="flex items-center gap-2"><span class="material-symbols-outlined text-blue-500 text-sm">check_circle</span> Điểm có thể đổi voucher giảm giá</li>
                  <li class="flex items-center gap-2"><span class="material-symbols-outlined text-blue-500 text-sm">check_circle</span> Điểm không có giá trị quy đổi thành tiền mặt</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Vouchers Content -->
          <div v-if="activeMenu.main === 'offers' && activeMenu.sub === 'vouchers'" class="bg-white p-6 sm:p-10 rounded-3xl shadow-sm border border-slate-200 animate-fade-in">
            <div class="text-center py-8">
              <div class="w-24 h-24 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <span class="material-symbols-outlined text-5xl text-purple-600">local_activity</span>
              </div>
              <h2 class="text-2xl font-bold text-slate-900 mb-2">Kho Voucher</h2>
              <p class="text-slate-500 max-w-md mx-auto mb-8">Ưu đãi độc quyền dành cho thành viên Smart Bus</p>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-left">
                <div class="border border-slate-200 rounded-2xl p-5 hover:shadow-md transition">
                  <div class="flex justify-between items-start mb-3">
                    <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">Giảm 20%</span>
                    <span class="text-xs text-slate-400">HSD: 30/12/2025</span>
                  </div>
                  <h3 class="font-bold text-slate-900">Giảm giá vé tuyến cố định</h3>
                  <p class="text-sm text-slate-500 mt-1">Áp dụng cho tất cả tuyến Đà Nẵng - Hội An</p>
                  <button class="mt-4 w-full bg-blue-600 text-white py-2 rounded-xl font-semibold hover:bg-blue-700 transition">Nhận ngay</button>
                </div>
                <div class="border border-slate-200 rounded-2xl p-5 hover:shadow-md transition">
                  <div class="flex justify-between items-start mb-3">
                    <span class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded-full">Miễn phí</span>
                    <span class="text-xs text-slate-400">HSD: 15/11/2025</span>
                  </div>
                  <h3 class="font-bold text-slate-900">Phụ kiện xe buýt</h3>
                  <p class="text-sm text-slate-500 mt-1">Tặng 1 suất nước suối trên xe</p>
                  <button class="mt-4 w-full bg-blue-600 text-white py-2 rounded-xl font-semibold hover:bg-blue-700 transition">Nhận ngay</button>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</template>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap");

.font-sans {
  font-family: "Inter", sans-serif;
}

.animate-fade-in {
  animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>