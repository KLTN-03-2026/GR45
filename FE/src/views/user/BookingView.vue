<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import clientApi from "@/api/clientApi";
import { createEcho } from "@/utils/echo.js";

const route = useRoute();
const router = useRouter();

// --- STATE ---
const tripId = route.query.id_chuyen_xe;
const currentStep = ref(1);

const isLoading = ref(true);
const isBooking = ref(false);

const tripData = ref(null);
const seatMapRaw = ref([]);

const stopsData = ref({ tram_don: [], tram_tra: [] });
const vouchers = ref([]);

// Form State
const selectedSeats = ref([]);
const pickupPointId = ref(null);
const dropoffPointId = ref(null);
const customerNote = ref("");
const selectedVoucherId = ref(null);
const paymentMethod = ref("tien_mat");

const bookingResult = ref(null);
const errorMessage = ref("");

// --- REALTIME STATE ---
// Instance Echo dùng để subscribe kênh sơ đồ ghế
const echoInstance = ref(null);
// Toast cảnh báo khi ghế đang chọn bị người khác đặt
const seatConflictAlert = ref(null); // { message: string, seats: [] }
// Đếm số người đang xem cùng chuyến (tuỳ chọn, sẽ dấu sau)
const isRealtimeConnected = ref(false);

// Loyalty State
const loyaltyInfo = ref(null);
const pointsToRedeem = ref(0);

import { watch } from "vue";
watch(pointsToRedeem, (newVal) => {
  if (loyaltyInfo.value && newVal > loyaltyInfo.value.diem_hien_tai) {
    pointsToRedeem.value = loyaltyInfo.value.diem_hien_tai;
  }

  // Không cho phép giảm quá số tiền cần thanh toán
  const remainingPrice = baseTotalPrice.value - discountAmount.value;
  const maxPointsForPrice = Math.floor(remainingPrice / 100);
  if (newVal > maxPointsForPrice) {
    pointsToRedeem.value = maxPointsForPrice;
  }

  if (newVal < 0) {
    pointsToRedeem.value = 0;
  }
});

// --- COMPUTED ---
const selectedVoucher = computed(() => {
  return vouchers.value.find((v) => v.id === selectedVoucherId.value) || null;
});

const baseTotalPrice = computed(() => {
  if (!tripData.value) return 0;
  const basePrice = parseFloat(tripData.value.tuyen_duong.gia_ve_co_ban || 0);
  return basePrice * selectedSeats.value.length;
});

const discountAmount = computed(() => {
  if (!selectedVoucher.value) return 0;
  let dist = 0;
  if (selectedVoucher.value.loai_voucher === "percent") {
    dist =
      (baseTotalPrice.value * parseFloat(selectedVoucher.value.gia_tri)) / 100;
  } else {
    dist = parseFloat(selectedVoucher.value.gia_tri);
  }
  return dist > baseTotalPrice.value ? baseTotalPrice.value : dist;
});

const pointsDiscountAmount = computed(() => {
  return (Number(pointsToRedeem.value) || 0) * 100;
});

const finalPrice = computed(() => {
  const total =
    baseTotalPrice.value - discountAmount.value - pointsDiscountAmount.value;
  return total < 0 ? 0 : total;
});

// Cấu hình VietQR
const bankId = import.meta.env.VITE_BANK_ID || "MB";
const bankAccount = import.meta.env.VITE_BANK_ACCOUNT || "0377417720";
const accountName = import.meta.env.VITE_ACCOUNT_NAME || "NGUYEN HUU THAI";

const vietQrUrl = computed(() => {
  if (!bookingResult.value) return "";
  return `https://img.vietqr.io/image/${bankId}-${bankAccount}-compact2.png?amount=${finalPrice.value}&addInfo=${bookingResult.value.ma_ve}&accountName=${encodeURIComponent(accountName)}`;
});

