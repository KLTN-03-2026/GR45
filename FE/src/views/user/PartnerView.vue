<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();

const goRegister = () => {
  router.push('/auth/operator-register');
};
</script>

<template>
  <div class="partner-page">
    <!-- HERO SECTION -->
    <section class="partner-hero">
      <div class="partner-hero__bg">
        <div class="partner-hero__blob partner-hero__blob--1"></div>
        <div class="partner-hero__blob partner-hero__blob--2"></div>
      </div>
      <div class="partner-hero__content">
        <span class="partner-badge">Dành cho Nhà xe</span>
        <h1 class="partner-title">Nâng tầm dịch vụ <br>Cùng nền tảng <span>BusSafe</span></h1>
        <p class="partner-subtitle">Giải pháp chuyển đổi số toàn diện giúp nhà xe quản lý tối ưu, tiết kiệm chi phí và tăng doanh thu vượt trội.</p>
        <div class="partner-actions">
          <button @click="goRegister" class="partner-btn partner-btn--primary">
            Đăng ký đối tác ngay
            <span class="material-symbols-outlined">arrow_forward</span>
          </button>
          <a href="#features" class="partner-btn partner-btn--outline">Tìm hiểu thêm</a>
        </div>
      </div>
    </section>

    <!-- STATISTICS -->
    <section class="partner-stats">
      <div class="partner-stats__grid">
        <div class="partner-stat-item">
          <h3 class="partner-stat-num">50+</h3>
          <p class="partner-stat-label">Nhà xe đối tác</p>
        </div>
        <div class="partner-stat-item">
          <h3 class="partner-stat-num">1000+</h3>
          <p class="partner-stat-label">Chuyến xe mỗi ngày</p>
        </div>
        <div class="partner-stat-item">
          <h3 class="partner-stat-num">2M+</h3>
          <p class="partner-stat-label">Hành khách tin dùng</p>
        </div>
      </div>
    </section>

    <!-- WHY CHOOSE US -->
    <section id="features" class="partner-features">
      <div class="partner-section-header">
        <h2>Tại sao nên hợp tác với BusSafe?</h2>
        <p>Hệ sinh thái công nghệ giúp bạn quản lý nhà xe dễ dàng hơn bao giờ hết.</p>
      </div>
      
      <div class="partner-features__grid">
        <!-- Feature 1 -->
        <div class="partner-feature-card">
          <div class="partner-feature-icon">
            <span class="material-symbols-outlined">dashboard_customize</span>
          </div>
          <h3>Quản lý tập trung</h3>
          <p>Điều hành toàn bộ hoạt động kinh doanh: từ quản lý tuyến đường, chuyến xe, tài xế đến hệ thống bán vé chỉ trên một giao diện duy nhất.</p>
        </div>
        <!-- Feature 2 -->
        <div class="partner-feature-card">
          <div class="partner-feature-icon partner-feature-icon--live">
            <span class="material-symbols-outlined">share_location</span>
          </div>
          <h3>Live Tracking Real-time</h3>
          <p>Giám sát vị trí xe theo thời gian thực trên nền tảng OpenMap 3D. Giúp bộ phận điều hành xử lý sự cố nhanh chóng, cảnh báo tốc độ tức thời.</p>
        </div>
        <!-- Feature 3 -->
        <div class="partner-feature-card">
          <div class="partner-feature-icon">
            <span class="material-symbols-outlined">trending_up</span>
          </div>
          <h3>Báo cáo thông minh</h3>
          <p>Hệ thống thống kê doanh thu, vé bán, và tỷ lệ lấp đầy xe được trực quan hoá qua các biểu đồ sinh động. Giúp nhà xe ra quyết định chính xác.</p>
        </div>
        <!-- Feature 4 -->
        <div class="partner-feature-card">
          <div class="partner-feature-icon">
            <span class="material-symbols-outlined">smartphone</span>
          </div>
          <h3>Ứng dụng Tài xế riêng biệt</h3>
          <p>Cung cấp ứng dụng chuyên dụng cho tài xế để nhận chuyến, xem danh sách đón khách và dẫn đường tự động tránh tắc nghẽn giao thông.</p>
        </div>
      </div>
    </section>

    <!-- HIGHLIGHT SECTION -->
    <section class="partner-highlight">
      <div class="partner-highlight__content">
        <h2>Sẵn sàng mở rộng quy mô kinh doanh?</h2>
        <p>Gia nhập hệ thống BusSafe ngay hôm nay để tiếp cận hàng triệu khách hàng trực tuyến và tối ưu hoá chi phí vận hành.</p>
        <button @click="goRegister" class="partner-btn partner-btn--primary partner-btn--large">
          Trở thành đối tác
          <span class="material-symbols-outlined">rocket_launch</span>
        </button>
      </div>
      <div class="partner-highlight__image">
        <!-- Placeholder for a dashboard mock or abstract graphic -->
        <div class="partner-mockup">
          <div class="partner-mockup-header">
            <span></span><span></span><span></span>
          </div>
          <div class="partner-mockup-body">
            <div class="partner-mockup-sidebar"></div>
            <div class="partner-mockup-main">
              <div class="partner-mockup-card"></div>
              <div class="partner-mockup-card"></div>
              <div class="partner-mockup-chart"></div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.partner-page {
  font-family: 'Manrope', 'Inter', sans-serif;
  color: #1e293b;
  overflow-x: hidden;
}

