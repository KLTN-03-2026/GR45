<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import clientApi from '@/api/clientApi';

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
const customerNote = ref('');
const selectedVoucherId = ref(null);
const paymentMethod = ref('tien_mat');

const bookingResult = ref(null);
const errorMessage = ref('');

// --- COMPUTED ---
const selectedVoucher = computed(() => {
  return vouchers.value.find(v => v.id === selectedVoucherId.value) || null;
});

const baseTotalPrice = computed(() => {
  if (!tripData.value) return 0;
  const basePrice = parseFloat(tripData.value.tuyen_duong.gia_ve_co_ban || 0);
  return basePrice * selectedSeats.value.length;
});

const discountAmount = computed(() => {
  if (!selectedVoucher.value) return 0;
  let dist = 0;
  if (selectedVoucher.value.loai_voucher === 'percent') {
    dist = (baseTotalPrice.value * parseFloat(selectedVoucher.value.gia_tri)) / 100;
  } else {
    dist = parseFloat(selectedVoucher.value.gia_tri);
  }
  return dist > baseTotalPrice.value ? baseTotalPrice.value : dist;
});

const finalPrice = computed(() => {
  return baseTotalPrice.value - discountAmount.value;
});

// Cấu hình VietQR
const bankId = import.meta.env.VITE_BANK_ID || 'MB';
const bankAccount = import.meta.env.VITE_BANK_ACCOUNT || '0377417720';
const accountName = import.meta.env.VITE_ACCOUNT_NAME || 'NGUYEN HUU THAI';

const vietQrUrl = computed(() => {
  if (!bookingResult.value) return '';
  return `https://img.vietqr.io/image/${bankId}-${bankAccount}-compact2.png?amount=${finalPrice.value}&addInfo=${bookingResult.value.ma_ve}&accountName=${encodeURIComponent(accountName)}`;
});

// Format utils
const formatPrice = (val) => new Intl.NumberFormat('vi-VN').format(val) + 'đ';

// Lọc vé theo tầng
const seatsFloor1 = computed(() => seatMapRaw.value.filter(s => s.tang === 1));
const seatsFloor2 = computed(() => seatMapRaw.value.filter(s => s.tang === 2));

const currentFloor = ref(1);

