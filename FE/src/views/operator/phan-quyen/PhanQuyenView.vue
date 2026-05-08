<script setup>
import { onMounted, ref, computed } from 'vue'
import operatorApi from '@/api/operatorApi.js'
import { useOperatorStore } from '@/stores/operatorStore'

const operatorStore = useOperatorStore()
const loading = ref(false)
const errorText = ref('')

const chucVu = ref(null)
const permissions = ref([])
const chucNangs = ref([])

// ── Nhãn tên chức năng ──────────────────────────────────────────────────────
const permissionLabelMap = {
  'xem-ve': 'Xem vé',
  'dat-ve-admin': 'Đặt vé',
  'cap-nhat-trang-thai-ve': 'Cập nhật trạng thái vé',
  'huy-ve': 'Huỷ vé',
  'xem-tuyen-duong': 'Xem tuyến đường',
  'them-tuyen-duong': 'Thêm tuyến đường',
  'sua-tuyen-duong': 'Sửa tuyến đường',
  'xoa-tuyen-duong': 'Xoá tuyến đường',
  'xem-chuyen-xe': 'Xem chuyến xe',
  'them-chuyen-xe': 'Thêm chuyến xe',
  'sua-chuyen-xe': 'Sửa chuyến xe',
  'xoa-chuyen-xe': 'Xoá chuyến xe',
  'cap-nhat-trang-thai-chuyen-xe': 'Cập nhật trạng thái chuyến xe',
  'doi-xe': 'Đổi xe',
  'xem-tracking-chuyen-xe': 'Xem tracking chuyến xe',
  'xem-xe': 'Xem phương tiện',
  'them-xe': 'Thêm phương tiện',
  'sua-xe': 'Sửa phương tiện',
  'xoa-xe': 'Xoá phương tiện',
  'cap-nhat-trang-thai-xe': 'Cập nhật trạng thái xe',
  'xem-tai-xe': 'Xem tài xế',
  'them-tai-xe': 'Thêm tài xế',
  'sua-tai-xe': 'Sửa tài xế',
  'xoa-tai-xe': 'Xoá tài xế',
  'cap-nhat-trang-thai-tai-xe': 'Cập nhật trạng thái tài xế',
  'xem-voucher': 'Xem voucher',
  'them-voucher': 'Thêm voucher',
  'xem-bao-dong': 'Xem báo động',
  'xem-thong-ke': 'Xem thống kê',
  'xem-dashboard': 'Xem dashboard',
}

