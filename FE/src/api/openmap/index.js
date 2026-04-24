import axiosClient from "../axiosClient";
import axios from "axios";

const openmapApi = {
  /**
   * Tự động dự đoán / gợi ý địa chỉ (Autocomplete)
   * @param {string} text - Nội dung tìm kiếm
   */
  autocomplete(text) {
    // Gọi thẳng vì endpoint này thường không bị CORS hoặc dùng ít hơn
    const apiKey = import.meta.env.VITE_OPENMAP_API_KEY;
    return axios.get("https://mapapis.openmap.vn/v1/autocomplete", {
      params: { text, apikey: apiKey },
    });
  },

  /**
   * Lấy chi tiết thông tin và tọa độ qua ID địa điểm
   * @param {string} place_id - Của địa điểm
   */
  getPlaceDetail(place_id) {
    const apiKey = import.meta.env.VITE_OPENMAP_API_KEY;
    return axios.get("https://mapapis.openmap.vn/v1/place-detail", {
      params: { place_id, apikey: apiKey },
    });
  },

  /**
   * Chỉ đường thực tế cho tài xế từ điểm A đến điểm B
   * Gọi qua backend proxy để tránh lỗi CORS
   * @param {string} origin - Tọa độ bắt đầu "lat,lng"
   * @param {string} destination - Tọa độ kết thúc "lat,lng"
   * @param {string} vehicle - Loại phương tiện (mặc định "car")
   */
  direction(origin, destination, vehicle = "car") {
    return axiosClient.get("/v1/map/direction", {
      params: { origin, destination, vehicle },
    });
  },

  /**
   * Tìm đường đi thực tế (Fallback OSRM) qua backend proxy để tránh CORS
   * @param {string} coordsString - Chuỗi tọa độ: "lng,lat;lng,lat;..."
   */
  getDrivingRoute(coordsString) {
    return axiosClient.get("/v1/map/osrm-route", {
      params: { coords: coordsString },
    });
  },
};

export default openmapApi;