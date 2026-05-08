<script setup>
import { onMounted, ref, reactive, computed } from "vue";
import operatorApi from "@/api/operatorApi.js";

const loading = ref(false);
const saving = ref(false);
const error = ref("");
const success = ref("");

const staffList = ref([]);
const chucVuList = ref([]);
const search = ref("");
const filterStatus = ref("");

// Modal
const showModal = ref(false);
const editingId = ref(null);
const form = reactive({
  ho_va_ten: "",
  email: "",
  password: "",
  so_dien_thoai: "",
  id_chuc_vu: "",
});

// Phan quyen modal
const showPqModal = ref(false);
const selectedChucVu = ref(null);
const chucNangList = ref([]);
const selectedIds = ref([]);
const pqSaving = ref(false);
const activeTab = ref("all");

const modalTitle = computed(() =>
  editingId.value ? "Cập nhật nhân viên" : "Thêm nhân viên mới",
);

// Nhóm chức năng theo module cho modal phân quyền
const moduleMap = {
  ve: {
    label: "🎫 Vé",
    slugs: ["op-xem-ve", "op-dat-ve", "op-cap-nhat-trang-thai-ve", "op-huy-ve"],
  },
  tuyen: {
    label: "🗺️ Tuyến đường",
    slugs: [
      "op-xem-tuyen-duong",
      "op-them-tuyen-duong",
      "op-sua-tuyen-duong",
      "op-xoa-tuyen-duong",
    ],
  },
  chuyen: {
    label: "🚌 Chuyến xe",
    slugs: [
      "op-xem-chuyen-xe",
      "op-tao-chuyen-xe",
      "op-sua-chuyen-xe",
      "op-xoa-chuyen-xe",
      "op-xem-tracking",
    ],
  },
  xe: {
    label: "🚐 Phương tiện",
    slugs: ["op-xem-xe", "op-them-xe", "op-sua-xe", "op-xoa-xe"],
  },
  taixe: {
    label: "👤 Tài xế",
    slugs: [
      "op-xem-tai-xe",
      "op-them-tai-xe",
      "op-sua-tai-xe",
      "op-xoa-tai-xe",
    ],
  },
  nhanvien: {
    label: "👥 Nhân viên",
    slugs: [
      "op-xem-nhan-vien",
      "op-them-nhan-vien",
      "op-sua-nhan-vien",
      "op-xoa-nhan-vien",
      "op-phan-quyen-nhan-vien",
    ],
  },
  khac: {
    label: "⚙️ Khác",
    slugs: [
      "op-xem-voucher",
      "op-tao-voucher",
      "op-xem-thong-ke",
      "op-xem-bao-dong",
      "op-xem-vi",
    ],
  },
};

const groupedChucNangs = computed(() => {
  const result = {};
  for (const [key, mod] of Object.entries(moduleMap)) {
    result[key] = {
      label: mod.label,
      items: chucNangList.value.filter((cn) => mod.slugs.includes(cn.slug)),
    };
  }
  return result;
});

const countSelected = computed(() => selectedIds.value.length);

function selectAll() {
  selectedIds.value = chucNangList.value.map((cn) => cn.id);
}
function clearAll() {
  selectedIds.value = [];
}

const filtered = computed(() => {
  let list = staffList.value;
  if (search.value) {
    const kw = search.value.toLowerCase();
    list = list.filter(
      (s) =>
        s.ho_va_ten?.toLowerCase().includes(kw) ||
        s.email?.toLowerCase().includes(kw),
    );
  }
  if (filterStatus.value)
    list = list.filter((s) => s.tinh_trang === filterStatus.value);
  return list;
});

