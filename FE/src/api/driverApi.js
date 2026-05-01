import axiosClient from './axiosClient';

const driverApi = {
  /**
   * Lấy danh sách chuyến xe
   * @param {Object} params - Tham số lọc (search, ngay_khoi_hanh, trang_thai, per_page)
   * @returns {Promise} Dữ liệu danh sách chuyến
   */
  getChuyenXeList(params) {
    const url = '/v1/tai-xe/chuyen-xe';
    return axiosClient.get(url, { params });
  },

  /**
   * Xem chi tiết 1 chuyến xe 
   * @param {Number} id 
   * @returns {Promise}
   */
  getChuyenXeDetail(id) {
    const url = `/v1/tai-xe/chuyen-xe/${id}`;
    return axiosClient.get(url);
  },

  /**
   * Cập nhật trạng thái chuyến
   * @param {Number} id 
   * @param {Object} data - Dữ liệu cập nhật (trang_thai: hoat_dong | dang_di_chuyen | hoan_thanh)
   * @returns {Promise}
   */
  updateTrangThaiChuyenXe(id, data) {
    const url = `/v1/tai-xe/chuyen-xe/${id}/trang-thai`;
    return axiosClient.patch(url, data);
  },

  /**
   * Lấy danh sách trạm dừng theo thứ tự
   * @param {Number} id 
   * @returns {Promise}
   */
  getLichTrinhChuyen(id) {
    const url = `/v1/tai-xe/chuyen-xe/${id}/lich-trinh`;
    return axiosClient.get(url);
  },

  /**
   * Lấy lịch trình (các chuyến) của cá nhân tài xế
   * @param {Object} params - Tham số lọc (days, ngay_bat_dau, ngay_ket_thuc, ...)
   * @returns {Promise}
   */
  getLichTrinhCaNhan(params) {
    const url = '/v1/tai-xe/chuyen-xe/lich-trinh-ca-nhan';
    return axiosClient.get(url, { params });
  },

  /**
   * Cập nhật GPS Live Tracking
   * @param {Number} id 
   * @param {Object} trackingData - {vi_do, kinh_do, van_toc, huong_di, ...}
   * @returns {Promise}
   */
  postLiveTrackingLocation(id, trackingData) {
    const url = `/v1/tai-xe/chuyen-xe/${id}/tracking`;
    return axiosClient.post(url, trackingData);
  },

  /**
   * Lấy thông tin vị trí hiện tại và lịch sử (quá khứ) của chuyến
   * @param {Number} id 
   * @returns {Promise}
   */
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

  /**
   * Danh sách chuyến sắp tới của tài xế
   */
  getUpcomingTrips() {
    return axiosClient.get('/v1/tai-xe/upcoming-trips');
  },

  /**
   * Gửi báo động vi phạm (ngủ gật, ...) kèm ảnh base64
   * @param {Object} data - { id_chuyen_xe, loai_bao_dong, muc_do, anh_vi_pham (base64), vi_do_luc_bao, kinh_do_luc_bao, du_lieu_phat_hien }
   * @returns {Promise}
   */
  postBaoDong(data) {
    const url = '/v1/tai-xe/bao-dong';
    return axiosClient.post(url, data);
  },

  /**
   * Lấy cấu hình AI (ngưỡng EAR, ...) cho tài xế đang đăng nhập
   * @returns {Promise}
   */
  getCauHinhAi() {
    const url = '/v1/tai-xe/cau-hinh-ai';
    return axiosClient.get(url);
  },

  /**
   * Gửi tín hiệu SOS khẩn cấp
   * @param {Object} data - { id_chuyen_xe }
   */
  postSOS(data) {
    const url = '/v1/tai-xe/sos';
    return axiosClient.post(url, data);
  },

  /**
   * Hoàn thành chuyến xe (Cập nhật trạng thái và tích điểm cho khách)
   * @param {Number} id 
   * @returns {Promise}
   */
  hoanThanhChuyenXe(id) {
    const url = `/v1/tai-xe/chuyen-xe/${id}/hoan-thanh`;
    return axiosClient.post(url);
  }
};

export default driverApi;
