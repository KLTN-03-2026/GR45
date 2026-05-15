import {
  gr45FastPlanner,
  routeExistenceSearchRoutesArgsFromUserMessage,
} from "./gr45-fast-planner.js";

/** Gắn synthesizer: GR45 chỉ bán vé xe đường bộ — tránh model nói “chuyến bay”. */
export const GR45_REPLY_SURFACE_TRANSPORT_VI =
  "Ứng dụng chỉ bán vé xe khách / xe bus (đường bộ). Trong `reply` cho khách: chỉ nói chuyến xe, lịch xe, giờ khởi hành, tuyến, vé xe, nhà xe. Cấm đề cập hoặc hỏi lựa chọn về: chuyến bay, máy bay, sân bay, flight, airline, vé tàu, tàu hỏa, đường sắt, chuyến tàu, metro, ‘các phương tiện khác’ kiểu so sánh với máy bay/tàu — trừ khi khách hỏi thẳng và bạn chỉ từ chối lịch sự và hướng sang xe khách (ở địa chỉ / điểm đón xe **đường bộ** được phép nhắc địa danh như gần sân bay nếu đó là **điểm đón xe khách**). Lời chào / small talk (xin chào, chào bạn, hello): chỉ chào ngắn và mời họ nói cần tìm **chuyến xe / lịch xe / vé** hay hỗ trợ gì; không bắt đầu bằng câu hỏi về máy bay hoặc tàu. Khi thao tác **search_routes** đã trả về danh sách tuyến (count > 0): trả lời rõ có tuyến / tóm tắt tuyến từ JSON; **không** hỏi giờ khởi hành hay “chuyến trong khung giờ nào” trừ khi khách hỏi lịch/chuyến/giờ/giá. Khi count = 0: nói không thấy tuyến công khai khớp tra cứu. **Kết quả thao tác (B) `ok: true` và có dữ liệu phục vụ được câu hỏi:** kết thúc `reply` bằng một câu ngắn, tự nhiên, có cụm **“đã hỗ trợ”** (ví dụ: “Em đã hỗ trợ bạn tra cứu chuyến như trên”). Khi **search_trips** trả về 0 chuyến nhưng khách **đã** nêu điểm đi/đến và ngày (và có thể giờ): **không** hỏi lại như thể thiếu giờ khởi hành; báo chưa có chuyến phù hợp, gợi ý đổi ngày/giờ; vẫn có **đã hỗ trợ** nếu tra cứu đã chạy. Khi thao tác ghi CSDL (đặt vé, thanh toán, cập nhật hồ sơ…) thành công: cũng **khẳng định đã hỗ trợ** và tóm tắt kết quả từ JSON, không bịa mã không có trong dữ liệu. **Trả lời từ trích PDF/RAG (A):** phải có dấu hiệu trích dẫn rõ **theo tài liệu** / **trong tài liệu** / **theo nội dung đã cung cấp** — không nói kiểu tự suy đoán ngoài tài liệu.";

/** Prompt ngắn cho planner. */
export const GR45_PLANNER_DOMAIN_INSTRUCTIONS = [
  "GR45 is a Vietnamese bus customer app.",
  "- Road transport only. Never say flight/airline/máy bay/chuyến bay.",
  "- Operational questions must use tools, not memory.",
  "- Use exact tool names from catalog.",
  "- Route/trip/schedule/seat/price/operator questions usually use search_trips or search_routes.",
  "- If the user only asks whether a **passenger route exists** (e.g. có tuyến … không, có chạy xe từ A đến B không) and does **not** ask for departure times, schedules, or a specific travel date: call **search_routes** with diem_di/diem_den extracted from the message. Do **not** block on ngay_khoi_hanh for that utterance.",
  "- Use **search_trips** when the user asks for **chuyến**/lịch/giờ/giá, or when ngay_khoi_hanh / a date is already given, or after you have shown routes and they pick a travel day, or when they mention **điểm đón / trạm / pickup** with a clear **tuyến + ngày** (map city/province into diem_di/diem_den plus date/time slots).",
  "- For search_trips/search_routes: ALWAYS extract from user message and put in arguments:",
  "  diem_di (departure province, e.g. 'Hà Nội'), diem_den (destination province, e.g. 'Đà Nẵng'),",
  "  ngay_khoi_hanh (YYYY-MM-DD), gio_khoi_hanh (HH:mm 24h). Omit key only if truly not mentioned.",
  "- Booking/payment/ticket/tracking/account/support questions must call matching tools; when a mutating tool returns success, the user-facing reply must reflect **only** persisted fields from the tool response (no fake IDs).",
  "- If user says đăng nhập/login (with phone or email), call auth_login first — never search_trips/search_routes for that utterance.",
  "- Human/admin support => support_create_support_session { target: 'admin' }.",
  "- Only use RAG fallback for PDF/policy/document questions.",
  "- Never emit confirmed:true; runtime/UI handles confirmation.",
  "- Greetings only (xin chào, hello): do NOT plan search_trips/search_routes unless user also asks about trips in the same message.",
].join("\n");

