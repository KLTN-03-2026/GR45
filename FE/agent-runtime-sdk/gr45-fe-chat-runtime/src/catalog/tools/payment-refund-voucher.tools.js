import {
  extractMaVe,
  extractPaymentCode,
  extractVoucherCode,
} from "../../fast-planner/text-utils.js";
import {
  PAYMENT_TOOL_SLOTS,
  REFUND_TOOL_SLOTS,
  VOUCHER_TOOL_SLOTS,
} from "../slots.js";

function asString(value) {
  return String(value ?? "").trim();
}

function jsonBody(body) {
  return {
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  };
}

function firstFilled(args, keys) {
  for (const key of keys) {
    const value = asString(args?.[key]);
    if (value) return value;
  }
  return "";
}

function asPositiveAmount(value) {
  const n = Number(value);
  return Number.isFinite(n) && n >= 0 ? n : null;
}

export function registerPaymentRefundVoucherTools(ctx) {
  const { getKhachBearerHeaders, jsonResult, register, stub } = ctx;

  const paymentImplemented = new Set(["get_payment_status"]);
  const refundImplemented = new Set(["estimate_refund"]);

  for (const [key, spec] of Object.entries(PAYMENT_TOOL_SLOTS)) {
    if (paymentImplemented.has(key)) continue;

    register(`payment_${key}`, spec, "sensitive", ["Thanh toán", "Trạng thái thanh toán"], (args) =>
      stub(`payment_${key}`, args),
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
    "payment_get_payment_status",
    PAYMENT_TOOL_SLOTS.get_payment_status,
    "safe",
    ["Trạng thái thanh toán", "Chi tiết vé"],
    async (args) => {
      const identifier = firstFilled(args, [
        "ma_ve",
        "payment_id",
        "ma_thanh_toan",
      ]);

      if (!identifier) {
        return {
          ok: false,
          error: "Thiếu ma_ve, payment_id hoặc ma_thanh_toan.",
        };
      }

      return jsonResult("agent/booking-tools/payment-status", {
        method: "POST",
        auth: "optional",
        ...jsonBody({
          ma_ve: identifier,
          payment_id: identifier,
          ma_thanh_toan: identifier,
        }),
      });
    },
  );

  register(
    "refund_estimate_refund",
    REFUND_TOOL_SLOTS.estimate_refund,
    "safe",
    ["Ước tính hoàn tiền", "Hủy vé"],
    async (args) => {
      const maVe = firstFilled(args, ["ma_ve", "ticket_id"]);

      if (!maVe) {
        return {
          ok: false,
          error: "Thiếu ma_ve hoặc ticket_id.",
        };
      }

      return jsonResult("agent/booking-tools/refund-estimate", {
        method: "POST",
        auth: "optional",
        ...jsonBody({
          ma_ve: maVe,
          ticket_id: maVe,
        }),
      });
    },
  );

  register(
    "voucher_validate_voucher",
    VOUCHER_TOOL_SLOTS.validate_voucher,
    "safe",
    ["Áp dụng voucher", "Voucher khả dụng"],
    async (args) => {
      const code = firstFilled(args, ["voucher_code", "code"]);

      if (!code) {
        return { ok: false, error: "Thiếu voucher_code." };
      }

      return jsonResult("voucher/validate-for-chat", {
        method: "POST",
        auth: "optional",
        ...jsonBody({
          voucher_code: code,
          trip_id: args.trip_id,
          ma_nha_xe: args.ma_nha_xe,
          booking_amount: args.booking_amount,
        }),
      });
    },
  );

  register(
    "voucher_apply_voucher",
    VOUCHER_TOOL_SLOTS.apply_voucher,
    "safe",
    ["Áp dụng voucher", "Thanh toán"],
    async (args) => {
      const code = firstFilled(args, ["voucher_code", "code"]);

      if (!code) {
        return { ok: false, error: "Thiếu voucher_code." };
      }

      const rawAmount = args.booking_amount ?? args.amount;

      if (rawAmount === undefined || rawAmount === null || rawAmount === "") {
        return {
          ok: false,
          error:
            "Thiếu booking_amount. Nếu đang có booking draft, runtime cần truyền tổng tiền vào tool.",
        };
      }

      const amount = asPositiveAmount(rawAmount);

      if (amount === null) {
        return {
          ok: false,
          error: "booking_amount không hợp lệ.",
        };
      }

      return jsonResult("voucher/preview-discount-for-chat", {
        method: "POST",
        auth: "optional",
        ...jsonBody({
          voucher_code: code,
          booking_amount: amount,
          trip_id: args.trip_id,
          ma_nha_xe: args.ma_nha_xe,
        }),
      });
    },
  );

  register(
    "voucher_list_available_vouchers",
    VOUCHER_TOOL_SLOTS.list_available_vouchers,
    "safe",
    ["Voucher khả dụng", "Áp dụng voucher", "Khuyến mãi"],
    () => {
      const headers = getKhachBearerHeaders();
      const path = headers.Authorization ? "voucher/huntable" : "voucher/public";

      return jsonResult(path, {
        method: "GET",
        auth: "optional",
      });
    },
  );

  register(
    "voucher_get_voucher_usage_history",
    VOUCHER_TOOL_SLOTS.get_voucher_usage_history,
    "safe",
    ["Lịch sử voucher", "Voucher khả dụng"],
    (args) => stub("voucher_get_voucher_usage_history", args),
  );
}

/**
 * Rule-base triggers for payment / refund / voucher.
 * Voucher apply/validate must come BEFORE voucher list.
 */
export const PAYMENT_REFUND_VOUCHER_TOOL_PATTERNS = [
  // payment_get_payment_status
  {
    test: (n) =>
      /\b(trang thai thanh toan|payment status|kiem tra thanh toan|da thanh toan chua)\b/.test(
        n,
      ),
    build: (n) => {
      const args = {};
      const code = extractPaymentCode(n);
      if (code) args.ma_thanh_toan = code;
      return {
        toolName: "payment_get_payment_status",
        rationale: "Khách xem trạng thái thanh toán.",
        arguments: args,
      };
    },
  },
  // refund_estimate_refund
  {
    test: (n) =>
      /\b(hoan tien|uoc tinh hoan|refund estimate|tinh tien hoan)\b/.test(n),
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
  // voucher_apply_voucher
  {
    test: (n) =>
      /\b(ap dung voucher|dung voucher|ap ma giam|ap ma|nhap voucher)\b/.test(
        n,
      ),
    build: (_n, _t, rawText) => {
      const args = {};
      const code = extractVoucherCode(rawText);
      if (code) args.voucher_code = code;
      return {
        toolName: "voucher_apply_voucher",
        rationale: "Khách áp voucher.",
        arguments: args,
      };
    },
  },
  // voucher_validate_voucher
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
  // voucher_get_voucher_usage_history
  {
    test: (n) =>
      /\b(lich su voucher|voucher da dung|voucher su dung)\b/.test(n),
    build: () => ({
      toolName: "voucher_get_voucher_usage_history",
      rationale: "Khách xem lịch sử dùng voucher.",
      arguments: {},
    }),
  },
  // voucher_list_available_vouchers
  {
    test: (n) =>
      /\b(voucher|ma giam gia|khuyen mai)\b/.test(n) &&
      !/\b(ap dung|ap ma|nhap)\b/.test(n),
    build: () => ({
      toolName: "voucher_list_available_vouchers",
      rationale: "Khách xem voucher khả dụng.",
      arguments: {},
    }),
  },
];