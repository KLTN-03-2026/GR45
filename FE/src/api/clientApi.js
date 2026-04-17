import axiosClient from './axiosClient.js';

const clientApi = {
  getProfile: () => axiosClient.get('/v1/profile'),
  updateProfile: (data) => axiosClient.put('/v1/profile', data),
  changePassword: (data) => axiosClient.post('/v1/doi-mat-khau', data),

  getProvinces: () => axiosClient.get('/v1/tinh-thanh'),
  searchTrips: (params) => axiosClient.get('/v1/chuyen-xe/search', { params }),
  getTripSeats: (tripId) => axiosClient.get(`/v1/chuyen-xe/${tripId}/ghe`),
  getTripStops: (tripId) => axiosClient.get(`/v1/chuyen-xe/${tripId}/tram-dung`),
  getPublicVouchers: (params) => axiosClient.get('/v1/voucher/public', { params }),
  getTickets: (params) => axiosClient.get('/v1/ve', { params }),
  bookTicket: (data) => axiosClient.post('/v1/ve/dat-ve', data),

  getTripRatings: (tripId, params) =>
    axiosClient.get(`/v1/chuyen-xe/${tripId}/danh-gia`, { params }),
  submitRating: (data) => axiosClient.post('/v1/rating', data),
  getRatingByTicket: (ticketCode) => axiosClient.get(`/v1/rating/${ticketCode}`),
  getRatingByTrip: (tripId) => axiosClient.get(`/v1/rating/trip/${tripId}`),
  getPendingRatings: () => axiosClient.get('/v1/pending-rating'),
  getMyRatings: () => axiosClient.get('/v1/my-ratings'),
};

export default clientApi;
