//api client
import axios from "axios";
import * as SecureStore from 'expo-secure-store';

const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL || "https://api.bussafe.io.vn/api"; // Lấy cấu hình từ .env hoặc fallback về URL đã deploy

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    "Content-Type": "application/json",
  },
});

apiClient.interceptors.request.use(async (config) => {
  try {
    const token = await SecureStore.getItemAsync('userToken');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`; 
    }
  } catch (error) {
    console.error("Lỗi lấy token:", error);
  }
  return config;
});

const clientApi = {
  // --- AUTH ---
  login: (data: any) => apiClient.post('/v1/dang-nhap', data),
  checkToken: () => apiClient.get('/v1/check-token'),

  // --- PROFILE ---
  getProfile: () => apiClient.get('/v1/profile'),
  updateProfile: (data: any) => apiClient.put('/v1/profile', data),
  changePassword: (data: any) => apiClient.post('/v1/doi-mat-khau', data),

  // --- TÌM KIẾM & ĐẶT VÉ ---
  getProvinces: () => apiClient.get('/v1/tinh-thanh'),
  searchTrips: (params?: any) => apiClient.get('/v1/chuyen-xe/search', { params }),
  getTripSeats: (tripId: string | number) => apiClient.get(`/v1/chuyen-xe/${tripId}/ghe`),
  getTripStops: (tripId: string | number) => apiClient.get(`/v1/chuyen-xe/${tripId}/tram-dung`),
  getPublicVouchers: (params?: any) => apiClient.get('/v1/voucher/public', { params }),
  getMyVouchers: (params?: any) => apiClient.get('/v1/voucher', { params }),
  getLoyaltyInfo: () => apiClient.get('/v1/diem-thanh-vien'),
  bookTicket: (data: any) => apiClient.post('/v1/ve/dat-ve', data),
  getTickets: (params?: any) => apiClient.get('/v1/ve', { params }),
  getTicketDetail: (id: string | number) => apiClient.get(`/v1/ve/${id}`),
  cancelTicket: (id: string | number) => apiClient.patch(`/v1/ve/${id}/huy`),
};

export default clientApi;