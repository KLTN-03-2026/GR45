<template>
  <div class="wallet-container p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <Wallet class="w-6 h-6 text-green-600" />
          Ví Điện Tử Nhà Xe
        </h1>
        <p class="text-sm text-gray-500 mt-1">Quản lý số dư, nạp/rút tiền và lịch sử giao dịch</p>
      </div>
      <div class="flex gap-3">
        <button @click="showBankInfoModal = true" class="btn-outline">
          <Building class="w-4 h-4 mr-2" /> Ngân hàng
        </button>
        <button @click="showTopupModal = true" class="btn-primary bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
          <ArrowDownLeft class="w-4 h-4 mr-2" /> Nạp tiền
        </button>
        <button @click="showWithdrawModal = true" class="btn-primary bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center transition-colors">
          <ArrowUpRight class="w-4 h-4 mr-2" /> Rút tiền
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="stat-card bg-gradient-to-br from-green-500 to-green-700 text-white p-6 rounded-2xl shadow-lg relative overflow-hidden">
        <div class="relative z-10">
          <p class="text-green-100 text-sm font-medium mb-1">Số dư hiện tại</p>
          <h3 class="text-3xl font-bold">{{ formatCurrency(wallet?.so_du || 0) }}</h3>
          <p class="text-xs text-green-200 mt-2 flex items-center">
            <CheckCircle2 class="w-3 h-3 mr-1" />
            Trạng thái: {{ formatStatus(wallet?.trang_thai) }}
          </p>
        </div>
        <Wallet class="absolute -right-4 -bottom-4 w-32 h-32 text-white opacity-10" />
      </div>

      <div class="stat-card bg-white p-6 rounded-2xl shadow border border-gray-100">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-gray-500 text-sm font-medium mb-1">Tổng tiền đã nạp</p>
            <h3 class="text-2xl font-bold text-gray-900">{{ formatCurrency(wallet?.tong_nap || 0) }}</h3>
          </div>
          <div class="p-3 bg-blue-50 rounded-xl text-blue-600">
            <ArrowDownLeft class="w-6 h-6" />
          </div>
        </div>
      </div>

      <div class="stat-card bg-white p-6 rounded-2xl shadow border border-gray-100">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-gray-500 text-sm font-medium mb-1">Tổng tiền đã rút</p>
            <h3 class="text-2xl font-bold text-gray-900">{{ formatCurrency(wallet?.tong_rut || 0) }}</h3>
          </div>
          <div class="p-3 bg-orange-50 rounded-xl text-orange-600">
            <ArrowUpRight class="w-6 h-6" />
          </div>
        </div>
      </div>
    </div>

    <!-- Transactions List -->
    <div class="bg-white rounded-2xl shadow border border-gray-100 overflow-hidden">
      <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
        <h3 class="text-lg font-bold text-gray-800 flex items-center">
          <History class="w-5 h-5 mr-2 text-gray-500" /> Lịch sử giao dịch
        </h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
              <th class="p-4 font-medium">Mã GD</th>
              <th class="p-4 font-medium">Thời gian</th>
              <th class="p-4 font-medium">Loại GD</th>
              <th class="p-4 font-medium text-right">Số tiền</th>
              <th class="p-4 font-medium text-right">Số dư sau GD</th>
              <th class="p-4 font-medium">Trạng thái</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loading" class="text-center">
              <td colspan="6" class="p-8 text-gray-500">Đang tải dữ liệu...</td>
            </tr>
            <tr v-else-if="!transactions.length" class="text-center">
              <td colspan="6" class="p-8 text-gray-500">Chưa có giao dịch nào.</td>
            </tr>
            <tr v-for="tx in transactions" :key="tx.id" class="hover:bg-gray-50/50 transition-colors">
              <td class="p-4 font-mono text-sm text-gray-600">{{ tx.transaction_code }}</td>
              <td class="p-4 text-sm text-gray-600">
                <div class="flex items-center">
                  <Clock class="w-3 h-3 mr-1 text-gray-400" />
                  {{ formatDate(tx.created_at) }}
                </div>
              </td>
              <td class="p-4 text-sm">
                <span :class="getTypeBadgeClass(tx.loai_giao_dich)" class="px-2.5 py-1 rounded-full text-xs font-medium">
                  {{ formatType(tx.loai_giao_dich) }}
                </span>
                <p class="text-xs text-gray-500 mt-1 max-w-[200px] truncate" :title="tx.noi_dung">{{ tx.noi_dung }}</p>
              </td>
              <td class="p-4 text-right font-medium" :class="tx.loai_giao_dich === 'phi_hoa_hong' || tx.loai_giao_dich === 'rut_tien' ? 'text-red-600' : 'text-green-600'">
                {{ tx.loai_giao_dich === 'phi_hoa_hong' || tx.loai_giao_dich === 'rut_tien' ? '-' : '+' }}{{ formatCurrency(tx.so_tien) }}
              </td>
              <td class="p-4 text-right text-sm text-gray-700">
                {{ formatCurrency(tx.so_du_sau_giao_dich) }}
              </td>
              <td class="p-4">
                <span :class="getStatusBadgeClass(tx.tinh_trang)" class="px-2.5 py-1 rounded-full text-xs font-medium flex items-center w-fit">
                  {{ formatTxStatus(tx.tinh_trang) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modals -->
    <!-- Bank Info Modal -->
    <div v-if="showBankInfoModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl">
        <h3 class="text-xl font-bold mb-4 flex items-center">
          <Building class="w-5 h-5 mr-2 text-blue-600" /> Cập nhật Ngân hàng
        </h3>
        <p class="text-sm text-gray-500 mb-4">Thông tin này sẽ được dùng để chuyển tiền khi bạn yêu cầu rút tiền.</p>
        
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tên ngân hàng / Mã BIN</label>
            <input v-model="bankForm.ngan_hang" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500" placeholder="VD: MB Bank, Vietcombank..." />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Số tài khoản</label>
            <input v-model="bankForm.so_tai_khoan" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tên chủ tài khoản</label>
            <input v-model="bankForm.ten_tai_khoan" type="text" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 uppercase" />
          </div>
        </div>
        
        <div class="mt-6 flex justify-end gap-3">
          <button @click="showBankInfoModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Hủy</button>
          <button @click="updateBankInfo" :disabled="submitting" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
            {{ submitting ? 'Đang xử lý...' : 'Lưu thông tin' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Topup Modal -->
    <div v-if="showTopupModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl text-center">
        <h3 class="text-xl font-bold mb-4 flex items-center justify-center">
          <ArrowDownLeft class="w-5 h-5 mr-2 text-blue-600" /> Nạp tiền vào ví
        </h3>
        
        <div v-if="!qrData" class="space-y-4 text-left">
          <p class="text-sm text-gray-500">Nhập số tiền cần nạp. Bạn sẽ nhận được mã QR để chuyển khoản.</p>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Số tiền nạp (VND)</label>
            <input v-model="topupAmount" type="number" min="10000" step="10000" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-lg font-medium" />
          </div>
          
          <div class="mt-6 flex justify-end gap-3">
            <button @click="showTopupModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Hủy</button>
            <button @click="requestTopup" :disabled="submitting || topupAmount < 10000" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
              {{ submitting ? 'Đang xử lý...' : 'Tạo mã QR' }}
            </button>
          </div>
        </div>
        
        <div v-else class="space-y-4">
          <div class="p-4 bg-gray-50 rounded-xl border border-gray-100 inline-block">
            <img :src="`https://img.vietqr.io/image/${qrData.bank_code}-${qrData.account_no}-compact2.jpg?amount=${qrData.amount}&addInfo=${qrData.content}&accountName=${qrData.account_name}`" alt="VietQR" class="w-64 h-64 object-contain mx-auto" />
          </div>
          <div class="text-left bg-blue-50 p-4 rounded-lg text-sm text-blue-800">
            <p><strong>Ngân hàng:</strong> {{ qrData.bank_code }}</p>
            <p><strong>STK:</strong> {{ qrData.account_no }} - {{ qrData.account_name }}</p>
            <p><strong>Số tiền:</strong> <span class="font-bold text-lg text-red-600">{{ formatCurrency(qrData.amount) }}</span></p>
            <p><strong>Nội dung:</strong> <span class="font-mono bg-white px-2 py-1 rounded">{{ qrData.content }}</span></p>
          </div>
          <p class="text-xs text-gray-500 mt-2">Giao dịch sẽ được duyệt tự động sau vài phút khi tiền vào tài khoản.</p>
          
          <div class="mt-6">
            <button @click="closeTopupModal" class="w-full px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors">
              Đóng
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Withdraw Modal -->
    <div v-if="showWithdrawModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl">
        <h3 class="text-xl font-bold mb-4 flex items-center">
          <ArrowUpRight class="w-5 h-5 mr-2 text-green-600" /> Yêu cầu rút tiền
        </h3>
        
        <div v-if="!wallet?.so_tai_khoan" class="text-center py-6 space-y-4">
          <div class="w-16 h-16 bg-orange-100 text-orange-500 rounded-full flex items-center justify-center mx-auto mb-2">
            <AlertTriangle class="w-8 h-8" />
          </div>
          <p class="text-gray-600">Bạn chưa thiết lập thông tin ngân hàng.</p>
          <button @click="showWithdrawModal = false; showBankInfoModal = true" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            Thiết lập ngay
          </button>
        </div>
        
        <div v-else class="space-y-4">
          <div class="bg-gray-50 p-4 rounded-lg text-sm mb-4">
            <p class="text-gray-500 mb-1">Rút về tài khoản:</p>
            <p class="font-medium text-gray-900">{{ wallet.ngan_hang }} - {{ wallet.so_tai_khoan }}</p>
            <p class="font-medium text-gray-900">{{ wallet.ten_tai_khoan }}</p>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1 flex justify-between">
              <span>Số tiền rút (VND)</span>
              <span class="text-green-600 cursor-pointer text-xs" @click="withdrawAmount = wallet.so_du">Rút toàn bộ</span>
            </label>
            <input v-model="withdrawAmount" type="number" min="10000" :max="wallet?.so_du || 0" step="10000" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 text-lg font-medium" />
            <p class="text-xs text-gray-500 mt-1">Số dư khả dụng: <span class="font-bold">{{ formatCurrency(wallet?.so_du || 0) }}</span></p>
          </div>
          
          <div class="mt-6 flex justify-end gap-3">
            <button @click="showWithdrawModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Hủy</button>
            <button @click="requestWithdraw" :disabled="submitting || withdrawAmount < 10000 || withdrawAmount > wallet?.so_du" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50">
              {{ submitting ? 'Đang xử lý...' : 'Xác nhận rút' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import operatorApi from '@/api/operatorApi';
import Swal from 'sweetalert2';
import { Wallet, Building, ArrowDownLeft, ArrowUpRight, CheckCircle2, History, Clock, XCircle, AlertTriangle } from 'lucide-vue-next';

// State
const wallet = ref(null);
const transactions = ref([]);
const loading = ref(true);
const submitting = ref(false);

// Modals state
const showBankInfoModal = ref(false);
const showTopupModal = ref(false);
const showWithdrawModal = ref(false);

// Form data
const bankForm = ref({ ngan_hang: '', so_tai_khoan: '', ten_tai_khoan: '' });
const topupAmount = ref(50000);
const qrData = ref(null);
const withdrawAmount = ref(0);

// Fetch data
const fetchWalletInfo = async () => {
  loading.value = true;
  try {
    const res = await operatorApi.getWalletInfo();
    const payload = res.data ?? res; // axios interceptor đã unwrap .data
    wallet.value = payload.wallet;
    transactions.value = payload.transactions?.data || payload.transactions || [];
    
    // Init bank form
    if (wallet.value) {
      bankForm.value = {
        ngan_hang: wallet.value.ngan_hang || '',
        so_tai_khoan: wallet.value.so_tai_khoan || '',
        ten_tai_khoan: wallet.value.ten_tai_khoan || '',
      };
    }
  } catch (err) {
    console.error('Lỗi lấy thông tin ví:', err);
    Swal.fire('Lỗi', 'Không thể lấy thông tin ví', 'error');
  } finally {
    loading.value = false;
  }
};

// Actions
const updateBankInfo = async () => {
  if (!bankForm.value.ngan_hang || !bankForm.value.so_tai_khoan || !bankForm.value.ten_tai_khoan) {
    return Swal.fire('Cảnh báo', 'Vui lòng nhập đầy đủ thông tin', 'warning');
  }
  
  submitting.value = true;
  try {
    const res = await operatorApi.updateBankInfo(bankForm.value);
    wallet.value = (res.data ?? res).wallet;
    showBankInfoModal.value = false;
    Swal.fire('Thành công', 'Cập nhật thông tin ngân hàng thành công', 'success');
  } catch (err) {
    Swal.fire('Lỗi', err.response?.data?.message || 'Có lỗi xảy ra', 'error');
  } finally {
    submitting.value = false;
  }
};

const requestTopup = async () => {
  submitting.value = true;
  try {
    const res = await operatorApi.requestTopup({ amount: topupAmount.value });
    qrData.value = (res.data ?? res).qr_data;
    // Cập nhật lại lịch sử
    fetchWalletInfo();
  } catch (err) {
    Swal.fire('Lỗi', err.response?.data?.message || 'Có lỗi xảy ra', 'error');
  } finally {
    submitting.value = false;
  }
};

const closeTopupModal = () => {
  showTopupModal.value = false;
  qrData.value = null;
  topupAmount.value = 50000;
};

const requestWithdraw = async () => {
  submitting.value = true;
  try {
    const res = await operatorApi.requestWithdraw({ amount: withdrawAmount.value });
    showWithdrawModal.value = false;
    withdrawAmount.value = 0;
    Swal.fire('Thành công', 'Đã tạo yêu cầu rút tiền thành công. Vui lòng chờ duyệt.', 'success');
    fetchWalletInfo();
  } catch (err) {
    Swal.fire('Lỗi', err.response?.data?.message || 'Có lỗi xảy ra', 'error');
  } finally {
    submitting.value = false;
  }
};

// Helpers
const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const formatDate = (val) => {
  if (!val) return '';
  const date = new Date(val);
  return date.toLocaleString('vi-VN');
};

const formatStatus = (val) => {
  const map = {
    'hoat_dong': 'Đang hoạt động',
    'tam_khoa': 'Tạm khóa',
    'khoa_vinh_vien': 'Khóa vĩnh viễn'
  };
  return map[val] || val;
};

const formatType = (val) => {
  const map = {
    'nap_tien': 'Nạp tiền',
    'nhan_doanh_thu': 'Nhận doanh thu',
    'rut_tien': 'Rút tiền',
    'phi_hoa_hong': 'Phí hoa hồng',
    'phi_dich_vu_thang': 'Phí dịch vụ',
    'hoan_tien': 'Hoàn tiền',
    'dieu_chinh': 'Điều chỉnh'
  };
  return map[val] || val;
};

const getTypeBadgeClass = (type) => {
  if (type === 'nap_tien' || type === 'nhan_doanh_thu' || type === 'hoan_tien') return 'bg-green-100 text-green-800';
  if (type === 'rut_tien') return 'bg-blue-100 text-blue-800';
  if (type === 'phi_hoa_hong' || type === 'phi_dich_vu_thang') return 'bg-red-100 text-red-800';
  return 'bg-gray-100 text-gray-800';
};

const formatTxStatus = (val) => {
  const map = {
    'cho_xac_nhan': 'Chờ xử lý',
    'dang_thanh_toan': 'Đang thanh toán',
    'thanh_toan_thanh_cong': 'Thành công',
    'that_bai': 'Thất bại',
    'huy': 'Đã hủy'
  };
  return map[val] || val;
};

const getStatusBadgeClass = (status) => {
  if (status === 'thanh_toan_thanh_cong') return 'bg-green-50 text-green-600 border border-green-200';
  if (status === 'cho_xac_nhan') return 'bg-orange-50 text-orange-600 border border-orange-200';
  if (status === 'that_bai' || status === 'huy') return 'bg-red-50 text-red-600 border border-red-200';
  return 'bg-blue-50 text-blue-600 border border-blue-200';
};

onMounted(() => {
  fetchWalletInfo();
});
</script>

<style scoped>
.btn-outline {
  @apply px-4 py-2 border border-gray-300 text-gray-700 rounded-lg flex items-center bg-white hover:bg-gray-50 transition-colors font-medium text-sm;
}
.btn-primary {
  @apply font-medium text-sm shadow-sm;
}
</style>
