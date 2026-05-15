import { unwrapSuggestionsEnvelope } from "@fe-agent/core";
import { SUGGESTIONS_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION } from "../../reply-language-policy.js";

const MAX_SUGGESTIONS = 3;

const GENERIC_SUGGESTION_RE =
  /^(xin chào|chào|hello|hi|ok|cảm ơn|bạn có thể giúp gì|tôi cần hỗ trợ)$/i;

function compact(value, max) {
  return String(value ?? "").trim().slice(0, max);
}

function normalize(value) {
  return String(value ?? "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d")
    .replace(/\s+/g, " ")
    .trim();
}

function uniqueCleanSuggestions(values, { latestUserMessage, toolNames }) {
  const latestNorm = normalize(latestUserMessage);
  const toolNameSet = new Set(toolNames.map(normalize));
  const out = [];

  for (const raw of Array.isArray(values) ? values : []) {
    const text = String(raw ?? "").trim();
    const norm = normalize(text);

    if (!text) continue;
    if (GENERIC_SUGGESTION_RE.test(text)) continue;
    if (norm.length < 3) continue;
    if (norm === latestNorm) continue;
    if (toolNameSet.has(norm)) continue;
    if (out.some((x) => normalize(x) === norm)) continue;

    out.push(text);
    if (out.length >= MAX_SUGGESTIONS) break;
  }

  return out;
}

function hasConcreteContext({ latestUserMessage, finalAnswer, transcript }) {
  const joined = normalize(
    `${latestUserMessage}\n${finalAnswer}\n${transcript}`,
  );

  if (!joined) return false;

  return /chuyen|tuyen|ve|ghe|dat|huy|thanh toan|hoan tien|voucher|nha xe|lich|gio|diem don|diem tra|ho tro|tai khoan|dang nhap/.test(
    joined,
  );
}

/**
 * Sinh suggestions freeform qua LLM.
 *
 * @param {{
 *   languageModel?: { completeJson?: (prompt: string) => Promise<unknown> };
 *   registeredToolDefinitions: Array<{ name?: string }>;
 *   latestUserMessage?: string;
 *   finalAnswer?: string;
 *   transcript?: string;
 * }} p
 */
export async function generateLlmSuggestions(p) {
  const {
    languageModel,
    registeredToolDefinitions = [],
    latestUserMessage = "",
    finalAnswer = "",
    transcript = "",
  } = p;

  if (typeof languageModel?.completeJson !== "function") return [];

  if (!hasConcreteContext({ latestUserMessage, finalAnswer, transcript })) {
    return [];
  }

  const capabilityNames = registeredToolDefinitions
    .map((toolDefinition) => String(toolDefinition?.name ?? "").trim())
    .filter(Boolean)
    .slice(0, 8);

  const prompt = [
    SUGGESTIONS_MATCH_CUSTOMER_LANGUAGE_INSTRUCTION,
    "",
    'Return JSON ONLY: {"suggestions": string[]}. Max 3.',
    "Suggestions are short customer tap-to-send next messages.",
    "They must be specific to the latest user question and assistant reply.",
    "Do not output tool names.",
    "Do not output generic greetings/help text.",
    "Do not repeat the customer's latest message.",
    "",
    `Customer:\n${compact(latestUserMessage, 500) || "(none)"}`,
    "",
    `Assistant:\n${compact(finalAnswer, 700) || "(none)"}`,
    "",
    `Recent:\n${compact(transcript, 700) || "(none)"}`,
    "",
    `Tool ids for context only: ${capabilityNames.join(", ")}`,
    "",
    'If no useful next action: {"suggestions":[]}',
  ].join("\n");

  try {
    const raw = await languageModel.completeJson(prompt);
    const suggestions = unwrapSuggestionsEnvelope(raw);

    return uniqueCleanSuggestions(suggestions, {
      latestUserMessage,
      toolNames: capabilityNames,
    });
  } catch {
    return [];
  }
}