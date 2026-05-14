import { journalEntry } from "@fe-agent/observability";

const DEFAULT_MAX_SAVED_MESSAGES = 30;

function safeRandomId() {
  return globalThis.crypto?.randomUUID
    ? globalThis.crypto.randomUUID()
    : `msg-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export function createSaveStateNode(graphDependencies) {
  return async function saveStateGraphNode(graphState) {
    graphDependencies.bus?.emit("stage", {
      stage: "save_state",
      status: "enter",
      correlationId: graphState.correlationId,
    });

    if (!graphDependencies.sessions) {
      return {
        journal: [journalEntry("save_state", { skipped: true })],
      };
    }

    const assistantMessage = {
      id: safeRandomId(),
      role: "assistant",
      content: String(graphState.finalAnswer ?? ""),
      meta: {
        suggestions: Array.isArray(graphState.suggestions)
          ? graphState.suggestions.slice(0, 5)
          : [],
      },
    };

    const maxMessages =
      graphDependencies.config?.maxSavedMessages ?? DEFAULT_MAX_SAVED_MESSAGES;

    const messages = [...(graphState.messages ?? []), assistantMessage].slice(
      -maxMessages,
    );

    const snapshot = {
      sessionId: graphState.sessionId,
      updatedAt: new Date().toISOString(),
      messages,
      workflow: {
        lastCorrelationId: graphState.correlationId,
        planGoal: graphState.plan?.goal,
        lastToolCalls: Array.isArray(graphState.plan?.toolCalls)
          ? graphState.plan.toolCalls.map((call) => call.toolName).slice(0, 10)
          : [],
      },
    };

    try {
      await graphDependencies.sessions.save(snapshot);

      return {
        messages,
        journal: [journalEntry("save_state", { persisted: true })],
      };
    } catch (error) {
      return {
        messages,
        journal: [
          journalEntry("save_state", {
            persisted: false,
            error: String(error?.message ?? error).slice(0, 300),
          }),
        ],
      };
    } finally {
      graphDependencies.bus?.emit("stage", {
        stage: "save_state",
        status: "exit",
        correlationId: graphState.correlationId,
      });
    }
  };
}