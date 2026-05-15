import {
  extractDate,
  extractSeatIds,
  extractTimeFilter,
  extractTripId,
  extractMaNhaXe,
  extractOperatorNameLoose,
  findDirectionedProvinces,
  routeExistenceSearchRoutesArgsFromNorm,
  normalizedTextWantsTripScheduleOrDate,
} from "../../fast-planner/text-utils.js";
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

function hasAnyTripSearchTime(args) {
  return Boolean(
    asString(args.gio_khoi_hanh) ||
      asString(args.gio_khoi_hanh_tu) ||
      asString(args.gio_khoi_hanh_den),
  );
}

function todayIsoInVnTz() {
  // VN = UTC+7. Tránh phụ thuộc Intl trong runtime cũ.
  const nowMs = Date.now() + 7 * 60 * 60 * 1000;
  return new Date(nowMs).toISOString().slice(0, 10);
}

function isPastIsoDate(dateStr) {
  const s = asString(dateStr);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return false;
  return s.localeCompare(todayIsoInVnTz()) < 0;
}

// Ghế format chấp nhận: chữ A–H (sàn xe giường nằm có tối đa H), kèm 1-2 chữ số.
// Z99 / I99 / 1A / ABC — invalid.
const SEAT_FORMAT_REGEX = /^[A-Ha-h](0?[1-9]|[12][0-9]|3[0-2])$/;

function invalidSeatIds(seatIds) {
  if (!Array.isArray(seatIds) || seatIds.length === 0) return [];
  return seatIds
    .map((id) => asString(id))
    .filter((id) => id && !SEAT_FORMAT_REGEX.test(id));
}

function isInvalidTripIdInput(rawValue) {
  if (rawValue === undefined || rawValue === null) return false;
  const s = asString(rawValue);
  if (!s) return false;
  if (/^\d+$/.test(s)) return false;
  return true;
}

function missingTripSearchSlots(args) {
  const missing = [];
  if (!asString(args.diem_di)) missing.push("diem_di");
  if (!asString(args.diem_den)) missing.push("diem_den");
  if (!asString(args.ngay_khoi_hanh)) missing.push("ngay_khoi_hanh");
  if (!hasAnyTripSearchTime(args)) missing.push("gio_khoi_hanh");
  return missing;
}

