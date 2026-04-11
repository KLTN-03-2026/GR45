<script setup>
import { ref } from 'vue';
const features = ref([
  {
    icon: 'verified_user',
    title: 'An TOÀN Tuyệt ĐỐI',
    desc: 'Hệ thống đánh giá chặt chẽ, kiểm định xe định kỳ đảm bảo an toàn tối đa trên mọi cung đường.',
    colorText: 'text-emerald-500',
    colorBg: 'bg-emerald-50'
  },
  {
    icon: 'speed',
    title: 'Đúng GiỜ, ĐÚNG TUYẾN',
    desc: 'Cam kết khởi hành và đến nơi theo lịch trình, giúp bạn tiết kiệm thời gian đáng giá.',
    colorText: 'text-blue-500',
    colorBg: 'bg-blue-50'
  },
  {
    icon: 'support_agent',
    title: 'Hỗ TRỢ 24/7',
    desc: 'Đội ngũ chăm sóc khách hàng luôn sẵn sàng lắng nghe và giải quyết vấn đề của bạn mọi lúc.',
    colorText: 'text-orange-500',
    colorBg: 'bg-orange-50'
  }
]);
</script>

<template>
  <div class="relative py-12" id="hop-tac">
    <div class="text-center mb-12">
      <h2 class="text-3xl font-extrabold text-slate-800 tracking-tight mb-2">Giá Trị Cốt Lõi</h2>
      <p class="text-slate-500 max-w-xl mx-auto font-medium">Chúng tôi mang lại trải nghiệm hoàn hảo với những tiêu chuẩn vượt trội nhất.</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
      <div 
        v-for="(item, idx) in features" 
        :key="idx" 
        class="bg-white rounded-3xl p-8 shadow-sm hover:shadow-xl hover:shadow-slate-200/50 transition-all duration-300 border border-slate-100 group relative overflow-hidden flex flex-col items-center text-center"
      >
        <div class="absolute inset-x-0 -top-px h-1 bg-gradient-to-r from-transparent via-slate-200 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
        <div 
          class="w-16 h-16 rounded-2xl flex items-center justify-center mb-6 shadow-sm group-hover:scale-110 transition-transform duration-300"
          :class="[item.colorBg, item.colorText]"
        >
          <span class="material-symbols-outlined text-[32px]">{{ item.icon }}</span>
        </div>
        <h3 class="text-xl font-bold text-slate-800 mb-3">{{ item.title }}</h3>
        <p class="text-slate-500 leading-relaxed">{{ item.desc }}</p>
      </div>
    </div>
  </div>
</template>