/* --- HERO SECTION --- */
.partner-hero {
  position: relative;
  padding: 8rem 2rem 6rem;
  text-align: center;
  background: #f8fafc;
  overflow: hidden;
}

.partner-hero__bg {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  overflow: hidden;
  z-index: 0;
}

.partner-hero__blob {
  position: absolute;
  filter: blur(80px);
  opacity: 0.5;
  border-radius: 50%;
  animation: float 10s ease-in-out infinite alternate;
}

.partner-hero__blob--1 {
  width: 400px; height: 400px;
  background: #bfdbfe;
  top: -100px; left: -100px;
}

.partner-hero__blob--2 {
  width: 300px; height: 300px;
  background: #c7d2fe;
  bottom: -50px; right: -50px;
  animation-delay: -5s;
}

@keyframes float {
  0% { transform: translate(0, 0) scale(1); }
  100% { transform: translate(30px, 50px) scale(1.1); }
}

.partner-hero__content {
  position: relative;
  z-index: 1;
  max-width: 800px;
  margin: 0 auto;
}

.partner-badge {
  display: inline-block;
  padding: 0.4rem 1rem;
  background: #eff6ff;
  color: #2563eb;
  font-size: 0.85rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-radius: 50px;
  margin-bottom: 1.5rem;
  border: 1px solid #bfdbfe;
}

.partner-title {
  font-size: 3.5rem;
  font-weight: 800;
  line-height: 1.2;
  margin: 0 0 1.5rem;
  color: #0f172a;
  letter-spacing: -0.03em;
}

.partner-title span {
  color: transparent;
  background: linear-gradient(135deg, #2563eb, #4f46e5);
  -webkit-background-clip: text;
  background-clip: text;
}

.partner-subtitle {
  font-size: 1.2rem;
  color: #475569;
  line-height: 1.6;
  margin: 0 auto 2.5rem;
  max-width: 600px;
}

.partner-actions {
  display: flex;
  gap: 1rem;
  justify-content: center;
}

.partner-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.85rem 1.8rem;
  border-radius: 12px;
  font-size: 1.05rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  font-family: inherit;
}

.partner-btn--primary {
  background: linear-gradient(135deg, #2563eb, #4f46e5);
  color: white;
  border: none;
  box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
}

.partner-btn--primary:hover {
  box-shadow: 0 12px 28px rgba(37, 99, 235, 0.35);
  transform: translateY(-2px);
}

.partner-btn--outline {
  background: transparent;
  color: #475569;
  border: 2px solid #cbd5e1;
}

.partner-btn--outline:hover {
  background: #f1f5f9;
  border-color: #94a3b8;
  color: #0f172a;
}

/* --- STATS --- */
.partner-stats {
  max-width: 1000px;
  margin: -3rem auto 4rem;
  position: relative;
  z-index: 10;
  padding: 0 1.5rem;
}

.partner-stats__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  background: white;
  border-radius: 24px;
  padding: 2rem;
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.06);
  border: 1px solid #f1f5f9;
}

.partner-stat-item {
  text-align: center;
  border-right: 1px solid #e2e8f0;
}

.partner-stat-item:last-child {
  border-right: none;
}

.partner-stat-num {
  font-size: 2.5rem;
  font-weight: 800;
  color: #2563eb;
  margin: 0 0 0.5rem;
}

