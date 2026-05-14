import { BOOKING_TOOL_SLOTS, TICKET_TOOL_SLOTS } from "../slots.js";

function jsonBody(body) {
  return {
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  };
}

function asString(value) {
  return String(value ?? "").trim();
}

function normalizeSeats(args) {
  const raw = args.seat_ids ?? args.danh_sach_ghe ?? args.ma_ghe ?? [];

  if (Array.isArray(raw)) {
    return raw.map(String).map((x) => x.trim()).filter(Boolean);
  }

  if (typeof raw === "string") {
    return raw
      .split(/[;,]/)
      .map((x) => x.trim())
      .filter(Boolean);
  }

  return [];
}

function optionalNumber(value) {
  if (value === undefined || value === null || value === "") return undefined;
  const n = Number(value);
  return Number.isFinite(n) && n > 0 ? n : undefined;
}

function buildBookingPayload(args) {
  const guestName = asString(args.ho_va_ten_if_guest ?? args.ho_va_ten);
  const guestPhone = asString(
    args.so_dien_thoai_if_guest ?? args.so_dien_thoai,
  );

  const payload = {
    id_chuyen_xe: Number(args.trip_id ?? args.id_chuyen_xe),
    danh_sach_ghe: normalizeSeats(args),
    id_tram_don: optionalNumber(args.id_tram_don),
    id_tram_tra: optionalNumber(args.id_tram_tra),
    ghi_chu: asString(args.ghi_chu) || undefined,
    id_voucher: optionalNumber(args.id_voucher),
    phuong_thuc_thanh_toan:
      asString(args.payment_method ?? args.phuong_thuc_thanh_toan) ||
      undefined,
    sdt_khach_hang: guestPhone || undefined,
    ho_va_ten: guestName || undefined,
    ten_khach_hang: guestName || undefined,
    diem_quy_doi:
      args.points != null || args.diem_quy_doi != null
        ? Number(args.points ?? args.diem_quy_doi)
        : undefined,
  };

  return Object.fromEntries(
    Object.entries(payload).filter(([, value]) => value !== undefined),
  );
}

function validateBookingPayload(payload) {
  if (!Number.isInteger(payload.id_chuyen_xe) || payload.id_chuyen_xe <= 0) {
    return { ok: false, error: "Thiếu trip_id hợp lệ." };
  }

  if (!Array.isArray(payload.danh_sach_ghe) || payload.danh_sach_ghe.length === 0) {
    return { ok: false, error: "Thiếu danh sách ghế." };
  }

  return null;
}

function getTicketIdentifier(args) {
  return {
    ticketId: args.ticket_id,
    maVe: asString(args.ma_ve),
    guestPhone: asString(args.so_dien_thoai_if_guest ?? args.so_dien_thoai),
  };
}

function executeTicketById({ args, positiveId, jsonResult, action }) {
  const { ticketId, maVe } = getTicketIdentifier(args);

  if (ticketId != null && ticketId !== "") {
    const parsed = positiveId(ticketId, "ticket_id");
    if (!parsed.ok) return Promise.resolve(parsed);

    const path =
      action === "cancel"
        ? `ve/${parsed.id}/huy`
        : `ve/${parsed.id}`;

    return jsonResult(path, {
      method: action === "cancel" ? "PATCH" : "GET",
      auth: "bearer",
    });
  }

  if (maVe) {
    // Lookup ticket by ma_ve via ticket list then fetch detail
    return jsonResult("ve", { method: "GET", auth: "bearer" }).then((listRes) => {
      if (!listRes.ok) return listRes;
      const tickets = Array.isArray(listRes.data?.data)
        ? listRes.data.data
        : Array.isArray(listRes.data)
          ? listRes.data
          : [];
      const found = tickets.find(
        (t) => String(t.ma_ve ?? "").toLowerCase() === maVe.toLowerCase(),
      );
      if (!found?.id) {
        return { ok: false, error: `Không tìm thấy vé ${maVe} trong tài khoản.` };
      }
      const path = action === "cancel" ? `ve/${found.id}/huy` : `ve/${found.id}`;
      return jsonResult(path, {
        method: action === "cancel" ? "PATCH" : "GET",
        auth: "bearer",
      });
    });
  }

  return Promise.resolve({
    ok: false,
    error: "Thiếu ticket_id hoặc ma_ve.",
  });
}

