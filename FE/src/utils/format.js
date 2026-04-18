export const formatCurrency = (amount) => {
  if (amount === null || amount === undefined) return '0 đ';
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
  }).format(amount);
};

/**
 * Format ngày giờ đầy đủ — hỗ trợ ISO timestamp (UTC) và chuỗi giờ thuần HH:mm:ss
 * VD: "2026-04-18T00:00:00.000000Z" → "18/04/2026, 07:00"
 * VD: "08:00:00" → "08:00"
 */
export const formatDateTime = (value) => {
  if (!value) return '—';

  if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(value)) {
    return value.slice(0, 5);
  }

  const date = new Date(value);
  if (isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('vi-VN', {
    timeZone: 'Asia/Ho_Chi_Minh',
    day:    '2-digit',
    month:  '2-digit',
    year:   'numeric',
    hour:   '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date);
};

export const formatDateOnly = (value) => {
  if (!value) return '—';
  const date = new Date(value);
  if (isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('vi-VN', {
    timeZone: 'Asia/Ho_Chi_Minh',
    day:   '2-digit',
    month: '2-digit',
    year:  'numeric',
  }).format(date);
};

export const formatTimeOnly = (value) => {
  if (!value) return '—';

  if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(value)) {
    return value.slice(0, 5);
  }

  const date = new Date(value);
  if (isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat('vi-VN', {
    timeZone: 'Asia/Ho_Chi_Minh',
    hour:   '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date);
};

/** @deprecated Dùng formatDateTime thay thế */
export const formatDate = (dateString) => formatDateTime(dateString);
