import {
  MAP_LOCATION_TOOL_SLOTS,
  SUPPORT_TOOL_SLOTS,
  TRACKING_TOOL_SLOTS,
  TRANSPORT_INFO_TOOL_SLOTS,
} from "../slots.js";

const SUPPORT_PUBLIC_ID_RE = /^[a-zA-Z0-9_-]{3,120}$/;
const SUPPORT_PHONE_RE = /^[0-9+()\-\s]{8,24}$/;
const SUPPORT_MESSAGE_MAX = 2000;

function asString(value) {
  return String(value ?? "").trim();
}

function jsonBody(body) {
  return {
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(
      Object.fromEntries(
        Object.entries(body).filter(([, value]) => value !== undefined),
      ),
    ),
  };
}

function optionalPositiveInt(value) {
  const raw = asString(value);
  if (!raw) return undefined;

  const n = Number.parseInt(raw, 10);
  return Number.isInteger(n) && n > 0 ? n : undefined;
}

function validatePublicId(value) {
  const publicId = asString(value);

  if (!publicId) {
    return { ok: false, error: "Thiếu public_id phiên hỗ trợ." };
  }

  if (!SUPPORT_PUBLIC_ID_RE.test(publicId)) {
    return { ok: false, error: "public_id phiên hỗ trợ không hợp lệ." };
  }

  return { ok: true, publicId };
}

function validateGuestPhone(value) {
  const phone = asString(value);

  if (!phone) return { ok: true, phone: undefined };

  if (!SUPPORT_PHONE_RE.test(phone)) {
    return { ok: false, error: "SĐT khách không hợp lệ." };
  }

  return { ok: true, phone: phone.slice(0, 32) };
}

function getTrackingLive(jsonResult, positiveId, args) {
  const parsed = positiveId(args.trip_id, "trip_id");
  if (!parsed.ok) return Promise.resolve(parsed);

  return jsonResult(`chuyen-xe/${parsed.id}/tracking/live`, {
    method: "GET",
    auth: "none",
  });
}

function normalizeTrackingPayload(result) {
  if (!result.ok) return result;

  const payload = result.data?.data ?? result.data ?? {};

  return {
    ok: true,
    data: {
      success: true,
      status:
        payload.trang_thai ??
        payload.status ??
        payload.tinh_trang ??
        null,
      current_location: {
        lat:
          payload.lat ??
          payload.latitude ??
          payload.vi_do ??
          payload.toa_do_x ??
          null,
        lng:
          payload.lng ??
          payload.longitude ??
          payload.kinh_do ??
          payload.toa_do_y ??
          null,
      },
      speed:
        payload.speed ??
        payload.toc_do ??
        payload.vehicle_speed ??
        null,
      last_updated:
        payload.updated_at ??
        payload.thoi_diem_ghi ??
        payload.timestamp ??
        null,
      raw: payload,
    },
  };
}

function getTripSummary(jsonResult, positiveId, args) {
  const parsed = positiveId(args.trip_id, "trip_id");
  if (!parsed.ok) return Promise.resolve(parsed);

  return jsonResult(`chuyen-xe/${parsed.id}/tom-tat`, {
    method: "GET",
    auth: "none",
  });
}

function normalizeSupportTarget(args) {
  const targetRaw = asString(args.target).toLowerCase();
  const hasOperator = Boolean(asString(args.ma_nha_xe));

  if (!targetRaw && hasOperator) return "nha_xe";
  if (!targetRaw) return "admin";

  if (targetRaw === "admin" || targetRaw === "nha_xe") {
    return targetRaw;
  }

  return null;
}

