<script setup>
import { ref, computed, onMounted } from 'vue'
import { Wallet, Search, CheckCircle, XCircle, Eye, ArrowDownLeft, ArrowUpRight, Building2, Clock, RefreshCw } from 'lucide-vue-next'
import adminApi from '@/api/adminApi'

const tab = ref('wallets') // wallets | withdrawals
const loading = ref(false)
const search = ref('')
const filterStatus = ref('cho_xac_nhan')

// Wallets
const wallets = ref([])
const walletPagination = ref({ current_page: 1, last_page: 1 })

// Withdrawals
const withdrawals = ref([])
const wdPagination = ref({ current_page: 1, last_page: 1 })

// Detail modal
const showDetail = ref(false)
const detailWallet = ref(null)
const detailTx = ref([])

// Reject modal
const showRejectModal = ref(false)
const rejectId = ref(null)
const rejectReason = ref('')
const processing = ref(false)

const fmt = (n) => Number(n || 0).toLocaleString('vi-VN') + ' ₫'
const fmtDate = (d) => d ? new Date(d).toLocaleString('vi-VN') : '—'
const statusCls = (s) => ({ hoat_dong: 'st-green', bi_khoa: 'st-red', cho_xac_nhan: 'st-yellow', thanh_toan_thanh_cong: 'st-green', that_bai: 'st-red' }[s] || 'st-gray')
const statusTxt = (s) => ({ hoat_dong: 'Hoạt động', bi_khoa: 'Bị khóa', cho_xac_nhan: 'Chờ duyệt', thanh_toan_thanh_cong: 'Đã duyệt', that_bai: 'Từ chối' }[s] || s)
const typeTxt = (t) => ({ nap_tien:'Nạp tiền', rut_tien:'Rút tiền', phi_hoa_hong:'Hoa hồng', nhan_doanh_thu:'Doanh thu' }[t] || t)
const typeCls = (t) => ({ nap_tien:'tp-blue', rut_tien:'tp-orange', phi_hoa_hong:'tp-red', nhan_doanh_thu:'tp-green' }[t] || 'tp-gray')

// Fetch wallets
const fetchWallets = async (page = 1) => {
  loading.value = true
  try {
    const res = await adminApi.getOperatorWallets({ search: search.value || undefined, per_page: 15, page })
    const d = res.data ?? res
    wallets.value = d.data?.data ?? d.data ?? []
    walletPagination.value = { current_page: d.data?.current_page ?? 1, last_page: d.data?.last_page ?? 1 }
  } catch (e) { console.warn(e) }
  loading.value = false
}

// Fetch withdrawals
const fetchWithdrawals = async (page = 1) => {
  loading.value = true
  try {
    const res = await adminApi.getWithdrawRequests({ tinh_trang: filterStatus.value, search: search.value || undefined, per_page: 15, page })
    const d = res.data ?? res
    withdrawals.value = d.data?.data ?? d.data ?? []
    wdPagination.value = { current_page: d.data?.current_page ?? 1, last_page: d.data?.last_page ?? 1 }
  } catch (e) { console.warn(e) }
  loading.value = false
}

// View wallet detail
const viewDetail = async (id) => {
  try {
    const res = await adminApi.getOperatorWalletDetail(id)
    // res sau interceptor = { success, data: { wallet, transactions } }
    const payload = res.data ?? res
    detailWallet.value = payload.wallet ?? null
    detailTx.value = payload.transactions?.data ?? payload.transactions ?? []
    showDetail.value = true
  } catch (e) { console.warn(e) }
}

// Approve
const handleApprove = async (id) => {
  if (!confirm('Xác nhận duyệt yêu cầu rút tiền này?')) return
  processing.value = true
  try {
    await adminApi.approveWithdraw(id)
    fetchWithdrawals(wdPagination.value.current_page)
  } catch (e) { alert(e?.response?.data?.message || e?.message || 'Lỗi') }
  processing.value = false
}

// Reject
const openReject = (id) => { rejectId.value = id; rejectReason.value = ''; showRejectModal.value = true }
const handleReject = async () => {
  processing.value = true
  try {
    await adminApi.rejectWithdraw(rejectId.value, { ly_do: rejectReason.value })
    showRejectModal.value = false
    fetchWithdrawals(wdPagination.value.current_page)
  } catch (e) { alert(e?.response?.data?.message || e?.message || 'Lỗi') }
  processing.value = false
}

const switchTab = (t) => { tab.value = t; if (t === 'wallets') fetchWallets(); else fetchWithdrawals() }
const doSearch = () => { if (tab.value === 'wallets') fetchWallets(); else fetchWithdrawals() }

onMounted(() => { fetchWallets(); fetchWithdrawals() })
</script>

