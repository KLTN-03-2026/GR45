import { createRouter, createWebHistory } from 'vue-router';

import AuthLayout from '../layouts/AuthLayout.vue';
import BlankLayout from '../layouts/BlankLayout.vue';
import DashboardShellLayout from '../layouts/DashboardShellLayout.vue';

import { checkClientLogin } from './guards/checkClientLogin';
import { checkAdminLogin } from './guards/checkAdminLogin';
import { checkOperatorLogin } from './guards/checkOperatorLogin';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/auth/login' },

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
      path: '/profile',
      component: BlankLayout,
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
