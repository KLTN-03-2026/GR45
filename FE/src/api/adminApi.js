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

  // --- QUẢN LÝ NHÀ XE ---
  getOperators: (params) => axiosClient.get('/v1/admin/nha-xe', { params }),
  getOperatorDetails: (id) => axiosClient.get(`/v1/admin/nha-xe/${id}`),
  createOperator: (data) => axiosClient.post('/v1/admin/nha-xe', data),
 updateOperator: (id, data) => axiosClient.put(`/v1/admin/nha-xe/${id}`, data),
  toggleOperatorStatus: (id) => axiosClient.patch(`/v1/admin/nha-xe/${id}/trang-thai`),
  deleteOperator: (id) => axiosClient.delete(`/v1/admin/nha-xe/${id}`),

  // --- QUẢN LÝ TÀI XẾ ---
  getDrivers: (params) => axiosClient.get('/v1/admin/tai-xe', { params }),
  getDriverDetails: (id) => axiosClient.get(`/v1/admin/tai-xe/${id}`),
  createDriver: (data) => axiosClient.post('/v1/admin/tai-xe', data),
  updateDriver: (id, data) => {
    if (data instanceof FormData) {
      if (!data.has('_method')) data.append('_method', 'PUT');
      return axiosClient.post(`/v1/admin/tai-xe/${id}`, data);
    }
    return axiosClient.put(`/v1/admin/tai-xe/${id}`, data);
  },
  toggleDriverStatus: (id) => axiosClient.patch(`/v1/admin/tai-xe/${id}/trang-thai`),
  deleteDriver: (id) => axiosClient.delete(`/v1/admin/tai-xe/${id}`),

  // --- QUẢN LÝ XE ---
  getVehicles: (params) => axiosClient.get('/v1/admin/xe', { params }),
  getVehicleDetails: (id) => axiosClient.get(`/v1/admin/xe/${id}`),
  createVehicle: (data) => axiosClient.post('/v1/admin/xe', data),
  updateVehicle: (id, data) => axiosClient.put(`/v1/admin/xe/${id}`, data),
  deleteVehicle: (id) => axiosClient.delete(`/v1/admin/xe/${id}`),
  updateVehicleStatus: (id, data) => axiosClient.patch(`/v1/admin/xe/${id}/trang-thai`, data),

  // --- QUẢN LÝ TUYẾN ĐƯỜNG ---
  getRoutes: (params) => axiosClient.get('/v1/admin/tuyen-duong', { params }),
  getRouteDetails: (id) => axiosClient.get(`/v1/admin/tuyen-duong/${id}`),
  createRoute: (data) => axiosClient.post('/v1/admin/tuyen-duong', data),
  updateRoute: (id, data) => axiosClient.put(`/v1/admin/tuyen-duong/${id}`, data),
  approveRoute: (id) => axiosClient.patch(`/v1/admin/tuyen-duong/${id}/duyet`),
  rejectRoute: (id) => axiosClient.patch(`/v1/admin/tuyen-duong/${id}/tu-choi`),
  deleteRoute: (id) => axiosClient.delete(`/v1/admin/tuyen-duong/${id}`),

  // --- QUẢN LÝ VÉ ---
  getTickets: (params) => axiosClient.get('/v1/admin/ve', { params }),
  getTicketDetails: (id) => axiosClient.get(`/v1/admin/ve/${id}`),
  bookTicket: (data) => axiosClient.post('/v1/admin/ve/dat-ve', data),
  updateTicketStatus: (id, data) => axiosClient.patch(`/v1/admin/ve/${id}/trang-thai`, data),
  cancelTicket: (id) => axiosClient.patch(`/v1/admin/ve/${id}/huy`),

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
  getTripTrackingHistory: (id, params) =>
    axiosClient.get(`/v1/admin/chuyen-xe/${id}/tracking`, { params }),
  getTripTrackingLive: (id) => axiosClient.get(`/v1/admin/chuyen-xe/${id}/tracking/live`),

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
  // GET /api/v1/admin/thanh-toan?search=...&trang_thai=1&phuong_thuc=1&tu_ngay=...&den_ngay=...&per_page=15
  getPayments: (params) => axiosClient.get('/v1/admin/thanh-toan', { params }),
  // GET /api/v1/admin/thanh-toan/{id}
  getPaymentDetails: (id) => axiosClient.get(`/v1/admin/thanh-toan/${id}`),
  // GET /api/v1/admin/thanh-toan/thong-ke?tu_ngay=...&den_ngay=...
  getPaymentStats: (params) => axiosClient.get('/v1/admin/thanh-toan/thong-ke', { params }),

  // --- TIỆN ÍCH ---
  autoGenerateSeats: () => axiosClient.post('/v1/admin/xe/auto-generate-seats'),
};

export default adminApi;
