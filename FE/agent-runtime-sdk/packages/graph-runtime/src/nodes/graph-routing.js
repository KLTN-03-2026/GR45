import { shouldRouteStandardThroughPlanner } from "./intent-classifier.js";

function getLastUserContent(messages) {
  if (!Array.isArray(messages)) return "";
  const last = [...messages].reverse().find((m) => m?.role === "user");
  return String(last?.content ?? "");
}

/**
 * Fast path: skip planner for non-operational intents to save 1 LLM call.
 * - operational / risk_sensitive / long_form → planner (tool calls needed)
 * - policy_or_document (gồm cả câu dài >200 ký tự về PDF/KB) or needs_grounding → rag_retriever
 * - standard (greetings, simple questions) → synthesizer directly (1 LLM call total)
 * - standard + shouldRouteStandardThroughPlanner (mã vé, tra cứu…) → planner
 * - qaPdfOnly forces RAG first, including standard-looking PDF questions
 */
export function routeAfterIntentDetection(graphState, options = {}) {
  if (options.qaPdfOnly) {
    return "rag_retriever";
  }

  const signalsIntent = graphState.signals?.intent ?? "standard";
  const lastUserText = getLastUserContent(graphState.messages);
  const intent =
    signalsIntent === "standard" &&
    shouldRouteStandardThroughPlanner(
      lastUserText,
      options.intentClassifierOptions,
    )
      ? "operational"
      : signalsIntent;

  const needsGrounding = Boolean(graphState.signals?.needs_grounding);

  if (
    intent === "operational" ||
    intent === "risk_sensitive" ||
    intent === "long_form"
  ) {
    return "planner";
  }

  if (intent === "policy_or_document" || needsGrounding) {
    return "rag_retriever";
  }

  return "synthesizer";
}

export function routeAfterPlanner(graphState) {
  const plan = graphState.plan;
  if (!plan) return "synthesizer";
  const hasToolCalls =
    Array.isArray(plan.toolCalls) && plan.toolCalls.length > 0;
  /** Luôn chạy tool trước khi RAG khi planner đã khai báo toolCalls (tránh câu hỏi vận hành bị nuốt bởi PDF-only). */
  if (hasToolCalls) {
    return "tool_router";
  }
  if (plan.needs_rag_fallback || plan.needs_grounding === true) {
    return "rag_retriever";
  }
  return "synthesizer";
}

export function routeAfterObservation(graphState, runtimeConfiguration) {
  if (graphState.signals.replan_terminal) return "synthesizer";
  if (graphState.signals.rag_fallback) return "rag_retriever";
  const plannerLoopCount = graphState.signals.planner_loop ?? 0;
  if (
    graphState.signals.needs_replan &&
    plannerLoopCount < runtimeConfiguration.maxPlannerLoops
  )
    return "replanner";
  return "synthesizer";
}

export function routeAfterReplanner(graphState) {
  if (graphState.signals.replan_terminal) return "synthesizer";
  if (graphState.signals.rag_fallback) return "rag_retriever";
  return "planner";
}
