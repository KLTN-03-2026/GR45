import axiosClient from './axiosClient.js';

const operatorApi = {

  // --- TUYẾN ĐƯỜNG ---
  getRoutes: (params) => axiosClient.get('/v1/nha-xe/tuyen-duong', { params }),
  getRouteDetails: (id) => axiosClient.get(`/v1/nha-xe/tuyen-duong/${id}`),
  createRoute: (data) => axiosClient.post('/v1/nha-xe/tuyen-duong', data),
  updateRoute: (id, data) => axiosClient.put(`/v1/nha-xe/tuyen-duong/${id}`, data),
  deleteRoute: (id) => axiosClient.delete(`/v1/nha-xe/tuyen-duong/${id}`),

  // --- VOUCHER ---
  getVouchers: () => axiosClient.get('/v1/nha-xe/voucher'),
  createVoucher: (data) => axiosClient.post('/v1/nha-xe/voucher', data),
  updateVoucher: (id, data) => axiosClient.put(`/v1/nha-xe/voucher/${id}`, data),
  deleteVoucher: (id) => axiosClient.delete(`/v1/nha-xe/voucher/${id}`),

};

export default operatorApi;
