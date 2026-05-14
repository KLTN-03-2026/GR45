import { journalEntry } from "@fe-agent/observability";

/** @param graphDependencies @param runtimeConfiguration */
export function createReplannerNode(graphDependencies, runtimeConfiguration) {
  return async function replannerGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "replanner",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    const replannerSignalsPatch = {
      rag_fallback: Boolean(
        graphState.observations.some(
          (observationRow) => observationRow.ok === false
        ) || graphState.plan?.needs_rag_fallback
      ),
      needs_replan: false,
    };

    if ((graphState.signals.planner_loop ?? 0) >= runtimeConfiguration.maxPlannerLoops) {
      replannerSignalsPatch.replan_terminal = true;
    }

    return {
      signals: {
        ...graphState.signals,
        ...replannerSignalsPatch,
      },
      journal: [journalEntry("replanner", replannerSignalsPatch)],
    };
  };
}
