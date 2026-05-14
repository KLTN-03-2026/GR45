import {
  ROUTE_TOOL_SLOTS,
  SEAT_TOOL_SLOTS,
  TRIP_TOOL_SLOTS,
} from "../slots.js";

const FREE_SEAT_STATUSES = new Set([
  "",
  "trong",
  "con_trong",
  "available",
  "empty",
]);

function asString(value) {
  return String(value ?? "").trim();
}

function normalizeText(value) {
  return asString(value)
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d");
}

function pickDefinedQuery(source) {
  return Object.fromEntries(
    Object.entries(source).filter(
      ([, value]) => value !== undefined && value !== null && asString(value) !== "",
    ),
  );
}

function hasAnyTripSearchAnchor(args) {
  const hasRoute = asString(args.diem_di) && asString(args.diem_den);
  const hasOperator = asString(args.nha_xe) || asString(args.ma_nha_xe);
  return Boolean(hasRoute || hasOperator);
}

function buildTripSearchQuery(args) {
  return pickDefinedQuery({
    diem_di: args.diem_di,
    diem_den: args.diem_den,
    ngay_khoi_hanh: args.ngay_khoi_hanh,
    gio_khoi_hanh_tu: args.gio_khoi_hanh,
    gia_ve_tu: args.min_price,
    gia_ve_den: args.max_price,
    nha_xe: args.nha_xe,
    ma_nha_xe: args.ma_nha_xe,
    loai_xe: args.loai_xe,
    tien_ich: Array.isArray(args.tien_ich)
      ? args.tien_ich.join(",")
      : args.tien_ich,
    so_luong_ghe: args.so_luong_ghe,
  });
}

function buildRouteSearchQuery(args) {
  return pickDefinedQuery({
    diem_di: args.diem_di,
    diem_den: args.diem_den,
    nha_xe: args.nha_xe,
    ma_nha_xe: args.ma_nha_xe,
    loai_xe: args.loai_xe,
  });
}

function hasNeedle(row, needle, keys) {
  const n = normalizeText(needle);
  if (!n) return true;

  return keys.some((key) => normalizeText(row?.[key]).includes(n));
}

function filterRoutesClientSide(rows, args) {
  const operator = args.ma_nha_xe ?? args.nha_xe;
  const from = args.diem_di;
  const to = args.diem_den;

  if (!asString(operator) && !asString(from) && !asString(to)) {
    return rows;
  }

  return rows.filter((row) => {
    const item = row ?? {};

    return (
      (!asString(operator) ||
        hasNeedle(item, operator, ["ma_nha_xe", "ten_nha_xe"])) &&
      hasNeedle(item, from, ["diem_bat_dau", "diem_di"]) &&
      hasNeedle(item, to, ["diem_ket_thuc", "diem_den"])
    );
  });
}

function extractList(payload) {
  const data = payload?.data;

  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.data)) return data.data;
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data?.items)) return payload.data.items;
  if (Array.isArray(payload?.items)) return payload.items;

  return [];
}

function isFreeSeat(seat) {
  const status = normalizeText(seat?.tinh_trang ?? seat?.status ?? "");
  return FREE_SEAT_STATUSES.has(status);
}

function publicTripSummary(jsonResult, positiveId, args) {
  const parsed = positiveId(args.trip_id, "trip_id");
  if (!parsed.ok) return Promise.resolve(parsed);

  return jsonResult(`chuyen-xe/${parsed.id}/tom-tat`, {
    method: "GET",
    auth: "none",
  });
}

function publicTripSeats(jsonResult, positiveId, args) {
  const parsed = positiveId(args.trip_id, "trip_id");
  if (!parsed.ok) return Promise.resolve(parsed);

  return jsonResult(`chuyen-xe/${parsed.id}/ghe`, {
    method: "GET",
    auth: "none",
  });
}

function compactTripPriceResult(result) {
  if (!result.ok) return result;

  const payload = result.data?.data ?? result.data ?? {};
  const price =
    payload.gia_ve ??
    payload.gia_ve_co_ban ??
    payload.price ??
    payload.base_price ??
    null;

  return {
    ok: true,
    data: {
      success: true,
      base_price: price,
      final_price: price,
      currency: "VND",
      raw: payload,
    },
  };
}

