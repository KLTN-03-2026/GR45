function normalizeText(value) {
  return String(value ?? "")
    .normalize("NFKC")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d")
    .replace(/\s+/g, " ")
    .trim();
}

const POLICY_RE =
  /\b(policy|chinh sach|quy dinh|dieu khoan|clause|legal|pdf|tai lieu|faq)\b/i;

/**
 * Câu hỏi dạng KNOWLEDGE / WIKI / FAQ general — không phải vận hành (search vé,
 * tra chuyến) và không phải policy keyword. Pattern này thường là câu hỏi PDF
 * KB admin đã upload (định nghĩa, thông tin nhân vật, số liệu, lịch sử…).
 *
 * Gồm cả diễn đạt kiểu đề ôn / nhận thức: "hiểu được vai trò", "GTCC", "giao thông công cộng"…
 *
 * Khi match → route qua `rag_retriever` thay vì trả lời 1-shot synthesizer.
 */
const KNOWLEDGE_QUERY_RE =
  /(\b(la ai|la gi|nghia la|dinh nghia|y nghia|khai niem|giai thich|tai sao|vi sao|nguyen nhan|nhu the nao|cach (thuc|nao)|the nao|gom (nhung)? gi|bao gom|thanh phan|co cau|cau truc|loai hinh|phan loai|so sanh|khac (nhau|biet)|lich su|nguon goc|so lieu|thong ke|tang truong|giam|tang|ti le|phan tram|bao nhieu|ke (tu|tu)? nam|trong nam|trong giai doan|hien nay|tinh den|theo|noi (ve|den)|noi cuoi|noi dung|thong tin (ve|chung|chi tiet)|tim hieu|ho (la|ten)|biet ve|hieu duoc|nam duoc|nhan thuc duoc|vai tro|chuc nang|tam quan trong|dong gop|phan tich|danh gia|chu truong|dinh huong|quy hoach|muc tieu|gtcc|gtcn)\b|\bgiao thong (cong cong|ca nhan)\b|\bchuong trinh hanh dong\b|\b(khao sat|nghien cuu|doi tuong dieu tra|doi tuong|mau dieu tra|bang hoi|ket qua nghien cuu|tham gia tra loi|yeu to tac dong|chuyen doi)\b|\b(he thong xe( buyt)?|xe buyt|van tai cong cong)\b|\b(danh cho ai|doi tuong la ai|phan nao|muc dich nghien cuu)\b)/i;

/**
 * Knowledge cues "đặc trưng KB" — gặp những từ này gần như chắc chắn là câu hỏi từ
 * tài liệu / nghiên cứu / phân tích, không phải tra cứu vé. Phải ưu tiên hơn
 * operational pattern (`chuyen`, `xe buyt` cũng xuất hiện trong PDF GTCC).
 *
 * Câu bị cắt giữa chừng ("...KHẢ NĂNG CHUYỂN") vẫn vào đây nhờ "yeu to tac dong".
 */
const STRONG_KNOWLEDGE_QUERY_RE =
  /\b(yeu to tac dong|kha nang chuyen doi|khao sat|nghien cuu|doi tuong dieu tra|mau dieu tra|bang hoi|ket qua nghien cuu|tham gia tra loi|chuong trinh hanh dong|gtcc|gtcn|van tai cong cong)\b|\bgiao thong (cong cong|ca nhan)\b/i;

const MONEY_RISK_RE =
  /\b(refund|hoan tien|cancel|huy|change|doi|payment|thanh toan|invoice|hoa don)\b/i;

/**
 * Liên hệ / hỗ trợ — bắt buộc vào planner + tool live support.
 * Dùng làm fallback nếu `operationalPatterns` domain không khớp (build cũ / edge unicode).
 */
export const SUPPORT_CONTACT_INTENT_RE =
  /\b(lien he|lien lac|ho tro|hotline|tu van vien|tong dai|gap admin|gap nha xe|gap nhan vien|live support|chat voi nhan vien|chat voi nha xe|noi chuyen voi nguoi|ket noi nhan vien|goi tong dai)\b/i;

