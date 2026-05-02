<script setup>
import HomeHeroSection from '@/components/user/home/HomeHeroSection.vue';
import HomeTripSearchSection from '@/components/user/home/HomeTripSearchSection.vue';
import HomeFeatureSection from '@/components/user/home/HomeFeatureSection.vue';
import HomePopularRoutesSection from '@/components/user/home/HomePopularRoutesSection.vue';
import HomeSafetyCommitSection from '@/components/user/home/HomeSafetyCommitSection.vue';
import HomeVoucherSection from '@/components/user/home/HomeVoucherSection.vue';
</script>

<template>
  <main class="font-['Manrope'] antialiased text-slate-800 bg-slate-50">
    <!-- Hero và Search Form Group -->
    <div class="relative">
      <HomeHeroSection />
      <HomeTripSearchSection />
    </div>
    
    <!-- Các Section Chính -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16 py-16">
      <HomePopularRoutesSection />
      <HomeVoucherSection />
      <HomeSafetyCommitSection />
      <HomeFeatureSection />
    </div>
  </main>
</template>
