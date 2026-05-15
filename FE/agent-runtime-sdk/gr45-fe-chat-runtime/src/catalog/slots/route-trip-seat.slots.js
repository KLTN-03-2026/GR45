export const ROUTE_TOOL_SLOTS = {
  search_routes: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: [],
    optionalSlots: ["diem_di", "diem_den", "nha_xe", "ma_nha_xe", "loai_xe"],
    shortDescription: "Tìm tuyến đường xe khách theo điểm đi, điểm đến hoặc nhà xe.",
    step: [
      "GET /api/v1/tuyen-duong/public",
      "Lọc client-side theo slot nếu API không filter",
    ],
  },

  get_route_detail: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["route_id|ten_tuyen_duong"],
    optionalSlots: ["ma_nha_xe"],
    shortDescription: "Xem chi tiết một tuyến đường.",
    step: ["AgentToolController detail tuyen_duongs"],
  },

  get_pickup_points: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["trip_id"],
    optionalSlots: ["route_id", "diem_di"],
    shortDescription: "Lấy điểm đón của chuyến xe.",
    step: ["GET /api/v1/chuyen-xe/{trip_id}/tram-dung"],
  },

  get_dropoff_points: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["trip_id"],
    optionalSlots: ["route_id", "diem_den"],
    shortDescription: "Lấy điểm trả của chuyến xe.",
    step: ["GET /api/v1/chuyen-xe/{trip_id}/tram-dung"],
  },
};

export const TRIP_TOOL_SLOTS = {
  search_trips: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: [
      "diem_di",
      "diem_den",
      "ngay_khoi_hanh",
      "gio_khoi_hanh|gio_khoi_hanh_tu|gio_khoi_hanh_den",
    ],
    optionalSlots: [
      "gio_khoi_hanh_tu",
      "gio_khoi_hanh_den",
      "nha_xe",
      "ma_nha_xe",
      "loai_xe",
      "tien_ich",
      "so_luong_ghe",
      "min_price",
      "max_price",
    ],
    shortDescription:
      "Tìm chuyến xe theo điểm đi, điểm đến, ngày đi và giờ/khung giờ khởi hành.",
    slotRules: [
      "Bắt buộc có diem_di, diem_den, ngay_khoi_hanh và ít nhất một trường giờ.",
      "nha_xe/ma_nha_xe chỉ là bộ lọc tùy chọn, không hỏi như slot bắt buộc.",
      "Giờ map sang gio_khoi_hanh_tu/gio_khoi_hanh_den của BE.",
    ],
    step: [
      "GET /api/v1/chuyen-xe/search",
      "Join/filter nhà xe hoặc loại xe nếu API chưa support trực tiếp",
    ],
  },

  get_trip_detail: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["trip_id"],
    optionalSlots: [],
    shortDescription: "Xem chi tiết chuyến xe.",
    step: ["Chưa có GET public chi tiết chuyến cho khách — AgentToolController"],
  },

  get_trip_status: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["trip_id"],
    optionalSlots: [],
    shortDescription: "Xem trạng thái hiện tại của chuyến xe.",
    step: ["Đọc chuyen_xes.trang_thai"],
  },

  get_trip_schedule: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["trip_id|route_id"],
    optionalSlots: ["ngay_khoi_hanh"],
    shortDescription: "Xem lịch chạy của chuyến hoặc tuyến.",
    step: ["Query chuyen_xes / lịch tuyến"],
  },

  get_available_seats: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["trip_id"],
    optionalSlots: [],
    shortDescription: "Xem ghế còn trống của chuyến xe.",
    step: ["GET /api/v1/chuyen-xe/{id}/ghe"],
  },

  get_trip_price: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["trip_id|route_id"],
    optionalSlots: ["seat_id", "voucher_code", "points"],
    shortDescription: "Tính hoặc xem giá vé của chuyến xe.",
    step: ["Tính từ tuyến/ghế/khuyến mãi"],
  },
};

export const SEAT_TOOL_SLOTS = {
  get_seat_map: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["trip_id"],
    optionalSlots: [],
    shortDescription: "Xem sơ đồ ghế của chuyến xe.",
    step: ["GET /api/v1/chuyen-xe/{id}/ghe"],
  },

  check_available_seats: {
    authPolicy: "public",
    confirm: false,
    requiredSlots: ["trip_id"],
    optionalSlots: ["seat_ids", "so_luong_ghe"],
    shortDescription: "Kiểm tra ghế trống của chuyến xe.",
    step: ["GET seat map", "Lọc ghế trống"],
  },

  hold_seat: {
    authPolicy: "optional",
    confirm: true,
    requiredSlots: ["trip_id", "seat_ids"],
    optionalSlots: ["session_key", "user_id"],
    hiddenFromCustomerPlanner: true,
    disabled: true,
    shortDescription: "Giữ ghế tạm thời. Hiện disabled vì hệ thống không dùng hold server.",
    step: ["UI-only / disabled — không hold server"],
  },
};
