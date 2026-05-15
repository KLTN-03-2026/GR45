/**
 * Plan post-processor (rule-only):
 *   - Sanitize forbidden transport words ("chuyến bay" → "chuyến xe").
 *   - Drop `confirmed` flags (runtime/UI handles confirmation).
 *   - Detect PDF/policy question with no tool plan → flag `needs_rag_fallback`.
 *
 * Tool selection lives entirely in fast-planner (per-tool patterns). No re-runs,
 * no merges — historical fast-planner re-merge caused the "router nhảy lung tung" symptom.
 *
 * Live-support helpers (`GR45_LIVE_SUPPORT_SESSION_TOOL_NAME`,
 * `collectLiveSupportPublicIdsFromToolResults`) live with the support tool file
 * under `catalog/tools/tracking-map-transport-support.tools.js` and are re-exported
 * here for backwards compatibility.
 */

export {
  collectLiveSupportDeferredOpeningFromToolResults,
  collectLiveSupportPublicIdsFromToolResults,
  GR45_LIVE_SUPPORT_SESSION_TOOL_NAME,
} from "./catalog/tools/tracking-map-transport-support.tools.js";

/** Synthesizer reply policy: GR45 only sells road-transport tickets. */
export const GR45_REPLY_SURFACE_TRANSPORT_VI =
  "Ứng dụng chỉ bán vé xe khách / xe bus (đường bộ). Trong `reply` cho khách: chỉ nói chuyến xe, lịch xe, giờ khởi hành, tuyến, vé xe, nhà xe. Cấm đề cập hoặc hỏi lựa chọn về: chuyến bay, máy bay, sân bay, flight, airline, vé tàu, tàu hỏa, đường sắt, chuyến tàu, metro, ‘các phương tiện khác’ kiểu so sánh với máy bay/tàu — trừ khi khách hỏi thẳng và bạn chỉ từ chối lịch sự và hướng sang xe khách (ở địa chỉ / điểm đón xe **đường bộ** được phép nhắc địa danh như gần sân bay nếu đó là **điểm đón xe khách**). Lời chào / small talk (xin chào, chào bạn, hello): chỉ chào ngắn và mời họ nói cần tìm **chuyến xe / lịch xe / vé** hay hỗ trợ gì; không bắt đầu bằng câu hỏi về máy bay hoặc tàu. Khi thao tác **search_routes** đã trả về danh sách tuyến (count > 0): trả lời rõ có tuyến / tóm tắt đúng các tuyến từ JSON; **không** hỏi giờ khởi hành hay “chuyến trong khung giờ nào” trừ khi khách hỏi lịch/chuyến/giờ/giá; **không** tự suy diễn chiều ngược lại hay nói kiểu “chỉ cung cấp” nếu khách không hỏi điều đó. Khi count = 0: chỉ nói không thấy tuyến công khai khớp tra cứu; **không** hứa sẽ tìm thêm, liên lạc lại, hoặc hướng khách sang website/nhà xe khác nếu dữ liệu không có. Khi **search_trips** trả về 0 chuyến nhưng khách đã nêu đủ điểm đi, điểm đến, ngày đi và giờ/khung giờ: báo chưa có chuyến phù hợp, có thể gợi ý đổi ngày/giờ; **không** hỏi thêm tên nhà xe vì nhà xe chỉ là bộ lọc tùy chọn. Khi thao tác ghi CSDL (đặt vé, thanh toán, cập nhật hồ sơ…) thành công: tóm tắt đúng kết quả từ JSON, không bịa mã không có trong dữ liệu. Không thêm câu kết máy móc kiểu “đã hỗ trợ” chỉ để đánh dấu hoàn tất. **Trả lời từ trích PDF/RAG (A):** phải có dấu hiệu trích dẫn rõ **theo tài liệu** / **trong tài liệu** / **theo nội dung đã cung cấp** — không nói kiểu tự suy đoán ngoài tài liệu.";

const FORBIDDEN_TRANSPORT_WORDS_RE =
  /\b(chuyến bay|máy bay|vé tàu|tàu hỏa|đường sắt|chuyến tàu|flight|airline|airport|sân bay)\b/gi;

const OPERATIONAL_INTENT_RE =
  /\b(tuyen|chuyen(?!\s+doi\b)|lich xe|gio xe|gio khoi hanh|ve xe|dat ve|diem don|don tai|tram dung|ghe|nha xe|thanh toan|huy ve|hoan tien|tracking|xe dang o dau|ho so|tai khoan|dang nhap|dang xuat|voucher|ho tro|lien he|lien lac|gap admin|gap nha xe|tu van|tong dai|live support)\b/i;

const PDF_CORPUS_QUERY_RE =
  /(?:trong|thong tin trong|noi dung trong)\s+(?:pdf|pfd|tai lieu)|\b(?:pdf|pfd|tai lieu)\s+(?:noi gi|co gi|ghi gi)\b|^\s*(pdf|pfd|tai lieu)\b[\s.,?¿]*$/i;

/** Khảo sát / nghiên cứu — fast planner không có tool → vẫn bật RAG (PDF KB). */
const SURVEY_RESEARCH_CORPUS_RE =
  /\b(khao sat|nghien cuu|doi tuong dieu tra|mau dieu tra|bang hoi|ket qua nghien cuu|tham gia tra loi|yeu to tac dong|danh cho ai|phan (nao|vi))\b/i;

function normalizePlannerUserText(message) {
  return String(message ?? "")
    .normalize("NFKC")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .replace(/đ/g, "d")
    .replace(/\s+/g, " ")
    .trim();
}

function sanitizeTransportText(value) {
  return String(value ?? "").replace(FORBIDDEN_TRANSPORT_WORDS_RE, "chuyến xe");
}

/**
 * @param {{ userMessage?: string; plan: object }} input
 */
export function postProcessGr45Plan({ userMessage = "", plan }) {
  const normalizedText = normalizePlannerUserText(userMessage);

  const toolCalls = Array.isArray(plan.toolCalls)
    ? plan.toolCalls.map(({ confirmed, ...call }) => ({
        ...call,
        rationale: sanitizeTransportText(call.rationale),
      }))
    : [];

  const isOperational = OPERATIONAL_INTENT_RE.test(normalizedText);
  const isPdfQuestion =
    !isOperational &&
    toolCalls.length === 0 &&
    PDF_CORPUS_QUERY_RE.test(normalizedText);

  const isSurveyResearchCorpus =
    !isOperational &&
    toolCalls.length === 0 &&
    SURVEY_RESEARCH_CORPUS_RE.test(normalizedText);

  return {
    ...plan,
    goal: sanitizeTransportText(plan.goal),
    hypothesis: sanitizeTransportText(plan.hypothesis),
    stopCondition: sanitizeTransportText(plan.stopCondition),
    toolCalls,
    needs_rag_fallback:
      isPdfQuestion ||
      isSurveyResearchCorpus ||
      (Boolean(plan.needs_rag_fallback) && !isOperational),
  };
}