/** Tên tool trong catalog GR45 tạo phiên live support (đồng bộ planner fast-path / runtime metadata). */
export const GR45_LIVE_SUPPORT_SESSION_TOOL_NAME = "support_create_support_session";

const FORBIDDEN_TRANSPORT_WORDS_RE =
  /\b(chuyến bay|máy bay|vé tàu|tàu hỏa|đường sắt|chuyến tàu|flight|airline|airport|sân bay)\b/gi;

function normalizePlannerUserText(message) {
  return String(message ?? "")
    .normalize("NFKC")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d")
    .replace(/\s+/g, " ")
    .trim();
}

const SUPPORT_INTENT_RE =
  /\b(ho tro|nhan vien|admin|lien he|live support|gap nguoi|gap tong dai|tong dai|chat voi nhan vien|chat admin|gap nha xe|chat voi nha xe|lien he nha xe)\b/i;

const OPERATIONAL_INTENT_RE =
  /\b(tuyen|chuyen|lich xe|gio xe|gio khoi hanh|ve xe|dat ve|diem don|don tai|tram dung|ghe|nha xe|thanh toan|huy ve|hoan tien|tracking|xe dang o dau|ho so|tai khoan|dang nhap|dang xuat|voucher)\b/i;

const PDF_CORPUS_QUERY_RE =
  /(?:trong|thong tin trong|noi dung trong)\s+(?:pdf|pfd|tai lieu)|\b(?:pdf|pfd|tai lieu)\s+(?:noi gi|co gi|ghi gi)\b|^\s*(pdf|pfd|tai lieu)\b[\s.,?¿]*$/i;

/** Ưu tiên đăng nhập: tránh planner LLM trả search_trips vì ngữ cảnh Hue–ĐN trong history/RAG. */
const LOGIN_INTENT_RE =
  /\b(dang nhap|dang nhap vao|log in|login)\b/i;

function sanitizeTransportText(value) {
  return String(value ?? "").replace(FORBIDDEN_TRANSPORT_WORDS_RE, "chuyến xe");
}

function extractMaNhaXe(normalizedText) {
  const match = normalizedText.match(/\b(nx[a-z0-9_-]{2,})\b/i);
  return match ? match[1].toUpperCase() : "";
}

/**
 * @param {{ userMessage?: string; plan: object }} input
 */
