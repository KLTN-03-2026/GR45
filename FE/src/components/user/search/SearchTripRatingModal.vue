<script setup>
import { computed } from "vue";

const props = defineProps({
  show: Boolean,
  selectedRatingGroup: Object,
});

const emit = defineEmits(["close"]);

const clampRatingScore = (score) => {
  if (score == null) return 0;
  return Math.min(5, Math.max(0, Math.round(score)));
};

const subRatingStars = (score) => {
  return score ? Math.round(score) : 0;
};

const subRatingLabel = (score) => {
  return score ? `${score}/5` : "N/A";
};

const getRatingTripRoute = (rating) => {
  return (
    rating?.ve?.chuyen_xe?.tuyen_duong?.ten_tuyen_duong ||
    "Hà Nội - Hải Phòng (Sửa)"
  );
};

const getRatingTripMeta = (rating) => {
  const date = rating?.ve?.chuyen_xe?.ngay_khoi_hanh || "2026-04-18";
  const time = rating?.ve?.chuyen_xe?.gio_khoi_hanh?.substring(0, 5) || "08:00";
  return `${date} • ${time}`;
};
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div
        v-if="show && selectedRatingGroup"
        class="trip-rating-detail-overlay"
        @click.self="$emit('close')"
      >
        <div class="trip-rating-detail-modal">
          <div class="trip-rating-detail-header">
            <h3>Chi tiết đánh giá</h3>
            <button type="button" @click="$emit('close')">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          <div class="trip-rating-detail-body">
            <div class="trip-rating-detail-top">
              <span class="trip-rating-detail-name">
                {{ selectedRatingGroup?.name }}
              </span>
              <div class="trip-rating-detail-stars-row">
                <span
                  v-for="star in 5"
                  :key="`detail-main-${star}`"
                  class="trip-rating-detail-star"
                  :class="{
                    'trip-rating-detail-star--on':
                      star <= clampRatingScore(selectedRatingGroup?.avgScore),
                  }"
                  aria-hidden="true"
                  >★</span
                >
                <span class="trip-rating-detail-score">{{
                  selectedRatingGroup?.avgScore
                }}/5</span>
              </div>
            </div>
            <div class="trip-rating-detail-scroll-list">
              <div
                v-for="rating in selectedRatingGroup?.ratings || []"
                :key="rating.id"
                class="trip-rating-detail-item"
              >
                <div class="trip-rating-detail-trip">
                  <div class="trip-rating-detail-trip-title">Chuyến xe</div>
                  <div class="trip-rating-detail-trip-route">
                    {{ getRatingTripRoute(rating) }}
                  </div>
                  <div class="trip-rating-detail-trip-meta">
                    {{ getRatingTripMeta(rating) }}
                  </div>
                </div>
                <div class="trip-rating-detail-stars-row">
                  <span
                    v-for="star in 5"
                    :key="`rate-${rating.id}-${star}`"
                    class="trip-rating-detail-star trip-rating-detail-star--sm"
                    :class="{
                      'trip-rating-detail-star--on':
                        star <= clampRatingScore(rating?.diem_so),
                    }"
                    aria-hidden="true"
                    >★</span
                  >
                  <strong>{{ clampRatingScore(rating?.diem_so) }}/5</strong>
                </div>
                <div class="trip-rating-detail-grid">
                  <div class="trip-rating-detail-metric">
                    <span class="trip-rating-detail-metric-label">Dịch vụ</span>
                    <div class="trip-rating-detail-metric-stars">
                      <span
                        v-for="star in 5"
                        :key="`dv-${rating.id}-${star}`"
                        class="trip-rating-detail-star trip-rating-detail-star--sm"
                        :class="{
                          'trip-rating-detail-star--on':
                            star <= subRatingStars(rating?.diem_dich_vu),
                        }"
                        aria-hidden="true"
                        >★</span
                      >
                      <strong>{{ subRatingLabel(rating?.diem_dich_vu) }}</strong>
                    </div>
                  </div>
                  <div class="trip-rating-detail-metric">
                    <span class="trip-rating-detail-metric-label">An toàn</span>
                    <div class="trip-rating-detail-metric-stars">
                      <span
                        v-for="star in 5"
                        :key="`at-${rating.id}-${star}`"
                        class="trip-rating-detail-star trip-rating-detail-star--sm"
                        :class="{
                          'trip-rating-detail-star--on':
                            star <= subRatingStars(rating?.diem_an_toan),
                        }"
                        aria-hidden="true"
                        >★</span
                      >
                      <strong>{{ subRatingLabel(rating?.diem_an_toan) }}</strong>
                    </div>
                  </div>
                  <div class="trip-rating-detail-metric">
                    <span class="trip-rating-detail-metric-label">Sạch sẽ</span>
                    <div class="trip-rating-detail-metric-stars">
                      <span
                        v-for="star in 5"
                        :key="`ss-${rating.id}-${star}`"
                        class="trip-rating-detail-star trip-rating-detail-star--sm"
                        :class="{
                          'trip-rating-detail-star--on':
                            star <= subRatingStars(rating?.diem_sach_se),
                        }"
                        aria-hidden="true"
                        >★</span
                      >
                      <strong>{{ subRatingLabel(rating?.diem_sach_se) }}</strong>
                    </div>
                  </div>
                  <div class="trip-rating-detail-metric">
                    <span class="trip-rating-detail-metric-label">Thái độ</span>
                    <div class="trip-rating-detail-metric-stars">
                      <span
                        v-for="star in 5"
                        :key="`td-${rating.id}-${star}`"
                        class="trip-rating-detail-star trip-rating-detail-star--sm"
                        :class="{
                          'trip-rating-detail-star--on':
                            star <= subRatingStars(rating?.diem_thai_do),
                        }"
                        aria-hidden="true"
                        >★</span
                      >
                      <strong>{{ subRatingLabel(rating?.diem_thai_do) }}</strong>
                    </div>
                  </div>
                </div>
                <p class="trip-rating-detail-note">{{ rating?.noi_dung || "Không có nhận xét." }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* CSS cho Rating Detail Modal */
.trip-rating-detail-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(8px);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.trip-rating-detail-modal {
  background: #fff;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  border-radius: 24px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.trip-rating-detail-header {
  padding: 1.5rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f1f5f9;
}

.trip-rating-detail-header h3 {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
}

.trip-rating-detail-header button {
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  transition: color 0.2s;
}

.trip-rating-detail-body {
  padding: 2rem;
  overflow-y: auto;
}

.trip-rating-detail-top {
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 2px dashed #f1f5f9;
}

.trip-rating-detail-name {
  display: block;
  font-size: 1.5rem;
  font-weight: 900;
  color: #0f172a;
  margin-bottom: 0.5rem;
}

.trip-rating-detail-stars-row {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.trip-rating-detail-star {
  font-size: 1.25rem;
  color: #e2e8f0;
}

.trip-rating-detail-star--on {
  color: #fbbf24;
}

.trip-rating-detail-star--sm {
  font-size: 1rem;
}

.trip-rating-detail-score {
  margin-left: 0.75rem;
  font-weight: 800;
  color: #1e293b;
  font-size: 1.15rem;
}

.trip-rating-detail-scroll-list {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}

.trip-rating-detail-item {
  padding: 1.5rem;
  background: #f8fafc;
  border-radius: 20px;
  border: 1px solid #f1f5f9;
}

.trip-rating-detail-trip {
  margin-bottom: 1.25rem;
}

.trip-rating-detail-trip-title {
  font-size: 0.7rem;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.25rem;
}

.trip-rating-detail-trip-route {
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.15rem;
}

.trip-rating-detail-trip-meta {
  font-size: 0.85rem;
  color: #94a3b8;
  font-weight: 600;
}

.trip-rating-detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 1.25rem;
  padding-top: 1.25rem;
  border-top: 1px solid #e2e8f0;
}

.trip-rating-detail-metric {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.trip-rating-detail-metric-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #64748b;
}

.trip-rating-detail-metric-stars {
  display: flex;
  align-items: center;
  gap: 0.15rem;
}

.trip-rating-detail-metric-stars strong {
  margin-left: 0.5rem;
  font-size: 0.8rem;
  color: #1e293b;
}

.trip-rating-detail-note {
  margin-top: 1.25rem;
  font-size: 0.9rem;
  color: #475569;
  line-height: 1.6;
  font-style: italic;
  padding-left: 1rem;
  border-left: 3px solid #cbd5e1;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
