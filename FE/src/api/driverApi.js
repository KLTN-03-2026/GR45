// Driver API integration for BusSafe
import axiosClient from './axiosClient';


const driverApi = {
  
  getChuyenXeList(params) {
    const url = '/v1/tai-xe/chuyen-xe';
    return axiosClient.get(url, { params });
  },

  
  getChuyenXeDetail(id) {
    const url = `/v1/tai-xe/chuyen-xe/${id}`;
    return axiosClient.get(url);
  },

  
  updateTrangThaiChuyenXe(id, data) {
    const url = `/v1/tai-xe/chuyen-xe/${id}/trang-thai`;
    return axiosClient.patch(url, data);
  },

  
  getLichTrinhChuyen(id) {
    const url = `/v1/tai-xe/chuyen-xe/${id}/lich-trinh`;
    return axiosClient.get(url);
  },

  
  getLichTrinhCaNhan(params) {
    const url = '/v1/tai-xe/chuyen-xe/lich-trinh-ca-nhan';
    return axiosClient.get(url, { params });
  },

  
  postLiveTrackingLocation(id, trackingData) {
    const url = `/v1/tai-xe/chuyen-xe/${id}/tracking`;
    return axiosClient.post(url, trackingData);
  },

  
  getTrackingHistory(id, params) {
    const url = `/v1/tai-xe/chuyen-xe/${id}/tracking`;
    return axiosClient.get(url, { params });
  },

  /**
   * Thống kê dashboard tài xế
   */
  getStats() {
    return axiosClient.get('/v1/tai-xe/stats');
  },

  getThongKeGioLam() {
    return axiosClient.get('/v1/tai-xe/chuyen-xe/thong-ke-gio-lam');
  },

  /**
   * Danh sách chuyến sắp tới của tài xế
   */
  getUpcomingTrips() {
    return axiosClient.get('/v1/tai-xe/upcoming-trips');
  },

  
  postBaoDong(data) {
    const url = '/v1/tai-xe/bao-dong';
    return axiosClient.post(url, data);
  },

  
  getCauHinhAi() {
    const url = '/v1/tai-xe/cau-hinh-ai';
    return axiosClient.get(url);
  },

  
  postSOS(data) {
    const url = '/v1/tai-xe/sos';
    return axiosClient.post(url, data);
  },

  
  hoanThanhChuyenXe(id) {
    const url = `/v1/tai-xe/chuyen-xe/${id}/hoan-thanh`;
    return axiosClient.post(url);
  }
};

export default driverApi;
