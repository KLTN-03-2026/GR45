import axiosClient from './axiosClient.js'

// ─── Rate-limit state ───────────────────────────────────────────────────────
// Đảm bảo không gọi liên tiếp quá nhanh làm quá tải queue
const MIN_INTERVAL = 1200 // ms
let _lastCallAt = 0
let _pendingQueue = Promise.resolve()

/**
 * Enqueue một request để đảm bảo khoảng cách giữa các lần gọi ≥ MIN_INTERVAL.
 */
const enqueue = (fn) => {
  _pendingQueue = _pendingQueue.then(async () => {
    const now = Date.now()
    const elapsed = now - _lastCallAt
    if (elapsed < MIN_INTERVAL) {
      await sleep(MIN_INTERVAL - elapsed)
    }
    _lastCallAt = Date.now()
    return fn()
  })
  return _pendingQueue
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))

/**
 * Gọi geocoding thông qua Laravel proxy BE (để bypass rate limit/CORS của OpenStreetMap ở client)
 * @param {string} query - Chuỗi tìm kiếm địa chỉ
 * @param {object} extraParams - Tham số thêm
 * @param {number} retries - Số lần thử lại tối đa
 */
const searchWithRetry = async (query, extraParams = {}, retries = 3) => {
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      // Gọi qua backend proxy /v1/map/geocode
      const data = await axiosClient.get('/v1/map/geocode', {
        params: {
          q: query,
          ...extraParams,
        },
      })
      // Trả về cấu trúc bọc { data: ... } giống như Axios gốc để tương thích hoàn toàn với FE cũ
      return { data }
    } catch (err) {
      const status = err?.response?.status
      if (status === 429 && attempt < retries) {
        // Exponential back-off: 2s, 4s, 8s…
        const waitMs = Math.pow(2, attempt) * 1000
        console.warn(`[mapApi] 429 Too Many Requests từ proxy BE – thử lại sau ${waitMs / 1000}s (lần ${attempt}/${retries})`)
        await sleep(waitMs)
      } else {
        throw err
      }
    }
  }
}

const mapApi = {
  /**
   * Tìm tọa độ theo địa chỉ.
   * Tự động queue để không gửi quá 1 req/1.2s, retry khi bị 429.
   */
  searchCoordinatesByAddress: (query, extraParams = {}) => {
    return enqueue(() => searchWithRetry(query, extraParams))
  },
}

export default mapApi
