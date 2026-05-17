const VI_DIACRITIC_RE =
  /[àáạảãâầấậẫẩăằắặẵẳèéẹẽẻêềếệễểìíịỉĩòóọỏõôồốộỗổơờớợỡởùúụủũưừứựửữỳýỵỷỹđĐ]/u;

const CJK_RE = /[\u4e00-\u9fff\u3000-\u303f\uff00-\uffef]/;

const VI_HINT_RE =
  /\b(co|khong|không|hong|ko|toi|tôi|hom nay|hôm nay|bao nhieu|bao nhiêu|xin|chao|chào|cam on|cảm ơn|gi|gì|sao|the nao|thế nào)\b/i;

export function userPrefersVietnamese(latestUserQuestion) {
  const text = textOrEmpty(latestUserQuestion);

  if (CJK_RE.test(text)) return false;
  if (VI_DIACRITIC_RE.test(text)) return true;

  return VI_HINT_RE.test(text);
}

export function replyHasCJK(text) {
  return CJK_RE.test(textOrEmpty(text));
}

export function replyHasVietnameseDiacritics(text) {
  return VI_DIACRITIC_RE.test(textOrEmpty(text));
}

export function coerceReplyForVietnameseAppUser(
  latestUserQuestion,
  replyCandidate,
) {
  const reply = textOrEmpty(replyCandidate).trim();

  if (!reply) return reply;
  if (!userPrefersVietnamese(latestUserQuestion)) return reply;
  if (!replyHasCJK(reply)) return reply;

  // Nếu reply có cả CJK lẫn tiếng Việt có dấu, giữ lại vì có thể là tên riêng/trích dẫn.
  if (replyHasVietnameseDiacritics(reply)) return reply;

  return "Xin lỗi, phản hồi vừa rồi bị sai ngôn ngữ. Bạn vui lòng gửi lại câu hỏi bằng tiếng Việt để mình hỗ trợ chính xác hơn nhé.";
}
import { textOrEmpty } from "../value.js";
