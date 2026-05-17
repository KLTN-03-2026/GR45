/**
 * Compact tool summary cho admin debug — không lộ payload đầy đủ, đủ để soi flow.
 */

function inferClarificationFlag(row) {
  const data = row?.data;
  if (!data || typeof data !== "object") return false;
  if (data.clarification_needed === true) return true;
  const inner = data.data;
  return Boolean(
    inner && typeof inner === "object" && inner.clarification_needed === true,
  );
}

export function summarizeToolResults(toolResults) {
  const rows = Array.isArray(toolResults) ? toolResults : [];
  return rows.map((row) => ({
    toolName: String(row?.toolName ?? "").trim(),
    ok: row?.ok === true,
    clarification_needed: inferClarificationFlag(row),
  }));
}
