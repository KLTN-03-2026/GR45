const MAX_UNWRAP_DEPTH = 4;
const MAX_OUTPUT_CHARS = 20_000;

function asText(value) {
  return String(value ?? "").trim().slice(0, MAX_OUTPUT_CHARS);
}

function extractJsonCandidate(text) {
  const raw = asText(text);
  if (!raw) return "";

  const objectStart = raw.indexOf("{");
  const objectEnd = raw.lastIndexOf("}");
  const arrayStart = raw.indexOf("[");
  const arrayEnd = raw.lastIndexOf("]");

  const objectCandidate =
    objectStart >= 0 && objectEnd > objectStart
      ? raw.slice(objectStart, objectEnd + 1)
      : "";

  const arrayCandidate =
    arrayStart >= 0 && arrayEnd > arrayStart
      ? raw.slice(arrayStart, arrayEnd + 1)
      : "";

  if (objectCandidate && arrayCandidate) {
    return objectStart < arrayStart ? objectCandidate : arrayCandidate;
  }

  return objectCandidate || arrayCandidate || raw;
}

function tryParseJson(value) {
  try {
    return JSON.parse(extractJsonCandidate(value));
  } catch {
    return null;
  }
}

function pickReplyText(payload) {
  if (!payload || typeof payload !== "object") return "";

  const candidates = [
    payload.reply,
    payload.answer,
    payload.response,
    payload.text,
    payload.message,
    payload.content,
  ];

  for (const candidate of candidates) {
    if (typeof candidate === "string" && candidate.trim()) {
      return candidate.trim();
    }

    if (candidate && typeof candidate === "object") {
      return JSON.stringify(candidate);
    }
  }

  return "";
}

export function unwrapSyntheticReplyEnvelope(rawLanguageModelOutput) {
  let current = asText(rawLanguageModelOutput);

  for (let depth = 0; depth < MAX_UNWRAP_DEPTH; depth += 1) {
    const parsed = tryParseJson(current);

    if (!parsed) break;

    const reply = pickReplyText(parsed);

    if (!reply || reply === current) break;

    current = reply;
  }

  return current;
}
