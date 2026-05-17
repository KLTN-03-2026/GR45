/**
 * Rule-base fast planner — generic runner.
 *
 * Patterns are registered by each catalog tool group. This module only reads
 * the registered list and owns iteration + plan envelope. No tool-specific
 * logic here.
 *
 * To extend: add a pattern entry inside the matching tool file and register it
 * from that file's `registerXTools(ctx)`.
 */
import { getGr45FastPlannerPatterns } from "../../catalog/tools/index.js";
import {
  DEFAULT_FAST_PLANNER_TIME_ZONE,
  extractMaNhaXe,
  extractOperatorNameLoose,
  localIsoDate,
  normalize,
} from "./text-utils.js";

function runPatterns(patterns, normalizedText, todayIso, rawText, recentUserMessages) {
  for (const pattern of patterns) {
    const ok =
      typeof pattern.test === "function" && pattern.test.length >= 2
        ? pattern.test(normalizedText, rawText, recentUserMessages)
        : pattern.test(normalizedText);
    if (ok) {
      return pattern.build(normalizedText, todayIso, rawText, recentUserMessages);
    }
  }
  return null;
}

function getRecentUserMessages(state) {
  return Array.isArray(state?.messages)
    ? state.messages
        .filter((message) => message?.role === "user")
        .map((message) => String(message?.content ?? "").trim())
        .filter(Boolean)
        .slice(-8)
    : [];
}

function getRecentChatMessages(state) {
  return Array.isArray(state?.messages)
    ? state.messages
        .filter(
          (message) =>
            message?.role === "user" || message?.role === "assistant",
        )
        .map((message) => ({
          role: message.role,
          content: String(message?.content ?? "").trim(),
        }))
        .filter((message) => message.content)
        .slice(-12)
    : [];
}

/**
 * Lệnh "liên hệ hỗ trợ / gặp admin / hotline …" là **one-shot** — không được kéo
 * vào planning text của lượt sau, kẻo câu hỏi tra vé bị merge → khớp
 * SUPPORT_USER_TRIGGER_RE → fast planner mở lại phiên live support nhầm.
 */
const PRIOR_SUPPORT_TRIGGER_DROP_RE =
  /\b(ho tro|gap admin|gap nha xe|gap nhan vien|tong dai|tu van vien|live support|chat voi nhan vien|chat voi nha xe|noi chuyen voi nguoi|lien he|lien lac|hotline|ket noi nhan vien|goi tong dai)\b/;

function dropStalePriorSupportMessages(messages) {
  if (!Array.isArray(messages) || messages.length < 2) return messages;
  const latest = messages[messages.length - 1];
  const prior = messages.slice(0, -1).filter((m) => {
    const n = normalize(m);
    return !PRIOR_SUPPORT_TRIGGER_DROP_RE.test(n);
  });
  return [...prior, latest];
}

