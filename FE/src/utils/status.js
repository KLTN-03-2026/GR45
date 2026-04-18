export const getRouteStatus = (status) => {
  //  ['khong_hoat_dong', 'hoat_dong', 'cho_duyet']
  switch(status) {
    case 'cho_duyet': return { text: 'Chờ duyệt', class: 'status-pending' };
    case 'hoat_dong': return { text: 'Đã duyệt', class: 'status-approved' };
    case 'khong_hoat_dong': return { text: 'Từ chối/Hủy', class: 'status-rejected' };
    default: return { text: 'Không rõ', class: '' };
  }
};

export const getTicketStatus = (status) => {
  switch(status) {
    case 'huy': return { text: 'Đã hủy', class: 'status-rejected' };
    case 'dang_cho':  return { text: 'Đang chờ', class: 'status-pending' };
    case 'da_thanh_toan':  return { text: 'Đã thanh toán', class: 'status-info' };
    default: return { text: 'Không rõ', class: '' };
  }
};

export const getVoucherStatus = (status) => {
  switch(status) {
    case 'cho_duyet':   return { text: 'Chờ duyệt', class: 'status-pending' };
    case 'hoat_dong':   return { text: 'Hoạt động', class: 'status-approved' };
    case 'tam_ngung':   return { text: 'Tạm ngưng', class: 'status-info' };
    case 'vo_hieu':     return { text: 'Vô hiệu', class: 'status-rejected' };
    case 'het_han':     return { text: 'Hết hạn', class: 'status-expired' };
    default:            return { text: 'Không rõ', class: '' };
  }
};

export const getStaffStatus = (status) => {
  switch(status) {
    case 'hoat_dong': return { text: 'Hoạt động', class: 'status-approved' };
    case 'khoa':
    case 'bi_khoa':    return { text: 'Bị khóa', class: 'status-rejected' };
    default:           return { text: 'Không rõ', class: '' };
  }
};