function buildTripSearchQuery(args) {
  const exactTime = asString(args.gio_khoi_hanh);
  return pickDefinedQuery({
    diem_di: args.diem_di,
    diem_den: args.diem_den,
    ngay_khoi_hanh: args.ngay_khoi_hanh,
    gio_khoi_hanh_tu: args.gio_khoi_hanh_tu ?? exactTime,
    gio_khoi_hanh_den: args.gio_khoi_hanh_den ?? (exactTime || undefined),
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

function asksAboutSpecificOperatorWithoutNamingOne(args) {
  const raw = normalizeText(args.raw_message);
  return (
    /\bnha xe\b/.test(raw) &&
    !asString(args.nha_xe) &&
    !asString(args.ma_nha_xe)
  );
}

function hasNeedle(row, needle, keys) {
  const n = normalizeText(needle);
  if (!n) return true;

  return keys.some((key) => normalizeText(row?.[key]).includes(n));
}

function hasTextNeedle(value, needle) {
  const n = normalizeText(needle);
  if (!n) return true;
  return normalizeText(value).includes(n);
}

function hasStationCue(args) {
  const text = normalizeText(
    [args.raw_message, args.diem_di, args.diem_den].filter(Boolean).join(" "),
  );

  return /\b(tram|diem don|diem tra|ben xe|ben tau|ga|station|pickup|dropoff)\b/.test(
    text,
  );
}

/** BE public tuyến thường embed nhà xe trong `nha_xe: { ma_nha_xe, ten_nha_xe }`. */
function routeRowOperatorHaystack(row) {
  const item = row ?? {};
  const nx = item.nha_xe && typeof item.nha_xe === "object" ? item.nha_xe : {};
  const parts = [
    item.ma_nha_xe,
    item.ten_nha_xe,
    nx.ma_nha_xe,
    nx.ten_nha_xe,
  ].filter((x) => asString(x) !== "");
  return normalizeText(parts.join(" "));
}

function routeMatchesOperator(row, operator) {
  const n = normalizeText(operator);
  if (!n) return true;
  const hay = routeRowOperatorHaystack(row);
  return hay.includes(n);
}

function getTripRoute(row) {
  return row?.tuyen_duong ?? row?.tuyenDuong ?? row?.route ?? {};
}

function filterRoutesClientSide(rows, args) {
  const operator = args.ma_nha_xe ?? args.nha_xe;
  const from = args.diem_di;
  const to = args.diem_den;

  if (!asString(operator) && !asString(from) && !asString(to)) {
    return rows.map(normalizeRouteRow);
  }

  return rows
    .filter((row) => {
      const item = row ?? {};

      return (
        routeMatchesOperator(item, operator) &&
        hasNeedle(item, from, ["diem_bat_dau", "diem_di"]) &&
        hasNeedle(item, to, ["diem_ket_thuc", "diem_den"])
      );
    })
    .map(normalizeRouteRow);
}

// Chuẩn hoá row tuyến đường để synthesizer KHÔNG diễn giải nhầm chiều/giờ.
// BE trả gio/gio_ket_thuc là 1 hướng duy nhất (route = 1 direction). Khoá
// schema rõ ràng để LLM không bịa "chiều đi / chiều về".
function normalizeRouteRow(row) {
  if (!row || typeof row !== "object") return row;
  const diemDi = row.diem_di ?? row.diem_bat_dau ?? null;
  const diemDen = row.diem_den ?? row.diem_ket_thuc ?? null;
  const gioKhoiHanh = row.gio ?? row.gio_khoi_hanh ?? null;
  const gioDenNoi = row.gio_ket_thuc ?? row.gio_den ?? row.gio_den_noi ?? null;
  return {
    ...row,
    route_id: row.id ?? row.route_id ?? null,
    huong: "one_way",
    diem_di: diemDi,
    diem_den: diemDen,
    gio_khoi_hanh: gioKhoiHanh,
    gio_den_noi: gioDenNoi,
    note_for_synthesizer:
      "Đây là MỘT chiều (one_way): diem_di → diem_den. gio_khoi_hanh là giờ bắt đầu chiều này. gio_den_noi là giờ đến cuối chiều này. KHÔNG được hiểu thành 'chiều về'.",
  };
}

function filterTripsClientSide(rows, args) {
  const from = args.diem_di;
  const to = args.diem_den;

  if (hasStationCue(args) || (!asString(from) && !asString(to))) {
    return rows;
  }

  return rows.filter((row) => {
    const route = getTripRoute(row);
    const routeFrom =
      route?.diem_bat_dau ??
      route?.diem_di ??
      row?.diem_bat_dau ??
      row?.diem_di;
    const routeTo =
      route?.diem_ket_thuc ??
      route?.diem_den ??
      row?.diem_ket_thuc ??
      row?.diem_den;

    if (!asString(routeFrom) && !asString(routeTo)) {
      return true;
    }

    return hasTextNeedle(routeFrom, from) && hasTextNeedle(routeTo, to);
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
    ["Tìm tuyến khác", "Đặt vé", "Liên hệ hỗ trợ"],
    async (args) => {
      const maNhaXe = extractMaNhaXe(normalizeText(args.raw_message));
      if (!asString(args.ma_nha_xe) && maNhaXe) {
        args.ma_nha_xe = maNhaXe;
      }
      if (!asString(args.nha_xe) && !asString(args.ma_nha_xe)) {
        const opName = extractOperatorNameLoose(args.raw_message);
        if (opName) args.nha_xe = opName;
      }

      if (asksAboutSpecificOperatorWithoutNamingOne(args)) {
        return {
          ok: false,
          data: {
            success: false,
            clarification_needed: true,
            missing_slots: ["ma_nha_xe"],
            suggested_questions_vi: ["Bạn muốn kiểm tra tuyến của nhà xe nào?"],
            suggested_reply_chips_vi: ["Tìm tất cả tuyến", "Liên hệ hỗ trợ"],
          },
          error: null,
        };
      }

      // Bước 1: gọi BE với query đầy đủ (kèm nha_xe / ma_nha_xe).
      // BE filter `nha_xe` thường yêu cầu match exact ten_nha_xe → khi user
      // nhập tên nhà xe tự nhiên ("Nha xe Thanh Truc Test") BE có thể trả 0.
      // Bước 2 fallback: nếu có diem_di/diem_den nhưng kết quả rỗng và đang
      // filter theo nha_xe tự nhiên (không có ma_nha_xe), gọi lại BE không
      // truyền nha_xe, rồi client-side substring filter theo ten_nha_xe.
      const query = buildRouteSearchQuery(args);
      const result = await jsonResult(
        withQuery("tuyen-duong/public", query),
        { method: "GET", auth: "none" },
      );
      if (!result.ok) return result;

      let rows = extractList(result.data);
      let filtered = filterRoutesClientSide(rows, args);

      const needsFallback =
        filtered.length === 0 &&
        asString(args.nha_xe) &&
        !asString(args.ma_nha_xe) &&
        (asString(args.diem_di) || asString(args.diem_den));

      let usedNameFallback = false;
      if (needsFallback) {
        const broadQuery = buildRouteSearchQuery({
          ...args,
          nha_xe: undefined,
          ma_nha_xe: undefined,
        });
        const broadResult = await jsonResult(
          withQuery("tuyen-duong/public", broadQuery),
          { method: "GET", auth: "none" },
        );
        if (broadResult.ok) {
          rows = extractList(broadResult.data);
          filtered = filterRoutesClientSide(rows, args);
          usedNameFallback = true;
        }
      }

      return {
        ok: true,
        data: {
          success: true,
          count: filtered.length,
          data: filtered,
          used_name_fallback: usedNameFallback || undefined,
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
    async (args) => {
      const missingSlots = missingTripSearchSlots(args);
      if (missingSlots.length > 0) {
        const questionBySlot = {
          diem_di: "Bạn muốn đi từ đâu?",
          diem_den: "Bạn muốn đến đâu?",
          ngay_khoi_hanh: "Bạn muốn đi ngày nào?",
          gio_khoi_hanh: "Bạn muốn đi giờ nào hoặc trong khung giờ nào?",
        };
        return {
          ok: false,
          data: {
            success: false,
            clarification_needed: true,
            missing_slots: missingSlots,
            suggested_questions_vi: missingSlots.map(
              (slot) => questionBySlot[slot],
            ),
            suggested_reply_chips_vi: [
              "Tìm chuyến khác",
              "Tìm tuyến khác",
              "Liên hệ hỗ trợ",
            ],
          },
          error: null,
        };
      }

      if (isPastIsoDate(args.ngay_khoi_hanh)) {
        const today = todayIsoInVnTz();
        return {
          ok: false,
          data: {
            success: false,
            clarification_needed: true,
            past_date: args.ngay_khoi_hanh,
            today_iso: today,
            missing_slots: ["ngay_khoi_hanh"],
            suggested_questions_vi: [
              `Ngày ${args.ngay_khoi_hanh} đã qua, bạn muốn đi ngày nào kể từ hôm nay (${today})?`,
            ],
            suggested_reply_chips_vi: ["Hôm nay", "Ngày mai", "Cuối tuần"],
          },
          error: null,
        };
      }

      const query = buildTripSearchQuery(args);

      const result = await jsonResult(withQuery("chuyen-xe/search", query), {
        method: "GET",
        auth: "none",
      });

      if (!result.ok) return result;

      const rows = extractList(result.data);
      const filtered = filterTripsClientSide(rows, args);

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
      if (isInvalidTripIdInput(args.trip_id)) {
        return {
          ok: false,
          data: {
            success: false,
            invalid_trip_id: asString(args.trip_id),
            error: `Mã chuyến "${asString(args.trip_id)}" không hợp lệ. Mã chuyến phải là dãy số nguyên dương.`,
          },
          error: `Invalid trip_id format: ${asString(args.trip_id)}`,
        };
      }
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
      const badSeats = invalidSeatIds(args.seat_ids);
      if (badSeats.length > 0) {
        return {
          ok: false,
          data: {
            success: false,
            invalid_seat: badSeats,
            error: `Mã ghế ${badSeats.join(", ")} không hợp lệ. Mã ghế phải có dạng A01–H32 (chữ A–H, kèm số 1–32).`,
          },
          error: `Invalid seat_ids: ${badSeats.join(",")}`,
        };
      }
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

/**
 * Rule-base triggers for route / trip / seat.
 * Specific patterns (seat/trip-detail/price) BEFORE generic search.
 * search_routes (route existence) BEFORE search_trips (broad fallback).
 */
export const ROUTE_TRIP_SEAT_TOOL_PATTERNS = [
  // seat_get_seat_map — "sơ đồ ghế chuyến 12"
  {
    test: (n) => /\b(so do ghe|seat map)\b/.test(n),
    build: (n) => {
      const args = {};
      const id = extractTripId(n);
      if (id) args.trip_id = id;
      return {
        toolName: "seat_get_seat_map",
        rationale: "Khách xem sơ đồ ghế.",
        arguments: args,
      };
    },
  },
  // seat_check_available_seats — "ghế trống", "còn ghế nào"
  {
    test: (n) =>
      /\b(con ghe|ghe trong|con bao nhieu ghe|available seats)\b/.test(n),
    build: (n) => {
      const args = {};
      const id = extractTripId(n);
      if (id) args.trip_id = id;
      return {
        toolName: "seat_check_available_seats",
        rationale: "Khách hỏi ghế trống.",
        arguments: args,
      };
    },
  },
  // seat_hold_seat
  {
    test: (n) => /\b(giu ghe|hold seat)\b/.test(n),
    build: (n) => {
      const args = {};
      const tripId = extractTripId(n);
      if (tripId) args.trip_id = tripId;
      const seats = extractSeatIds(n);
      if (seats.length) args.seat_ids = seats;
      return {
        toolName: "seat_hold_seat",
        rationale: "Khách giữ ghế.",
        arguments: args,
      };
    },
  },
  // trip_get_trip_price
  {
    test: (n) => /\b(gia ve|bao nhieu tien|gia chuyen|gia tien)\b/.test(n),
    build: (n) => {
      const args = {};
      const id = extractTripId(n);
      if (id) args.trip_id = id;
      return {
        toolName: "trip_get_trip_price",
        rationale: "Khách hỏi giá vé.",
        arguments: args,
      };
    },
  },
  // trip_get_trip_detail — "chi tiết chuyến 12"
  {
    test: (n) =>
      /\b(chi tiet chuyen|thong tin chuyen)\b.*\b(\d+)\b/.test(n) ||
      /\b(chuyen so|chuyen id|chuyen #)\s*\d+/.test(n),
    build: (n) => {
      const args = {};
      const id = extractTripId(n);
      if (id) args.trip_id = id;
      return {
        toolName: "trip_get_trip_detail",
        rationale: "Khách xem chi tiết chuyến.",
        arguments: args,
      };
    },
  },
  // route_get_pickup_points
  {
    test: (n) => /\b(diem don|tram don|pickup|noi don)\b/.test(n),
    build: () => ({
      toolName: "route_get_pickup_points",
      rationale: "Khách xem điểm đón.",
      arguments: {},
    }),
  },
  // route_get_dropoff_points
  {
    test: (n) => /\b(diem tra|tram tra|dropoff|noi tra)\b/.test(n),
    build: () => ({
      toolName: "route_get_dropoff_points",
      rationale: "Khách xem điểm trả.",
      arguments: {},
    }),
  },
  // search_routes — route existence between two points (no date/time)
  {
    test: (n) =>
      /\b(nha xe|operator|operated by)\b/.test(n) &&
      Boolean(extractMaNhaXe(n)) &&
      routeExistenceSearchRoutesArgsFromNorm(n) === null &&
      !/\b(chuyen|trip)\b/.test(n) &&
      !/\b(ngay|date)\b/.test(n),
    build: (n) => ({
      toolName: "search_routes",
      rationale: "Khách hỏi tuyến đang khai thác theo nhà xe.",
      arguments: { ma_nha_xe: extractMaNhaXe(n) },
    }),
  },
  // search_routes — route existence between two points (no date/time)
  {
    test: (n) => routeExistenceSearchRoutesArgsFromNorm(n) !== null,
    build: (n, _todayIso, rawText) => {
      const args = routeExistenceSearchRoutesArgsFromNorm(n) ?? {};
      const maNhaXe = extractMaNhaXe(n);
      if (maNhaXe) args.ma_nha_xe = maNhaXe;
      if (!args.nha_xe && !args.ma_nha_xe) {
        const opName = extractOperatorNameLoose(rawText ?? n);
        if (opName) args.nha_xe = opName;
      }
      return {
        toolName: "search_routes",
        rationale:
          "Khách hỏi có tuyến giữa hai điểm — tra danh mục tuyến (không cần ngày).",
        arguments: args,
      };
    },
  },
  // search_trips — broad fallback for trip queries
  {
    test: (n) => {
      if (
        routeExistenceSearchRoutesArgsFromNorm(n) !== null &&
        !normalizedTextWantsTripScheduleOrDate(n)
      ) {
        return false;
      }

      const broadOpener =
        /\b(tim|kiem|co|xem)\b.*\b(chuyen|xe|tuyen|lich)\b/.test(n);

      const restDirection =
        /\b(chuyen|xe|tuyen)\b.*\b(tu|di tu)\b.*\b(den|toi|ve)\b/.test(n) ||
        /\b(di tu|tu)\s+\w+.*\b(den|toi|ve)\b/.test(n) ||
        /\b(den|toi|ve)\s+\w+.*\b(tu|di tu)\b/.test(n) ||
        /\btoi muon di\b/.test(n) ||
        /\b(find|need)\b.*\b(bus|buses|coach|trip)\b/.test(n) ||
        /\b(bus|buses|coach|trip)\b.*\bfrom\b.*\bto\b/.test(n) ||
        /\bi want to travel\b/.test(n);

      return Boolean(broadOpener || restDirection);
    },
    build: (n, todayIso, rawText) => {
      const [diemDi, diemDen] = findDirectionedProvinces(rawText ?? n);
      const args = {};
      if (diemDi) args.diem_di = diemDi;
      if (diemDen) args.diem_den = diemDen;
      const date = extractDate(n, todayIso);
      if (date) args.ngay_khoi_hanh = date;
      const maNhaXe = extractMaNhaXe(n);
      if (maNhaXe) args.ma_nha_xe = maNhaXe;
      if (!args.nha_xe && !args.ma_nha_xe) {
        const opName = extractOperatorNameLoose(rawText ?? n);
        if (opName) args.nha_xe = opName;
      }
      Object.assign(args, extractTimeFilter(n));
      return {
        toolName: "search_trips",
        rationale: "Khách tìm chuyến xe.",
        arguments: args,
      };
    },
  },
];
