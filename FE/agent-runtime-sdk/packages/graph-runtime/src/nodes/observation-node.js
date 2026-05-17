import { journalEntry } from "../journal.js";
import {
  anyTrue,
  isNumber,
  isObject,
  isString,
  textOrEmpty,
  valueOr,
} from "../value.js";

import { shouldPreferPdfAfterBarrenTools } from "../intent/intent-classifier.js";

function getLastUserContent(messages = []) {
  return textOrEmpty(
    [...messages].reverse().find((m) => m?.role === "user")?.content,
  );
}

/**
 * Tool đã cho kết quả vận hành đủ để không cần RAG fallback (vé/chuyến/live chat…).
 * clarification_needed / count 0 → không coi là đã trả lời.
 */
function toolRowGivesOperationalPayload(row) {
  if (!row?.ok) return false;
  const data = row.data;
  if (!isObject(data)) return false;
  if (data.clarification_needed === true) return false;

  const name = textOrEmpty(row.toolName).trim();

  if (name === "support_create_support_session") {
    const inner = isObject(data.data) ? data.data : data;
    return Boolean(valueOr(inner?.public_id, data.public_id));
  }

  if (["search_routes", "search_trips"].includes(name)) {
    if (data.success !== true) return false;
    if (isNumber(data.count)) return data.count > 0;
    const rows = Array.isArray(data.data)
      ? data.data
      : Array.isArray(data.rows)
        ? data.rows
        : Array.isArray(data.items)
          ? data.items
          : [];
    return rows.length > 0;
  }

  if (data.login_success === true) return true;
  if (
    data.khach_hang &&
    isObject(data.khach_hang) &&
    data.khach_hang.id != null
  ) {
    return true;
  }
  if ([data.booking_confirmed === true, Boolean(data.ma_dat_ve)].some(Boolean)) return true;
  if (data.cancellation_confirmed === true) return true;

  return false;
}

export function createObservationNode(graphDependencies, runtimeConfiguration) {
  return async function observationGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "observation",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    const completedResultRows = graphState.toolResults.filter((toolResultRow) =>
      graphState.completedToolCalls.includes(toolResultRow.callId)
    );

    const anyToolFailed = completedResultRows.some(
      (toolResultRow) => !toolResultRow.ok
    );
    const needsConfirmation = completedResultRows.some(
      (toolResultRow) =>
        !toolResultRow.ok &&
        isString(toolResultRow.error) &&
        toolResultRow.error.startsWith("confirmation_required:")
    );
    const planConfidence = valueOr(graphState.plan?.confidence, 0.5);
    const plannerLoopCount = valueOr(graphState.signals.planner_loop, 0);

    const maxPlannerLoopsReached =
      plannerLoopCount >= runtimeConfiguration.maxPlannerLoops;

    const toolsPlannedButNoneRan =
      completedResultRows.length === 0 &&
      valueOr(graphState.plan?.toolCalls?.length, 0) > 0;

    const lastUser = getLastUserContent(graphState.messages);
    const corpusLikely = shouldPreferPdfAfterBarrenTools(
      lastUser,
      valueOr(graphDependencies.intentClassifierOptions, {}),
    );
    const operationalPayloadHit =
      completedResultRows.some(toolRowGivesOperationalPayload);
    const ragAlreadyFilled =
      Array.isArray(graphState.ragContext) && graphState.ragContext.length > 0;

    const ragFallbackAfterBarrenTools =
      corpusLikely &&
      !operationalPayloadHit &&
      !ragAlreadyFilled &&
      completedResultRows.length > 0;

    const observationSignalsPatch = {
      tool_fail_streak: anyToolFailed
        ? valueOr(graphState.signals.tool_fail_streak, 0) + 1
        : 0,
      needs_replan: Boolean(anyToolFailed && !needsConfirmation && !maxPlannerLoopsReached),
      // Only trigger RAG when there's nothing useful to replan with, or confidence is low.
      // Tool failures with a live replan path are handled by replanner, not RAG.
      rag_fallback: Boolean(
        anyTrue(
          planConfidence < 0.45,
          graphState.plan?.needs_rag_fallback,
          toolsPlannedButNoneRan && !anyToolFailed,
          ragFallbackAfterBarrenTools,
        ),
      ),
      replan_terminal: [needsConfirmation, maxPlannerLoopsReached].some(Boolean),
    };

    if (observationSignalsPatch.replan_terminal) {
      observationSignalsPatch.needs_replan = false;
    }

    return {
      signals: {
        ...graphState.signals,
        ...observationSignalsPatch,
      },
      observations: completedResultRows.map((toolResultRow) => ({
        ok: toolResultRow.ok,
        tool: toolResultRow.toolName,
        error: valueOr(toolResultRow.error, null),
      })),
      journal: [journalEntry("observation", observationSignalsPatch)],
    };
  };
}
