<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  modelValue: String,
  label: { type: String, default: 'Ngày đi' }
});

const emit = defineEmits(['update:modelValue']);

// State
const isOpen = ref(false);
const containerRef = ref(null);

// Date logic
const today = new Date();
const currentMonth = ref(new Date(today.getFullYear(), today.getMonth(), 1));

const selectedDate = computed(() => {
  return props.modelValue ? new Date(props.modelValue) : null;
});

const months = computed(() => [new Date(currentMonth.value)]);

const nextMonth = () => {
  currentMonth.value = new Date(currentMonth.value.setMonth(currentMonth.value.getMonth() + 1));
};

const prevMonth = () => {
  currentMonth.value = new Date(currentMonth.value.setMonth(currentMonth.value.getMonth() - 1));
};

const getDaysInMonth = (date) => {
  const year = date.getFullYear();
  const month = date.getMonth();
  const firstDay = new Date(year, month, 1).getDay();
  const startDay = firstDay === 0 ? 6 : firstDay - 1;
  
  const days = [];
  const lastDay = new Date(year, month + 1, 0).getDate();

  for (let i = 0; i < startDay; i++) days.push(null);
  for (let i = 1; i <= lastDay; i++) days.push(new Date(year, month, i));
  
  return days;
};

const selectDate = (date) => {
  if (!date || isPast(date)) return;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const formatted = `${year}-${month}-${day}`;
  emit('update:modelValue', formatted);
  isOpen.value = false;
};

const isToday = (date) => {
  if (!date) return false;
  return date.toDateString() === today.toDateString();
};

const isSelected = (date) => {
  if (!date || !selectedDate.value) return false;
  return date.toDateString() === selectedDate.value.toDateString();
};

const isPast = (date) => {
  if (!date) return false;
  const d = new Date(date);
  d.setHours(0,0,0,0);
  const t = new Date(today);
  t.setHours(0,0,0,0);
  return d < t;
};

const formatDateDisplay = (dateStr) => {
  if (!dateStr) return 'Chọn ngày đi';
  const d = new Date(dateStr);
  return `${d.getDate()}/${d.getMonth() + 1}/${d.getFullYear()}`;
};

const handleOutsideClick = (e) => {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    isOpen.value = false;
  }
};

onMounted(() => document.addEventListener('click', handleOutsideClick));
onUnmounted(() => document.removeEventListener('click', handleOutsideClick));

const weekDays = [
  { name: 'T2', isWeekend: false },
  { name: 'T3', isWeekend: false },
  { name: 'T4', isWeekend: false },
  { name: 'T5', isWeekend: false },
  { name: 'T6', isWeekend: false },
  { name: 'T7', isWeekend: true },
  { name: 'CN', isWeekend: true }
];
</script>

<template>
  <div class="relative w-full h-full" ref="containerRef">
    <!-- Input Trigger -->
    <div 
      @click="isOpen = !isOpen"
      class="w-full h-14 pl-12 pr-4 bg-slate-50 border border-slate-200 text-slate-700 text-base rounded-2xl flex items-center cursor-pointer font-bold hover:border-primary transition-all select-none group"
      :class="{'ring-4 ring-primary/10 border-primary bg-white': isOpen}"
    >
      <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-primary">calendar_month</span>
      <span :class="!modelValue ? 'text-slate-400' : 'text-slate-800'">
        {{ formatDateDisplay(modelValue) }}
      </span>
    </div>

    <!-- Calendar Dropdown -->
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform scale-95 opacity-0 -translate-y-2"
      enter-to-class="transform scale-100 opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform scale-100 opacity-100 translate-y-0"
      leave-to-class="transform scale-95 opacity-0 -translate-y-2"
    >
      <div v-if="isOpen" class="absolute top-[calc(100%+10px)] left-0 md:left-auto right-0 bg-white rounded-xl border border-slate-200 p-6 z-[100] w-full min-w-[320px] md:w-[440px]">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <button @click.stop="prevMonth" class="w-10 h-10 flex items-center justify-center text-blue-600">
            <span class="material-symbols-outlined font-bold text-[28px]">chevron_left</span>
          </button>
          
          <div class="text-[22px] font-extrabold text-slate-900 tracking-tight">
             Tháng {{ months[0].getMonth() + 1 }}, {{ months[0].getFullYear() }}
          </div>

          <button @click.stop="nextMonth" class="w-10 h-10 flex items-center justify-center text-blue-600">
            <span class="material-symbols-outlined font-bold text-[28px]">chevron_right</span>
          </button>
        </div>

        <!-- Weekday Labels -->
        <div class="grid grid-cols-7 mb-2 border-b border-slate-100 pb-2">
          <div 
            v-for="day in weekDays" :key="day.name" 
            class="text-center text-sm font-bold py-1"
            :class="day.isWeekend ? 'text-red-500' : 'text-slate-600'"
          >
            {{ day.name }}
          </div>
        </div>

        <!-- Days Grid -->
        <div class="grid grid-cols-7 gap-y-2">
          <div 
            v-for="(day, dIdx) in getDaysInMonth(months[0])" :key="dIdx"
            class="h-14 flex flex-col items-center justify-center relative cursor-pointer transition-all group"
            @click.stop="selectDate(day)"
          >
            <div 
              v-if="day"
              class="w-12 h-12 flex flex-col items-center justify-center rounded-xl transition-all"
              :class="[
                isPast(day) ? 'text-slate-200 cursor-not-allowed' : 'text-slate-900 hover:bg-slate-50',
                isSelected(day) ? 'bg-amber-400 !text-slate-900 font-bold' : '',
                isToday(day) && !isSelected(day) ? '!text-blue-600 font-bold' : '',
              ]"
            >
              <span class="text-xl leading-none font-bold">{{ day.getDate() }}</span>
            </div>
          </div>
        </div>

      </div>
    </transition>
  </div>
</template>
