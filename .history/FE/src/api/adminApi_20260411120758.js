import axiosClient from './axiosClient.js';

const adminApi = {
        // --- PROFILE ---
  getMe: () => axiosClient.get('/v1/admin/me'),

   // --- QUẢN LÝ KHÁCH HÀNG ---
  getClients: (params) => axiosClient.get('/v1/admin/khach-hang', { params }),
  getClientDetails: (id) => axiosClient.get(`/v1/admin/khach-hang/${id}`),
  createClient: (data) => axiosClient.post('/v1/admin/khach-hang', data),
  updateClient: (id, data) => axiosClient.put(`/v1/admin/khach-hang/${id}`, data),
  toggleClientStatus: (id) => axiosClient.patch(`/v1/admin/khach-hang/${id}/trang-thai`),
  deleteClient: (id) => axiosClient.delete(`/v1/admin/khach-hang/${id}`),

  // --- QUẢN LÝ NHÀ XE ---
  getOperators: (params) => axiosClient.get('/v1/admin/nha-xe', { params }),
  getOperatorDetails: (id) => axiosClient.get(`/v1/admin/nha-xe/${id}`),
  createOperator: (data) => axiosClient.post('/v1/admin/nha-xe', data),
 updateOperator: (id, data) => axiosClient.put(`/v1/admin/nha-xe/${id}`, data),
  toggleOperatorStatus: (id) => axiosClient.patch(`/v1/admin/nha-xe/${id}/trang-thai`),
  deleteOperator: (id) => axiosClient.delete(`/v1/admin/nha-xe/${id}`),


  // --- QUẢN LÝ VOUCHER ---
  getVouchers: () => axiosClient.get('/v1/admin/voucher'),
  approveVoucher: (id, data) => axiosClient.patch(`/v1/admin/voucher/${id}/duyet`, data),
};
export default adminApi;    