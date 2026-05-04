<script setup>
import {
  computed,
  onMounted,
  onUnmounted,
  reactive,
  ref,
  watch,
  provide,
} from "vue";
// provide tái sử dụng cho component con
import { useRoute, useRouter } from "vue-router";
import clientApi from "@/api/clientApi";
import ClientHeader from "@/components/layout/ClientHeader.vue";
import ClientFooter from "@/components/layout/ClientFooter.vue";

const router = useRouter();
const route = useRoute();
const pendingRatings = ref([]);
const showRatingPopup = ref(false);
const showDetailedRatingModal = ref(false);
const loadingPendingRatings = ref(false);
const submitting = ref(false);
const hoverRating = ref(0);
const dismissedTripId = ref(null);
const pollingId = ref(null);

const draft = reactive({
  diem_so: 5,
  noi_dung: "",
});

const detailedDraft = reactive({
  diem_so: 5,
  diem_dich_vu: 5,
  diem_an_toan: 5,
  diem_sach_se: 5,
  diem_thai_do: 5,
  noi_dung: "",
});
const selectedTrip = ref(null);

const popupTrip = computed(() =>
  pendingRatings.value.length > 0 ? pendingRatings.value[0] : null,
);

const isClientLoggedIn = () => {
  const token = localStorage.getItem("auth.client.token");
  if (!token) return false;
  localStorage.setItem("auth.active_role", "client");
  return true;
};

const getRatingText = (stars) => {
  const texts = {
    1: "Rất tệ",
    2: "Tệ",
    3: "Bình thường",
    4: "Tốt",
    5: "Rất tốt",
  };
  return texts[stars] || "";
};

const maVeDisplay = (trip) => {
  const list = trip?.ma_ve_list;
  if (!Array.isArray(list) || !list.length) return null;
  if (list.length === 1) return String(list[0]);
  if (list.length === 2) return `${list[0]}, ${list[1]}`;
  return `${list[0]}, ${list[1]} +${list.length - 2}`;
};

const fetchPendingRatings = async (options = {}) => {
  const forcePopupOnRouteChange = Boolean(options.forcePopupOnRouteChange);
  if (!isClientLoggedIn() || loadingPendingRatings.value) return;

  loadingPendingRatings.value = true;
  try {
    const res = await clientApi.getPendingRatings();
    pendingRatings.value = Array.isArray(res?.data) ? res.data : [];

    const tripId = popupTrip.value?.trip_id;
    if (
      tripId &&
      (forcePopupOnRouteChange || tripId !== dismissedTripId.value)
    ) {
      showRatingPopup.value = true;
    } else if (!tripId) {
      showRatingPopup.value = false;
    }
  } catch {
    pendingRatings.value = [];
  } finally {
    loadingPendingRatings.value = false;
  }
};

const submitQuickRating = async () => {
  if (!popupTrip.value || submitting.value) return;

  submitting.value = true;
  try {
    await clientApi.submitRating({
      trip_id: popupTrip.value.trip_id,
      ma_ve_list: popupTrip.value.ma_ve_list || [],
      diem_so: Number(draft.diem_so) || 5,
      noi_dung: draft.noi_dung || "",
    });

    dismissedTripId.value = popupTrip.value.trip_id;
    showRatingPopup.value = false;
    draft.diem_so = 5;
    draft.noi_dung = "";
    await fetchPendingRatings();
  } finally {
    submitting.value = false;
  }
};

const submitDetailedRating = async () => {
  if (!selectedTrip.value || submitting.value) return;

  submitting.value = true;
  try {
    await clientApi.submitRating({
      trip_id: selectedTrip.value.trip_id,
      ma_ve_list: selectedTrip.value.ma_ve_list || [],
      diem_so: Number(detailedDraft.diem_so) || 5,
      diem_dich_vu:
        Number(detailedDraft.diem_dich_vu) ||
        Number(detailedDraft.diem_so) ||
        5,
      diem_an_toan:
        Number(detailedDraft.diem_an_toan) ||
        Number(detailedDraft.diem_so) ||
        5,
      diem_sach_se:
        Number(detailedDraft.diem_sach_se) ||
        Number(detailedDraft.diem_so) ||
        5,
      diem_thai_do:
        Number(detailedDraft.diem_thai_do) ||
        Number(detailedDraft.diem_so) ||
        5,
      noi_dung: detailedDraft.noi_dung || "",
    });

    dismissedTripId.value = selectedTrip.value.trip_id;
    showDetailedRatingModal.value = false;
    selectedTrip.value = null;
    await fetchPendingRatings();
  } finally {
    submitting.value = false;
  }
};

