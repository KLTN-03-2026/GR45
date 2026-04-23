<script setup>
import { useClientStore } from "@/stores/clientStore";
import { ref, reactive, onMounted, onUnmounted, computed, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import clientApi from "@/api/clientApi";

const clientStore = useClientStore();
const route = useRoute();
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
const myRatings = ref([]);
const isLoadingRatings = ref(false);
const ratingsMessage = ref({ type: "", text: "" });
let ratingPollingTimer = null;

const showMyRatingDetail = ref(false);
const selectedMyRating = ref(null);
const CLIENT_TOKEN_KEY = "auth.client.token";

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

const anyProfileRatingModalOpen = computed(() => showMyRatingDetail.value);

const onProfileRatingModalKeydown = (e) => {
  if (e.key !== "Escape") return;
  if (showMyRatingDetail.value) {
    showMyRatingDetail.value = false;
    selectedMyRating.value = null;
  }
};

watch(anyProfileRatingModalOpen, (open) => {
  document.body.style.overflow = open ? "hidden" : "";
  if (open) document.addEventListener("keydown", onProfileRatingModalKeydown);
  else document.removeEventListener("keydown", onProfileRatingModalKeydown);
});

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

/** Điểm sao 0–5 (số nguyên) */
const clampRatingScore = (raw) => {
  const n = Math.round(Number(raw));
  if (Number.isNaN(n)) return 0;
  return Math.min(5, Math.max(0, n));
};

const formatRatingDate = (iso) => {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "—";
  return d.toLocaleDateString("vi-VN", {
    day: "numeric",
    month: "numeric",
    year: "numeric",
  });
};

/** Tuyến: ưu tiên field phẳng từ API my-ratings, kèm snake/camel lồng nhau */
const getRatingRouteLine = (rating) => {
  const from =
    rating?.route_diem_bat_dau ||
    rating?.chuyen_xe?.tuyen_duong?.diem_bat_dau ||
    rating?.chuyenXe?.tuyenDuong?.diem_bat_dau ||
    rating?.diem_bat_dau;
  const to =
    rating?.route_diem_ket_thuc ||
    rating?.chuyen_xe?.tuyen_duong?.diem_ket_thuc ||
    rating?.chuyenXe?.tuyenDuong?.diem_ket_thuc ||
    rating?.diem_ket_thuc;
  const ten =
    rating?.ten_tuyen_duong ||
    rating?.chuyen_xe?.tuyen_duong?.ten_tuyen_duong ||
    rating?.chuyenXe?.tuyenDuong?.ten_tuyen_duong;
  const hasEnds =
    (from != null && String(from).trim() !== "") ||
    (to != null && String(to).trim() !== "");
  if (!hasEnds && ten) return String(ten);
  const a = from != null && String(from).trim() !== "" ? String(from).trim() : "—";
  const b = to != null && String(to).trim() !== "" ? String(to).trim() : "—";
  return `${a} → ${b}`;
};

const getRatingProfileName = (rating) => {
  return (
    rating?.khach_hang?.ho_va_ten ||
    rating?.khachHang?.ho_va_ten ||
    clientStore.user?.ho_va_ten ||
    "—"
  );
};

const subRatingStars = (raw) => clampRatingScore(raw);
const subRatingLabel = (raw) => `${clampRatingScore(raw)}/5`;

const formatRatingDateTime = (iso) => {
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

const getRankInfo = (rank) => {
  const map = {
    'dong': { label: 'Hạng Đồng', class: 'bg-orange-100 text-orange-800 border-orange-200' },
    'bac': { label: 'Hạng Bạc', class: 'bg-slate-200 text-slate-800 border-slate-300' },
    'vang': { label: 'Hạng Vàng', class: 'bg-yellow-100 text-yellow-800 border-yellow-200' },
    'kim_cuong': { label: 'Hạng Kim Cương', class: 'bg-blue-100 text-blue-800 border-blue-200' },
  };
  return map[rank] || { label: 'Thành viên', class: 'bg-slate-100 text-slate-800 border-slate-200' };
};

const displayPoints = computed(() => {
  const p = clientStore.user?.diem_thanh_vien;
  if (p && typeof p === 'object') return p.diem_kha_dung ?? 0;
  return p ?? 0;
});

const displayRank = computed(() => {
  const p = clientStore.user?.diem_thanh_vien;
  if (p && typeof p === 'object') return getRankInfo(p.hang_thanh_vien);
  return getRankInfo(null);
});

const getStatusInfo = (status) => {
  const map = {
    dang_cho: { label: "Chờ thanh toán", class: "bg-amber-100 text-amber-700 border-amber-200" },
    da_thanh_toan: { label: "Đã thanh toán", class: "bg-emerald-100 text-emerald-700 border-emerald-200" },
    da_huy: { label: "Đã hủy", class: "bg-rose-100 text-rose-700 border-rose-200" },
  };
  return map[status] || { label: status, class: "bg-slate-100 text-slate-700 border-slate-200" };
};

const ensureClientAuthContext = () => {
  const clientToken = localStorage.getItem(CLIENT_TOKEN_KEY);
  if (clientToken) {
    localStorage.setItem("auth.active_role", "client");
  }
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
    profileMessage.value = { type: "error", text: "Cập nhật thất bại." };
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
    passwordMessage.value = { type: "error", text: "Đổi mật khẩu thất bại." };
  } finally {
    isSavingPassword.value = false;
  }
};

const fetchTickets = async (page = 1) => {
  ensureClientAuthContext();
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

const fetchRatingsData = async () => {
  ensureClientAuthContext();
  isLoadingRatings.value = true;
  ratingsMessage.value = { type: "", text: "" };
  try {
    const myRes = await clientApi.getMyRatings();
    const inner = myRes?.data;
    let ratings = inner?.ratings;
    if (!Array.isArray(ratings) && Array.isArray(myRes?.ratings)) {
      ratings = myRes.ratings;
    }
    if (!Array.isArray(ratings) && Array.isArray(inner)) {
      ratings = inner;
    }
    myRatings.value = Array.isArray(ratings) ? ratings : [];
  } catch {
    myRatings.value = [];
    ratingsMessage.value = { type: "error", text: "Không thể tải dữ liệu đánh giá." };
  } finally {
    isLoadingRatings.value = false;
  }
};

const openMyRatingDetail = (rating) => {
  selectedMyRating.value = rating;
  showMyRatingDetail.value = true;
};

const closeMyRatingDetail = () => {
  showMyRatingDetail.value = false;
  selectedMyRating.value = null;
};

const selectMenu = (main, sub) => {
  activeMenu.value = { main, sub };
  if (main === "account") expandedMenus.value.account = true;
  if (main === "offers") expandedMenus.value.offers = true;
  if (main === "tickets" || main === "my-ratings") fetchRatingsData();
};

onMounted(() => {
  ensureClientAuthContext();
  fetchProfile();
  fetchTickets();
  fetchRatingsData();

  if (route.query?.tab === "my-ratings" || route.query?.tab === "tickets") {
    selectMenu(String(route.query.tab), null);
  }

  ratingPollingTimer = window.setInterval(() => {
    fetchRatingsData();
  }, 15000);
});

onUnmounted(() => {
  document.removeEventListener("keydown", onProfileRatingModalKeydown);
  document.body.style.overflow = "";
  if (ratingPollingTimer) {
    clearInterval(ratingPollingTimer);
    ratingPollingTimer = null;
  }
});
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
              <button @click="selectMenu('tickets', null), fetchTickets(), fetchRatingsData()" :class="['flex items-center gap-3 px-4 py-3 rounded-2xl font-semibold transition-all duration-200', activeMenu.main === 'tickets' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
                <span class="material-symbols-outlined">confirmation_number</span>
                <span>Vé của tôi</span>
              </button>
              <button @click="selectMenu('my-ratings', null), fetchRatingsData()" :class="['flex items-center gap-3 px-4 py-3 rounded-2xl font-semibold transition-all duration-200', activeMenu.main === 'my-ratings' ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-50']">
                <span class="material-symbols-outlined">star</span>
                <span>Đánh giá của tôi</span>
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
                  <span :class="displayRank.class" class="px-4 py-1.5 font-bold text-xs rounded-full border">{{ displayRank.label }}</span>
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
                    <label class="text-sm font-semibold text-slate-700 flex items-center h-6">Họ và tên <span class="text-red-500 ml-1">*</span></label>
                    <input type="text" v-model="profileForm.ho_va_ten" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 transition-colors h-[50px]" />
                  </div>
                  <div class="space-y-2">
                    <label class="text-sm font-semibold text-slate-700 flex items-center h-6">Số điện thoại</label>
                    <input type="tel" v-model="profileForm.so_dien_thoai" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 transition-colors h-[50px]" placeholder="0901 234 567" />
                  </div>
                  <div class="space-y-2">
                    <label class="text-sm font-semibold text-slate-700 flex items-center justify-between h-6">
                      <span>Email</span>
                      <span class="text-[10px] bg-slate-200 text-slate-600 px-2 py-0.5 rounded uppercase font-bold">Cố định</span>
                    </label>
                    <input type="email" :value="profileForm.email" disabled class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-slate-500 cursor-not-allowed h-[50px]" />
                  </div>
                  <div class="space-y-2">
                    <label class="text-sm font-semibold text-slate-700 flex items-center h-6">Ngày sinh</label>
                    <input type="date" v-model="profileForm.ngay_sinh" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 transition-colors h-[50px]" />
                  </div>
                  <div class="space-y-2 md:col-span-2">
                    <label class="text-sm font-semibold text-slate-700 flex items-center h-6">Địa chỉ liên hệ</label>
                    <input type="text" v-model="profileForm.dia_chi" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 transition-colors h-[50px]" placeholder="Số nhà, đường, thành phố..." />
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
                  <input type="password" v-model="passwordForm.mat_khau_cu" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-colors font-mono h-[50px]" placeholder="••••••••" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">Mật khẩu mới</label>
                    <input type="password" v-model="passwordForm.mat_khau_moi" required minlength="6" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-colors font-mono h-[50px]" placeholder="••••••••" />
                  </div>
                  <div class="space-y-2">
                    <label class="block text-sm font-semibold text-slate-700">Xác nhận mật khẩu</label>
                    <input type="password" v-model="passwordForm.mat_khau_moi_confirmation" required minlength="6" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition-colors font-mono h-[50px]" placeholder="••••••••" />
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
              <div v-if="ratingsMessage.text" :class="ratingsMessage.type === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-green-50 text-green-700 border-green-200'" class="p-4 rounded-xl text-sm font-medium border">
                {{ ratingsMessage.text }}
              </div>

              <div class="grid grid-cols-1 gap-6">
                <div
                  v-for="ticket in tickets"
                  :key="ticket.id"
                  class="border border-slate-200 rounded-2xl p-6 flex flex-col md:flex-row items-center justify-between hover:shadow-md transition-shadow cursor-pointer bg-slate-50 group"
                >
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
                      <div class="text-xs text-slate-500">
                        Người đặt:
                        <span class="font-bold text-slate-700">{{ ticket.khach_hang?.ho_va_ten }}</span>
                      </div>
                      <div class="text-sm font-bold text-blue-700 tabular-nums">{{ formatCurrency(ticket.tong_tien) }}</div>
                    </div>
                  </div>
                  <div class="w-full md:w-auto mt-6 md:mt-0 md:ml-8 flex justify-center shrink-0">
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

          <!-- My Ratings Content -->
          <div v-if="activeMenu.main === 'my-ratings'" class="bg-white p-6 sm:p-10 rounded-3xl shadow-sm border border-slate-200 animate-fade-in">
            <div class="mb-8">
              <h2 class="text-xl font-bold text-slate-900">Đánh giá của tôi</h2>
              <p class="text-sm text-slate-500 mt-1 max-w-xl">
                Danh sách đánh giá đã gửi. Chọn một dòng để xem chi tiết.
              </p>
            </div>

            <div v-if="isLoadingRatings" class="py-20 text-center">
              <span class="material-symbols-outlined animate-spin text-4xl text-blue-600">refresh</span>
              <p class="mt-4 text-slate-500 font-medium">Đang tải lịch sử đánh giá...</p>
            </div>
            <div v-else-if="myRatings.length === 0" class="py-20 text-center border-2 border-dashed border-slate-100 rounded-3xl">
              <span class="material-symbols-outlined text-6xl text-slate-200">star</span>
              <p class="mt-4 text-slate-500 font-medium">Bạn chưa có đánh giá nào.</p>
            </div>
            <div v-else class="mr-list-wrap">
              <button
                v-for="rating in myRatings"
                :key="rating.id"
                type="button"
                class="mr-list-row"
                @click="openMyRatingDetail(rating)"
              >
                <div class="mr-list-row-main">
                  <div class="mr-list-route">{{ getRatingRouteLine(rating) }}</div>
                  <div class="mr-list-meta">
                    <span v-if="rating.chuyen_ngay_khoi_hanh" class="mr-list-date"
                      >Chuyến {{ rating.chuyen_ngay_khoi_hanh
                      }}<template v-if="rating.chuyen_gio_khoi_hanh"> · {{ rating.chuyen_gio_khoi_hanh }}</template></span
                    >
                    <span v-else class="mr-list-date">{{ formatRatingDate(rating.created_at) }}</span>
                  </div>
                </div>
                <div class="mr-list-row-score">
                  <div class="mr-stars-inline" aria-hidden="true">
                    <span
                      v-for="star in 5"
                      :key="`${rating.id}-li-${star}`"
                      class="mr-star"
                      :class="star <= clampRatingScore(rating.diem_so) ? 'mr-star--on' : ''"
                      >★</span
                    >
                  </div>
                  <span class="mr-score-num">{{ clampRatingScore(rating.diem_so) }}/5</span>
                  <span class="material-symbols-outlined mr-list-chevron">chevron_right</span>
                </div>
              </button>
            </div>
          </div>

          <!-- Points Content -->
          <div v-if="activeMenu.main === 'offers' && activeMenu.sub === 'points'" class="bg-white p-6 sm:p-10 rounded-3xl shadow-sm border border-slate-200 animate-fade-in">
            <div class="text-center py-8">
              <div class="w-24 h-24 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                <span class="material-symbols-outlined text-5xl text-white">hotel_class</span>
              </div>
              <h2 class="text-3xl font-bold text-slate-900 mb-2">{{ displayPoints }} điểm</h2>
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
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-left items-stretch">
                <div class="border border-slate-200 rounded-2xl p-5 hover:shadow-md transition flex flex-col h-full">
                  <div>
                    <div class="flex justify-between items-start mb-3">
                      <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-full">Giảm 20%</span>
                      <span class="text-xs text-slate-400">HSD: 30/12/2025</span>
                    </div>
                    <h3 class="font-bold text-slate-900">Giảm giá vé tuyến cố định</h3>
                    <p class="text-sm text-slate-500 mt-1">Áp dụng cho tất cả tuyến Đà Nẵng - Hội An</p>
                  </div>
                  <button class="mt-6 w-full bg-blue-600 text-white py-2 rounded-xl font-semibold hover:bg-blue-700 transition mt-auto">Nhận ngay</button>
                </div>
                <div class="border border-slate-200 rounded-2xl p-5 hover:shadow-md transition flex flex-col h-full">
                  <div>
                    <div class="flex justify-between items-start mb-3">
                      <span class="bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded-full">Miễn phí</span>
                      <span class="text-xs text-slate-400">HSD: 15/11/2025</span>
                    </div>
                    <h3 class="font-bold text-slate-900">Phụ kiện xe buýt</h3>
                    <p class="text-sm text-slate-500 mt-1">Tặng 1 suất nước suối trên xe</p>
                  </div>
                  <button class="mt-6 w-full bg-blue-600 text-white py-2 rounded-xl font-semibold hover:bg-blue-700 transition mt-auto">Nhận ngay</button>
                </div>
              </div>
            </div>
          </div>
        </main>
      </div>

      <Teleport to="body">
        <Transition name="rt-rating-modal-fade">
          <div
            v-if="showMyRatingDetail && selectedMyRating"
            class="trip-rating-detail-overlay"
            role="presentation"
            @click.self="closeMyRatingDetail"
          >
            <div
              class="trip-rating-detail-modal"
              role="dialog"
              aria-modal="true"
              aria-labelledby="profile-my-rating-detail-title"
            >
              <div class="trip-rating-detail-header">
                <h3 id="profile-my-rating-detail-title">Chi tiết đánh giá</h3>
                <button type="button" class="trip-rating-detail-close" aria-label="Đóng" @click="closeMyRatingDetail">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path
                      d="M18 6L6 18M6 6L18 18"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                </button>
              </div>
              <div class="trip-rating-detail-body">
                <div class="trip-rating-detail-trip">
                  <div class="trip-rating-detail-trip-title">Chuyến xe</div>
                  <div class="trip-rating-detail-trip-route">{{ getRatingRouteLine(selectedMyRating) }}</div>
                  <div class="trip-rating-detail-trip-meta">
                    <span v-if="selectedMyRating.ten_tuyen_duong">{{ selectedMyRating.ten_tuyen_duong }}</span>
                    <span v-if="selectedMyRating.id_chuyen_xe"> · ID #{{ selectedMyRating.id_chuyen_xe }}</span>
                  </div>
                  <div class="trip-rating-detail-trip-meta">
                    <span v-if="selectedMyRating.chuyen_ngay_khoi_hanh"
                      >Ngày: {{ selectedMyRating.chuyen_ngay_khoi_hanh }}</span
                    >
                    <span v-if="selectedMyRating.chuyen_gio_khoi_hanh">
                      · Giờ: {{ selectedMyRating.chuyen_gio_khoi_hanh }}</span
                    >
                    <span v-if="selectedMyRating.ma_ve">
                      · Mã vé: <strong class="trip-rating-detail-mono">{{ selectedMyRating.ma_ve }}</strong></span
                    >
                  </div>
                </div>

                <div class="trip-rating-detail-top">
                  <span class="trip-rating-detail-name">{{ getRatingProfileName(selectedMyRating) }}</span>
                  <div class="trip-rating-detail-stars-row">
                    <span
                      v-for="star in 5"
                      :key="`mrd-${star}`"
                      class="trip-rating-detail-star"
                      :class="{ 'trip-rating-detail-star--on': star <= clampRatingScore(selectedMyRating?.diem_so) }"
                      aria-hidden="true"
                      >★</span
                    >
                    <span class="trip-rating-detail-score">{{ clampRatingScore(selectedMyRating?.diem_so) }}/5</span>
                  </div>
                </div>

                <div
                  v-if="
                    selectedMyRating.diem_dich_vu ||
                    selectedMyRating.diem_an_toan ||
                    selectedMyRating.diem_sach_se ||
                    selectedMyRating.diem_thai_do
                  "
                  class="trip-rating-detail-grid"
                >
                  <div class="trip-rating-detail-metric">
                    <span class="trip-rating-detail-metric-label">Dịch vụ</span>
                    <div class="trip-rating-detail-metric-stars">
                      <span
                        v-for="star in 5"
                        :key="`dv-${star}`"
                        class="trip-rating-detail-star trip-rating-detail-star--sm"
                        :class="{ 'trip-rating-detail-star--on': star <= subRatingStars(selectedMyRating?.diem_dich_vu) }"
                        aria-hidden="true"
                        >★</span
                      >
                      <strong>{{ subRatingLabel(selectedMyRating?.diem_dich_vu) }}</strong>
                    </div>
                  </div>
                  <div class="trip-rating-detail-metric">
                    <span class="trip-rating-detail-metric-label">An toàn</span>
                    <div class="trip-rating-detail-metric-stars">
                      <span
                        v-for="star in 5"
                        :key="`at-${star}`"
                        class="trip-rating-detail-star trip-rating-detail-star--sm"
                        :class="{ 'trip-rating-detail-star--on': star <= subRatingStars(selectedMyRating?.diem_an_toan) }"
                        aria-hidden="true"
                        >★</span
                      >
                      <strong>{{ subRatingLabel(selectedMyRating?.diem_an_toan) }}</strong>
                    </div>
                  </div>
                  <div class="trip-rating-detail-metric">
                    <span class="trip-rating-detail-metric-label">Sạch sẽ</span>
                    <div class="trip-rating-detail-metric-stars">
                      <span
                        v-for="star in 5"
                        :key="`ss-${star}`"
                        class="trip-rating-detail-star trip-rating-detail-star--sm"
                        :class="{ 'trip-rating-detail-star--on': star <= subRatingStars(selectedMyRating?.diem_sach_se) }"
                        aria-hidden="true"
                        >★</span
                      >
                      <strong>{{ subRatingLabel(selectedMyRating?.diem_sach_se) }}</strong>
                    </div>
                  </div>
                  <div class="trip-rating-detail-metric">
                    <span class="trip-rating-detail-metric-label">Thái độ</span>
                    <div class="trip-rating-detail-metric-stars">
                      <span
                        v-for="star in 5"
                        :key="`td-${star}`"
                        class="trip-rating-detail-star trip-rating-detail-star--sm"
                        :class="{ 'trip-rating-detail-star--on': star <= subRatingStars(selectedMyRating?.diem_thai_do) }"
                        aria-hidden="true"
                        >★</span
                      >
                      <strong>{{ subRatingLabel(selectedMyRating?.diem_thai_do) }}</strong>
                    </div>
                  </div>
                </div>

                <div class="trip-rating-detail-trip-meta trip-rating-detail-sent">
                  Gửi lúc: {{ formatRatingDateTime(selectedMyRating.created_at) }}
                </div>
                <p class="trip-rating-detail-note">{{ selectedMyRating?.noi_dung || "Không có nhận xét." }}</p>
              </div>
            </div>
          </div>
        </Transition>
      </Teleport>
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

/* —— Đánh giá của tôi: danh sách —— */
.mr-list-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.mr-list-row {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 1rem;
  text-align: left;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #fff;
  cursor: pointer;
  transition:
    background 0.15s ease,
    border-color 0.15s ease,
    box-shadow 0.15s ease;
}

.mr-list-row:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
}

.mr-list-main {
  min-width: 0;
  flex: 1;
}

.mr-list-route {
  font-size: 0.9rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.35;
}

.mr-list-meta {
  margin-top: 0.25rem;
}

.mr-list-date {
  font-size: 0.72rem;
  font-weight: 600;
  color: #64748b;
}

.mr-list-row-score {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-shrink: 0;
}

.mr-stars-inline {
  display: flex;
  gap: 0.02rem;
}

.mr-star {
  font-size: 0.95rem;
  line-height: 1;
  color: #cbd5e1;
}

.mr-star--on {
  color: #f59e0b;
}

.mr-score-num {
  font-size: 0.8rem;
  font-weight: 800;
  color: #d97706;
}

.mr-list-chevron {
  font-size: 1.25rem;
  color: #94a3b8;
}

/* —— Popup đánh giá / chi tiết —— */
.rt-rating-modal-fade-enter-active {
  transition: opacity 0.25s ease;
}
.rt-rating-modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.rt-rating-modal-fade-enter-from,
.rt-rating-modal-fade-leave-to {
  opacity: 0;
}

.trip-rating-detail-overlay {
  position: fixed;
  inset: 0;
  z-index: 10050;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.trip-rating-detail-modal {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25);
  border: 1px solid #e2e8f0;
  max-height: min(90vh, 680px);
  display: flex;
  flex-direction: column;
}

.trip-rating-detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.trip-rating-detail-header h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 800;
  color: #1e293b;
}