async function fetchAll() {
  loading.value = true;
  try {
    const [r1, r2] = await Promise.all([
      operatorApi.getStaffs(),
      operatorApi.getChucVusNhaXe(),
    ]);
    staffList.value = r1?.data?.data?.data ?? r1?.data?.data ?? r1?.data ?? [];
    chucVuList.value = r2?.data?.data ?? r2?.data ?? [];
  } catch (e) {
    error.value = e?.response?.data?.message || "Không tải được dữ liệu.";
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingId.value = null;
  Object.assign(form, {
    ho_va_ten: "",
    email: "",
    password: "",
    so_dien_thoai: "",
    id_chuc_vu: "",
  });
  showModal.value = true;
}

function openEdit(nv) {
  editingId.value = nv.id;
  Object.assign(form, {
    ho_va_ten: nv.ho_va_ten,
    email: nv.email,
    password: "",
    so_dien_thoai: nv.so_dien_thoai || "",
    id_chuc_vu: nv.id_chuc_vu || "",
  });
  showModal.value = true;
}

async function saveStaff() {
  saving.value = true;
  error.value = "";
  try {
    const payload = { ...form };
    if (!payload.password) delete payload.password;
    if (editingId.value)
      await operatorApi.updateStaff(editingId.value, payload);
    else await operatorApi.createStaff(payload);
    showModal.value = false;
    success.value = editingId.value
      ? "Cập nhật thành công."
      : "Thêm nhân viên thành công.";
    await fetchAll();
    setTimeout(() => (success.value = ""), 3000);
  } catch (e) {
    error.value = e?.response?.data?.message || "Lỗi khi lưu.";
  } finally {
    saving.value = false;
  }
}

async function toggleStatus(nv) {
  try {
    await operatorApi.toggleStaffStatus(nv.id);
    await fetchAll();
  } catch (e) {
    error.value = e?.response?.data?.message || "Lỗi.";
  }
}

async function deleteStaff(nv) {
  if (!confirm(`Xoá nhân viên "${nv.ho_va_ten}"?`)) return;
  try {
    await operatorApi.deleteStaff(nv.id);
    await fetchAll();
    success.value = "Xoá nhân viên thành công.";
    setTimeout(() => (success.value = ""), 3000);
  } catch (e) {
    error.value = e?.response?.data?.message || "Lỗi.";
  }
}

async function openPhanQuyen(cv) {
  selectedChucVu.value = cv;
  pqSaving.value = false;
  try {
    const [r1, r2] = await Promise.all([
      operatorApi.getChucNangsNhaXe(),
      operatorApi.getChucVuPhanQuyen(cv.id),
    ]);
    chucNangList.value = r1?.data?.data ?? r1?.data ?? [];
    selectedIds.value = (r2?.data?.data?.quyen_ids ?? []).map(Number);
    showPqModal.value = true;
  } catch (e) {
    error.value = e?.response?.data?.message || "Lỗi tải phân quyền.";
  }
}

function togglePq(id) {
  const s = new Set(selectedIds.value);
  s.has(id) ? s.delete(id) : s.add(id);
  selectedIds.value = [...s];
}

async function savePhanQuyen() {
  pqSaving.value = true;
  try {
    await operatorApi.syncChucVuPhanQuyen(selectedChucVu.value.id, {
      chuc_nang_ids: selectedIds.value,
    });
    showPqModal.value = false;
    success.value = "Cập nhật phân quyền thành công.";
    setTimeout(() => (success.value = ""), 3000);
  } catch (e) {
    error.value = e?.response?.data?.message || "Lỗi.";
  } finally {
    pqSaving.value = false;
  }
}

onMounted(fetchAll);
</script>

<template>
  <section class="nv-page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Quản lý nhân viên nhà xe</h1>
        <p class="page-sub">
          Thêm, sửa, phân quyền nhân viên nội bộ của nhà xe bạn.
        </p>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary" @click="openCreate">
          + Thêm nhân viên
        </button>
      </div>
    </div>

    <!-- Alert -->
    <div v-if="success" class="alert alert-success">✓ {{ success }}</div>
    <div v-if="error" class="alert alert-error">✕ {{ error }}</div>

    <!-- Toolbar -->
    <div class="toolbar">
      <input
        v-model="search"
        class="inp"
        placeholder="🔍 Tìm theo tên, email..."
      />
      <select v-model="filterStatus" class="inp sel">
        <option value="">Tất cả trạng thái</option>
        <option value="hoat_dong">Hoạt động</option>
        <option value="khoa">Khoá</option>
      </select>
      <button class="btn btn-ghost" @click="fetchAll">↻ Làm mới</button>
    </div>

    <!-- Table -->
    <div class="card">
      <div v-if="loading" class="loading-state">Đang tải...</div>
      <table v-else class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Họ tên</th>
            <th>Email</th>
            <th>Điện thoại</th>
            <th>Chức vụ</th>
            <th>Trạng thái</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filtered.length === 0">
            <td colspan="7" class="empty">Chưa có nhân viên nào.</td>
          </tr>
          <tr v-for="(nv, i) in filtered" :key="nv.id">
            <td class="td-num">{{ i + 1 }}</td>
            <td class="td-name">{{ nv.ho_va_ten }}</td>
            <td class="td-email">{{ nv.email }}</td>
            <td>{{ nv.so_dien_thoai || "—" }}</td>
            <td>
              <span v-if="nv.chuc_vu" class="badge badge-role">{{
                nv.chuc_vu.ten_chuc_vu
              }}</span>
              <span v-else class="badge badge-none">Chưa gán</span>
            </td>
            <td>
              <span
                class="badge"
                :class="
                  nv.tinh_trang === 'hoat_dong'
                    ? 'badge-active'
                    : 'badge-locked'
                "
              >
                {{ nv.tinh_trang === "hoat_dong" ? "Hoạt động" : "Khoá" }}
              </span>
            </td>
            <td>
              <div class="action-row">
                <button class="btn-sm btn-edit" @click="openEdit(nv)">
                  ✏️
                </button>
                <button
                  class="btn-sm"
                  :class="
                    nv.tinh_trang === 'hoat_dong' ? 'btn-lock' : 'btn-unlock'
                  "
                  @click="toggleStatus(nv)"
                >
                  {{ nv.tinh_trang === "hoat_dong" ? "🔒" : "🔓" }}
                </button>
                <button class="btn-sm btn-del" @click="deleteStaff(nv)">
                  🗑️
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Phân quyền chức vụ section -->
    <div class="card" v-if="chucVuList.length > 0">
      <h2 class="section-title">⚙️ Phân quyền theo chức vụ nhà xe</h2>
      <p class="section-sub">
        Cấu hình các chức năng được phép cho từng chức vụ.
      </p>
      <div class="cv-grid">
        <div
          v-for="cv in chucVuList"
          :key="cv.id"
          class="cv-card"
          @click="openPhanQuyen(cv)"
        >
          <div class="cv-icon">🏷️</div>
          <div class="cv-info">
            <p class="cv-name">{{ cv.ten_chuc_vu }}</p>
            <span class="cv-slug">{{ cv.slug }}</span>
          </div>
          <button class="btn-sm btn-pq">Cấu hình →</button>
        </div>
      </div>
    </div>

    <!-- Modal thêm/sửa nhân viên -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="modal-backdrop"
        @click.self="showModal = false"
      >
        <div class="modal">
          <div class="modal-header">
            <h3>{{ modalTitle }}</h3>
            <button class="modal-close" @click="showModal = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="field">
              <label>Họ và tên *</label>
              <input
                v-model="form.ho_va_ten"
                class="inp"
                placeholder="Nguyễn Văn A"
              />
            </div>
            <div class="field">
              <label>Email *</label>
              <input
                v-model="form.email"
                type="email"
                class="inp"
                placeholder="nv@nhaxe.vn"
              />
            </div>
            <div class="field">
              <label
                >Mật khẩu
                {{ editingId ? "(để trống nếu không đổi)" : "*" }}</label
              >
              <input
                v-model="form.password"
                type="password"
                class="inp"
                placeholder="Ít nhất 6 ký tự"
              />
            </div>
            <div class="field">
              <label>Số điện thoại</label>
              <input
                v-model="form.so_dien_thoai"
                class="inp"
                placeholder="0901..."
              />
            </div>
            <div class="field">
              <label>Chức vụ</label>
              <select v-model="form.id_chuc_vu" class="inp">
                <option value="">-- Chọn chức vụ --</option>
                <option v-for="cv in chucVuList" :key="cv.id" :value="cv.id">
                  {{ cv.ten_chuc_vu }}
                </option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline" @click="showModal = false">
              Huỷ
            </button>
            <button
              class="btn btn-primary"
              :disabled="saving"
              @click="saveStaff"
            >
              {{ saving ? "Đang lưu..." : "Lưu" }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal phân quyền chức vụ -->
    <Teleport to="body">
      <div
        v-if="showPqModal"
        class="modal-backdrop"
        @click.self="showPqModal = false"
      >
        <div class="modal modal-lg">
          <div class="modal-header pq-modal-header">
            <div class="pq-header-info">
              <div class="pq-header-icon">⚙️</div>
              <div>
                <h3>Phân quyền chức vụ</h3>
                <p class="pq-header-sub">
                  {{ selectedChucVu?.ten_chuc_vu }} ·
                  <strong>{{ countSelected }}</strong> quyền được chọn
                </p>
              </div>
            </div>
            <div class="pq-header-actions">
              <button class="btn-xs" @click="selectAll">Chọn tất cả</button>
              <button class="btn-xs btn-xs-danger" @click="clearAll">
                Bỏ chọn
              </button>
              <button class="modal-close" @click="showPqModal = false">
                ✕
              </button>
            </div>
          </div>
          <div class="modal-body pq-body">
            <template v-for="(mod, key) in groupedChucNangs" :key="key">
              <div v-if="mod.items.length > 0" class="pq-group">
                <div class="pq-group-title">{{ mod.label }}</div>
                <div class="pq-grid">
                  <label
                    v-for="cn in mod.items"
                    :key="cn.id"
                    class="pq-item"
                    :class="{ checked: selectedIds.includes(cn.id) }"
                  >
                    <div
                      class="pq-checkbox"
                      :class="{
                        'pq-checkbox-checked': selectedIds.includes(cn.id),
                      }"
                      @click="togglePq(cn.id)"
                    >
                      <svg
                        v-if="selectedIds.includes(cn.id)"
                        width="12"
                        height="12"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="3"
                      >
                        <polyline points="20 6 9 17 4 12" />
                      </svg>
                    </div>
                    <div @click="togglePq(cn.id)">
                      <p class="pq-name">
                        {{ cn.ten_chuc_nang.replace(" [NX]", "") }}
                      </p>
                      <span class="pq-slug">{{ cn.slug }}</span>
                    </div>
                  </label>
                </div>
              </div>
            </template>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline" @click="showPqModal = false">
              Đóng
            </button>
            <button
              class="btn btn-primary"
              :disabled="pqSaving"
              @click="savePhanQuyen"
            >
              <span v-if="pqSaving">Đang lưu...</span>
              <span v-else>💾 Lưu phân quyền ({{ countSelected }})</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.nv-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  font-family: "Inter", sans-serif;
}
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}
.page-title {
  font-size: 22px;
  font-weight: 800;
  color: #0d4f35;
  margin: 0;
}
.page-sub {
  font-size: 13px;
  color: #64748b;
  margin: 4px 0 0;
}
.header-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.alert {
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
}
.alert-success {
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
}
.alert-error {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
}

