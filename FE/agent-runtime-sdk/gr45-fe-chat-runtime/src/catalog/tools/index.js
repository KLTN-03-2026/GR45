import { registerAuthAccountTools } from "./auth-account.tools.js";
import { registerBookingTicketTools } from "./booking-ticket.tools.js";
import { registerPaymentRefundVoucherTools } from "./payment-refund-voucher.tools.js";
import { registerRouteTripSeatTools } from "./route-trip-seat.tools.js";
import { createCatalogToolRegistrar } from "./shared.js";
import { registerTrackingMapTransportSupportTools } from "./tracking-map-transport-support.tools.js";

let registeredPlannerPatterns = [];
/** @type {Map<string, (result: any) => Array<object>>} */
let registeredToolSuggestions = new Map();

export function registerAllCatalogTools(registry) {
  const ctx = createCatalogToolRegistrar(registry);
  registerAuthAccountTools(ctx);
  registerBookingTicketTools(ctx);
  registerPaymentRefundVoucherTools(ctx);
  registerTrackingMapTransportSupportTools(ctx);
  registerRouteTripSeatTools(ctx);
  registeredPlannerPatterns = ctx.getFastPlannerPatterns();
  registeredToolSuggestions = ctx.getToolSuggestionsMap();
  return {
    plannerPatterns: registeredPlannerPatterns,
    toolSuggestions: registeredToolSuggestions,
  };
}

export const registerGr45Tools = registerAllCatalogTools;
export const registerGr45CatalogTools = registerAllCatalogTools;

export function getGr45FastPlannerPatterns() {
  return registeredPlannerPatterns.slice();
}

export function getGr45ToolSuggestions() {
  return new Map(registeredToolSuggestions);
}

function authRequiredSuggestions(rows) {
  const hasAuthRequired = rows.some(
    (row) =>
      row?.data?.auth_required === true ||
      row?.data?.data?.auth_required === true,
  );
  return hasAuthRequired
    ? [{ text: "Đăng nhập", action: "login", params: {} }]
    : null;
}

function dedupeChips(chips) {
  const seen = new Set();
  const out = [];
  for (const chip of chips) {
    const key = `${chip?.action ?? ""}|${String(chip?.text ?? "").toLowerCase()}`;
    if (seen.has(key)) continue;
    seen.add(key);
    out.push(chip);
    if (out.length >= 6) break;
  }
  return out;
}

function chips(...texts) {
  return texts.map((text) => ({ text, action: "", params: {} }));
}

function fallbackSuggestionsForTool(row) {
  const name = String(row?.toolName ?? "");
  const ok = row?.ok === true;
  if (!ok) {
    if (name.startsWith("auth_")) return chips("Thử lại", "Quên mật khẩu");
    if (name.startsWith("support_")) return chips("Thử lại", "Liên hệ hỗ trợ");
    return chips("Thử lại", "Liên hệ hỗ trợ");
  }

  if (name === "auth_login") {
    return chips("Tìm chuyến xe", "Xem vé của tôi", "Xem hồ sơ");
  }
  if (name === "auth_logout") {
    return chips("Đăng nhập", "Tìm chuyến xe", "Liên hệ hỗ trợ");
  }
  if (
    name.startsWith("account_") ||
    name.startsWith("customer_") ||
    name.startsWith("loyalty_") ||
    name.includes("profile")
  ) {
    return chips("Xem hồ sơ", "Xem vé của tôi", "Tìm chuyến xe");
  }
  if (
    name.startsWith("search_") ||
    name.startsWith("trip_") ||
    name.startsWith("seat_") ||
    name.startsWith("pickup_") ||
    name.startsWith("dropoff_")
  ) {
    return chips("Đặt vé", "Xem sơ đồ ghế", "Tìm chuyến khác", "Liên hệ hỗ trợ");
  }
  if (name.startsWith("booking_") || name.startsWith("ticket_")) {
    return chips("Thanh toán", "Xem vé của tôi", "Hủy vé", "Liên hệ hỗ trợ");
  }
  if (
    name.startsWith("payment_") ||
    name.startsWith("refund_") ||
    name.startsWith("voucher_")
  ) {
    return chips("Xem vé của tôi", "Kiểm tra thanh toán", "Tìm chuyến xe");
  }
  if (
    name.startsWith("tracking_") ||
    name.startsWith("map_") ||
    name.startsWith("transport_")
  ) {
    return chips("Theo dõi chuyến", "Xem bản đồ", "Liên hệ hỗ trợ");
  }
  if (name === "support_close_support_session") {
    return chips("Liên hệ hỗ trợ", "Tìm chuyến xe", "Xem vé của tôi");
  }
  if (
    name === "support_create_support_session" ||
    name === "support_send_support_message" ||
    name === "support_get_support_messages"
  ) {
    return chips("Gửi tin nhắn hỗ trợ", "Kết thúc hỗ trợ", "Quay lại bot");
  }
  if (name.startsWith("support_")) {
    return chips("Liên hệ hỗ trợ", "Tìm chuyến xe");
  }
  return chips("Tìm chuyến xe", "Xem vé của tôi", "Liên hệ hỗ trợ");
}

export function deriveGr45ToolSuggestions(toolResults) {
  const rows = Array.isArray(toolResults) ? toolResults : [];
  const authChips = authRequiredSuggestions(rows);
  if (authChips) return authChips;

  const collected = [];
  for (const row of rows) {
    const fn = registeredToolSuggestions.get(row?.toolName);
    if (typeof fn !== "function") continue;
    try {
      const chips = fn(row);
      if (Array.isArray(chips) && chips.length > 0) collected.push(...chips);
    } catch {
      // Suggestion lỗi thì bỏ qua, không làm hỏng lượt chat.
    }
  }
  if (collected.length > 0) return dedupeChips(collected);

  for (const row of rows) {
    collected.push(...fallbackSuggestionsForTool(row));
  }
  if (collected.length > 0) return dedupeChips(collected);

  if (rows.length === 0) {
    return [
      { text: "Tìm chuyến xe", action: "", params: {} },
      { text: "Kiểm tra tuyến xe", action: "", params: {} },
      { text: "Liên hệ hỗ trợ", action: "", params: {} },
    ];
  }

  if (rows.some((row) => row?.ok === false)) {
    return [
      { text: "Thử lại", action: "", params: {} },
      { text: "Liên hệ hỗ trợ", action: "", params: {} },
    ];
  }

  return [];
}
