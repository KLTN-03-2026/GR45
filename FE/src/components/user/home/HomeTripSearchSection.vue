<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import clientApi from '@/api/clientApi.js';
import CustomDatePicker from '@/components/common/CustomDatePicker.vue';

const router = useRouter();
const searchForm = reactive({
  ngay_di: '', // format YYYY-MM-DD
  tinh_thanh_di_id: '',
  tinh_thanh_den_id: '',
});

const formErrors = reactive({});
const provinces = ref([]);
const recentRoutes = [
  { fromId: 1, toId: 13, fromName: 'Hà Nội', toName: 'Hải Phòng', icon: 'directions_bus' },
  { fromId: 1, toId: 28, fromName: 'Hà Nội', toName: 'Hồ Chí Minh', icon: 'directions_bus' },
  { fromId: 21, toId: 28, fromName: 'Đà Nẵng', toName: 'Hồ Chí Minh', icon: 'directions_bus' },
  { fromId: 21, toId: 20, fromName: 'Đà Nẵng', toName: 'Thành phố Huế', icon: 'directions_bus' },
];

const fetchProvinces = async () => {
  try {
    const response = await clientApi.getProvinces();
    if (response) {
      // Xử lý đúng cấu trúc trả về từ API { success: true, data: [...] }
      provinces.value = response.data || response;
    }
  } catch (error) {
    console.error('Lỗi khi lấy danh sách tỉnh thành:', error);
  }
};

const handleSwap = () => {
  const temp = searchForm.tinh_thanh_di_id;
  searchForm.tinh_thanh_di_id = searchForm.tinh_thanh_den_id;
  searchForm.tinh_thanh_den_id = temp;
};

const selectRecentRoute = (route) => {
  searchForm.tinh_thanh_di_id = route.fromId;
  searchForm.tinh_thanh_den_id = route.toId;
  if (!searchForm.ngay_di) {
    const today = new Date();
    today.setDate(today.getDate() + 1);
    searchForm.ngay_di = today.toISOString().split('T')[0];
  }
};

const validateSearch = () => {
  formErrors.ngay_di = !searchForm.ngay_di ? 'Vui lòng chọn ngày đi' : '';
  formErrors.tinh_thanh_di_id = !searchForm.tinh_thanh_di_id ? 'Vui lòng chọn điểm đi' : '';
  formErrors.tinh_thanh_den_id = !searchForm.tinh_thanh_den_id ? 'Vui lòng chọn điểm đến' : '';

  if (searchForm.tinh_thanh_di_id && searchForm.tinh_thanh_den_id && searchForm.tinh_thanh_di_id === searchForm.tinh_thanh_den_id) {
    formErrors.tinh_thanh_den_id = 'Điểm đến không được trùng điểm đi';
  }

  return !formErrors.ngay_di && !formErrors.tinh_thanh_di_id && !formErrors.tinh_thanh_den_id;
};

const getProvinceName = (id) => {
  const p = provinces.value.find(x => x.id === id);
  return p ? p.ten_tinh_thanh : '';
};

// State custom dropdown
const isOpenFrom = ref(false);
const isOpenTo = ref(false);
const fromSelectRef = ref(null);
const toSelectRef = ref(null);

const searchFromQuery = ref('');
const searchToQuery = ref('');
const fromInputText = ref('');
const toInputText = ref('');

const filteredFromProvinces = computed(() => {
  const q = searchFromQuery.value.toLowerCase().trim();
  if (!q) return provinces.value;
  return provinces.value.filter(p => 
    p.ten_tinh_thanh && p.ten_tinh_thanh.toLowerCase().includes(q)
  );
});

const filteredToProvinces = computed(() => {
  const q = searchToQuery.value.toLowerCase().trim();
  if (!q) return provinces.value;
  return provinces.value.filter(p => 
    p.ten_tinh_thanh && p.ten_tinh_thanh.toLowerCase().includes(q)
  );
});