.toolbar {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.inp {
  border: 1px solid #d1d5db;
  border-radius: 10px;
  padding: 9px 14px;
  font-size: 14px;
  outline: none;
}
.inp:focus {
  border-color: #16a34a;
}
.toolbar .inp {
  flex: 1;
  min-width: 180px;
}
.sel {
  min-width: 180px;
}

.card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  overflow: hidden;
}
.loading-state {
  padding: 40px;
  text-align: center;
  color: #64748b;
}
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}
.table th {
  background: #f8fdf9;
  padding: 12px 14px;
  text-align: left;
  font-size: 12px;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-bottom: 1px solid #e2e8f0;
}
.table td {
  padding: 12px 14px;
  border-bottom: 1px solid #f1f5f9;
  color: #1e293b;
  vertical-align: middle;
}
.table tr:last-child td {
  border-bottom: none;
}
.table tr:hover td {
  background: #f8fdf9;
}
.td-num {
  color: #94a3b8;
  width: 40px;
}
.td-name {
  font-weight: 600;
}
.td-email {
  color: #475569;
}
.empty {
  text-align: center;
  color: #94a3b8;
  padding: 32px;
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}
.badge-active {
  background: #dcfce7;
  color: #166534;
}
.badge-locked {
  background: #fee2e2;
  color: #b91c1c;
}
.badge-role {
  background: #e0f2fe;
  color: #0369a1;
}
.badge-none {
  background: #f1f5f9;
  color: #94a3b8;
}

