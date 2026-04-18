import axios from 'axios'

const mapApi = {
  searchCoordinatesByAddress: (query, extraParams = {}) => {
    return axios.get('https://nominatim.openstreetmap.org/search', {
      params: {
        q: query,
        format: 'jsonv2',
        limit: 1,
        addressdetails: 1,
        countrycodes: 'vn',
        ...extraParams,
      },
      headers: {
        'Accept-Language': 'vi',
      },
    })
  },
}

export default mapApi