watch(isOpenFrom, (isOpen) => {
  if (!isOpen) {
    fromInputText.value = searchForm.tinh_thanh_di_id ? getProvinceName(searchForm.tinh_thanh_di_id) : '';
    searchFromQuery.value = '';
  }
});

watch(isOpenTo, (isOpen) => {
  if (!isOpen) {
    toInputText.value = searchForm.tinh_thanh_den_id ? getProvinceName(searchForm.tinh_thanh_den_id) : '';
    searchToQuery.value = '';
  }
});

watch(() => searchForm.tinh_thanh_di_id, (newId) => {
  fromInputText.value = newId ? getProvinceName(newId) : '';
});

watch(() => searchForm.tinh_thanh_den_id, (newId) => {
  toInputText.value = newId ? getProvinceName(newId) : '';
});

watch(provinces, () => {
  if (searchForm.tinh_thanh_di_id) fromInputText.value = getProvinceName(searchForm.tinh_thanh_di_id);
  if (searchForm.tinh_thanh_den_id) toInputText.value = getProvinceName(searchForm.tinh_thanh_den_id);
});

const handleOutsideClick = (e) => {
  if (fromSelectRef.value && !fromSelectRef.value.contains(e.target)) {
    isOpenFrom.value = false;
  }
  if (toSelectRef.value && !toSelectRef.value.contains(e.target)) {
    isOpenTo.value = false;
  }
};

const cleanProvinceName = (name) => {
  if (!name) return '';
  return name.replace(/^(Thành phố |Tỉnh )/i, '').trim();
};

const submitSearch = () => {
  if (validateSearch()) {
    // Lấy tên tỉnh thành từ ID đã chọn, loại bỏ prefix "Thành phố"/"Tỉnh"
    const diemDi = cleanProvinceName(getProvinceName(searchForm.tinh_thanh_di_id));
    const diemDen = cleanProvinceName(getProvinceName(searchForm.tinh_thanh_den_id));
    router.push({
      path: '/search',
      query: {
        ngay_di: searchForm.ngay_di,
        diem_di: diemDi,
        diem_den: diemDen,
      },
    });
  }
};


import { onBeforeUnmount } from 'vue';

onMounted(() => {
  fetchProvinces();
  // Set default date to today
  const today = new Date();
  searchForm.ngay_di = today.toISOString().split('T')[0];
  document.addEventListener('click', handleOutsideClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick);
});
</script>

