import {
  createLangChainLlmDeps,
  createLangChainRagEmbedderDeps,
} from "@fe-agent/core";
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
import { createPlannerRunnable } from "./nodes/planner-runnable.js";
import { createRagRetrieverNode } from "./nodes/rag-retriever-node.js";
import { createReplannerNode } from "./nodes/replanner-node.js";
import { createSaveStateNode } from "./nodes/save-state-node.js";
import { createSuggestionGeneratorNode } from "./nodes/suggestion-generator-node.js";
import { createSynthesizerNode } from "./nodes/synthesizer-node.js";
import { createToolExecutorNode } from "./nodes/tool-executor-node.js";
import { createToolRouterNode } from "./nodes/tool-router-node.js";

export function compileAgentGraph(graphDependencies) {
  const runtimeConfiguration = RuntimeConfigSchema.parse(
    graphDependencies.config ?? {}
  );
  const llm =
    graphDependencies.llm ??
    createLangChainLlmDeps({
      ollamaModel: graphDependencies.ollamaModel,
      ollamaChatModel: graphDependencies.ollamaChatModel,
      ollamaBaseUrl: graphDependencies.ollamaBaseUrl,
      ollamaKeepAlive: graphDependencies.ollamaKeepAlive,
      ollamaNumCtx: graphDependencies.ollamaNumCtx,
      groqKey: graphDependencies.groqKey,
      groqApiKey: graphDependencies.groqApiKey,
      groqModel: graphDependencies.groqModel,
      groqBaseUrl: graphDependencies.groqBaseUrl,
      fetchImpl: graphDependencies.fetchImpl,
      signal: graphDependencies.signal,
    });
  const rag =
    graphDependencies.rag && !graphDependencies.rag.embedder
      ? {
          ...graphDependencies.rag,
          embedder: createLangChainRagEmbedderDeps({
            ollamaEmbedModel: graphDependencies.ollamaEmbedModel,
            ollamaBaseUrl: graphDependencies.ollamaBaseUrl,
            huggingFaceKey: graphDependencies.huggingFaceKey,
            huggingFaceApiKey: graphDependencies.huggingFaceApiKey,
            huggingFaceModel: graphDependencies.huggingFaceModel,
            huggingFaceEmbedModel: graphDependencies.huggingFaceEmbedModel,
            huggingFaceEndpointUrl: graphDependencies.huggingFaceEndpointUrl,
            huggingFaceProvider: graphDependencies.huggingFaceProvider,
            fetchImpl: graphDependencies.fetchImpl,
            signal: graphDependencies.signal,
          }),
        }
      : graphDependencies.rag;
  const resolvedGraphDependencies = {
    ...graphDependencies,
    llm,
    rag,
  };

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
    .addNode(
      "suggestion_generator",
      createSuggestionGeneratorNode(resolvedGraphDependencies)
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
    .addEdge("synthesizer", "suggestion_generator")
    .addEdge("suggestion_generator", "save_state")
    .addEdge("save_state", END);

  const explicitCheckpointer = graphDependencies.checkpointer;
  const resolvedCheckpointer =
    explicitCheckpointer === false || explicitCheckpointer === null
      ? undefined
      : explicitCheckpointer ?? new MemorySaver();

  return graph.compile({ checkpointer: resolvedCheckpointer });
}

export function createInitialAgentState(statePatch) {
  const correlationIdentifier = statePatch.correlationId ?? crypto.randomUUID();
  return {
    sessionId: statePatch.sessionId,
    correlationId: correlationIdentifier,
    messages: statePatch.messages ?? [],
    signals: RuntimeSignalsSchema.parse(statePatch.signals ?? {}),
    plan: statePatch.plan,
    activeStepIndex: statePatch.activeStepIndex,
    pendingToolCalls: statePatch.pendingToolCalls ?? [],
    completedToolCalls: statePatch.completedToolCalls ?? [],
    toolResults: statePatch.toolResults ?? [],
    ragContext: statePatch.ragContext ?? [],
    observations: statePatch.observations ?? [],
    finalAnswer: statePatch.finalAnswer,
    suggestions: statePatch.suggestions ?? [],
    journal: statePatch.journal ?? [],
    _signal: statePatch._signal ?? undefined,
  };
}