const handleClosePopup = () => {
  if (popupTrip.value?.trip_id) {
    dismissedTripId.value = popupTrip.value.trip_id;
  }
  showRatingPopup.value = false;
};

const goToDetailedRating = () => {
  if (!popupTrip.value) return;
  openDetailedRatingFromChild(popupTrip.value);
};

const openDetailedRatingFromChild = (tripData) => {
  selectedTrip.value = { ...tripData };
  detailedDraft.diem_so = Number(draft.diem_so) || 5;
  detailedDraft.diem_dich_vu = Number(draft.diem_so) || 5;
  detailedDraft.diem_an_toan = Number(draft.diem_so) || 5;
  detailedDraft.diem_sach_se = Number(draft.diem_so) || 5;
  detailedDraft.diem_thai_do = Number(draft.diem_so) || 5;
  detailedDraft.noi_dung = draft.noi_dung || "";
  showRatingPopup.value = false;
  showDetailedRatingModal.value = true;
};

provide("openDetailedRating", openDetailedRatingFromChild);

const anyRatingModalOpen = computed(
  () => showRatingPopup.value || showDetailedRatingModal.value,
);

const onPendingModalKeydown = (e) => {
  if (e.key !== "Escape") return;
  if (showDetailedRatingModal.value) {
    showDetailedRatingModal.value = false;
    return;
  }
  if (showRatingPopup.value) {
    handleClosePopup();
  }
};

watch(anyRatingModalOpen, (open) => {
  document.body.style.overflow = open ? "hidden" : "";
  if (open) document.addEventListener("keydown", onPendingModalKeydown);
  else document.removeEventListener("keydown", onPendingModalKeydown);
});

watch(
  // () => route.fullPath,
  // () => {
  //   if (showDetailedRatingModal.value) return;
  //   fetchPendingRatings({ forcePopupOnRouteChange: true });
  // },
  () => route.path,
  (newPath) => {
    if (newPath === "/profile" || newPath === "/lich-su-dat-ve") {
      if (showDetailedRatingModal.value) return;
      fetchPendingRatings({ forcePopupOnRouteChange: true });
    }
  },
);

onMounted(() => {
  fetchPendingRatings();
  // pollingId.value = window.setInterval(fetchPendingRatings, 15000);
});

onUnmounted(() => {
  document.removeEventListener("keydown", onPendingModalKeydown);
  document.body.style.overflow = "";
  if (pollingId.value) {
    clearInterval(pollingId.value);
    pollingId.value = null;
  }
});
</script>