function patternMatches(pattern, text) {
  if (!pattern) return false;
  if (pattern instanceof RegExp) return pattern.test(text);
  return new RegExp(String(pattern), "i").test(text);
}

function anyPatternMatches(patterns, text) {
  return (Array.isArray(patterns) ? patterns : []).some((pattern) =>
    patternMatches(pattern, text),
  );
}

/**
 * `intent === "standard"` nhưng vẫn nên qua planner để có tool (false-negative regex).
 * Không dùng khi đã operational / policy / money risk (intent khác đã xử lý).
 */
export function shouldRouteStandardThroughPlanner(text, options = {}) {
  const n = normalizeText(text);
  if (!n) return false;
  if (SUPPORT_CONTACT_INTENT_RE.test(n)) return true;
  if (anyPatternMatches(options.operationalPatterns, n)) return false;
  if (patternMatches(options.policyPattern ?? POLICY_RE, n)) return false;
  if (patternMatches(options.moneyRiskPattern ?? MONEY_RISK_RE, n)) return false;
  if (anyPatternMatches(options.followUpOperationalPatterns, n)) return true;
  if (anyPatternMatches(options.shortOperationalPatterns, n)) return true;
  return false;
}

/**
 * Sau khi planner/tool không cho payload vận hành hữu ích — có nên thử RAG (PDF KB)?
 * Dùng ở planner-runnable (plan rỗng) và observation-node (tool barren).
 */
export function shouldPreferPdfAfterBarrenTools(text, options = {}) {
  const { intent } = classifyIntentText(text, options);
  return intent === "policy_or_document" || intent === "long_form";
}

export function classifyIntentText(text, options = {}) {
  const rawText = String(text ?? "");
  const normalized = normalizeText(rawText);

  const isLongForm = rawText.length > 200;
  // Strong KB cue thắng cả operational ("chuyen" / "xe buyt" có thể xuất hiện cả trong PDF GTCC).
  const isStrongKnowledgeQuery = patternMatches(
    options.strongKnowledgeQueryPattern ?? STRONG_KNOWLEDGE_QUERY_RE,
    normalized,
  );
  const isOperational =
    !isStrongKnowledgeQuery &&
    (anyPatternMatches(options.operationalPatterns, normalized) ||
      SUPPORT_CONTACT_INTENT_RE.test(normalized));
  const isPolicyQuestion = patternMatches(
    options.policyPattern ?? POLICY_RE,
    normalized,
  );
  const isMoneyRisk =
    !isStrongKnowledgeQuery &&
    patternMatches(options.moneyRiskPattern ?? MONEY_RISK_RE, normalized);
  // Câu hỏi knowledge-style nhưng không phải operational/money-risk → coi như
  // policy_or_document để cho qua rag_retriever (PDF KB).
  const isKnowledgeQuery =
    isStrongKnowledgeQuery ||
    (!isOperational &&
      !isMoneyRisk &&
      !isPolicyQuestion &&
      patternMatches(
        options.knowledgeQueryPattern ?? KNOWLEDGE_QUERY_RE,
        normalized,
      ));

  const docOrKnowledgeIntent = isPolicyQuestion || isKnowledgeQuery;

  /**
   * Thứ tự quan trọng:
   * - `long_form` (độ dài >200) KHÔNG được nuốt câu hỏi PDF/KB — nếu khớp policy/knowledge
   *   vẫn phải vào `policy_or_document` → rag_retriever.
   * - Tiền / hoàn vé vẫn ưu tiên trước KB.
   */
  const intent = isMoneyRisk
    ? "risk_sensitive"
    : docOrKnowledgeIntent
      ? "policy_or_document"
      : isLongForm
        ? "long_form"
        : isOperational
          ? "operational"
          : "standard";

  return {
    intent,
    isOperational,
    isPolicyQuestion: isPolicyQuestion || isKnowledgeQuery,
    isKnowledgeQuery,
    isMoneyRisk,
    normalized,
  };
}