function buildSupportSessionBody(args) {
  const target = normalizeSupportTarget(args);

  if (!target) {
    return {
      ok: false,
      error: "target không hợp lệ. Chỉ hỗ trợ `admin` hoặc `nha_xe`.",
    };
  }

  const maNhaXe = asString(args.ma_nha_xe);

  if (target === "nha_xe" && !maNhaXe) {
    return {
      ok: false,
      error:
        "Để nhắn nhà xe cần ma_nha_xe. Nếu chưa chọn nhà xe, dùng target=admin.",
    };
  }

  const guestPhone = validateGuestPhone(args.guest_phone);
  if (!guestPhone.ok) return guestPhone;

  return {
    ok: true,
    body: {
      target,
      chat_widget_session_key:
        asString(args.chat_widget_session_key).slice(0, 255) || undefined,
      ma_nha_xe: maNhaXe || undefined,
      id_chuyen_xe: optionalPositiveInt(args.trip_id ?? args.id_chuyen_xe),
      guest_name: asString(args.guest_name).slice(0, 120) || undefined,
      guest_phone: guestPhone.phone,
      guest_email: asString(args.guest_email).slice(0, 160) || undefined,
      initial_message:
        asString(args.initial_message).slice(0, SUPPORT_MESSAGE_MAX) ||
        undefined,
    },
  };
}

function registerTripSummaryTransportTool({
  register,
  jsonResult,
  positiveId,
  key,
  spec,
}) {
  register(`transport_${key}`, spec, "safe", ["Thông tin xe", "Tài xế", "Chi tiết chuyến"], (args) => {
    if (args.vehicle_id && !args.trip_id) {
      return Promise.resolve({
        ok: false,
        error:
          "Tool này hiện resolve qua trip_id. Nếu chỉ có vehicle_id cần AgentToolController riêng.",
      });
    }

    return getTripSummary(jsonResult, positiveId, args);
  });
}