<template>
<section class="vi-page">
  <header class="page-head">
    <div><h1><Wallet :size="24" /> Quản lý Ví Nhà xe</h1><p class="sub">Xem số dư, lịch sử giao dịch và duyệt yêu cầu rút tiền</p></div>
  </header>

  <!-- TABS -->
  <div class="tabs">
    <button :class="{ active: tab==='wallets' }" @click="switchTab('wallets')"><Building2 :size="16" /> Danh sách ví</button>
    <button :class="{ active: tab==='withdrawals' }" @click="switchTab('withdrawals')"><ArrowUpRight :size="16" /> Yêu cầu rút tiền</button>
  </div>

  <!-- SEARCH + FILTERS -->
  <div class="toolbar">
    <div class="search-box">
      <Search :size="16" />
      <input v-model="search" placeholder="Tìm nhà xe, mã ví..." @keyup.enter="doSearch" />
    </div>
    <select v-if="tab==='withdrawals'" v-model="filterStatus" @change="fetchWithdrawals()" class="filter-sel">
      <option value="cho_xac_nhan">Chờ duyệt</option>
      <option value="thanh_toan_thanh_cong">Đã duyệt</option>
      <option value="that_bai">Từ chối</option>
      <option value="tat_ca">Tất cả</option>
    </select>
    <button class="btn-ref" @click="doSearch"><RefreshCw :size="16" /> Tải lại</button>
  </div>

  <!-- WALLETS TABLE -->
  <div v-if="tab==='wallets'" class="glass panel">
    <table class="tbl">
      <thead><tr><th>Mã ví</th><th>Nhà xe</th><th>Số dư</th><th>Tổng nạp</th><th>Tổng rút</th><th>Hoa hồng</th><th>Trạng thái</th><th></th></tr></thead>
      <tbody>
        <tr v-if="loading"><td colspan="8" class="loading-td">Đang tải...</td></tr>
        <tr v-else-if="!wallets.length"><td colspan="8" class="loading-td">Không có dữ liệu</td></tr>
        <tr v-for="w in wallets" :key="w.id">
          <td class="mono">{{ w.ma_vi_nha_xe }}</td>
          <td><strong>{{ w.nha_xe?.ten_nha_xe || w.ma_nha_xe }}</strong><br><small class="muted">{{ w.nha_xe?.email }}</small></td>
          <td class="num txt-green"><strong>{{ fmt(w.so_du) }}</strong></td>
          <td class="num">{{ fmt(w.tong_nap) }}</td>
          <td class="num">{{ fmt(w.tong_rut) }}</td>
          <td class="num txt-red">{{ fmt(w.tong_phi_hoa_hong) }}</td>
          <td><span :class="statusCls(w.trang_thai)" class="badge">{{ statusTxt(w.trang_thai) }}</span></td>
          <td><button class="btn-sm" @click="viewDetail(w.id)"><Eye :size="14" /> Xem</button></td>
        </tr>
      </tbody>
    </table>
    <div class="paging" v-if="walletPagination.last_page > 1">
      <button :disabled="walletPagination.current_page <= 1" @click="fetchWallets(walletPagination.current_page - 1)">‹</button>
      <span>{{ walletPagination.current_page }} / {{ walletPagination.last_page }}</span>
      <button :disabled="walletPagination.current_page >= walletPagination.last_page" @click="fetchWallets(walletPagination.current_page + 1)">›</button>
    </div>
  </div>

  <!-- WITHDRAWALS TABLE -->
  <div v-if="tab==='withdrawals'" class="glass panel">
    <table class="tbl">
      <thead><tr><th>Mã GD</th><th>Nhà xe</th><th>Số tiền</th><th>Số dư trước</th><th>Sau GD</th><th>Nội dung</th><th>Thời gian</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
      <tbody>
        <tr v-if="loading"><td colspan="9" class="loading-td">Đang tải...</td></tr>
        <tr v-else-if="!withdrawals.length"><td colspan="9" class="loading-td">Không có yêu cầu nào</td></tr>
        <tr v-for="wd in withdrawals" :key="wd.id">
          <td class="mono">{{ wd.transaction_code }}</td>
          <td><strong>{{ wd.vi_nha_xe?.nha_xe?.ten_nha_xe || wd.ma_vi_nha_xe }}</strong></td>
          <td class="num txt-orange"><strong>{{ fmt(wd.so_tien) }}</strong></td>
          <td class="num">{{ fmt(wd.so_du_truoc) }}</td>
          <td class="num">{{ fmt(wd.so_du_sau_giao_dich) }}</td>
          <td><small>{{ wd.noi_dung }}</small></td>
          <td><small><Clock :size="12" /> {{ fmtDate(wd.created_at) }}</small></td>
          <td><span :class="statusCls(wd.tinh_trang)" class="badge">{{ statusTxt(wd.tinh_trang) }}</span></td>
          <td>
            <div v-if="wd.tinh_trang === 'cho_xac_nhan'" class="act-btns">
              <button class="btn-approve" @click="handleApprove(wd.id)" :disabled="processing"><CheckCircle :size="14" /> Duyệt</button>
              <button class="btn-reject" @click="openReject(wd.id)" :disabled="processing"><XCircle :size="14" /> Từ chối</button>
            </div>
            <span v-else class="muted">—</span>
          </td>
        </tr>
      </tbody>
    </table>
    <div class="paging" v-if="wdPagination.last_page > 1">
      <button :disabled="wdPagination.current_page <= 1" @click="fetchWithdrawals(wdPagination.current_page - 1)">‹</button>
      <span>{{ wdPagination.current_page }} / {{ wdPagination.last_page }}</span>
      <button :disabled="wdPagination.current_page >= wdPagination.last_page" @click="fetchWithdrawals(wdPagination.current_page + 1)">›</button>
    </div>
  </div>

  <!-- DETAIL MODAL -->
  <div v-if="showDetail" class="modal-ov" @click="showDetail=false">
    <div class="modal-box wide" @click.stop>
      <h2><Wallet :size="20" /> Chi tiết ví {{ detailWallet?.ma_vi_nha_xe }}</h2>
      <div class="detail-cards">
        <div class="dc green"><p>Số dư</p><h3>{{ fmt(detailWallet?.so_du) }}</h3></div>
        <div class="dc blue"><p>Tổng nạp</p><h3>{{ fmt(detailWallet?.tong_nap) }}</h3></div>
        <div class="dc orange"><p>Tổng rút</p><h3>{{ fmt(detailWallet?.tong_rut) }}</h3></div>
      </div>
      <div v-if="detailWallet?.ngan_hang" class="bank-info">
        <p><strong>Ngân hàng:</strong> {{ detailWallet.ngan_hang }} — {{ detailWallet.ten_tai_khoan }} — {{ detailWallet.so_tai_khoan }}</p>
      </div>
      <h3 style="margin:16px 0 8px;font-size:.9rem">Lịch sử giao dịch</h3>
      <table class="tbl mini">
        <thead><tr><th>Mã GD</th><th>Loại</th><th>Số tiền</th><th>Sau GD</th><th>Nội dung</th><th>Trạng thái</th><th>Thời gian</th></tr></thead>
        <tbody>
          <tr v-for="tx in detailTx" :key="tx.id">
            <td class="mono">{{ tx.transaction_code }}</td>
            <td><span :class="typeCls(tx.loai_giao_dich)" class="badge">{{ typeTxt(tx.loai_giao_dich) }}</span></td>
            <td class="num">{{ fmt(tx.so_tien) }}</td>
            <td class="num">{{ fmt(tx.so_du_sau_giao_dich) }}</td>
            <td><small>{{ tx.noi_dung }}</small></td>
            <td><span :class="statusCls(tx.tinh_trang)" class="badge">{{ statusTxt(tx.tinh_trang) }}</span></td>
            <td><small>{{ fmtDate(tx.created_at) }}</small></td>
          </tr>
        </tbody>
      </table>
      <button class="btn-close" @click="showDetail=false">Đóng</button>
    </div>
  </div>

  <!-- REJECT MODAL -->
  <div v-if="showRejectModal" class="modal-ov" @click="showRejectModal=false">
    <div class="modal-box" @click.stop>
      <h2><XCircle :size="20" class="txt-red" /> Từ chối rút tiền</h2>
      <p style="font-size:.88rem;color:#64748b">Tiền sẽ được hoàn lại vào ví nhà xe.</p>
      <textarea v-model="rejectReason" placeholder="Lý do từ chối..." rows="3" class="rej-input"></textarea>
      <div class="modal-btns">
        <button class="btn-cancel" @click="showRejectModal=false">Hủy</button>
        <button class="btn-reject" @click="handleReject" :disabled="processing">{{ processing ? 'Đang xử lý...' : 'Từ chối & Hoàn tiền' }}</button>
      </div>
    </div>
  </div>
