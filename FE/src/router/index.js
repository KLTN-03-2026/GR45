import { createRouter, createWebHistory } from 'vue-router';

// Nhập Layouts
import DefaultLayout from '../layouts/DefaultLayout.vue';
import AdminLayout from '../layouts/AdminLayout.vue';
import OperatorLayout from '../layouts/OperatorLayout.vue';
import DriverLayout from '../layouts/DriverLayout.vue';
import AuthLayout from '../layouts/AuthLayout.vue';
import ErrorLayout from '../layouts/ErrorLayout.vue';

// Nhập Guards (mỗi role có 1 file guard riêng)
import { checkAdminLogin } from './guards/checkAdminLogin.js';
import { checkClientLogin } from './guards/checkClientLogin.js';
import { checkOperatorLogin } from './guards/checkOperatorLogin.js';
import { checkDriverLogin } from './guards/checkDriverLogin.js';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [

    // ============================================================
    // 1. KHÁCH HÀNG — DefaultLayout
    //    Route công khai: không cần đăng nhập để xem trang chủ
    // ============================================================
    {
      path: '/',
      component: DefaultLayout,
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('../views/user/HomeView.vue'),
        },
        {
          path: 'search',
          name: 'search',
          component: () => import('../views/user/SearchTripView.vue'),
        },
        // Các trang cần đăng nhập khách hàng thêm beforeEnter: checkClientLogin
        {
          path: 'dat-ve',
          name: 'booking',
          component: () => import('../views/user/BookingView.vue'),
          beforeEnter: checkClientLogin,
        },
        {
          path: 'profile',
          name: 'client-profile',
          component: () => import('../views/user/ProfileView.vue'),
          beforeEnter: checkClientLogin,
        },
        {
          path: 'lich-su-dat-ve',
          name: 'client-ticket-history',
          component: () => import('../views/user/TicketHistoryView.vue'),
          beforeEnter: checkClientLogin,
        },
        {
          path: 'theo-doi-chuyen-xe',
          name: 'tracking-relative',
          component: () => import('../views/user/TrackingView.vue'),
        },
        {
          path: 'hop-tac',
          name: 'partner',
          component: () => import('../views/user/PartnerView.vue'),
        },
        {
          path: 've-cua-toi',
          redirect: { name: 'client-ticket-history' },
        },
      ],
    },

    // ============================================================
    // 2. ADMIN — AdminLayout
    //    Toàn bộ khu vực /admin yêu cầu đăng nhập Admin
    // ============================================================
    {
      path: '/admin',
      component: AdminLayout,
      beforeEnter: checkAdminLogin,
      redirect: '/admin/dashboard',
      children: [
        {
          path: 'dashboard',
          name: 'admin-dashboard',
          component: () => import('../views/admin/dashboard/AdminDashboardView.vue'),
        },
        {
          path: 'tuyen-duong',
          name: 'admin-tuyen-duong',
          component: () => import('../views/admin/tuyen-duong/TuyenDuongView.vue'),
        },
        {
          path: 'chuyen-xe',
          name: 'admin-chuyen-xe',
          component: () => import('../views/admin/chuyen-xe/ChuyenXeView.vue'),
        },
        {
          path: 'live-tracking',
          name: 'admin-live-tracking',
          component: () => import('../views/admin/tracking/LiveTrackingView.vue'),
        },
        {
          path: 'lich-su-hanh-trinh',
          name: 'admin-lich-su-hanh-trinh',
          component: () => import('../views/admin/tracking/TrackingView.vue'),
        },
        {
          path: 've',
          name: 'admin-ve',
          component: () => import('../views/admin/ve/VeView.vue'),
        },
        {
          path: 'voucher',
          name: 'admin-voucher',
          component: () => import('../views/admin/voucher/VoucherView.vue'),
        },
        {
          path: 'danh-gia',
          name: 'admin-danh-gia',
          component: () => import('../views/admin/ratings/RatingsView.vue'),
        },
        {
          path: 'nhan-vien',
          name: 'admin-nhan-vien',
          component: () => import('../views/admin/nhan-vien/NhanVienView.vue'),
        },
        {
          path: 'nha-xe',
          name: 'admin-nha-xe',
          component: () => import('../views/admin/nha-xe/NhaXeView.vue'),
        },
        {
          path: 'tai-xe',
          name: 'admin-tai-xe',
          component: () => import('../views/admin/tai-xe/TaiXeView.vue'),
        },
        {
          path: 'phuong-tien',
          name: 'admin-phuong-tien',
          component: () => import('../views/admin/phuong-tien/PhuongTienView.vue'),
        },
        {
          path: 'khach-hang',
          name: 'admin-khach-hang',
          component: () => import('../views/admin/khach-hang/KhachHangView.vue'),
        },
        {
          path: 'phan-quyen',
          name: 'admin-phan-quyen',
          component: () => import('../views/admin/phan-quyen/PhanQuyenView.vue'),
        },
        {
          path: 'cau-hinh',
          name: 'admin-cau-hinh',
          component: () => import('../views/admin/cau-hinh/CauHinhView.vue'),
        },
        {
          path: 'ho-tro',
          name: 'admin-ho-tro',
          component: () => import('../views/admin/ho-tro/HoTroView.vue'),
        },
        {
          path: 'thong-ke',
          name: 'admin-thong-ke',
          component: () => import('../views/admin/thong-ke/ThongKeView.vue'),
        },
        {
          path: 'cai-dat',
          name: 'admin-cai-dat',
          component: () => import('../views/admin/cai-dat/CaiDatView.vue'),
        },
      ],
    },

    // ============================================================
    // 3. NHÀ XE (OPERATOR) — OperatorLayout
    //    Toàn bộ khu vực /nha-xe yêu cầu đăng nhập Nhà Xe
    // ============================================================
    {
      path: '/nha-xe',
      component: OperatorLayout,
      beforeEnter: checkOperatorLogin,
      redirect: '/nha-xe/dashboard',
      children: [
        {
          path: 'dashboard',
          name: 'operator-dashboard',
          component: () => import('../views/operator/dashboard/DashboardView.vue'),
        },
        {
          path: 've',
          name: 'operator-ve',
          component: () => import('../views/operator/ve/VeView.vue'),
        },
        {
          path: 'voucher',
          name: 'operator-voucher',
          component: () => import('../views/operator/voucher/VoucherView.vue'),
        },
        {
          path: 'danh-gia',
          name: 'operator-danh-gia',
          component: () => import('../views/operator/ratings/RatingsView.vue'),
        },
        {
          path: 'tuyen-duong',
          name: 'operator-tuyen-duong',
          component: () => import('../views/operator/tuyen-duong/TuyenDuongView.vue'),
        },
        {
          path: 'chuyen-xe',
          name: 'operator-chuyen-xe',
          component: () => import('../views/operator/chuyen-xe/ChuyenXeView.vue'),
        },
        {
          path: 'live-tracking',
          name: 'operator-live-tracking',
          component: () => import('../views/operator/tracking/LiveTrackingView.vue'),
        },
        {
          path: 'lich-su-hanh-trinh',
          name: 'operator-lich-su-hanh-trinh',
          component: () => import('../views/operator/tracking/TrackingView.vue'),
        },
        {
          path: 'tai-xe',
          name: 'operator-tai-xe',
          component: () => import('../views/operator/tai-xe/TaiXeView.vue'),
        },
        {
          path: 'phuong-tien',
          name: 'operator-phuong-tien',
          component: () => import('../views/operator/phuong-tien/PhuongTienView.vue'),
        },
        {
          path: 'canh-bao',
          name: 'operator-canh-bao',
          component: () => import('../views/operator/canh-bao/CanhBaoView.vue'),
        },
        {
          path: 'ho-tro',
          name: 'operator-ho-tro',
          component: () => import('../views/operator/ho-tro/HoTroView.vue'),
        },
        {
          path: 'thong-ke',
          name: 'operator-thong-ke',
          component: () => import('../views/operator/thong-ke/ThongKeView.vue'),
        },
        {
          path: 'phan-quyen',
          name: 'operator-phan-quyen',
          component: () => import('../views/operator/phan-quyen/PhanQuyenView.vue'),
        },
        {
          path: 'cai-dat',
          name: 'operator-cai-dat',
          component: () => import('../views/operator/cai-dat/CaiDatView.vue'),
        },
      ],
    },

    // ============================================================
    // 4. TÀI XẾ — DriverLayout
    //    Toàn bộ khu vực /tai-xe yêu cầu đăng nhập Tài Xế
    // ============================================================
    {
      path: '/tai-xe',
      component: DriverLayout,
      beforeEnter: checkDriverLogin,
      redirect: '/tai-xe/dashboard',
      children: [
        {
          path: 'dashboard',
          name: 'driver-dashboard',
          component: () => import('../views/driver/DashboardView.vue'),
        },
        {
          path: 'lich-trinh',
          name: 'driver-lich-trinh',
          component: () => import('../views/driver/LichTrinhView.vue'),
        },
        {
          path: 'ho-tro',
          name: 'driver-ho-tro',
          component: () => import('../views/driver/HoTroView.vue'),
        },
        {
          path: 'cai-dat',
          name: 'driver-cai-dat',
          component: () => import('../views/driver/CaiDatView.vue'),
        },
      ],
    },

    // ============================================================
    // 5. AUTH — AuthLayout
    //    Trang đăng nhập / đăng ký (không cần guard)
    // ============================================================
    {
      path: '/auth',
      component: AuthLayout,
      children: [
        {
          path: 'login',
          name: 'client-login',
          component: () => import('../views/auth/ClientLoginView.vue'),
        },
        {
          path: 'register',
          name: 'client-register',
          component: () => import('../views/auth/ClientRegisterView.vue'),
        },
        {
          path: 'admin-login',
          name: 'admin-login',
          component: () => import('../views/auth/AdminLoginView.vue'),
        },
        {
          path: 'operator-login',
          name: 'operator-login',
          component: () => import('../views/auth/OperatorLoginView.vue'),
        },
        {
          path: 'operator-register',
          name: 'operator-register',
          component: () => import('../views/auth/OperatorRegister.vue'),
        },
        {
          path: 'driver-login',
          name: 'driver-login',
          component: () => import('../views/auth/DriverLoginView.vue'),
        },
        {
          path: 'forgot-password',
          name: 'forgot-password',
          component: () => import('../views/auth/ForgotPasswordView.vue'),
        },
        {
          path: 'reset-password',
          name: 'reset-password',
          component: () => import('../views/auth/ResetPasswordView.vue'),
        },
        {
          path: 'activate-account',
          name: 'activate-account',
          component: () => import('../views/auth/ActivateAccountView.vue'),
        },
        {
          path: 'check-email',
          name: 'check-email',
          component: () => import('../views/auth/CheckEmailView.vue'),
        },
      ],
    },

    // ============================================================
    // 6. LỖI — ErrorLayout (404)
    // ============================================================
    {
      path: '/:pathMatch(.*)*',
      component: ErrorLayout,
      children: [
        {
          path: '',
          name: 'not-found',
          component: () => import('../views/error/NotFoundView.vue'),
        },
      ],
    },

  ],
});

export default router;
