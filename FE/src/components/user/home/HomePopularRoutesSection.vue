<script setup>
import { ref } from 'vue';

const popularRoutes = ref([
  {
    image: 'https://images.unsplash.com/photo-1616484173745-07f25fd0547f?q=80&w=2070&auto=format&fit=crop',
    name: 'Sài Gòn - Đà Lạt',
    distance: '300km',
    time: '6h 30m',
    price: '250.000đ'
  },
  {
    image: 'https://images.unsplash.com/photo-1559592413-7cec4d0cae2b?q=80&w=2128&auto=format&fit=crop',
    name: 'Hà Nội - Sapa',
    distance: '320km',
    time: '5h 45m',
    price: '280.000đ'
  },
  {
    image: 'https://images.unsplash.com/photo-1593950315186-76a92975b60c?q=80&w=2070&auto=format&fit=crop',
    name: 'Đà Nẵng - Huế',
    distance: '100km',
    time: '2h 15m',
    price: '120.000đ'
  }
]);
</script>

<template>
  <div class="relative py-12" id="tuyen-pho-bien">
    <div class="flex items-end justify-between mb-10">
      <div>
        <h2 class="text-3xl md:text-4xl font-extrabold text-slate-800 tracking-tight flex items-center gap-3">
          Tuyến Phổ Biến <span class="material-symbols-outlined text-orange-500 text-[36px]">local_fire_department</span>
        </h2>
        <p class="text-slate-500 font-medium mt-2 text-lg">Hành trình khám phá tuyệt vời đang chờ đón bạn.</p>
      </div>
      <button class="hidden md:flex items-center gap-2 text-primary font-bold hover:text-blue-800 transition-colors group">
        Xem tất cả <span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
      <!-- Card -->
      <div v-for="(route, i) in popularRoutes" :key="i" class="group bg-white rounded-3xl overflow-hidden shadow-sm hover:shadow-2xl hover:shadow-blue-900/10 transition-all duration-300 border border-slate-100 cursor-pointer flex flex-col h-full hover:-translate-y-1">
        <div class="relative h-56 overflow-hidden">
          <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent z-10"></div>
          <span class="absolute top-4 left-4 z-20 px-3 py-1 bg-white/20 backdrop-blur-md rounded-xl text-white text-xs font-bold border border-white/20 uppercase tracking-widest shadow-sm">Hot</span>
          <img 
            :src="route.image" 
            :alt="route.name"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out" 
          />
          <div class="absolute bottom-4 left-4 right-4 z-20">
            <h3 class="text-2xl font-extrabold text-white mb-1 drop-shadow-md tracking-tight">{{ route.name }}</h3>
             <div class="flex items-center gap-4 text-white/90 text-sm font-medium">
               <span class="flex items-center gap-1.5 drop-shadow"><span class="material-symbols-outlined text-[16px]">route</span> {{ route.distance }}</span>
               <span class="flex items-center gap-1.5 drop-shadow"><span class="material-symbols-outlined text-[16px]">schedule</span> {{ route.time }}</span>
             </div>
          </div>
        </div>
        <div class="p-5 flex-1 flex flex-col justify-between bg-white border-t-4 border-primary group-hover:bg-blue-50/30 transition-colors">
          <div class="flex justify-between items-center mt-2">
            <div>
              <p class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-1">Chỉ từ</p>
              <p class="text-2xl font-extrabold text-orange-600">{{ route.price }}</p>
            </div>
             <button class="w-12 h-12 rounded-2xl bg-slate-50 text-primary flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-all shadow-sm">
                <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
             </button>
          </div>
        </div>
      </div>
    </div>
    
    <div class="mt-8 text-center md:hidden">
      <button class="px-6 py-3 rounded-xl bg-slate-100 text-slate-700 font-bold hover:bg-slate-200 transition-colors w-full flex justify-center items-center gap-2 max-w-sm mx-auto">
        Xem tất cả <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
      </button>
    </div>
  </div>
</template>
