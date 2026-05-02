console.log('format.js loaded');

export const formatDate = (dateStr) => {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString("vi-VN");
};

export const formatCurrency = (amount, suffix = 'VNĐ') => {
  if (amount === null || amount === undefined || isNaN(amount)) return `0 ${suffix}`;
  const formattedNumber = Number(amount).toLocaleString('vi-VN');
  return `${formattedNumber} ${suffix}`;
};

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

export const calcArrivalTime = (gioKhoiHanh, gioDuKien) => {
  if (!gioKhoiHanh || !gioDuKien) return "--:--";
  const parts = gioKhoiHanh.split(":");
  const h = parseInt(parts[0]) + parseInt(gioDuKien);
  const m = parts[1] || "00";
  return `${String(h % 24).padStart(2, "0")}:${m}`;
};

export const formatTime = (timeStr) => {
  if (!timeStr) return "--:--";
  return timeStr.slice(0, 5);
};

export const formatFullDate = (isoStr) => {
  if (!isoStr) return "...";
  const d = new Date(isoStr);
  if (isNaN(d.getTime())) return isoStr;
  const days = ["Chủ nhật", "Thứ 2", "Thứ 3", "Thứ 4", "Thứ 5", "Thứ 6", "Thứ 7"];
  const dayName = days[d.getDay()];
  const dateStr = d.toLocaleDateString("vi-VN");
  return `${dayName}, ${dateStr}`;
};
