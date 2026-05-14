/** Slot dùng chung. Chỉ là dictionary nội bộ, KHÔNG inject toàn bộ vào LLM. */
export const COMMON_SLOTS = {
  user_id: "ID khách hàng nếu đã login",
  session_key: "Chat/session key",
  access_token: "Bearer token",
  locale: "vi",
  timezone: "Asia/Ho_Chi_Minh",

  ho_va_ten: "Họ và tên",
  so_dien_thoai: "Số điện thoại",
  email: "Email",
  password: "Mật khẩu",
  password_confirmation: "Xác nhận mật khẩu",

  diem_di: "Điểm đi",
  diem_den: "Điểm đến",
  ngay_khoi_hanh: "Ngày khởi hành YYYY-MM-DD",
  gio_khoi_hanh: "Giờ khởi hành HH:mm",
  nha_xe: "Tên nhà xe",
  ma_nha_xe: "Mã nhà xe",
  loai_xe: "Loại xe: limousine, giường nằm, ghế ngồi, VIP...",
  tien_ich: "Tiện ích xe: wifi, nước uống, ghế massage...",

  route_id: "ID tuyến đường",
  trip_id: "ID chuyến xe",
  vehicle_id: "ID xe",
  driver_id: "ID tài xế",
  operator_id: "ID nhà xe",
  seat_id: "ID ghế",
  seat_ids: "Danh sách ID ghế",
  ma_ghe: "Mã ghế",
  ma_ve: "Mã vé",
  ticket_id: "ID vé",
  payment_id: "ID thanh toán",
  ma_thanh_toan: "Mã thanh toán",
  voucher_code: "Mã voucher",

  lat: "Vĩ độ",
  lng: "Kinh độ",
  radius_km: "Bán kính tìm kiếm km",
};

/** Slot bổ sung cho BE/tool runtime. */
export const EXTENDED_SLOTS = {
  remember_me: "Ghi nhớ đăng nhập",
  device_name: "Tên thiết bị",
  dia_chi: "Địa chỉ",
  ngay_sinh: "Ngày sinh YYYY-MM-DD",

  old_password: "Mật khẩu cũ",
  mat_khau_cu: "Mật khẩu hiện tại",
  mat_khau_moi: "Mật khẩu mới",
  mat_khau_moi_confirmation: "Xác nhận mật khẩu mới",
  role: "Vai trò reset mật khẩu: khach_hang",
  token: "Token kích hoạt / reset",
  otp: "Mã OTP",

  from_date: "Từ ngày YYYY-MM-DD",
  to_date: "Đến ngày YYYY-MM-DD",
  ticket_status: "Trạng thái vé",
  payment_status: "Trạng thái thanh toán",

  points: "Số điểm",
  booking_id: "ID booking / vé draft",
  loai_giao_dich: "Loại giao dịch điểm",

  ten_tuyen_duong: "Tên tuyến",
  so_luong_ghe: "Số lượng ghế",
  min_price: "Giá tối thiểu",
  max_price: "Giá tối đa",

  cancel_reason: "Lý do hủy",
  new_trip_id: "ID chuyến mới",
  new_seat_ids: "Ghế mới",

  return_url: "URL return thanh toán",
  bank_code: "Mã ngân hàng",
  ma_giao_dich: "Mã giao dịch",
  transaction_id: "ID giao dịch cổng thanh toán",

  refund_reason: "Lý do hoàn tiền",
  refund_id: "ID yêu cầu hoàn",
  policy_id: "ID chính sách hoàn",
  cancel_time: "Thời điểm hủy",

  booking_amount: "Giá booking để áp voucher",

  target: "Mục tiêu support: admin | nha_xe",
  guest_name: "Tên khách guest",
  guest_phone: "SĐT khách guest",
  guest_email: "Email khách guest",
  ho_va_ten_if_guest: "Họ tên khách nếu chưa đăng nhập",
  so_dien_thoai_if_guest: "SĐT khách nếu chưa đăng nhập",

  initial_message: "Tin nhắn đầu phiên support",
  public_id: "Public ID phiên support",
  client_token: "Client token phiên support",
  message: "Nội dung tin nhắn",

  id_tram_don: "ID trạm đón",
  id_tram_tra: "ID trạm trả",
  id_chuyen_xe: "ID chuyến theo BE",
  danh_sach_ghe: "Danh sách mã ghế theo BE",
  phuong_thuc_thanh_toan: "Phương thức thanh toán",
  payment_method: "Phương thức thanh toán",
  diem_quy_doi: "Điểm quy đổi khi đặt vé",
  id_voucher: "ID voucher",
  ghi_chu: "Ghi chú",

  bank_account: "Số tài khoản ngân hàng",
  bank_name: "Tên ngân hàng",
  account_holder: "Tên chủ tài khoản",

  avatar: "File ảnh đại diện",
};

