import { journalEntry } from "../journal.js";
import { anyTrue, valueOr } from "../value.js";

export function createReplannerNode(graphDependencies, runtimeConfiguration) {
  return async function replannerGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "replanner",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    const replannerSignalsPatch = {
      rag_fallback: Boolean(
        anyTrue(
          graphState.observations.some(
            (observationRow) => observationRow.ok === false
          ),
          graphState.plan?.needs_rag_fallback,
        )
      ),
      needs_replan: false,
    };

    if (valueOr(graphState.signals.planner_loop, 0) >= runtimeConfiguration.maxPlannerLoops) {
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
