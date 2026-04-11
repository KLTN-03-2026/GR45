<script setup>
</script>

<template>
  <div class="py-16">
    <div class="relative group transform hover:-translate-y-1 transition-all duration-500 max-w-full">
      
      <!-- Background Container (isolated overflow hidden) -->
      <div class="absolute inset-0 bg-slate-900 rounded-[2.5rem] shadow-2xl overflow-hidden pointer-events-none">
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-gradient-to-br from-blue-500/20 to-purple-500/20 rounded-full blur-[80px] opacity-60 group-hover:opacity-100 group-hover:scale-110 transition-all duration-700 ease-in-out"></div>
        <div class="absolute -bottom-32 -left-32 w-[400px] h-[400px] bg-gradient-to-tr from-emerald-500/20 to-teal-500/20 rounded-full blur-[60px] opacity-60 group-hover:opacity-100 group-hover:scale-110 transition-all duration-700 ease-in-out"></div>
      </div>
      
      <!-- Content Container -->
      <div class="relative z-10 p-8 md:p-14 md:px-16 lg:px-20 text-white flex flex-col lg:flex-row items-center justify-between gap-12 lg:gap-16">
        
        <div class="flex-1 text-center lg:text-left w-full">
           <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 backdrop-blur-md border border-white/10 text-xs font-bold tracking-widest uppercase mb-8 shadow-inner hover:bg-white/10 transition-colors">
             <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
             Cam Kết An Toàn
           </div>
          
          <h2 class="text-4xl lg:text-5xl xl:text-6xl font-extrabold mb-6 leading-tight tracking-tight">
            Kỷ Nguyên Mới Cho <br class="hidden sm:block"/>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-emerald-300 to-teal-200 animate-gradient-x">Hành Trình Của Bạn</span>
          </h2>
          
          <p class="text-slate-300 mb-12 max-w-xl text-base lg:text-lg font-medium text-center lg:text-left mx-auto lg:mx-0 leading-relaxed">
            Hệ thống theo dõi AI liên tục kiểm tra tài xế đảm bảo sự tỉnh táo, kết hợp với các tiêu chuẩn bảo dưỡng khắt khe. Chúng tôi không chỉ chở bạn, chúng tôi bảo vệ bạn.
          </p>
          
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 md:gap-6 text-center">
              <div class="p-4 md:p-5 rounded-2xl bg-white/5 border border-white/5 backdrop-blur-md hover:bg-white/10 hover:border-white/20 transition-all duration-300 group/item hover:-translate-y-1">
                  <div class="w-10 h-10 md:w-12 md:h-12 mx-auto bg-emerald-500/20 rounded-xl flex items-center justify-center mb-3 group-hover/item:scale-110 group-hover/item:rotate-3 transition-transform">
                    <span class="material-symbols-outlined text-[24px] md:text-[28px] text-emerald-400">policy</span>
                  </div>
                  <p class="text-xs md:text-sm font-bold text-white uppercase tracking-wider">Tiêu Chuẩn</p>
                  <p class="text-[10px] md:text-xs text-slate-400 mt-1.5 font-medium">ISO 9001</p>
              </div>
               <div class="p-4 md:p-5 rounded-2xl bg-white/5 border border-white/5 backdrop-blur-md hover:bg-white/10 hover:border-white/20 transition-all duration-300 group/item hover:-translate-y-1">
                  <div class="w-10 h-10 md:w-12 md:h-12 mx-auto bg-blue-500/20 rounded-xl flex items-center justify-center mb-3 group-hover/item:scale-110 group-hover/item:rotate-3 transition-transform">
                    <span class="material-symbols-outlined text-[24px] md:text-[28px] text-blue-400">car_repair</span>
                  </div>
                  <p class="text-xs md:text-sm font-bold text-white uppercase tracking-wider">Bảo Dưỡng</p>
                  <p class="text-[10px] md:text-xs text-slate-400 mt-1.5 font-medium">Định Kỳ 100%</p>
              </div>
               <div class="p-4 md:p-5 rounded-2xl bg-white/5 border border-white/5 backdrop-blur-md hover:bg-white/10 hover:border-white/20 transition-all duration-300 group/item hover:-translate-y-1">
                  <div class="w-10 h-10 md:w-12 md:h-12 mx-auto bg-orange-500/20 rounded-xl flex items-center justify-center mb-3 group-hover/item:scale-110 group-hover/item:rotate-3 transition-transform">
                    <span class="material-symbols-outlined text-[24px] md:text-[28px] text-orange-400">health_and_safety</span>
                  </div>
                  <p class="text-xs md:text-sm font-bold text-white uppercase tracking-wider">Bảo Hiểm</p>
                   <p class="text-[10px] md:text-xs text-slate-400 mt-1.5 font-medium">Toàn Diện</p>
              </div>
               <div class="p-4 md:p-5 rounded-2xl bg-white/5 border border-white/5 backdrop-blur-md hover:bg-white/10 hover:border-white/20 transition-all duration-300 group/item hover:-translate-y-1">
                  <div class="w-10 h-10 md:w-12 md:h-12 mx-auto bg-purple-500/20 rounded-xl flex items-center justify-center mb-3 group-hover/item:scale-110 group-hover/item:rotate-3 transition-transform">
                    <span class="material-symbols-outlined text-[24px] md:text-[28px] text-purple-400">support_agent</span>
                  </div>
                  <p class="text-xs md:text-sm font-bold text-white uppercase tracking-wider">Hỗ Trợ</p>
                  <p class="text-[10px] md:text-xs text-slate-400 mt-1.5 font-medium">24/7</p>
              </div>
          </div>
        </div>
        
        <div class="relative w-full lg:w-1/2 max-w-[460px] perspective-1000 mt-8 lg:mt-0">
          <div class="relative group-hover:rotate-y-[8deg] group-hover:rotate-x-[4deg] transition-transform duration-700 ease-out transform-style-3d">
            <div class="absolute inset-0 bg-gradient-to-tr from-blue-500/40 to-teal-400/40 rounded-[2.5rem] blur-2xl transform rotate-3 scale-105 opacity-50 group-hover:opacity-80 group-hover:rotate-6 transition-all duration-700 pointer-events-none"></div>
            <img src="https://images.unsplash.com/photo-1570125909232-eb263c188f7e?q=80&w=2071&auto=format&fit=crop" alt="Safety" class="relative z-10 rounded-[2.5rem] shadow-2xl border border-white/10 object-cover h-[340px] md:h-[480px] w-full"/>
             
             <!-- Floating Card -->
             <div class="absolute -bottom-6 -left-4 md:-bottom-8 md:-left-8 z-20 bg-white/90 backdrop-blur-xl p-4 md:p-5 rounded-3xl shadow-2xl border border-white/50 flex items-center gap-4 md:gap-5 transform group-hover:-translate-y-4 group-hover:-translate-x-2 transition-transform duration-700">
                 <div class="w-12 h-12 md:w-14 md:h-14 bg-gradient-to-br from-emerald-400 to-teal-500 text-white rounded-xl md:rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-500/30">
                      <span class="material-symbols-outlined text-[28px] md:text-[32px]">verified_user</span>
                 </div>
                 <div>
                     <p class="text-slate-800 font-extrabold text-xl md:text-2xl drop-shadow-sm">100%</p>
                     <p class="text-slate-500 text-[10px] md:text-xs font-bold uppercase tracking-wider mt-0.5">An Toàn Tuyệt Đối</p>
                 </div>
             </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.perspective-1000 {
  perspective: 1000px;
}
.transform-style-3d {
  transform-style: preserve-3d;
}
.animate-gradient-x {
  background-size: 200% auto;
  animation: gradientX 3s ease infinite;
}
@keyframes gradientX {
  0%, 100% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
}
</style>
