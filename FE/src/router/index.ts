import { createRouter, createWebHistory } from 'vue-router';

import DefaultLayout from '../layouts/DefaultLayout.vue';
import AdminLayout from '../layouts/AdminLayout.vue';
import OperatorLayout from '../layouts/OperatorLayout.vue';
import DriverLayout from '../layouts/DriverLayout.vue';
import AuthLayout from '../layouts/AuthLayout.vue';
import ErrorLayout from '../layouts/ErrorLayout.vue';


import { checkClientLogin } from './guards/checkClientLogin';
import { checkAdminLogin } from './guards/checkAdminLogin';
import { checkOperatorLogin } from './guards/checkOperatorLogin';
import { checkDriverLogin } from './guards/checkDriverLogin.js';
import AdminLoginView from '../views/auth/AdminLoginView.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
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

    {
      path: '/admin',
      component: AdminLayout,
      beforeEnter: checkAdminLogin,
      redirect: '/admin/dashboard',
      children: [
        {
          path: 'dashboard',
          name: 'admin-dashboard',
          meta: { title: 'Dashboard (Admin)' },
          component: () => import('../views/admin/dashboard/AdminDashboardView.vue'),
        },
        {
          path: 'xe',
          name: 'admin-xe',
          meta: { title: 'Quản lý xe & ghế (Admin)' },
          component: () => import('../views/admin/phuong-tien/PhuongTienView.vue'),
        },
        {
          path: 'danh-gia',
          name: 'admin-danh-gia',
          meta: { title: 'Đánh giá chuyến xe (Admin)' },
          component: () => import('../views/admin/ratings/RatingsView.vue'),
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

    {
      path: '/nha-xe',
      component: OperatorLayout,
      beforeEnter: checkOperatorLogin,
      children: [
        { path: '', redirect: '/nha-xe/xe' },
        {
          path: 'xe',
          name: 'operator-xe',
          meta: { title: 'Quản lý xe & ghế (Nhà xe)' },
          component: () => import('../views/operator/phuong-tien/PhuongTienView.vue'),
        },
        {
          path: 'danh-gia',
          name: 'operator-danh-gia',
          meta: { title: 'Đánh giá chuyến xe (Nhà xe)' },
          component: () => import('../views/operator/ratings/RatingsView.vue'),
        },
      ],
    },

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
      ],
    },

    {
      path: '/tai-xe',
      component: DriverLayout,
      beforeEnter: checkDriverLogin,
      redirect: '/tai-xe/dashboard',
      children: [
        {
          path: 'dashboard',
          name: 'driver-dashboard',
          meta: { title: 'Điều khiển chuyến (Tài xế)' },
          component: () => import('../views/driver/DashboardView.vue'),
        },
        {
          path: 'lich-trinh',
          name: 'driver-lich-trinh',
          meta: { title: 'Lịch trình chuyến xe' },
          component: () => import('../views/driver/LichTrinhView.vue'),
        },
        {
          path: 'ho-tro',
          name: 'driver-ho-tro',
          meta: { title: 'Hỗ trợ khẩn cấp' },
          component: () => import('../views/driver/HoTroView.vue'),
        },
        {
          path: 'cai-dat',
          name: 'driver-cai-dat',
          meta: { title: 'Cài đặt tài xế' },
          component: () => import('../views/driver/CaiDatView.vue'),
        },
      ],
    },

    {
      path: '/profile',
      component: DefaultLayout,
      beforeEnter: checkClientLogin,
      children: [
        {
          path: '',
          name: 'client-profile',
          component: () => import('../views/user/ProfileView.vue'),
        },
      ],
    },

    { path: '/:pathMatch(.*)*', redirect: '/auth/login' },
  ],
});

export default router;
