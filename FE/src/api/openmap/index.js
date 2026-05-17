import axiosClient from "../axiosClient";
import axios from "axios";

const openmapApi = {
  
  autocomplete(text) {
    // Gọi thẳng vì endpoint này thường không bị CORS hoặc dùng ít hơn
    const apiKey = import.meta.env.VITE_OPENMAP_API_KEY;
    return axios.get("https://mapapis.openmap.vn/v1/autocomplete", {
      params: { text, apikey: apiKey },
    });
  },

  
  getPlaceDetail(place_id) {
    const apiKey = import.meta.env.VITE_OPENMAP_API_KEY;
    return axios.get("https://mapapis.openmap.vn/v1/place-detail", {
      params: { place_id, apikey: apiKey },
    });
  },

  
  direction(origin, destination, vehicle = "car") {
    return axiosClient.get("/v1/map/direction", {
      params: { origin, destination, vehicle },
    });
  },

  
  getDrivingRoute(coordsString) {
    return axiosClient.get("/v1/map/osrm-route", {
      params: { coords: coordsString },
    });
  },
};

export default openmapApi;