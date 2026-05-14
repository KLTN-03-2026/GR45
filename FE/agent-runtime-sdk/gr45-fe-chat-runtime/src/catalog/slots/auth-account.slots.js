export const AUTH_TOOL_SLOTS = {
  login: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["so_dien_thoai|email", "password"],
    optionalSlots: ["remember_me", "device_name"],
    step: [
      "Hỏi SĐT/email nếu thiếu",
      "Hỏi mật khẩu nếu thiếu",
      "Gọi POST /api/v1/dang-nhap (BE hiện chỉ nhận email+password)",
      "Lưu token FE (auth.client.token)",
      "Sau đó có thể gọi GET /api/v1/profile",
    ],
  },
  logout: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: [],
    step: ["Check token", "Gọi POST /api/v1/dang-xuat", "Clear token FE"],
  },
};

export const ACCOUNT_TOOL_SLOTS = {
  get_profile: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: [],
    step: ["Gọi GET /api/v1/profile"],
  },
  update_profile: {
    authPolicy: "required",
    confirm: true,
    requiredSlots: [],
    optionalSlots: ["ho_va_ten", "email", "so_dien_thoai", "dia_chi", "ngay_sinh"],
    step: ["Hỏi field muốn cập nhật", "Show preview", "User xác nhận", "Gọi PUT /api/v1/profile"],
  },
  update_avatar: {
    authPolicy: "required",
    confirm: true,
    requiredSlots: ["avatar"],
    optionalSlots: [],
    step: ["Upload/chọn ảnh", "Xác nhận", "Multipart — ưu tiên UI, tool trả hướng dẫn"],
  },
  update_email: {
    authPolicy: "required",
    confirm: true,
    requiredSlots: ["email"],
    optionalSlots: [],
    step: ["Hỏi email mới", "Xác nhận", "PUT /api/v1/profile"],
  },
  update_phone: {
    authPolicy: "required",
    confirm: true,
    requiredSlots: ["so_dien_thoai"],
    optionalSlots: [],
    step: ["Hỏi SĐT mới", "Xác nhận", "PUT /api/v1/profile"],
  },
};

export const PASSWORD_TOOL_SLOTS = {
  change_password: {
    authPolicy: "required",
    confirm: true,
    requiredSlots: ["old_password", "password", "password_confirmation"],
    optionalSlots: [],
    step: [
      "Hỏi mật khẩu cũ/mới",
      "Xác nhận",
      "POST /api/v1/doi-mat-khau (mat_khau_cu, mat_khau_moi, mat_khau_moi_confirmation)",
    ],
  },
  forgot_password: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["email|so_dien_thoai"],
    optionalSlots: [],
    step: ["Hỏi email/SĐT", "POST /api/v1/quen-mat-khau (BE: role+email)", "BE chưa hỗ trợ SĐT-only"],
  },
  reset_password: {
    authPolicy: "public",
    confirm: true,
    requiredSlots: ["token", "password", "password_confirmation"],
    optionalSlots: ["email"],
    step: ["POST /api/v1/dat-lai-mat-khau — mat_khau_moi, role=khach_hang; confirmation is runtime/UI controlled"],
  },
  verify_reset_token: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["token"],
    optionalSlots: ["email"],
    step: ["Validate token — chờ AgentToolController nếu BE thêm route"],
  },
};

export const REGISTRATION_TOOL_SLOTS = {
  register_account: {
    authPolicy: "public",
    confirm: true,
    requiredSlots: ["ho_va_ten", "so_dien_thoai", "password", "password_confirmation"],
    optionalSlots: ["email", "dia_chi", "ngay_sinh"],
    step: ["Thu thập field", "Show summary", "POST /api/v1/dang-ky"],
  },
  verify_otp: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["email|so_dien_thoai", "otp|token"],
    optionalSlots: [],
    step: ["POST /api/v1/kich-hoat-tai-khoan — BE: email + token"],
  },
  activate_account: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["email|so_dien_thoai", "token"],
    optionalSlots: [],
    step: ["POST /api/v1/kich-hoat-tai-khoan"],
  },
};

export const CUSTOMER_TOOL_SLOTS = {
  get_customer_info: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: [],
    step: ["GET /api/v1/profile"],
  },
  get_account_status: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: [],
    step: ["GET /api/v1/profile", "Đọc tinh_trang"],
  },
  get_booking_history: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: ["from_date", "to_date", "ticket_status"],
    step: ["GET /api/v1/ve"],
  },
  get_transaction_history: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: ["from_date", "to_date", "payment_status"],
    step: ["Chưa có route public — AgentToolController / query thanh_toans"],
  },
};

export const LOYALTY_TOOL_SLOTS = {
  get_current_points: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: [],
    step: ["GET /api/v1/diem-thanh-vien"],
  },
  redeem_points: {
    authPolicy: "required",
    confirm: true,
    requiredSlots: ["points"],
    optionalSlots: ["booking_id", "ma_ve"],
    step: ["Check điểm", "User xác nhận", "Chờ AgentToolController quy đổi"],
  },
  get_points_history: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: ["from_date", "to_date", "loai_giao_dich"],
    step: ["GET /api/v1/lich-su-diem"],
  },
  get_membership_tier: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: [],
    step: ["GET /api/v1/diem-thanh-vien", "Đọc hang_thanh_vien nếu có"],
  },
};
