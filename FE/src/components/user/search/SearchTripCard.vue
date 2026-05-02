<script setup>
import { formatCurrency, formatTime, calcArrivalTime, formatFullDate } from "@/utils/format";

defineProps({
  chuyen: {
    type: Object,
    required: true,
  },
});

defineEmits(["select"]);
</script>

<template>
  <div class="trip-card" @click="$emit('select', chuyen)">
    <div class="trip-card__body">
      <!-- Cột thời gian + lộ trình -->
      <div class="trip-card__timeline">
        <!-- Thông tin nhà xe -->
        <div class="trip-card__operator">
          <span class="material-symbols-outlined trip-card__operator-icon">directions_bus</span>
          <span class="trip-card__operator-name">{{
            chuyen.tuyen_duong?.nha_xe?.ten_nha_xe || "Nhà xe"
          }}</span>
          <span class="trip-card__vehicle-badge">{{ chuyen.xe?.ten_xe }}</span>
        </div>

        <!-- Lộ trình -->
        <div class="trip-card__route">
          <div class="trip-card__stop">
            <div class="trip-card__stop-time">
              {{ formatTime(chuyen.gio_khoi_hanh) }}
            </div>
            <div class="trip-card__stop-dot trip-card__stop-dot--from"></div>
            <div class="trip-card__stop-name">
              {{ chuyen.tuyen_duong?.diem_bat_dau }}
            </div>
          </div>

          <div class="trip-card__duration">
            <div class="trip-card__duration-line"></div>
            <span class="trip-card__duration-text">
              <span class="material-symbols-outlined">schedule</span>
              ~{{ chuyen.tuyen_duong?.gio_du_kien || "?" }}h
            </span>
            <div class="trip-card__duration-line"></div>
          </div>

          <div class="trip-card__stop">
            <div class="trip-card__stop-time">
              {{ calcArrivalTime(chuyen.gio_khoi_hanh, chuyen.tuyen_duong?.gio_du_kien) }}
            </div>
            <div class="trip-card__stop-dot trip-card__stop-dot--to"></div>
            <div class="trip-card__stop-name">
              {{ chuyen.tuyen_duong?.diem_ket_thuc }}
            </div>
          </div>
        </div>
      </div>

      <!-- Cột thông tin xe -->
      <div class="trip-card__info">
        <div class="trip-card__details">
          <div class="trip-card__detail-item">
            <span class="material-symbols-outlined">event</span>
            {{ formatFullDate(chuyen.ngay_khoi_hanh) }}
          </div>
          <div class="trip-card__detail-item">
            <span class="material-symbols-outlined">airline_seat_recline_normal</span>
            {{ chuyen.xe?.so_ghe_thuc_te || "?" }} ghế
          </div>
          <div class="trip-card__detail-item">
            <span class="material-symbols-outlined">confirmation_number</span>
            {{ chuyen.xe?.bien_so }}
          </div>
        </div>
      </div>

      <!-- Cột giá + nút đặt -->
      <div class="trip-card__action">
        <div class="trip-card__price">
          {{ formatCurrency(chuyen.tuyen_duong?.gia_ve_co_ban) }}
        </div>
        <button class="trip-card__book-btn" @click.stop="$emit('select', chuyen)">
          Chọn chuyến
          <span class="material-symbols-outlined">arrow_forward</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Di chuyển CSS liên quan đến trip-card từ SearchTripView.vue vào đây */
.trip-card {
  background: #fff;
  border-radius: 20px;
  border: 1px solid #f1f5f9;
  overflow: hidden;
  transition: all 0.25s ease;
  position: relative;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
  cursor: pointer;
}

.trip-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
  border-color: #3b82f6;
}

.trip-card__body {
  display: grid;
  grid-template-columns: 1fr 200px 180px;
  padding: 1.5rem;
  gap: 1.5rem;
}

@media (max-width: 1024px) {
  .trip-card__body {
    grid-template-columns: 1fr 180px;
  }
  .trip-card__info {
    display: none;
  }
}

@media (max-width: 768px) {
  .trip-card__body {
    grid-template-columns: 1fr;
    gap: 1rem;
    padding: 1.25rem;
  }
}

.trip-card__operator {
  display: flex;
  items-center: center;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.trip-card__operator-icon {
  color: #3b82f6;
  font-size: 1.25rem;
}

.trip-card__operator-name {
  font-weight: 800;
  color: #0f172a;
  font-size: 1rem;
}

.trip-card__vehicle-badge {
  background: #f1f5f9;
  color: #64748b;
  padding: 0.25rem 0.75rem;
  border-radius: 50px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.trip-card__route {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.trip-card__stop {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-w: 80px;
}

.trip-card__stop-time {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.5rem;
}

.trip-card__stop-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid #fff;
  position: relative;
  z-index: 2;
  margin-bottom: 0.5rem;
}

.trip-card__stop-dot--from {
  background: #3b82f6;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
}

.trip-card__stop-dot--to {
  background: #ef4444;
  box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15);
}

.trip-card__stop-name {
  font-size: 0.85rem;
  font-weight: 700;
  color: #475569;
  text-align: center;
}

.trip-card__duration {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding-top: 2rem;
}

.trip-card__duration-line {
  height: 2px;
  background: #e2e8f0;
  width: 100%;
  border-radius: 2px;
}

.trip-card__duration-text {
  font-size: 0.75rem;
  color: #94a3b8;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin: 0.5rem 0;
}

.trip-card__details {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.trip-card__detail-item {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  font-size: 0.85rem;
  color: #64748b;
  font-weight: 600;
}

.trip-card__detail-item .material-symbols-outlined {
  font-size: 1.15rem;
  color: #94a3b8;
}

.trip-card__action {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: flex-end;
  gap: 1rem;
  border-left: 1px dashed #e2e8f0;
  padding-left: 1.5rem;
}

@media (max-width: 768px) {
  .trip-card__action {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    border-left: none;
    border-top: 1px dashed #e2e8f0;
    padding-left: 0;
    padding-top: 1rem;
  }
}

.trip-card__price {
  font-size: 1.5rem;
  font-weight: 900;
  color: #3b82f6;
  letter-spacing: -0.02em;
}

.trip-card__book-btn {
  background: #0f172a;
  color: #fff;
  border: none;
  padding: 0.75rem 1.25rem;
  border-radius: 14px;
  font-weight: 700;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
  width: 100%;
  justify-content: center;
}

.trip-card__book-btn:hover {
  background: #3b82f6;
  transform: scale(1.02);
}
</style>