const formatLabel = (slug) => {
  if (permissionLabelMap[slug]) return permissionLabelMap[slug]
  return slug.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

// ── Nhóm chức năng theo module ───────────────────────────────────────────────
const moduleGroups = [
  { key: 've', label: 'Quản lý vé', icon: '🎫', slugs: ['xem-ve', 'dat-ve-admin', 'cap-nhat-trang-thai-ve', 'huy-ve'] },
  { key: 'tuyen', label: 'Tuyến đường', icon: '🗺️', slugs: ['xem-tuyen-duong', 'them-tuyen-duong', 'sua-tuyen-duong', 'xoa-tuyen-duong'] },
  { key: 'chuyen', label: 'Chuyến xe', icon: '🚌', slugs: ['xem-chuyen-xe', 'them-chuyen-xe', 'sua-chuyen-xe', 'xoa-chuyen-xe', 'cap-nhat-trang-thai-chuyen-xe', 'doi-xe', 'xem-tracking-chuyen-xe'] },
  { key: 'xe', label: 'Phương tiện', icon: '🚐', slugs: ['xem-xe', 'them-xe', 'sua-xe', 'xoa-xe', 'cap-nhat-trang-thai-xe'] },
  { key: 'taixe', label: 'Tài xế', icon: '👤', slugs: ['xem-tai-xe', 'them-tai-xe', 'sua-tai-xe', 'xoa-tai-xe', 'cap-nhat-trang-thai-tai-xe'] },
  { key: 'voucher', label: 'Voucher', icon: '🎟️', slugs: ['xem-voucher', 'them-voucher'] },
  { key: 'other', label: 'Khác', icon: '⚙️', slugs: ['xem-bao-dong', 'xem-thong-ke', 'xem-dashboard'] },
]

const permSet = computed(() => new Set(permissions.value))

// Nhóm hiển thị: chỉ nhóm nào có ít nhất 1 quyền được phép
const activeGroups = computed(() => {
  if (!chucVu.value) return []
  return moduleGroups.map(g => ({
    ...g,
    items: g.slugs.map(s => ({
      slug: s,
      label: formatLabel(s),
      granted: permSet.value.has(s),
    }))
  }))
})

const totalGranted = computed(() => permissions.value.length)

// Chức năng ngoài danh sách nhóm (nếu có)
const extraPermissions = computed(() => {
  const known = new Set(moduleGroups.flatMap(g => g.slugs))
  return permissions.value.filter(s => !known.has(s))
})

const fetchPhanQuyen = async () => {
  loading.value = true
  errorText.value = ''
  try {
    const res = await operatorApi.getPhanQuyen()
    const payload = res?.data?.data ?? res?.data ?? {}
    chucVu.value = payload.chuc_vu || null
    permissions.value = payload.permissions || []
    chucNangs.value = payload.chuc_nangs || []
  } catch (err) {
    errorText.value = err?.response?.data?.message || err.message || 'Không tải được thông tin phân quyền.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchPhanQuyen)
</script>

<template>
  <section class="pq-page">
    <!-- Header -->
    <div class="pq-header">
      <div class="pq-header-left">
        <div class="pq-header-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </div>
        <div>
          <h1 class="pq-title">Phân quyền tài khoản</h1>
          <p class="pq-subtitle">Xem chức vụ và các chức năng bạn được phép sử dụng trong hệ thống.</p>
        </div>
      </div>
      <button class="btn-refresh" :class="{ spinning: loading }" @click="fetchPhanQuyen" :disabled="loading">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
          <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
        </svg>
        Làm mới
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="skeleton-wrap">
      <div class="skeleton-card">
        <div class="sk-line sk-w60"></div>
        <div class="sk-line sk-w40"></div>
        <div class="sk-line sk-w80"></div>
      </div>
      <div class="skeleton-grid">
        <div v-for="i in 6" :key="i" class="skeleton-module">
          <div class="sk-line sk-w50"></div>
          <div v-for="j in 3" :key="j" class="sk-item"></div>
        </div>
      </div>
    </div>

    <template v-else>
      <!-- Error -->
      <div v-if="errorText" class="error-banner">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        {{ errorText }}
      </div>

      <!-- Không có chức vụ -->
      <div v-else-if="!chucVu" class="no-role-card">
        <div class="no-role-icon">🔒</div>
        <h2>Chưa được phân chức vụ</h2>
        <p>Tài khoản nhà xe của bạn chưa được Admin gán chức vụ. Vui lòng liên hệ quản trị viên để được cấp quyền.</p>
      </div>

      <template v-else>
        <!-- Thông tin chức vụ -->
        <div class="role-summary-card">
          <div class="role-badge-row">
            <span class="role-icon-wrap">🏷️</span>
            <div class="role-info">
              <p class="role-label">Chức vụ hiện tại</p>
              <h2 class="role-name">{{ chucVu.ten_chuc_vu }}</h2>
              <span class="role-slug">{{ chucVu.slug }}</span>
            </div>
            <div class="role-stats">
              <div class="stat-box">
                <span class="stat-num">{{ totalGranted }}</span>
                <span class="stat-label">Chức năng</span>
              </div>
              <div class="stat-box stat-box--modules">
                <span class="stat-num">{{ activeGroups.length }}</span>
                <span class="stat-label">Module</span>
              </div>
            </div>
          </div>

          <!-- Tags tất cả quyền -->
          <div class="permission-tags" v-if="permissions.length > 0">
            <span v-for="slug in permissions" :key="slug" class="ptag">
              {{ formatLabel(slug) }}
            </span>
          </div>
          <p v-else class="no-perm-text">Chức vụ này chưa được gán chức năng nào.</p>
        </div>

        <!-- Grid modules -->
        <div class="modules-grid">
          <div v-for="group in activeGroups" :key="group.key" class="module-card">
            <div class="module-header">
              <span class="module-icon">{{ group.icon }}</span>
              <span class="module-title">{{ group.label }}</span>
              <span class="module-count">
                {{ group.items.filter(i => i.granted).length }}/{{ group.items.length }}
              </span>
            </div>
            <ul class="module-items">
              <li v-for="item in group.items" :key="item.slug" class="module-item" :class="item.granted ? 'granted' : 'denied'">
                <span class="item-icon">
                  <svg v-if="item.granted" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </span>
                <span class="item-label">{{ item.label }}</span>
                <span class="item-slug">{{ item.slug }}</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Quyền bổ sung ngoài nhóm -->
        <div v-if="extraPermissions.length > 0" class="extra-card">
          <div class="module-header">
            <span class="module-icon">✨</span>
            <span class="module-title">Chức năng bổ sung</span>
            <span class="module-count">{{ extraPermissions.length }}</span>
          </div>
          <div class="extra-tags">
            <span v-for="slug in extraPermissions" :key="slug" class="ptag ptag--extra">
              {{ formatLabel(slug) }}
              <small>{{ slug }}</small>
            </span>
          </div>
        </div>
      </template>
    </template>
  </section>
</template>

<style scoped>
/* ── Base ─────────────────────────────────────────────────────────────────── */
.pq-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: 'Inter', sans-serif;
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.pq-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.pq-header-left {
  display: flex;
  align-items: center;
  gap: 14px;
}
.pq-header-icon {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  background: linear-gradient(135deg, #0d4f35, #15803d);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
}
.pq-title {
  font-size: 22px;
  font-weight: 800;
  color: #0d4f35;
  margin: 0;
  line-height: 1.2;
}
.pq-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 4px 0 0;
}

.btn-refresh {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  border: 1px solid #d1fae5;
  background: #f0fdf4;
  color: #0d4f35;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-refresh:hover:not(:disabled) {
  background: #dcfce7;
  border-color: #4ade80;
}
.btn-refresh:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-refresh.spinning svg { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Loading skeleton ─────────────────────────────────────────────────────── */
.skeleton-wrap { display: flex; flex-direction: column; gap: 20px; }
.skeleton-card {
  background: #fff;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.skeleton-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}
.skeleton-module {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.sk-line {
  height: 14px;
  border-radius: 6px;
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}
.sk-item { height: 38px; border-radius: 8px; background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
.sk-w40 { width: 40%; }
.sk-w50 { width: 50%; }
.sk-w60 { width: 60%; }
.sk-w80 { width: 80%; }
@keyframes shimmer { to { background-position: -200% 0; } }

/* ── Error ────────────────────────────────────────────────────────────────── */
.error-banner {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 18px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 12px;
  color: #b91c1c;
  font-size: 14px;
  font-weight: 600;
}

/* ── No role ──────────────────────────────────────────────────────────────── */
.no-role-card {
  text-align: center;
  padding: 60px 24px;
  background: #fff;
  border-radius: 16px;
  border: 1px dashed #d1fae5;
}
.no-role-icon { font-size: 52px; margin-bottom: 14px; }
.no-role-card h2 { font-size: 18px; font-weight: 700; color: #1e3a2f; margin: 0 0 8px; }
.no-role-card p { font-size: 14px; color: #64748b; max-width: 420px; margin: 0 auto; }

/* ── Role summary ─────────────────────────────────────────────────────────── */
.role-summary-card {
  background: linear-gradient(135deg, #0d4f35 0%, #166534 60%, #15803d 100%);
  border-radius: 18px;
  padding: 28px 28px 24px;
  color: #fff;
  position: relative;
  overflow: hidden;
}
.role-summary-card::before {
  content: '';
  position: absolute;
  top: -40px;
  right: -40px;
  width: 200px;
  height: 200px;
  background: rgba(255,255,255,0.05);
  border-radius: 50%;
}
.role-badge-row {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.role-icon-wrap {
  font-size: 32px;
  line-height: 1;
  flex-shrink: 0;
}
.role-info { flex: 1; min-width: 0; }
.role-label {
  font-size: 12px;
  opacity: 0.75;
  margin: 0 0 4px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.role-name {
  font-size: 22px;
  font-weight: 800;
  margin: 0;
  line-height: 1.2;
}
.role-slug {
  font-size: 12px;
  opacity: 0.6;
  font-family: monospace;
}
.role-stats {
  display: flex;
  gap: 12px;
  flex-shrink: 0;
}
.stat-box {
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 12px;
  padding: 10px 16px;
  text-align: center;
  backdrop-filter: blur(4px);
}
.stat-num {
  display: block;
  font-size: 26px;
  font-weight: 800;
  line-height: 1;
}
.stat-label {
  display: block;
  font-size: 11px;
  opacity: 0.75;
  margin-top: 4px;
}

/* Tags quyền trong summary */
.permission-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.ptag {
  padding: 5px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  background: rgba(255,255,255,0.18);
  border: 1px solid rgba(255,255,255,0.25);
  backdrop-filter: blur(4px);
  color: #fff;
  white-space: nowrap;
}
.no-perm-text {
  font-size: 13px;
  opacity: 0.7;
  margin: 0;
}

/* ── Module grid ──────────────────────────────────────────────────────────── */
.modules-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 16px;
}
.module-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  overflow: hidden;
  transition: box-shadow 0.2s, transform 0.2s;
}
.module-card:hover {
  box-shadow: 0 8px 24px rgba(13, 79, 53, 0.08);
  transform: translateY(-2px);
}
.module-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  border-bottom: 1px solid #f1f5f9;
  background: #f8fdf9;
}
.module-icon { font-size: 18px; line-height: 1; }
.module-title {
  flex: 1;
  font-size: 14px;
  font-weight: 700;
  color: #0d4f35;
}
.module-count {
  font-size: 12px;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 999px;
  background: #dcfce7;
  color: #166534;
}

.module-items {
  list-style: none;
  padding: 10px 12px;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.module-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 8px;
  transition: background 0.15s;
}
.module-item.granted {
  background: #f0fdf4;
}
.module-item.denied {
  background: #fafafa;
  opacity: 0.55;
}
.item-icon {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.granted .item-icon {
  background: #dcfce7;
  color: #16a34a;
}
.denied .item-icon {
  background: #f1f5f9;
  color: #94a3b8;
}
.item-label {
  flex: 1;
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
}
.denied .item-label { color: #94a3b8; }
.item-slug {
  font-size: 11px;
  color: #cbd5e1;
  font-family: monospace;
}

/* ── Extra permissions ────────────────────────────────────────────────────── */
.extra-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  overflow: hidden;
}
.extra-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  padding: 16px;
}
.ptag--extra {
  background: #eef2ff;
  border-color: #c7d2fe;
  color: #4338ca;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  border-radius: 10px;
  padding: 8px 12px;
}
.ptag--extra small {
  font-size: 10px;
  opacity: 0.7;
  font-family: monospace;
  font-weight: 400;
  margin-top: 2px;
}

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .pq-header { flex-direction: column; align-items: stretch; }
  .btn-refresh { justify-content: center; }
  .role-badge-row { flex-direction: column; }
  .role-stats { justify-content: flex-start; }
  .modules-grid { grid-template-columns: 1fr; }
}
</style>
