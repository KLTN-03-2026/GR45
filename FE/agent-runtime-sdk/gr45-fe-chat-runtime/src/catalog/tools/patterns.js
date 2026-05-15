/**
 * Aggregator for rule-base patterns across all tool files.
 *
 * Each tool file exports its own `XXX_TOOL_PATTERNS` array; this file just
 * concatenates them in deterministic priority order. First match wins.
 *
 * Order rules (cross-file precedence):
 *   1. auth / account / password / loyalty — login first so phone in msg doesn't divert
 *   2. booking / ticket — specific booking + ticket actions BEFORE ticket-list
 *   3. payment / refund / voucher — apply BEFORE list
 *   4. tracking / map / transport / support — specific tracking BEFORE live
 *   5. route / trip / seat — search_routes / search_trips broad fallback LAST
 *
 * This file is intentionally zod-free so a rule-only smoke test (no zod
 * runtime) can load it.
 */
import { AUTH_ACCOUNT_TOOL_PATTERNS } from "./auth-account.tools.js";
import { BOOKING_TICKET_TOOL_PATTERNS } from "./booking-ticket.tools.js";
import { PAYMENT_REFUND_VOUCHER_TOOL_PATTERNS } from "./payment-refund-voucher.tools.js";
import { ROUTE_TRIP_SEAT_TOOL_PATTERNS } from "./route-trip-seat.tools.js";
import { TRACKING_MAP_TRANSPORT_SUPPORT_TOOL_PATTERNS } from "./tracking-map-transport-support.tools.js";

export const GR45_FAST_PLANNER_PATTERNS = [
  ...AUTH_ACCOUNT_TOOL_PATTERNS,
  ...BOOKING_TICKET_TOOL_PATTERNS,
  ...PAYMENT_REFUND_VOUCHER_TOOL_PATTERNS,
  ...TRACKING_MAP_TRANSPORT_SUPPORT_TOOL_PATTERNS,
  ...ROUTE_TRIP_SEAT_TOOL_PATTERNS,
];