// Format utils
const formatPrice = (val) => new Intl.NumberFormat("vi-VN").format(val) + "đ";
const formatDate = (dateStr) => {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  return d.toLocaleDateString("vi-VN", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};

// Lọc vé theo tầng
const seatsFloor1 = computed(() =>
  seatMapRaw.value.filter(
    (s) => Number(s.tang) === 1 && s.trang_thai !== "an_ghe",
  ),
);
const seatsFloor2 = computed(() =>
  seatMapRaw.value.filter(
    (s) => Number(s.tang) === 2 && s.trang_thai !== "an_ghe",
  ),
);

const SEAT_ROWS_LS_KEY = "gobus_seat_map_rows_per_floor";
const seatRowsPerFloor = ref(2);
const seatDirection = ref("driver_left");
const SEAT_DIRECTION_LS_PREFIX = "gobus_seat_direction_driver_";
const getSeatDirectionStorageKey = (driverId) =>
  `${SEAT_DIRECTION_LS_PREFIX}${driverId || "none"}`;

const applySeatDirectionFromDriver = () => {
  const driverId = tripData.value?.xe?.id_tai_xe_chinh || null;
  const stored = localStorage.getItem(getSeatDirectionStorageKey(driverId));
  seatDirection.value =
    stored === "driver_right" ? "driver_right" : "driver_left";
};

const seatsByFloor = computed(() => {
  const grouped = seatMapRaw.value.reduce((acc, seat) => {
    const floor = Number(seat.tang || 1);
    if (!acc[floor]) acc[floor] = [];
    acc[floor].push(seat);
    return acc;
  }, {});

  return Object.entries(grouped)
    .sort((a, b) => Number(a[0]) - Number(b[0]))
    .map(([floor, seats]) => ({
      floor: Number(floor),
      seats: [...seats].sort((x, y) =>
        String(x.ma_ghe || "").localeCompare(String(y.ma_ghe || "")),
      ),
    }));
});

const splitSeatsIntoRows = (seats, numRows) => {
  const list = Array.isArray(seats) ? [...seats] : [];
  if (!list.length) return [];
  const rows = Math.min(8, Math.max(1, Number(numRows) || 1));
  const perRow = Math.ceil(list.length / rows);
  const out = [];
  for (let i = 0; i < list.length; i += perRow) {
    out.push(list.slice(i, i + perRow));
  }
  return out;
};

const isDriverSeat = (seat, floorNumber, row, rowIndex) => {
  if (
    !seat ||
    floorNumber !== 1 ||
    rowIndex !== 0 ||
    !Array.isArray(row) ||
    !row.length
  )
    return false;
  const driverSeat =
    seatDirection.value === "driver_right" ? row[row.length - 1] : row[0];
  return Number(driverSeat?.id_ghe) === Number(seat.id_ghe);
};

const driverSeatIdSet = computed(() => {
  const ids = new Set();
  seatsByFloor.value.forEach((floor) => {
    const rows = splitSeatsIntoRows(floor.seats, seatRowsPerFloor.value);
    rows.forEach((row, rowIndex) => {
      if (rowIndex !== 0 || floor.floor !== 1 || !row.length) return;
      const driverSeat =
        seatDirection.value === "driver_right" ? row[row.length - 1] : row[0];
      if (driverSeat?.id_ghe != null) {
        ids.add(Number(driverSeat.id_ghe));
      }
    });
  });
  return ids;
});

const isDriverSeatById = (seatId) => driverSeatIdSet.value.has(Number(seatId));

// --- METHODS ---
const fetchInitialData = async () => {
  if (!tripId) {
    router.push("/search");
    return;
  }
  try {
    isLoading.value = true;
    const res = await clientApi.getTripSeats(tripId);
    if (res.success) {
      tripData.value = res.data.chuyen_xe;
      const rawSeats = res.data.so_do_ghe;
      if (Array.isArray(rawSeats)) {
        seatMapRaw.value = rawSeats;
      } else if (rawSeats && typeof rawSeats === "object") {
        seatMapRaw.value = Object.values(rawSeats).flat();
      } else {
        seatMapRaw.value = [];
      }
      applySeatDirectionFromDriver();

      // Load stops
      const stopsRes = await clientApi.getTripStops(tripId);
      if (stopsRes.success) {
        stopsData.value = stopsRes.data;
      }

      // Load vouchers from customer wallet
      const vouchersRes = await clientApi.getMyVouchers({
        ma_nha_xe: tripData.value.tuyen_duong.ma_nha_xe,
        usable_only: 1,
      });
      if (vouchersRes.success) {
        vouchers.value = vouchersRes.data || [];
      }

      // Load loyalty points
      const loyaltyRes = await clientApi.getLoyaltyInfo();
      if (loyaltyRes.success) {
        loyaltyInfo.value = loyaltyRes.data;
      }

      // Select appropriate payment method if cash is not allowed
      if (tripData.value.thanh_toan_sau === 0) {
        paymentMethod.value = "chuyen_khoan";
      }
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || "Lỗi tải dữ liệu";
  } finally {
    isLoading.value = false;
    // Subscribe vào kênh realtime sơ đồ ghế sau khi load xong
    subscribeToSeatUpdates();
  }
};

const toggleSeat = (seat) => {
  if (seat.trang_thai !== "trong") return; // Không cho chọn ghế đã đặt/khóa
  if (isDriverSeatById(seat.id_ghe)) return; // Ghế tài xế: disable chọn

  const index = selectedSeats.value.findIndex((s) => s.id_ghe === seat.id_ghe);
  if (index >= 0) {
    selectedSeats.value.splice(index, 1);
  } else {
    if (selectedSeats.value.length >= 6) {
      alert("Chỉ được chọn tối đa 6 ghế 1 lần đặt.");
      return;
    }
    selectedSeats.value.push(seat);
  }
};

const isSeatSelected = (seatId) =>
  selectedSeats.value.some((s) => s.id_ghe === seatId);

const validateStep1 = () => {
  if (selectedSeats.value.length === 0) {
    alert("Vui lòng chọn ít nhất 1 ghế.");
    return false;
  }
  return true;
};

const validateStep2 = () => {
  if (!pickupPointId.value || !dropoffPointId.value) {
    alert("Vui lòng chọn đầy đủ điểm đón và điểm trả.");
    return false;
  }
  return true;
};

const nextStep = () => {
  if (currentStep.value === 1 && !validateStep1()) return;
  if (currentStep.value === 2 && !validateStep2()) return;
  currentStep.value++;
};

const prevStep = () => {
  if (currentStep.value > 1) currentStep.value--;
};

const submitBooking = async () => {
  try {
    isBooking.value = true;
    errorMessage.value = "";

    const payload = {
      id_chuyen_xe: tripData.value.id,
      danh_sach_ghe: selectedSeats.value.map((s) => s.ma_ghe),
      id_tram_don: pickupPointId.value,
      id_tram_tra: dropoffPointId.value,
      ghi_chu: customerNote.value,
      id_voucher: selectedVoucherId.value || null,
      diem_quy_doi: Number(pointsToRedeem.value) || 0,
      phuong_thuc_thanh_toan: paymentMethod.value,
    };

    const res = await clientApi.bookTicket(payload);

    if (res.success) {
      bookingResult.value = res.data;
      currentStep.value = 4; // Hoàn tất

      const echo = createEcho();
      if (echo) {
        echo
          .channel(`ve.${bookingResult.value.ma_ve}`)
          .listen(".ve.huy_tu_dong", (e) => {
            if (bookingResult.value) {
              errorMessage.value =
                e.message ||
                "Giao dịch bị từ chối do hết thời gian thanh toán";
              bookingResult.value.tinh_trang = "huy";
            }
          })
          .listen(".ve.da_thanh_toan", () => {
            if (bookingResult.value) {
              bookingResult.value.tinh_trang = "da_thanh_toan";
            }
          });
      }
    } else {
      errorMessage.value = res.message || "Đặt vé thất bại.";
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || "Có lỗi xảy ra.";
  } finally {
    isBooking.value = false;
  }
};

onMounted(() => {
  const rawSeatRows = localStorage.getItem(SEAT_ROWS_LS_KEY);
  if (rawSeatRows != null) {
    const n = parseInt(rawSeatRows, 10);
    if (!Number.isNaN(n)) {
      seatRowsPerFloor.value = Math.min(8, Math.max(1, n));
    }
  }
  fetchInitialData();
});

onUnmounted(() => {
  // Dọn dẹp: rời kênh Reverb khi rời khỏi trang đặt vé
  if (echoInstance.value && tripId) {
    try {
      echoInstance.value.leaveChannel(`chuyen-xe.${tripId}`);
    } catch (e) {
      // bỏ qua lỗi cleanup
    }
  }
});

/**
 * Subscribe vào kênh Reverb công khai `chuyen-xe.{id}`.
 * Gọi sau khi fetchInitialData() hoàn tất và tripId có giá trị.
 */
const subscribeToSeatUpdates = () => {
  if (!tripId) return;
  try {
    const echo = createEcho();
    echoInstance.value = echo;
    echo
      .channel(`chuyen-xe.${tripId}`)
      .listen(".seat.updated", (e) => {
        isRealtimeConnected.value = true;
        if (!Array.isArray(e.danh_sach_ghe_da_dat)) return;

        // 1. Cập nhật sơ đồ ghế: đánh dấu các ghế vừa bị đặt là da_dat
        e.danh_sach_ghe_da_dat.forEach(({ id_ghe }) => {
          const seat = seatMapRaw.value.find((s) => s.id_ghe === id_ghe);
          if (seat && seat.trang_thai === "trong") {
            seat.trang_thai = "da_dat";
          }
        });

        // 2. Kiểm tra có ghế nào đang chọn bị người khác đặt không
        const conflictSeats = selectedSeats.value.filter((s) =>
          e.danh_sach_ghe_da_dat.some((d) => d.id_ghe === s.id_ghe),
        );

        if (conflictSeats.length > 0) {
          // Xoá các ghế bị conflict khỏi danh sách đang chọn
          selectedSeats.value = selectedSeats.value.filter(
            (s) => !conflictSeats.some((c) => c.id_ghe === s.id_ghe),
          );

          const conflictNames = conflictSeats
            .map((s) => s.ma_ghe)
            .join(", ");
          seatConflictAlert.value = {
            message: `⚠️ Ghế ${conflictNames} vừa được người khác đặt! Đã tự động bỏ chọn, vui lòng chọn ghế khác.`,
            seats: conflictSeats,
          };

          // Tự ẩn sau 6 giây
          setTimeout(() => {
            seatConflictAlert.value = null;
          }, 6000);
        }
      });
    isRealtimeConnected.value = true;
  } catch (err) {
    // Reverb không khả dụng — không ảnh hưởng luồng đặt vé
    console.warn("[SeatMap] Không thể kết nối realtime:", err.message);
  }
};
</script>

<template>
  <div class="booking-page container mx-auto py-8 px-4 max-w-7xl">
    <!-- Loading State -->
    <div
      v-if="isLoading"
      class="flex flex-col items-center justify-center min-h-[50vh]"
    >
      <div
        class="loader mb-4 border-4 border-t-blue-500 border-r-blue-500 border-b-blue-200 border-l-blue-200 rounded-full w-12 h-12 animate-spin"
      ></div>
      <p class="text-slate-600 font-medium">Đang tải dữ liệu chuyến xe...</p>
    </div>

    <template v-else-if="tripData">
      <!-- Title & Stepper -->
      <div class="mb-8 pl-4">
        <h1 class="text-3xl font-extrabold text-slate-900 mb-2">
          Hoàn tất đặt vé
        </h1>
        <p class="text-slate-500">
          {{ tripData.tuyen_duong?.ten_tuyen_duong }} • Khởi hành:
          {{ tripData.gio_khoi_hanh }}
        </p>
      </div>

      <div class="flex flex-col lg:flex-row gap-8">
        <!-- Main Content Box -->
        <div class="lg:w-[62%]">
          <!-- Stepper Indicator -->
          <div
            class="flex items-center mb-8 glass-card p-4 rounded-2xl relative z-10"
          >
            <template v-for="step in 3" :key="step">
              <div class="flex items-center relative z-20">
                <div
                  class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-colors duration-300"
                  :class="
                    currentStep >= step
                      ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30'
                      : 'bg-slate-100 text-slate-400'
                  "
                >
                  {{ step }}
                </div>
                <span
                  class="ml-3 font-medium hidden sm:block"
                  :class="
                    currentStep >= step ? 'text-blue-600' : 'text-slate-400'
                  "
                >
                  {{
                    step === 1
                      ? "Chọn ghế"
                      : step === 2
                        ? "Điểm bắt xe"
                        : "Thanh toán"
                  }}
                </span>
              </div>
              <div
                v-if="step < 3"
                class="flex-1 h-1 mx-4 rounded-full transition-colors duration-300"
                :class="currentStep > step ? 'bg-blue-600' : 'bg-slate-100'"
              ></div>
            </template>
          </div>

          <!-- Error Alert -->
          <div
            v-if="errorMessage"
            class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center gap-2"
          >
            <span class="material-symbols-outlined">error</span>
            {{ errorMessage }}
          </div>

          <!-- STEP 1: CHỌN GHẾ -->
          <div
            v-if="currentStep === 1"
            class="glass-card rounded-2xl p-6 fade-in"
          >
            <h2
              class="text-xl font-bold mb-6 flex items-center gap-2 text-slate-800"
            >
              <span class="material-symbols-outlined text-blue-500"
                >airline_seat_recline_normal</span
              >
              Sơ đồ ghế
              <!-- Badge Live Realtime -->
              <span
                class="ml-auto flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full"
                :class="isRealtimeConnected
                  ? 'bg-emerald-100 text-emerald-700'
                  : 'bg-slate-100 text-slate-400'"
              >
                <span
                  class="w-1.5 h-1.5 rounded-full"
                  :class="isRealtimeConnected ? 'bg-emerald-500 animate-pulse' : 'bg-slate-300'"
                ></span>
                {{ isRealtimeConnected ? 'Cập nhật tự động' : 'Đang kết nối...' }}
              </span>
            </h2>

            <!-- Alert khi ghế đang chọn bị người khác đặt (realtime conflict) -->
            <transition name="fade-slide">
              <div
                v-if="seatConflictAlert"
                class="mb-4 flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-sm font-medium"
              >
                <span class="material-symbols-outlined text-amber-500 shrink-0 text-xl">warning</span>
                <div>
                  <div class="font-bold mb-0.5">Ghế vừa được đặt bởi khách khác!</div>
                  <div>{{ seatConflictAlert.message }}</div>
                </div>
                <button
                  @click="seatConflictAlert = null"
                  class="ml-auto shrink-0 text-amber-400 hover:text-amber-600 transition-colors"
                >
                  <span class="material-symbols-outlined text-lg">close</span>
                </button>
              </div>
            </transition>

            <div class="seat-map-wrap">
              <div class="seat-legend-row">
                <div class="seat-legend">
                  <span class="legend-item"
                    ><span class="seat-dot dot-active"></span> Hoạt động</span
                  >
                  <span class="legend-item"
                    ><span class="seat-dot dot-booked"></span> Đã đặt</span
                  >
                  <span class="legend-item"
                    ><span class="seat-dot dot-locked"></span> Khóa / bảo
                    trì</span
                  >
                  <span class="legend-item"
                    ><span class="seat-dot dot-driver"></span> Ghế tài xế</span
                  >
                </div>
              </div>

              <div
                v-for="floor in seatsByFloor"
                :key="floor.floor"
                class="seat-floor-block"
              >
                <h4 class="seat-floor-title">Tầng {{ floor.floor }}</h4>
                <div
                  v-for="(row, ri) in splitSeatsIntoRows(
                    floor.seats,
                    seatRowsPerFloor,
                  )"
                  :key="ri"
                  class="seat-row"
                  :style="{ '--seat-cols': Math.max(row.length, 1) }"
                >
                  <button
                    v-for="seat in row"
                    :key="seat.id_ghe"
                    type="button"
                    class="seat-tile"
                    :disabled="
                      seat.trang_thai !== 'trong' ||
                      isDriverSeatById(seat.id_ghe)
                    "
                    :class="{
                      blocked: seat.trang_thai === 'bao_tri_hoac_khoa',
                      booked: seat.trang_thai === 'da_dat',
                      editing: isSeatSelected(seat.id_ghe),
                      driver:
                        isDriverSeat(seat, floor.floor, row, ri) &&
                        seat.trang_thai === 'trong',
                    }"
                    @click="toggleSeat(seat)"
                  >
                    {{ seat.ma_ghe }}
                  </button>
                </div>
              </div>
            </div>

            <div class="flex justify-end mt-8">
              <button
                @click="nextStep"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-10 rounded-xl transition-all duration-300 flex items-center justify-center shadow-lg shadow-blue-500/30 active:scale-95 w-full sm:w-auto"
              >
                Tiếp tục
              </button>
            </div>
          </div>


          <!-- STEP 2: ĐIỂM ĐÓN / TRẢ -->
          <div
            v-if="currentStep === 2"
            class="glass-card rounded-2xl p-5 fade-in"
          >
            <h2
              class="text-xl font-bold mb-6 flex items-center gap-2 text-slate-800"
            >
              <span class="material-symbols-outlined text-blue-500"
                >location_on</span
              >
              Thông tin đón trả
            </h2>

            <div class="grid md:grid-cols-2 gap-6">
              <!-- Điểm đón -->
              <div>
                <h4 class="font-semibold text-slate-700 mb-4 pb-2 border-b">
                  Điểm Đón
                </h4>
                <div
                  v-if="stopsData.tram_don.length === 0"
                  class="text-slate-500 italic"
                >
                  Không có dữ liệu trạm đón.
                </div>
                <div
                  class="space-y-3 max-h-[350px] overflow-y-auto pr-2 custom-scroll"
                >
                  <label
                    v-for="stop in stopsData.tram_don"
                    :key="'don-' + stop.id"
                    class="radio-card block"
                  >
                    <input
                      type="radio"
                      :value="stop.id"
                      v-model="pickupPointId"
                      name="pickup"
                    />
                    <div
                      class="card-content flex items-start p-4 gap-3 border-2 rounded-xl cursor-pointer transition-all relative overflow-hidden h-full min-h-[90px]"
                      :class="
                        pickupPointId === stop.id
                          ? 'bg-blue-50 border-blue-200'
                          : 'bg-white border-slate-200 hover:border-blue-300'
                      "
                    >
                      <!-- Thanh xanh bên trái khi được chọn -->
                      <div
                        v-if="pickupPointId === stop.id"
                        class="absolute left-0 top-0 bottom-0 w-1.5 bg-blue-600"
                      ></div>

                      <div
                        class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all shrink-0 mt-1"
                        :class="
                          pickupPointId === stop.id
                            ? 'border-blue-600'
                            : 'border-slate-300'
                        "
                      >
                        <div
                          class="w-2.5 h-2.5 rounded-full transition-all"
                          :class="
                            pickupPointId === stop.id
                              ? 'bg-blue-600'
                              : 'bg-transparent'
                          "
                        ></div>
                      </div>
                      <div class="flex-1">
                        <span
                          class="font-bold block text-slate-900 text-base mb-1"
                          >{{ stop.ten_tram }}</span
                        >
                        <span
                          class="text-sm text-slate-500 block leading-relaxed"
                          >{{ stop.dia_chi }}</span
                        >
                      </div>
                    </div>
                  </label>
                </div>
              </div>

              <!-- Điểm trả -->
              <div>
                <h4 class="font-semibold text-slate-700 mb-4 pb-2 border-b">
                  Điểm Trả
                </h4>
                <div
                  v-if="stopsData.tram_tra.length === 0"
                  class="text-slate-500 italic"
                >
                  Không có dữ liệu trạm trả.
                </div>
                <div
                  class="space-y-3 max-h-[350px] overflow-y-auto pr-2 custom-scroll"
                >
                  <label
                    v-for="stop in stopsData.tram_tra"
                    :key="'tra-' + stop.id"
                    class="radio-card block"
                  >
                    <input
                      type="radio"
                      :value="stop.id"
                      v-model="dropoffPointId"
                      name="dropoff"
                    />
                    <div
                      class="card-content flex items-start p-4 gap-3 border-2 rounded-xl cursor-pointer transition-all relative overflow-hidden h-full min-h-[90px]"
                      :class="
                        dropoffPointId === stop.id
                          ? 'bg-blue-50 border-blue-200'
                          : 'bg-white border-slate-200 hover:border-blue-300'
                      "
                    >
                      <!-- Thanh xanh bên trái khi được chọn -->
                      <div
                        v-if="dropoffPointId === stop.id"
                        class="absolute left-0 top-0 bottom-0 w-1.5 bg-blue-600"
                      ></div>

                      <div
                        class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all shrink-0 mt-1"
                        :class="
                          dropoffPointId === stop.id
                            ? 'border-blue-600'
                            : 'border-slate-300'
                        "
                      >
                        <div
                          class="w-2.5 h-2.5 rounded-full transition-all"
                          :class="
                            dropoffPointId === stop.id
                              ? 'bg-blue-600'
                              : 'bg-transparent'
                          "
                        ></div>
                      </div>
                      <div class="flex-1">
                        <span
                          class="font-bold block text-slate-900 text-base mb-1"
                          >{{ stop.ten_tram }}</span
                        >
                        <span
                          class="text-sm text-slate-500 block leading-relaxed"
                          >{{ stop.dia_chi }}</span
                        >
                      </div>
                    </div>
                  </label>
                </div>
              </div>
            </div>

            <div class="mt-8">
              <label class="block font-semibold text-slate-700 mb-2 text-sm"
                >Ghi chú yêu cầu (Tùy chọn)</label
              >
              <textarea
                v-model="customerNote"
                rows="5"
                class="w-full border-2 border-slate-200 rounded-xl p-3 focus:outline-none focus:border-blue-500 bg-slate-50 transition-colors"
                placeholder="Yêu cầu điểm đón khác ngoài danh sách (nhà xe sẽ liên hệ xác nhận)..."
              ></textarea>
            </div>

            <!-- Action Buttons Step 2 -->
            <div
              class="flex justify-between mt-10 pt-6 border-t border-slate-100"
            >
              <button
                @click="prevStep"
                class="flex items-center gap-2 px-6 py-3.5 rounded-xl border-2 border-slate-200 text-slate-600 font-bold hover:bg-slate-50 hover:border-slate-300 transition-all active:scale-95"
              >
                Quay lại
              </button>
              <button
                @click="nextStep"
                class="flex items-center gap-2 px-10 py-3.5 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 shadow-lg shadow-blue-500/20 transition-all active:scale-95"
              >
                Tiếp tục
              </button>
            </div>
          </div>

          <!-- STEP 3: THANH TOÁN -->
          <div
            v-if="currentStep === 3"
            class="glass-card rounded-2xl p-6 fade-in"
          >
            <h2
              class="text-xl font-bold mb-6 pb-4 border-b border-slate-100 text-slate-800"
            >
              Xác nhận thanh toán
            </h2>

            <!-- Khuyến mãi -->
            <div class="mb-10">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-slate-800">Mã giảm giá</h3>
                <button
                  type="button"
                  class="text-blue-600 font-bold text-sm hover:underline"
                >
                  Chọn hoặc nhập mã
                </button>
              </div>

              <div
                v-if="vouchers.length === 0"
                class="flex items-center justify-center py-6 gap-3 text-slate-400 bg-slate-50/50 rounded-xl border-2 border-dashed border-slate-200/60"
              >
                <span class="material-symbols-outlined text-2xl text-slate-300"
                  >confirmation_number</span
                >
                <div class="text-xs font-medium italic">
                  Hiện chưa có mã giảm giá khả dụng
                </div>
              </div>

              <div
                v-else
                class="flex gap-4 overflow-x-auto pb-4 custom-scroll-h"
              >
                <div
                  v-for="vc in vouchers"
                  :key="vc.id"
                  class="flex-shrink-0 w-[280px] h-32 flex border rounded-xl overflow-hidden cursor-pointer transition-all relative group"
                  :class="
                    selectedVoucherId === vc.id
                      ? 'border-blue-500 ring-1 ring-blue-500 shadow-md'
                      : 'border-slate-200 hover:border-blue-300 hover:shadow-sm'
                  "
                  @click="
                    selectedVoucherId =
                      selectedVoucherId === vc.id ? null : vc.id
                  "
                >
                  <!-- Cánh trái vé (Logo/Icon) -->
                  <div
                    class="w-[85px] bg-slate-700 flex flex-col items-center justify-center p-2 text-white relative"
                  >
                    <div
                      class="absolute -right-1.5 top-0 bottom-0 flex flex-col justify-around py-1 z-10"
                    >
                      <div
                        v-for="i in 6"
                        :key="i"
                        class="w-3 h-3 rounded-full bg-white -mr-1.5"
                      ></div>
                    </div>
                    <span
                      class="material-symbols-outlined text-3xl mb-1 opacity-80"
                      >directions_bus</span
                    >
                    <div
                      class="text-[10px] font-bold uppercase tracking-tighter opacity-60"
                    >
                      {{ vc.ten_voucher }}
                    </div>
                  </div>

                  <!-- Cánh phải vé (Info) -->
                  <div
                    class="flex-1 bg-white p-3 flex flex-col justify-between relative"
                  >
                    <!-- Checkmark cho mã đang chọn -->
                    <div
                      v-if="selectedVoucherId === vc.id"
                      class="absolute top-2 right-2"
                    >
                      <span
                        class="material-symbols-outlined text-blue-600 font-bold text-xl"
                        >check_circle</span
                      >
                    </div>

                    <div>
                      <div class="text-lg font-black text-slate-800">
                        Giảm
                        {{
                          vc.loai_voucher === "percent"
                            ? vc.gia_tri + "%"
                            : formatPrice(vc.gia_tri)
                        }}
                      </div>
                      <div class="text-[11px] text-slate-500 font-medium">
                        Đơn hàng tối thiểu từ 0đ
                      </div>
                    </div>

                    <div
                      class="bg-amber-50 text-[10px] text-amber-700 px-2 py-1 rounded font-bold flex items-center gap-1"
                    >
                      <span class="material-symbols-outlined text-[12px]"
                        >info</span
                      >
                      Hãy chọn phương thức thanh toán
                    </div>
                  </div>
                </div>
              </div>

              <div
                class="text-xs text-slate-500 mt-3 font-medium flex items-center gap-1.5"
              >
                <span class="material-symbols-outlined text-sm">info</span>
                Bạn có thể áp dụng nhiều mã cùng lúc
              </div>
            </div>

            <!-- Điểm thành viên -->
            <div
              v-if="loyaltyInfo && loyaltyInfo.diem_hien_tai > 0"
              class="mb-10 bg-blue-50/50 p-6 rounded-2xl border border-blue-100"
            >
              <div class="flex justify-between items-center mb-4">
                <h3
                  class="text-xl font-bold text-slate-800 flex items-center gap-2"
                >
                  <span class="material-symbols-outlined text-blue-600"
                    >stars</span
                  >
                  Dùng điểm tích lũy
                </h3>
                <div class="text-sm font-bold text-blue-700">
                  Bạn có: {{ loyaltyInfo.diem_hien_tai }} điểm
                </div>
              </div>

              <div class="flex flex-col sm:flex-row items-center gap-4">
                <div class="relative flex-1 w-full">
                  <input
                    type="number"
                    v-model="pointsToRedeem"
                    :max="loyaltyInfo.diem_hien_tai"
                    min="0"
                    step="10"
                    class="w-full bg-white border-2 border-slate-200 rounded-xl px-4 py-3 pr-16 focus:outline-none focus:border-blue-500 font-bold text-slate-700"
                    placeholder="Nhập số điểm muốn dùng..."
                  />
                  <div
                    class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1"
                  >
                    <button
                      @click="pointsToRedeem = loyaltyInfo.diem_hien_tai"
                      class="text-[10px] font-black bg-blue-600 text-white px-2 py-1 rounded uppercase tracking-tighter"
                    >
                      MAX
                    </button>
                  </div>
                </div>
                <div class="shrink-0 text-slate-500 font-bold hidden sm:block">
                  <span class="material-symbols-outlined">sync_alt</span>
                </div>
                <div
                  class="bg-white border-2 border-dashed border-emerald-200 rounded-xl px-6 py-3 flex-1 w-full text-center"
                >
                  <div
                    class="text-[10px] uppercase font-black text-slate-400 tracking-widest mb-0.5"
                  >
                    Tiết kiệm được
                  </div>
                  <div class="text-lg font-black text-emerald-600">
                    {{ formatPrice(pointsDiscountAmount) }}
                  </div>
                </div>
              </div>
              <p class="text-[11px] text-slate-500 mt-3 italic">
                * Quy đổi: 100 điểm = 10.000đ. Số điểm sử dụng phải nhỏ hơn hoặc
                bằng số dư hiện có.
              </p>
            </div>

            <!-- Phương thức TT -->
            <div class="mb-8">
              <h3 class="text-xl font-bold text-slate-800 mb-4">
                Phương thức thanh toán
              </h3>
              <div class="space-y-3">
                <label
                  v-if="tripData.thanh_toan_sau !== 0"
                  class="block cursor-pointer group"
                >
                  <input
                    type="radio"
                    value="tien_mat"
                    v-model="paymentMethod"
                    class="hidden"
                  />
                  <div
                    class="flex items-center p-4 border-2 rounded-xl transition-all relative overflow-hidden"
                    :class="
                      paymentMethod === 'tien_mat'
                        ? 'bg-emerald-50/50 border-emerald-200'
                        : 'bg-white border-slate-100 hover:border-emerald-200'
                    "
                  >
                    <div
                      v-if="paymentMethod === 'tien_mat'"
                      class="absolute left-0 top-0 bottom-0 w-1.5 bg-emerald-500"
                    ></div>
                    <span
                      class="material-symbols-outlined mr-4 text-3xl transition-colors"
                      :class="
                        paymentMethod === 'tien_mat'
                          ? 'text-emerald-600'
                          : 'text-slate-400'
                      "
                      >payments</span
                    >
                    <div class="flex-1">
                      <div
                        class="font-bold"
                        :class="
                          paymentMethod === 'tien_mat'
                            ? 'text-emerald-800'
                            : 'text-slate-700'
                        "
                      >
                        Tiền mặt tại nhà xe / Khi lên xe
                      </div>
                      <div class="text-xs text-slate-500">
                        Thanh toán trực tiếp cho lơ xe hoặc tại quầy vé.
                      </div>
                    </div>
                    <div
                      class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 ml-4"
                      :class="
                        paymentMethod === 'tien_mat'
                          ? 'border-emerald-500'
                          : 'border-slate-300'
                      "
                    >
                      <div
                        class="w-2.5 h-2.5 rounded-full"
                        :class="
                          paymentMethod === 'tien_mat'
                            ? 'bg-emerald-500'
                            : 'bg-transparent'
                        "
                      ></div>
                    </div>
                  </div>
                </label>

                <div
                  v-else
                  class="p-4 bg-amber-50 text-amber-700 rounded-xl text-sm flex items-center gap-3 mb-3 border border-amber-200"
                >
                  <span
                    class="material-symbols-outlined shrink-0 text-amber-500"
                    >info</span
                  >
                  Chuyến xe này không hỗ trợ thanh toán bằng tiền mặt. Vui lòng
                  chuyển khoản trước.
                </div>

                <label class="block cursor-pointer group">
                  <input
                    type="radio"
                    value="chuyen_khoan"
                    v-model="paymentMethod"
                    class="hidden"
                  />
                  <div
                    class="flex items-center p-4 border-2 rounded-xl transition-all relative overflow-hidden"
                    :class="
                      paymentMethod === 'chuyen_khoan'
                        ? 'bg-blue-50 border-blue-200'
                        : 'bg-white border-slate-100 hover:border-blue-200'
                    "
                  >
                    <div
                      v-if="paymentMethod === 'chuyen_khoan'"
                      class="absolute left-0 top-0 bottom-0 w-1.5 bg-blue-600"
                    ></div>
                    <span
                      class="material-symbols-outlined mr-4 text-3xl transition-colors"
                      :class="
                        paymentMethod === 'chuyen_khoan'
                          ? 'text-blue-600'
                          : 'text-slate-400'
                      "
                      >qr_code_scanner</span
                    >
                    <div class="flex-1">
                      <div
                        class="font-bold"
                        :class="
                          paymentMethod === 'chuyen_khoan'
                            ? 'text-blue-800'
                            : 'text-slate-700'
                        "
                      >
                        Chuyển khoản (VietQR / SePay)
                      </div>
                      <div class="text-xs text-slate-500">
                        Mở ứng dụng ngân hàng và quét mã để thanh toán tự động
                        tiện lợi.
                      </div>
                    </div>
                    <div
                      class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 ml-4"
                      :class="
                        paymentMethod === 'chuyen_khoan'
                          ? 'border-blue-600'
                          : 'border-slate-300'
                      "
                    >
                      <div
                        class="w-2.5 h-2.5 rounded-full"
                        :class="
                          paymentMethod === 'chuyen_khoan'
                            ? 'bg-blue-600'
                            : 'bg-transparent'
                        "
                      ></div>
                    </div>
                  </div>
                </label>
              </div>
            </div>

            <!-- Action Buttons Step 3 -->
            <div
              class="flex justify-between mt-10 pt-6 border-t border-slate-100"
            >
              <button
                @click="prevStep"
                class="flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl border-2 border-slate-200 text-slate-600 font-bold hover:bg-slate-50 hover:border-slate-300 transition-all active:scale-95"
              >
                Quay lại
              </button>
              <button
                @click="submitBooking"
                :disabled="isBooking"
                class="flex items-center justify-center gap-2 px-10 py-3.5 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 shadow-lg shadow-blue-500/20 transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span
                  v-if="isBooking"
                  class="material-symbols-outlined animate-spin"
                  >sync</span
                >
                {{ isBooking ? "Đang xử lý..." : "Xác nhận đặt vé" }}
              </button>
            </div>
          </div>

          <!-- STEP 4: HOÀN TẤT -->
          <div
            v-if="currentStep === 4 && bookingResult"
            class="glass-card rounded-2xl p-8 text-center fade-in max-w-2xl mx-auto relative"
          >
            <div
              v-if="bookingResult.tinh_trang === 'huy'"
              class="absolute inset-0 bg-white/90 backdrop-blur-sm z-50 rounded-2xl flex flex-col items-center justify-center p-8 text-center"
            >
              <span class="material-symbols-outlined text-red-500 text-6xl mb-4"
                >cancel</span
              >
              <h3 class="text-2xl font-bold text-slate-800 mb-2">
                Giao dịch bị từ chối
              </h3>
              <p class="text-red-500 font-medium mb-6">
                Vé của bạn đã bị huỷ do hết thời gian thanh toán.
              </p>
              <button @click="currentStep = 1" class="btn-primary">
                <span class="material-symbols-outlined mr-2">refresh</span> Đặt
                lại vé
              </button>
            </div>

            <div
              class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6"
              :class="
                bookingResult.tinh_trang === 'da_thanh_toan' ||
                paymentMethod === 'tien_mat'
                  ? 'bg-emerald-100'
                  : 'bg-blue-100'
              "
            >
              <span
                v-if="
                  bookingResult.tinh_trang === 'da_thanh_toan' ||
                  paymentMethod === 'tien_mat'
                "
                class="material-symbols-outlined text-emerald-500 text-5xl"
                >check_circle</span
              >
              <span
                v-else
                class="material-symbols-outlined text-blue-500 text-4xl animate-spin"
                >hourglass_empty</span
              >
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-2">
              <template
                v-if="
                  bookingResult.tinh_trang === 'da_thanh_toan' ||
                  paymentMethod === 'tien_mat'
                "
                >Đặt vé thành công!</template
              >
              <template v-else>Đang chờ thanh toán...</template>
            </h2>
            <p class="text-slate-500 mb-6">
              Cảm ơn bạn đã sử dụng dịch vụ. Mã đặt vé của bạn là:
            </p>

            <div
              class="bg-slate-50 border border-slate-200 rounded-xl p-6 mx-auto max-w-sm mb-6 relative"
            >
              <div class="text-3xl font-black text-blue-600 tracking-widest">
                {{ bookingResult.ma_ve }}
              </div>
              <div class="text-sm mt-2 text-slate-500">
                Vui lòng cung cấp mã này khi lên xe.
              </div>
            </div>

            <!-- VietQR Display -->
            <div
              v-if="
                paymentMethod === 'chuyen_khoan' &&
                bookingResult.tinh_trang === 'dang_cho'
              "
              class="mb-8 pt-6 border-t border-dashed border-slate-200"
            >
              <h3 class="font-bold text-slate-800 mb-3 text-lg">
                Quét mã QR để thanh toán tự động
              </h3>
              <p class="text-sm text-slate-500 mb-4 px-4">
                Vui lòng sử dụng Ứng dụng Ngân hàng để quét mã QR bên dưới.<br />Hệ
                thống sẽ <b>Tự động xác nhận</b> trong vòng vài giây sau khi
                giao dịch thành công.
              </p>

              <div
                class="inline-block p-4 mx-auto border-2 border-slate-200 rounded-2xl bg-white focus-within:ring hover:shadow-xl transition-all"
              >
                <img
                  :src="vietQrUrl"
                  alt="Mã VietQR Thanh Toán"
                  class="w-64 h-auto rounded-xl mx-auto mb-2 mix-blend-multiply"
                />
                <div class="text-sm text-left px-2">
                  <div class="flex justify-between items-center py-2 border-b">
                    <span class="text-slate-500">Ngân hàng hưởng:</span>
                    <span class="font-bold text-slate-800">{{ bankId }}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b">
                    <span class="text-slate-500">Chủ tài khoản:</span>
                    <span class="font-bold text-slate-800 uppercase">{{
                      accountName
                    }}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b">
                    <span class="text-slate-500">Số tài khoản:</span>
                    <span class="font-bold text-slate-800 text-lg">{{
                      bankAccount
                    }}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b">
                    <span class="text-slate-500">Số tiền:</span>
                    <span class="font-bold text-blue-600 text-lg">{{
                      formatPrice(finalPrice)
                    }}</span>
                  </div>
                  <div
                    class="flex justify-between items-center py-2 bg-yellow-50 px-2 mt-2 -mx-2 rounded"
                  >
                    <span class="text-slate-600 font-medium">Nội dung CK:</span>
                    <span class="font-black text-slate-900 tracking-widest">{{
                      bookingResult.ma_ve
                    }}</span>
                  </div>
                </div>
              </div>
              <div
                class="text-sm text-emerald-600 font-semibold mt-4 flex items-center justify-center gap-1"
              >
                <span class="material-symbols-outlined text-lg animate-spin"
                  >autorenew</span
                >
                Đang chờ thanh toán...
              </div>
            </div>

            <div class="flex justify-center pt-4">
              <button
                @click="$router.push('/')"
                class="px-10 py-3.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/30 hover:shadow-xl active:scale-95"
              >
                Quay về Trang Chủ
              </button>
            </div>
          </div>
        </div>

        <!-- Sidebar Summary -->
        <div class="lg:w-[38%]">
          <div class="glass-card rounded-2xl p-6 h-full flex flex-col">
            <h3
              class="font-bold text-xl mb-6 text-slate-800 pb-4 border-b shrink-0"
            >
              Thông tin đặt vé
            </h3>

            <div class="space-y-4 mb-6 flex-1">
              <div>
                <span
                  class="block text-xs text-slate-400 font-bold uppercase tracking-wider mb-1.5"
                  >Nhà xe</span
                >
                <span class="block text-base font-bold text-blue-600">{{
                  tripData.tuyen_duong?.nha_xe?.ten_nha_xe
                }}</span>
              </div>

              <div>
                <span
                  class="block text-xs text-slate-400 font-bold uppercase tracking-wider mb-1.5"
                  >Chuyến xe</span
                >
                <span class="block text-base font-bold text-slate-800">{{
                  tripData.tuyen_duong?.ten_tuyen_duong
                }}</span>
              </div>

              <div>
                <span
                  class="block text-xs text-slate-400 font-bold uppercase tracking-wider mb-1.5"
                  >Loại xe</span
                >
                <span class="block text-base font-bold text-slate-700">{{
                  tripData.xe?.loai_xe?.ten_loai_xe || "Xe giường nằm"
                }}</span>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <span
                    class="block text-xs text-slate-400 font-bold uppercase tracking-wider mb-1.5"
                    >Ngày đi</span
                  >
                  <span class="block text-base font-bold text-slate-800">{{
                    formatDate(tripData.ngay_khoi_hanh)
                  }}</span>
                </div>
                <div>
                  <span
                    class="block text-xs text-slate-400 font-bold uppercase tracking-wider mb-1.5"
                    >Giờ đi</span
                  >
                  <span class="block text-base font-bold text-emerald-600">{{
                    tripData.gio_khoi_hanh
                  }}</span>
                </div>
              </div>

              <div>
                <span
                  class="block text-xs text-slate-400 font-bold uppercase tracking-wider mb-1.5"
                  >Ghế đã chọn ({{ selectedSeats.length }})</span
                >
                <div class="flex flex-wrap gap-2.5 mt-2">
                  <span
                    v-for="s in selectedSeats"
                    :key="s.id_ghe"
                    class="bg-blue-100 text-blue-700 text-base font-bold px-4 py-2 rounded-lg inline-block"
                  >
                    {{ s.ma_ghe }}
                  </span>
                  <span
                    v-if="selectedSeats.length === 0"
                    class="text-base text-slate-400 italic"
                    >Chưa chọn ghế</span
                  >
                </div>
              </div>
            </div>

            <div
              class="border-t border-dashed border-slate-200 pt-4 space-y-3 mt-auto shrink-0"
            >
              <div class="flex justify-between text-sm">
                <span class="text-slate-500"
                  >Giá vé (x{{ selectedSeats.length }})</span
                >
                <span class="font-medium text-slate-700">{{
                  formatPrice(baseTotalPrice)
                }}</span>
              </div>

              <div
                v-if="discountAmount > 0"
                class="flex justify-between text-sm text-green-600"
              >
                <span>Khuyến mãi Voucher</span>
                <span>-{{ formatPrice(discountAmount) }}</span>
              </div>

              <div
                v-if="pointsDiscountAmount > 0"
                class="flex justify-between text-sm text-emerald-600"
              >
                <span>Giảm giá từ điểm</span>
                <span>-{{ formatPrice(pointsDiscountAmount) }}</span>
              </div>

              <div class="flex justify-between items-center mt-2 pt-3 border-t">
                <span class="font-bold text-slate-800">Tổng thanh toán</span>
                <span class="text-2xl font-black text-blue-600">{{
                  formatPrice(finalPrice)
                }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.glass-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 10px 40px rgba(15, 23, 42, 0.05);
}

.btn-primary {
  @apply bg-blue-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 flex items-center justify-center border border-transparent shadow-lg shadow-blue-500/30 hover:bg-blue-700 hover:shadow-blue-500/50 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed;
}

.btn-outline {
  @apply bg-white text-slate-700 font-bold py-3 px-6 rounded-xl transition-all duration-300 flex items-center justify-center border-2 border-slate-200 hover:border-slate-300 hover:bg-slate-50 active:scale-95;
}

/* Radio Card Styles */
.radio-card input[type="radio"] {
  display: none;
}

/* Voucher Card Styles */
.voucher-card input[type="radio"] {
  display: none;
}
.voucher-card input[type="radio"]:checked + .card-content {
  @apply border-blue-500 bg-blue-50;
}
.voucher-card input[type="radio"]:checked + .card-content .radio-indicator {
  @apply border-blue-500 bg-blue-500;
}
.voucher-card
  input[type="radio"]:checked
  + .card-content
  .radio-indicator::after {
  content: "";
  @apply absolute w-1.5 h-1.5 bg-white rounded-full left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2;
}

.radio-indicator {
  @apply w-4 h-4 rounded-full border-2 border-slate-300 relative transition-colors;
}

.fade-in {
  animation: fadeIn 0.4s ease-out;
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

/* Custom Scrollbar */
.custom-scroll::-webkit-scrollbar {
  width: 6px;
}
.custom-scroll::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.seat-map-wrap {
  margin: 12px 0 14px;
  padding: 10px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
}

.seat-legend-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.seat-legend {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 20px;
  font-size: 14px;
  color: #334155;
  flex: 1;
  min-width: 0;
}

.legend-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.seat-dot {
  width: 18px;
  height: 18px;
  border-radius: 6px;
  display: inline-block;
}

.dot-active {
  background: #dcfce7;
  border: 1px solid #86efac;
}

.dot-booked {
  background: #ffedd5;
  border: 1px solid #ea580c;
}

.dot-locked {
  background: #e2e8f0;
  border: 1px solid #475569;
}

.dot-driver {
  background: #fef3c7;
  border: 1px solid #fcd34d;
}

.seat-floor-block {
  margin-bottom: 14px;
}

.seat-floor-title {
  font-size: 14px;
  color: #475569;
  margin: 0 0 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}

.seat-row {
  display: grid;
  grid-template-columns: repeat(var(--seat-cols), minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 12px;
}

.seat-floor-block .seat-row:last-child {
  margin-bottom: 0;
}

.seat-tile {
  width: 100%;
  border: 2px solid #86efac;
  background: #dcfce7;
  color: #166534;
  border-radius: 12px;
  padding: 12px 4px;
  font-weight: 800;
  font-size: 17px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 54px;
}

.seat-tile:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 10px rgba(22, 163, 74, 0.2);
}

.seat-tile.blocked {
  border-width: 2px;
  border-color: #64748b;
  background: #f1f5f9;
  color: #1e293b;
}

.seat-tile.blocked:hover {
  box-shadow: 0 4px 10px rgba(71, 85, 105, 0.25);
}

.seat-tile.booked {
  border-width: 2px;
  border-color: #fb923c;
  background: #fff7ed;
  color: #c2410c;
  cursor: not-allowed;
}

.seat-tile.booked:hover {
  box-shadow: 0 4px 10px rgba(234, 88, 12, 0.2);
  transform: none;
}

.seat-tile.editing {
  border-width: 2px;
  border-color: #60a5fa;
  background: #dbeafe;
  color: #1d4ed8;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.seat-tile.driver {
  border-width: 2px;
  border-color: #f59e0b;
  background: #fef3c7;
  color: #92400e;
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15);
}

/* Transition cho conflict alert */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
