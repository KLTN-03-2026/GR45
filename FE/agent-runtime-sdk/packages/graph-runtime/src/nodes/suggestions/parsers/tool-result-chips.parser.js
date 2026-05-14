import { uniqueStrings } from "../create-suggestion-result.js";

function unwrapToolData(row) {
  if (!row || typeof row !== "object") return null;

  let data = row.data;

  for (let i = 0; i < 3; i += 1) {
    if (!data || typeof data !== "object") break;
    if (Array.isArray(data.suggested_reply_chips_vi)) return data;
    data = data.data;
  }

  return null;
}

/** Chips gợi ý từ backend — kể cả payload clarification (`ok: false` + clarification_needed). */
function rowHasSuggestChips(row) {
  if (!row || typeof row !== "object") return false;
  if (row.error != null && String(row.error).trim() !== "") return false;
  const data = unwrapToolData(row);
  return (
    data &&
    typeof data === "object" &&
    Array.isArray(data.suggested_reply_chips_vi) &&
    data.suggested_reply_chips_vi.length > 0
  );
}

export function parseSuggestedReplyChipsFromToolResults(toolResults) {
  const rows = Array.isArray(toolResults) ? toolResults : [];

  for (let i = rows.length - 1; i >= 0; i -= 1) {
    const row = rows[i];
    if (!rowHasSuggestChips(row)) continue;

    const data = unwrapToolData(row);
    const chips = data?.suggested_reply_chips_vi;

    if (Array.isArray(chips)) {
      const clean = uniqueStrings(chips, 5);
      if (clean.length) return clean;
    }
  }

  return [];
}