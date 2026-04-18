<script setup>
import { ref } from 'vue'

const CONFIG_KEY = 'admin.system.config'

const defaultConfig = {
  aiEarThreshold: 0.22,
  eyeCloseSeconds: 2.4,
  violationEscalateCount: 3,
  gpsPushInterval: 5,
  gpsLostSeconds: 25,
  speedLimitUrban: 60,
  speedLimitHighway: 90,
  wsRetryCount: 5,
  wsRetrySeconds: 3,
  enableCriticalSound: true,
  enableEmailAlert: false,
  alertTemplate: 'Xe [Biển số] - Tài xế [Tài xế] vi phạm: [Nội dung], GPS [Tọa độ]',
}

const saveState = ref('')
const configForm = ref(loadConfig())

function loadConfig() {
  try {
    const raw = localStorage.getItem(CONFIG_KEY)
    if (!raw) return { ...defaultConfig }
    return { ...defaultConfig, ...JSON.parse(raw) }
  } catch {
    return { ...defaultConfig }
  }
}

const handleReset = () => {
  configForm.value = { ...defaultConfig }
  saveState.value = 'Đã khôi phục cấu hình mặc định.'
}

const handleSave = () => {
  localStorage.setItem(CONFIG_KEY, JSON.stringify(configForm.value))
  saveState.value = 'Lưu cấu hình thành công. Có thể map trực tiếp payload này cho API backend.'
}
</script>

<template>
  <section class="config-page">
    <header class="page-head">
      <div>
        <h1 class="page-title">Cấu hình chung hệ thống</h1>
        <p class="page-sub">
          Thiết lập chuẩn vận hành AI, GPS, cảnh báo và kết nối realtime cho toàn bộ dashboard quản trị.
        </p>
      </div>
      <div class="head-actions">
        <button class="btn btn-outline" @click="handleReset">Khôi phục mặc định</button>
        <button class="btn btn-primary" @click="handleSave">Lưu cấu hình</button>
      </div>
    </header>

    <div class="config-grid">
      <article class="glass-card">
        <h2 class="card-title">Ngưỡng AI ngủ gật & hành vi</h2>
        <div class="form-grid">
          <label class="field">
            <span>EAR threshold</span>
            <input v-model.number="configForm.aiEarThreshold" type="number" step="0.01" min="0.1" max="0.4" />
          </label>
          <label class="field">
            <span>Nhắm mắt liên tục (giây)</span>
            <input v-model.number="configForm.eyeCloseSeconds" type="number" step="0.1" min="1" max="10" />
          </label>
          <label class="field">
            <span>Số lần vi phạm để nâng Critical</span>
            <input v-model.number="configForm.violationEscalateCount" type="number" min="1" max="10" />
          </label>
        </div>
      </article>

      <article class="glass-card">
        <h2 class="card-title">GPS & Tracking</h2>
        <div class="form-grid">
          <label class="field">
            <span>Chu kỳ gửi GPS (giây)</span>
            <input v-model.number="configForm.gpsPushInterval" type="number" min="1" max="30" />
          </label>
          <label class="field">
            <span>Timeout mất GPS (giây)</span>
            <input v-model.number="configForm.gpsLostSeconds" type="number" min="5" max="180" />
          </label>
          <label class="field">
            <span>Giới hạn tốc độ nội đô (km/h)</span>
            <input v-model.number="configForm.speedLimitUrban" type="number" min="20" max="120" />
          </label>
          <label class="field">
            <span>Giới hạn tốc độ cao tốc (km/h)</span>
            <input v-model.number="configForm.speedLimitHighway" type="number" min="40" max="140" />
          </label>
        </div>
      </article>

      <article class="glass-card">
        <h2 class="card-title">Realtime WebSocket</h2>
        <div class="form-grid">
          <label class="field">
            <span>Số lần retry khi mất kết nối</span>
            <input v-model.number="configForm.wsRetryCount" type="number" min="1" max="20" />
          </label>
          <label class="field">
            <span>Khoảng cách retry (giây)</span>
            <input v-model.number="configForm.wsRetrySeconds" type="number" min="1" max="30" />
          </label>
          <label class="field checkbox-field">
            <input v-model="configForm.enableCriticalSound" type="checkbox" />
            <span>Bật âm thanh cảnh báo mức Critical</span>
          </label>
          <label class="field checkbox-field">
            <input v-model="configForm.enableEmailAlert" type="checkbox" />
            <span>Bật gửi email cảnh báo</span>
          </label>
        </div>
      </article>

      <article class="glass-card full-width">
        <h2 class="card-title">Template nội dung cảnh báo</h2>
        <label class="field">
          <span>Mẫu thông báo (hỗ trợ biến [Biển số], [Tài xế], [Nội dung], [Tọa độ])</span>
          <textarea
            v-model="configForm.alertTemplate"
            rows="3"
            placeholder="Xe [Biển số] - Tài xế [Tài xế]..."
          ></textarea>
        </label>
      </article>
    </div>

    <p v-if="saveState" class="save-state">{{ saveState }}</p>
  </section>
</template>

<style scoped>
.config-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.page-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.page-title {
  font-size: 24px;
  font-weight: 800;
  margin: 0;
}

.page-sub {
  margin-top: 6px;
  color: #64748b;
  font-size: 14px;
}

.head-actions {
  display: flex;
  gap: 8px;
}

.btn {
  border: none;
  border-radius: 10px;
  padding: 10px 14px;
  font-weight: 700;
  cursor: pointer;
}

.btn-primary {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff;
}

.btn-outline {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
}

.config-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.glass-card {
  background: rgba(255, 255, 255, 0.85);
  border: 1px solid #e2e8f0;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
  border-radius: 14px;
  padding: 16px;
}

.full-width {
  grid-column: 1 / -1;
}

.card-title {
  font-size: 16px;
  margin: 0 0 12px 0;
  color: #0f172a;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 12px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  color: #334155;
}

.field input,
.field textarea {
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  padding: 10px;
  font-size: 14px;
  outline: none;
  transition: all 0.2s ease;
}

.field input:focus,
.field textarea:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.checkbox-field {
  flex-direction: row;
  align-items: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px;
}

.checkbox-field input {
  width: 16px;
  height: 16px;
}

.save-state {
  font-size: 13px;
  color: #0369a1;
  background: #e0f2fe;
  border: 1px solid #bae6fd;
  border-radius: 10px;
  padding: 10px 12px;
}

@media (max-width: 1024px) {
  .config-grid,
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
