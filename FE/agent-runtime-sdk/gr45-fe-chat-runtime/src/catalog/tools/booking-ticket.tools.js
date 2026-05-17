import {
  extractBookingId,
  extractMaVe,
  extractSeatIds,
  extractTripId,
} from "../../domain/planner/text-utils.js";
import {
  BOOKING_TOOL_SLOTS,
  TICKET_TOOL_SLOTS,
} from "../slots/booking-ticket.slots.js";

export function registerBookingTicketTools(ctx) {
  const { jsonResult, positiveId, register, stub, withQuery } = ctx;
  const ticketsFromListResult = (listRes) =>
    Array.isArray(listRes.data?.data?.data)
      ? listRes.data.data.data
      : Array.isArray(listRes.data?.data)
        ? listRes.data.data
        : Array.isArray(listRes.data)
          ? listRes.data
          : [];

  register(
    "booking_create_booking",
    BOOKING_TOOL_SLOTS.create_booking,
    "sensitive",
    ["Xác nhận đặt vé", "Thanh toán", "Chọn ghế"],
    async (args) => {
      const rawSeats =
        args.seat_ids == null
          ? args.danh_sach_ghe == null
            ? args.ma_ghe == null
              ? []
              : args.ma_ghe
            : args.danh_sach_ghe
          : args.seat_ids;
      const seats = Array.isArray(rawSeats)
        ? rawSeats
            .map(String)
            .map((seat) => seat.trim())
            .filter(Boolean)
        : rawSeats?.constructor === String
          ? rawSeats
              .split(/[;,]/)
              .map((seat) => seat.trim())
              .filter(Boolean)
          : [];
      const pickupId =
        args.id_tram_don === undefined ||
        args.id_tram_don === null ||
        args.id_tram_don === ""
          ? undefined
          : Number(args.id_tram_don);
      const dropoffId =
        args.id_tram_tra === undefined ||
        args.id_tram_tra === null ||
        args.id_tram_tra === ""
          ? undefined
          : Number(args.id_tram_tra);
      const voucherId =
        args.id_voucher === undefined ||
        args.id_voucher === null ||
        args.id_voucher === ""
          ? undefined
          : Number(args.id_voucher);
      const guestName = String(
        args.ho_va_ten_if_guest == null
          ? args.ho_va_ten == null
            ? ""
            : args.ho_va_ten
          : args.ho_va_ten_if_guest,
      ).trim();
      const guestPhone = String(
        args.so_dien_thoai_if_guest == null
          ? args.so_dien_thoai == null
            ? ""
            : args.so_dien_thoai
          : args.so_dien_thoai_if_guest,
      ).trim();
      const paymentMethod = String(
        args.payment_method == null
          ? args.phuong_thuc_thanh_toan == null
            ? ""
            : args.phuong_thuc_thanh_toan
          : args.payment_method,
      )
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/đ/g, "d")
        .replace(/\s+/g, "_");
      const payload = Object.fromEntries(
        Object.entries({
          id_chuyen_xe: Number(
            args.trip_id == null ? args.id_chuyen_xe : args.trip_id,
          ),
          danh_sach_ghe: seats,
          id_tram_don:
            Number.isFinite(pickupId) && pickupId > 0 ? pickupId : undefined,
          id_tram_tra:
            Number.isFinite(dropoffId) && dropoffId > 0 ? dropoffId : undefined,
          ghi_chu: String(args.ghi_chu == null ? "" : args.ghi_chu).trim()
            ? String(args.ghi_chu).trim()
            : undefined,
          id_voucher:
            Number.isFinite(voucherId) && voucherId > 0 ? voucherId : undefined,
          phuong_thuc_thanh_toan:
            paymentMethod.includes("chuyen") || paymentMethod.includes("bank")
              ? "chuyen_khoan"
              : paymentMethod.includes("vi")
                ? "vi_dien_tu"
                : paymentMethod.includes("tien") || paymentMethod.includes("cash")
                  ? "tien_mat"
                  : paymentMethod
                    ? paymentMethod
                    : undefined,
          sdt_khach_hang: guestPhone ? guestPhone : undefined,
          ho_va_ten: guestName ? guestName : undefined,
          ten_khach_hang: guestName ? guestName : undefined,
          diem_quy_doi:
            args.points != null || args.diem_quy_doi != null
              ? Number(args.points == null ? args.diem_quy_doi : args.points)
              : undefined,
        }).filter(([, value]) => value !== undefined),
      );

      if (
        !Number.isInteger(payload.id_chuyen_xe) ||
        payload.id_chuyen_xe <= 0
      ) {
        return { ok: false, error: "Thiếu trip_id hợp lệ." };
      }

      if (
        !Array.isArray(payload.danh_sach_ghe) ||
        payload.danh_sach_ghe.length === 0
      ) {
        return { ok: false, error: "Thiếu danh sách ghế." };
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
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
    },
    {
      test: (n) =>
        /\b(dat ve|book ve|mua ve)\b/.test(n) &&
        /\b(chuyen|trip)\s*(so|id|#)?\s*\d+/.test(n),
      build: (n) => {
        const args = {};
        const tripId = extractTripId(n);
        if (tripId) args.trip_id = tripId;
        const seats = extractSeatIds(n);
        if (seats.length) args.seat_ids = seats;
        return {
          toolName: "booking_create_booking",
          rationale: "Khách đặt vé chuyến cụ thể.",
          arguments: args,
        };
      },
    },
  );

  register(
    "booking_confirm_booking",
    BOOKING_TOOL_SLOTS.confirm_booking,
    "safe",
    ["Xác nhận đặt vé", "Thanh toán"],
    async (args) => {
      const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
      if (!maVe) return { ok: false, error: "Thiếu ma_ve." };
      const listRes = await jsonResult("ve", { method: "GET", auth: "bearer" });
      if (!listRes.ok) return listRes;
      const tickets = ticketsFromListResult(listRes);
      const found = tickets.find(
        (ticket) =>
          String(ticket.ma_ve == null ? "" : ticket.ma_ve).toLowerCase() ===
          maVe.toLowerCase(),
      );
      if (!found?.id) return { ok: false, error: `Không tìm thấy vé ${maVe} trong tài khoản.` };
      return {
        ok: true,
        data: {
          success: true,
          message: "Đã xác nhận thông tin đặt vé để tiếp tục thanh toán.",
          payment_method: args.payment_method ?? args.phuong_thuc_thanh_toan ?? null,
          data: found,
        },
      };
    },
    {
      test: (n) => /\b(xac nhan (dat ve|booking)|confirm booking)\b/.test(n),
      build: (n) => {
        const args = {};
        const ma = extractMaVe(n);
        if (ma) args.ma_ve = ma;
        if (/\b(chuyen khoan|bank)\b/.test(n)) args.payment_method = "chuyen_khoan";
        return {
          toolName: "booking_confirm_booking",
          rationale: "Khách xác nhận đặt vé.",
          arguments: args,
        };
      },
    },
  );

  register(
    "booking_cancel_booking",
    BOOKING_TOOL_SLOTS.cancel_booking,
    "sensitive",
    ["Hủy đặt vé", "Hoàn tiền"],
    async (args) => {
      const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
      if (args.ticket_id != null && args.ticket_id !== "") {
        const parsed = positiveId(args.ticket_id, "ticket_id");
        if (!parsed.ok) return parsed;
        return jsonResult(`ve/${parsed.id}/huy`, {
          method: "PATCH",
          auth: "bearer",
        });
      }
      if (!maVe) return { ok: false, error: "Thiếu ticket_id hoặc ma_ve." };
      const listRes = await jsonResult("ve", { method: "GET", auth: "bearer" });
      if (!listRes.ok) return listRes;
      const tickets = ticketsFromListResult(listRes);
      const found = tickets.find(
        (ticket) =>
          String(ticket.ma_ve == null ? "" : ticket.ma_ve).toLowerCase() ===
          maVe.toLowerCase(),
      );
      if (!found?.id) {
        return {
          ok: false,
          error: `Không tìm thấy vé ${maVe} trong tài khoản.`,
        };
      }
      return jsonResult(`ve/${found.id}/huy`, {
        method: "PATCH",
        auth: "bearer",
      });
    },
    {
      test: (n) => /\b(huy (dat ve|booking|don dat))\b/.test(n),
      build: (n) => {
        const args = {};
        const id = extractBookingId(n);
        if (id) args.booking_id = id;
        const ma = extractMaVe(n);
        if (ma) args.ma_ve = ma;
        return {
          toolName: "booking_cancel_booking",
          rationale: "Khách hủy đặt vé.",
          arguments: args,
        };
      },
    },
  );

  register(
    "booking_get_booking_detail",
    BOOKING_TOOL_SLOTS.get_booking_detail,
    "safe",
    ["Chi tiết đặt vé", "Thanh toán"],
    async (args) => {
      const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
      if (args.ticket_id != null && args.ticket_id !== "") {
        const parsed = positiveId(args.ticket_id, "ticket_id");
        if (!parsed.ok) return parsed;
        return jsonResult(`ve/${parsed.id}`, {
          method: "GET",
          auth: "bearer",
        });
      }
      if (!maVe) return { ok: false, error: "Thiếu ticket_id hoặc ma_ve." };
      const listRes = await jsonResult("ve", { method: "GET", auth: "bearer" });
      if (!listRes.ok) return listRes;
      const tickets = ticketsFromListResult(listRes);
      const found = tickets.find(
        (ticket) =>
          String(ticket.ma_ve == null ? "" : ticket.ma_ve).toLowerCase() ===
          maVe.toLowerCase(),
      );
      if (!found?.id) {
        return {
          ok: false,
          error: `Không tìm thấy vé ${maVe} trong tài khoản.`,
        };
      }
      return jsonResult(`ve/${found.id}`, {
        method: "GET",
        auth: "bearer",
      });
    },
    {
      test: (n) =>
        /\b(chi tiet (don )?dat ve|chi tiet booking|xem booking)\b/.test(n),
      build: (n) => {
        const args = {};
        const id = extractBookingId(n);
        if (id) args.booking_id = id;
        return {
          toolName: "booking_get_booking_detail",
          rationale: "Khách xem chi tiết đặt vé.",
          arguments: args,
        };
      },
    },
  );

  register(
    "booking_modify_booking",
    BOOKING_TOOL_SLOTS.modify_booking,
    "sensitive",
    ["Đổi đặt vé", "Đổi giờ chuyến"],
    async (args) => {
      const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
      if (!maVe) return { ok: false, error: "Thiếu ma_ve." };
      const listRes = await jsonResult("ve", { method: "GET", auth: "bearer" });
      if (!listRes.ok) return listRes;
      const tickets = ticketsFromListResult(listRes);
      const found = tickets.find(
        (ticket) =>
          String(ticket.ma_ve == null ? "" : ticket.ma_ve).toLowerCase() ===
          maVe.toLowerCase(),
      );
      if (!found?.id) return { ok: false, error: `Không tìm thấy vé ${maVe} trong tài khoản.` };
      return {
        ok: true,
        data: {
          success: true,
          message: "Đã ghi nhận yêu cầu đổi ghế, cần nhân viên xác nhận trước khi cập nhật vé.",
          requested_seat_ids: args.new_seat_ids ?? args.seat_ids ?? [],
          data: found,
        },
      };
    },
    {
      test: (n) => /\b(doi ghe|doi cho)\b.*\bve\b/.test(n),
      build: (n) => {
        const args = {};
        const ma = extractMaVe(n);
        if (ma) args.ma_ve = ma;
        const seats = extractSeatIds(n);
        if (seats.length) args.new_seat_ids = seats;
        return {
          toolName: "booking_modify_booking",
          rationale: "Khách đổi ghế vé.",
          arguments: args,
        };
      },
    },
  );

  register(
    "booking_reschedule_booking",
    BOOKING_TOOL_SLOTS.reschedule_booking,
    "sensitive",
    ["Đổi giờ chuyến", "Đổi vé"],
    async (args) => {
      const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
      if (!maVe) return { ok: false, error: "Thiếu ma_ve." };
      const listRes = await jsonResult("ve", { method: "GET", auth: "bearer" });
      if (!listRes.ok) return listRes;
      const tickets = ticketsFromListResult(listRes);
      const found = tickets.find(
        (ticket) =>
          String(ticket.ma_ve == null ? "" : ticket.ma_ve).toLowerCase() ===
          maVe.toLowerCase(),
      );
      if (!found?.id) return { ok: false, error: `Không tìm thấy vé ${maVe} trong tài khoản.` };
      return {
        ok: true,
        data: {
          success: true,
          message: "Đã ghi nhận yêu cầu đổi chuyến, cần nhân viên xác nhận trước khi cập nhật vé.",
          new_trip_id: args.new_trip_id ?? null,
          data: found,
        },
      };
    },
    {
      test: (n) =>
        /\b(doi (chuyen|lich|ve) (cho )?(booking|dat ve)?|reschedule)\b/.test(n) &&
        !/\btheo doi\b/.test(n),
      build: (n) => {
        const args = {};
        const id = extractBookingId(n);
        if (id) args.booking_id = id;
        const ma = extractMaVe(n);
        if (ma) args.ma_ve = ma;
        const tripMatches = [...String(n).matchAll(/\bchuyen\s+(\d+)\b/g)];
        const lastTrip = tripMatches[tripMatches.length - 1];
        if (lastTrip) args.new_trip_id = lastTrip[1];
        return {
          toolName: "booking_reschedule_booking",
          rationale: "Khách đổi chuyến cho đơn đặt.",
          arguments: args,
        };
      },
    },
  );

  register(
    "ticket_get_ticket_detail",
    TICKET_TOOL_SLOTS.get_ticket_detail,
    "safe",
    ["Chi tiết vé", "Hủy vé"],
    async (args) => {
      const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
      if (args.ticket_id != null && args.ticket_id !== "") {
        const parsed = positiveId(args.ticket_id, "ticket_id");
        if (!parsed.ok) return parsed;
        return jsonResult(`ve/${parsed.id}`, {
          method: "GET",
          auth: "bearer",
        });
      }
      if (!maVe) return { ok: false, error: "Thiếu ticket_id hoặc ma_ve." };
      const listRes = await jsonResult("ve", { method: "GET", auth: "bearer" });
      if (!listRes.ok) return listRes;
      const tickets = ticketsFromListResult(listRes);
      const found = tickets.find(
        (ticket) =>
          String(ticket.ma_ve == null ? "" : ticket.ma_ve).toLowerCase() ===
          maVe.toLowerCase(),
      );
      if (!found?.id) {
        return {
          ok: false,
          error: `Không tìm thấy vé ${maVe} trong tài khoản.`,
        };
      }
      return jsonResult(`ve/${found.id}`, {
        method: "GET",
        auth: "bearer",
      });
    },
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
    {
      test: (n) =>
        /\b(ve|nhung ve|cac ve|danh sach ve)\s+(cua toi|cua minh|da dat|toi da)\b/.test(
          n,
        ) || /\b(xem|liet ke)\s+(ve|cac ve|danh sach ve)\b/.test(n),
      build: () => ({
        toolName: "ticket_list_tickets",
        rationale: "Khách muốn xem danh sách vé.",
        arguments: {},
      }),
    },
  );

  register(
    "ticket_validate_ticket",
    TICKET_TOOL_SLOTS.validate_ticket,
    "safe",
    ["Kiểm tra vé", "Chi tiết vé"],
    (args) => stub("ticket_validate_ticket", args),
    {
      test: (n) =>
        /\b(kiem tra ve|xac thuc ve|validate ticket|ve.*hop le)\b/.test(n) &&
        !/\b(gui tin nhan ho tro|gui ho tro|tra loi ho tro|xem tin nhan ho tro|dong phien ho tro|ket thuc ho tro)\b/.test(
          n,
        ),
      build: (n) => {
        const args = {};
        const ma = extractMaVe(n);
        if (ma) args.ma_ve = ma;
        return {
          toolName: "ticket_validate_ticket",
          rationale: "Khách kiểm tra vé.",
          arguments: args,
        };
      },
    },
  );

  register(
    "ticket_get_ticket_status",
    TICKET_TOOL_SLOTS.get_ticket_status,
    "safe",
    ["Trạng thái vé", "Chi tiết vé"],
    async (args) => {
      const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
      if (args.ticket_id != null && args.ticket_id !== "") {
        const parsed = positiveId(args.ticket_id, "ticket_id");
        if (!parsed.ok) return parsed;
        return jsonResult(`ve/${parsed.id}`, {
          method: "GET",
          auth: "bearer",
        });
      }
      if (!maVe) return { ok: false, error: "Thiếu ticket_id hoặc ma_ve." };
      const listRes = await jsonResult("ve", { method: "GET", auth: "bearer" });
      if (!listRes.ok) return listRes;
      const tickets = ticketsFromListResult(listRes);
      const found = tickets.find(
        (ticket) =>
          String(ticket.ma_ve == null ? "" : ticket.ma_ve).toLowerCase() ===
          maVe.toLowerCase(),
      );
      if (!found?.id) {
        return {
          ok: false,
          error: `Không tìm thấy vé ${maVe} trong tài khoản.`,
        };
      }
      return jsonResult(`ve/${found.id}`, {
        method: "GET",
        auth: "bearer",
      });
    },
    {
      test: (n) => /\b(trang thai ve|chi tiet ve|tinh trang ve)\b/.test(n),
      build: (n) => {
        const args = {};
        const ma = extractMaVe(n);
        if (ma) args.ma_ve = ma;
        const isStatus = /\btrang thai\b|\btinh trang\b/.test(n);
        return {
          toolName: isStatus
            ? "ticket_get_ticket_status"
            : "ticket_get_ticket_detail",
          rationale: isStatus
            ? "Khách xem trạng thái vé."
            : "Khách xem chi tiết vé.",
          arguments: args,
        };
      },
    },
  );

  register(
    "ticket_cancel_ticket",
    TICKET_TOOL_SLOTS.cancel_ticket,
    "sensitive",
    ["Hủy vé", "Hoàn tiền"],
    async (args) => {
      const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
      if (args.ticket_id != null && args.ticket_id !== "") {
        const parsed = positiveId(args.ticket_id, "ticket_id");
        if (!parsed.ok) return parsed;
        return jsonResult(`ve/${parsed.id}/huy`, {
          method: "PATCH",
          auth: "bearer",
        });
      }
      if (!maVe) return { ok: false, error: "Thiếu ticket_id hoặc ma_ve." };
      const listRes = await jsonResult("ve", { method: "GET", auth: "bearer" });
      if (!listRes.ok) return listRes;
      const tickets = ticketsFromListResult(listRes);
      const found = tickets.find(
        (ticket) =>
          String(ticket.ma_ve == null ? "" : ticket.ma_ve).toLowerCase() ===
          maVe.toLowerCase(),
      );
      if (!found?.id) {
        return {
          ok: false,
          error: `Không tìm thấy vé ${maVe} trong tài khoản.`,
        };
      }
      return jsonResult(`ve/${found.id}/huy`, {
        method: "PATCH",
        auth: "bearer",
      });
    },
    {
      test: (n) => /\b(huy ve|cancel ticket)\b/.test(n),
      build: (n) => {
        const args = {};
        const ma = extractMaVe(n);
        if (ma) args.ma_ve = ma;
        return {
          toolName: "ticket_cancel_ticket",
          rationale: "Khách hủy vé.",
          arguments: args,
        };
      },
    },
  );
}
