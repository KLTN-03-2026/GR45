import {
  RuntimeConfigSchema,
  RuntimeSignalsSchema,
} from "@fe-agent/shared-zod-schemas";
import {
  END,
  MemorySaver,
  START,
  StateGraph,
} from "@langchain/langgraph";

import { AgentAnnotation } from "./nodes/agent-annotation.js";
import {
  routeAfterIntentDetection,
  routeAfterObservation,
  routeAfterPlanner,
  routeAfterReplanner,
} from "./nodes/graph-routing.js";
import { createIntentDetectionNode } from "./nodes/intent-detection-node.js";
import { createLoadContextNode } from "./nodes/load-context-node.js";
import { createObservationNode } from "./nodes/observation-node.js";
import { createPlannerNodeHandler } from "./nodes/planner-node.js";
import { createPlannerRunnable } from "./planner/planner-runnable.js";
import { createRagRetrieverNode } from "./nodes/rag-retriever-node.js";
import { createReplannerNode } from "./nodes/replanner-node.js";
import { createSaveStateNode } from "./nodes/save-state-node.js";
import { createSynthesizerNode } from "./nodes/synthesizer-node.js";
import { createToolExecutorNode } from "./nodes/tool-executor-node.js";
import { createToolRouterNode } from "./nodes/tool-router-node.js";
import { resolveGraphRuntimeDependencies } from "./runtime-dependencies.js";
import { valueOr } from "./value.js";

export function compileAgentGraph(graphDependencies) {
  const runtimeConfiguration = RuntimeConfigSchema.parse(
    valueOr(graphDependencies.config, {})
  );
  const resolvedGraphDependencies =
    resolveGraphRuntimeDependencies(graphDependencies);

  const plannerRunnable = createPlannerRunnable({
    llm: resolvedGraphDependencies.llm,
    tools: resolvedGraphDependencies.tools,
    bus: resolvedGraphDependencies.bus,
    domainInstructions: resolvedGraphDependencies.domainInstructions,
    planPostProcessor: resolvedGraphDependencies.planPostProcessor,
    prePlannerHook: resolvedGraphDependencies.prePlannerHook,
    intentClassifierOptions: resolvedGraphDependencies.intentClassifierOptions,
  });

  const isQuestionAnswerPdfOnly = Boolean(resolvedGraphDependencies.qaPdfOnly);
  const useRestrictedAnswerSourcesOnly = Boolean(
    resolvedGraphDependencies.restrictedAnswerSources
  );

  const plannerGraphHandler = createPlannerNodeHandler({
    graphDependencies: resolvedGraphDependencies,
    plannerRunnable,
    isQuestionAnswerPdfOnly,
  });

  const graph = new StateGraph(AgentAnnotation)
    .addNode("load_context", createLoadContextNode(resolvedGraphDependencies))
    .addNode("intent_detection", createIntentDetectionNode(resolvedGraphDependencies))
    .addNode("planner", plannerGraphHandler)
    .addNode("tool_router", createToolRouterNode(resolvedGraphDependencies))
    .addNode("tool_executor", createToolExecutorNode(resolvedGraphDependencies))
    .addNode(
      "observation",
      createObservationNode(resolvedGraphDependencies, runtimeConfiguration)
    )
    .addNode(
      "replanner",
      createReplannerNode(resolvedGraphDependencies, runtimeConfiguration)
    )
    .addNode("rag_retriever", createRagRetrieverNode(resolvedGraphDependencies))
    .addNode(
      "synthesizer",
      createSynthesizerNode(
        resolvedGraphDependencies,
        isQuestionAnswerPdfOnly,
        useRestrictedAnswerSourcesOnly
      )
    )
    .addNode("save_state", createSaveStateNode(resolvedGraphDependencies))
    .addEdge(START, "load_context")
    .addEdge("load_context", "intent_detection")
    .addConditionalEdges(
      "intent_detection",
      (graphState) =>
        routeAfterIntentDetection(graphState, {
          qaPdfOnly: isQuestionAnswerPdfOnly,
          intentClassifierOptions:
            resolvedGraphDependencies.intentClassifierOptions,
        }),
      ["planner", "rag_retriever", "synthesizer"]
    )
    .addConditionalEdges(
      "planner",
      (graphState) => routeAfterPlanner(graphState),
      ["tool_router", "rag_retriever", "synthesizer"]
    )
    .addConditionalEdges(
      "tool_router",
      (graphState) =>
        graphState.pendingToolCalls.length ? "tool_executor" : "observation",
      ["tool_executor", "observation"]
    )
    .addEdge("tool_executor", "observation")
    .addConditionalEdges(
      "observation",
      (graphState) =>
        routeAfterObservation(graphState, runtimeConfiguration),
      ["replanner", "rag_retriever", "synthesizer"]
    )
    .addConditionalEdges(
      "replanner",
      (graphState) => routeAfterReplanner(graphState),
      ["planner", "rag_retriever", "synthesizer"]
    )
    .addEdge("rag_retriever", "synthesizer")
    .addEdge("synthesizer", "save_state")
    .addEdge("save_state", END);

  const explicitCheckpointer = graphDependencies.checkpointer;
  const resolvedCheckpointer =
    [false, null].includes(explicitCheckpointer)
      ? undefined
      : valueOr(explicitCheckpointer, new MemorySaver());

  return graph.compile({ checkpointer: resolvedCheckpointer });
}

export function createInitialAgentState(statePatch) {
  const correlationIdentifier = valueOr(statePatch.correlationId, crypto.randomUUID());
  return {
    sessionId: statePatch.sessionId,
    correlationId: correlationIdentifier,
    messages: valueOr(statePatch.messages, []),
    signals: RuntimeSignalsSchema.parse(valueOr(statePatch.signals, {})),
    plan: statePatch.plan,
    activeStepIndex: statePatch.activeStepIndex,
    pendingToolCalls: valueOr(statePatch.pendingToolCalls, []),
    completedToolCalls: valueOr(statePatch.completedToolCalls, []),
    toolResults: valueOr(statePatch.toolResults, []),
    ragContext: valueOr(statePatch.ragContext, []),
    observations: valueOr(statePatch.observations, []),
    finalAnswer: statePatch.finalAnswer,
    journal: valueOr(statePatch.journal, []),
    _signal: valueOr(statePatch._signal, undefined),
  };
}
