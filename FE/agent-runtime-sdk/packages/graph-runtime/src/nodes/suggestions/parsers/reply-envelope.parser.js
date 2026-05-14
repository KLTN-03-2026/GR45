import { uniqueStrings } from "../create-suggestion-result.js";

function extractJsonObject(text) {
  const raw = String(text ?? "").trim();
  const start = raw.indexOf("{");
  const end = raw.lastIndexOf("}");

  if (start < 0 || end <= start) return null;

  try {
    return JSON.parse(raw.slice(start, end + 1));
  } catch {
    return null;
  }
}

function optionText(row) {
  if (typeof row === "string") return row.trim();
  if (!row || typeof row !== "object") return "";

  return String(row.value ?? row.label ?? row.text ?? row.title ?? "").trim();
}

export function parseSuggestionLabelsFromReplyEnvelope(finalAnswer) {
  const parsed = extractJsonObject(finalAnswer);
  const options = parsed?.options;

  if (!Array.isArray(options)) return [];

  return uniqueStrings(options.map(optionText), 5);
}