.trip-rating-detail-close {
  border: none;
  background: transparent;
  cursor: pointer;
  color: #64748b;
  padding: 0.2rem;
  line-height: 0;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.trip-rating-detail-close:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.trip-rating-detail-body {
  padding: 0.9rem 1rem 1rem;
  overflow-y: auto;
}

.trip-rating-detail-trip {
  margin-bottom: 0.85rem;
  padding: 0.65rem 0.75rem;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.78rem;
  color: #334155;
}

.trip-rating-detail-trip-title {
  font-size: 0.65rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
  margin-bottom: 0.35rem;
}

.trip-rating-detail-trip-route {
  font-weight: 800;
  color: #1e40af;
  font-size: 0.82rem;
  margin-bottom: 0.25rem;
}

.trip-rating-detail-trip-meta {
  line-height: 1.45;
  color: #475569;
}

.trip-rating-detail-mono {
  font-family: ui-monospace, monospace;
  font-weight: 600;
}

.trip-rating-detail-sent {
  margin-top: 0.5rem;
  font-size: 0.72rem;
  color: #64748b;
}

.trip-rating-detail-top {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.35rem;
  margin-bottom: 0.5rem;
}

.trip-rating-detail-name {
  font-size: 0.85rem;
  font-weight: 700;
  color: #1e293b;
}

.trip-rating-detail-stars-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.08rem 0.15rem;
}

.trip-rating-detail-star {
  font-size: 0.95rem;
  line-height: 1;
  color: #cbd5e1;
  letter-spacing: -0.05em;
}

.trip-rating-detail-star--sm {
  font-size: 0.72rem;
}

.trip-rating-detail-star--on {
  color: #f59e0b;
}

.trip-rating-detail-score {
  font-size: 0.8rem;
  font-weight: 800;
  color: #d97706;
  margin-left: 0.3rem;
}

.trip-rating-detail-grid {
  margin-top: 0.65rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem 0.65rem;
  font-size: 0.78rem;
  color: #475569;
}

.trip-rating-detail-metric {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.trip-rating-detail-metric-label {
  font-size: 0.72rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.trip-rating-detail-metric-stars {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.06rem 0.12rem;
}

.trip-rating-detail-metric-stars strong {
  margin-left: 0.25rem;
  font-size: 0.78rem;
  color: #334155;
}

.trip-rating-detail-note {
  margin-top: 0.65rem;
  margin-bottom: 0;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  border-radius: 10px;
  padding: 0.55rem 0.65rem;
  font-size: 0.8rem;
  color: #334155;
  white-space: pre-wrap;
}

@media (max-width: 520px) {
  .trip-rating-detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>