export function registerBookingTicketTools(ctx) {
  const { jsonResult, positiveId, register, stub, withQuery } = ctx;

  register(
    "booking_create_booking",
    BOOKING_TOOL_SLOTS.create_booking,
    "sensitive",
    ["Xác nhận đặt vé", "Thanh toán", "Chọn ghế"],
    async (args) => {
      const payload = buildBookingPayload(args);
      const validationError = validateBookingPayload(payload);

      if (validationError) {
        return validationError;
      }

      // Auto-fetch station IDs when not supplied — use first pickup + first dropoff
      if (!payload.id_tram_don || !payload.id_tram_tra) {
        const stRes = await jsonResult(
          `chuyen-xe/${payload.id_chuyen_xe}/tram-dung`,
          { method: "GET", auth: "none" },
        );
        if (stRes.ok) {
          const st = stRes.data?.data ?? {};
          const tramDon = Array.isArray(st.tram_don) ? st.tram_don : [];
          const tramTra = Array.isArray(st.tram_tra) ? st.tram_tra : [];
          if (!payload.id_tram_don && tramDon[0]?.id) {
            payload.id_tram_don = tramDon[0].id;
          }
          if (!payload.id_tram_tra && tramTra[0]?.id) {
            payload.id_tram_tra = tramTra[0].id;
          }
        }
      }

      // Default payment to bank transfer when not specified
      if (!payload.phuong_thuc_thanh_toan) {
        payload.phuong_thuc_thanh_toan = "chuyen_khoan";
      }

      return jsonResult("ve/dat-ve", {
        method: "POST",
        auth: "optional",
        ...jsonBody(payload),
      });
    },
  );

  register(
    "booking_confirm_booking",
    BOOKING_TOOL_SLOTS.confirm_booking,
    "safe",
    ["Xác nhận đặt vé", "Thanh toán"],
    (args) => stub("booking_confirm_booking", args),
  );

  register(
    "booking_cancel_booking",
    BOOKING_TOOL_SLOTS.cancel_booking,
    "sensitive",
    ["Hủy đặt vé", "Hoàn tiền"],
    (args) =>
      executeTicketById({
        args,
        positiveId,
        jsonResult,
        action: "cancel",
      }),
  );

  register(
    "booking_get_booking_detail",
    BOOKING_TOOL_SLOTS.get_booking_detail,
    "safe",
    ["Chi tiết đặt vé", "Thanh toán"],
    (args) =>
      executeTicketById({
        args,
        positiveId,
        jsonResult,
        action: "detail",
      }),
  );

  register(
    "booking_modify_booking",
    BOOKING_TOOL_SLOTS.modify_booking,
    "sensitive",
    ["Đổi đặt vé", "Đổi giờ chuyến"],
    (args) => stub("booking_modify_booking", args),
  );

  register(
    "booking_reschedule_booking",
    BOOKING_TOOL_SLOTS.reschedule_booking,
    "sensitive",
    ["Đổi giờ chuyến", "Đổi vé"],
    (args) => stub("booking_reschedule_booking", args),
  );

  register(
    "ticket_get_ticket_detail",
    TICKET_TOOL_SLOTS.get_ticket_detail,
    "safe",
    ["Chi tiết vé", "Hủy vé"],
    (args) =>
      executeTicketById({
        args,
        positiveId,
        jsonResult,
        action: "detail",
      }),
  );

  register(
    "ticket_list_tickets",
    TICKET_TOOL_SLOTS.list_tickets,
    "safe",
    ["Vé của tôi", "Hủy vé", "Chi tiết vé"],
    (args) =>
      jsonResult(
        withQuery("ve", {
          ticket_status: args.ticket_status,
          from_date: args.from_date,
          to_date: args.to_date,
        }),
        { method: "GET", auth: "bearer" },
      ),
  );

  register(
    "ticket_validate_ticket",
    TICKET_TOOL_SLOTS.validate_ticket,
    "safe",
    ["Kiểm tra vé", "Chi tiết vé"],
    (args) => stub("ticket_validate_ticket", args),
  );

  register(
    "ticket_get_ticket_status",
    TICKET_TOOL_SLOTS.get_ticket_status,
    "safe",
    ["Trạng thái vé", "Chi tiết vé"],
    (args) =>
      executeTicketById({
        args,
        positiveId,
        jsonResult,
        action: "detail",
      }),
  );

  register(
    "ticket_cancel_ticket",
    TICKET_TOOL_SLOTS.cancel_ticket,
    "sensitive",
    ["Hủy vé", "Hoàn tiền"],
    (args) =>
      executeTicketById({
        args,
        positiveId,
        jsonResult,
        action: "cancel",
      }),
  );
}