export function registerTrackingMapTransportSupportTools(ctx) {
  const { jsonResult, positiveId, register, stub } = ctx;

  register(
    "tracking_get_live_vehicle_location",
    TRACKING_TOOL_SLOTS.get_live_vehicle_location,
    "safe",
    ["Vị trí xe", "Theo dõi chuyến", "Tốc độ xe"],
    (args) => getTrackingLive(jsonResult, positiveId, args),
  );

  register(
    "tracking_get_trip_progress",
    TRACKING_TOOL_SLOTS.get_trip_progress,
    "safe",
    ["Theo dõi chuyến", "Vị trí xe"],
    (args) => stub("tracking_get_trip_progress", args),
  );

  register(
    "tracking_estimate_arrival_time",
    TRACKING_TOOL_SLOTS.estimate_arrival_time,
    "safe",
    ["Giờ đến dự kiến", "Theo dõi chuyến"],
    (args) => stub("tracking_estimate_arrival_time", args),
  );

  register(
    "tracking_get_trip_current_status",
    TRACKING_TOOL_SLOTS.get_trip_current_status,
    "safe",
    ["Trạng thái chuyến", "Vị trí xe"],
    async (args) =>
      normalizeTrackingPayload(
        await getTrackingLive(jsonResult, positiveId, args),
      ),
  );

  register(
    "tracking_get_vehicle_speed",
    TRACKING_TOOL_SLOTS.get_vehicle_speed,
    "safe",
    ["Tốc độ xe", "Theo dõi chuyến"],
    async (args) => {
      const normalized = normalizeTrackingPayload(
        await getTrackingLive(jsonResult, positiveId, args),
      );

      if (!normalized.ok) return normalized;

      return {
        ok: true,
        data: {
          success: true,
          speed: normalized.data.speed,
          last_updated: normalized.data.last_updated,
          raw: normalized.data.raw,
        },
      };
    },
  );

  register(
    "map_map_trip",
    MAP_LOCATION_TOOL_SLOTS.map_trip,
    "safe",
    ["Bản đồ chuyến", "Vị trí xe"],
    (args) => stub("map_map_trip", args),
  );

  register(
    "map_get_live_route_map",
    MAP_LOCATION_TOOL_SLOTS.get_live_route_map,
    "safe",
    ["Bản đồ chuyến", "Theo dõi chuyến"],
    (args) => stub("map_get_live_route_map", args),
  );

  register(
    "map_find_nearby_trips",
    MAP_LOCATION_TOOL_SLOTS.find_nearby_trips,
    "safe",
    ["Chuyến gần đây", "Tìm chuyến"],
    (args) => stub("map_find_nearby_trips", args),
  );

  register(
    "map_find_nearby_pickup_points",
    MAP_LOCATION_TOOL_SLOTS.find_nearby_pickup_points,
    "safe",
    ["Điểm đón gần đây", "Điểm đón"],
    (args) => stub("map_find_nearby_pickup_points", args),
  );

  register(
    "map_get_nearest_station",
    MAP_LOCATION_TOOL_SLOTS.get_nearest_station,
    "safe",
    ["Trạm gần nhất", "Điểm đón"],
    (args) => stub("map_get_nearest_station", args),
  );

  register(
    "map_calculate_distance_to_vehicle",
    MAP_LOCATION_TOOL_SLOTS.calculate_distance_to_vehicle,
    "safe",
    ["Khoảng cách xe", "Vị trí xe"],
    (args) => stub("map_calculate_distance_to_vehicle", args),
  );

  for (const [key, spec] of Object.entries(TRANSPORT_INFO_TOOL_SLOTS)) {
    if (
      key === "get_trip_vehicle" ||
      key === "get_trip_driver" ||
      key === "get_vehicle_info"
    ) {
      registerTripSummaryTransportTool({
        register,
        jsonResult,
        positiveId,
        key,
        spec,
      });
      continue;
    }

    register(`transport_${key}`, spec, "safe", ["Thông tin xe", "Chi tiết chuyến"], (args) =>
      stub(`transport_${key}`, args),
    );
  }

  register(
    "support_create_support_session",
    SUPPORT_TOOL_SLOTS.create_support_session,
    "safe",
    ["Liên hệ hỗ trợ", "Gặp hỗ trợ admin", "Gặp nhà xe", "Liên hệ"],
    async (args) => {
      const built = buildSupportSessionBody(args);
      if (!built.ok) return built;

      return jsonResult("agent/support/sessions", {
        method: "POST",
        auth: "optional",
        ...jsonBody(built.body),
      });
    },
  );

  register(
    "support_send_support_message",
    SUPPORT_TOOL_SLOTS.send_support_message,
    "safe",
    ["Liên hệ hỗ trợ", "Gửi tin hỗ trợ", "Gặp admin"],
    async (args) => {
      const publicId = validatePublicId(args.public_id);
      if (!publicId.ok) return publicId;

      const message = asString(args.message ?? args.body).slice(
        0,
        SUPPORT_MESSAGE_MAX,
      );

      if (!message) {
        return { ok: false, error: "Thiếu nội dung tin nhắn." };
      }

      return jsonResult(
        `agent/support/sessions/${encodeURIComponent(
          publicId.publicId,
        )}/messages`,
        {
          method: "POST",
          auth: "optional",
          ...jsonBody({
            body: message,
            sender_type: "customer",
          }),
        },
      );
    },
  );

  register(
    "support_get_support_messages",
    SUPPORT_TOOL_SLOTS.get_support_messages,
    "safe",
    ["Xem tin hỗ trợ", "Gửi tin hỗ trợ"],
    async (args) => {
      const publicId = validatePublicId(args.public_id);
      if (!publicId.ok) return publicId;

      return jsonResult(
        `agent/support/sessions/${encodeURIComponent(
          publicId.publicId,
        )}/messages`,
        {
          method: "GET",
          auth: "optional",
        },
      );
    },
  );

  register(
    "support_escalate_to_human",
    SUPPORT_TOOL_SLOTS.escalate_to_human,
    "safe",
    ["Gặp admin", "Liên hệ hỗ trợ"],
    (args) => stub("support_escalate_to_human", args),
  );

  register(
    "support_close_support_session",
    SUPPORT_TOOL_SLOTS.close_support_session,
    "safe",
    ["Đóng phiên hỗ trợ"],
    (args) => stub("support_close_support_session", args),
  );
}