<template>
  <div class="default-layout">
    <ClientHeader />
    <main class="main-content">
      <RouterView />
    </main>
    <ClientFooter />

    <Teleport to="body">
      <Transition name="rt-rating-modal-fade">
        <div
          v-if="showRatingPopup"
          class="trip-rating-detail-overlay"
          role="presentation"
          @click.self="handleClosePopup"
        >
          <div
            class="trip-rating-detail-modal trip-rating-detail-modal--pending"
            role="dialog"
            aria-modal="true"
            aria-labelledby="pending-rating-quick-title"
          >
            <div class="trip-rating-detail-header">
              <h3 id="pending-rating-quick-title">
                Đánh giá chuyến đi của bạn
              </h3>
              <button
                type="button"
                class="trip-rating-detail-close"
                aria-label="Đóng"
                @click="handleClosePopup"
              >
                <svg
                  width="22"
                  height="22"
                  viewBox="0 0 24 24"
                  fill="none"
                  aria-hidden="true"
                >
                  <path
                    d="M18 6L6 18M6 6L18 18"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </div>
            <div class="trip-rating-detail-body">
              <div
                v-if="loadingPendingRatings"
                class="trip-rating-detail-loading"
              >
                Đang tải...
              </div>
              <template v-else-if="popupTrip">
                <p class="trip-rating-detail-hint">
                  Bạn có một chuyến đi đã hoàn thành nhưng chưa được đánh giá.
                  Hãy chia sẻ trải nghiệm của bạn!
                </p>

                <div class="trip-rating-detail-trip">
                  <div class="trip-rating-detail-trip-title">Chuyến xe</div>
                  <div class="trip-rating-detail-trip-route">
                    {{ popupTrip.diem_bat_dau }} → {{ popupTrip.diem_ket_thuc }}
                  </div>
                  <div class="trip-rating-detail-trip-meta">
                    <span v-if="popupTrip.ten_tuyen_duong">{{
                      popupTrip.ten_tuyen_duong
                    }}</span>
                    <span v-else>Chuyến #{{ popupTrip.trip_id }}</span>
                    <span v-if="popupTrip.ten_nha_xe">
                      · {{ popupTrip.ten_nha_xe }}</span
                    >
                  </div>
                  <div class="trip-rating-detail-trip-meta">
                    <span>Ngày: {{ popupTrip.ngay_khoi_hanh }}</span>
                    <span> · Giờ: {{ popupTrip.gio_khoi_hanh }}</span>
                    <span v-if="popupTrip.bien_so">
                      · BKS: {{ popupTrip.bien_so }}</span
                    >
                  </div>
                  <div
                    v-if="
                      popupTrip.ten_xe ||
                      popupTrip.quang_duong != null ||
                      popupTrip.trang_thai_chuyen
                    "
                    class="trip-rating-detail-trip-meta"
                  >
                    <span v-if="popupTrip.ten_xe"
                      >Xe: {{ popupTrip.ten_xe }}</span
                    >
                    <span
                      v-if="
                        popupTrip.quang_duong != null &&
                        popupTrip.quang_duong !== ''
                      "
                    >
                      <template v-if="popupTrip.ten_xe"> · </template
                      >{{ popupTrip.quang_duong }} km
                    </span>
                    <span v-if="popupTrip.trang_thai_chuyen">
                      <template
                        v-if="
                          popupTrip.ten_xe ||
                          (popupTrip.quang_duong != null &&
                            popupTrip.quang_duong !== '')
                        "
                      >
                        ·
                      </template>
                      Trạng thái: {{ popupTrip.trang_thai_chuyen }}
                    </span>
                  </div>
                  <div class="trip-rating-detail-trip-meta">
                    <span>{{ popupTrip.ticket_count }} vé</span>
                    <span v-if="maVeDisplay(popupTrip)">
                      · Mã vé:
                      <strong class="trip-rating-detail-mono">{{
                        maVeDisplay(popupTrip)
                      }}</strong>
                    </span>
                    <span> · ID chuyến: #{{ popupTrip.trip_id }}</span>
                  </div>
                </div>

                <div class="pending-rating-field">
                  <label class="pending-rating-label">Đánh giá nhanh *</label>
                  <div
                    class="trip-rating-detail-stars-row pending-rating-stars"
                  >
                    <button
                      v-for="star in 5"
                      :key="`layout-star-${star}`"
                      type="button"
                      class="trip-rating-detail-star pending-rating-star-btn"
                      :class="{
                        'trip-rating-detail-star--on':
                          (hoverRating || draft.diem_so) >= star,
                      }"
                      @click="draft.diem_so = star"
                      @mouseenter="hoverRating = star"
                      @mouseleave="hoverRating = 0"
                    >
                      ★
                    </button>
                  </div>
                  <div class="pending-rating-caption">
                    {{ getRatingText(draft.diem_so) }}
                  </div>
                </div>

                <div class="pending-rating-field">
                  <label class="pending-rating-label"
                    >Nhận xét (tùy chọn)</label
                  >
                  <textarea
                    v-model="draft.noi_dung"
                    rows="3"
                    class="trip-rating-detail-textarea"
                    placeholder="Chia sẻ trải nghiệm của bạn..."
                    maxlength="300"
                  />
                </div>
              </template>
            </div>
            <div
              v-if="popupTrip && !loadingPendingRatings"
              class="trip-rating-detail-footer"
            >
              <button
                type="button"
                class="trip-rating-detail-btn trip-rating-detail-btn--ghost"
                @click="handleClosePopup"
              >
                Để sau
              </button>
              <button
                type="button"
                class="trip-rating-detail-btn trip-rating-detail-btn--secondary"
                @click="goToDetailedRating"
              >
                Đánh giá chi tiết
              </button>
              <button
                type="button"
                class="trip-rating-detail-btn trip-rating-detail-btn--primary"
                :disabled="submitting || !popupTrip"
                @click="submitQuickRating"
              >
                {{ submitting ? "Đang gửi..." : "Gửi đánh giá" }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <Teleport to="body">
      <Transition name="rt-rating-modal-fade">
        <div
          v-if="showDetailedRatingModal && selectedTrip"
          class="trip-rating-detail-overlay"
          role="presentation"
          @click.self="showDetailedRatingModal = false"
        >
          <div
            class="trip-rating-detail-modal trip-rating-detail-modal--pending"
            role="dialog"
            aria-modal="true"
            aria-labelledby="pending-rating-detail-title"
          >
            <div class="trip-rating-detail-header">
              <h3 id="pending-rating-detail-title">
                Đánh giá chi tiết chuyến đi
              </h3>
              <button
                type="button"
                class="trip-rating-detail-close"
                aria-label="Đóng"
                @click="showDetailedRatingModal = false"
              >
                <svg
                  width="22"
                  height="22"
                  viewBox="0 0 24 24"
                  fill="none"
                  aria-hidden="true"
                >
                  <path
                    d="M18 6L6 18M6 6L18 18"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </div>
            <div class="trip-rating-detail-body">
              <p class="trip-rating-detail-hint">
                Bạn có một chuyến đi đã hoàn thành nhưng chưa được đánh giá.
                Hãy chia sẻ trải nghiệm của bạn!
              </p>

              <div class="trip-rating-detail-trip">
                <div class="trip-rating-detail-trip-title">Chuyến xe</div>
                <div class="trip-rating-detail-trip-route">
                  {{ selectedTrip.diem_bat_dau }} →
                  {{ selectedTrip.diem_ket_thuc }}
                </div>
                <div class="trip-rating-detail-trip-meta">
                  <span v-if="selectedTrip.ten_tuyen_duong">{{
                    selectedTrip.ten_tuyen_duong
                  }}</span>
                  <span v-else>Chuyến #{{ selectedTrip.trip_id }}</span>
                  <span v-if="selectedTrip.ten_nha_xe">
                    · {{ selectedTrip.ten_nha_xe }}</span
                  >
                </div>
                <div class="trip-rating-detail-trip-meta">
                  <span>Ngày: {{ selectedTrip.ngay_khoi_hanh }}</span>
                  <span> · Giờ: {{ selectedTrip.gio_khoi_hanh }}</span>
                  <span v-if="selectedTrip.bien_so">
                    · BKS: {{ selectedTrip.bien_so }}</span
                  >
                </div>
                <div
                  v-if="
                    selectedTrip.ten_xe ||
                    selectedTrip.quang_duong != null ||
                    selectedTrip.trang_thai_chuyen
                  "
                  class="trip-rating-detail-trip-meta"
                >
                  <span v-if="selectedTrip.ten_xe"
                    >Xe: {{ selectedTrip.ten_xe }}</span
                  >
                  <span
                    v-if="
                      selectedTrip.quang_duong != null &&
                      selectedTrip.quang_duong !== ''
                    "
                  >
                    <template v-if="selectedTrip.ten_xe"> · </template
                    >{{ selectedTrip.quang_duong }} km
                  </span>
                  <span v-if="selectedTrip.trang_thai_chuyen">
                    <template
                      v-if="
                        selectedTrip.ten_xe ||
                        (selectedTrip.quang_duong != null &&
                          selectedTrip.quang_duong !== '')
                      "
                    >
                      ·
                    </template>
                    Trạng thái: {{ selectedTrip.trang_thai_chuyen }}
                  </span>
                </div>
                <div class="trip-rating-detail-trip-meta">
                  <span>{{ selectedTrip.ticket_count }} vé</span>
                  <span v-if="maVeDisplay(selectedTrip)">
                    · Mã vé:
                    <strong class="trip-rating-detail-mono">{{
                      maVeDisplay(selectedTrip)
                    }}</strong>
                  </span>
                  <span> · ID chuyến: #{{ selectedTrip.trip_id }}</span>
                </div>
              </div>

              <div class="pending-rating-grid">
                <label class="pending-rating-label"
                  >Đánh giá tổng thể
                  <div class="trip-rating-detail-stars-row pending-rating-stars">
                    <button
                      v-for="star in 5"
                      :key="`detail-overall-${star}`"
                      type="button"
                      class="trip-rating-detail-star pending-rating-star-btn"
                      :class="{
                        'trip-rating-detail-star--on': detailedDraft.diem_so >= star,
                      }"
                      @click="detailedDraft.diem_so = star"
                    >
                      ★
                    </button>
                  </div>
                </label>
                <label class="pending-rating-label"
                  >Chất lượng dịch vụ
                  <div class="trip-rating-detail-stars-row pending-rating-stars">
                    <button
                      v-for="star in 5"
                      :key="`detail-service-${star}`"
                      type="button"
                      class="trip-rating-detail-star pending-rating-star-btn"
                      :class="{
                        'trip-rating-detail-star--on':
                          detailedDraft.diem_dich_vu >= star,
                      }"
                      @click="detailedDraft.diem_dich_vu = star"
                    >
                      ★
                    </button>
                  </div>
                </label>
                <label class="pending-rating-label"
                  >Độ an toàn
                  <div class="trip-rating-detail-stars-row pending-rating-stars">
                    <button
                      v-for="star in 5"
                      :key="`detail-safety-${star}`"
                      type="button"
                      class="trip-rating-detail-star pending-rating-star-btn"
                      :class="{
                        'trip-rating-detail-star--on':
                          detailedDraft.diem_an_toan >= star,
                      }"
                      @click="detailedDraft.diem_an_toan = star"
                    >
                      ★
                    </button>
                  </div>
                </label>
                <label class="pending-rating-label"
                  >Độ sạch sẽ
                  <div class="trip-rating-detail-stars-row pending-rating-stars">
                    <button
                      v-for="star in 5"
                      :key="`detail-clean-${star}`"
                      type="button"
                      class="trip-rating-detail-star pending-rating-star-btn"
                      :class="{
                        'trip-rating-detail-star--on':
                          detailedDraft.diem_sach_se >= star,
                      }"
                      @click="detailedDraft.diem_sach_se = star"
                    >
                      ★
                    </button>
                  </div>
                </label>
              </div>

              <label class="pending-rating-label rating-label--full"
                >Thái độ phục vụ
                <div class="trip-rating-detail-stars-row pending-rating-stars">
                  <button
                    v-for="star in 5"
                    :key="`detail-attitude-${star}`"
                    type="button"
                    class="trip-rating-detail-star pending-rating-star-btn"
                    :class="{
                      'trip-rating-detail-star--on': detailedDraft.diem_thai_do >= star,
                    }"
                    @click="detailedDraft.diem_thai_do = star"
                  >
                    ★
                  </button>
                </div>
              </label>

              <label class="pending-rating-label pending-rating-label--block"
                >Nhận xét (tùy chọn)
                <textarea
                  v-model="detailedDraft.noi_dung"
                  rows="3"
                  class="trip-rating-detail-textarea"
                  maxlength="500"
                  placeholder="Chia sẻ trải nghiệm của bạn..."
                />
              </label>
            </div>
            <div class="trip-rating-detail-footer">
              <button
                type="button"
                class="trip-rating-detail-btn trip-rating-detail-btn--ghost"
                @click="showDetailedRatingModal = false"
              >
                Hủy
              </button>
              <button
                type="button"
                class="trip-rating-detail-btn trip-rating-detail-btn--primary"
                :disabled="submitting || !selectedTrip"
                @click="submitDetailedRating"
              >
                {{ submitting ? "Đang gửi..." : "Gửi đánh giá" }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.rating-label {
  display: block;
  font-size: 0.82rem;
  font-weight: 700;
  color: #334155;
}

.rating-select {
  margin-top: 0.45rem;
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid #cbd5e1;
  background: #fff;
  padding: 0.62rem 0.8rem;
  font-size: 0.9rem;
  font-weight: 500;
  color: #0f172a;
  transition: all 0.2s ease;
}

.rating-select:hover {
  border-color: #94a3b8;
  background: #f8fafc;
}

.rating-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
  background: #fff;
}

