import { RuntimeSignalsSchema } from "@fe-agent/shared-zod-schemas";
import { Annotation } from "@langchain/langgraph";
import { valueOr } from "../value.js";

export const AgentAnnotation = Annotation.Root({
  sessionId: Annotation(),
  correlationId: Annotation(),
  messages: Annotation({
    reducer: (previousMessages, nextMessages) => valueOr(nextMessages, []),
    default: () => [],
  }),
  signals: Annotation({
    reducer: (previousSignals, patch) =>
      RuntimeSignalsSchema.parse({ ...previousSignals, ...valueOr(patch, {}) }),
    default: () => RuntimeSignalsSchema.parse({}),
  }),
  plan: Annotation({
    reducer: (unusedPreviousPlan, nextPlan) => nextPlan,
    default: () => undefined,
  }),
  activeStepIndex: Annotation({
    reducer: (unusedPreviousActiveStepIndex, nextActiveStepIndex) =>
      nextActiveStepIndex,
    default: () => undefined,
  }),
  pendingToolCalls: Annotation({
    reducer: (unusedPreviousPendingCalls, nextPendingCalls) =>
      valueOr(nextPendingCalls, []),
    default: () => [],
  }),
  completedToolCalls: Annotation({
    reducer: (previousIds, nextIds) => previousIds.concat(valueOr(nextIds, [])),
    default: () => [],
  }),
  toolResults: Annotation({
    reducer: (previousResults, nextResults) =>
      previousResults.concat(valueOr(nextResults, [])),
    default: () => [],
  }),
  ragContext: Annotation({
    reducer: (unusedPreviousRagContext, nextRagContext) =>
      valueOr(nextRagContext, []),
    default: () => [],
  }),
  observations: Annotation({
    reducer: (previousObservations, nextObservations) =>
      previousObservations.concat(valueOr(nextObservations, [])),
    default: () => [],
  }),
  finalAnswer: Annotation({
    reducer: (unusedPreviousFinalAnswer, nextFinalAnswer) =>
      nextFinalAnswer,
    default: () => undefined,
  }),
  journal: Annotation({
    reducer: (previousJournal, nextJournal) =>
      previousJournal.concat(valueOr(nextJournal, [])),
    default: () => [],
  }),
  // Per-request AbortSignal for cancellation; not serialized.
  _signal: Annotation({
    reducer: (_, next) => next,
    default: () => undefined,
  }),
});
