import axiosClient from './axiosClient.js';

const clientApi = {
  // --- PROFILE ---
  getProfile: () => axiosClient.get('/v1/profile'),
  updateProfile: (data) => axiosClient.put('/v1/profile', data),
  changePassword: (data) => axiosClient.post('/v1/doi-mat-khau', data),

  // --- TÌM KIẾM & ĐẶT VÉ ---
  getProvinces: () => axiosClient.get('/v1/tinh-thanh'),
  searchTrips: (params) => axiosClient.get('/v1/chuyen-xe/search', { params }),
  getTripSeats: (tripId) => axiosClient.get(`/v1/chuyen-xe/${tripId}/ghe`),
  getTripStops: (tripId) => axiosClient.get(`/v1/chuyen-xe/${tripId}/tram-dung`),
  getPublicVouchers: (params) => axiosClient.get('/v1/voucher/public', { params }),
  bookTicket: (data) => axiosClient.post('/v1/ve/dat-ve', data),
  getTickets: (params) => axiosClient.get('/v1/ve', { params }),
};

export default clientApi;