// --- METHODS ---
const fetchInitialData = async () => {
  if (!tripId) {
    router.push('/search');
    return;
  }
  try {
    isLoading.value = true;
    const res = await clientApi.getTripSeats(tripId);
    if (res.success) {
      tripData.value = res.data.chuyen_xe;
      seatMapRaw.value = res.data.so_do_ghe;
      
      // Load stops
      const stopsRes = await clientApi.getTripStops(tripId);
      if (stopsRes.success) {
        stopsData.value = stopsRes.data;
      }

      // Load vouchers
      const vouchersRes = await clientApi.getPublicVouchers({ ma_nha_xe: tripData.value.tuyen_duong.ma_nha_xe });
      if (vouchersRes.success) {
        vouchers.value = vouchersRes.data;
      }
      
      // Select appropriate payment method if cash is not allowed
      if (tripData.value.thanh_toan_sau === 0) {
        paymentMethod.value = 'chuyen_khoan';
      }
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Lỗi tải dữ liệu';
  } finally {
    isLoading.value = false;
  }
};

const toggleSeat = (seat) => {
  if (seat.trang_thai !== 'trong') return; // Không cho chọn ghế đã đặt/khóa
  
  const index = selectedSeats.value.findIndex(s => s.id_ghe === seat.id_ghe);
  if (index >= 0) {
    selectedSeats.value.splice(index, 1);
  } else {
    if (selectedSeats.value.length >= 6) {
      alert('Chỉ được chọn tối đa 6 ghế 1 lần đặt.');
      return;
    }
    selectedSeats.value.push(seat);
  }
};

const isSeatSelected = (seatId) => selectedSeats.value.some(s => s.id_ghe === seatId);

const validateStep1 = () => {
  if (selectedSeats.value.length === 0) {
    alert('Vui lòng chọn ít nhất 1 ghế.');
    return false;
  }
  return true;
};

const validateStep2 = () => {
  if (!pickupPointId.value || !dropoffPointId.value) {
    alert('Vui lòng chọn đầy đủ điểm đón và điểm trả.');
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
    errorMessage.value = '';
    
    const payload = {
      id_chuyen_xe: tripData.value.id,
      danh_sach_ghe: selectedSeats.value.map(s => s.ma_ghe),
      id_tram_don: pickupPointId.value,
      id_tram_tra: dropoffPointId.value,
      ghi_chu: customerNote.value,
      id_voucher: selectedVoucherId.value || null,
      phuong_thuc_thanh_toan: paymentMethod.value
    };

    const res = await clientApi.bookTicket(payload);
    
    if (res.success) {
      bookingResult.value = res.data;
      currentStep.value = 4; // Hoàn tất

      // Bổ sung lắng nghe Websocket (Pusher/Echo)
      if (window.Echo) {
        window.Echo.channel(`ve.${bookingResult.value.ma_ve}`)
          .listen('.ve.huy_tu_dong', (e) => {
            if (bookingResult.value) {
              errorMessage.value = e.message || 'Giao dịch bị từ chối do hết thời gian thanh toán';
              bookingResult.value.tinh_trang = 'huy'; // Kích hoạt UI che QR lại
            }
          })
          .listen('.ve.da_thanh_toan', (e) => {
            if (bookingResult.value) {
              bookingResult.value.tinh_trang = 'da_thanh_toan';
            }
          });
      }
    } else {
      errorMessage.value = res.message || 'Đặt vé thất bại.';
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Có lỗi xảy ra.';
  } finally {
    isBooking.value = false;
  }
};

onMounted(() => {
  fetchInitialData();
});
</script>

<template>
  <div class="booking-page container mx-auto py-8 px-4 max-w-7xl">
    <!-- Loading State -->
    <div v-if="isLoading" class="flex flex-col items-center justify-center min-h-[50vh]">
      <div class="loader mb-4 border-4 border-t-blue-500 border-r-blue-500 border-b-blue-200 border-l-blue-200 rounded-full w-12 h-12 animate-spin"></div>
      <p class="text-slate-600 font-medium">Đang tải dữ liệu chuyến xe...</p>
    </div>

    <template v-else-if="tripData">
      <!-- Title & Stepper -->
      <div class="mb-8 pl-4">
        <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Hoàn tất đặt vé</h1>
        <p class="text-slate-500">{{ tripData.tuyen_duong.ten_tuyen_duong }} • Khởi hành: {{ tripData.gio_khoi_hanh }}</p>
      </div>

      <div class="flex flex-col lg:flex-row gap-6">
        <!-- Main Content Box -->
        <div class="lg:w-2/3">
          
          <!-- Stepper Indicator -->
          <div class="flex items-center mb-8 glass-card p-4 rounded-2xl relative z-10">
            <template v-for="step in 3" :key="step">
              <div class="flex items-center relative z-20">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-colors duration-300"
                  :class="currentStep >= step ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30' : 'bg-slate-100 text-slate-400'">
                  {{ step }}
                </div>
                <span class="ml-3 font-medium hidden sm:block"
                  :class="currentStep >= step ? 'text-blue-600' : 'text-slate-400'">
                  {{ step === 1 ? 'Chọn ghế' : step === 2 ? 'Điểm bắt xe' : 'Thanh toán' }}
                </span>
              </div>
              <div v-if="step < 3" class="flex-1 h-1 mx-4 rounded-full transition-colors duration-300"
                :class="currentStep > step ? 'bg-blue-600' : 'bg-slate-100'"></div>
            </template>
          </div>

          <!-- Error Alert -->
          <div v-if="errorMessage" class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl flex items-center gap-2">
            <span class="material-symbols-outlined">error</span>
            {{ errorMessage }}
          </div>

          <!-- STEP 1: CHỌN GHẾ -->
          <div v-if="currentStep === 1" class="glass-card rounded-2xl p-6 fade-in">
            <h2 class="text-xl font-bold mb-6 flex items-center gap-2 text-slate-800">
              <span class="material-symbols-outlined text-blue-500">airline_seat_recline_normal</span> 
              Sơ đồ ghế
            </h2>
            
            <div class="flex justify-center gap-4 mb-8">
              <button class="px-6 py-2 rounded-full font-medium transition-all"
                :class="currentFloor === 1 ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="currentFloor = 1">Tầng 1</button>
              <button class="px-6 py-2 rounded-full font-medium transition-all"
                :class="currentFloor === 2 ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="currentFloor = 2">Tầng 2</button>
            </div>

            <!-- Chú thích -->
            <div class="flex justify-center gap-6 mb-8 text-sm text-slate-600 flex-wrap">
              <div class="flex items-center gap-2"><div class="w-5 h-5 bg-white border-2 border-slate-200 rounded"></div> Ghế trống</div>
              <div class="flex items-center gap-2"><div class="w-5 h-5 bg-slate-200 border-2 border-slate-300 rounded opacity-60"></div> Đã đặt</div>
              <div class="flex items-center gap-2"><div class="w-5 h-5 bg-blue-100 border-2 border-blue-500 rounded"></div> Đang chọn</div>
            </div>

            <div class="seat-map-container bg-slate-50 p-6 rounded-2xl border border-slate-100 w-fit mx-auto">
              <!-- Đầu xe -->
              <div class="w-full text-center text-xs font-bold text-slate-400 mb-6 uppercase tracking-widest border-b-2 border-dashed border-slate-200 pb-2">Đầu xe</div>
              
              <div class="grid grid-cols-5 gap-x-2 sm:gap-x-4 gap-y-4">
                <template v-for="(seat, index) in (currentFloor === 1 ? seatsFloor1 : seatsFloor2)" :key="seat.id_ghe">
                  <!-- Tạo khoảng trống cho lối đi (cột 3) -->
                  <div v-if="index % 4 === 2" class="w-4 sm:w-8"></div>
                  
                  <button class="seat-btn relative w-10 sm:w-12 h-14 sm:h-16 rounded-lg border-2 transition-all flex flex-col items-center justify-center group"
                    :disabled="seat.trang_thai !== 'trong'"
                    :class="[
                      seat.trang_thai !== 'trong' ? 'bg-slate-200 border-slate-300 cursor-not-allowed opacity-60' :
                      isSeatSelected(seat.id_ghe) ? 'bg-blue-100 border-blue-500 shadow-inner' :
                      'bg-white border-slate-200 hover:border-blue-400 hover:shadow'
                    ]"
                    @click="toggleSeat(seat)"
                  >
                    <span class="text-xs sm:text-sm font-bold"
                      :class="isSeatSelected(seat.id_ghe) ? 'text-blue-700' : 'text-slate-600'">
                      {{ seat.ma_ghe }}
                    </span>
                    <span v-if="isSeatSelected(seat.id_ghe)" class="material-symbols-outlined text-blue-500 text-[16px] mt-1 absolute bottom-1">check_circle</span>
                  </button>
                </template>
              </div>
            </div>

            <div class="flex justify-end mt-8">
              <button @click="nextStep" class="btn-primary w-full sm:w-auto">
                Tiếp tục <span class="material-symbols-outlined ml-1">arrow_forward</span>
              </button>
            </div>
          </div>

          <!-- STEP 2: ĐIỂM ĐÓN / TRẢ -->
          <div v-if="currentStep === 2" class="glass-card rounded-2xl p-6 fade-in">
            <h2 class="text-xl font-bold mb-6 flex items-center gap-2 text-slate-800">
              <span class="material-symbols-outlined text-blue-500">location_on</span> 
              Thông tin đón trả
            </h2>

            <div class="grid md:grid-cols-2 gap-8">
              <!-- Điểm đón -->
              <div>
                <h3 class="font-semibold text-slate-700 mb-4 pb-2 border-b">Điểm Đón</h3>
                <div v-if="stopsData.tram_don.length === 0" class="text-slate-500 italic">Không có dữ liệu trạm đón.</div>
                <div class="space-y-3 max-h-[300px] overflow-y-auto pr-2 custom-scroll">
                  <label v-for="stop in stopsData.tram_don" :key="'don-'+stop.id" class="radio-card">
                    <input type="radio" :value="stop.id" v-model="pickupPointId" name="pickup">
                    <div class="card-content">
                      <span class="font-semibold block text-slate-800 text-sm mb-1">{{ stop.ten_tram }}</span>
                      <span class="text-xs text-slate-500 flex gap-1"><span class="material-symbols-outlined text-[14px]">map</span> {{ stop.dia_chi }}</span>
                    </div>
                  </label>
                </div>
              </div>

              <!-- Điểm trả -->
              <div>
                <h3 class="font-semibold text-slate-700 mb-4 pb-2 border-b">Điểm Trả</h3>
                <div v-if="stopsData.tram_tra.length === 0" class="text-slate-500 italic">Không có dữ liệu trạm trả.</div>
                <div class="space-y-3 max-h-[300px] overflow-y-auto pr-2 custom-scroll">
                  <label v-for="stop in stopsData.tram_tra" :key="'tra-'+stop.id" class="radio-card">
                    <input type="radio" :value="stop.id" v-model="dropoffPointId" name="dropoff">
                    <div class="card-content">
                      <span class="font-semibold block text-slate-800 text-sm mb-1">{{ stop.ten_tram }}</span>
                      <span class="text-xs text-slate-500 flex gap-1"><span class="material-symbols-outlined text-[14px]">map</span> {{ stop.dia_chi }}</span>
                    </div>
                  </label>
                </div>
              </div>
            </div>

            <div class="mt-8">
              <label class="block font-semibold text-slate-700 mb-2 text-sm">Ghi chú yêu cầu (Tùy chọn)</label>
              <textarea v-model="customerNote" rows="3" class="w-full border-2 border-slate-200 rounded-xl p-3 focus:outline-none focus:border-blue-500 bg-slate-50 transition-colors" placeholder="Yêu cầu điểm đón khác ngoài danh sách (nhà xe sẽ liên hệ xác nhận)..."></textarea>
            </div>

            <div class="flex justify-between mt-8">
              <button @click="prevStep" class="btn-outline">
                <span class="material-symbols-outlined mr-1">arrow_back</span> Quay lại
              </button>
              <button @click="nextStep" class="btn-primary">
                Tiếp tục <span class="material-symbols-outlined ml-1">arrow_forward</span>
              </button>
            </div>
          </div>

          <!-- STEP 3: THANH TOÁN -->
          <div v-if="currentStep === 3" class="glass-card rounded-2xl p-6 fade-in">
            <h2 class="text-xl font-bold mb-6 flex items-center gap-2 text-slate-800">
              <span class="material-symbols-outlined text-blue-500">payments</span> 
              Xác nhận thanh toán
            </h2>

            <!-- Khuyến mãi -->
            <div class="mb-8 p-5 bg-blue-50/50 rounded-2xl border border-blue-100">
              <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-500">local_offer</span> 
                Mã giảm giá
              </h3>
              <div v-if="vouchers.length === 0" class="text-slate-500 text-sm">Không có mã giảm giá nào phù hợp.</div>
              <div v-else class="grid sm:grid-cols-2 gap-3 max-h-[200px] overflow-y-auto pr-2 custom-scroll">
                <label v-for="vc in vouchers" :key="vc.id" class="voucher-card">
                  <input type="radio" :value="vc.id" v-model="selectedVoucherId">
                  <div class="card-content flex items-center p-3 border rounded-xl cursor-pointer transition-all hover:bg-white bg-slate-50">
                    <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold mr-3 shrink-0">
                      %
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="font-bold text-slate-800 text-sm mb-0.5 truncate">{{ vc.ma_voucher }}</div>
                      <div class="text-xs text-slate-500">{{ vc.loai_voucher === 'percent' ? `Giảm ${vc.gia_tri}%` : `Giảm ${formatPrice(vc.gia_tri)}` }}</div>
                    </div>
                    <div class="radio-indicator ml-2"></div>
                  </div>
                </label>
              </div>
              <div v-if="selectedVoucherId" class="mt-3 text-sm text-blue-600 font-medium cursor-pointer flex justify-end" @click="selectedVoucherId = null">
                Bỏ chọn mã
              </div>
            </div>

            <!-- Phương thức TT -->
            <div class="mb-8">
              <h3 class="font-semibold text-slate-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-green-500">account_balance_wallet</span> 
                Phương thức thanh toán
              </h3>
              <div class="space-y-3">
                <label v-if="tripData.thanh_toan_sau !== 0" class="radio-card block">
                  <input type="radio" value="tien_mat" v-model="paymentMethod">
                  <div class="card-content flex items-center">
                    <span class="material-symbols-outlined text-emerald-500 mr-3 text-2xl">payments</span>
                    <div class="flex-1">
                      <div class="font-semibold text-slate-800">Tiền mặt tại nhà xe / Khi lên xe</div>
                      <div class="text-xs text-slate-500">Thanh toán trực tiếp cho lơ xe hoặc tại quầy vé.</div>
                    </div>
                  </div>
                </label>
                <div v-else class="p-3 bg-amber-50 text-amber-700 rounded-xl text-sm flex items-center gap-2 mb-3 border border-amber-200">
                  <span class="material-symbols-outlined shrink-0 text-amber-500">info</span>
                  Chuyến xe này không hỗ trợ thanh toán bằng tiền mặt. Vui lòng chuyển khoản trước.
                </div>
                <!-- Online payment options will be added here later -->
                <label class="radio-card block">
                  <input type="radio" value="chuyen_khoan" v-model="paymentMethod">
                  <div class="card-content flex items-center">
                    <span class="material-symbols-outlined text-blue-500 mr-3 text-2xl">qr_code_scanner</span>
                    <div class="flex-1">
                      <div class="font-semibold text-slate-800">Chuyển khoản (VietQR / SePay)</div>
                      <div class="text-xs text-slate-500">Mở ứng dụng ngân hàng và quét mã để thanh toán tự động tiện lợi.</div>
                    </div>
                  </div>
                </label>
              </div>
            </div>

            <div class="flex justify-between mt-8 border-t pt-6">
              <button @click="prevStep" class="btn-outline">
                <span class="material-symbols-outlined mr-1">arrow_back</span> Quay lại
              </button>
              <button @click="submitBooking" :disabled="isBooking" class="btn-primary" :class="{'opacity-75 cursor-wait': isBooking}">
                <span v-if="isBooking" class="material-symbols-outlined animate-spin mr-2">autorenew</span>
                Xác nhận đặt vé
              </button>
            </div>
          </div>

          <!-- STEP 4: HOÀN TẤT -->
          <div v-if="currentStep === 4 && bookingResult" class="glass-card rounded-2xl p-8 text-center fade-in max-w-2xl mx-auto relative">
            <div v-if="bookingResult.tinh_trang === 'huy'" class="absolute inset-0 bg-white/90 backdrop-blur-sm z-50 rounded-2xl flex flex-col items-center justify-center p-8 text-center">
                <span class="material-symbols-outlined text-red-500 text-6xl mb-4">cancel</span>
                <h3 class="text-2xl font-bold text-slate-800 mb-2">Giao dịch bị từ chối</h3>
                <p class="text-red-500 font-medium mb-6">Vé của bạn đã bị huỷ do hết thời gian thanh toán.</p>
                <button @click="currentStep = 1" class="btn-primary">
                  <span class="material-symbols-outlined mr-2">refresh</span> Đặt lại vé
                </button>
            </div>

            <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6"
                 :class="bookingResult.tinh_trang === 'da_thanh_toan' ? 'bg-emerald-100' : 'bg-blue-100'">
              <span v-if="bookingResult.tinh_trang === 'da_thanh_toan'" class="material-symbols-outlined text-emerald-500 text-5xl">check_circle</span>
              <span v-else class="material-symbols-outlined text-blue-500 text-4xl animate-spin">hourglass_empty</span>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-2">
              <template v-if="bookingResult.tinh_trang === 'da_thanh_toan'">Đặt vé thành công!</template>
              <template v-else>Đang chờ thanh toán...</template>
            </h2>
            <p class="text-slate-500 mb-6">Cảm ơn bạn đã sử dụng dịch vụ. Mã đặt vé của bạn là:</p>
            
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-6 mx-auto max-w-sm mb-6 relative">
              <div class="text-3xl font-black text-blue-600 tracking-widest">{{ bookingResult.ma_ve }}</div>
              <div class="text-sm mt-2 text-slate-500">Vui lòng cung cấp mã này khi lên xe.</div>
            </div>

            <!-- VietQR Display -->
            <div v-if="paymentMethod === 'chuyen_khoan' && bookingResult.tinh_trang === 'dang_cho'" class="mb-8 pt-6 border-t border-dashed border-slate-200">
              <h3 class="font-bold text-slate-800 mb-3 text-lg">Quét mã QR để thanh toán tự động</h3>
              <p class="text-sm text-slate-500 mb-4 px-4">Vui lòng sử dụng Ứng dụng Ngân hàng để quét mã QR bên dưới.<br/>Hệ thống sẽ <b>Tự động xác nhận</b> trong vòng vài giây sau khi giao dịch thành công.</p>
              
              <div class="inline-block p-4 mx-auto border-2 border-slate-200 rounded-2xl bg-white focus-within:ring hover:shadow-xl transition-all">
                <img :src="vietQrUrl" alt="Mã VietQR Thanh Toán" class="w-64 h-auto rounded-xl mx-auto mb-2 mix-blend-multiply" />
                <div class="text-sm text-left px-2">
                  <div class="flex justify-between items-center py-2 border-b">
                    <span class="text-slate-500">Ngân hàng hưởng:</span>
                    <span class="font-bold text-slate-800">{{ bankId }}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b">
                    <span class="text-slate-500">Chủ tài khoản:</span>
                    <span class="font-bold text-slate-800 uppercase">{{ accountName }}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b">
                    <span class="text-slate-500">Số tài khoản:</span>
                    <span class="font-bold text-slate-800 text-lg">{{ bankAccount }}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b">
                    <span class="text-slate-500">Số tiền:</span>
                    <span class="font-bold text-blue-600 text-lg">{{ formatPrice(finalPrice) }}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 bg-yellow-50 px-2 mt-2 -mx-2 rounded">
                    <span class="text-slate-600 font-medium">Nội dung CK:</span>
                    <span class="font-black text-slate-900 tracking-widest">{{ bookingResult.ma_ve }}</span>
                  </div>
                </div>
              </div>
              <div class="text-sm text-emerald-600 font-semibold mt-4 flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-lg animate-spin">autorenew</span> Đang chờ thanh toán...
              </div>
            </div>

            <button @click="$router.push('/')" class="btn-primary mx-auto mt-4">
              Quay về Trang Chủ
            </button>
          </div>

        </div>

        <!-- Sidebar Summary -->
        <div class="lg:w-1/3">
          <div class="glass-card rounded-2xl p-5 sticky top-24">
            <h3 class="font-bold text-lg mb-4 text-slate-800 pb-3 border-b">Thông tin đặt vé</h3>
            
            <div class="space-y-4 mb-6">
              <div>
                <span class="block text-xs text-slate-400 font-medium uppercase tracking-wider mb-1">Chuyến xe</span>
                <span class="block text-sm font-semibold text-slate-700">{{ tripData.tuyen_duong.ten_tuyen_duong }}</span>
              </div>
              
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <span class="block text-xs text-slate-400 font-medium uppercase tracking-wider mb-1">Ngày đi</span>
                  <span class="block text-sm font-semibold text-slate-700">{{ tripData.ngay_khoi_hanh }}</span>
                </div>
                <div>
                  <span class="block text-xs text-slate-400 font-medium uppercase tracking-wider mb-1">Giờ đi</span>
                  <span class="block text-sm font-semibold text-emerald-600">{{ tripData.gio_khoi_hanh }}</span>
                </div>
              </div>

              <div>
                <span class="block text-xs text-slate-400 font-medium uppercase tracking-wider mb-1">Ghế đã chọn ({{ selectedSeats.length }})</span>
                <div class="flex flex-wrap gap-2 mt-1">
                  <span v-for="s in selectedSeats" :key="s.id_ghe" class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-1 rounded inline-block">
                    {{ s.ma_ghe }}
                  </span>
                  <span v-if="selectedSeats.length === 0" class="text-sm text-slate-400 italic">Chưa chọn ghế</span>
                </div>
              </div>
            </div>

            <div class="border-t border-dashed border-slate-200 pt-4 space-y-3">
              <div class="flex justify-between text-sm">
                <span class="text-slate-500">Giá vé (x{{ selectedSeats.length }})</span>
                <span class="font-medium text-slate-700">{{ formatPrice(baseTotalPrice) }}</span>
              </div>
              
              <div v-if="discountAmount > 0" class="flex justify-between text-sm text-green-600">
                <span>Khuyến mãi</span>
                <span>-{{ formatPrice(discountAmount) }}</span>
              </div>
              
              <div class="flex justify-between items-center mt-2 pt-3 border-t">
                <span class="font-bold text-slate-800">Tổng thanh toán</span>
                <span class="text-xl font-black text-blue-600">{{ formatPrice(finalPrice) }}</span>
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

.radio-card .card-content {
  @apply block p-4 border-2 border-slate-200 rounded-xl cursor-pointer transition-all bg-white relative overflow-hidden;
}

.radio-card input[type="radio"]:checked + .card-content {
  @apply border-blue-500 bg-blue-50 shadow-inner;
}

.radio-card input[type="radio"]:checked + .card-content::after {
  content: "check_circle";
  font-family: "Material Symbols Outlined";
  @apply absolute right-4 top-1/2 -translate-y-1/2 text-blue-500 text-xl;
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
.voucher-card input[type="radio"]:checked + .card-content .radio-indicator::after {
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
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
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
</style>
