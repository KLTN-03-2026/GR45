export const PAYMENT_TOOL_SLOTS = {
  create_payment: {
    authPolicy: "optional",
    confirm: true,
    requiredSlots: ["ticket_id|ma_ve", "payment_method"],
    optionalSlots: ["return_url", "bank_code", "so_dien_thoai_if_guest"],
    sensitiveSlots: [],
    step: ["Tạo thanh toán — AgentToolController / flow hiện tại"],
  },

  get_payment_status: {
    authPolicy: "optional",
    confirm: false,
    requiredSlots: ["payment_id|ma_thanh_toan|ma_ve"],
    optionalSlots: ["so_dien_thoai_if_guest"],
    step: ["Query thanh_toans"],
  },

  verify_payment: {
    authPolicy: "internal_or_required",
    confirm: false,
    requiredSlots: ["ma_thanh_toan|ma_giao_dich"],
    optionalSlots: ["transaction_id"],
    step: ["SePay / nội bộ"],
  },

  retry_payment: {
    authPolicy: "optional",
    confirm: true,
    requiredSlots: ["payment_id|ma_ve"],
    optionalSlots: ["payment_method", "so_dien_thoai_if_guest"],
    step: ["Tạo lại thanh toán"],
  },

  reconcile_payment: {
    authPolicy: "admin_internal",
    confirm: true,
    requiredSlots: ["ma_thanh_toan|ma_giao_dich|transaction_id"],
    optionalSlots: [],
    hiddenFromCustomerPlanner: true,
    step: ["Admin/internal"],
  },

  get_payment_history: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: ["from_date", "to_date", "payment_status"],
    step: ["Query thanh_toans theo khách"],
  },
};

export const REFUND_TOOL_SLOTS = {
  estimate_refund: {
    authPolicy: "required_or_guest_lookup",
    confirm: false,
    requiredSlots: ["ticket_id|ma_ve"],
    optionalSlots: ["so_dien_thoai_if_guest", "cancel_time", "policy_id"],
    step: ["Ước tính hoàn"],
  },

  create_refund_request: {
    authPolicy: "required_or_guest_lookup",
    confirm: true,
    requiredSlots: ["ticket_id|ma_ve", "refund_reason"],
    optionalSlots: [
      "so_dien_thoai_if_guest",
      "bank_account",
      "bank_name",
      "account_holder",
    ],
    sensitiveSlots: ["bank_account"],
    step: ["Tạo yêu cầu hoàn"],
  },

  get_refund_status: {
    authPolicy: "required_or_guest_lookup",
    confirm: false,
    requiredSlots: ["refund_id|ma_ve"],
    optionalSlots: ["so_dien_thoai_if_guest"],
    step: ["Trạng thái hoàn"],
  },
};

export const VOUCHER_TOOL_SLOTS = {
  validate_voucher: {
    authPolicy: "optional",
    confirm: false,
    requiredSlots: ["voucher_code"],
    optionalSlots: ["trip_id", "ma_nha_xe", "booking_amount", "user_id"],
    step: ["Validate voucher"],
  },

  apply_voucher: {
    authPolicy: "optional",
    confirm: false,
    requiredSlots: ["voucher_code"],
    optionalSlots: ["booking_amount", "trip_id", "ma_nha_xe", "user_id"],
    step: ["Tính giảm và attach draft"],
  },

  list_available_vouchers: {
    authPolicy: "optional",
    confirm: false,
    requiredSlots: [],
    optionalSlots: ["ma_nha_xe", "trip_id", "booking_amount"],
    step: [
      "Guest: GET /api/v1/voucher/public",
      "Login: GET /api/v1/voucher/huntable",
    ],
  },

  get_voucher_usage_history: {
    authPolicy: "required",
    confirm: false,
    requiredSlots: [],
    optionalSlots: ["from_date", "to_date"],
    step: ["AgentToolController — voucher usage"],
  },
};