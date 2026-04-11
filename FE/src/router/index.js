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
          path: 'nha-xe',
          name: 'admin-nha-xe',
          component: () => import('../views/admin/nha-xe/NhaXeView.vue'),
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
          path: 'voucher',
          name: 'operator-voucher',
          component: () => import('../views/operator/voucher/VoucherView.vue'),
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
          path: 'canh-bao',
          name: 'operator-canh-bao',
          component: () => import('../views/operator/canh-bao/CanhBaoView.vue'),
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
          path: 'driver-login',
          name: 'driver-login',
          component: () => import('../views/auth/DriverLoginView.vue'),
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
