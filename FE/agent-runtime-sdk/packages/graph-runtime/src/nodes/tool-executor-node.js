import { journalEntry } from "../journal.js";
import { valueOr } from "../value.js";

export function createToolExecutorNode(graphDependencies) {
  return async function toolExecutorGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "tool_executor",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    const requestHeaders = valueOr(await graphDependencies.getHeaders?.(), {});
    const toolExecutionContext = {
      correlationId: graphState.correlationId,
      sessionId: graphState.sessionId,
      headers: requestHeaders,
      bus: graphDependencies.bus,
      signal: valueOr(graphState._signal, graphDependencies.signal),
      confirmToolCall: graphDependencies.confirmToolCall,
    };

    const toolExecutionResults = await graphDependencies.tools.executeMany(
      graphState.pendingToolCalls,
      toolExecutionContext
    );

    return {
      toolResults: toolExecutionResults,
      completedToolCalls: toolExecutionResults.map(
        (executionResult) => executionResult.callId
      ),
      pendingToolCalls: [],
      journal: [
        journalEntry("tool_executor", {
          ok: toolExecutionResults.every((executionResult) => executionResult.ok),
        }),
      ],
    };
  };
}