.action-row {
  display: flex;
  gap: 6px;
}
.btn-sm {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  transition: all 0.15s;
}
.btn-sm:hover {
  transform: scale(1.1);
}
.btn-edit:hover {
  background: #e0f2fe;
  border-color: #7dd3fc;
}
.btn-lock:hover {
  background: #fee2e2;
  border-color: #fca5a5;
}
.btn-unlock:hover {
  background: #dcfce7;
  border-color: #86efac;
}
.btn-del:hover {
  background: #fee2e2;
  border-color: #fca5a5;
}
.btn-pq {
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 600;
  background: #f0fdf4;
  color: #166534;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 4px;
}

.btn {
  padding: 9px 18px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}
.btn-primary {
  background: linear-gradient(135deg, #0d4f35, #16a34a);
  color: #fff;
}
.btn-primary:hover:not(:disabled) {
  opacity: 0.9;
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-outline {
  background: #fff;
  border: 1px solid #d1d5db;
  color: #374151;
}
.btn-outline:hover {
  background: #f9fafb;
}
.btn-ghost {
  background: transparent;
  border: 1px solid #e2e8f0;
  color: #64748b;
}

/* Chức vụ grid */
.section-title {
  font-size: 16px;
  font-weight: 700;
  color: #0d4f35;
  margin: 0 0 4px;
  padding: 20px 20px 0;
}
.section-sub {
  font-size: 13px;
  color: #64748b;
  margin: 0 0 16px;
  padding: 0 20px;
}
.cv-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 12px;
  padding: 0 20px 20px;
}
.cv-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
}
.cv-card:hover {
  border-color: #4ade80;
  box-shadow: 0 4px 12px rgba(13, 79, 53, 0.08);
}
.cv-icon {
  font-size: 22px;
  flex-shrink: 0;
}
.cv-info {
  flex: 1;
  min-width: 0;
}
.cv-name {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}
.cv-slug {
  font-size: 11px;
  color: #94a3b8;
  font-family: monospace;
}

