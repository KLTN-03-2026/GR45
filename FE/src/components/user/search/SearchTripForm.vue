<script setup>
import { ref, computed } from "vue";
import CustomDatePicker from "@/components/common/CustomDatePicker.vue";
import { formatDateOnly } from "@/utils/format";

const props = defineProps({
  searchForm: {
    type: Object,
    required: true,
  },
  searchParams: {
    type: Object,
    required: true,
  },
  provinces: {
    type: Array,
    required: true,
  },
});

const emit = defineEmits(["submit", "swap"]);

const isOpenFrom = ref(false);
const isOpenTo = ref(false);

const cleanProvinceName = (name) => {
  if (!name) return "";
  return name.replace(/^(Thành phố |Tỉnh )/i, "").trim();
};

const getProvinceName = (id) => {
  const p = props.provinces.find((x) => x.id === id);
  return p ? p.ten_tinh_thanh : "";
};

const provincesForTo = computed(() =>
  props.provinces.filter((p) => p.id !== props.searchForm.tinh_thanh_di_id)
);
const provincesForFrom = computed(() =>
  props.provinces.filter((p) => p.id !== props.searchForm.tinh_thanh_den_id)
);

const handleSwap = () => {
  emit("swap");
};

const handleSubmit = () => {
  emit("submit");
};
</script>