export const SENSITIVE_SLOTS = new Set([
  "password",
  "password_confirmation",
  "old_password",
  "mat_khau_cu",
  "mat_khau_moi",
  "mat_khau_moi_confirmation",
  "token",
  "otp",
  "access_token",
  "client_token",
  "bank_account",
]);

export function getSlotDescription(slotName) {
  return COMMON_SLOTS[slotName] || EXTENDED_SLOTS[slotName] || slotName;
}

export function expandSlotExpression(slotExpression) {
  return String(slotExpression ?? "")
    .split("|")
    .map((slot) => slot.trim())
    .filter(Boolean);
}

export function getSlotsForToolSpec(spec = {}) {
  const conditionalSlots = Object.values(
    spec.conditionalRequiredSlots || {},
  ).flat();

  const slots = [
    ...(Array.isArray(spec.requiredSlots) ? spec.requiredSlots : []),
    ...(Array.isArray(spec.optionalSlots) ? spec.optionalSlots : []),
    ...(Array.isArray(spec.sensitiveSlots) ? spec.sensitiveSlots : []),
    ...conditionalSlots,
  ];

  return [...new Set(slots.flatMap(expandSlotExpression))];
}

export function buildToolJsonSchemaFromSlots(spec = {}) {
  const properties = {};

  for (const slot of getSlotsForToolSpec(spec)) {
    properties[slot] = {
      type: inferSlotType(slot),
      description: getSlotDescription(slot),
    };
  }

  return {
    type: "object",
    properties,
    additionalProperties: true,
  };
}

function inferSlotType(slot) {
  if (
    [
      "points",
      "booking_amount",
      "min_price",
      "max_price",
      "lat",
      "lng",
      "radius_km",
      "so_luong_ghe",
    ].includes(slot)
  ) {
    return "number";
  }

  if (["seat_ids", "new_seat_ids", "danh_sach_ghe", "tien_ich"].includes(slot)) {
    return "array";
  }

  if (["remember_me"].includes(slot)) {
    return "boolean";
  }

  return "string";
}

export function redactSensitiveArgs(args = {}) {
  const out = { ...args };

  for (const key of Object.keys(out)) {
    if (SENSITIVE_SLOTS.has(key)) {
      out[key] = "[REDACTED]";
    }
  }

  return out;
}

export function buildCompactToolDescription(toolName, spec = {}) {
  const required = Array.isArray(spec.requiredSlots)
    ? spec.requiredSlots.slice(0, 4)
    : [];

  const optional = Array.isArray(spec.optionalSlots)
    ? spec.optionalSlots.slice(0, 5)
    : [];

  return [
    spec.shortDescription || inferToolPurpose(toolName),
    required.length ? `Required: ${required.join(", ")}` : "",
    optional.length ? `Optional: ${optional.join(", ")}` : "",
  ]
    .filter(Boolean)
    .join("\n");
}

function inferToolPurpose(toolName) {
  return String(toolName)
    .replace(/_/g, " ")
    .replace(
      /^(auth|account|password|registration|customer|loyalty|route|trip|seat|booking|ticket|payment|refund|voucher|tracking|map|transport|support)\s+/,
      "",
    )
    .trim();
}

export const ROUTE_SCHEMA_NOTE =
  "tuyen_duongs: ma_nha_xe, ten_tuyen_duong, diem_bat_dau, diem_ket_thuc, quang_duong, gio_khoi_hanh, gia_ve_co_ban; tram_dungs: tọa độ + loại trạm đón/trả.";

export const TRIP_SCHEMA_NOTE =
  "chuyen_xes: id_tuyen_duong, id_xe, id_tai_xe, ngay_khoi_hanh, gio_khoi_hanh, trang_thai.";

export const TICKET_BOOKING_NOTE =
  "ves.id_khach_hang nullable → guest/login; POST /api/v1/ve/dat-ve Bearer tùy chọn.";