/* Modal */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  overflow-y: auto;
}
.modal {
  background: #fff;
  border-radius: 16px;
  width: 100%;
  max-width: 500px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
  margin: auto;
  position: relative;
}
.modal-lg {
  max-width: 720px;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
  border-bottom: 1px solid #f1f5f9;
  flex-shrink: 0;
}
.modal-header h3 {
  font-size: 16px;
  font-weight: 700;
  color: #0d4f35;
  margin: 0;
}
.modal-close {
  background: none;
  border: none;
  font-size: 18px;
  color: #94a3b8;
  cursor: pointer;
}
.modal-body {
  padding: 20px;
  overflow-y: auto;
  flex: 1;
}
.modal-footer {
  padding: 16px 20px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 14px;
}
.field label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}
.field .inp {
  width: 100%;
}

/* Phan quyen modal */
.pq-modal-header {
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
  padding: 20px 24px;
  background: linear-gradient(135deg, #0d4f35, #166534);
  color: #fff;
}
.pq-header-info {
  display: flex;
  align-items: center;
  gap: 14px;
}
.pq-header-icon {
  font-size: 28px;
}
.pq-modal-header h3 {
  color: #fff;
  font-size: 18px;
  margin: 0;
}
.pq-header-sub {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.75);
  margin: 3px 0 0;
}
.pq-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-xs {
  padding: 5px 12px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-xs:hover {
  background: rgba(255, 255, 255, 0.25);
}
.btn-xs-danger {
  border-color: rgba(255, 100, 100, 0.4);
  background: rgba(255, 100, 100, 0.15);
}
.pq-modal-header .modal-close {
  color: rgba(255, 255, 255, 0.7);
  font-size: 18px;
  margin-left: auto;
}
.pq-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.pq-group-title {
  font-size: 13px;
  font-weight: 700;
  color: #0d4f35;
  margin-bottom: 10px;
  padding: 6px 10px;
  background: #f0fdf4;
  border-radius: 8px;
  border-left: 3px solid #16a34a;
}
.pq-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 8px;
}
.pq-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.15s;
  background: #fff;
}
.pq-item:hover {
  border-color: #86efac;
  background: #f9fff9;
}
.pq-item.checked {
  background: #f0fdf4;
  border-color: #4ade80;
  box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
}
.pq-checkbox {
  width: 18px;
  height: 18px;
  border-radius: 5px;
  border: 2px solid #d1d5db;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 2px;
  transition: all 0.15s;
}
.pq-checkbox-checked {
  background: #16a34a;
  border-color: #16a34a;
  color: #fff;
}
.pq-name {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  margin: 0;
  line-height: 1.3;
}
.pq-slug {
  font-size: 10px;
  color: #94a3b8;
  font-family: monospace;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
  }
  .pq-grid {
    grid-template-columns: 1fr;
  }
  .cv-grid {
    grid-template-columns: 1fr;
  }
}
</style>