<template>
  <div class="search-hero">
    <div class="search-hero__bg"></div>
    <div class="search-container relative z-10">
      <div class="search-hero__box">
        <div class="search-hero__form">
          <!-- Điểm đi -->
          <div class="search-hero__field" @click="isOpenFrom = !isOpenFrom">
            <div class="search-hero__field-inner">
              <span class="material-symbols-outlined search-hero__icon"
                >location_on</span
              >
              <span
                class="search-hero__select-text"
                :class="searchForm.tinh_thanh_di_id ? 'search-hero__select-text--filled' : ''"
              >
                {{
                  searchForm.tinh_thanh_di_id
                    ? getProvinceName(searchForm.tinh_thanh_di_id)
                    : "Điểm đi"
                }}
              </span>
              <span
                class="material-symbols-outlined search-hero__chevron"
                :class="{ 'search-hero__chevron--open': isOpenFrom }"
                >expand_more</span
              >
            </div>
            <!-- Dropdown -->
            <div v-if="isOpenFrom" class="search-hero__dropdown">
              <div class="search-hero__dropdown-scroll">
                <button
                  v-for="p in provincesForFrom"
                  :key="p.id"
                  @click.stop="
                    searchForm.tinh_thanh_di_id = p.id;
                    isOpenFrom = false;
                  "
                  class="search-hero__dropdown-item"
                  :class="{
                    'search-hero__dropdown-item--active':
                      searchForm.tinh_thanh_di_id === p.id,
                  }"
                >
                  <span class="material-symbols-outlined">{{
                    searchForm.tinh_thanh_di_id === p.id
                      ? "my_location"
                      : "location_on"
                  }}</span>
                  {{ p.ten_tinh_thanh }}
                </button>
              </div>
            </div>
          </div>

          <!-- Nút đảo -->
          <button class="search-hero__swap-btn" @click="handleSwap">
            <span class="material-symbols-outlined">swap_horiz</span>
          </button>

          <!-- Điểm đến -->
          <div class="search-hero__field" @click="isOpenTo = !isOpenTo">
            <div class="search-hero__field-inner">
              <span class="material-symbols-outlined search-hero__icon"
                >location_on</span
              >
              <span
                class="search-hero__select-text"
                :class="searchForm.tinh_thanh_den_id ? 'search-hero__select-text--filled' : ''"
              >
                {{
                  searchForm.tinh_thanh_den_id
                    ? getProvinceName(searchForm.tinh_thanh_den_id)
                    : "Điểm đến"
                }}
              </span>
              <span
                class="material-symbols-outlined search-hero__chevron"
                :class="{ 'search-hero__chevron--open': isOpenTo }"
                >expand_more</span
              >
            </div>
            <!-- Dropdown -->
            <div v-if="isOpenTo" class="search-hero__dropdown">
              <div class="search-hero__dropdown-scroll">
                <button
                  v-for="p in provincesForTo"
                  :key="p.id"
                  @click.stop="
                    searchForm.tinh_thanh_den_id = p.id;
                    isOpenTo = false;
                  "
                  class="search-hero__dropdown-item"
                  :class="{
                    'search-hero__dropdown-item--active':
                      searchForm.tinh_thanh_den_id === p.id,
                  }"
                >
                  <span class="material-symbols-outlined">{{
                    searchForm.tinh_thanh_den_id === p.id
                      ? "my_location"
                      : "location_on"
                  }}</span>
                  {{ p.ten_tinh_thanh }}
                </button>
              </div>
            </div>
          </div>

          <!-- Ngày khởi hành -->
          <div class="search-hero__field search-hero__field--date">
            <CustomDatePicker v-model="searchForm.ngay_di" />
          </div>

          <!-- Nút tìm kiếm -->
          <button class="search-hero__search-btn" @click="handleSubmit">
            <span class="material-symbols-outlined">search</span>
            <span class="search-hero__search-btn-text">Tìm chuyến</span>
          </button>
        </div>

        <!-- Thông tin lộ trình hiện tại -->
        <div
          class="search-hero__summary"
          v-if="searchParams.diem_di && searchParams.diem_den"
        >
          <span class="material-symbols-outlined">route</span>
          <strong>{{ searchParams.diem_di }}</strong>
          <span
            class="material-symbols-outlined"
            style="font-size: 16px; opacity: 0.5"
            >arrow_forward</span
          >
          <strong>{{ searchParams.diem_den }}</strong>
          <span class="search-hero__summary-sep">•</span>
          <span>{{ formatDateOnly(searchParams.ngay_di) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* CSS cho SearchTripForm từ SearchTripView.vue */
.search-hero {
  position: relative;
  padding: 1.5rem 1rem 2.5rem;
  overflow: visible;
  z-index: 100;
}

.search-hero__bg {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 50%, #3b82f6 100%);
}

.search-hero__bg::after {
  content: "";
  position: absolute;
  inset: 0;
  background: radial-gradient(
      ellipse at 20% 50%,
      rgba(56, 189, 248, 0.15) 0%,
      transparent 70%
    ),
    radial-gradient(
      ellipse at 80% 80%,
      rgba(30, 64, 175, 0.3) 0%,
      transparent 70%
    );
}

.search-container {
  max-width: 1200px;
  margin: 0 auto;
}

.search-hero__box {
  background: rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 32px;
  padding: 1rem;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.search-hero__form {
  display: flex;
  background: #fff;
  border-radius: 24px;
  padding: 0.5rem;
  gap: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

@media (max-width: 1024px) {
  .search-hero__form {
    flex-direction: column;
    padding: 1rem;
  }
}

.search-hero__field {
  flex: 1;
  position: relative;
  cursor: pointer;
}

.search-hero__field-inner {
  height: 100%;
  padding: 0.85rem 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  border-radius: 18px;
  transition: all 0.2s;
  border: 1px solid transparent;
}

.search-hero__field:hover .search-hero__field-inner {
  background: #f8fafc;
  border-color: #e2e8f0;
}

.search-hero__icon {
  color: #3b82f6;
  font-size: 1.25rem;
}

.search-hero__select-text {
  flex: 1;
  font-size: 0.95rem;
  font-weight: 700;
  color: #94a3b8;
}

.search-hero__select-text--filled {
  color: #0f172a;
}

.search-hero__chevron {
  color: #94a3b8;
  font-size: 1.25rem;
  transition: transform 0.2s;
}

.search-hero__chevron--open {
  transform: rotate(180deg);
}

.search-hero__dropdown {
  position: absolute;
  top: calc(100% + 12px);
  left: 0;
  width: 100%;
  min-width: 280px;
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.15);
  border: 1px solid #f1f5f9;
  z-index: 1000;
  padding: 0.75rem;
  overflow: hidden;
}

.search-hero__dropdown-scroll {
  max-height: 320px;
  overflow-y: auto;
  padding-right: 4px;
}

.search-hero__dropdown-item {
  width: 100%;
  padding: 0.85rem 1rem;
  display: flex;
  align-items: center;
  gap: 0.85rem;
  border-radius: 12px;
  font-size: 0.9rem;
  font-weight: 600;
  color: #475569;
  border: none;
  background: none;
  cursor: pointer;
  transition: all 0.15s;
  text-align: left;
}

.search-hero__dropdown-item:hover {
  background: #f1f5f9;
  color: #3b82f6;
}

.search-hero__dropdown-item--active {
  background: #eff6ff;
  color: #3b82f6;
}

.search-hero__swap-btn {
  width: 50px;
  height: 50px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #64748b;
  transition: all 0.2s;
  flex-shrink: 0;
  align-self: center;
}

.search-hero__swap-btn:hover {
  background: #eff6ff;
  color: #3b82f6;
  border-color: #3b82f6;
  transform: rotate(180deg);
}

@media (max-width: 1024px) {
  .search-hero__swap-btn {
    align-self: flex-end;
    transform: rotate(90deg);
  }
  .search-hero__swap-btn:hover {
    transform: rotate(270deg);
  }
}

.search-hero__search-btn {
  background: #0f172a;
  color: #fff;
  border: none;
  padding: 0 2rem;
  border-radius: 18px;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
}

.search-hero__search-btn:hover {
  background: #3b82f6;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

@media (max-width: 1024px) {
  .search-hero__search-btn {
    padding: 1.25rem;
    justify-content: center;
  }
}

.search-hero__summary {
  margin-top: 1rem;
  display: flex;
  align-items: center;
  gap: 0.65rem;
  color: #fff;
  font-size: 0.85rem;
  padding: 0 0.5rem;
}

.search-hero__summary-sep {
  opacity: 0.4;
}
</style>
