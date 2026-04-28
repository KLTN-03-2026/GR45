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
  /** Danh sách đánh giá theo chuyến — API công khai */
  getTripRatings: (tripId, params) => axiosClient.get(`/v1/chuyen-xe/${tripId}/danh-gia`, { params }),
  getPublicVouchers: (params) => axiosClient.get('/v1/voucher/public', { params }),
  bookTicket: (data) => axiosClient.post('/v1/ve/dat-ve', data),
  getTickets: (params) => axiosClient.get('/v1/ve', { params }),
  bookTicketGuest: (data) => axiosClient.post('/v1/ve/dat-ve-guest', data),

  // --- ĐÁNH GIÁ CHUYẾN XE ---
  submitRating: (data) => axiosClient.post('/v1/rating', data),
  getRatingByTicket: (ticketCode) => axiosClient.get(`/v1/rating/${ticketCode}`),
  getRatingByTrip: (tripId) => axiosClient.get(`/v1/rating/trip/${tripId}`),
  getPendingRatings: () => axiosClient.get('/v1/pending-rating'),
  getMyRatings: () => axiosClient.get('/v1/my-ratings'),

  // chi tiết vé
  getTicket: (id) => axiosClient.get(`/v1/ve/${id}`),

  // nhà xe công khai
  getOperators: (params) => axiosClient.get('/v1/nha-xe', { params }),
  getOperatorDetails: (id) => axiosClient.get(`/v1/nha-xe/${id}`),

  // --- THEO DÕI CHUYẾN XE (cho người thân) ---
  lookupTripsByPhone: (data) => axiosClient.post('/v1/tracking/lookup-by-phone', data),
  getLiveTrackingPublic: (tripId, params) => axiosClient.get(`/v1/chuyen-xe/${tripId}/tracking/live`, { params }),
};

export default clientApi;
