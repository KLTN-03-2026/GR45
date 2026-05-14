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

const OPERATIONAL_RE =
  /\b(tuyen|tim tuyen|tim chuyen|chuyen|lich xe|gio xe|ve xe|dat ve|diem don|don tai|tram dung|kiem tra ve|chi tiet ve|trang thai ve|xem ve|thong tin ve|tra cuu ve|tra cuu|ma ve|so ve|ve cua toi|danh sach ve|lich su dat ve|ghe|chon ghe|nha xe|thanh toan|huy ve|doi ve|hoan tien|tracking|vi tri xe|ho so|tai khoan|dang nhap|voucher|ho tro|lien he|gap admin|gap nha xe|tong dai|tu van)\b/i;

/** Mã vé / tra cứu ngắn — OPERATIONAL_RE có thể miss (vd. chỉ nói mã). */
const TICKET_CODE_RE = /\b(vx|bs|bv|tk)[a-z0-9]{2,}\b/i;

const POLICY_RE =
  /\b(policy|chinh sach|quy dinh|dieu khoan|clause|legal|pdf|tai lieu|faq)\b/i;

const MONEY_RISK_RE =
  /\b(refund|hoan tien|huy ve|doi ve|thanh toan|invoice|hoa don)\b/i;

/**
 * `intent === "standard"` nhưng vẫn nên qua planner để có tool (false-negative regex).
 * Không dùng khi đã operational / policy / money risk (intent khác đã xử lý).
 */
export function shouldRouteStandardThroughPlanner(text) {
  const n = normalizeText(text);
  if (!n) return false;
  if (OPERATIONAL_RE.test(n)) return false;
  if (POLICY_RE.test(n)) return false;
  if (MONEY_RISK_RE.test(n)) return false;
  if (TICKET_CODE_RE.test(n)) return true;
  if (/\btra cuu\b/.test(n)) return true;
  if (/\b(ma ve|so ve)\b/.test(n)) return true;
  return false;
}

export function classifyIntentText(text) {
  const rawText = String(text ?? "");
  const normalized = normalizeText(rawText);

  const isLongForm = rawText.length > 200;
  const isOperational = OPERATIONAL_RE.test(normalized);
  const isPolicyQuestion = POLICY_RE.test(normalized);
  const isMoneyRisk = MONEY_RISK_RE.test(normalized);

  const intent = isLongForm
    ? "long_form"
    : isMoneyRisk
      ? "risk_sensitive"
      : isPolicyQuestion
        ? "policy_or_document"
        : isOperational
          ? "operational"
          : "standard";

  return {
    intent,
    isOperational,
    isPolicyQuestion,
    isMoneyRisk,
    normalized,
  };
}

