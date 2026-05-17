import {
  extractMaVe,
  extractMaNhaXe,
  extractOperatorNameLoose,
  extractTripId,
} from "../../domain/planner/text-utils.js";
import {
  MAP_LOCATION_TOOL_SLOTS,
  SUPPORT_TOOL_SLOTS,
  TRACKING_TOOL_SLOTS,
  TRANSPORT_INFO_TOOL_SLOTS,
} from "../slots/tracking-map-transport-support.slots.js";

/**
 * Live-support session tool name — colocated with the support handler
 * so other modules (collector, planner-policy) can reference the literal.
 */
export const GR45_LIVE_SUPPORT_SESSION_TOOL_NAME =
  "support_create_support_session";

const SUPPORT_MESSAGE_MAX = 2000;

/** Khớp ý định liên hệ / hỗ trợ — đồng bộ với SUPPORT_CONTACT_INTENT_RE (graph-runtime). */
const SUPPORT_USER_TRIGGER_RE =
  /\b(ho tro|gap admin|gap nha xe|gap nhan vien|tong dai|tu van vien|live support|chat voi nhan vien|chat voi nha xe|noi chuyen voi nguoi|lien he|lien lac|hotline|ket noi nhan vien|goi tong dai)\b/;

export function registerTrackingMapTransportSupportTools(ctx) {
  const { jsonResult, positiveId, register, stub } = ctx;
  const trackingTripId = async (args) => {
    const tripId = String(args.trip_id == null ? "" : args.trip_id).trim();
    if (tripId) return positiveId(tripId, "trip_id");
    const maVe = String(args.ma_ve == null ? "" : args.ma_ve).trim();
    if (!maVe) return positiveId(args.trip_id, "trip_id");
    const listRes = await jsonResult("ve", { method: "GET", auth: "bearer" });
    if (!listRes.ok) return listRes;
    const tickets = Array.isArray(listRes.data?.data?.data)
      ? listRes.data.data.data
      : Array.isArray(listRes.data?.data)
        ? listRes.data.data
        : [];
    const found = tickets.find(
      (ticket) =>
        String(ticket.ma_ve == null ? "" : ticket.ma_ve).toLowerCase() ===
        maVe.toLowerCase(),
    );
    const id = found?.id_chuyen_xe ?? found?.chuyen_xe?.id;
    return id
      ? { ok: true, id: String(id) }
      : { ok: false, error: `Không tìm thấy vé ${maVe} để theo dõi.` };
  };

  register(
    "assistant_transport_guidance",
    SUPPORT_TOOL_SLOTS.clarify_live_target,
    "safe",
    ["Tìm chuyến xe", "Liên hệ hỗ trợ"],
    async (args) => {
      const raw = String(args.raw_message == null ? "" : args.raw_message);
      const n = raw
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/đ/g, "d");
      if (
        /\b(chuyen bay|may bay|tau hoa|duong sat|ve tau|phuong tien cong cong)\b/.test(
          n,
        )
      ) {
        return {
          ok: true,
          data: {
            success: true,
            message:
              "Hiện hệ thống chỉ hỗ trợ xe khách/xe tuyến. Bạn có thể tìm tuyến xe, chuyến xe, ghế, vé hoặc liên hệ hỗ trợ.",
          },
        };
      }
      return {
        ok: true,
        data: {
          success: true,
          message:
            "Thiếu thông tin để chạy tool đầy đủ. Vui lòng cung cấp thêm tuyến, ngày giờ, mã chuyến hoặc mã vé.",
        },
      };
    },
    {
      test: (n) =>
        /\b(chuyen bay|may bay|tau hoa|duong sat|ve tau|phuong tien cong cong|trip abc|xem chuyen abc|dat ve chuyen abc|dat ve ghe|nha xe nao chay tuyen nay|toi muon den|doi chuyen xe)\b/.test(
          n,
        ),
      build: () => ({
        toolName: "assistant_transport_guidance",
        rationale: "Câu thiếu dữ liệu hoặc ngoài phạm vi xe khách.",
        arguments: {},
      }),
    },
  );

  register(
    "tracking_get_live_vehicle_location",
    TRACKING_TOOL_SLOTS.get_live_vehicle_location,
    "safe",
    ["Vị trí xe", "Theo dõi chuyến", "Tốc độ xe"],
    async (args) => {
      const parsed = await trackingTripId(args);
      if (!parsed.ok) return Promise.resolve(parsed);
      return jsonResult(`chuyen-xe/${parsed.id}/tracking/live`, {
        method: "GET",
        auth: "optional",
      });
    },
    {
      test: (n) =>
        /\b(xe .*dang o dau|xe (dang )?o dau|vi tri (xe|chuyen)|tracking|theo doi (xe|chuyen|ve)|xe da toi)\b/.test(
          n,
        ),
      build: (n) => {
        const args = {};
        const id = extractTripId(n);
        if (id) args.trip_id = id;
        const maVe = extractMaVe(n);
        if (maVe) args.ma_ve = maVe;
        return {
          toolName: "tracking_get_live_vehicle_location",
          rationale: "Khách hỏi vị trí xe.",
          arguments: args,
        };
      },
    },
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
    async (args) => {
      const parsed = await trackingTripId(args);
      if (!parsed.ok) return parsed;
      const live = await jsonResult(`chuyen-xe/${parsed.id}/tracking/live`, {
        method: "GET",
        auth: "optional",
      });
      if (!live.ok) return live;
      const summary = await jsonResult(`chuyen-xe/${parsed.id}/tom-tat`, {
        method: "GET",
        auth: "none",
      });
      const trip = summary.ok ? summary.data?.data?.trip : {};
      const route = summary.ok ? summary.data?.data?.route : {};
      return {
        ok: true,
        data: {
          success: true,
          trip_id: parsed.id,
          estimated_arrival_time: route?.gio_ket_thuc ?? null,
          departure_date: trip?.ngay_khoi_hanh ?? null,
          departure_time: trip?.gio_khoi_hanh ?? null,
          live_tracking: live.data?.data ?? live.data,
          note: "Giờ đến dự kiến lấy theo lịch tuyến và vị trí tracking mới nhất.",
        },
      };
    },
    {
      test: (n) =>
        /\b(khi nao\b.{0,30}\b(toi|den)|du kien\b.{0,30}\b(toi|den)|eta\b|gio den)\b/.test(n),
      build: (n) => {
        const args = {};
        const id = extractTripId(n);
        if (id) args.trip_id = id;
        return {
          toolName: "tracking_estimate_arrival_time",
          rationale: "Khách hỏi giờ đến dự kiến.",
          arguments: args,
        };
      },
    },
  );

  register(
    "tracking_get_trip_current_status",
    TRACKING_TOOL_SLOTS.get_trip_current_status,
    "safe",
    ["Trạng thái chuyến", "Vị trí xe"],
    async (args) => {
      const parsed = await trackingTripId(args);
      if (!parsed.ok) return parsed;
      const result = await jsonResult(`chuyen-xe/${parsed.id}/tracking/live`, {
        method: "GET",
        auth: "optional",
      });
      if (!result.ok) return result;
      const payload =
        result.data?.data == null
          ? result.data == null
            ? {}
            : result.data
          : result.data.data;
      return {
        ok: true,
        data: {
          success: true,
          status:
            payload.trang_thai == null
              ? payload.status == null
                ? payload.tinh_trang == null
                  ? null
                  : payload.tinh_trang
                : payload.status
              : payload.trang_thai,
          current_location: {
            lat:
              payload.lat == null
                ? payload.latitude == null
                  ? payload.vi_do == null
                    ? payload.toa_do_x == null
                      ? null
                      : payload.toa_do_x
                    : payload.vi_do
                  : payload.latitude
                : payload.lat,
            lng:
              payload.lng == null
                ? payload.longitude == null
                  ? payload.kinh_do == null
                    ? payload.toa_do_y == null
                      ? null
                      : payload.toa_do_y
                    : payload.kinh_do
                  : payload.longitude
                : payload.lng,
          },
          speed:
            payload.speed == null
              ? payload.toc_do == null
                ? payload.vehicle_speed == null
                  ? null
                  : payload.vehicle_speed
                : payload.toc_do
              : payload.speed,
          last_updated:
            payload.updated_at == null
              ? payload.thoi_diem_ghi == null
                ? payload.timestamp == null
                  ? null
                  : payload.timestamp
                : payload.thoi_diem_ghi
              : payload.updated_at,
          raw: payload,
        },
      };
    },
    {
      test: (n) =>
        /\b(trang thai (hien tai cua )?chuyen|chuyen da chay chua|trip status)\b/.test(n),
      build: (n) => {
        const args = {};
        const id = extractTripId(n);
        if (id) args.trip_id = id;
        const maVe = extractMaVe(n);
        if (maVe) args.ma_ve = maVe;
        return {
          toolName: "tracking_get_trip_current_status",
          rationale: "Khách xem trạng thái chuyến.",
          arguments: args,
        };
      },
    },
  );

  register(
    "tracking_get_vehicle_speed",
    TRACKING_TOOL_SLOTS.get_vehicle_speed,
    "safe",
    ["Tốc độ xe", "Theo dõi chuyến"],
    async (args) => {
      const parsed = await trackingTripId(args);
      if (!parsed.ok) return parsed;
      const result = await jsonResult(`chuyen-xe/${parsed.id}/tracking/live`, {
        method: "GET",
        auth: "optional",
      });
      if (!result.ok) return result;
      const payload =
        result.data?.data == null
          ? result.data == null
            ? {}
            : result.data
          : result.data.data;

      return {
        ok: true,
        data: {
          success: true,
          speed:
            payload.speed == null
              ? payload.toc_do == null
                ? payload.vehicle_speed == null
                  ? null
                  : payload.vehicle_speed
                : payload.toc_do
              : payload.speed,
          last_updated:
            payload.updated_at == null
              ? payload.thoi_diem_ghi == null
                ? payload.timestamp == null
                  ? null
                  : payload.timestamp
                : payload.thoi_diem_ghi
              : payload.updated_at,
          raw: payload,
        },
      };
    },
    {
      test: (n) => /\b(toc do (xe|chuyen)|van toc|speed)\b/.test(n),
      build: (n) => {
        const args = {};
        const id = extractTripId(n);
        if (id) args.trip_id = id;
        const maVe = extractMaVe(n);
        if (maVe) args.ma_ve = maVe;
        return {
          toolName: "tracking_get_vehicle_speed",
          rationale: "Khách hỏi tốc độ xe.",
          arguments: args,
        };
      },
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
    async (args) => {
      const parsed = await trackingTripId(args);
      if (!parsed.ok) return parsed;
      const live = await jsonResult(`chuyen-xe/${parsed.id}/tracking/live`, {
        method: "GET",
        auth: "optional",
      });
      if (!live.ok) return live;
      const summary = await jsonResult(`chuyen-xe/${parsed.id}/tom-tat`, {
        method: "GET",
        auth: "none",
      });
      return {
        ok: true,
        data: {
          success: true,
          trip_id: parsed.id,
          route: summary.ok ? summary.data?.data?.route : null,
          current_location: live.data?.data ?? live.data,
          map_hint: "FE dùng current_location + route để mở bản đồ live.",
        },
      };
    },
    {
      test: (n) => /\b(ban do chuyen|map chuyen|xem ban do)\b/.test(n),
      build: (n) => {
        const args = {};
        const id = extractTripId(n);
        if (id) args.trip_id = id;
        return {
          toolName: "map_get_live_route_map",
          rationale: "Khách xem bản đồ chuyến.",
          arguments: args,
        };
      },
    },
  );

  register(
    "map_find_nearby_trips",
    MAP_LOCATION_TOOL_SLOTS.find_nearby_trips,
    "safe",
    ["Chuyến gần đây", "Tìm chuyến"],
    (args) => stub("map_find_nearby_trips", args),
    {
      test: (n) => /\b(chuyen gan day|chuyen sap chay gan)\b/.test(n),
      build: () => ({
        toolName: "map_find_nearby_trips",
        rationale: "Khách tìm chuyến gần.",
        arguments: {},
      }),
    },
  );

  register(
    "map_find_nearby_pickup_points",
    MAP_LOCATION_TOOL_SLOTS.find_nearby_pickup_points,
    "safe",
    ["Điểm đón gần đây", "Điểm đón"],
    (args) => stub("map_find_nearby_pickup_points", args),
    {
      test: (n) => /\b(diem don gan|gan day.*(diem don|pickup))\b/.test(n),
      build: () => ({
        toolName: "map_find_nearby_pickup_points",
        rationale: "Khách tìm điểm đón gần.",
        arguments: {},
      }),
    },
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
      key === "get_vehicle_info" ||
      key === "get_operator_info"
    ) {
      register(
        `transport_${key}`,
        spec,
        "safe",
        ["Thông tin xe", "Tài xế", "Chi tiết chuyến"],
        (args) => {
          if (args.vehicle_id && !args.trip_id) {
            return Promise.resolve({
              ok: false,
              error:
                "Tool này hiện resolve qua trip_id. Nếu chỉ có vehicle_id cần AgentToolController riêng.",
            });
          }
          const parsed = positiveId(args.trip_id, "trip_id");
          if (!parsed.ok) return Promise.resolve(parsed);
          return jsonResult(`chuyen-xe/${parsed.id}/tom-tat`, {
            method: "GET",
            auth: "none",
          });
        },
        key === "get_operator_info"
          ? {
              test: (n) => /\b(nha xe|operator)\b.*\bchuyen\b/.test(n),
              build: (n) => {
                const args = {};
                const id = extractTripId(n);
                if (id) args.trip_id = id;
                return {
                  toolName: "transport_get_operator_info",
                  rationale: "Khách hỏi nhà xe của chuyến.",
                  arguments: args,
                };
              },
            }
          : key === "get_trip_driver"
          ? {
              test: (n) =>
                /\b(tai xe|lai xe|driver|thong tin xe|bien so xe)\b/.test(n),
              build: (n) => {
                const args = {};
                const id = extractTripId(n);
                if (id) args.trip_id = id;
                return {
                  toolName: "transport_get_trip_driver",
                  rationale: "Khách hỏi thông tin tài xế / xe.",
                  arguments: args,
                };
              },
            }
          : null,
      );
      continue;
    }

    register(
      `transport_${key}`,
      spec,
      "safe",
      ["Thông tin xe", "Chi tiết chuyến"],
      (args) => stub(`transport_${key}`, args),
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
  );

  register(
    "support_create_support_session",
    SUPPORT_TOOL_SLOTS.create_support_session,
    "safe",
    ["Liên hệ hỗ trợ", "Gặp hỗ trợ admin", "Gặp nhà xe", "Liên hệ"],
    async (args, ctx) => {
      const widgetFromCtx =
        ctx?.sessionId && ctx.sessionId?.constructor === String
          ? String(ctx.sessionId).trim().slice(0, 255)
          : "";
      const mergedArgs = { ...args };
      if (
        widgetFromCtx &&
        !String(
          args.chat_widget_session_key == null
            ? ""
            : args.chat_widget_session_key,
        ).trim()
      ) {
        mergedArgs.chat_widget_session_key = widgetFromCtx;
      }

      const targetRaw = String(
        mergedArgs.target == null ? "" : mergedArgs.target,
      )
        .trim()
        .toLowerCase();
      const maNhaXe = String(
        mergedArgs.ma_nha_xe == null ? "" : mergedArgs.ma_nha_xe,
      ).trim();
      const target = targetRaw
        ? ["admin", "nha_xe"].includes(targetRaw)
          ? targetRaw
          : null
        : maNhaXe
          ? "nha_xe"
          : "admin";
      if (!target) {
        return {
          ok: false,
          error: "target không hợp lệ. Chỉ hỗ trợ `admin` hoặc `nha_xe`.",
        };
      }
      if (target === "nha_xe" && !maNhaXe) {
        return {
          ok: false,
          error:
            "Để nhắn nhà xe cần ma_nha_xe. Nếu chưa chọn nhà xe, dùng target=admin.",
        };
      }
      const guestPhone = String(
        mergedArgs.guest_phone == null ? "" : mergedArgs.guest_phone,
      ).trim();
      if (guestPhone && !/^[0-9+()\-\s]{8,24}$/.test(guestPhone)) {
        return { ok: false, error: "SĐT khách không hợp lệ." };
      }
      const tripIdRaw = String(
        mergedArgs.trip_id == null
          ? mergedArgs.id_chuyen_xe == null
            ? ""
            : mergedArgs.id_chuyen_xe
          : mergedArgs.trip_id,
      ).trim();
      const tripId = Number.parseInt(tripIdRaw, 10);

      /** Không gửi initial_message trong POST — Laravel không broadcast tin cho admin cho đến khi widget POST sau invoke. */
      const payload = Object.fromEntries(
        Object.entries({
          target,
          chat_widget_session_key: String(
            mergedArgs.chat_widget_session_key == null
              ? ""
              : mergedArgs.chat_widget_session_key,
          )
            .trim()
            .slice(0, 255),
          ma_nha_xe: maNhaXe,
          id_chuyen_xe:
            Number.isInteger(tripId) && tripId > 0 ? tripId : undefined,
          guest_name: String(
            mergedArgs.guest_name == null ? "" : mergedArgs.guest_name,
          )
            .trim()
            .slice(0, 120),
          guest_phone: guestPhone ? guestPhone.slice(0, 32) : undefined,
          guest_email: String(
            mergedArgs.guest_email == null ? "" : mergedArgs.guest_email,
          )
            .trim()
            .slice(0, 160),
        }).filter(([, value]) => value !== undefined && value !== ""),
      );
      Object.assign(payload, {
        defer_customer_opening_message: true,
      });

      return jsonResult("agent/support/sessions", {
        method: "POST",
        auth: "optional",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
    },
    {
      test: (n) =>
        SUPPORT_USER_TRIGGER_RE.test(n) &&
        !/\b(gui tin nhan ho tro|xem tin nhan ho tro|dong phien ho tro|dong ho tro|ket thuc ho tro|ket thuc lien he ho tro|close support)\b/.test(
          n,
        ),
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
  );

  register(
    "support_send_support_message",
    SUPPORT_TOOL_SLOTS.send_support_message,
    "safe",
    ["Liên hệ hỗ trợ", "Gửi tin hỗ trợ", "Gặp admin"],
    async (args) => {
      const publicId = String(
        args.public_id == null ? "" : args.public_id,
      ).trim();
      if (!publicId) {
        return { ok: false, error: "Thiếu public_id phiên hỗ trợ." };
      }
      if (!/^[a-zA-Z0-9_-]{3,120}$/.test(publicId)) {
        return { ok: false, error: "public_id phiên hỗ trợ không hợp lệ." };
      }

      const message = String(args.message == null ? args.body : args.message)
        .trim()
        .slice(0, SUPPORT_MESSAGE_MAX);

      if (!message) {
        return { ok: false, error: "Thiếu nội dung tin nhắn." };
      }

      return jsonResult(
        `agent/support/sessions/${encodeURIComponent(publicId)}/messages`,
        {
          method: "POST",
          auth: "optional",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            body: message,
            sender_type: "customer",
          }),
        },
      );
    },
    {
      test: (n) =>
        /\b(gui tin nhan ho tro|gui ho tro|tra loi ho tro)\b/.test(n),
      build: (n, _todayIso, rawText) => {
        const text = String(rawText == null ? n : rawText);
        const byLabel = text.match(/\bphien\s+([a-zA-Z0-9_-]{3,120})\b/i);
        const publicId = byLabel ? byLabel[1] : "";
        const message =
          text.split(":").slice(1).join(":").trim() ||
          text
            .replace(/gui tin nhan ho tro/gi, "")
            .replace(/\bphien\s+[a-zA-Z0-9_-]{3,120}\b/gi, "")
            .trim();
        const args = {};
        if (publicId) args.public_id = publicId;
        if (message) args.message = message;
        return {
          toolName: "support_send_support_message",
          rationale: "Khách gửi tin nhắn cho hỗ trợ.",
          arguments: args,
        };
      },
    },
  );

  register(
    "support_get_support_messages",
    SUPPORT_TOOL_SLOTS.get_support_messages,
    "safe",
    ["Xem tin hỗ trợ", "Gửi tin hỗ trợ"],
    async (args) => {
      const publicId = String(
        args.public_id == null ? "" : args.public_id,
      ).trim();
      if (!publicId) {
        return { ok: false, error: "Thiếu public_id phiên hỗ trợ." };
      }
      if (!/^[a-zA-Z0-9_-]{3,120}$/.test(publicId)) {
        return { ok: false, error: "public_id phiên hỗ trợ không hợp lệ." };
      }

      return jsonResult(
        `agent/support/sessions/${encodeURIComponent(publicId)}/messages`,
        {
          method: "GET",
          auth: "optional",
        },
      );
    },
    {
      test: (n) => /\b(xem tin nhan ho tro|lich su ho tro)\b/.test(n),
      build: (n) => {
        const byLabel = String(n).match(/\bphien\s+([a-zA-Z0-9_-]{3,120})\b/i);
        const byCode = String(n).match(/\b([a-zA-Z]{2,}[0-9][a-zA-Z0-9_-]{2,119})\b/);
        const publicId = byLabel ? byLabel[1] : byCode ? byCode[1] : "";
        return {
          toolName: "support_get_support_messages",
          rationale: "Khách xem tin nhắn trong phiên hỗ trợ.",
          arguments: publicId ? { public_id: publicId } : {},
        };
      },
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
    async (args) => {
      const publicId = String(
        args.public_id == null ? "" : args.public_id,
      ).trim();
      if (!publicId) {
        return { ok: false, error: "Thiếu public_id phiên hỗ trợ." };
      }
      if (!/^[a-zA-Z0-9_-]{3,120}$/.test(publicId)) {
        return { ok: false, error: "public_id phiên hỗ trợ không hợp lệ." };
      }
      return jsonResult(
        `agent/support/sessions/${encodeURIComponent(publicId)}/customer-close`,
        {
          method: "POST",
          auth: "optional",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({}),
        },
      );
    },
    {
      test: (n) =>
        /\b(dong phien ho tro|dong ho tro|ket thuc ho tro|ket thuc lien he ho tro|close support)\b/.test(
          n,
        ),
      build: (n) => {
        const byLabel = String(n).match(/\bphien\s+([a-zA-Z0-9_-]{3,120})\b/i);
        const byCode = String(n).match(/\b([a-zA-Z]{2,}[0-9][a-zA-Z0-9_-]{2,119})\b/);
        const publicId = byLabel ? byLabel[1] : byCode ? byCode[1] : "";
        return {
          toolName: "support_close_support_session",
          rationale: "Khách đóng phiên hỗ trợ.",
          arguments: publicId ? { public_id: publicId } : {},
        };
      },
    },
  );
}

/**
 * Collect live-support `public_id`s from tool results so the widget can subscribe.
 * Each row: `{ toolName, ok, data }` with `data` = Laravel `{ success, data: { public_id } }`.
 */
export const collectLiveSupportPublicIdsFromToolResults = (toolResults) => {
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
};

/**
 * Phiên support_create_support_session được tạo với defer_customer_opening_message —
 * widget phải POST tin khách sau khi invoke xong.
 */
export const collectLiveSupportDeferredOpeningFromToolResults = (
  toolResults,
) => {
  const expected = String(GR45_LIVE_SUPPORT_SESSION_TOOL_NAME).trim();
  for (const row of Array.isArray(toolResults) ? toolResults : []) {
    if (!row?.ok || String(row.toolName ?? "").trim() !== expected) {
      continue;
    }
    const body = row.data;
    if (!body || typeof body !== "object") continue;
    const inner = body.data;
    if (
      inner &&
      typeof inner === "object" &&
      inner.deferred_customer_opening_message === true
    ) {
      return true;
    }
    if (body.deferred_customer_opening_message === true) {
      return true;
    }
  }
  return false;
};