.rt-rating-modal-fade-enter-active {
  transition: opacity 0.25s ease;
}
.rt-rating-modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.rt-rating-modal-fade-enter-from,
.rt-rating-modal-fade-leave-to {
  opacity: 0;
}

.trip-rating-detail-overlay {
  position: fixed;
  inset: 0;
  z-index: 10050;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.trip-rating-detail-modal {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25);
  border: 1px solid #e2e8f0;
  max-height: min(90vh, 720px);
  display: flex;
  flex-direction: column;
}

.trip-rating-detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.trip-rating-detail-header h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 800;
  color: #1e293b;
}

.trip-rating-detail-close {
  border: none;
  background: transparent;
  cursor: pointer;
  color: #64748b;
  padding: 0.2rem;
  line-height: 0;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.trip-rating-detail-close:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.trip-rating-detail-body {
  padding: 0.9rem 1rem 1rem;
  overflow-y: auto;
}

.trip-rating-detail-loading {
  font-size: 0.85rem;
  color: #64748b;
}

.trip-rating-detail-hint {
  margin: 0 0 0.75rem;
  padding: 0.55rem 0.65rem;
  border: 1px solid #bfdbfe;
  background: #eff6ff;
  border-radius: 10px;
  font-size: 0.78rem;
  color: #1e40af;
  line-height: 1.45;
}

.trip-rating-detail-trip {
  margin-bottom: 0.85rem;
  padding: 0.65rem 0.75rem;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 0.78rem;
  color: #334155;
}

.trip-rating-detail-trip-title {
  font-size: 0.65rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
  margin-bottom: 0.35rem;
}

.trip-rating-detail-trip-route {
  font-weight: 800;
  color: #1e40af;
  font-size: 0.82rem;
  margin-bottom: 0.25rem;
}

.trip-rating-detail-trip-meta {
  line-height: 1.45;
  color: #475569;
}

.trip-rating-detail-mono {
  font-family: ui-monospace, monospace;
  font-weight: 600;
}

.trip-rating-detail-stars-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.08rem 0.15rem;
}