</section>
</template>

<style scoped>
.vi-page{display:flex;flex-direction:column;gap:16px;color:#0f172a}
.page-head h1{margin:0 0 4px;font-size:1.3rem;font-weight:800;display:flex;align-items:center;gap:8px}
.sub{margin:0;color:#64748b;font-size:.85rem}
.glass{background:rgba(255,255,255,.85);border:1px solid rgba(226,232,240,.95);border-radius:16px;box-shadow:0 10px 28px rgba(15,23,42,.06)}
.panel{padding:16px;overflow-x:auto}
.tabs{display:flex;gap:4px;border-bottom:2px solid #e2e8f0;padding-bottom:0}
.tabs button{padding:10px 18px;border:none;background:none;font-weight:700;font-size:.88rem;color:#64748b;cursor:pointer;border-bottom:3px solid transparent;display:flex;align-items:center;gap:6px;transition:.2s}
.tabs button.active{color:#2563eb;border-bottom-color:#2563eb}
.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.search-box{display:flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;flex:1;max-width:360px}
.search-box input{border:none;outline:none;font-size:.88rem;flex:1;background:transparent}
.filter-sel{padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:.85rem;background:#fff}
.btn-ref{display:flex;align-items:center;gap:4px;padding:8px 14px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer;font-size:.85rem;font-weight:600}
.tbl{width:100%;border-collapse:collapse;font-size:.84rem}
.tbl th{text-align:left;padding:10px 8px;border-bottom:2px solid #e2e8f0;color:#64748b;font-weight:700;font-size:.78rem;text-transform:uppercase;white-space:nowrap}
.tbl td{padding:10px 8px;border-bottom:1px solid #f1f5f9;vertical-align:top}
.tbl tr:hover td{background:rgba(59,130,246,.03)}
.tbl.mini th,.tbl.mini td{padding:7px 6px;font-size:.8rem}
.mono{font-family:monospace;font-size:.78rem;color:#475569}
.num{text-align:right;font-variant-numeric:tabular-nums}
.muted{color:#94a3b8;font-size:.8rem}
.loading-td{text-align:center;padding:28px;color:#94a3b8}
.badge{padding:3px 10px;border-radius:8px;font-size:.75rem;font-weight:700;white-space:nowrap}
.st-green{background:#dcfce7;color:#16a34a}.st-red{background:#fef2f2;color:#dc2626}.st-yellow{background:#fef9c3;color:#ca8a04}.st-gray{background:#f1f5f9;color:#64748b}
.tp-blue{background:#eff6ff;color:#2563eb}.tp-orange{background:#fff7ed;color:#ea580c}.tp-red{background:#fef2f2;color:#dc2626}.tp-green{background:#f0fdf4;color:#16a34a}.tp-gray{background:#f1f5f9;color:#64748b}
.txt-green{color:#16a34a}.txt-red{color:#dc2626}.txt-orange{color:#ea580c}
.btn-sm{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;font-size:.8rem;font-weight:600}
.btn-sm:hover{border-color:#3b82f6;color:#2563eb}
.act-btns{display:flex;gap:6px}
.btn-approve{display:inline-flex;align-items:center;gap:3px;padding:5px 12px;border:none;border-radius:8px;background:#16a34a;color:#fff;cursor:pointer;font-size:.78rem;font-weight:700}
.btn-approve:hover{background:#15803d}
.btn-reject{display:inline-flex;align-items:center;gap:3px;padding:5px 12px;border:none;border-radius:8px;background:#dc2626;color:#fff;cursor:pointer;font-size:.78rem;font-weight:700}
.btn-reject:hover{background:#b91c1c}
.btn-approve:disabled,.btn-reject:disabled{opacity:.5;cursor:not-allowed}
.paging{display:flex;align-items:center;justify-content:center;gap:12px;padding:14px 0}
.paging button{padding:6px 14px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;font-weight:700}
.paging button:disabled{opacity:.4;cursor:not-allowed}
.paging span{font-size:.85rem;color:#64748b}
.modal-ov{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;display:flex;align-items:center;justify-content:center}
.modal-box{background:#fff;border-radius:18px;padding:24px;max-width:500px;width:90vw;box-shadow:0 20px 60px rgba(0,0,0,.2);max-height:85vh;overflow-y:auto}
.modal-box.wide{max-width:800px}
.modal-box h2{margin:0 0 14px;font-size:1.05rem;font-weight:800;display:flex;align-items:center;gap:8px}
.detail-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px}
.dc{padding:14px;border-radius:12px;text-align:center}
.dc p{margin:0 0 4px;font-size:.78rem;opacity:.8}
.dc h3{margin:0;font-size:1.15rem;font-weight:800}
.dc.green{background:#f0fdf4;color:#16a34a}.dc.blue{background:#eff6ff;color:#2563eb}.dc.orange{background:#fff7ed;color:#ea580c}
.bank-info{padding:10px 14px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;font-size:.85rem;margin-bottom:10px}
.bank-info p{margin:0}
.btn-close{margin-top:14px;padding:8px 20px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer;font-weight:600;font-size:.85rem;float:right}
.rej-input{width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:10px;font-size:.88rem;resize:vertical;box-sizing:border-box;margin:10px 0}
.modal-btns{display:flex;gap:10px;justify-content:flex-end}
.btn-cancel{padding:8px 18px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer;font-weight:600;font-size:.85rem}
@media(max-width:768px){.detail-cards{grid-template-columns:1fr}.tabs button{padding:8px 12px;font-size:.82rem}}
</style>
