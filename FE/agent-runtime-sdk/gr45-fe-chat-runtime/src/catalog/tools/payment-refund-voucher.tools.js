import {
  extractMaVe,
  extractPaymentCode,
  extractVoucherCode,
} from "../../domain/planner/text-utils.js";
import {
  PAYMENT_TOOL_SLOTS,
  REFUND_TOOL_SLOTS,
  VOUCHER_TOOL_SLOTS,
} from "../slots/payment-refund-voucher.slots.js";

export function registerPaymentRefundVoucherTools(ctx) {
  const { getKhachBearerHeaders, jsonResult, register, stub } = ctx;

  const paymentImplemented = new Set([
    "create_payment",
    "get_payment_status",
    "retry_payment",
  ]);
  const refundImplemented = new Set(["estimate_refund", "get_refund_status"]);

  for (const [key, spec] of Object.entries(PAYMENT_TOOL_SLOTS)) {
    if (paymentImplemented.has(key)) continue;

    register(
      `payment_${key}`,
      spec,
      "sensitive",
      ["Thanh toán", "Trạng thái thanh toán"],
      (args) => stub(`payment_${key}`, args),
    );
  }

  for (const [key, spec] of Object.entries(REFUND_TOOL_SLOTS)) {
    if (refundImplemented.has(key)) continue;

    register(
      `refund_${key}`,
      spec,
      spec.confirm ? "sensitive" : "safe",
      ["Hoàn tiền", "Hủy vé"],
      (args) => stub(`refund_${key}`, args),
    );
  }

  register(
    "payment_create_payment",
    PAYMENT_TOOL_SLOTS.create_payment,
    "sensitive",
    ["Thanh toán", "Trạng thái thanh toán"],
    async (args) => {
      const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
      if (!maVe) return { ok: false, error: "Thiếu ma_ve." };
      const result = await jsonResult("agent/booking-tools/payment-status", {
        method: "POST",
        auth: "optional",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ma_ve: maVe }),
      });
      if (!result.ok) return result;
      return {
        ok: true,
        data: {
          success: true,
          message:
            "Đã tạo yêu cầu thanh toán ở mức chat: dùng thông tin vé hiện tại để tiếp tục thanh toán.",
          payment_method: args.payment_method ?? args.phuong_thuc_thanh_toan ?? null,
          data: result.data?.data ?? result.data,
        },
      };
    },
    {
      test: (n) => /\b(tao thanh toan|thanh toan cho ve)\b/.test(n),
      build: (n) => {
        const args = {};
        const ma = extractMaVe(n);
        if (ma) args.ma_ve = ma;
        if (/\b(chuyen khoan|bank)\b/.test(n)) args.payment_method = "chuyen_khoan";
        return {
          toolName: "payment_create_payment",
          rationale: "Khách tạo thanh toán cho vé.",
          arguments: args,
        };
      },
    },
  );

  register(
    "payment_retry_payment",
    PAYMENT_TOOL_SLOTS.retry_payment,
    "sensitive",
    ["Thanh toán lại", "Trạng thái thanh toán"],
    async (args) => {
      const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
      if (!maVe) return { ok: false, error: "Thiếu ma_ve." };
      const result = await jsonResult("agent/booking-tools/payment-status", {
        method: "POST",
        auth: "optional",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ma_ve: maVe }),
      });
      if (!result.ok) return result;
      return {
        ok: true,
        data: {
          success: true,
          message: "Đã lấy lại thông tin thanh toán để khách thanh toán lại.",
          data: result.data?.data ?? result.data,
        },
      };
    },
    {
      test: (n) => /\b(thanh toan lai|retry payment)\b/.test(n),
      build: (n) => {
        const args = {};
        const ma = extractMaVe(n);
        if (ma) args.ma_ve = ma;
        return {
          toolName: "payment_retry_payment",
          rationale: "Khách muốn thanh toán lại.",
          arguments: args,
        };
      },
    },
  );

  register(
    "payment_get_payment_status",
    PAYMENT_TOOL_SLOTS.get_payment_status,
    "safe",
    ["Trạng thái thanh toán", "Chi tiết vé"],
    async (args) => {
      let identifier = "";
      let identifierKey = "";
      for (const key of ["ma_ve", "payment_id", "ma_thanh_toan"]) {
        const value = String(args?.[key] == null ? "" : args[key]).trim();
        if (value) {
          identifier = value;
          identifierKey = key;
          break;
        }
      }

      if (!identifier) {
        return {
          ok: false,
          error: "Thiếu ma_ve, payment_id hoặc ma_thanh_toan.",
        };
      }

      return jsonResult("agent/booking-tools/payment-status", {
        method: "POST",
        auth: "optional",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ma_ve: identifierKey === "ma_ve" ? identifier : undefined,
          payment_id: identifierKey === "payment_id" ? identifier : undefined,
          ma_thanh_toan:
            identifierKey === "ma_thanh_toan" ? identifier : undefined,
        }),
      });
    },
    {
      test: (n) =>
        /\b(trang thai thanh toan|payment status|kiem tra thanh toan|da thanh toan chua)\b/.test(
          n,
        ),
      build: (n) => {
        const args = {};
        const code = extractPaymentCode(n);
        if (code && /^PT/i.test(code)) args.ma_thanh_toan = code;
        if (code && !/^PT/i.test(code)) args.ma_ve = code;
        return {
          toolName: "payment_get_payment_status",
          rationale: "Khách xem trạng thái thanh toán.",
          arguments: args,
        };
      },
    },
  );

  register(
    "refund_estimate_refund",
    REFUND_TOOL_SLOTS.estimate_refund,
    "safe",
    ["Ước tính hoàn tiền", "Hủy vé"],
    async (args) => {
      let maVe = "";
      for (const key of ["ma_ve", "ticket_id"]) {
        const value = String(args?.[key] == null ? "" : args[key]).trim();
        if (value) {
          maVe = value;
          break;
        }
      }

      if (!maVe) {
        return {
          ok: false,
          error: "Thiếu ma_ve hoặc ticket_id.",
        };
      }

      return jsonResult("agent/booking-tools/refund-estimate", {
        method: "POST",
        auth: "optional",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ma_ve: maVe,
          ticket_id: maVe,
        }),
      });
    },
    {
      test: (n) =>
        /\b(hoan tien|uoc tinh hoan|refund estimate|tinh tien hoan)\b/.test(n) &&
        !/\b(trang thai|kiem tra)\b/.test(n),
      build: (n) => {
        const args = {};
        const ma = extractMaVe(n);
        if (ma) args.ma_ve = ma;
        return {
          toolName: "refund_estimate_refund",
          rationale: "Khách ước tính tiền hoàn.",
          arguments: args,
        };
      },
    },
  );

  register(
    "refund_get_refund_status",
    REFUND_TOOL_SLOTS.get_refund_status,
    "safe",
    ["Trạng thái hoàn tiền", "Hủy vé"],
    async (args) => {
      const refundId = String(args.refund_id == null ? "" : args.refund_id).trim();
      const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
      if (maVe) {
        return jsonResult("agent/booking-tools/refund-estimate", {
          method: "POST",
          auth: "optional",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ ma_ve: maVe }),
        });
      }
      if (!refundId) return { ok: false, error: "Thiếu refund_id hoặc ma_ve." };
      return {
        ok: true,
        data: {
          success: true,
          refund_id: refundId,
          status: "dang_xu_ly",
          message:
            "Mã hoàn tiền đã được ghi nhận ở mức chat; backend hiện chưa có bảng hoàn tiền riêng cho mã này.",
        },
      };
    },
    {
      test: (n) => /\b(trang thai hoan tien|kiem tra.*hoan tien)\b/.test(n),
      build: (n) => {
        const args = {};
        const ma = extractMaVe(n);
        if (ma) args.ma_ve = ma;
        const rf = String(n).match(/\b(rf[a-z0-9]+)\b/i);
        if (rf) args.refund_id = rf[1].toUpperCase();
        return {
          toolName: "refund_get_refund_status",
          rationale: "Khách kiểm tra trạng thái hoàn tiền.",
          arguments: args,
        };
      },
    },
  );

  register(
    "voucher_validate_voucher",
    VOUCHER_TOOL_SLOTS.validate_voucher,
    "safe",
    ["Áp dụng voucher", "Voucher khả dụng"],
    async (args) => {
      let code = "";
      for (const key of ["voucher_code", "code"]) {
        const value = String(args?.[key] == null ? "" : args[key]).trim();
        if (value) {
          code = value;
          break;
        }
      }

      if (!code) {
        return { ok: false, error: "Thiếu voucher_code." };
      }

      return jsonResult("voucher/validate-for-chat", {
        method: "POST",
        auth: "optional",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          voucher_code: code,
          trip_id: args.trip_id,
          ma_nha_xe: args.ma_nha_xe,
          booking_amount: args.booking_amount,
        }),
      });
    },
    {
      test: (n) =>
        /\b(kiem tra voucher|voucher.*hop le|validate voucher)\b/.test(n),
      build: (_n, _t, rawText) => {
        const args = {};
        const code = extractVoucherCode(rawText);
        if (code) args.voucher_code = code;
        return {
          toolName: "voucher_validate_voucher",
          rationale: "Khách kiểm tra voucher.",
          arguments: args,
        };
      },
    },
  );

  register(
    "voucher_apply_voucher",
    VOUCHER_TOOL_SLOTS.apply_voucher,
    "safe",
    ["Áp dụng voucher", "Thanh toán"],
    async (args) => {
      let code = "";
      for (const key of ["voucher_code", "code"]) {
        const value = String(args?.[key] == null ? "" : args[key]).trim();
        if (value) {
          code = value;
          break;
        }
      }

      if (!code) {
        return { ok: false, error: "Thiếu voucher_code." };
      }

      const rawAmount =
        args.booking_amount == null ? args.amount : args.booking_amount;

      if (rawAmount === undefined || rawAmount === null || rawAmount === "") {
        return {
          ok: false,
          error:
            "Thiếu booking_amount. Nếu đang có booking draft, runtime cần truyền tổng tiền vào tool.",
        };
      }

      const amount = Number(rawAmount);

      if (!Number.isFinite(amount) || amount < 0) {
        return {
          ok: false,
          error: "booking_amount không hợp lệ.",
        };
      }

      return jsonResult("voucher/preview-discount-for-chat", {
        method: "POST",
        auth: "optional",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          voucher_code: code,
          booking_amount: amount,
          trip_id: args.trip_id,
          ma_nha_xe: args.ma_nha_xe,
        }),
      });
    },
    {
      test: (n) =>
        /\b(ap dung voucher|dung voucher|ap ma giam|ap ma|nhap voucher)\b/.test(
          n,
        ) && !/\b(lich su|da dung|su dung)\b/.test(n),
      build: (_n, _t, rawText) => {
        const args = {};
        const code = extractVoucherCode(rawText);
        if (code) args.voucher_code = code;
        const amount = String(rawText == null ? "" : rawText).match(
          /\b(\d{4,12})\b/,
        );
        if (amount) args.booking_amount = Number(amount[1]);
        return {
          toolName: "voucher_apply_voucher",
          rationale: "Khách áp voucher.",
          arguments: args,
        };
      },
    },
  );

  register(
    "voucher_list_available_vouchers",
    VOUCHER_TOOL_SLOTS.list_available_vouchers,
    "safe",
    ["Voucher khả dụng", "Áp dụng voucher", "Khuyến mãi"],
    () => {
      const headers = getKhachBearerHeaders();
      const path = headers.Authorization
        ? "voucher/huntable"
        : "voucher/public";

      return jsonResult(path, {
        method: "GET",
        auth: "optional",
      });
    },
    {
      test: (n) =>
        /\b(voucher|ma giam gia|khuyen mai)\b/.test(n) &&
        !/\b(ap dung|ap ma|nhap|lich su|da dung|su dung)\b/.test(n),
      build: () => ({
        toolName: "voucher_list_available_vouchers",
        rationale: "Khách xem voucher khả dụng.",
        arguments: {},
      }),
    },
  );

  register(
    "voucher_get_voucher_usage_history",
    VOUCHER_TOOL_SLOTS.get_voucher_usage_history,
    "safe",
    ["Lịch sử voucher", "Voucher khả dụng"],
    async () => {
      const result = await jsonResult("voucher", { method: "GET", auth: "bearer" });
      if (!result.ok) return result;
      return {
        ok: true,
        data: {
          success: true,
          message:
            "Đã kiểm tra lịch sử/ví voucher của khách. Nếu danh sách rỗng nghĩa là khách chưa lưu hoặc chưa dùng voucher.",
          vouchers: result.data?.data ?? result.data,
        },
      };
    },
    {
      test: (n) =>
        /\b(lich su voucher|lich su dung voucher|voucher da dung|voucher su dung)\b/.test(n),
      build: () => ({
        toolName: "voucher_get_voucher_usage_history",
        rationale: "Khách xem lịch sử dùng voucher.",
        arguments: {},
      }),
    },
  );
}
