import axiosClient from './axiosClient.js';

const adminApi = {
  // --- PROFILE ---
  getMe: () => axiosClient.get('/v1/admin/me'),

  // --- QUẢN LÝ NHÂN VIÊN ---
  getStaffs: (params) => axiosClient.get('/v1/admin/nhan-vien', { params }),
  getStaffDetails: (id) => axiosClient.get(`/v1/admin/nhan-vien/${id}`),
  createStaff: (data) => axiosClient.post('/v1/admin/nhan-vien', data),
  updateStaff: (id, data) => axiosClient.put(`/v1/admin/nhan-vien/${id}`, data),
  toggleStaffStatus: (id) => axiosClient.patch(`/v1/admin/nhan-vien/${id}/trang-thai`),
  deleteStaff: (id) => axiosClient.delete(`/v1/admin/nhan-vien/${id}`),

  // --- QUẢN LÝ KHÁCH HÀNG ---
  getClients: (params) => axiosClient.get('/v1/admin/khach-hang', { params }),
  getClientDetails: (id) => axiosClient.get(`/v1/admin/khach-hang/${id}`),
  createClient: (data) => axiosClient.post('/v1/admin/khach-hang', data),
  updateClient: (id, data) => axiosClient.put(`/v1/admin/khach-hang/${id}`, data),
  toggleClientStatus: (id) => axiosClient.patch(`/v1/admin/khach-hang/${id}/trang-thai`),
  deleteClient: (id) => axiosClient.delete(`/v1/admin/khach-hang/${id}`),

  // --- QUẢN LÝ TUYẾN ĐƯỜNG ---
  getRoutes: (params) => axiosClient.get('/v1/admin/tuyen-duong', { params }),
  getRouteDetails: (id) => axiosClient.get(`/v1/admin/tuyen-duong/${id}`),
  createRoute: (data) => axiosClient.post('/v1/admin/tuyen-duong', data),
  updateRoute: (id, data) => axiosClient.put(`/v1/admin/tuyen-duong/${id}`, data),
  approveRoute: (id) => axiosClient.patch(`/v1/admin/tuyen-duong/${id}/duyet`),
  rejectRoute: (id) => axiosClient.patch(`/v1/admin/tuyen-duong/${id}/tu-choi`),
  deleteRoute: (id) => axiosClient.delete(`/v1/admin/tuyen-duong/${id}`),

  // --- QUẢN LÝ VÉ ---


  // --- QUẢN LÝ CHUYẾN XE ---
  autoGenerateTrips: () => axiosClient.post('/v1/admin/chuyen-xe/auto-generate'),
  getTrips: (params) => axiosClient.get('/v1/admin/chuyen-xe', { params }),
  getTripDetails: (id) => axiosClient.get(`/v1/admin/chuyen-xe/${id}`),
  createTrip: (data) => axiosClient.post('/v1/admin/chuyen-xe', data),
  updateTrip: (id, data) => axiosClient.put(`/v1/admin/chuyen-xe/${id}`, data),
  toggleTripStatus: (id) => axiosClient.patch(`/v1/admin/chuyen-xe/${id}/trang-thai`),
  deleteTrip: (id) => axiosClient.delete(`/v1/admin/chuyen-xe/${id}`),
  getTripSeats: (id) => axiosClient.get(`/v1/admin/chuyen-xe/${id}/so-do-ghe`),
  changeTripBus: (id, data) => axiosClient.put(`/v1/admin/chuyen-xe/${id}/doi-xe`, data),

  // --- QUẢN LÝ VOUCHER ---
  getVouchers: () => axiosClient.get('/v1/admin/voucher'),
  approveVoucher: (id, data) => axiosClient.patch(`/v1/admin/voucher/${id}/duyet`, data),

  // --- PHÂN QUYỀN & CHỨC NĂNG ---
  getMyPermissions: () => axiosClient.get('/v1/admin/phan-quyen'),
  getFunctions: () => axiosClient.get('/v1/admin/chuc-nangs'),
  getFunctionDetails: (id) => axiosClient.get(`/v1/admin/chuc-nangs/${id}`),
  createFunction: (data) => axiosClient.post('/v1/admin/chuc-nangs', data),
  updateFunction: (id, data) => axiosClient.put(`/v1/admin/chuc-nangs/${id}`, data),
  deleteFunction: (id) => axiosClient.delete(`/v1/admin/chuc-nangs/${id}`),

  // --- CHỨC VỤ & PHÂN QUYỀN CHỨC VỤ ---
  getRoles: () => axiosClient.get('/v1/admin/chuc-vus'),
  getRolePermissions: (id) => axiosClient.get(`/v1/admin/chuc-vus/${id}/phan-quyen`),
  syncRolePermissions: (id, data) => axiosClient.post(`/v1/admin/chuc-vus/${id}/phan-quyen`, data),

  // --- QUẢN LÝ THANH TOÁN ---

  // --- TIỆN ÍCH ---
  autoGenerateSeats: () => axiosClient.post('/v1/admin/xe/auto-generate-seats'),
};

export default adminApi;
