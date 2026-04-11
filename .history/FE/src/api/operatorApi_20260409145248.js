import axiosClient from './axiosClient.js';

const operatorApi = {
  // --- PROFILE NHÀ XE ---
  getProfile: () => axiosClient.get('/v1/nha-xe/profile'),
  changePassword: (data) => axiosClient.post('/v1/nha-xe/doi-mat-khau', data),

  // --- XE / PHƯƠNG TIỆN ---
  getVehicles: (params) => axiosClient.get('/v1/nha-xe/xe', { params }),
  getVehicleDetails: (id) => axiosClient.get(`/v1/nha-xe/xe/${id}`),
  createVehicle: (data) => axiosClient.post('/v1/nha-xe/xe', data),
  updateVehicle: (id, data) => axiosClient.put(`/v1/nha-xe/xe/${id}`, data),

  // --- TUYẾN ĐƯỜNG ---
  getRoutes: (params) => axiosClient.get('/v1/nha-xe/tuyen-duong', { params }),
  getRouteDetails: (id) => axiosClient.get(`/v1/nha-xe/tuyen-duong/${id}`),
  createRoute: (data) => axiosClient.post('/v1/nha-xe/tuyen-duong', data),
  updateRoute: (id, data) => axiosClient.put(`/v1/nha-xe/tuyen-duong/${id}`, data),
  deleteRoute: (id) => axiosClient.delete(`/v1/nha-xe/tuyen-duong/${id}`),

  // --- VÉ ---
  getTickets: (params) => axiosClient.get('/v1/nha-xe/ve', { params }),
  getTicketDetail: (id) => axiosClient.get(`/v1/nha-xe/ve/${id}`),
  bookTicket: (data) => axiosClient.post('/v1/nha-xe/ve/dat-ve', data),
  updateTicketStatus: (id, data) => axiosClient.patch(`/v1/nha-xe/ve/${id}/trang-thai`, data),
  cancelTicket: (id) => axiosClient.patch(`/v1/nha-xe/ve/${id}/huy`),

  // --- CHUYẾN XE ---
  getTrips: (params) => axiosClient.get(`/v1/nha-xe/chuyen-xe/`, { params }),
  getTripDetails: (id) => axiosClient.get(`/v1/nha-xe/chuyen-xe/${id}`),
  createTrip: (data) => axiosClient.post('/v1/nha-xe/chuyen-xe', data),
  updateTrip: (id, data) => axiosClient.put(`/v1/nha-xe/chuyen-xe/${id}`, data),
  changeTripStatus: (id) => axiosClient.patch(`/v1/nha-xe/chuyen-xe/${id}/trang-thai`),
  deleteTrip: (id) => axiosClient.delete(`/v1/nha-xe/chuyen-xe/${id}`),
  getTripSeats: (id) => axiosClient.get(`/v1/nha-xe/chuyen-xe/${id}/so-do-ghe`),
  changeTripBus: (id, data) => axiosClient.put(`/v1/nha-xe/chuyen-xe/${id}/doi-xe`, data),
  getTripTrackingHistory: (id, params) =>
    axiosClient.get(`/v1/nha-xe/chuyen-xe/${id}/tracking`, { params }),
  getTripTrackingLive: (id) => axiosClient.get(`/v1/nha-xe/chuyen-xe/${id}/tracking/live`),

  // --- VOUCHER ---
  getVouchers: () => axiosClient.get('/v1/nha-xe/voucher'),
  createVoucher: (data) => axiosClient.post('/v1/nha-xe/voucher', data),
  updateVoucher: (id, data) => axiosClient.put(`/v1/nha-xe/voucher/${id}`, data),
  deleteVoucher: (id) => axiosClient.delete(`/v1/nha-xe/voucher/${id}`),

  // --- TÀI XẾ ---
  getDrivers: (params) => axiosClient.get('/v1/nha-xe/tai-xe', { params }),
  getDriverDetails: (id) => axiosClient.get(`/v1/nha-xe/tai-xe/${id}`),
  createDriver: (data) => axiosClient.post('/v1/nha-xe/tai-xe', data),
  updateDriver: (id, data) => {
    if (data instanceof FormData) {
      if (!data.has("_method")) data.append("_method", "PUT");
      return axiosClient.post(`/v1/nha-xe/tai-xe/${id}`, data);
    }
    return axiosClient.put(`/v1/nha-xe/tai-xe/${id}`, data);
  },
  toggleDriverStatus: (id) =>
    axiosClient.patch(`/v1/nha-xe/tai-xe/${id}/trang-thai`),
  deleteDriver: (id) => axiosClient.delete(`/v1/nha-xe/tai-xe/${id}`),

  // --- CẢNH BÁO / BÁO ĐỘNG AI ---
  getAlarms: (params) => axiosClient.get('/v1/nha-xe/bao-dong', { params }),
  getAlarmDetails: (id) => axiosClient.get(`/v1/nha-xe/bao-dong/${id}`),
  toggleAlarmStatus: (id) =>
    axiosClient.patch(`/v1/nha-xe/bao-dong/${id}/trang-thai`),
};

export default operatorApi;
