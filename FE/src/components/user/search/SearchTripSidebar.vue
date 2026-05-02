<script setup>
import { ref } from 'vue';

const props = defineProps({
  filters: {
    type: Object,
    required: true
  },
  predefinedTimeFilters: {
    type: Array,
    required: true
  },
  selectedPredefinedTime: {
    type: String,
    default: null
  },
  isFilterOpen: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['apply', 'close', 'reset', 'apply-time', 'update:filters', 'update:selectedPredefinedTime']);

const handleApplyTime = (timeFilter) => {
  emit('apply-time', timeFilter);
};

const handleApplyFilters = () => {
  emit('apply');
};

const resetFilters = () => {
  emit('reset');
};
</script>

<template>
  <aside class="search-sidebar" :class="{ 'search-sidebar--open': isFilterOpen }">
    <!-- Overlay mobile -->
    <div class="search-sidebar__overlay" @click="$emit('close')"></div>

    <div class="search-sidebar__content">
      <div class="search-sidebar__header">
        <h3 class="search-sidebar__title">Bộ lọc tìm kiếm</h3>
        <button class="search-sidebar__reset" @click="resetFilters">Xóa lọc</button>
      </div>

      <!-- Giờ khởi hành nhanh -->
      <div class="search-filter-section">
        <h4 class="search-filter-section__title">Giờ khởi hành</h4>
        <div class="search-filter-time-grid">
          <button
            v-for="tf in predefinedTimeFilters"
            :key="tf.value"
            class="search-filter-time-btn"
            :class="{ 'search-filter-time-btn--active': selectedPredefinedTime === tf.value }"
            @click="handleApplyTime(tf)"
          >
            <span class="material-symbols-outlined">{{ tf.icon }}</span>
            <span class="search-filter-time-btn__label">{{ tf.label.split(' (')[0] }}</span>
            <span class="search-filter-time-btn__range">{{ tf.tu }} - {{ tf.den }}</span>
          </button>
        </div>
      </div>

      <!-- Khoảng giá -->
      <div class="search-filter-section">
        <h4 class="search-filter-section__title">Khoảng giá (VNĐ)</h4>
        <div class="search-filter-price">
          <div class="search-filter-price__inputs">
            <input
              type="number"
              :value="filters.gia_ve_tu"
              @input="$emit('update:filters', { ...filters, gia_ve_tu: $event.target.value })"
              placeholder="Từ..."
              class="search-filter-input"
            />
            <span class="search-filter-price__sep">—</span>
            <input
              type="number"
              :value="filters.gia_ve_den"
              @input="$emit('update:filters', { ...filters, gia_ve_den: $event.target.value })"
              placeholder="Đến..."
              class="search-filter-input"
            />
          </div>
          <button @click="handleApplyFilters" class="search-filter-apply-btn">
            Áp dụng
          </button>
        </div>
      </div>

      <!-- Nút đóng cho mobile -->
      <button class="search-sidebar__close-btn" @click="$emit('close')">
        Áp dụng bộ lọc
      </button>
    </div>
  </aside>
</template>

<style scoped>
/* CSS cho SearchSidebar */
.search-sidebar {
  width: 320px;
  flex-shrink: 0;
}

@media (max-width: 1024px) {
  .search-sidebar {
    position: fixed;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    z-index: 1000;
    transition: all 0.3s ease;
    visibility: hidden;
  }

  .search-sidebar--open {
    left: 0;
    visibility: visible;
  }
}

.search-sidebar__overlay {
  display: none;
}

@media (max-width: 1024px) {
  .search-sidebar__overlay {
    display: block;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
  }
}

.search-sidebar__content {
  background: #fff;
  border-radius: 24px;
  border: 1px solid #f1f5f9;
  padding: 1.5rem;
  position: sticky;
  top: 100px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}

@media (max-width: 1024px) {
  .search-sidebar__content {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 80%;
    border-radius: 32px 32px 0 0;
    overflow-y: auto;
    padding-bottom: 5rem;
  }
}

.search-sidebar__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.search-sidebar__title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #0f172a;
}

.search-sidebar__reset {
  background: none;
  border: none;
  color: #3b82f6;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}

.search-filter-section {
  margin-bottom: 2.5rem;
}

.search-filter-section__title {
  font-size: 0.95rem;
  font-weight: 700;
  color: #475569;
  margin-bottom: 1.25rem;
}

.search-filter-time-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}

.search-filter-time-btn {
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  padding: 1rem 0.5rem;
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
}

.search-filter-time-btn:hover {
  background: #f1f5f9;
  border-color: #cbd5e1;
}

.search-filter-time-btn--active {
  background: #eff6ff;
  border-color: #3b82f6;
  box-shadow: 0 0 0 1px #3b82f6;
}

.search-filter-time-btn .material-symbols-outlined {
  color: #64748b;
  font-size: 1.25rem;
}

.search-filter-time-btn--active .material-symbols-outlined {
  color: #3b82f6;
}

.search-filter-time-btn__label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #475569;
  text-align: center;
}

.search-filter-time-btn__range {
  font-size: 0.7rem;
  color: #94a3b8;
  font-weight: 600;
}

.search-filter-price__inputs {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.search-filter-input {
  flex: 1;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  padding: 0.75rem 1rem;
  border-radius: 12px;
  font-size: 0.9rem;
  font-weight: 600;
  color: #0f172a;
  outline: none;
  width: 0;
}

.search-filter-input:focus {
  border-color: #3b82f6;
  background: #fff;
}

.search-filter-price__sep {
  color: #94a3b8;
  font-weight: 700;
}

.search-filter-apply-btn {
  width: 100%;
  background: #0f172a;
  color: #fff;
  border: none;
  padding: 0.85rem;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s;
}

.search-filter-apply-btn:hover {
  background: #3b82f6;
}

.search-sidebar__close-btn {
  display: none;
}

@media (max-width: 1024px) {
  .search-sidebar__close-btn {
    display: block;
    position: absolute;
    bottom: 1.5rem;
    left: 1.5rem;
    right: 1.5rem;
    background: #3b82f6;
    color: #fff;
    border: none;
    padding: 1rem;
    border-radius: 16px;
    font-weight: 800;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
  }
}
</style>
