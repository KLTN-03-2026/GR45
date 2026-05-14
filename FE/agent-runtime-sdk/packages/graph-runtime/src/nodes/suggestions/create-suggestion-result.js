import { journalEntry } from "@fe-agent/observability";

const MAX_SUGGESTIONS = 5;
const MAX_SUGGESTION_LENGTH = 120;

function normalize(value) {
  return String(value ?? "")
    .normalize("NFC")
    .replace(/\s+/g, " ")
    .trim();
}

function normalizeKey(value) {
  return normalize(value)
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/đ/g, "d");
}

function truncate(value, max = MAX_SUGGESTION_LENGTH) {
  return value.length > max ? `${value.slice(0, max)}…` : value;
}

export function uniqueStrings(values, limit = MAX_SUGGESTIONS) {
  const out = [];
  const seen = new Set();

  for (const value of Array.isArray(values) ? values : []) {
    const text = truncate(normalize(value));

    if (!text) continue;

    const key = normalizeKey(text);

    if (seen.has(key)) continue;

    seen.add(key);
    out.push(text);

    if (out.length >= limit) break;
  }

  return out;
}

export function createSuggestionResult({
  source,
  suggestions,
  extra = {},
}) {
  const clean = uniqueStrings(suggestions, MAX_SUGGESTIONS);

  return {
    suggestions: clean,
    journal: [
      journalEntry("suggestions", {
        source: String(source || "unknown"),
        count: clean.length,
        suggestions_preview: clean.slice(0, 3),
        ...extra,
      }),
    ],
  };
}