function shouldMergeRecentUserMessages(latestText) {
  const n = normalize(latestText);
  if (
    /\b(dang nhap|dang xuat|dang ky|quen mat khau|dat lai mat khau|doi mat khau|kich hoat|xem ho so|tai khoan|trang thai tai khoan|cap nhat|lich su dat ve|xem ve cua toi|chi tiet ve|chi tiet chuyen|thong tin chuyen|trang thai ve|trang thai chuyen|gia ve|so do ghe|kiem tra ghe|chon ghe|diem don|diem tra|theo doi|xem ban do|toc do xe|gio den du kien|dat ve|huy ve|huy dat ve|doi ve|doi ghe|thanh toan|voucher|hoan tien|lien he ho tro|gap admin|chat voi nha xe|gui tin nhan ho tro|xem tin nhan ho tro|dong phien ho tro)\b/.test(
      n,
    )
  ) {
    return false;
  }
  if (extractMaNhaXe(n)) {
    return true;
  }
  if (extractOperatorNameLoose(latestText)) {
    return true;
  }
  if (/^nha xe\b/.test(n) || /^nx[a-z0-9_-]{2,}\b/.test(n)) {
    return true;
  }
  if (
    /\b(co|con|tuyen|routes?|buses?|bus service)\b/.test(n) &&
    /\b(tu|from)\b/.test(n) &&
    /\b(den|toi|to)\b/.test(n)
  ) {
    return false;
  }
  // Chip-click style messages — short verb-only labels that imply continuing
  // an existing trip-search / detail / booking context. Without merging, the
  // planner sees only "Tim chuyen khac" and can't resolve route/date/operator.
  const isShort = latestText.length <= 32;
  if (
    isShort &&
    /\b(tim (tuyen|chuyen|ghe|ve) khac|tim them|chi tiet (chuyen|tuyen)|chon ghe|dat ve|huy ve|xem (chuyen|ve|ghe)|so do ghe|gia ve|theo doi|loc theo gio)\b/.test(
      n,
    )
  ) {
    return true;
  }
  // Chip chọn đích live support sau bước hỏi admin vs nhà xe.
  if (
    isShort &&
    /\b(gap admin he thong|gap nha xe|chat voi admin|chat voi nha xe|lien he admin|lien he nha xe)\b/.test(
      n,
    )
  ) {
    return true;
  }
  // Follow-up câu hỏi liên quan ngữ cảnh tuyến trước đó. Ví dụ user vừa hỏi
  // "Nhà xe X có tuyến A→B không", rồi hỏi "nếu có nhiều chuyến thì sao" /
  // "có chuyến nào sáng không" — cần merge để planner biết operator + route.
  const isShortish = latestText.length <= 80;
  if (
    isShortish &&
    /\b(neu|nếu|the?|thi|thì|con|còn|gio (sang|chieu|toi)|sang|chieu|toi|sớm|trưa|tối|nhieu (chuyen|tuyen|ve)|bao nhieu|chuyen nao|tuyen nao|chuyen khac|tuyen khac|may gio|gia bao nhieu|gia ve|gio xuat phat|gio den)\b/.test(
      n,
    )
  ) {
    return true;
  }
  return (
    /\b(ngay|buoi sang|buoi chieu|buoi toi|truoc|sau|di tu|tu|den|toi)\b/.test(n) ||
    /\b(morning|afternoon|evening|before|after|between)\b/.test(n) ||
    /\b(january|february|march|april|may|june|july|august|september|october|november|december)\b/.test(
      n,
    ) ||
    /\b\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?\b/.test(n)
  );
}


function buildPlanningText(userMessage, state) {
  const latestText = String(userMessage ?? "").trim();
  const recentUserMessages = dropStalePriorSupportMessages(
    getRecentUserMessages(state),
  );
  if (
    recentUserMessages.length < 2 ||
    !shouldMergeRecentUserMessages(latestText)
  ) {
    return latestText;
  }
  return recentUserMessages.join(" ");
}

export function gr45FastPlanner({
  userMessage,
  state,
  now = new Date(),
  timeZone = DEFAULT_FAST_PLANNER_TIME_ZONE,
  patterns = getGr45FastPlannerPatterns(),
} = {}) {
  const text = String(userMessage ?? "").trim();
  if (!text) return null;

  const recentUserMessages = dropStalePriorSupportMessages(
    getRecentUserMessages(state),
  );
  const recentChatMessages = getRecentChatMessages(state);

  const planningText = buildPlanningText(text, state);
  const normalizedText = normalize(planningText);
  const todayIso = localIsoDate(now, timeZone);
  const toolCall = runPatterns(patterns, normalizedText, todayIso, planningText, recentChatMessages);
  if (!toolCall) return null;

  return {
    goal: text.slice(0, 200),
    steps: [],
    stopCondition: "tool_result_observed",
    confidence: 0.85,
    toolCalls: [toolCall],
    needs_grounding: false,
    needs_rag_fallback: false,
  };
}