.partner-stat-label {
  font-size: 0.95rem;
  font-weight: 600;
  color: #64748b;
  margin: 0;
}

/* --- FEATURES --- */
.partner-features {
  padding: 5rem 1.5rem;
  max-width: 1200px;
  margin: 0 auto;
}

.partner-section-header {
  text-align: center;
  margin-bottom: 4rem;
}

.partner-section-header h2 {
  font-size: 2.5rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 1rem;
}

.partner-section-header p {
  font-size: 1.1rem;
  color: #64748b;
  max-width: 600px;
  margin: 0 auto;
}

.partner-features__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 2rem;
}

.partner-feature-card {
  background: #fff;
  padding: 2.5rem 2rem;
  border-radius: 24px;
  border: 1px solid #e2e8f0;
  transition: all 0.3s ease;
}

.partner-feature-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
  border-color: #bfdbfe;
}

.partner-feature-icon {
  width: 60px;
  height: 60px;
  border-radius: 16px;
  background: #eff6ff;
  color: #2563eb;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.5rem;
}

.partner-feature-icon .material-symbols-outlined {
  font-size: 32px;
}

.partner-feature-icon--live {
  background: #fdf2f8;
  color: #db2777;
}

.partner-feature-card h3 {
  font-size: 1.25rem;
  font-weight: 800;
  color: #1e293b;
  margin: 0 0 1rem;
}

.partner-feature-card p {
  font-size: 0.95rem;
  color: #64748b;
  line-height: 1.6;
  margin: 0;
}

/* --- HIGHLIGHT --- */
.partner-highlight {
  display: grid;
  grid-template-columns: 1fr 1fr;
  align-items: center;
  gap: 4rem;
  background: #0f172a;
  color: white;
  margin: 4rem 1.5rem 6rem;
  padding: 4rem;
  border-radius: 32px;
  max-width: 1200px;
  margin-left: auto;
  margin-right: auto;
}

.partner-highlight__content h2 {
  font-size: 2.5rem;
  font-weight: 800;
  margin: 0 0 1.5rem;
  line-height: 1.2;
}

.partner-highlight__content p {
  font-size: 1.1rem;
  color: #94a3b8;
  line-height: 1.6;
  margin: 0 0 2.5rem;
}

.partner-btn--large {
  padding: 1rem 2.5rem;
  font-size: 1.15rem;
}

/* Abstract Mockup */
.partner-highlight__image {
  perspective: 1000px;
}

.partner-mockup {
  background: #1e293b;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  border: 1px solid #334155;
  transform: rotateY(-10deg) rotateX(5deg);
  transition: transform 0.5s ease;
}

.partner-mockup:hover {
  transform: rotateY(0) rotateX(0);
}

.partner-mockup-header {
  height: 30px;
  background: #0f172a;
  display: flex;
  align-items: center;
  padding: 0 1rem;
  gap: 6px;
}

.partner-mockup-header span {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #475569;
}

.partner-mockup-body {
  display: flex;
  height: 250px;
}

.partner-mockup-sidebar {
  width: 60px;
  background: #0f172a;
  border-right: 1px solid #1e293b;
}

.partner-mockup-main {
  flex: 1;
  padding: 1.5rem;
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}

.partner-mockup-card {
  width: calc(50% - 0.5rem);
  height: 60px;
  background: #334155;
  border-radius: 8px;
}

.partner-mockup-chart {
  width: 100%;
  height: 120px;
  background: linear-gradient(to top, rgba(37, 99, 235, 0.2), transparent);
  border-radius: 8px;
  border: 1px solid #475569;
  border-bottom: 4px solid #3b82f6;
}

/* --- RESPONSIVE --- */
@media (max-width: 992px) {
  .partner-highlight {
    grid-template-columns: 1fr;
    padding: 3rem 2rem;
    gap: 3rem;
  }
  
  .partner-title {
    font-size: 2.8rem;
  }
}

@media (max-width: 768px) {
  .partner-hero {
    padding: 6rem 1.5rem 4rem;
  }

  .partner-title {
    font-size: 2.2rem;
  }
  
  .partner-stats {
    margin-top: 2rem;
  }

  .partner-stats__grid {
    grid-template-columns: 1fr;
    gap: 2rem;
  }

  .partner-stat-item {
    border-right: none;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 2rem;
  }
  
  .partner-stat-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
  }
  
  .partner-actions {
    flex-direction: column;
  }
}
</style>
