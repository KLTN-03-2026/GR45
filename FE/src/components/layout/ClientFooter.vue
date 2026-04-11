<script setup>
</script>

<template>
  <footer class="bg-gray-50 border-t border-gray-200 mt-auto font-['Manrope'] antialiased">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-12">
        <!-- Brand & About -->
        <div class="space-y-4">
          <div class="text-2xl font-extrabold tracking-tight text-blue-900">
            VigilantFleet
          </div>
          <p class="text-sm text-gray-600 leading-relaxed">
            Hệ thống đặt vé xe trực tuyến hàng đầu, mang đến trải nghiệm hành trình an toàn, tiện lợi và đáng tin cậy.
          </p>
          <div class="flex space-x-4 pt-4">
            <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors">
               <span class="material-symbols-outlined">facebook</span>
            </a>
            <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors">
               <span class="material-symbols-outlined">mail</span>
            </a>
            <a href="#" class="text-gray-400 hover:text-red-500 transition-colors">
               <span class="material-symbols-outlined">play_circle</span>
            </a>
          </div>
        </div>

        <!-- Links -->
        <div>
          <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">Về Chúng Tôi</h3>
          <ul class="space-y-3">
            <li><a href="#" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Giới thiệu</a></li>
            <li><a href="#" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Tuyển dụng</a></li>
            <li><a href="#" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Quy chế hoạt động</a></li>
            <li><a href="#" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Chính sách bảo mật</a></li>
          </ul>
        </div>

        <!-- Support -->
        <div>
          <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">Hỗ Trợ</h3>
          <ul class="space-y-3">
            <li><a href="#" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Hướng dẫn đặt vé</a></li>
            <li><a href="#" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Chính sách hủy vé</a></li>
            <li><a href="#" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Giải quyết khiếu nại</a></li>
            <li><a href="#" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Câu hỏi thường gặp</a></li>
          </ul>
        </div>

        <!-- Contact -->
        <div>
          <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">Thông tin liên hệ</h3>
          <ul class="space-y-4 text-sm text-gray-600">
            <li class="flex items-start gap-3">
              <span class="material-symbols-outlined text-gray-400 text-[20px]">location_on</span>
              <span>123 Đường ABC, Quận XYZ, TP. Hà Nội</span>
            </li>
            <li class="flex items-center gap-3">
              <span class="material-symbols-outlined text-gray-400 text-[20px]">call</span>
              <a href="tel:19001234" class="hover:text-blue-600 font-medium transition-colors">1900 1234</a>
            </li>
            <li class="flex items-center gap-3">
              <span class="material-symbols-outlined text-gray-400 text-[20px]">mail</span>
              <a href="mailto:hotro@vigilantfleet.vn" class="hover:text-blue-600 transition-colors">hotro@vigilantfleet.vn</a>
            </li>
          </ul>
        </div>
      </div>

      <div class="mt-12 pt-8 border-t border-gray-200 flex flex-col md:flex-row justify-between items-center gap-4">
        <p class="text-sm text-gray-500">
          © 2024 VigilantFleet. Bản quyền thuộc về Công ty TNHH Vigilant.
        </p>
        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-500">Thanh toán an toàn qua</span>
          <!-- Appreciate icons or text -->
          <div class="flex gap-2">
            <div class="px-2 py-1 bg-white border border-gray-200 rounded text-xs font-bold text-blue-800">VISA</div>
            <div class="px-2 py-1 bg-white border border-gray-200 rounded text-xs font-bold text-red-600">MasterCard</div>
            <div class="px-2 py-1 bg-white border border-gray-200 rounded text-xs font-bold text-blue-500">VNPay</div>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>
