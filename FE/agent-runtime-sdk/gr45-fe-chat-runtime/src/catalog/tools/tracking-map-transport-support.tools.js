import {
  extractMaNhaXe,
  extractOperatorNameLoose,
} from "../../fast-planner/text-utils.js";
import {
  MAP_LOCATION_TOOL_SLOTS,
  SUPPORT_TOOL_SLOTS,
  TRACKING_TOOL_SLOTS,
  TRANSPORT_INFO_TOOL_SLOTS,
} from "../slots.js";

/**
 * Live-support session tool name — colocated with the support handler
 * so other modules (collector, planner-policy) can reference the literal.
 */
export const GR45_LIVE_SUPPORT_SESSION_TOOL_NAME =
  "support_create_support_session";

const SUPPORT_PUBLIC_ID_RE = /^[a-zA-Z0-9_-]{3,120}$/;
const SUPPORT_PHONE_RE = /^[0-9+()\-\s]{8,24}$/;
const SUPPORT_MESSAGE_MAX = 2000;

/** Khớp ý định liên hệ / hỗ trợ — đồng bộ với SUPPORT_CONTACT_INTENT_RE (graph-runtime). */
const SUPPORT_USER_TRIGGER_RE =
  /\b(ho tro|gap admin|gap nha xe|gap nhan vien|tong dai|tu van vien|live support|chat voi nhan vien|chat voi nha xe|noi chuyen voi nguoi|lien he|lien lac|hotline|ket noi nhan vien|goi tong dai)\b/;

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
    "support_clarify_live_target",
    SUPPORT_TOOL_SLOTS.clarify_live_target,
    "safe",
    ["Gặp admin hệ thống", "Gặp nhà xe"],
    async () => ({
      ok: true,
      data: {
        success: false,
        clarification_needed: true,
        missing_slots: ["live_support_target"],
        suggested_questions_vi: [
          "Trong cuộc trò chuyện có nhắc tới nhà xe — bạn muốn được hỗ trợ bởi admin hệ thống BusSafe hay nhà xe đó?",
        ],
        suggested_reply_chips_vi: ["Gặp admin hệ thống", "Gặp nhà xe"],
      },
      error: null,
    }),
  );

  register(
    "support_create_support_session",
    SUPPORT_TOOL_SLOTS.create_support_session,
    "safe",
    ["Liên hệ hỗ trợ", "Gặp hỗ trợ admin", "Gặp nhà xe", "Liên hệ"],
    async (args, ctx) => {
      const widgetFromCtx =
        ctx?.sessionId && typeof ctx.sessionId === "string"
          ? String(ctx.sessionId).trim().slice(0, 255)
          : "";
      const mergedArgs = { ...args };
      if (
        widgetFromCtx &&
        !String(asString(args.chat_widget_session_key)).trim()
      ) {
        mergedArgs.chat_widget_session_key = widgetFromCtx;
      }

      const built = buildSupportSessionBody(mergedArgs);
      if (!built.ok) return built;

      /** Không gửi initial_message trong POST — Laravel không broadcast tin cho admin cho đến khi widget POST sau invoke. */
      const payload = {
        ...built.body,
        defer_customer_opening_message: true,
      };
      delete payload.initial_message;

      return jsonResult("agent/support/sessions", {
        method: "POST",
        auth: "optional",
        ...jsonBody(payload),
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

/**
 * Collect live-support `public_id`s from tool results so the widget can subscribe.
 * Each row: `{ toolName, ok, data }` with `data` = Laravel `{ success, data: { public_id } }`.
 */
export function collectLiveSupportPublicIdsFromToolResults(toolResults) {
  const seen = new Set();
  const out = [];
  const expected = String(GR45_LIVE_SUPPORT_SESSION_TOOL_NAME).trim();
  for (const row of Array.isArray(toolResults) ? toolResults : []) {
    if (!row?.ok || String(row.toolName ?? "").trim() !== expected) {
      continue;
    }
    const body = row.data;
    if (!body || typeof body !== "object") continue;
    const inner = body.data;
    const pid =
      (inner && typeof inner === "object" && inner.public_id) ||
      body.public_id ||
      (typeof inner === "string" ? inner : undefined);
    const s = String(pid ?? "").trim();
    if (s && !seen.has(s)) {
      seen.add(s);
      out.push(s);
    }
  }
  return out;
}

/**
 * Phiên support_create_support_session được tạo với defer_customer_opening_message —
 * widget phải POST tin khách sau khi invoke xong.
 */
export function collectLiveSupportDeferredOpeningFromToolResults(toolResults) {
  const expected = String(GR45_LIVE_SUPPORT_SESSION_TOOL_NAME).trim();
  for (const row of Array.isArray(toolResults) ? toolResults : []) {
    if (!row?.ok || String(row.toolName ?? "").trim() !== expected) {
      continue;
    }
    const body = row.data;
    if (!body || typeof body !== "object") continue;
    const inner = body.data;
    if (inner && typeof inner === "object" && inner.deferred_customer_opening_message === true) {
      return true;
    }
    if (body.deferred_customer_opening_message === true) {
      return true;
    }
  }
  return false;
}

/**
 * Rule-base triggers for tracking / map / transport-info / support.
 * Specific tracking patterns (ETA / speed / status) BEFORE generic live-location.
 */
export const TRACKING_MAP_TRANSPORT_SUPPORT_TOOL_PATTERNS = [
  // tracking_estimate_arrival_time
  {
    test: (n) => /\b(khi nao (toi|den)|du kien (toi|den)|eta|gio den)\b/.test(n),
    build: () => ({
      toolName: "tracking_estimate_arrival_time",
      rationale: "Khách hỏi giờ đến dự kiến.",
      arguments: {},
    }),
  },
  // tracking_get_vehicle_speed
  {
    test: (n) => /\b(toc do (xe|chuyen)|van toc|speed)\b/.test(n),
    build: () => ({
      toolName: "tracking_get_vehicle_speed",
      rationale: "Khách hỏi tốc độ xe.",
      arguments: {},
    }),
  },
  // tracking_get_trip_current_status
  {
    test: (n) =>
      /\b(trang thai chuyen|chuyen da chay chua|trip status)\b/.test(n),
    build: () => ({
      toolName: "tracking_get_trip_current_status",
      rationale: "Khách xem trạng thái chuyến.",
      arguments: {},
    }),
  },
  // tracking_get_live_vehicle_location (catch-all live position)
  {
    test: (n) =>
      /\b(xe (dang )?o dau|vi tri (xe|chuyen)|tracking|theo doi (xe|chuyen)|xe da toi)\b/.test(
        n,
      ),
    build: () => ({
      toolName: "tracking_get_live_vehicle_location",
      rationale: "Khách hỏi vị trí xe.",
      arguments: {},
    }),
  },
  // map_find_nearby_pickup_points
  {
    test: (n) => /\b(diem don gan|gan day.*(diem don|pickup))\b/.test(n),
    build: () => ({
      toolName: "map_find_nearby_pickup_points",
      rationale: "Khách tìm điểm đón gần.",
      arguments: {},
    }),
  },
  // map_find_nearby_trips
  {
    test: (n) => /\b(chuyen gan day|chuyen sap chay gan)\b/.test(n),
    build: () => ({
      toolName: "map_find_nearby_trips",
      rationale: "Khách tìm chuyến gần.",
      arguments: {},
    }),
  },
  // get_trip_driver / vehicle info
  {
    test: (n) => /\b(tai xe|lai xe|driver|thong tin xe|bien so xe)\b/.test(n),
    build: () => ({
      toolName: "get_trip_driver",
      rationale: "Khách hỏi thông tin tài xế / xe.",
      arguments: {},
    }),
  },
  // support_send_support_message
  {
    test: (n) => /\b(gui tin nhan ho tro|gui ho tro|tra loi ho tro)\b/.test(n),
    build: () => ({
      toolName: "support_send_support_message",
      rationale: "Khách gửi tin nhắn cho hỗ trợ.",
      arguments: {},
    }),
  },
  // Khi đã có ngữ cảnh nhà xe (mã NX / tên sau "nhà xe") nhưng khách chưa nói rõ
  // đích — hỏi admin BusSafe hay nhà xe. Đặt TRƯỚC support_create_support_session.
  {
    test: (n, raw) => {
      const r = String(raw ?? "");
      if (!SUPPORT_USER_TRIGGER_RE.test(n)) {
        return false;
      }
      if (
        /\b(gap nha xe|chat voi nha xe|lien he nha xe|noi chuyen voi nha xe)\b/.test(
          n,
        )
      ) {
        return false;
      }
      if (
        /\b(gap admin|chat voi admin|lien he admin|tu van vien|tong dai|gap nhan vien|nhan vien ho tro)\b/.test(
          n,
        )
      ) {
        return false;
      }
      const ma = extractMaNhaXe(n);
      const op = extractOperatorNameLoose(r);
      return Boolean(ma || op);
    },
    build: () => ({
      toolName: "support_clarify_live_target",
      rationale:
        "Khách muốn hỗ trợ nhưng hội thoại có nhắc nhà xe — cần chọn admin hay nhà xe.",
      arguments: {},
    }),
  },
  // support_create_support_session (admin / operator)
  {
    test: (n) => SUPPORT_USER_TRIGGER_RE.test(n),
    build: (n) => {
      const wantsOperator =
        /\b(gap nha xe|chat voi nha xe|lien he nha xe)\b/.test(n);
      const maNhaXe = extractMaNhaXe(n);
      return {
        toolName: GR45_LIVE_SUPPORT_SESSION_TOOL_NAME,
        rationale: wantsOperator
          ? "Khách muốn mở phiên hỗ trợ với nhà xe."
          : "Khách muốn gặp hỗ trợ viên/admin.",
        arguments:
          wantsOperator && maNhaXe
            ? { target: "nha_xe", ma_nha_xe: maNhaXe }
            : { target: "admin" },
      };
    },
  },
];
