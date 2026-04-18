import axiosClient from './axiosClient.js';

const operatorApi = {
  // --- PROFILE NHÀ XE ---
  getProfile: () => axiosClient.get('/v1/nha-xe/profile'),
  changePassword: (data) => axiosClient.post('/v1/nha-xe/doi-mat-khau', data),

  // --- XE / PHƯƠNG TIỆN ---
  getLoaiXe: () => axiosClient.get('/v1/nha-xe/loai-xe'),
  getDrivers: (params) => axiosClient.get('/v1/nha-xe/tai-xe', { params }),
  getVehicles: (params) => axiosClient.get('/v1/nha-xe/xe', { params }),
  getVehicleDetails: (id) => axiosClient.get(`/v1/nha-xe/xe/${id}`),
  createVehicle: (data) => axiosClient.post('/v1/nha-xe/xe', data),
  updateVehicle: (id, data) => axiosClient.put(`/v1/nha-xe/xe/${id}`, data),
  deleteVehicle: (id) => axiosClient.delete(`/v1/nha-xe/xe/${id}`),
  getSeatTypes: () => axiosClient.get('/v1/nha-xe/loai-ghe'),
  getVehicleSeats: (id) => axiosClient.get(`/v1/nha-xe/xe/${id}/ghe`),
  createVehicleSeat: (id, data) => axiosClient.post(`/v1/nha-xe/xe/${id}/ghe`, data),
  clearVehicleSeats: (id) => axiosClient.delete(`/v1/nha-xe/xe/${id}/ghe`),
  updateVehicleSeat: (id, seatId, data) => axiosClient.put(`/v1/nha-xe/xe/${id}/ghe/${seatId}`, data),
  deleteVehicleSeat: (id, seatId) => axiosClient.delete(`/v1/nha-xe/xe/${id}/ghe/${seatId}`),

  // --- TUYẾN ĐƯỜNG ---
  getRoutes: (params) => axiosClient.get('/v1/nha-xe/tuyen-duong', { params }),
  getRouteDetails: (id) => axiosClient.get(`/v1/nha-xe/tuyen-duong/${id}`),
  createRoute: (data) => axiosClient.post('/v1/nha-xe/tuyen-duong', data),
  updateRoute: (id, data) => axiosClient.put(`/v1/nha-xe/tuyen-duong/${id}`, data),
  deleteRoute: (id) => axiosClient.delete(`/v1/nha-xe/tuyen-duong/${id}`),

  // --- VÉ ---
  getTickets: (params) => axiosClient.get('/v1/nha-xe/ve', { params }),
  getTicketDetail: (id) => axiosClient.get(`/v1/nha-xe/ve/${id}`),
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

  // --- ĐÁNH GIÁ ---
  getRatings: () => axiosClient.get('/v1/nha-xe/ratings'),
};

export default operatorApi;