.trip-rating-detail-star {
  font-size: 0.95rem;
  line-height: 1;
  color: #cbd5e1;
  letter-spacing: -0.05em;
}

.trip-rating-detail-star--on {
  color: #f59e0b;
}

.pending-rating-stars {
  margin-top: 0.35rem;
}

.pending-rating-star-btn {
  border: none;
  background: none;
  cursor: pointer;
  padding: 0.05rem 0.1rem;
  font-size: 1.05rem;
  transition: transform 0.15s ease;
}

.pending-rating-star-btn:hover {
  transform: scale(1.08);
}

.pending-rating-field {
  margin-bottom: 0.85rem;
}

.pending-rating-label {
  display: block;
  font-size: 0.82rem;
  font-weight: 700;
  color: #334155;
}

.pending-rating-label--block {
  margin-top: 1rem;
}

.pending-rating-caption {
  margin-top: 0.35rem;
  font-size: 0.8rem;
  font-weight: 700;
  color: #2563eb;
}

.trip-rating-detail-textarea {
  width: 100%;
  margin-top: 0.35rem;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  padding: 0.55rem 0.65rem;
  font-size: 0.85rem;
  resize: vertical;
  min-height: 4.5rem;
  font-family: inherit;
}

.trip-rating-detail-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.16);
}