export function postProcessGr45Plan({ userMessage = "", plan }) {
  const normalizedText = normalizePlannerUserText(userMessage);

  let toolCalls = Array.isArray(plan.toolCalls)
    ? plan.toolCalls.map(({ confirmed, ...call }) => ({
        ...call,
        rationale: sanitizeTransportText(call.rationale),
      }))
    : [];

  const wantsLogin = LOGIN_INTENT_RE.test(normalizedText);
  if (wantsLogin) {
    const hasAuthLogin = toolCalls.some(
      (c) => String(c?.toolName ?? "").trim() === "auth_login",
    );
    if (!hasAuthLogin) {
      const onlyTripTools =
        toolCalls.length > 0 &&
        toolCalls.every((c) => {
          const t = String(c?.toolName ?? "").trim();
          return t === "search_trips" || t === "search_routes";
        });
      const noTools = toolCalls.length === 0;
      if (onlyTripTools || noTools) {
        const phoneMatch = normalizedText.match(/\b(0\d{9,10})\b/);
        const emailMatch = String(userMessage ?? "").match(
          /\b[^\s@]{1,64}@[^\s@]{1,255}\.[^\s@]{2,32}\b/,
        );
        const args = {};
        if (phoneMatch) args.so_dien_thoai = phoneMatch[1];
        if (emailMatch) args.email = emailMatch[0].trim();
        toolCalls = [
          {
            toolName: "auth_login",
            rationale: "Khách yêu cầu đăng nhập — không dùng tìm chuyến cho câu này.",
            arguments: args,
          },
        ];
      }
    }
  }

  if (SUPPORT_INTENT_RE.test(normalizedText) && toolCalls.length === 0) {
    const wantsOperator =
      /\b(gap nha xe|chat voi nha xe|lien he nha xe|nha xe)\b/i.test(
        normalizedText,
      );
    const maNhaXe = extractMaNhaXe(normalizedText);
    toolCalls = [
      {
        toolName: GR45_LIVE_SUPPORT_SESSION_TOOL_NAME,
        rationale: wantsOperator
          ? "Khách muốn gặp hỗ trợ nhà xe."
          : "Khách muốn gặp hỗ trợ viên/admin.",
        arguments:
          wantsOperator && maNhaXe
            ? { target: "nha_xe", ma_nha_xe: maNhaXe }
            : { target: "admin" },
      },
    ];
  }

  /** Khách đã tìm chuyến kèm ngày — không được ép về search_routes. */
  const userWantsScheduledTripSearch =
    /\b(tim|kiem)\b.*\b(chuyen|xe khach|xe bus)\b/.test(normalizedText) &&
    /\d{1,2}[\/\-]\d{1,2}/.test(normalizedText);

  const routeSlots = routeExistenceSearchRoutesArgsFromUserMessage(userMessage);
  if (routeSlots && !userWantsScheduledTripSearch) {
    const names = toolCalls.map((c) => String(c?.toolName ?? "").trim());
    const onlySearchTools =
      toolCalls.length === 0 ||
      names.every((t) => t === "search_trips" || t === "search_routes");
    const misusedTrips = names.includes("search_trips");
    if (onlySearchTools && (toolCalls.length === 0 || misusedTrips)) {
      toolCalls = [
        {
          toolName: "search_routes",
          rationale: sanitizeTransportText(
            "Khách chỉ hỏi có tuyến giữa hai điểm — dùng search_routes (không cần ngày/giờ).",
          ),
          arguments: routeSlots,
        },
      ];
    }
  }

  const toolCallsBlockedForFastMerge = toolCalls.some((c) => {
    const t = String(c?.toolName ?? "").trim();
    return t === "auth_login" || t === GR45_LIVE_SUPPORT_SESSION_TOOL_NAME;
  });

  if (!toolCallsBlockedForFastMerge) {
    const fastPlan = gr45FastPlanner({ userMessage });
    const fastCall = fastPlan?.toolCalls?.[0];
    if (fastCall && String(fastCall.toolName ?? "").trim() === "search_trips") {
      const fa =
        fastCall.arguments && typeof fastCall.arguments === "object"
          ? fastCall.arguments
          : {};
      const okAnchors =
        String(fa.diem_di ?? "").trim() && String(fa.diem_den ?? "").trim();

      if (okAnchors && OPERATIONAL_INTENT_RE.test(normalizedText)) {
        if (toolCalls.length === 0) {
          toolCalls = [
            {
              toolName: "search_trips",
              rationale: sanitizeTransportText(
                String(fastCall.rationale ?? "Khách tìm chuyến xe."),
              ),
              arguments: { ...fa },
            },
          ];
        } else {
          const onlyRoutes =
            toolCalls.length > 0 &&
            toolCalls.every(
              (c) => String(c?.toolName ?? "").trim() === "search_routes",
            );

          if (onlyRoutes && userWantsScheduledTripSearch) {
            toolCalls = [
              {
                toolName: "search_trips",
                rationale: sanitizeTransportText(
                  "Khách tìm chuyến có ngày — tra search_trips.",
                ),
                arguments: { ...fa },
              },
            ];
          } else {
            const idx = toolCalls.findIndex(
              (c) => String(c?.toolName ?? "").trim() === "search_trips",
            );
            if (idx >= 0) {
              const cur = toolCalls[idx];
              const ca =
                cur.arguments && typeof cur.arguments === "object"
                  ? cur.arguments
                  : {};
              const merged = { ...fa };
              for (const [k, v] of Object.entries(ca)) {
                if (
                  v !== undefined &&
                  v !== null &&
                  String(v).trim() !== ""
                ) {
                  merged[k] = v;
                }
              }
              toolCalls = [...toolCalls];
              toolCalls[idx] = { ...cur, arguments: merged };
            }
          }
        }
      }
    }
  }

  const isOperational = OPERATIONAL_INTENT_RE.test(normalizedText);
  const isPdfQuestion =
    !isOperational &&
    toolCalls.length === 0 &&
    PDF_CORPUS_QUERY_RE.test(normalizedText);

  return {
    ...plan,
    goal: sanitizeTransportText(plan.goal),
    hypothesis: sanitizeTransportText(plan.hypothesis),
    stopCondition: sanitizeTransportText(plan.stopCondition),
    toolCalls,
    needs_rag_fallback:
      isPdfQuestion || (Boolean(plan.needs_rag_fallback) && !isOperational),
  };
}

/**
 * Thu `public_id` từ kết quả tool live support sau invoke graph.
 * Mỗi dòng `toolResults`: `{ toolName, ok, data }` với `data` = body JSON Laravel `{ success, data: { public_id } }`.
 * Metadata trả cho widget: `metadata.ai.live_support_public_ids` để subscribe `live-support.session.{id}`.
 *
 * @param {unknown} toolResults
 * @returns {string[]}
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
