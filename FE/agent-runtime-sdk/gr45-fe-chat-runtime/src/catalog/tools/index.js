import { registerAuthAccountTools } from "./auth-account.tools.js";
import { registerBookingTicketTools } from "./booking-ticket.tools.js";
import { registerPaymentRefundVoucherTools } from "./payment-refund-voucher.tools.js";
import { registerRouteTripSeatTools } from "./route-trip-seat.tools.js";
import { createCatalogToolRegistrar } from "./shared.js";
import { registerTrackingMapTransportSupportTools } from "./tracking-map-transport-support.tools.js";

export function registerAllCatalogTools(registry) {
  const ctx = createCatalogToolRegistrar(registry);
  registerAuthAccountTools(ctx);
  registerRouteTripSeatTools(ctx);
  registerBookingTicketTools(ctx);
  registerPaymentRefundVoucherTools(ctx);
  registerTrackingMapTransportSupportTools(ctx);
}

export { GR45_FAST_PLANNER_PATTERNS } from "./patterns.js";