.trip-rating-detail-textarea::placeholder {
  color: #94a3b8;
}

.trip-rating-detail-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: flex-end;
  padding: 0.75rem 1rem;
  border-top: 1px solid #e2e8f0;
  flex-shrink: 0;
  background: #fff;
  border-radius: 0 0 16px 16px;
}

.trip-rating-detail-btn {
  border-radius: 10px;
  padding: 0.5rem 0.85rem;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
  font-family: inherit;
}

.trip-rating-detail-btn--ghost {
  border-color: #e2e8f0;
  background: #fff;
  color: #475569;
}

.trip-rating-detail-btn--ghost:hover {
  background: #f8fafc;
}

.trip-rating-detail-btn--secondary {
  border-color: #bfdbfe;
  color: #1d4ed8;
  background: #eff6ff;
}

.trip-rating-detail-btn--secondary:hover {
  background: #dbeafe;
}

.trip-rating-detail-btn--primary {
  background: #2563eb;
  color: #fff;
  border-color: #2563eb;
}

.trip-rating-detail-btn--primary:hover:not(:disabled) {
  background: #1d4ed8;
  border-color: #1d4ed8;
}

.trip-rating-detail-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.pending-rating-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.65rem 0.75rem;
  margin-bottom: 0.65rem;
}

.pending-rating-grid .trip-rating-detail-stars-row {
  margin-top: 0.35rem;
}

.rating-label--full {
  grid-column: 1 / -1;
}

@media (max-width: 640px) {
  .pending-rating-grid {
    grid-template-columns: 1fr;
  }
}
</style>
