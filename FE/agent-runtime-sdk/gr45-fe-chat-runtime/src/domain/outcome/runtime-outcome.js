/**
 * Derive `metadata.ai.outcome` từ tín hiệu graph runtime.
 *
 * Admin chat-logs (`AdminChatAiKnowledgeController::resolveOutcome`) đọc thẳng cờ
 * này — không suy đoán bằng regex VN. Trả về một trong:
 *   - `success`       : có tool result OK (data/success/count) hoặc RAG hit + reply hợp lệ.
 *   - `clarification` : chatbot đang hỏi lại (tool báo clarification_needed / reply ngắn-kiểu hỏi).
 *   - `failed`        : tool error / không nguồn / reply là NO_KB.
 *   - `unknown`       : không khớp luật (mặc định an toàn).
 */

const NO_KB_ANSWER_NEEDLE = /chưa tìm được thông tin/i;

const CLARIFICATION_REPLY_NEEDLE =
  /\b(bạn muốn|bạn cho em|vui lòng|thiếu (ngày|giờ|điểm)|chưa rõ)\b/i;

const RAG_MIN_ANSWER_LEN = 28;

function hasClarificationFlag(row) {
  const data = row?.data;
  if (!data || typeof data !== "object") return false;
  if (data.clarification_needed === true) return true;
  const inner = data.data;
  return Boolean(
    inner && typeof inner === "object" && inner.clarification_needed === true,
  );
}

function hasSuccessfulPayload(row) {
  const data = row?.data;
  if (!data || typeof data !== "object") return false;
  if (data.success === true) return true;
  if (Array.isArray(data.data) && data.data.length > 0) return true;
  if (typeof data.count === "number" && data.count > 0) return true;
  const inner = data.data;
  if (inner && typeof inner === "object") {
    if (inner.success === true) return true;
    if (Array.isArray(inner.data) && inner.data.length > 0) return true;
  }
  return false;
}

function isErrorRow(row) {
  return (
    row?.ok === false || (row?.error != null && String(row?.error).trim() !== "")
  );
}

export function deriveRuntimeOutcome({
  toolResults,
  ragContext,
  answerText,
} = {}) {
  const tools = Array.isArray(toolResults) ? toolResults : [];
  const trimmed = String(answerText ?? "").trim();
  const answerLen = trimmed.length;

  if (NO_KB_ANSWER_NEEDLE.test(trimmed)) return "failed";

  const okTools = tools.filter((row) => row?.ok === true);
  const errorTools = tools.filter(isErrorRow);

  if (
    okTools.some(hasClarificationFlag) ||
    errorTools.some(hasClarificationFlag)
  ) {
    return "clarification";
  }

  if (okTools.some(hasSuccessfulPayload)) {
    return answerLen > 0 && !CLARIFICATION_REPLY_NEEDLE.test(trimmed)
      ? "success"
      : "clarification";
  }

  if (errorTools.length > 0 && okTools.length === 0) return "failed";

  const ragHits = Array.isArray(ragContext) ? ragContext.length : 0;
  if (ragHits > 0) {
    if (answerLen < RAG_MIN_ANSWER_LEN || CLARIFICATION_REPLY_NEEDLE.test(trimmed)) {
      return "clarification";
    }
    return "success";
  }

  if (answerLen === 0) return "failed";
  return "unknown";
}
