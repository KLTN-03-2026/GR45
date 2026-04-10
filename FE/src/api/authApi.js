import axiosClient from './axiosClient.js';

const authApi = {
  // --- KHÁCH HÀNG ---
  clientRegister: (data) => axiosClient.post('/v1/dang-ky', data),
  clientLogin: (data) => axiosClient.post('/v1/dang-nhap', data),
  clientLogout: () => axiosClient.post('/v1/dang-xuat'),

  // --- NHÀ XE ---
  operatorLogin: (data) => axiosClient.post('/v1/nha-xe/dang-nhap', data),
  operatorLogout: () => axiosClient.post('/v1/nha-xe/dang-xuat'),

  // --- TÀI XẾ ---
  driverLogin: (data) => axiosClient.post('/v1/tai-xe/dang-nhap', data),
  driverLogout: () => axiosClient.post('/v1/tai-xe/dang-xuat'),

  // --- ADMIN ---
  adminLogin: (data) => axiosClient.post('/v1/admin/login', data),
  adminLogout: () => axiosClient.post('/v1/admin/logout'),
  adminRefresh: () => axiosClient.post('/v1/admin/refresh'),
};

export default authApi;
