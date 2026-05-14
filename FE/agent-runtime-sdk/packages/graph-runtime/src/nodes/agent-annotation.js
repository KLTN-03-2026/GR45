import { RuntimeSignalsSchema } from "@fe-agent/shared-zod-schemas";
import { Annotation } from "@langchain/langgraph";

export const AgentAnnotation = Annotation.Root({
  sessionId: Annotation(),
  correlationId: Annotation(),
  messages: Annotation({
    reducer: (previousMessages, nextMessages) => nextMessages ?? [],
    default: () => [],
  }),
  signals: Annotation({
    reducer: (previousSignals, patch) =>
      RuntimeSignalsSchema.parse({ ...previousSignals, ...(patch ?? {}) }),
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
      nextPendingCalls ?? [],
    default: () => [],
  }),
  completedToolCalls: Annotation({
    reducer: (previousIds, nextIds) => previousIds.concat(nextIds ?? []),
    default: () => [],
  }),
  toolResults: Annotation({
    reducer: (previousResults, nextResults) =>
      previousResults.concat(nextResults ?? []),
    default: () => [],
  }),
  ragContext: Annotation({
    reducer: (unusedPreviousRagContext, nextRagContext) =>
      nextRagContext ?? [],
    default: () => [],
  }),
  observations: Annotation({
    reducer: (previousObservations, nextObservations) =>
      previousObservations.concat(nextObservations ?? []),
    default: () => [],
  }),
  finalAnswer: Annotation({
    reducer: (unusedPreviousFinalAnswer, nextFinalAnswer) =>
      nextFinalAnswer,
    default: () => undefined,
  }),
  suggestions: Annotation({
    reducer: (unusedPreviousSuggestions, nextSuggestions) =>
      nextSuggestions ?? [],
    default: () => [],
  }),
  journal: Annotation({
    reducer: (previousJournal, nextJournal) =>
      previousJournal.concat(nextJournal ?? []),
    default: () => [],
  }),
  // Per-request AbortSignal for cancellation; not serialized.
  _signal: Annotation({
    reducer: (_, next) => next,
    default: () => undefined,
  }),
});