export function registerRouteTripSeatTools(ctx) {
  const { exeTramDung, jsonResult, positiveId, register, stub, withQuery } = ctx;

  register(
    "search_routes",
    ROUTE_TOOL_SLOTS.search_routes,
    "safe",
    ["Tìm tuyến khác", "Đặt vé", "Chọn nhà xe"],
    async (args) => {
      const query = buildRouteSearchQuery(args);

      const result = await jsonResult(
        withQuery("tuyen-duong/public", query),
        {
          method: "GET",
          auth: "none",
        },
      );

      if (!result.ok) return result;

      const rows = extractList(result.data);
      const filtered = filterRoutesClientSide(rows, args);

      return {
        ok: true,
        data: {
          success: true,
          count: filtered.length,
          data: filtered,
        },
      };
    },
  );

  register(
    "route_get_route_detail",
    ROUTE_TOOL_SLOTS.get_route_detail,
    "safe",
    ["Chi tiết tuyến", "Điểm đón", "Điểm trả"],
    (args) => stub("route_get_route_detail", args),
  );

  register(
    "route_get_pickup_points",
    ROUTE_TOOL_SLOTS.get_pickup_points,
    "safe",
    ["Điểm đón gần đây", "Điểm trả"],
    (args) => exeTramDung(args, "pickup"),
  );

  register(
    "route_get_dropoff_points",
    ROUTE_TOOL_SLOTS.get_dropoff_points,
    "safe",
    ["Điểm trả", "Điểm đón"],
    (args) => exeTramDung(args, "dropoff"),
  );

  register(
    "search_trips",
    TRIP_TOOL_SLOTS.search_trips,
    "safe",
    ["Tìm chuyến khác", "Lọc theo giờ", "Đặt vé", "Chọn ghế", "Chi tiết chuyến"],
    (args) => {
      if (!hasAnyTripSearchAnchor(args)) {
        return Promise.resolve({
          ok: false,
          data: {
            success: false,
            clarification_needed: true,
            missing_slots: ["diem_di", "diem_den"],
            suggested_questions_vi: [
              "Bạn muốn đi từ đâu đến đâu?",
              "Hoặc bạn muốn kiểm tra chuyến của nhà xe nào?",
            ],
            suggested_reply_chips_vi: [
              "Đà Nẵng đi Huế",
              "Huế đi Đà Nẵng",
              "Theo nhà xe",
            ],
          },
          error: null,
        });
      }

      const query = buildTripSearchQuery(args);

      return jsonResult(withQuery("chuyen-xe/search", query), {
        method: "GET",
        auth: "none",
      });
    },
  );

  register(
    "trip_get_trip_detail",
    TRIP_TOOL_SLOTS.get_trip_detail,
    "safe",
    ["Chi tiết chuyến", "Đặt vé", "Chọn ghế"],
    (args) => publicTripSummary(jsonResult, positiveId, args),
  );

  register(
    "trip_get_trip_status",
    TRIP_TOOL_SLOTS.get_trip_status,
    "safe",
    ["Trạng thái chuyến", "Theo dõi chuyến"],
    async (args) => {
      const result = await publicTripSummary(jsonResult, positiveId, args);
      if (!result.ok) return result;

      const payload = result.data?.data ?? result.data ?? {};

      return {
        ok: true,
        data: {
          success: true,
          trip_id: args.trip_id,
          status:
            payload.trang_thai ??
            payload.tinh_trang ??
            payload.status ??
            null,
          raw: payload,
        },
      };
    },
  );

  register(
    "trip_get_trip_schedule",
    TRIP_TOOL_SLOTS.get_trip_schedule,
    "safe",
    ["Lịch chuyến", "Tìm chuyến khác"],
    (args) => stub("trip_get_trip_schedule", args),
  );

  register(
    "trip_get_available_seats",
    TRIP_TOOL_SLOTS.get_available_seats,
    "safe",
    ["Chọn ghế", "Sơ đồ ghế", "Đặt vé"],
    (args) => publicTripSeats(jsonResult, positiveId, args),
  );

  register(
    "trip_get_trip_price",
    TRIP_TOOL_SLOTS.get_trip_price,
    "safe",
    ["Giá vé", "Đặt vé"],
    async (args) => compactTripPriceResult(
      await publicTripSummary(jsonResult, positiveId, args),
    ),
  );

  register(
    "seat_get_seat_map",
    SEAT_TOOL_SLOTS.get_seat_map,
    "safe",
    ["Sơ đồ ghế", "Chọn ghế"],
    (args) => publicTripSeats(jsonResult, positiveId, args),
  );

  register(
    "seat_check_available_seats",
    SEAT_TOOL_SLOTS.check_available_seats,
    "safe",
    ["Ghế trống", "Chọn ghế"],
    async (args) => {
      const result = await publicTripSeats(jsonResult, positiveId, args);
      if (!result.ok) return result;

      const rows = extractList(result.data);
      const free = rows.filter(isFreeSeat);

      return {
        ok: true,
        data: {
          success: true,
          total: rows.length,
          available_count: free.length,
          seats: free,
        },
      };
    },
  );

  register(
    "seat_hold_seat",
    SEAT_TOOL_SLOTS.hold_seat,
    "safe",
    ["Giữ ghế", "Đặt vé"],
    (args) => stub("seat_hold_seat", args),
  );
}