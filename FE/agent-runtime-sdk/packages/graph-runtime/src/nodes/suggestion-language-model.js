import { unwrapSuggestionsEnvelope } from "@fe-agent/core";

const MAX_LABELS_FOR_LLM = 30;
const MAX_PICKED_SUGGESTIONS = 3;

function normalize(value) {
  return String(value ?? "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d")
    .replace(/\s+/g, " ")
    .trim();
}

function uniqueStrings(values, limit = Infinity) {
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

export function collectRegisteredSuggestionLabelsInOrder(toolDefinitionList) {
  const visibleTools = Array.isArray(toolDefinitionList)
    ? toolDefinitionList.filter(
        (tool) => !tool?.disabled && !tool?.hiddenFromCustomerPlanner,
      )
    : [];

  return uniqueStrings(
    visibleTools.flatMap((toolDefinition) =>
      Array.isArray(toolDefinition.suggestionLabels)
        ? toolDefinition.suggestionLabels
        : [],
    ),
    50,
  );
}

function fallbackPickLabels() {
  return [];
}

export async function narrowRegisteredSuggestionLabelsWithLanguageModel({
  languageModel,
  orderedRegisteredSuggestionLabels,
  latestUserQuestionText = "",
  assistantFinalAnswerText = "",
  recentConversationTranscriptSnippet = "",
}) {
  const labels = uniqueStrings(
    orderedRegisteredSuggestionLabels,
    MAX_LABELS_FOR_LLM,
  );

  if (!labels.length) return [];

  const contextText = [
    latestUserQuestionText,
    assistantFinalAnswerText,
    recentConversationTranscriptSnippet,
  ].join("\n");

  const fallback = fallbackPickLabels(labels, contextText);

  if (!languageModel || typeof languageModel.completeJson !== "function") {
    return fallback;
  }

  const prompt = [
    'Return JSON only: {"suggestions": string[]}.',
    "Pick up to 3 exact labels from catalog. Do not rewrite.",
    "If none fit: {\"suggestions\":[]}.",
    "",
    "Catalog:",
    labels.map((label, index) => `${index + 1}. ${label}`).join("\n"),
    "",
    `Customer: ${String(latestUserQuestionText).slice(0, 500)}`,
    `Assistant: ${String(assistantFinalAnswerText).slice(0, 700)}`,
    `Recent: ${String(recentConversationTranscriptSnippet).slice(0, 700)}`,
  ].join("\n");

  try {
    const raw = await languageModel.completeJson(prompt);
    const picked = unwrapSuggestionsEnvelope(raw);

    const allowed = new Map(labels.map((label) => [normalize(label), label]));

    const clean = uniqueStrings(picked, MAX_PICKED_SUGGESTIONS)
      .map((label) => allowed.get(normalize(label)))
      .filter(Boolean);

    return clean.length ? clean : fallback;
  } catch {
    return fallback;
  }
}
