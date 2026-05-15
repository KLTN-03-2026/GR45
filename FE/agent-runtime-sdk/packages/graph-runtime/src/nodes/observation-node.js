import { journalEntry } from "@fe-agent/observability";

/**
 * @param graphDependencies
 * @param {{ maxPlannerLoops: number }} runtimeConfiguration
 */
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
        typeof toolResultRow.error === "string" &&
        toolResultRow.error.startsWith("confirmation_required:")
    );
    const planConfidence = graphState.plan?.confidence ?? 0.5;
    const plannerLoopCount = graphState.signals.planner_loop ?? 0;

    const maxPlannerLoopsReached =
      plannerLoopCount >= runtimeConfiguration.maxPlannerLoops;

    const toolsPlannedButNoneRan =
      completedResultRows.length === 0 &&
      (graphState.plan?.toolCalls?.length ?? 0) > 0;

    const observationSignalsPatch = {
      tool_fail_streak: anyToolFailed
        ? (graphState.signals.tool_fail_streak ?? 0) + 1
        : 0,
      needs_replan: Boolean(anyToolFailed && !needsConfirmation && !maxPlannerLoopsReached),
      // Only trigger RAG when there's nothing useful to replan with, or confidence is low.
      // Tool failures with a live replan path are handled by replanner, not RAG.
      rag_fallback: Boolean(
        planConfidence < 0.45 ||
          graphState.plan?.needs_rag_fallback ||
          (toolsPlannedButNoneRan && !anyToolFailed)
      ),
      replan_terminal: needsConfirmation || maxPlannerLoopsReached,
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
        error: toolResultRow.error ?? null,
      })),
      journal: [journalEntry("observation", observationSignalsPatch)],
    };
  };
}
