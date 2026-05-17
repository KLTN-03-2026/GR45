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
} from "../../domain/planner/text-utils.js";
import {
  ROUTE_TOOL_SLOTS,
  SEAT_TOOL_SLOTS,
  TRIP_TOOL_SLOTS,
} from "../slots/route-trip-seat.slots.js";

export function registerRouteTripSeatTools(ctx) {
  const { exeTramDung, jsonResult, positiveId, register, stub, withQuery } =
    ctx;

  register(
    "search_routes",
    ROUTE_TOOL_SLOTS.search_routes,
    "safe",
    ["Tìm tuyến khác", "Đặt vé", "Liên hệ hỗ trợ"],
    async (args) => {
      const rawMessageNormalized = String(
        args.raw_message == null ? "" : args.raw_message,
      )
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/đ/g, "d");
      const maNhaXe = extractMaNhaXe(rawMessageNormalized);
      if (
        !String(args.ma_nha_xe == null ? "" : args.ma_nha_xe).trim() &&
        maNhaXe
      ) {
        args.ma_nha_xe = maNhaXe;
      }
      if (
        !String(args.nha_xe == null ? "" : args.nha_xe).trim() &&
        !String(args.ma_nha_xe == null ? "" : args.ma_nha_xe).trim()
      ) {
        const opName = extractOperatorNameLoose(args.raw_message);
        if (opName) args.nha_xe = opName;
      }

      if (
        /\bnha xe\b/.test(rawMessageNormalized) &&
        !String(args.nha_xe == null ? "" : args.nha_xe).trim() &&
        !String(args.ma_nha_xe == null ? "" : args.ma_nha_xe).trim()
      ) {
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
      const query = Object.fromEntries(
        Object.entries({
          diem_di: args.diem_di,
          diem_den: args.diem_den,
          nha_xe: args.nha_xe,
          ma_nha_xe: args.ma_nha_xe,
          loai_xe: args.loai_xe,
        }).filter(
          ([, value]) =>
            value !== undefined &&
            value !== null &&
            String(value).trim() !== "",
        ),
      );
      const result = await jsonResult(withQuery("tuyen-duong/public", query), {
        method: "GET",
        auth: "none",
      });
      if (!result.ok) return result;

      const routeOperator =
        args.ma_nha_xe == null ? args.nha_xe : args.ma_nha_xe;
      const routeOperatorNormalized = String(
        routeOperator == null ? "" : routeOperator,
      )
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/đ/g, "d");
      const routeFromNormalized = String(
        args.diem_di == null ? "" : args.diem_di,
      )
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/đ/g, "d");
      const routeToNormalized = String(
        args.diem_den == null ? "" : args.diem_den,
      )
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/đ/g, "d");
      let rows = Array.isArray(result.data?.data)
        ? result.data.data
        : Array.isArray(result.data?.data?.data)
          ? result.data.data.data
          : Array.isArray(result.data)
            ? result.data
            : Array.isArray(result.data?.data?.items)
              ? result.data.data.items
              : Array.isArray(result.data?.items)
                ? result.data.items
                : [];
      let filtered = rows
        .filter((row) => {
          if (
            !routeOperatorNormalized &&
            !routeFromNormalized &&
            !routeToNormalized
          ) {
            return true;
          }
          const item = row == null ? {} : row;
          const nhaXe = item.nha_xe?.constructor === Object ? item.nha_xe : {};
          const operatorHaystack = [
            item.ma_nha_xe,
            item.ten_nha_xe,
            nhaXe.ma_nha_xe,
            nhaXe.ten_nha_xe,
          ]
            .filter((value) => String(value == null ? "" : value).trim() !== "")
            .join(" ")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/đ/g, "d");
          const fromHaystack = [item.diem_bat_dau, item.diem_di]
            .join(" ")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/đ/g, "d");
          const toHaystack = [item.diem_ket_thuc, item.diem_den]
            .join(" ")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/đ/g, "d");
          return (
            (!routeOperatorNormalized ||
              operatorHaystack.includes(routeOperatorNormalized)) &&
            (!routeFromNormalized ||
              fromHaystack.includes(routeFromNormalized)) &&
            (!routeToNormalized || toHaystack.includes(routeToNormalized))
          );
        })
        .map((row) => {
          if (!row || row.constructor !== Object) return row;
          const diemDi =
            row.diem_di == null ? (row.diem_bat_dau ?? null) : row.diem_di;
          const diemDen =
            row.diem_den == null ? (row.diem_ket_thuc ?? null) : row.diem_den;
          const gioKhoiHanh =
            row.gio == null ? (row.gio_khoi_hanh ?? null) : row.gio;
          const gioDenNoi =
            row.gio_ket_thuc == null
              ? row.gio_den == null
                ? row.gio_den_noi == null
                  ? null
                  : row.gio_den_noi
                : row.gio_den
              : row.gio_ket_thuc;
          return {
            ...row,
            route_id: row.id == null ? (row.route_id ?? null) : row.id,
            huong: "one_way",
            diem_di: diemDi,
            diem_den: diemDen,
            gio_khoi_hanh: gioKhoiHanh,
            gio_den_noi: gioDenNoi,
            note_for_synthesizer:
              "Đây là MỘT chiều (one_way): diem_di → diem_den. gio_khoi_hanh là giờ bắt đầu chiều này. gio_den_noi là giờ đến cuối chiều này. KHÔNG được hiểu thành 'chiều về'.",
          };
        });

      const needsFallback =
        filtered.length === 0 &&
        String(args.nha_xe == null ? "" : args.nha_xe).trim() &&
        !String(args.ma_nha_xe == null ? "" : args.ma_nha_xe).trim() &&
        (String(args.diem_di == null ? "" : args.diem_di).trim() ||
          String(args.diem_den == null ? "" : args.diem_den).trim());

      let usedNameFallback = false;
      if (needsFallback) {
        const broadQuery = Object.fromEntries(
          Object.entries({
            diem_di: args.diem_di,
            diem_den: args.diem_den,
            loai_xe: args.loai_xe,
          }).filter(
            ([, value]) =>
              value !== undefined &&
              value !== null &&
              String(value).trim() !== "",
          ),
        );
        const broadResult = await jsonResult(
          withQuery("tuyen-duong/public", broadQuery),
          { method: "GET", auth: "none" },
        );
        if (broadResult.ok) {
          rows = Array.isArray(broadResult.data?.data)
            ? broadResult.data.data
            : Array.isArray(broadResult.data?.data?.data)
              ? broadResult.data.data.data
              : Array.isArray(broadResult.data)
                ? broadResult.data
                : Array.isArray(broadResult.data?.data?.items)
                  ? broadResult.data.data.items
                  : Array.isArray(broadResult.data?.items)
                    ? broadResult.data.items
                    : [];
          filtered = rows
            .filter((row) => {
              const item = row == null ? {} : row;
              const nhaXe =
                item.nha_xe?.constructor === Object ? item.nha_xe : {};
              const operatorHaystack = [
                item.ma_nha_xe,
                item.ten_nha_xe,
                nhaXe.ma_nha_xe,
                nhaXe.ten_nha_xe,
              ]
                .filter(
                  (value) => String(value == null ? "" : value).trim() !== "",
                )
                .join(" ")
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/đ/g, "d");
              const fromHaystack = [item.diem_bat_dau, item.diem_di]
                .join(" ")
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/đ/g, "d");
              const toHaystack = [item.diem_ket_thuc, item.diem_den]
                .join(" ")
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/đ/g, "d");
              return (
                (!routeOperatorNormalized ||
                  operatorHaystack.includes(routeOperatorNormalized)) &&
                (!routeFromNormalized ||
                  fromHaystack.includes(routeFromNormalized)) &&
                (!routeToNormalized || toHaystack.includes(routeToNormalized))
              );
            })
            .map((row) => {
              if (!row || row.constructor !== Object) return row;
              const diemDi =
                row.diem_di == null ? (row.diem_bat_dau ?? null) : row.diem_di;
              const diemDen =
                row.diem_den == null
                  ? (row.diem_ket_thuc ?? null)
                  : row.diem_den;
              const gioKhoiHanh =
                row.gio == null ? (row.gio_khoi_hanh ?? null) : row.gio;
              const gioDenNoi =
                row.gio_ket_thuc == null
                  ? row.gio_den == null
                    ? row.gio_den_noi == null
                      ? null
                      : row.gio_den_noi
                    : row.gio_den
                  : row.gio_ket_thuc;
              return {
                ...row,
                route_id: row.id == null ? (row.route_id ?? null) : row.id,
                huong: "one_way",
                diem_di: diemDi,
                diem_den: diemDen,
                gio_khoi_hanh: gioKhoiHanh,
                gio_den_noi: gioDenNoi,
                note_for_synthesizer:
                  "Đây là MỘT chiều (one_way): diem_di → diem_den. gio_khoi_hanh là giờ bắt đầu chiều này. gio_den_noi là giờ đến cuối chiều này. KHÔNG được hiểu thành 'chiều về'.",
              };
            });
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
    [
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
        suggestions: (result) => {
          if (result?.ok !== true) return [];
          return [
            { text: "Tìm chuyến xe", action: "", params: {} },
            { text: "Liên hệ hỗ trợ", action: "", params: {} },
          ];
        },
      },
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
    ],
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
    {
      test: (n) => /\b(diem don|tram don|pickup|noi don)\b/.test(n),
      build: (n) => {
        const args = {};
        const id = extractTripId(n);
        if (id) args.trip_id = id;
        return {
          toolName: "route_get_pickup_points",
          rationale: "Khách xem điểm đón.",
          arguments: args,
        };
      },
    },
  );

  register(
    "route_get_dropoff_points",
    ROUTE_TOOL_SLOTS.get_dropoff_points,
    "safe",
    ["Điểm trả", "Điểm đón"],
    (args) => exeTramDung(args, "dropoff"),
    {
      test: (n) => /\b(diem tra|tram tra|dropoff|noi tra)\b/.test(n),
      build: (n) => {
        const args = {};
        const id = extractTripId(n);
        if (id) args.trip_id = id;
        return {
          toolName: "route_get_dropoff_points",
          rationale: "Khách xem điểm trả.",
          arguments: args,
        };
      },
    },
  );

  register(
    "search_trips",
    TRIP_TOOL_SLOTS.search_trips,
    "safe",
    [
      "Tìm chuyến khác",
      "Lọc theo giờ",
      "Đặt vé",
      "Chọn ghế",
      "Chi tiết chuyến",
    ],
    async (args) => {
      const missingSlots = [];
      if (!String(args.diem_di == null ? "" : args.diem_di).trim()) {
        missingSlots.push("diem_di");
      }
      if (!String(args.diem_den == null ? "" : args.diem_den).trim()) {
        missingSlots.push("diem_den");
      }
      if (
        !String(args.ngay_khoi_hanh == null ? "" : args.ngay_khoi_hanh).trim()
      ) {
        missingSlots.push("ngay_khoi_hanh");
      }
      if (
        !String(args.gio_khoi_hanh == null ? "" : args.gio_khoi_hanh).trim() &&
        !String(
          args.gio_khoi_hanh_tu == null ? "" : args.gio_khoi_hanh_tu,
        ).trim() &&
        !String(
          args.gio_khoi_hanh_den == null ? "" : args.gio_khoi_hanh_den,
        ).trim()
      ) {
        missingSlots.push("gio_khoi_hanh");
      }
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

      const dateText = String(
        args.ngay_khoi_hanh == null ? "" : args.ngay_khoi_hanh,
      ).trim();
      const today = new Date(Date.now() + 7 * 60 * 60 * 1000)
        .toISOString()
        .slice(0, 10);
      if (
        /^\d{4}-\d{2}-\d{2}$/.test(dateText) &&
        dateText.localeCompare(today) < 0
      ) {
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

      const exactTime = String(
        args.gio_khoi_hanh == null ? "" : args.gio_khoi_hanh,
      ).trim();
      const query = Object.fromEntries(
        Object.entries({
          diem_di: args.diem_di,
          diem_den: args.diem_den,
          ngay_khoi_hanh: args.ngay_khoi_hanh,
          gio_khoi_hanh_tu:
            args.gio_khoi_hanh_tu == null ? exactTime : args.gio_khoi_hanh_tu,
          gio_khoi_hanh_den:
            args.gio_khoi_hanh_den == null
              ? exactTime || undefined
              : args.gio_khoi_hanh_den,
          gia_ve_tu: args.min_price,
          gia_ve_den: args.max_price,
          nha_xe: args.nha_xe,
          ma_nha_xe: args.ma_nha_xe,
          loai_xe: args.loai_xe,
          tien_ich: Array.isArray(args.tien_ich)
            ? args.tien_ich.join(",")
            : args.tien_ich,
          so_luong_ghe: args.so_luong_ghe,
        }).filter(
          ([, value]) =>
            value !== undefined &&
            value !== null &&
            String(value).trim() !== "",
        ),
      );

      const result = await jsonResult(withQuery("chuyen-xe/search", query), {
        method: "GET",
        auth: "none",
      });

      if (!result.ok) return result;

      const seatPayload = result.data?.data;
      const rows = Array.isArray(seatPayload?.so_do_ghe)
        ? seatPayload.so_do_ghe
        : Array.isArray(seatPayload)
          ? seatPayload
          : Array.isArray(seatPayload?.data)
            ? seatPayload.data
            : Array.isArray(result.data)
              ? result.data
              : Array.isArray(seatPayload?.items)
                ? seatPayload.items
                : Array.isArray(result.data?.items)
                  ? result.data.items
                  : [];
      const stationCueText = [args.raw_message, args.diem_di, args.diem_den]
        .filter(Boolean)
        .join(" ")
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/đ/g, "d");
      const tripFromNormalized = String(
        args.diem_di == null ? "" : args.diem_di,
      )
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/đ/g, "d");
      const tripToNormalized = String(
        args.diem_den == null ? "" : args.diem_den,
      )
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/đ/g, "d");
      const filtered =
        /\b(tram|diem don|diem tra|ben xe|ben tau|ga|station|pickup|dropoff)\b/.test(
          stationCueText,
        ) ||
        (!tripFromNormalized && !tripToNormalized)
          ? rows
          : rows.filter((row) => {
              const route =
                row?.tuyen_duong == null
                  ? row?.tuyenDuong == null
                    ? row?.route == null
                      ? {}
                      : row.route
                    : row.tuyenDuong
                  : row.tuyen_duong;
              const routeFrom =
                route?.diem_bat_dau == null
                  ? route?.diem_di == null
                    ? row?.diem_bat_dau == null
                      ? row?.diem_di
                      : row.diem_bat_dau
                    : route.diem_di
                  : route.diem_bat_dau;
              const routeTo =
                route?.diem_ket_thuc == null
                  ? route?.diem_den == null
                    ? row?.diem_ket_thuc == null
                      ? row?.diem_den
                      : row.diem_ket_thuc
                    : route.diem_den
                  : route.diem_ket_thuc;
              const routeFromNormalized = String(
                routeFrom == null ? "" : routeFrom,
              )
                .trim()
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/đ/g, "d");
              const routeToNormalized = String(routeTo == null ? "" : routeTo)
                .trim()
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/đ/g, "d");
              if (!routeFromNormalized && !routeToNormalized) return true;
              return (
                (!tripFromNormalized ||
                  routeFromNormalized.includes(tripFromNormalized)) &&
                (!tripToNormalized ||
                  routeToNormalized.includes(tripToNormalized))
              );
            });

      return {
        ok: true,
        data: {
          success: true,
          count: filtered.length,
          data: filtered,
        },
      };
    },
    {
      test: (n) => {
        if (/\b(chuyen bay|may bay|tau hoa|duong sat|ve tau)\b/.test(n)) {
          return false;
        }
        if (/\b(voucher|ma giam gia|khuyen mai)\b/.test(n)) {
          return false;
        }
        if (
          /\b(chi tiet chuyen|thong tin chuyen|so do ghe|kiem tra ghe|ghe .*con trong|diem don|diem tra)\b/.test(
            n,
          )
        ) {
          return false;
        }

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
      suggestions: (result) => {
        if (result?.ok !== true) return [];
        const inner = result?.data?.data;
        const trips = Array.isArray(inner) ? inner : [];
        if (trips.length === 0) return [];
        const chips = [];
        for (const trip of trips.slice(0, 2)) {
          const id = trip?.id ?? trip?.id_chuyen_xe;
          const label = trip?.ten_tuyen ?? trip?.diem_di ?? "Đặt vé";
          if (id != null && String(id).trim() !== "") {
            chips.push({
              text: `Đặt vé — ${label}`.slice(0, 40),
              action: "open_booking",
              params: { id_chuyen_xe: String(id).trim() },
            });
          }
        }
        chips.push({ text: "Tìm chuyến khác", action: "", params: {} });
        return chips;
      },
    },
  );

  register(
    "trip_get_trip_detail",
    TRIP_TOOL_SLOTS.get_trip_detail,
    "safe",
    ["Chi tiết chuyến", "Đặt vé", "Chọn ghế"],
    (args) => {
      const parsed = positiveId(args.trip_id, "trip_id");
      if (!parsed.ok) return Promise.resolve(parsed);
      return jsonResult(`chuyen-xe/${parsed.id}/tom-tat`, {
        method: "GET",
        auth: "none",
      });
    },
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
  );

  register(
    "trip_get_trip_status",
    TRIP_TOOL_SLOTS.get_trip_status,
    "safe",
    ["Trạng thái chuyến", "Theo dõi chuyến"],
    async (args) => {
      const tripIdText = String(
        args.trip_id == null ? "" : args.trip_id,
      ).trim();
      if (tripIdText && !/^\d+$/.test(tripIdText)) {
        return {
          ok: false,
          data: {
            success: false,
            invalid_trip_id: tripIdText,
            error: `Mã chuyến "${tripIdText}" không hợp lệ. Mã chuyến phải là dãy số nguyên dương.`,
          },
          error: `Invalid trip_id format: ${tripIdText}`,
        };
      }
      const parsed = positiveId(args.trip_id, "trip_id");
      if (!parsed.ok) return parsed;
      const result = await jsonResult(`chuyen-xe/${parsed.id}/tom-tat`, {
        method: "GET",
        auth: "none",
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
          trip_id: args.trip_id,
          status:
            payload.trang_thai == null
              ? payload.tinh_trang == null
                ? payload.status == null
                  ? null
                  : payload.status
                : payload.tinh_trang
              : payload.trang_thai,
          raw: payload,
        },
      };
    },
    {
      test: (n) => /\b(trang thai chuyen|chuyen da chay chua)\b/.test(n),
      build: (n) => {
        const args = {};
        const id = extractTripId(n);
        if (id) args.trip_id = id;
        return {
          toolName: "trip_get_trip_status",
          rationale: "Khách xem trạng thái chuyến.",
          arguments: args,
        };
      },
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
    (args) => {
      const parsed = positiveId(args.trip_id, "trip_id");
      if (!parsed.ok) return Promise.resolve(parsed);
      return jsonResult(`chuyen-xe/${parsed.id}/ghe`, {
        method: "GET",
        auth: "none",
      });
    },
  );

  register(
    "trip_get_trip_price",
    TRIP_TOOL_SLOTS.get_trip_price,
    "safe",
    ["Giá vé", "Đặt vé"],
    async (args) => {
      const parsed = positiveId(args.trip_id, "trip_id");
      if (!parsed.ok) return parsed;
      const result = await jsonResult(`chuyen-xe/${parsed.id}/tom-tat`, {
        method: "GET",
        auth: "none",
      });
      if (!result.ok) return result;
      const payload =
        result.data?.data == null
          ? result.data == null
            ? {}
            : result.data
          : result.data.data;
      const price =
        payload.gia_ve == null
          ? payload.gia_ve_co_ban == null
            ? payload.price == null
              ? payload.base_price == null
                ? null
                : payload.base_price
              : payload.price
            : payload.gia_ve_co_ban
          : payload.gia_ve;
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
    },
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
  );

  register(
    "seat_get_seat_map",
    SEAT_TOOL_SLOTS.get_seat_map,
    "safe",
    ["Sơ đồ ghế", "Chọn ghế"],
    (args) => {
      const parsed = positiveId(args.trip_id, "trip_id");
      if (!parsed.ok) return Promise.resolve(parsed);
      return jsonResult(`chuyen-xe/${parsed.id}/ghe`, {
        method: "GET",
        auth: "none",
      });
    },
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
  );

  register(
    "seat_check_available_seats",
    SEAT_TOOL_SLOTS.check_available_seats,
    "safe",
    ["Ghế trống", "Chọn ghế"],
    async (args) => {
      const badSeats = !Array.isArray(args.seat_ids)
        ? []
        : args.seat_ids
            .map((id) => String(id == null ? "" : id).trim())
            .filter(
              (id) => id && !/^[A-Ha-h](0?[1-9]|[12][0-9]|3[0-2])$/.test(id),
            );
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
      const parsed = positiveId(args.trip_id, "trip_id");
      if (!parsed.ok) return parsed;
      const result = await jsonResult(`chuyen-xe/${parsed.id}/ghe`, {
        method: "GET",
        auth: "none",
      });
      if (!result.ok) return result;

      const seatPayload = result.data?.data;
      const rows = Array.isArray(seatPayload?.so_do_ghe)
        ? seatPayload.so_do_ghe
        : Array.isArray(seatPayload)
          ? seatPayload
          : Array.isArray(seatPayload?.data)
            ? seatPayload.data
            : Array.isArray(result.data)
              ? result.data
              : Array.isArray(seatPayload?.items)
                ? seatPayload.items
                : Array.isArray(result.data?.items)
                  ? result.data.items
                  : [];
      const free = rows.filter((seat) => {
        const status = String(
          seat?.tinh_trang == null
            ? seat?.trang_thai == null
              ? seat?.status == null
                ? ""
                : seat.status
              : seat.trang_thai
            : seat.tinh_trang,
        )
          .trim()
          .toLowerCase()
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, "")
          .replace(/đ/g, "d");
        return ["", "trong", "con_trong", "available", "empty"].includes(
          status,
        );
      });

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
    {
      test: (n) =>
        /\b(con ghe|ghe trong|con trong|con bao nhieu ghe|available seats|kiem tra ghe)\b/.test(n),
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
  );

  register(
    "seat_hold_seat",
    SEAT_TOOL_SLOTS.hold_seat,
    "safe",
    ["Giữ ghế", "Đặt vé"],
    (args) => stub("seat_hold_seat", args),
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
  );
}