<template>
  <div class="relative -mt-24 z-10 px-4 md:px-0" id="tim-chuyen">
    <div class="max-w-7xl mx-auto px-6">
      <div class="bg-white rounded-3xl shadow-xl shadow-blue-900/10 p-6 md:p-8 border border-slate-100">
        <!-- Main Form Grid -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
          
          <!-- Điểm Đi Custom Dropdown -->
          <div class="md:col-span-3">
            <div class="relative group" ref="fromSelectRef">
              <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-primary z-10 pointer-events-none">location_on</span>
              <input 
                v-model="fromInputText"
                @focus="isOpenFrom = true; isOpenTo = false"
                @input="searchFromQuery = fromInputText; isOpenFrom = true"
                type="text"
                placeholder="Điểm Đi"
                class="w-full h-14 pl-12 pr-10 bg-slate-50 border border-slate-200 text-slate-800 text-base rounded-2xl flex items-center justify-between font-bold hover:border-slate-300 transition-all focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary focus:bg-white shadow-sm"
                :class="{'border-red-500': formErrors.tinh_thanh_di_id}"
              />
              <span 
                @click="isOpenFrom = !isOpenFrom; isOpenTo = false"
                class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 cursor-pointer transition-transform duration-300" 
                :class="{'rotate-180 text-primary': isOpenFrom}"
              >
                expand_more
              </span>

              <!-- Dropdown Menu List -->
              <transition 
                enter-active-class="transition duration-200 ease-out" enter-from-class="transform scale-95 opacity-0" enter-to-class="transform scale-100 opacity-100"
                leave-active-class="transition duration-75 ease-in" leave-from-class="transform scale-100 opacity-100" leave-to-class="transform scale-95 opacity-0"
              >
                <div v-if="isOpenFrom" class="absolute top-[calc(100%+8px)] left-0 w-full min-w-[240px] bg-white rounded-2xl shadow-xl shadow-slate-200 border border-slate-100 overflow-hidden z-50">
                  <div class="max-h-[280px] overflow-y-auto overscroll-contain py-2 custom-scrollbar">
                    <button 
                      v-for="province in filteredFromProvinces" :key="province.id"
                      @click.prevent="searchForm.tinh_thanh_di_id = province.id; isOpenFrom = false; fromInputText = province.ten_tinh_thanh"
                      class="w-full text-left px-5 py-3 hover:bg-slate-50 transition-colors flex items-center gap-3 text-base group/item"
                      :class="{'bg-blue-50/50': searchForm.tinh_thanh_di_id === province.id}"
                    >
                      <span class="material-symbols-outlined text-[18px]" :class="searchForm.tinh_thanh_di_id === province.id ? 'text-primary' : 'text-slate-300 group-hover/item:text-slate-400'">
                        {{ searchForm.tinh_thanh_di_id === province.id ? 'my_location' : 'location_on' }}
                      </span>
                      <span :class="{'font-bold text-primary': searchForm.tinh_thanh_di_id === province.id, 'text-slate-700 font-medium': searchForm.tinh_thanh_di_id !== province.id}">
                        {{ province.ten_tinh_thanh }}
                      </span>
                      <span v-if="searchForm.tinh_thanh_di_id === province.id" class="material-symbols-outlined text-[18px] text-primary ml-auto">check</span>
                    </button>
                  </div>
                </div>
              </transition>
            </div>
            <p v-if="formErrors.tinh_thanh_di_id" class="text-red-500 text-xs mt-1 font-medium italic absolute">{{ formErrors.tinh_thanh_di_id }}</p>
          </div>

          <!-- Swap Button - Căn giữa trên Desktop -->
          <div class="md:col-span-1 flex justify-center py-2 h-full items-center">
            <button 
              @click.prevent="handleSwap"
              class="w-10 h-10 rounded-full bg-slate-50 border border-slate-200 text-slate-400 hover:text-primary hover:border-primary hover:bg-blue-50 flex items-center justify-center transition-all hover:rotate-180 hover:shadow-md active:scale-95"
              title="Đảo chiều"
            >
              <span class="material-symbols-outlined text-[20px]">swap_horiz</span>
            </button>
          </div>

          <!-- Điểm Đến Custom Dropdown -->
          <div class="md:col-span-3">
            <div class="relative group" ref="toSelectRef">
               <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-orange-500 z-10 pointer-events-none">location_on</span>
              <input 
                v-model="toInputText"
                @focus="isOpenTo = true; isOpenFrom = false"
                @input="searchToQuery = toInputText; isOpenTo = true"
                type="text"
                placeholder="Điểm Đến"
                class="w-full h-14 pl-12 pr-10 bg-slate-50 border border-slate-200 text-slate-800 text-base rounded-2xl flex items-center justify-between font-bold hover:border-slate-300 transition-all focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 focus:bg-white shadow-sm"
                :class="{'border-red-500': formErrors.tinh_thanh_den_id}"
              />
              <span 
                @click="isOpenTo = !isOpenTo; isOpenFrom = false"
                class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 cursor-pointer transition-transform duration-300" 
                :class="{'rotate-180 text-orange-500': isOpenTo}"
              >
                expand_more
              </span>

               <!-- Dropdown Menu List -->
               <transition 
                enter-active-class="transition duration-200 ease-out" enter-from-class="transform scale-95 opacity-0" enter-to-class="transform scale-100 opacity-100"
                leave-active-class="transition duration-75 ease-in" leave-from-class="transform scale-100 opacity-100" leave-to-class="transform scale-95 opacity-0"
              >
                <div v-if="isOpenTo" class="absolute top-[calc(100%+8px)] left-0 md:-left-4 lg:left-0 w-full min-w-[240px] bg-white rounded-2xl shadow-xl shadow-slate-200 border border-slate-100 overflow-hidden z-50">
                  <div class="max-h-[280px] overflow-y-auto overscroll-contain py-2 custom-scrollbar">
                    <button 
                      v-for="province in filteredToProvinces" :key="province.id"
                      @click.prevent="searchForm.tinh_thanh_den_id = province.id; isOpenTo = false; toInputText = province.ten_tinh_thanh"
                      class="w-full text-left px-5 py-3 hover:bg-slate-50 transition-colors flex items-center gap-3 text-base group/item"
                      :class="{'bg-orange-50/50': searchForm.tinh_thanh_den_id === province.id}"
                    >
                      <span class="material-symbols-outlined text-[18px]" :class="searchForm.tinh_thanh_den_id === province.id ? 'text-orange-500' : 'text-slate-300 group-hover/item:text-slate-400'">
                        {{ searchForm.tinh_thanh_den_id === province.id ? 'my_location' : 'location_on' }}
                      </span>
                      <span :class="{'font-bold text-orange-600': searchForm.tinh_thanh_den_id === province.id, 'text-slate-700 font-medium': searchForm.tinh_thanh_den_id !== province.id}">
                        {{ province.ten_tinh_thanh }}
                      </span>
                      <span v-if="searchForm.tinh_thanh_den_id === province.id" class="material-symbols-outlined text-[18px] text-orange-500 ml-auto">check</span>
                    </button>
                  </div>
                </div>
              </transition>
            </div>
            <p v-if="formErrors.tinh_thanh_den_id" class="text-red-500 text-xs mt-1 font-medium italic absolute">{{ formErrors.tinh_thanh_den_id }}</p>
          </div>

          <!-- Ngày Đi -->
          <div class="md:col-span-3">
            <CustomDatePicker 
              v-model="searchForm.ngay_di"
            />
            <p v-if="formErrors.ngay_di" class="text-red-500 text-xs mt-1 font-medium italic absolute">{{ formErrors.ngay_di }}</p>
          </div>

          <!-- Tìm Kiếm Box -->
          <div class="md:col-span-2 flex items-end">
            <button 
              @click="submitSearch"
              class="w-full h-14 bg-primary hover:bg-blue-700 text-white font-bold text-base rounded-2xl shadow-lg shadow-blue-600/30 flex items-center justify-center gap-2 transition-all active:scale-[0.98] outline-none hover:-translate-y-0.5"
            >
              <span class="material-symbols-outlined text-[20px]">search</span>
              <span>Tìm Chuyến</span>
            </button>
          </div>
        </div>
        
         <!-- Quick Links (Tùy chọn) -->
         <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-start gap-6">
            <div class="flex items-center gap-2 text-slate-500 text-sm font-bold uppercase tracking-wider flex-shrink-0">
               <span class="material-symbols-outlined text-[20px]">history</span> Tuyến gần đây:
            </div>
             <div class="flex items-center gap-3 overflow-x-auto pb-1 scrollbar-hide flex-1">
                 <button v-for="(route, i) in recentRoutes" :key="i" @click="selectRecentRoute(route)" class="whitespace-nowrap flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 hover:bg-blue-50 text-slate-600 hover:text-primary border border-slate-100 transition-all text-base font-semibold hover:shadow-sm">
                     <span class="material-symbols-outlined text-[18px] opacity-70">{{ route.icon }}</span> {{ route.fromName }} - {{ route.toName }}
                 </button>
             </div>
         </div>
      </div>
    </div>
  </div>
</template>
