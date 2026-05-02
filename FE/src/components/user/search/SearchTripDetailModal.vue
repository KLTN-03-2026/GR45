<script setup>
import { formatCurrency, formatTime, calcArrivalTime, formatFullDate } from "@/utils/format";

const props = defineProps({
  show: Boolean,
  selectedTrip: Object,
  ratingLoading: Boolean,
  tripHeaderAvgScore: [Number, String],
  tripHeaderTotalRatings: Number,
  tripHeaderStarFill: Number,
  groupedRatings: Array,
  detailRatings: Array,
});

const emit = defineEmits(["close", "book", "open-rating"]);

const clampRatingScore = (score) => {
  if (score == null) return 0;
  return Math.min(5, Math.max(0, Math.round(score)));
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div
        v-if="show && selectedTrip"
        class="trip-modal-overlay"
        @click.self="$emit('close')"
      >
        <div class="trip-modal">
          <!-- Header Modal -->
          <div class="trip-modal__header">
            <div class="trip-modal__header-text">
              <h2 class="trip-modal__title">Chi tiết chuyến xe</h2>
              <span class="trip-modal__route-badge">
                {{ selectedTrip.tuyen_duong?.diem_bat_dau }} →
                {{ selectedTrip.tuyen_duong?.diem_ket_thuc }}
              </span>
            </div>
            <div class="trip-modal__header-rating" aria-live="polite">
              <template v-if="ratingLoading">
                <span class="trip-modal__header-rating-loading">Đang tải…</span>
              </template>
              <template v-else-if="tripHeaderTotalRatings > 0 && tripHeaderAvgScore != null">
                <div class="trip-modal__header-rating-stars">
                  <span
                    v-for="star in 5"
                    :key="`hdr-star-${star}`"
                    class="trip-modal__header-star"
                    :class="{
                      'trip-modal__header-star--on': star <= tripHeaderStarFill,
                    }"
                    aria-hidden="true"
                    >★</span
                  >
                </div>
                <div class="trip-modal__header-rating-meta">
                  <span class="trip-modal__header-rating-score">{{ tripHeaderAvgScore }}/5</span>
                  <span class="trip-modal__header-rating-count">{{ tripHeaderTotalRatings }} đánh giá</span>
                </div>
              </template>
              <template v-else>
                <span class="trip-modal__header-rating-empty">Chưa có đánh giá</span>
              </template>
            </div>
            <button class="trip-modal__close" @click="$emit('close')">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Body Modal -->
          <div class="trip-modal__body">
            <div v-if="ratingLoading || detailRatings.length" class="trip-modal__ratings-top">
              <div v-if="ratingLoading" class="trip-modal__ratings-top-loading">
                Đang tải đánh giá...
              </div>
              <div v-else class="trip-modal__rating-list">
                <div
                  v-for="group in groupedRatings"
                  :key="group.key"
                  class="trip-modal__rating-item"
                  @click="$emit('open-rating', group)"
                >
                  <div class="trip-modal__rating-head">
                    <span class="trip-modal__rating-name">
                      {{ group.name }}
                    </span>
                    <span class="trip-modal__rating-count">{{ group.total }} lượt</span>
                  </div>
                  <div class="trip-modal__rating-stars-row">
                    <span
                      v-for="star in 5"
                      :key="`${group.key}-list-${star}`"
                      class="trip-modal__rating-star"
                      :class="{
                        'trip-modal__rating-star--on': star <= clampRatingScore(group.avgScore),
                      }"
                      aria-hidden="true"
                      >★</span
                    >
                    <span class="trip-modal__rating-score">{{ group.avgScore }}/5</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Nhà xe -->
            <div class="trip-modal__section">
              <div class="trip-modal__section-icon">
                <span class="material-symbols-outlined">apartment</span>
              </div>
              <div>
                <h3 class="trip-modal__section-title">Nhà xe</h3>
                <p class="trip-modal__section-value">
                  {{ selectedTrip.tuyen_duong?.nha_xe?.ten_nha_xe }}
                </p>
                <p class="trip-modal__section-sub">
                  SĐT: {{ selectedTrip.tuyen_duong?.nha_xe?.so_dien_thoai }}
                </p>
              </div>
            </div>

            <!-- Lộ trình -->
            <div class="trip-modal__section">
              <div class="trip-modal__section-icon">
                <span class="material-symbols-outlined">route</span>
              </div>
              <div>
                <h3 class="trip-modal__section-title">Lộ trình</h3>
                <p class="trip-modal__section-value">
                  {{ selectedTrip.tuyen_duong?.ten_tuyen_duong }}
                </p>
                <p class="trip-modal__section-sub">
                  Quãng đường: {{ selectedTrip.tuyen_duong?.quang_duong }} km
                </p>
              </div>
            </div>

            <!-- Timeline giờ -->
            <div class="trip-modal__timeline">
              <div class="trip-modal__tl-point">
                <div class="trip-modal__tl-dot trip-modal__tl-dot--from"></div>
                <div>
                  <div class="trip-modal__tl-time">
                    {{ formatTime(selectedTrip.gio_khoi_hanh) }}
                  </div>
                  <div class="trip-modal__tl-place">
                    {{ selectedTrip.tuyen_duong?.diem_bat_dau }}
                  </div>
                </div>
              </div>
              <div class="trip-modal__tl-line">
                <span class="trip-modal__tl-duration">~{{ selectedTrip.tuyen_duong?.gio_du_kien }}h di chuyển</span>
              </div>
              <div class="trip-modal__tl-point">
                <div class="trip-modal__tl-dot trip-modal__tl-dot--to"></div>
                <div>
                  <div class="trip-modal__tl-time">
                    {{ calcArrivalTime(selectedTrip.gio_khoi_hanh, selectedTrip.tuyen_duong?.gio_du_kien) }}
                  </div>
                  <div class="trip-modal__tl-place">
                    {{ selectedTrip.tuyen_duong?.diem_ket_thuc }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Thông tin chi tiết -->
            <div class="trip-modal__grid">
              <div class="trip-modal__grid-item">
                <span class="material-symbols-outlined">calendar_month</span>
                <div>
                  <span class="trip-modal__grid-label">Ngày khởi hành</span>
                  <span class="trip-modal__grid-value">{{ formatFullDate(selectedTrip.ngay_khoi_hanh) }}</span>
                </div>
              </div>
              <div class="trip-modal__grid-item">
                <span class="material-symbols-outlined">directions_bus</span>
                <div>
                  <span class="trip-modal__grid-label">Xe</span>
                  <span class="trip-modal__grid-value">{{ selectedTrip.xe?.ten_xe }}</span>
                </div>
              </div>
              <div class="trip-modal__grid-item">
                <span class="material-symbols-outlined">pin</span>
                <div>
                  <span class="trip-modal__grid-label">Biển số</span>
                  <span class="trip-modal__grid-value">{{ selectedTrip.xe?.bien_so }}</span>
                </div>
              </div>
              <div class="trip-modal__grid-item">
                <span class="material-symbols-outlined">airline_seat_recline_normal</span>
                <div>
                  <span class="trip-modal__grid-label">Số ghế</span>
                  <span class="trip-modal__grid-value">{{ selectedTrip.xe?.so_ghe_thuc_te }} ghế</span>
                </div>
              </div>
            </div>

            <!-- Ghi chú -->
            <div v-if="selectedTrip.tuyen_duong?.ghi_chu" class="trip-modal__note">
              <span class="material-symbols-outlined">info</span>
              <span>{{ selectedTrip.tuyen_duong?.ghi_chu }}</span>
            </div>
          </div>

          <!-- Footer Modal -->
          <div class="trip-modal__footer">
            <div class="trip-modal__footer-price">
              <span class="trip-modal__footer-price-label">Giá vé</span>
              <span class="trip-modal__footer-price-value">{{
                formatCurrency(selectedTrip.tuyen_duong?.gia_ve_co_ban)
              }}</span>
            </div>
            <button class="trip-modal__footer-btn" @click="$emit('book')">
              <span class="material-symbols-outlined">confirmation_number</span>
              Đặt vé ngay
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* CSS cho Trip Detail Modal */
.trip-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(8px);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.trip-modal {
  background: #fff;
  width: 100%;
  max-width: 700px;
  max-height: 90vh;
  border-radius: 32px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  animation: modal-slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modal-slide-up {
  from {
    opacity: 0;
    transform: translateY(20px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.trip-modal__header {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
}

.trip-modal__title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.25rem;
}

.trip-modal__route-badge {
  font-size: 0.75rem;
  font-weight: 700;
  color: #64748b;
  background: #f1f5f9;
  padding: 0.25rem 0.75rem;
  border-radius: 50px;
}

.trip-modal__close {
  background: #f1f5f9;
  border: none;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #64748b;
  transition: all 0.2s;
}

.trip-modal__close:hover {
  background: #e2e8f0;
  color: #0f172a;
}

.trip-modal__body {
  padding: 2rem;
  overflow-y: auto;
  flex: 1;
}

.trip-modal__section {
  display: flex;
  gap: 1.25rem;
  margin-bottom: 2rem;
}

.trip-modal__section-icon {
  width: 48px;
  height: 48px;
  background: #eff6ff;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #3b82f6;
  flex-shrink: 0;
}

.trip-modal__section-title {
  font-size: 0.75rem;
  font-weight: 800;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.25rem;
}

.trip-modal__section-value {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1e293b;
}

.trip-modal__section-sub {
  font-size: 0.85rem;
  color: #64748b;
  font-weight: 600;
}

.trip-modal__timeline {
  background: #f8fafc;
  padding: 1.5rem;
  border-radius: 20px;
  border: 1px solid #f1f5f9;
  margin-bottom: 2rem;
}

.trip-modal__tl-point {
  display: flex;
  gap: 1.25rem;
  align-items: flex-start;
}

.trip-modal__tl-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 3px solid #fff;
  margin-top: 0.25rem;
  flex-shrink: 0;
}

.trip-modal__tl-dot--from {
  background: #3b82f6;
}

.trip-modal__tl-dot--to {
  background: #ef4444;
}

.trip-modal__tl-time {
  font-size: 1.1rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.15rem;
}

.trip-modal__tl-place {
  font-size: 0.85rem;
  font-weight: 700;
  color: #64748b;
}

.trip-modal__tl-line {
  margin: 0.5rem 0 0.5rem 5px;
  padding-left: 2rem;
  border-left: 2px dashed #cbd5e1;
}

.trip-modal__tl-duration {
  font-size: 0.75rem;
  font-weight: 700;
  color: #94a3b8;
  padding: 0.75rem 0;
  display: block;
}

.trip-modal__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.trip-modal__grid-item {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.trip-modal__grid-item .material-symbols-outlined {
  color: #94a3b8;
  font-size: 1.5rem;
}

.trip-modal__grid-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 800;
  color: #94a3b8;
  text-transform: uppercase;
  margin-bottom: 0.15rem;
}

.trip-modal__grid-value {
  font-size: 0.95rem;
  font-weight: 700;
  color: #1e293b;
}

.trip-modal__note {
  background: #fffbeb;
  border: 1px solid #fef3c7;
  padding: 1rem 1.25rem;
  border-radius: 16px;
  display: flex;
  gap: 0.75rem;
  font-size: 0.85rem;
  color: #92400e;
  font-weight: 600;
  line-height: 1.5;
}

.trip-modal__note .material-symbols-outlined {
  font-size: 1.25rem;
  color: #f59e0b;
}

.trip-modal__footer {
  padding: 1.5rem 2rem;
  background: #f8fafc;
  border-top: 1px solid #f1f5f9;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.trip-modal__footer-price-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
}

.trip-modal__footer-price-value {
  font-size: 1.5rem;
  font-weight: 900;
  color: #3b82f6;
}

.trip-modal__footer-btn {
  background: #3b82f6;
  color: #fff;
  border: none;
  padding: 1rem 2rem;
  border-radius: 18px;
  font-weight: 800;
  font-size: 1rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
}

.trip-modal__footer-btn:hover {
  background: #2563eb;
  transform: translateY(-2px);
}

.trip-modal__header-rating {
  margin-left: 1.5rem;
  padding: 0.5rem 1rem;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.trip-modal__header-rating-stars {
  display: flex;
  gap: 1px;
}

.trip-modal__header-star {
  color: #e2e8f0;
  font-size: 0.9rem;
}

.trip-modal__header-star--on {
  color: #fbbf24;
}

.trip-modal__header-rating-score {
  font-weight: 800;
  color: #0f172a;
  font-size: 0.9rem;
}

.trip-modal__header-rating-count {
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 600;
  margin-left: 0.5rem;
}

.trip-modal__rating-list {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 2px dashed #f1f5f9;
}

@media (max-width: 640px) {
  .trip-modal__rating-list {
    grid-template-columns: 1fr;
  }
}

.trip-modal__rating-item {
  background: #fff;
  border: 1px solid #f1f5f9;
  padding: 1rem;
  border-radius: 16px;
  cursor: pointer;
  transition: all 0.2s;
}

.trip-modal__rating-item:hover {
  border-color: #3b82f6;
  background: #f8fafc;
}

.trip-modal__rating-head {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.trip-modal__rating-name {
  font-size: 0.85rem;
  font-weight: 700;
  color: #1e293b;
}

.trip-modal__rating-count {
  font-size: 0.7rem;
  color: #94a3b8;
  font-weight: 600;
}

.trip-modal__rating-star {
  color: #e2e8f0;
  font-size: 0.8rem;
}

.trip-modal__rating-star--on {
  color: #fbbf24;
}

.trip-modal__rating-score {
  margin-left: 0.5rem;
  font-size: 0.8rem;
  font-weight: 700;
  color: #475569;
}

.modal-fade-leave-active {
  transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
