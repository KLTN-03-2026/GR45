import {
  narrowRegisteredSuggestionLabelsWithLanguageModel,
} from "../../suggestion-language-model.js";

function normalize(value) {
  return String(value ?? "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d")
    .replace(/\s+/g, " ")
    .trim();
}

function uniqueStrings(values, limit = 5) {
  const out = [];
  const seen = new Set();

  for (const value of Array.isArray(values) ? values : []) {
    const text = String(value ?? "").trim();
    const key = normalize(text);

    if (!text || seen.has(key)) continue;

    seen.add(key);
    out.push(text);

    if (out.length >= limit) break;
  }

  return out;
}

function hasConcreteContext({ latestUserMessage, finalAnswer, transcript }) {
  const joined = normalize(
    `${latestUserMessage}\n${finalAnswer}\n${transcript}`,
  );

  return /chuyen|tuyen|ve|ghe|dat|huy|thanh toan|hoan tien|voucher|nha xe|lich|gio|diem don|diem tra|ho tro|tai khoan|dang nhap/.test(
    joined,
  );
}

function fallbackRankLabels(labels, latestUserMessage, limit = 5) {
  const text = normalize(latestUserMessage);

  const scored = uniqueStrings(labels, 50).map((label) => {
    const key = normalize(label);

    let score = 0;

    if (/ve|dat|ghe|chuyen|tuyen/.test(text) && /ve|dat|ghe|chuyen|tuyen/.test(key)) score += 3;
    if (/thanh toan|payment/.test(text) && /thanh toan|payment/.test(key)) score += 3;
    if (/huy|hoan tien|refund/.test(text) && /huy|hoan|refund/.test(key)) score += 3;
    if (/voucher|ma giam/.test(text) && /voucher|giam/.test(key)) score += 3;
    if (/ho tro|nhan vien|admin/.test(text) && /ho tro|nhan vien|admin/.test(key)) score += 3;

    return { label, score };
  });

  return scored
    .sort((a, b) => b.score - a.score)
    .filter((x) => x.score > 0)
    .map((x) => x.label)
    .slice(0, limit);
}

/**
 * Hẹp catalogue đăng ký tool xuống tối đa 5 nhãn.
 *
 * @param {{
 *   languageModel?: { completeJson?: (prompt: string) => Promise<unknown> };
 *   registeredSuggestionLabels: string[];
 *   latestUserMessage?: string;
 *   finalAnswer?: string;
 *   transcript?: string;
 * }} p
 */
export async function rankRegisteredSuggestionLabelsWithLlm(p) {
  const {
    languageModel,
    registeredSuggestionLabels = [],
    latestUserMessage = "",
    finalAnswer = "",
    transcript = "",
  } = p;

  const labels = uniqueStrings(registeredSuggestionLabels, 50);

  if (!labels.length) return [];

  if (!hasConcreteContext({ latestUserMessage, finalAnswer, transcript })) {
    return [];
  }

  const fallback = fallbackRankLabels(labels, latestUserMessage, 5);

  if (!languageModel || typeof languageModel.completeJson !== "function") {
    return fallback;
  }

  try {
    const ranked = await narrowRegisteredSuggestionLabelsWithLanguageModel({
      languageModel,
      orderedRegisteredSuggestionLabels: labels,
      latestUserQuestionText: latestUserMessage,
      assistantFinalAnswerText: finalAnswer,
      recentConversationTranscriptSnippet: transcript,
    });

    const allowed = new Set(labels.map(normalize));

    const clean = uniqueStrings(ranked, 5).filter((label) =>
      allowed.has(normalize(label)),
    );

    return clean.length ? clean : fallback;
  } catch {
    return fallback;
  }
}