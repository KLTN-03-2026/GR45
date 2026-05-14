export const BOOKING_TOOL_SLOTS = {
  create_booking: {
    authPolicy: "optional",
    confirm: true,
    requiredSlots: ["trip_id", "seat_ids"],
    conditionalRequiredSlots: {
      guest: ["ho_va_ten_if_guest", "so_dien_thoai_if_guest"],
    },
    optionalSlots: [
      "id_tram_don",
      "id_tram_tra",
      "ghi_chu",
      "voucher_code",
      "points",
      "payment_method",
    ],
    step: [
      "POST /api/v1/ve/dat-ve",
      "Nếu có token → gắn vé vào tài khoản",
      "Nếu không có token → cần ho_va_ten_if_guest + so_dien_thoai_if_guest",
      "Map id_chuyen_xe, danh_sach_ghe[], id_tram_don, id_tram_tra, ho_va_ten, sdt_khach_hang",
    ],
  },

  confirm_booking: {
    authPolicy: "optional",
    confirm: true,
    requiredSlots: ["booking_id|ma_ve"],
    optionalSlots: ["payment_method"],
    step: ["Xác nhận vé dang_cho / thanh toán — tùy BE"],
  },

  cancel_booking: {
    authPolicy: "required_or_guest_lookup",
    confirm: true,
    requiredSlots: ["ticket_id|ma_ve"],
    optionalSlots: ["so_dien_thoai_if_guest", "cancel_reason"],
    step: ["PATCH /api/v1/ve/{id}/huy hoặc guest lookup rồi hủy"],
  },

  get_booking_detail: {
    authPolicy: "required_or_guest_lookup",
    confirm: false,
    requiredSlots: ["ticket_id|ma_ve"],
    optionalSlots: ["so_dien_thoai_if_guest"],
    step: ["Login: GET /api/v1/ve/{id}", "Guest: lookup ma_ve + SĐT"],
  },

  modify_booking: {
    authPolicy: "required",
    confirm: true,
    requiredSlots: ["ticket_id|ma_ve"],
    optionalSlots: ["new_seat_ids", "id_tram_don", "id_tram_tra", "ghi_chu"],
    step: ["Custom AgentToolController"],
  },

  reschedule_booking: {
    authPolicy: "required",
    confirm: true,
    requiredSlots: ["ticket_id|ma_ve", "new_trip_id"],
    optionalSlots: ["new_seat_ids"],
    step: ["Custom AgentToolController"],
  },
};

export const TICKET_TOOL_SLOTS = {
  get_ticket_detail: {
    authPolicy: "required_or_guest_lookup",
    confirm: false,
    requiredSlots: ["ticket_id|ma_ve"],
    optionalSlots: ["so_dien_thoai_if_guest"],
    step: ["Login: GET /api/v1/ve/{id}", "Guest: lookup ma_ve + SĐT"],
  },

  list_tickets: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: ["ticket_status", "from_date", "to_date"],
    step: ["GET /api/v1/ve với ticket_status, from_date, to_date"],
  },

  validate_ticket: {
    authPolicy: "optional",
    confirm: false,
    requiredSlots: ["ticket_id|ma_ve"],
    optionalSlots: [],
    step: ["Stub — chờ AgentToolController validate ve"],
  },

  get_ticket_status: {
    authPolicy: "required_or_guest_lookup",
    confirm: false,
    requiredSlots: ["ticket_id|ma_ve"],
    optionalSlots: ["so_dien_thoai_if_guest"],
    step: ["GET /api/v1/ve/{id} — trạng thái trong response"],
  },

  cancel_ticket: {
    authPolicy: "required_or_guest_lookup",
    confirm: true,
    requiredSlots: ["ticket_id|ma_ve"],
    optionalSlots: ["so_dien_thoai_if_guest", "cancel_reason"],
    step: ["PATCH /api/v1/ve/{id}/huy hoặc guest lookup rồi hủy"],
  },
};