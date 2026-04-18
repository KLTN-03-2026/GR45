import { createRouter, createWebHistory } from 'vue-router';

import AuthLayout from '../layouts/AuthLayout.vue';
import DashboardShellLayout from '../layouts/DashboardShellLayout.vue';
import DefaultLayout from '../layouts/DefaultLayout.vue';
import DriverLayout from '../layouts/DriverLayout.vue';

import { checkClientLogin } from './guards/checkClientLogin';
import { checkAdminLogin } from './guards/checkAdminLogin';
import { checkOperatorLogin } from './guards/checkOperatorLogin';
import { checkDriverLogin } from './guards/checkDriverLogin.js';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/auth/login' },

    {
      path: '/dat-ve',
      component: DefaultLayout,
      beforeEnter: checkClientLogin,
      children: [
        {
          path: '',
          name: 'booking',
          meta: { title: 'Đặt vé' },
          component: () => import('../views/user/BookingView.vue'),
        },
      ],
    },

    {
      path: '/search',
      component: DefaultLayout,
      children: [
        {
          path: '',
          name: 'search-trip',
          meta: { title: 'Tìm chuyến xe' },
          component: () => import('../views/user/SearchTripView.vue'),
        },
      ],
    },
    {
      path: '/admin',
      component: DashboardShellLayout,
      beforeEnter: checkAdminLogin,
      children: [
        { path: '', redirect: '/admin/xe' },
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
      ],
    },

    {
      path: '/nha-xe',
      component: DashboardShellLayout,
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
